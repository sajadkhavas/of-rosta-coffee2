<?php

namespace App\Services\Identity;

use App\Contracts\OtpSender;
use App\Exceptions\ApiDomainException;
use App\Jobs\SendOtpCode;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\IranMobile;
use App\Support\RequestFingerprint;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OtpService
{
    public function __construct(
        private readonly OtpCodeHasher $hasher,
        private readonly OtpSender $sender,
        private readonly RoleService $roles,
        private readonly AuditRecorder $audit,
    ) {}

    public function request(
        string $mobile,
        string $purpose,
        Request $request,
    ): OtpChallenge {
        if (! $this->sender->isAvailable()) {
            throw new ApiDomainException(
                'sms.unavailable',
                'سرویس ارسال کد تأیید فعال نیست.',
                503,
            );
        }

        $normalizedMobile = IranMobile::normalize($mobile);
        $lockName = 'otp:request:'.hash('sha256', $normalizedMobile.'|'.$purpose);

        try {
            /** @var OtpChallenge $challenge */
            $challenge = Cache::lock($lockName, 10)->block(3, function () use (
                $normalizedMobile,
                $purpose,
                $request,
            ): OtpChallenge {
                return DB::transaction(function () use (
                    $normalizedMobile,
                    $purpose,
                    $request,
                ): OtpChallenge {
                    $active = OtpChallenge::query()
                        ->where('mobile', $normalizedMobile)
                        ->where('purpose', $purpose)
                        ->whereNull('consumed_at')
                        ->whereNull('locked_at')
                        ->where('expires_at', '>', now())
                        ->latest('created_at')
                        ->lockForUpdate()
                        ->first();

                    if ($active && $active->resend_available_at->isFuture()) {
                        $retryAfter = max(
                            1,
                            (int) ceil(now()->diffInSeconds(
                                $active->resend_available_at,
                                false,
                            )),
                        );

                        throw new ApiDomainException(
                            'auth.otp_resend_too_soon',
                            'برای ارسال دوباره کد کمی صبر کنید.',
                            429,
                            headers: ['Retry-After' => (string) $retryAfter],
                        );
                    }

                    OtpChallenge::query()
                        ->where('mobile', $normalizedMobile)
                        ->where('purpose', $purpose)
                        ->whereNull('consumed_at')
                        ->whereNull('locked_at')
                        ->update([
                            'locked_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $challengeId = (string) Str::ulid();
                    $code = $this->hasher->generate();
                    $ttl = (int) config('rosta.otp.ttl_seconds', 120);
                    $resendAfter = (int) config(
                        'rosta.otp.resend_after_seconds',
                        60,
                    );

                    $challenge = new OtpChallenge;
                    $challenge->id = $challengeId;
                    $challenge->fill([
                        'mobile' => $normalizedMobile,
                        'purpose' => $purpose,
                        'code_digest' => $this->hasher->digest(
                            $challengeId,
                            $normalizedMobile,
                            $purpose,
                            $code,
                        ),
                        'attempts' => 0,
                        'max_attempts' => (int) config(
                            'rosta.otp.max_attempts',
                            5,
                        ),
                        'expires_at' => now()->addSeconds($ttl),
                        'resend_available_at' => now()->addSeconds($resendAfter),
                        'requested_ip_hash' => RequestFingerprint::ip($request),
                        'user_agent_hash' => RequestFingerprint::userAgent($request),
                    ]);
                    $challenge->save();

                    $this->audit->record(
                        'identity.otp.requested',
                        auditable: $challenge,
                        metadata: [
                            'purpose' => $purpose,
                            'mobile_suffix' => substr($normalizedMobile, -4),
                        ],
                        request: $request,
                    );

                    dispatch(SendOtpCode::forPlaintextCode(
                        $challengeId,
                        $normalizedMobile,
                        $purpose,
                        $code,
                    ))->afterCommit();

                    return $challenge;
                }, 3);
            });
        } catch (LockTimeoutException) {
            throw new ApiDomainException(
                'request.rate_limited',
                'درخواست مشابه در حال پردازش است.',
                429,
                headers: ['Retry-After' => '1'],
            );
        }

        return $challenge;
    }

    public function verify(
        string $challengeId,
        string $code,
        Request $request,
    ): User {
        $normalizedCode = trim(IranMobile::normalizeDigits($code));
        $lockName = 'otp:verify:'.hash('sha256', $challengeId);

        try {
            /** @var array{state: string, user?: User, remaining?: int} $result */
            $result = Cache::lock($lockName, 10)->block(3, function () use (
                $challengeId,
                $normalizedCode,
                $request,
            ): array {
                return DB::transaction(function () use (
                    $challengeId,
                    $normalizedCode,
                    $request,
                ): array {
                    $challenge = OtpChallenge::query()
                        ->whereKey($challengeId)
                        ->lockForUpdate()
                        ->first();

                    if (! $challenge || $challenge->isConsumed()) {
                        return ['state' => 'invalid'];
                    }

                    if ($challenge->isExpired()) {
                        $challenge->forceFill(['locked_at' => now()])->save();

                        return ['state' => 'expired'];
                    }

                    if ($challenge->isLocked()) {
                        return ['state' => 'locked'];
                    }

                    if (! $this->hasher->verify(
                        $challenge->code_digest,
                        $challenge->id,
                        $challenge->mobile,
                        $challenge->purpose,
                        $normalizedCode,
                    )) {
                        $attempts = $challenge->attempts + 1;
                        $locked = $attempts >= $challenge->max_attempts;
                        $challenge->forceFill([
                            'attempts' => $attempts,
                            'locked_at' => $locked ? now() : null,
                        ])->save();

                        return [
                            'state' => $locked ? 'locked' : 'invalid',
                            'remaining' => max(0, $challenge->max_attempts - $attempts),
                        ];
                    }

                    $challenge->forceFill(['consumed_at' => now()])->save();

                    $user = User::query()->firstOrCreate(
                        ['mobile' => $challenge->mobile],
                        ['mobile_verified_at' => now()],
                    );

                    if ($user->mobile_verified_at === null) {
                        $user->forceFill(['mobile_verified_at' => now()])->save();
                    }

                    $this->roles->ensureCustomer($user);
                    $user->load('roleAssignments');

                    $this->audit->record(
                        'identity.otp.verified',
                        actor: $user,
                        auditable: $user,
                        metadata: ['purpose' => $challenge->purpose],
                        request: $request,
                    );

                    return [
                        'state' => 'verified',
                        'user' => $user,
                    ];
                }, 3);
            });
        } catch (LockTimeoutException) {
            throw new ApiDomainException(
                'request.rate_limited',
                'تأیید این کد هم‌اکنون در حال پردازش است.',
                429,
                headers: ['Retry-After' => '1'],
            );
        }

        if ($result['state'] === 'expired') {
            throw new ApiDomainException(
                'auth.otp_expired',
                'کد تأیید منقضی شده است.',
                422,
                ['code' => ['کد تأیید منقضی شده است.']],
            );
        }

        if ($result['state'] === 'locked') {
            throw new ApiDomainException(
                'auth.otp_locked',
                'تعداد تلاش‌های مجاز تمام شده است.',
                429,
                headers: ['Retry-After' => (string) config('rosta.otp.ttl_seconds', 120)],
            );
        }

        if ($result['state'] !== 'verified' || ! isset($result['user'])) {
            throw new ApiDomainException(
                'auth.otp_invalid',
                'کد تأیید معتبر نیست.',
                422,
                ['code' => ['کد تأیید معتبر نیست.']],
            );
        }

        return $result['user'];
    }
}
