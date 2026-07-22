<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\ContentAuthor;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoastBatch;
use App\Models\Roastery;
use App\Models\SeoRedirect;
use App\Models\ShippingRule;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Content\ContentBlockValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $customer = User::factory()->create([
                'mobile' => '09123456789',
                'name' => 'سجاد',
                'email' => 'sajad@example.test',
            ]);

            $administrator = User::factory()->create([
                'mobile' => '09120000001',
                'name' => 'مدیر رستا',
                'email' => 'admin@rosta.test',
            ]);

            $owner = User::factory()->create([
                'mobile' => '09120000002',
                'name' => 'مدیر روستری نمونه',
                'email' => 'seller@rosta.test',
            ]);

            foreach ([[$customer, Role::Customer], [$administrator, Role::Administrator]] as [$user, $role]) {
                UserRole::query()->create([
                    'user_id' => $user->id,
                    'role' => $role->value,
                    'scope_type' => 'global',
                    'scope_id' => 'global',
                ]);
            }

            $roastery = Roastery::query()->create([
                'name' => 'روستری رستا',
                'slug' => 'rosta-roastery',
                'city' => 'کرج',
                'description' => 'روستری نمونه برای محیط توسعه و پذیرش.',
                'shipping_policy' => 'ارسال دانه کامل قهوه به سراسر ایران.',
                'status' => 'verified',
                'verified_at' => now(),
                'preparation_min_hours' => 12,
                'preparation_max_hours' => 24,
            ]);

            UserRole::query()->create([
                'user_id' => $owner->id,
                'role' => Role::RoasteryOwner->value,
                'scope_type' => 'roastery',
                'scope_id' => $roastery->id,
            ]);

            ShippingRule::query()->create([
                'roastery_id' => $roastery->id,
                'province' => null,
                'city' => null,
                'base_cost' => 450_000,
                'free_over' => 15_000_000,
                'priority' => 0,
                'is_active' => true,
            ]);

            Coupon::query()->create([
                'roastery_id' => $roastery->id,
                'code' => 'ROSTA10',
                'type' => 'percentage',
                'value' => 1000,
                'minimum_subtotal' => 5_000_000,
                'maximum_discount' => 1_500_000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'max_redemptions' => 1000,
                'redemption_count' => 0,
                'is_active' => true,
            ]);

            $origin = Origin::query()->create([
                'name' => 'اتیوپی سیدامو',
                'slug' => 'ethiopia-sidamo',
                'country_code' => 'ETH',
            ]);

            $product = Product::query()->create([
                'roastery_id' => $roastery->id,
                'origin_id' => $origin->id,
                'name' => 'اتیوپی سیدامو دانه کامل',
                'slug' => 'ethiopia-sidamo-whole-bean',
                'short_description' => 'قهوه دانه کامل با نت‌های گلی و مرکباتی.',
                'description' => 'یک محصول کامل نمونه برای تست کاتالوگ، سبد و پنل‌ها.',
                'processing_method' => 'washed',
                'roast_level' => 'light',
                'arabica_percentage' => 100,
                'tasting_notes' => ['یاس', 'برگاموت', 'لیمو'],
                'brewing_suggestions' => ['V60', 'کمکس'],
                'seo_title' => 'خرید قهوه اتیوپی سیدامو دانه کامل',
                'seo_description' => 'قهوه اتیوپی سیدامو فقط به‌صورت دانه کامل.',
                'status' => 'published',
                'published_at' => now(),
            ]);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => 'ROSTA-ETH-250',
                'weight_grams' => 250,
                'price' => 6_900_000,
                'currency' => 'IRR',
                'is_active' => true,
                'stock_on_hand' => 30,
                'stock_reserved' => 0,
            ]);

            $batch = RoastBatch::query()->create([
                'product_id' => $product->id,
                'batch_code' => 'DEV-001',
                'roasted_at' => now()->subDays(2),
                'available_from' => now()->subDay(),
                'is_active' => true,
            ]);

            StockLedgerEntry::query()->create([
                'variant_id' => $variant->id,
                'roast_batch_id' => $batch->id,
                'actor_id' => $owner->id,
                'delta' => 30,
                'balance_after' => 30,
                'reason' => 'opening',
                'idempotency_key' => 'seed:opening:ROSTA-ETH-250',
                'metadata' => ['source' => 'development-seeder'],
            ]);

            $author = ContentAuthor::query()->create([
                'name' => 'تحریریه رستا',
                'slug' => 'rosta-editorial',
                'bio' => 'تیم محتوای رستا با تمرکز بر انتخاب دانه کامل، تازگی و روش دم‌آوری.',
                'credentials' => ['تحقیق محصول', 'راهنمای انتخاب قهوه'],
                'is_active' => true,
            ]);

            $body = app(ContentBlockValidator::class)->validate([
                [
                    'type' => 'paragraph',
                    'text' => 'قهوه تازه‌رست فقط به معنی نزدیک‌بودن تاریخ رست نیست؛ روش دم‌آوری و زمان استراحت دانه نیز روی نتیجه فنجان اثر می‌گذارد.',
                ],
                [
                    'type' => 'heading',
                    'level' => 2,
                    'text' => 'بازه مناسب مصرف',
                ],
                [
                    'type' => 'list',
                    'style' => 'unordered',
                    'items' => [
                        'برای روش‌های دمی معمولاً چند روز پس از رست مناسب است.',
                        'برای اسپرسو زمان استراحت بیشتری لازم است.',
                        'تاریخ رست باید کنار موجودی واقعی نمایش داده شود.',
                    ],
                ],
                [
                    'type' => 'product_grid',
                    'product_slugs' => [$product->slug],
                ],
            ]);

            $entry = ContentEntry::query()->create([
                'author_id' => $author->id,
                'reviewed_by' => $administrator->id,
                'type' => 'guide',
                'title' => 'راهنمای انتخاب قهوه تازه‌رست',
                'slug' => 'fresh-roast-basics',
                'canonical_path' => '/guides/fresh-roast-basics',
                'excerpt' => 'چطور تاریخ رست، زمان استراحت و روش دم‌آوری را برای انتخاب دانه کامل بررسی کنیم.',
                'body' => $body,
                'status' => 'published',
                'published_at' => now(),
                'seo_title' => 'راهنمای انتخاب قهوه تازه‌رست | رستا',
                'seo_description' => 'راهنمای عملی بررسی تاریخ رست، زمان استراحت و انتخاب دانه کامل مناسب روش دم‌آوری.',
                'robots_index' => true,
                'robots_follow' => true,
                'schema_type' => 'BlogPosting',
                'keywords' => ['قهوه تازه رست', 'تاریخ رست', 'دانه کامل'],
                'content_hash' => app(ContentBlockValidator::class)->hash($body),
            ]);

            $entry->relations()->createMany([
                [
                    'relation_type' => 'recommends',
                    'target_type' => 'product',
                    'target_key' => $product->slug,
                    'anchor_text' => $product->name,
                    'position' => 0,
                ],
                [
                    'relation_type' => 'mentions',
                    'target_type' => 'roastery',
                    'target_key' => $roastery->slug,
                    'anchor_text' => $roastery->name,
                    'position' => 1,
                ],
            ]);

            SeoRedirect::query()->create([
                'source_path' => '/articles/fresh-roast',
                'destination_path' => $entry->canonical_path,
                'status_code' => 301,
                'is_active' => true,
                'hits' => 0,
                'created_by' => $administrator->id,
            ]);
        });
    }
}
