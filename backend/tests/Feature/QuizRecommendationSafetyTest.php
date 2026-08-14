<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QuizAttempt;
use App\Models\QuizVersion;
use App\Models\Roastery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class QuizRecommendationSafetyTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_guest_submission_is_idempotent_private_and_recomputes_live_availability(): void
    {
        [, $variant] = $this->catalogProduct();
        $token = str_repeat('g', 64);
        $payload = ['answers' => $this->answers(), 'guest_token' => $token, 'idempotency_key' => 'quiz-submit-0001'];

        $first = $this->postJson('/api/v1/quiz/attempts', $payload)
            ->assertCreated()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonCount(1, 'data.recommendations.items')
            ->json('data');
        $second = $this->postJson('/api/v1/quiz/attempts', $payload)->assertCreated()->json('data');

        $this->assertSame($first['attempt']['id'], $second['attempt']['id']);
        $this->assertSame($first['recommendations']['items'][0]['score'], $second['recommendations']['items'][0]['score']);
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertNotSame($token, QuizAttempt::query()->firstOrFail()->guest_token_hash);

        $variant->forceFill(['stock_on_hand' => 0, 'stock_reserved' => 0])->save();
        $this->withHeader('X-Quiz-Guest-Token', $token)
            ->getJson('/api/v1/quiz/attempts/'.$first['attempt']['id'].'/recommendations')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.stale_safe', true);

        $this->withHeader('X-Quiz-Guest-Token', str_repeat('x', 64))
            ->getJson('/api/v1/quiz/attempts/'.$first['attempt']['id'])
            ->assertNotFound();
    }

    public function test_guest_sync_is_explicit_idempotent_owned_and_deletable(): void
    {
        $token = str_repeat('s', 64);
        $created = $this->postJson('/api/v1/quiz/attempts', ['answers' => $this->answers(), 'guest_token' => $token, 'idempotency_key' => 'quiz-sync-seed'])
            ->assertCreated()->json('data.attempt');

        $customer = User::factory()->create();
        $this->authenticateWithRole($customer, Role::Customer);
        $endpoint = '/api/v1/quiz/attempts/'.$created['id'].'/sync';
        $sync = ['guest_token' => $token, 'idempotency_key' => 'explicit-consent-0001'];
        $this->postJson($endpoint, $sync)->assertOk()->assertJsonPath('data.synced', true);
        $this->postJson($endpoint, $sync)->assertOk()->assertJsonPath('data.synced', true);
        $this->assertNull(QuizAttempt::query()->findOrFail($created['id'])->guest_token_hash);

        $this->getJson('/api/v1/me/quiz-attempts')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertJsonPath('data.items.0.id', $created['id']);

        $other = User::factory()->create();
        $this->authenticateWithRole($other, Role::Customer);
        $this->postJson($endpoint, $sync)->assertNotFound();

        $this->authenticateWithRole($customer, Role::Customer);
        $this->deleteJson('/api/v1/me/quiz-attempts/'.$created['id'])->assertOk();
        $this->assertDatabaseMissing('quiz_attempts', ['id' => $created['id']]);
    }

    public function test_version_change_keeps_old_attempt_semantics_and_published_version_is_immutable(): void
    {
        $token = str_repeat('v', 64);
        $attempt = $this->postJson('/api/v1/quiz/attempts', ['answers' => $this->answers(), 'guest_token' => $token, 'idempotency_key' => 'version-seed-0001'])
            ->assertCreated()->json('data.attempt');
        $v1 = QuizVersion::query()->where('status', 'published')->firstOrFail();
        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);
        $draft = $this->postJson('/api/v1/admin/quiz/versions', [
            'title' => 'نسخه دوم', 'questions' => $v1->questions, 'scoring_profile' => $v1->scoring_profile, 'recommendation_rules' => $v1->recommendation_rules,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');
        $this->postJson('/api/v1/admin/quiz/versions/'.$draft['id'].'/publish')->assertOk()->assertJsonPath('data.status', 'published');
        $this->patchJson('/api/v1/admin/quiz/versions/'.$draft['id'], [
            'title' => 'نباید تغییر کند', 'questions' => $v1->questions, 'scoring_profile' => $v1->scoring_profile, 'recommendation_rules' => $v1->recommendation_rules,
        ])->assertConflict()->assertJsonPath('error.code', 'quiz.version_immutable');

        $this->withHeader('X-Quiz-Guest-Token', $token)
            ->getJson('/api/v1/quiz/attempts/'.$attempt['id'])
            ->assertOk()->assertJsonPath('data.version', 1);
        $this->getJson('/api/v1/quiz/current')->assertOk()->assertJsonPath('data.version', 2);
    }

    private function answers(): array
    {
        return ['brew_method' => 'espresso', 'roast' => 'medium', 'adventure' => 'safe', 'flavors' => ['chocolate'], 'experience' => 'beginner'];
    }

    /** @return array{Product, ProductVariant} */
    private function catalogProduct(): array
    {
        $origin = Origin::query()->create(['name' => 'اتیوپی', 'slug' => 'quiz-ethiopia', 'country_code' => 'ETH']);
        $roastery = Roastery::query()->create(['name' => 'Quiz Roastery', 'slug' => 'quiz-roastery', 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        $product = Product::query()->create(['roastery_id' => $roastery->id, 'origin_id' => $origin->id, 'name' => 'Quiz Coffee', 'slug' => 'quiz-coffee', 'description' => '', 'processing_method' => 'washed', 'roast_level' => 'medium', 'arabica_percentage' => 100, 'tasting_notes' => ['شکلات'], 'brewing_suggestions' => [], 'status' => 'published', 'published_at' => now()]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'QUIZ-250', 'weight_grams' => 250, 'price' => 1_000_000, 'currency' => 'IRR', 'is_active' => true, 'stock_on_hand' => 5, 'stock_reserved' => 0]);
        return [$product, $variant];
    }
}
