<?php

namespace App\Services\Quiz;

use App\Exceptions\ApiDomainException;
use App\Models\Product;
use App\Models\QuizAttempt;
use App\Models\QuizVersion;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QuizService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function publishedVersion(): QuizVersion
    {
        return QuizVersion::query()->where('status', 'published')->whereNotNull('published_at')->orderByDesc('version')->firstOrFail();
    }

    public function publicVersion(QuizVersion $version): array
    {
        return ['id' => $version->id, 'version' => $version->version, 'title' => $version->title, 'questions' => $version->questions, 'checksum' => $version->checksum];
    }

    public function createGuestAttempt(array $answers, string $guestToken, string $idempotencyKey, Request $request): QuizAttempt
    {
        $version = $this->publishedVersion();
        $tokenHash = $this->hashToken($guestToken);
        $submissionHash = hash('sha256', $guestToken.'|'.$idempotencyKey.'|'.$version->id);
        $normalized = $this->validateAnswers($version, $answers);

        return DB::transaction(function () use ($version, $normalized, $guestToken, $tokenHash, $submissionHash, $request): QuizAttempt {
            $attempt = QuizAttempt::query()->firstOrCreate(
                ['submission_key_hash' => $submissionHash],
                [
                    'quiz_version_id' => $version->id,
                    'guest_token_hash' => $tokenHash,
                    'answers' => $normalized,
                    'score_profile' => $this->buildScoreProfile($version, $normalized),
                    'completed_at' => now(),
                ],
            );
            $this->assertGuestToken($attempt, $guestToken);
            if ($attempt->wasRecentlyCreated) {
                $this->audit->record('quiz.attempt.created', auditable: $attempt, metadata: ['quiz_version' => $version->version], request: $request);
            }

            return $attempt->load('version');
        }, 3);
    }

    public function guestAttempt(string $id, string $guestToken): QuizAttempt
    {
        $attempt = QuizAttempt::query()->with('version')->findOrFail($id);
        if ($attempt->user_id !== null) {
            throw new ApiDomainException('quiz.attempt_not_guest', 'این نتیجه به حساب کاربری منتقل شده است.', 409);
        }
        $this->assertGuestToken($attempt, $guestToken);

        return $attempt;
    }

    public function ownedAttempt(User $user, string $id): QuizAttempt
    {
        return QuizAttempt::query()->with('version')->whereKey($id)->where('user_id', $user->id)->firstOrFail();
    }

    public function sync(User $user, string $id, string $guestToken, string $idempotencyKey, Request $request): QuizAttempt
    {
        return DB::transaction(function () use ($user, $id, $guestToken, $idempotencyKey, $request): QuizAttempt {
            $attempt = QuizAttempt::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($attempt->user_id !== null) {
                if ($attempt->user_id !== $user->id) {
                    abort(404);
                }

                return $attempt->load('version');
            }
            $this->assertGuestToken($attempt, $guestToken);
            $syncHash = hash('sha256', $user->id.'|'.$idempotencyKey.'|'.$attempt->id);
            $attempt->forceFill(['user_id' => $user->id, 'guest_token_hash' => null, 'sync_key_hash' => $syncHash, 'synced_at' => now()])->save();
            $this->audit->record('quiz.attempt.synced', actor: $user, auditable: $attempt, metadata: ['quiz_version_id' => $attempt->quiz_version_id], request: $request);

            return $attempt->load('version');
        }, 3);
    }

    public function deleteGuest(string $id, string $guestToken, Request $request): void
    {
        $attempt = $this->guestAttempt($id, $guestToken);
        $versionId = $attempt->quiz_version_id;
        $attempt->delete();
        $this->audit->record('quiz.attempt.deleted', metadata: ['attempt_id' => $id, 'quiz_version_id' => $versionId, 'owner' => 'guest'], request: $request);
    }

    public function deleteOwned(User $user, string $id, Request $request): void
    {
        $attempt = $this->ownedAttempt($user, $id);
        $versionId = $attempt->quiz_version_id;
        $attempt->delete();
        $this->audit->record('quiz.attempt.deleted', actor: $user, metadata: ['attempt_id' => $id, 'quiz_version_id' => $versionId, 'owner' => 'account'], request: $request);
    }

    public function recommendations(QuizAttempt $attempt, int $limit = 8): array
    {
        $attempt->loadMissing('version');
        $products = Product::query()->published()
            ->whereHas('variants', static fn (Builder $q): Builder => $q->where('is_active', true)->whereColumn('stock_on_hand', '>', 'stock_reserved'))
            ->with(['origin', 'primaryImage', 'latestRoastBatch', 'roastery.logo', 'roastery.cover', 'variants' => static fn ($q) => $q->where('is_active', true)->orderBy('weight_grams')])
            ->get();
        $scored = $products->map(function (Product $product) use ($attempt): array {
            [$score, $reasons] = $this->score($attempt->version, $attempt->answers, $product);

            return ['product' => $product, 'score' => $score, 'reasons' => $reasons, 'available' => $product->variants->sum(fn ($v): int => $v->availableQuantity())];
        })->sort(static fn (array $a, array $b): int => [$b['score'], $b['available'], $a['product']->id] <=> [$a['score'], $a['available'], $b['product']->id])
            ->take(max(1, min(12, $limit)))->values()->all();

        return $scored;
    }

    public function adminCreate(User $admin, array $input, Request $request): QuizVersion
    {
        $versionNumber = ((int) QuizVersion::query()->max('version')) + 1;
        $payload = $this->validatedVersionPayload($input);
        $version = QuizVersion::query()->create([...$payload, 'version' => $versionNumber, 'status' => 'draft', 'checksum' => $this->checksum($payload), 'created_by' => $admin->id]);
        $this->audit->record('quiz.version.created', actor: $admin, auditable: $version, metadata: ['version' => $versionNumber], request: $request);

        return $version;
    }

    public function adminUpdate(User $admin, QuizVersion $version, array $input, Request $request): QuizVersion
    {
        if ($version->status !== 'draft') {
            throw new ApiDomainException('quiz.version_immutable', 'نسخه منتشرشده یا آرشیوشده قابل ویرایش نیست.', 409);
        }
        $payload = $this->validatedVersionPayload($input);
        $version->forceFill([...$payload, 'checksum' => $this->checksum($payload)])->save();
        $this->audit->record('quiz.version.updated', actor: $admin, auditable: $version, metadata: ['version' => $version->version], request: $request);

        return $version;
    }

    public function publish(User $admin, QuizVersion $version, Request $request): QuizVersion
    {
        if ($version->status !== 'draft') {
            throw new ApiDomainException('quiz.version_not_draft', 'فقط نسخه draft قابل انتشار است.', 409);
        }

        return DB::transaction(function () use ($admin, $version, $request): QuizVersion {
            QuizVersion::query()->where('status', 'published')->lockForUpdate()->update(['status' => 'archived', 'archived_at' => now()]);
            $locked = QuizVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => 'published', 'published_by' => $admin->id, 'published_at' => now(), 'archived_at' => null])->save();
            $this->audit->record('quiz.version.published', actor: $admin, auditable: $locked, metadata: ['version' => $locked->version, 'checksum' => $locked->checksum], request: $request);

            return $locked;
        }, 3);
    }

    public function archive(User $admin, QuizVersion $version, Request $request): QuizVersion
    {
        if ($version->status === 'archived') {
            return $version;
        }
        if ($version->status === 'published' && QuizVersion::query()->where('status', 'published')->count() <= 1) {
            throw new ApiDomainException('quiz.last_published_version', 'تا زمان انتشار نسخه جایگزین، نسخه فعال قابل آرشیو نیست.', 409);
        }
        $version->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
        $this->audit->record('quiz.version.archived', actor: $admin, auditable: $version, metadata: ['version' => $version->version], request: $request);

        return $version;
    }

    public function preview(QuizVersion $version, array $answers): array
    {
        $normalized = $this->validateAnswers($version, $answers);
        $shadow = new QuizAttempt(['answers' => $normalized]);
        $shadow->setRelation('version', $version);

        return $this->recommendations($shadow, 8);
    }

    private function validateAnswers(QuizVersion $version, array $answers): array
    {
        $normalized = [];
        foreach ($version->questions as $question) {
            $key = (string) ($question['key'] ?? '');
            $options = collect($question['options'] ?? [])->pluck('value')->map(fn ($v): string => (string) $v)->all();
            $value = $answers[$key] ?? null;
            if (($question['type'] ?? 'single') === 'multi') {
                if (! is_array($value) || $value === [] || count($value) > (int) ($question['max_selections'] ?? 3)) {
                    throw new ApiDomainException('quiz.answers_invalid', 'پاسخ‌های کوییز معتبر نیست.', 422);
                }
                $values = array_values(array_unique(array_map('strval', $value)));
                if (array_diff($values, $options) !== []) {
                    throw new ApiDomainException('quiz.answers_invalid', 'پاسخ‌های کوییز معتبر نیست.', 422);
                }
                $normalized[$key] = $values;
            } else {
                if (! is_string($value) || ! in_array($value, $options, true)) {
                    throw new ApiDomainException('quiz.answers_invalid', 'پاسخ‌های کوییز معتبر نیست.', 422);
                }
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function buildScoreProfile(QuizVersion $version, array $answers): array
    {
        return ['quiz_version' => $version->version, 'quiz_checksum' => $version->checksum, 'criteria' => $answers];
    }

    private function score(QuizVersion $version, array $answers, Product $product): array
    {
        $weights = $version->scoring_profile['weights'] ?? [];
        $rules = $version->recommendation_rules;
        $score = 0;
        $reasons = [];
        $roast = (string) ($answers['roast'] ?? 'recommend');
        if ($roast !== 'recommend' && $product->roast_level->value === $roast) {
            $score += (int) ($weights['roast_exact'] ?? 0);
            $reasons[] = 'سطح رست با انتخاب شما هماهنگ است.';
        }
        $brew = (string) ($answers['brew_method'] ?? '');
        if (in_array($product->roast_level->value, $rules['brew_roast'][$brew] ?? [], true)) {
            $score += (int) ($weights['brew_roast'] ?? 0);
            $reasons[] = 'برای روش دم‌آوری انتخابی شما سازگاری خوبی دارد.';
        }
        $adventure = (string) ($answers['adventure'] ?? '');
        if (in_array($product->processing_method->value, $rules['adventure_process'][$adventure] ?? [], true)) {
            $score += (int) ($weights['process'] ?? 0);
            $reasons[] = 'روش فرآوری با میزان تمایل شما به طعم‌های متفاوت هماهنگ است.';
        }
        foreach (($answers['flavors'] ?? []) as $flavor) {
            foreach (($version->scoring_profile['flavor_note_map'][$flavor] ?? []) as $target) {
                if (collect($product->tasting_notes ?? [])->contains(fn ($note): bool => str_contains((string) $note, (string) $target) || str_contains((string) $target, (string) $note))) {
                    $score += (int) ($weights['flavor_note'] ?? 0);
                    $reasons[] = 'یادداشت‌های طعمی با یکی از انتخاب‌های شما هم‌پوشانی دارد.';
                    break;
                }
            }
        }
        $experience = (string) ($answers['experience'] ?? '');
        $experienceRule = $rules['experience'][$experience] ?? [];
        if (($experienceRule['roast_level'] ?? null) === $product->roast_level->value || (isset($experienceRule['processing_not']) && $experienceRule['processing_not'] !== $product->processing_method->value)) {
            $score += (int) ($weights['experience'] ?? 0);
            $reasons[] = 'با سطح تجربه‌ای که انتخاب کرده‌اید هم‌خوان است.';
        }

        return [$score, array_values(array_unique($reasons))];
    }

    private function validatedVersionPayload(array $input): array
    {
        foreach (['title', 'questions', 'scoring_profile', 'recommendation_rules'] as $key) {
            if (! array_key_exists($key, $input)) {
                throw new ApiDomainException('quiz.version_invalid', 'تعریف نسخه کوییز کامل نیست.', 422);
            }
        }
        if (! is_string($input['title']) || trim($input['title']) === '' || ! is_array($input['questions']) || count($input['questions']) < 1 || count($input['questions']) > 20 || ! is_array($input['scoring_profile']) || ! is_array($input['recommendation_rules'])) {
            throw new ApiDomainException('quiz.version_invalid', 'تعریف نسخه کوییز معتبر نیست.', 422);
        }
        $keys = [];
        foreach ($input['questions'] as $question) {
            if (! is_array($question) || ! isset($question['key'], $question['title'], $question['type'], $question['options']) || ! in_array($question['type'], ['single', 'multi'], true) || ! is_array($question['options']) || $question['options'] === []) {
                throw new ApiDomainException('quiz.version_invalid', 'ساختار سؤال معتبر نیست.', 422);
            }
            $keys[] = (string) $question['key'];
        }
        if (count($keys) !== count(array_unique($keys))) {
            throw new ApiDomainException('quiz.version_invalid', 'کلید سؤال‌ها باید یکتا باشد.', 422);
        }

        return ['title' => trim($input['title']), 'questions' => $input['questions'], 'scoring_profile' => $input['scoring_profile'], 'recommendation_rules' => $input['recommendation_rules']];
    }

    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function hashToken(string $token): string
    {
        if (strlen($token) < 32 || strlen($token) > 200) {
            throw new ApiDomainException('quiz.guest_token_invalid', 'شناسه مهمان معتبر نیست.', 422);
        }

return hash('sha256', $token);
    }

    private function assertGuestToken(QuizAttempt $attempt, string $token): void
    {
        if ($attempt->guest_token_hash === null || ! hash_equals($attempt->guest_token_hash, $this->hashToken($token))) {
            abort(404);
        }
    }
}
