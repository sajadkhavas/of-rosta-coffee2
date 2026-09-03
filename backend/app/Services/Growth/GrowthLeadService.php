<?php

namespace App\Services\Growth;

use App\Exceptions\ApiDomainException;
use App\Models\GrowthLead;
use App\Models\GrowthPartner;
use App\Models\Order;
use App\Models\PartnerAttribution;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\IranMobile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class GrowthLeadService
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array{
     *     type:string,
     *     name?:string|null,
     *     mobile?:string|null,
     *     email?:string|null,
     *     company_name?:string|null,
     *     notes?:string|null,
     *     meta?:array<string,mixed>|null
     * } $payload
     */
    public function claim(GrowthPartner $partner, array $payload, ?User $actor = null): GrowthLead
    {
        $type = trim((string) $payload['type']);
        if (in_array($type, GrowthLead::types(), true) === false) {
            throw new ApiDomainException(
                'growth.lead_type_invalid',
                'نوع سرنخ معتبر نیست.',
                422,
                ['type' => ['نوع سرنخ معتبر نیست.']],
            );
        }

        $mobile = $this->normalizeMobile($payload['mobile'] ?? null);
        $email = $this->normalizeEmail($payload['email'] ?? null);

        if ($mobile === null && $email === null) {
            throw new ApiDomainException(
                'growth.lead_identity_required',
                'برای ثبت سرنخ، موبایل یا ایمیل معتبر لازم است.',
                422,
            );
        }

        $dedupeHash = $this->dedupeHash($type, $mobile, $email);

        try {
            return DB::transaction(function () use ($partner, $payload, $type, $mobile, $email, $dedupeHash, $actor): GrowthLead {
                $lockedPartner = GrowthPartner::query()
                    ->with('user')
                    ->lockForUpdate()
                    ->find($partner->id);

                if ($lockedPartner === null || $lockedPartner->isActive() === false) {
                    throw new ApiDomainException(
                        'growth.partner_not_active',
                        'همکار رشد برای ثبت سرنخ فعال نیست.',
                        409,
                    );
                }

                $this->assertNotSelfReferral($lockedPartner, $mobile, $email);

                $existing = GrowthLead::query()
                    ->where('dedupe_hash', $dedupeHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof GrowthLead) {
                    return $this->resolveExistingLead($existing, $lockedPartner);
                }

                $lead = GrowthLead::query()->create([
                    'partner_id' => $lockedPartner->id,
                    'type' => $type,
                    'status' => GrowthLead::STATUS_LEAD,
                    'dedupe_hash' => $dedupeHash,
                    'name' => $this->nullableTrimmed($payload['name'] ?? null),
                    'mobile' => $mobile,
                    'email' => $email,
                    'company_name' => $this->nullableTrimmed($payload['company_name'] ?? null),
                    'notes' => $this->nullableTrimmed($payload['notes'] ?? null),
                    'claimed_at' => now(),
                    'meta' => $this->safeLeadMeta($payload['meta'] ?? null),
                ]);

                $this->auditRecorder->record(
                    action: 'growth.lead.claimed',
                    actor: $actor,
                    auditable: $lead,
                    metadata: [
                        'partner_id' => (string) $lockedPartner->id,
                        'lead_id' => (string) $lead->id,
                        'lead_type' => $type,
                    ],
                );

                return $lead;
            });
        } catch (QueryException $exception) {
            $existing = GrowthLead::query()->where('dedupe_hash', $dedupeHash)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->resolveExistingLead($existing, $partner);
        }
    }

    /** @param array<string,mixed> $context */
    public function attribute(
        GrowthPartner $partner,
        string $subjectType,
        string $subjectId,
        ?GrowthLead $lead = null,
        ?User $actor = null,
        array $context = [],
    ): PartnerAttribution {
        $subjectType = trim($subjectType);
        $subjectId = trim($subjectId);

        if (in_array($subjectType, ['user', 'roastery', 'order'], true) === false || $subjectId === '') {
            throw new ApiDomainException(
                'growth.attribution_subject_invalid',
                'موضوع انتساب معتبر نیست.',
                422,
            );
        }

        if ($this->subjectExists($subjectType, $subjectId) === false) {
            throw new ApiDomainException(
                'growth.attribution_subject_not_found',
                'موضوع انتساب پیدا نشد.',
                404,
            );
        }

        try {
            return DB::transaction(function () use ($partner, $subjectType, $subjectId, $lead, $actor, $context): PartnerAttribution {
                $lockedPartner = GrowthPartner::query()->lockForUpdate()->find($partner->id);
                if ($lockedPartner === null || $lockedPartner->isActive() === false) {
                    throw new ApiDomainException(
                        'growth.partner_not_active',
                        'همکار رشد برای انتساب فعال نیست.',
                        409,
                    );
                }

                if ($subjectType === 'user' && hash_equals((string) $lockedPartner->user_id, $subjectId)) {
                    throw new ApiDomainException(
                        'growth.self_referral_forbidden',
                        'انتساب مستقیم حساب خود همکار رشد مجاز نیست.',
                        409,
                    );
                }

                if ($lead instanceof GrowthLead && $lead->partner_id !== $lockedPartner->id) {
                    throw new ApiDomainException(
                        'growth.lead_partner_mismatch',
                        'سرنخ متعلق به همکار رشد دیگری است.',
                        409,
                    );
                }

                $existing = PartnerAttribution::query()
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof PartnerAttribution) {
                    return $this->resolveExistingAttribution($existing, $lockedPartner);
                }

                $attribution = PartnerAttribution::query()->create([
                    'partner_id' => $lockedPartner->id,
                    'lead_id' => $lead?->id,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'source' => $lead instanceof GrowthLead ? 'lead' : 'direct',
                    'attributed_at' => now(),
                    'context' => $this->safeAttributionContext($context),
                ]);

                $this->auditRecorder->record(
                    action: 'growth.attribution.created',
                    actor: $actor,
                    auditable: $attribution,
                    metadata: [
                        'partner_id' => (string) $lockedPartner->id,
                        'attribution_id' => (string) $attribution->id,
                        'lead_id' => $lead?->id === null ? null : (string) $lead->id,
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                    ],
                );

                return $attribution;
            });
        } catch (QueryException $exception) {
            $existing = PartnerAttribution::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->resolveExistingAttribution($existing, $partner);
        }
    }

    private function resolveExistingLead(GrowthLead $existing, GrowthPartner $partner): GrowthLead
    {
        if ($existing->partner_id === $partner->id) {
            return $existing;
        }

        throw new ApiDomainException(
            'growth.lead_already_claimed',
            'این سرنخ قبلاً به همکار رشد دیگری منتسب شده است.',
            409,
        );
    }

    private function resolveExistingAttribution(PartnerAttribution $existing, GrowthPartner $partner): PartnerAttribution
    {
        if ($existing->partner_id === $partner->id) {
            return $existing;
        }

        throw new ApiDomainException(
            'growth.attribution_already_claimed',
            'این موضوع قبلاً به همکار رشد دیگری منتسب شده است.',
            409,
        );
    }

    private function assertNotSelfReferral(GrowthPartner $partner, ?string $mobile, ?string $email): void
    {
        $partnerUser = $partner->user;
        if (! $partnerUser instanceof User) {
            return;
        }

        $partnerMobile = IranMobile::normalize((string) ($partnerUser->mobile ?? ''));
        if ($mobile !== null && IranMobile::isValid($partnerMobile) && hash_equals($partnerMobile, $mobile)) {
            throw new ApiDomainException(
                'growth.self_referral_forbidden',
                'ثبت خودارجاعی به عنوان سرنخ مجاز نیست.',
                409,
            );
        }

        $partnerEmail = $this->normalizeEmailValue($partnerUser->email);
        if ($email !== null && $partnerEmail !== null && hash_equals($partnerEmail, $email)) {
            throw new ApiDomainException(
                'growth.self_referral_forbidden',
                'ثبت خودارجاعی به عنوان سرنخ مجاز نیست.',
                409,
            );
        }
    }

    private function normalizeMobile(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = IranMobile::normalize($raw);
        if (IranMobile::isValid($normalized) === false) {
            throw new ApiDomainException(
                'growth.lead_mobile_invalid',
                'شماره موبایل سرنخ معتبر نیست.',
                422,
                ['mobile' => ['شماره موبایل معتبر نیست.']],
            );
        }

        return $normalized;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = $this->normalizeEmailValue($raw);
        if ($normalized === null) {
            throw new ApiDomainException(
                'growth.lead_email_invalid',
                'ایمیل سرنخ معتبر نیست.',
                422,
                ['email' => ['ایمیل معتبر نیست.']],
            );
        }

        return $normalized;
    }

    private function normalizeEmailValue(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return $normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? $normalized
            : null;
    }

    private function dedupeHash(string $type, ?string $mobile, ?string $email): string
    {
        $identity = $mobile !== null ? 'mobile:'.$mobile : 'email:'.$email;

        return hash('sha256', 'growth-lead-v1|'.$type.'|'.$identity);
    }

    private function subjectExists(string $subjectType, string $subjectId): bool
    {
        return match ($subjectType) {
            'user' => User::query()->whereKey($subjectId)->exists(),
            'roastery' => Roastery::query()->whereKey($subjectId)->exists(),
            'order' => Order::query()->whereKey($subjectId)->exists(),
            default => false,
        };
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    /** @return array<string, scalar> */
    private function safeLeadMeta(mixed $meta): array
    {
        if (is_array($meta) === false) {
            return [];
        }

        $safe = [];
        foreach (['campaign', 'medium', 'placement', 'landing_path'] as $key) {
            if (array_key_exists($key, $meta) === false || $meta[$key] === null || is_scalar($meta[$key]) === false) {
                continue;
            }

            $value = $meta[$key];
            $safe[$key] = is_string($value) ? mb_substr(trim($value), 0, 191) : $value;
        }

        return $safe;
    }

    /** @param array<string,mixed> $context @return array<string, scalar> */
    private function safeAttributionContext(array $context): array
    {
        return $this->safeLeadMeta($context);
    }
}
