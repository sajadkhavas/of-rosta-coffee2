<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->string('status', 24)->index();
            $table->string('title', 160);
            $table->json('questions');
            $table->json('scoring_profile');
            $table->json('recommendation_rules');
            $table->char('checksum', 64)->unique();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('quiz_version_id')->constrained('quiz_versions')->restrictOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->char('guest_token_hash', 64)->nullable()->index();
            $table->char('submission_key_hash', 64)->unique();
            $table->char('sync_key_hash', 64)->nullable()->unique();
            $table->json('answers');
            $table->json('score_profile');
            $table->timestamp('completed_at')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('review_replies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->unique()->constrained('reviews')->cascadeOnDelete();
            $table->foreignUlid('roastery_id')->constrained('roasteries')->restrictOnDelete()->index();
            $table->foreignUlid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->string('status', 24)->default('visible')->index();
            $table->foreignUlid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('review_reply_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('reply_id')->constrained('review_replies')->cascadeOnDelete()->index();
            $table->foreignUlid('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('previous_status', 24);
            $table->timestamp('created_at')->index();
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32)->index();
            $table->string('evidence', 500)->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->foreignUlid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('resolution_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        $questions = [
            ['key' => 'brew_method', 'type' => 'single', 'title' => 'با چه دستگاهی قهوه درست می‌کنی؟', 'required' => true, 'options' => [
                ['value' => 'espresso', 'label' => 'اسپرسوساز خانگی'], ['value' => 'moka', 'label' => 'موکاپات'],
                ['value' => 'french_press', 'label' => 'فرنچ‌پرس'], ['value' => 'pour_over', 'label' => 'V60 یا دریپ'],
                ['value' => 'cold_brew', 'label' => 'کلدبرو'], ['value' => 'unknown', 'label' => 'هنوز مشخص نیست'],
            ]],
            ['key' => 'roast', 'type' => 'single', 'title' => 'چه سطح رستی را ترجیح می‌دهی؟', 'required' => true, 'options' => [
                ['value' => 'light', 'label' => 'روشن'], ['value' => 'medium', 'label' => 'متوسط'],
                ['value' => 'dark', 'label' => 'تیره'], ['value' => 'recommend', 'label' => 'پیشنهاد بده'],
            ]],
            ['key' => 'adventure', 'type' => 'single', 'title' => 'چقدر اهل طعم‌های متفاوت هستی؟', 'required' => true, 'options' => [
                ['value' => 'safe', 'label' => 'کلاسیک و قابل‌اعتماد'], ['value' => 'balanced', 'label' => 'متعادل'],
                ['value' => 'adventurous', 'label' => 'متفاوت و ماجراجویانه'],
            ]],
            ['key' => 'flavors', 'type' => 'multi', 'title' => 'کدام طعم‌ها بیشتر جذبت می‌کنند؟', 'required' => true, 'max_selections' => 3, 'options' => [
                ['value' => 'fruity', 'label' => 'میوه‌ای و ترش'], ['value' => 'chocolate', 'label' => 'شکلاتی و کارامل'],
                ['value' => 'floral', 'label' => 'گلی و عطری'], ['value' => 'nutty', 'label' => 'آجیلی و خاکی'],
                ['value' => 'citrus', 'label' => 'مرکباتی'], ['value' => 'sweet', 'label' => 'شیرین و عسلی'],
            ]],
            ['key' => 'experience', 'type' => 'single', 'title' => 'تجربه‌ات با قهوه تخصصی چقدر است؟', 'required' => true, 'options' => [
                ['value' => 'beginner', 'label' => 'تازه شروع کرده‌ام'], ['value' => 'some', 'label' => 'کمی تجربه دارم'],
                ['value' => 'pro', 'label' => 'حرفه‌ای هستم'],
            ]],
        ];
        $scoring = [
            'weights' => ['roast_exact' => 4, 'brew_roast' => 2, 'process' => 2, 'flavor_note' => 2, 'experience' => 1],
            'flavor_note_map' => [
                'fruity' => ['توت', 'لیمو', 'پرتقال', 'هلو', 'بری'], 'chocolate' => ['شکلات', 'کارامل', 'کاکائو'],
                'floral' => ['یاس', 'گل', 'عطری'], 'nutty' => ['بادام', 'فندق', 'آجیلی', 'ادویه'],
                'citrus' => ['لیمو', 'پرتقال', 'گریپ', 'برگاموت'], 'sweet' => ['عسل', 'کارامل', 'شیرین'],
            ],
        ];
        $rules = [
            'brew_roast' => ['espresso' => ['medium', 'dark'], 'moka' => ['medium', 'dark'], 'pour_over' => ['light', 'medium'], 'cold_brew' => ['light', 'medium']],
            'adventure_process' => ['safe' => ['washed'], 'adventurous' => ['natural', 'honey', 'other']],
            'experience' => ['beginner' => ['roast_level' => 'medium'], 'pro' => ['processing_not' => 'washed']],
        ];
        $checksum = hash('sha256', json_encode([$questions, $scoring, $rules], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $now = now();
        DB::table('quiz_versions')->insert([
            'id' => (string) Str::ulid(), 'version' => 1, 'status' => 'published', 'title' => 'کوییز سلیقه قهوه رستا',
            'questions' => json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'scoring_profile' => json_encode($scoring, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'recommendation_rules' => json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'checksum' => $checksum, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_reply_revisions');
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_versions');
    }
};
