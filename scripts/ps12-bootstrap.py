from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    content = target.read_text(encoding='utf-8')
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, found {count}: {old[:80]!r}')
    target.write_text(content.replace(old, new, 1), encoding='utf-8')


write('backend/app/Enums/CafeStatus.php', r'''<?php

namespace App\Enums;

enum CafeStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
''')

write('backend/app/Models/Cafe.php', r'''<?php

namespace App\Models;

use App\Enums\CafeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Cafe extends Model
{
    use HasUlids;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'status',
        'city',
        'address',
        'latitude',
        'longitude',
        'phone',
        'website_url',
        'instagram_handle',
        'description',
        'opening_hours',
        'amenities',
        'verified_at',
        'reviewed_by_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => CafeStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'opening_hours' => 'array',
            'amenities' => 'array',
            'verified_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'review_note' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CafeMembership::class);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === CafeStatus::Verified && $this->verified_at !== null;
    }
}
''')

write('backend/app/Models/CafeMembership.php', r'''<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CafeMembership extends Model
{
    use HasUlids;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';

    protected $fillable = [
        'cafe_id',
        'user_id',
        'role',
        'is_active',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
''')

write('backend/app/Models/WholesalePriceTier.php', r'''<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WholesalePriceTier extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_variant_id',
        'min_weight_grams',
        'unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'unit_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
''')

write('backend/database/migrations/2026_09_05_000001_create_cafes_and_wholesale_pricing.php', r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('city', 120)->index();
            $table->string('address', 1000);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('instagram_handle', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('amenities')->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->foreignUlid('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'city', 'updated_at'], 'cafes_directory_idx');
            $table->index(['status', 'latitude', 'longitude'], 'cafes_geo_idx');
        });

        Schema::create('cafe_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('cafe_id')->constrained('cafes')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cafe_id', 'user_id']);
            $table->index(['user_id', 'is_active', 'cafe_id'], 'cafe_membership_access_idx');
        });

        Schema::create('wholesale_price_tiers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('min_weight_grams');
            $table->unsignedBigInteger('unit_price');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_variant_id', 'min_weight_grams'], 'wholesale_variant_weight_unique');
            $table->index(['product_variant_id', 'is_active', 'min_weight_grams'], 'wholesale_resolve_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_price_tiers');
        Schema::dropIfExists('cafe_memberships');
        Schema::dropIfExists('cafes');
    }
};
''')

write('backend/app/Services/B2B/WholesalePricingService.php', r'''<?php

namespace App\Services\B2B;

use App\Enums\CafeStatus;
use App\Exceptions\ApiDomainException;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WholesalePriceTier;

final class WholesalePricingService
{
    private const int MAX_WEIGHT_GRAMS = 10_000_000;

    /**
     * @return array{unit_price:int,snapshot:array<string,mixed>}
     */
    public function resolve(?User $user, ProductVariant $variant, int $quantity): array
    {
        if ($quantity < 1 || $variant->weight_grams < 1) {
            throw new ApiDomainException('b2b.quantity_invalid', 'مقدار سفارش برای قیمت‌گذاری عمده معتبر نیست.', 422);
        }

        if ($quantity > intdiv(self::MAX_WEIGHT_GRAMS, (int) $variant->weight_grams)) {
            throw new ApiDomainException('b2b.weight_out_of_range', 'وزن سفارش از محدوده قیمت‌گذاری عمده خارج است.', 409);
        }

        $totalWeight = (int) $variant->weight_grams * $quantity;
        $cafe = $user instanceof User ? $this->eligibleCafe($user) : null;
        $tier = null;

        if ($cafe instanceof Cafe) {
            $tiers = $variant->relationLoaded('wholesaleTiers')
                ? $variant->wholesaleTiers
                : $variant->wholesaleTiers()->where('is_active', true)->get();

            $tier = $tiers
                ->filter(static fn (WholesalePriceTier $candidate): bool => $candidate->is_active && $candidate->min_weight_grams <= $totalWeight)
                ->sortByDesc('min_weight_grams')
                ->first();
        }

        $unitPrice = $tier instanceof WholesalePriceTier ? (int) $tier->unit_price : (int) $variant->price;
        $snapshot = [
            'version' => 'ps12-wholesale-tier-v1',
            'mode' => $tier instanceof WholesalePriceTier ? 'wholesale' : 'retail',
            'retail_unit_price' => (int) $variant->price,
            'applied_unit_price' => $unitPrice,
            'variant_weight_grams' => (int) $variant->weight_grams,
            'quantity' => $quantity,
            'total_weight_grams' => $totalWeight,
            'cafe_id' => $cafe?->id,
            'tier_id' => $tier?->id,
            'tier_min_weight_grams' => $tier?->min_weight_grams,
        ];

        return ['unit_price' => $unitPrice, 'snapshot' => $snapshot];
    }

    public function eligibleCafe(User $user): ?Cafe
    {
        $membership = CafeMembership::query()
            ->with('cafe')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('cafe', static fn ($query) => $query
                ->where('status', CafeStatus::Verified->value)
                ->whereNotNull('verified_at'))
            ->orderBy('created_at')
            ->first();

        return $membership?->cafe;
    }

    /** @param array<string,mixed> $actual */
    public function snapshotMatches(array $expected, array $actual): bool
    {
        return $expected === $actual;
    }
}
''')

write('backend/app/Services/B2B/WholesaleTierService.php', r'''<?php

namespace App\Services\B2B;

use App\Exceptions\ApiDomainException;
use App\Models\ProductVariant;
use App\Models\WholesalePriceTier;
use Illuminate\Support\Facades\DB;

final class WholesaleTierService
{
    public const array DEFAULT_THRESHOLDS = [5_000, 10_000, 20_000, 50_000];

    /**
     * @param list<array{min_weight_grams:int,unit_price:int,is_active?:bool}> $tiers
     */
    public function replace(ProductVariant $variant, array $tiers): ProductVariant
    {
        $seen = [];
        $ordered = collect($tiers)->sortBy('min_weight_grams')->values();
        $previousPrice = (int) $variant->price;

        foreach ($ordered as $tier) {
            $threshold = (int) $tier['min_weight_grams'];
            $price = (int) $tier['unit_price'];
            if (! in_array($threshold, self::DEFAULT_THRESHOLDS, true) || isset($seen[$threshold])) {
                throw new ApiDomainException('b2b.wholesale_threshold_invalid', 'پله وزن عمده معتبر یا یکتا نیست.', 422);
            }
            if ($price <= 0 || $price > (int) $variant->price || $price > $previousPrice) {
                throw new ApiDomainException('b2b.wholesale_price_invalid', 'قیمت عمده باید مثبت، حداکثر قیمت تک و با افزایش وزن نزولی یا ثابت باشد.', 422);
            }
            $seen[$threshold] = true;
            $previousPrice = $price;
        }

        return DB::transaction(function () use ($variant, $ordered): ProductVariant {
            WholesalePriceTier::query()->where('product_variant_id', $variant->id)->delete();
            foreach ($ordered as $tier) {
                WholesalePriceTier::query()->create([
                    'product_variant_id' => $variant->id,
                    'min_weight_grams' => (int) $tier['min_weight_grams'],
                    'unit_price' => (int) $tier['unit_price'],
                    'is_active' => (bool) ($tier['is_active'] ?? true),
                ]);
            }

            return $variant->refresh()->load(['wholesaleTiers' => static fn ($query) => $query->orderBy('min_weight_grams')]);
        });
    }
}
''')

write('backend/app/Services/Cafe/CafeService.php', r'''<?php

namespace App\Services\Cafe;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Exceptions\ApiDomainException;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CafeService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string,mixed> $payload */
    public function apply(User $user, array $payload, ?Request $request = null): Cafe
    {
        return DB::transaction(function () use ($user, $payload, $request): Cafe {
            $slug = $this->uniqueSlug((string) ($payload['slug'] ?? ''), (string) $payload['name']);
            $cafe = Cafe::query()->create([
                'owner_user_id' => $user->id,
                'name' => trim((string) $payload['name']),
                'slug' => $slug,
                'status' => CafeStatus::Pending,
                'city' => trim((string) $payload['city']),
                'address' => trim((string) $payload['address']),
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'phone' => $this->nullableTrim($payload['phone'] ?? null),
                'website_url' => $this->nullableTrim($payload['website_url'] ?? null),
                'instagram_handle' => $this->nullableTrim($payload['instagram_handle'] ?? null),
                'description' => $this->nullableTrim($payload['description'] ?? null),
                'opening_hours' => $payload['opening_hours'] ?? null,
                'amenities' => array_values(array_unique($payload['amenities'] ?? [])),
            ]);

            CafeMembership::query()->create([
                'cafe_id' => $cafe->id,
                'user_id' => $user->id,
                'role' => CafeMembership::ROLE_OWNER,
                'is_active' => true,
                'created_by_id' => $user->id,
            ]);

            $this->audit->record('cafe.application.created', actor: $user, auditable: $cafe, request: $request);

            return $cafe;
        });
    }

    /** @param array<string,mixed> $payload */
    public function updateForMember(Cafe $cafe, User $user, array $payload, ?Request $request = null): Cafe
    {
        $membership = CafeMembership::query()
            ->where('cafe_id', $cafe->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('role', [CafeMembership::ROLE_OWNER, CafeMembership::ROLE_MANAGER])
            ->first();
        if (! $membership instanceof CafeMembership) {
            abort(404);
        }

        $allowed = collect($payload)->only([
            'name', 'city', 'address', 'latitude', 'longitude', 'phone', 'website_url',
            'instagram_handle', 'description', 'opening_hours', 'amenities',
        ])->all();
        foreach (['name', 'city', 'address', 'phone', 'website_url', 'instagram_handle', 'description'] as $field) {
            if (array_key_exists($field, $allowed)) {
                $allowed[$field] = $this->nullableTrim($allowed[$field]);
            }
        }
        if (array_key_exists('amenities', $allowed)) {
            $allowed['amenities'] = array_values(array_unique($allowed['amenities'] ?? []));
        }

        $cafe->fill($allowed)->save();
        $this->audit->record('cafe.profile.updated', actor: $user, auditable: $cafe, metadata: ['fields' => array_keys($allowed)], request: $request);

        return $cafe->refresh();
    }

    public function setStatus(Cafe $cafe, CafeStatus $status, User $actor, ?string $note = null, ?Request $request = null): Cafe
    {
        return DB::transaction(function () use ($cafe, $status, $actor, $note, $request): Cafe {
            $locked = Cafe::query()->lockForUpdate()->findOrFail($cafe->id);
            $locked->forceFill([
                'status' => $status,
                'verified_at' => $status === CafeStatus::Verified ? ($locked->verified_at ?? now()) : null,
                'reviewed_by_id' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $this->nullableTrim($note),
            ])->save();

            $memberships = CafeMembership::query()->where('cafe_id', $locked->id)->where('is_active', true)->get();
            foreach ($memberships as $membership) {
                $role = $membership->role === CafeMembership::ROLE_OWNER ? Role::CafeOwner : Role::CafeManager;
                if ($status === CafeStatus::Verified) {
                    UserRole::query()->firstOrCreate([
                        'user_id' => $membership->user_id,
                        'role' => $role,
                        'scope_type' => 'cafe',
                        'scope_id' => $locked->id,
                    ]);
                } else {
                    UserRole::query()
                        ->where('user_id', $membership->user_id)
                        ->where('scope_type', 'cafe')
                        ->where('scope_id', $locked->id)
                        ->whereIn('role', [Role::CafeOwner->value, Role::CafeManager->value])
                        ->delete();
                }
            }

            $this->audit->record('cafe.status.changed', actor: $actor, auditable: $locked, metadata: ['status' => $status->value], request: $request);

            return $locked->refresh();
        });
    }

    private function uniqueSlug(string $requested, string $name): string
    {
        $base = Str::slug(trim($requested !== '' ? $requested : $name));
        if ($base === '') {
            $base = 'cafe';
        }
        $candidate = $base;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            if (! Cafe::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
            $candidate = $base.'-'.strtolower(Str::random(6));
        }
        throw new ApiDomainException('cafe.slug_unavailable', 'شناسه عمومی مناسب برای کافه پیدا نشد.', 409);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }
}
''')

write('backend/app/Services/Cafe/CafeDirectoryService.php', r'''<?php

namespace App\Services\Cafe;

use App\Enums\CafeStatus;
use App\Models\Cafe;
use Illuminate\Support\Collection;

final class CafeDirectoryService
{
    /** @return Collection<int,array{cafe:Cafe,distance_km:float|null}> */
    public function search(?string $city, ?float $latitude, ?float $longitude, float $radiusKm = 10.0): Collection
    {
        $query = Cafe::query()
            ->where('status', CafeStatus::Verified->value)
            ->whereNotNull('verified_at');

        if ($city !== null && trim($city) !== '') {
            $query->where('city', trim($city));
        }

        if ($latitude !== null && $longitude !== null) {
            $latDelta = $radiusKm / 111.32;
            $cosine = max(0.01, cos(deg2rad($latitude)));
            $lngDelta = $radiusKm / (111.32 * $cosine);
            $query->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
        }

        return $query->orderBy('name')->limit(200)->get()
            ->map(function (Cafe $cafe) use ($latitude, $longitude): array {
                $distance = null;
                if ($latitude !== null && $longitude !== null && $cafe->latitude !== null && $cafe->longitude !== null) {
                    $distance = $this->distanceKm($latitude, $longitude, (float) $cafe->latitude, (float) $cafe->longitude);
                }
                return ['cafe' => $cafe, 'distance_km' => $distance];
            })
            ->filter(static fn (array $entry): bool => $entry['distance_km'] === null || $entry['distance_km'] <= $radiusKm)
            ->sortBy(static fn (array $entry): float => $entry['distance_km'] ?? PHP_FLOAT_MAX)
            ->values();
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }
}
''')

write('backend/app/Http/Requests/Cafe/ApplyCafeRequest.php', r'''<?php

namespace App\Http\Requests\Cafe;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class ApplyCafeRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'slug', 'city', 'address', 'latitude', 'longitude', 'phone', 'website_url', 'instagram_handle', 'description', 'opening_hours', 'amenities']);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_handle' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:4000'],
            'opening_hours' => ['nullable', 'array', 'max:7'],
            'amenities' => ['nullable', 'array', 'max:50'],
            'amenities.*' => ['string', 'max:80', 'distinct:strict'],
        ];
    }
}
''')

write('backend/app/Http/Requests/Cafe/UpdateCafeRequest.php', r'''<?php

namespace App\Http\Requests\Cafe;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCafeRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['name', 'city', 'address', 'latitude', 'longitude', 'phone', 'website_url', 'instagram_handle', 'description', 'opening_hours', 'amenities']);
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'city' => ['sometimes', 'required', 'string', 'max:120'],
            'address' => ['sometimes', 'required', 'string', 'max:1000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'instagram_handle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'opening_hours' => ['sometimes', 'nullable', 'array', 'max:7'],
            'amenities' => ['sometimes', 'nullable', 'array', 'max:50'],
            'amenities.*' => ['string', 'max:80', 'distinct:strict'],
        ];
    }
}
''')

write('backend/app/Http/Requests/Cafe/SetCafeStatusRequest.php', r'''<?php

namespace App\Http\Requests\Cafe;

use App\Enums\CafeStatus;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SetCafeStatusRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void { $this->rejectUnexpected(['status', 'review_note']); }
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CafeStatus::class)],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
''')

write('backend/app/Http/Requests/Catalog/ReplaceWholesaleTiersRequest.php', r'''<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Services\B2B\WholesaleTierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceWholesaleTiersRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected(['tiers']);
        $this->rejectUnexpectedNested(is_array($this->input('tiers')) ? $this->input('tiers') : [], ['min_weight_grams', 'unit_price', 'is_active'], 'tiers');
    }

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tiers' => ['required', 'array', 'max:4'],
            'tiers.*.min_weight_grams' => ['required', 'integer', Rule::in(WholesaleTierService::DEFAULT_THRESHOLDS), 'distinct:strict'],
            'tiers.*.unit_price' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            'tiers.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
''')

write('backend/app/Http/Resources/CafeResource.php', r'''<?php

namespace App\Http\Resources;

use App\Models\Cafe;
use Illuminate\Http\Request;

/** @mixin Cafe */
final class CafeResource extends OkJsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'is_verified' => $this->isPubliclyVisible(),
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'website_url' => $this->website_url,
            'instagram_handle' => $this->instagram_handle,
            'description' => $this->description,
            'opening_hours' => $this->opening_hours ?? [],
            'amenities' => $this->amenities ?? [],
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
''')

write('backend/app/Http/Controllers/Cafe/CafeDirectoryController.php', r'''<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Services\Cafe\CafeDirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CafeDirectoryController
{
    public function index(Request $request, CafeDirectoryService $directory): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'city' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:50'],
        ])->validate();

        $items = $directory->search(
            isset($data['city']) ? (string) $data['city'] : null,
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
            isset($data['radius_km']) ? (float) $data['radius_km'] : 10.0,
        )->map(function (array $entry) use ($request): array {
            return [
                ...(new CafeResource($entry['cafe']))->resolve($request),
                'distance_km' => $entry['distance_km'],
            ];
        })->all();

        return ApiResponse::success(['items' => $items]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $cafe = Cafe::query()->where('slug', $slug)->where('status', 'verified')->whereNotNull('verified_at')->firstOrFail();
        return ApiResponse::success((new CafeResource($cafe))->resolve($request));
    }
}
''')

write('backend/app/Http/Controllers/Cafe/CafeApplicationController.php', r'''<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Requests\Cafe\ApplyCafeRequest;
use App\Http\Resources\CafeResource;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CafeApplicationController
{
    public function store(ApplyCafeRequest $request, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cafe = $service->apply($user, $request->validated(), $request);
        return ApiResponse::success((new CafeResource($cafe))->resolve($request), 201);
    }
}
''')

write('backend/app/Http/Controllers/Cafe/CafeAccountController.php', r'''<?php

namespace App\Http\Controllers\Cafe;

use App\Http\Requests\Cafe\UpdateCafeRequest;
use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CafeAccountController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $memberships = CafeMembership::query()->with('cafe')->where('user_id', $user->id)->where('is_active', true)->get();
        return ApiResponse::success(['items' => $memberships->map(fn (CafeMembership $membership): array => [
            ...(new CafeResource($membership->cafe))->resolve($request),
            'membership_role' => $membership->role,
        ])->all()]);
    }

    public function update(UpdateCafeRequest $request, string $cafeId, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cafe = Cafe::query()->findOrFail($cafeId);
        $updated = $service->updateForMember($cafe, $user, $request->validated(), $request);
        return ApiResponse::success((new CafeResource($updated))->resolve($request));
    }
}
''')

write('backend/app/Http/Controllers/Admin/AdminCafeController.php', r'''<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Http\Requests\Cafe\SetCafeStatusRequest;
use App\Http\Resources\CafeResource;
use App\Models\Cafe;
use App\Models\User;
use App\Services\Cafe\CafeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminCafeController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasRole(Role::Administrator)) { abort(403); }
        $status = trim((string) $request->query('status', CafeStatus::Pending->value));
        $query = Cafe::query()->orderByDesc('updated_at');
        if (in_array($status, array_column(CafeStatus::cases(), 'value'), true)) { $query->where('status', $status); }
        $page = $query->paginate(perPage: max(1, min(100, (int) $request->query('per_page', 50))), page: max(1, (int) $request->query('page', 1)));
        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (Cafe $cafe): array => (new CafeResource($cafe))->resolve($request))->all(),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function setStatus(SetCafeStatusRequest $request, string $cafeId, CafeService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasRole(Role::Administrator)) { abort(403); }
        $cafe = Cafe::query()->findOrFail($cafeId);
        $updated = $service->setStatus($cafe, CafeStatus::from((string) $request->validated('status')), $user, $request->validated('review_note'), $request);
        return ApiResponse::success((new CafeResource($updated))->resolve($request));
    }
}
''')

write('backend/app/Http/Controllers/Seller/SellerWholesaleTierController.php', r'''<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Http\Requests\Catalog\ReplaceWholesaleTiersRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Services\B2B\WholesaleTierService;
use App\Services\Catalog\CatalogAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SellerWholesaleTierController
{
    public function index(Request $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access): JsonResponse
    {
        [, $variant] = $this->context($request, $roasteryId, $productId, $variantId, $access);
        return ApiResponse::success(['items' => $this->tiers($variant->load('wholesaleTiers'))]);
    }

    public function replace(ReplaceWholesaleTiersRequest $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access, WholesaleTierService $tiers, AuditRecorder $audit): JsonResponse
    {
        [$user, $variant] = $this->context($request, $roasteryId, $productId, $variantId, $access);
        $updated = $tiers->replace($variant, $request->validated('tiers'));
        $audit->record('catalog.wholesale_tiers.replaced', actor: $user, auditable: $updated, metadata: ['thresholds' => $updated->wholesaleTiers->pluck('min_weight_grams')->all()], request: $request);
        return ApiResponse::success(['items' => $this->tiers($updated)]);
    }

    private function context(Request $request, string $roasteryId, string $productId, string $variantId, CatalogAccess $access): array
    {
        /** @var User $user */
        $user = $request->user();
        $roastery = Roastery::query()->findOrFail($roasteryId);
        $access->assertRoasteryAccess($user, $roastery, [Role::RoasteryOwner, Role::RoasteryManager]);
        $product = Product::query()->where('roastery_id', $roastery->id)->findOrFail($productId);
        $variant = ProductVariant::query()->where('product_id', $product->id)->findOrFail($variantId);
        return [$user, $variant];
    }

    private function tiers(ProductVariant $variant): array
    {
        return $variant->wholesaleTiers->sortBy('min_weight_grams')->values()->map(static fn ($tier): array => [
            'id' => $tier->id,
            'min_weight_grams' => $tier->min_weight_grams,
            'unit_price' => $tier->unit_price,
            'is_active' => $tier->is_active,
        ])->all();
    }
}
''')

write('backend/tests/Feature/PS12CafeWholesaleTest.php', r'''<?php

namespace Tests\Feature;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Roastery;
use App\Models\ShippingRule;
use App\Models\User;
use App\Models\WholesalePriceTier;
use App\Services\B2B\WholesalePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class PS12CafeWholesaleTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_wholesale_tiers_require_verified_cafe_and_resolve_at_exact_weight_boundaries(): void
    {
        [$user, , , $variant] = $this->fixture();
        foreach ([[5_000, 900_000], [10_000, 850_000], [20_000, 800_000], [50_000, 750_000]] as [$weight, $price]) {
            WholesalePriceTier::query()->create(['product_variant_id' => $variant->id, 'min_weight_grams' => $weight, 'unit_price' => $price, 'is_active' => true]);
        }

        $pricing = app(WholesalePricingService::class);
        self::assertSame(1_000_000, $pricing->resolve($user, $variant, 50)['unit_price']);

        $cafe = Cafe::query()->create(['owner_user_id' => $user->id, 'name' => 'کافه تست', 'slug' => 'test-cafe', 'status' => CafeStatus::Pending, 'city' => 'کرج', 'address' => 'مرکز شهر']);
        CafeMembership::query()->create(['cafe_id' => $cafe->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true, 'created_by_id' => $user->id]);
        self::assertSame(1_000_000, $pricing->resolve($user, $variant, 5)['unit_price']);

        $cafe->forceFill(['status' => CafeStatus::Verified, 'verified_at' => now()])->save();
        self::assertSame(900_000, $pricing->resolve($user, $variant, 5)['unit_price']);
        self::assertSame(850_000, $pricing->resolve($user, $variant, 10)['unit_price']);
        self::assertSame(800_000, $pricing->resolve($user, $variant, 20)['unit_price']);
        self::assertSame(750_000, $pricing->resolve($user, $variant, 50)['unit_price']);
    }

    public function test_verified_cafe_gets_server_authoritative_wholesale_quote_snapshot(): void
    {
        [$user, $address, , $variant] = $this->fixture();
        $this->authenticateWithRole($user, Role::Customer);
        $cafe = Cafe::query()->create(['owner_user_id' => $user->id, 'name' => 'کافه عمده', 'slug' => 'wholesale-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'مرکز', 'verified_at' => now()]);
        CafeMembership::query()->create(['cafe_id' => $cafe->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true, 'created_by_id' => $user->id]);
        WholesalePriceTier::query()->create(['product_variant_id' => $variant->id, 'min_weight_grams' => 5_000, 'unit_price' => 900_000, 'is_active' => true]);

        $quote = $this->postJson('/api/v1/checkout/quote', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 5]],
            'address_id' => $address->id,
        ])->assertOk()->json('data');

        self::assertSame(900_000, $quote['items'][0]['unit_price']);
        $this->assertDatabaseHas('checkout_quote_items', ['quote_id' => $quote['id'], 'variant_id' => $variant->id, 'unit_price' => 900_000, 'line_total' => 4_500_000]);
        $stored = \App\Models\CheckoutQuoteItem::query()->where('quote_id', $quote['id'])->firstOrFail();
        self::assertSame('ps12-wholesale-tier-v1', $stored->variant_snapshot['pricing']['version']);
        self::assertSame('wholesale', $stored->variant_snapshot['pricing']['mode']);
    }

    public function test_public_directory_returns_only_verified_nearby_cafes_sorted_by_distance(): void
    {
        $owner = User::factory()->create();
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'نزدیک', 'slug' => 'near-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'نزدیک', 'latitude' => 35.8327, 'longitude' => 50.9915, 'verified_at' => now()]);
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'دورتر', 'slug' => 'farther-cafe', 'status' => CafeStatus::Verified, 'city' => 'کرج', 'address' => 'دورتر', 'latitude' => 35.8500, 'longitude' => 51.0100, 'verified_at' => now()]);
        Cafe::query()->create(['owner_user_id' => $owner->id, 'name' => 'در انتظار', 'slug' => 'pending-cafe', 'status' => CafeStatus::Pending, 'city' => 'کرج', 'address' => 'پنهان', 'latitude' => 35.8328, 'longitude' => 50.9916]);

        $response = $this->getJson('/api/v1/cafes?lat=35.8325&lng=50.9912&radius_km=10')->assertOk();
        self::assertSame('near-cafe', $response->json('data.items.0.slug'));
        self::assertCount(2, $response->json('data.items'));
    }

    public function test_cafe_application_is_pending_until_admin_verifies_it(): void
    {
        $owner = User::factory()->create();
        $this->authenticateWithRole($owner, Role::Customer);
        $cafe = $this->postJson('/api/v1/cafes/apply', ['name' => 'کافه درخواست', 'slug' => 'apply-cafe', 'city' => 'کرج', 'address' => 'آدرس کافه', 'latitude' => 35.83, 'longitude' => 50.99])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

        $admin = User::factory()->create();
        $this->authenticateWithRole($admin, Role::Administrator);
        $this->patchJson('/api/v1/admin/cafes/'.$cafe['id'].'/status', ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.status', 'verified');
        $this->assertDatabaseHas('user_roles', ['user_id' => $owner->id, 'role' => Role::CafeOwner->value, 'scope_type' => 'cafe', 'scope_id' => $cafe['id']]);
    }

    /** @return array{User,Address,Roastery,ProductVariant} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $address = Address::query()->create(['user_id' => $user->id, 'title' => 'کافه', 'recipient_name' => 'مدیر', 'recipient_mobile' => '09123456789', 'province' => 'البرز', 'city' => 'کرج', 'address_line' => 'نشانی', 'postal_code' => '1234567890', 'is_default' => true]);
        $roastery = Roastery::query()->create(['name' => 'روستری B2B', 'slug' => 'b2b-roastery-'.substr((string) $user->id, -6), 'description' => '', 'status' => 'verified', 'verified_at' => now()]);
        ShippingRule::query()->create(['roastery_id' => $roastery->id, 'base_cost' => 0, 'priority' => 0, 'is_active' => true]);
        $origin = Origin::query()->create(['name' => 'برزیل B2B', 'slug' => 'b2b-origin-'.substr((string) $user->id, -6), 'country_code' => 'BRA']);
        $product = Product::query()->create(['roastery_id' => $roastery->id, 'origin_id' => $origin->id, 'name' => 'قهوه B2B', 'slug' => 'b2b-product-'.substr((string) $user->id, -6), 'description' => '', 'processing_method' => 'washed', 'roast_level' => 'medium', 'arabica_percentage' => 100, 'tasting_notes' => [], 'brewing_suggestions' => [], 'status' => 'published', 'published_at' => now()]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'B2B-'.substr((string) $user->id, -8), 'weight_grams' => 1000, 'price' => 1_000_000, 'currency' => 'IRR', 'is_active' => true, 'stock_on_hand' => 1000, 'stock_reserved' => 0]);
        return [$user, $address, $roastery, $variant];
    }
}
''')

replace_once('backend/app/Enums/Role.php', "    case Customer = 'customer';\n", "    case Customer = 'customer';\n    case CafeOwner = 'cafe_owner';\n    case CafeManager = 'cafe_manager';\n")

replace_once('backend/app/Models/ProductVariant.php', "    public function orderItems(): HasMany\n    {\n        return $this->hasMany(OrderItem::class, 'variant_id');\n    }\n\n", "    public function orderItems(): HasMany\n    {\n        return $this->hasMany(OrderItem::class, 'variant_id');\n    }\n\n    public function wholesaleTiers(): HasMany\n    {\n        return $this->hasMany(WholesalePriceTier::class, 'product_variant_id')->orderBy('min_weight_grams');\n    }\n\n")

replace_once('backend/app/Http/Resources/ProductVariantResource.php', "            'available_quantity' => $this->availableQuantity(),\n", "            'available_quantity' => $this->availableQuantity(),\n            'wholesale_tiers' => $this->whenLoaded('wholesaleTiers', fn (): array => $this->wholesaleTiers->where('is_active', true)->sortBy('min_weight_grams')->values()->map(static fn ($tier): array => [\n                'min_weight_grams' => $tier->min_weight_grams,\n                'unit_price' => $tier->unit_price,\n            ])->all()),\n")

replace_once('backend/app/Services/Catalog/PublicCatalogService.php', "                'variants' => static fn ($variants) => $variants->where('is_active', true)->orderBy('weight_grams'),\n", "                'variants' => static fn ($variants) => $variants->where('is_active', true)->orderBy('weight_grams'),\n                'variants.wholesaleTiers' => static fn ($tiers) => $tiers->where('is_active', true)->orderBy('min_weight_grams'),\n")

replace_once('backend/config/rosta.php', "        'max_quantity_per_line' => 20,\n", "        'max_quantity_per_line' => 1000,\n")
replace_once('backend/app/Http/Requests/Checkout/CartValidateRequest.php', "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],\n", "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.(int) config('rosta.checkout.max_quantity_per_line', 1000)],\n")
replace_once('backend/app/Http/Requests/Checkout/CheckoutQuoteRequest.php', "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],\n", "            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.(int) config('rosta.checkout.max_quantity_per_line', 1000)],\n")

replace_once('backend/app/Services/Checkout/QuoteService.php', "use App\\Services\\Catalog\\ProductPackagingPolicy;\n", "use App\\Services\\B2B\\WholesalePricingService;\nuse App\\Services\\Catalog\\ProductPackagingPolicy;\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "        private readonly FinancialTruthEngine $financialTruth,\n", "        private readonly FinancialTruthEngine $financialTruth,\n        private readonly WholesalePricingService $wholesalePricing,\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "                'product.variants' => static fn ($query) => $query\n                    ->where('is_active', true)\n                    ->orderBy('weight_grams'),\n", "                'product.variants' => static fn ($query) => $query\n                    ->where('is_active', true)\n                    ->orderBy('weight_grams'),\n                'wholesaleTiers' => static fn ($query) => $query\n                    ->where('is_active', true)\n                    ->orderBy('min_weight_grams'),\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "            $lineTotal = $this->multiplyMoney($variant->price, $quantity);\n", "            $pricing = $this->wholesalePricing->resolve($user, $variant, $quantity);\n            $unitPrice = $pricing['unit_price'];\n            $lineTotal = $this->multiplyMoney($unitPrice, $quantity);\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "                'quantity' => $quantity,\n                'line_total' => $lineTotal,\n", "                'quantity' => $quantity,\n                'unit_price' => $unitPrice,\n                'pricing_snapshot' => $pricing['snapshot'],\n                'line_total' => $lineTotal,\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "                        'unit_price' => $variant->price,\n", "                        'unit_price' => $resolved['unit_price'],\n")
replace_once('backend/app/Services/Checkout/QuoteService.php', "                        'variant_snapshot' => $this->variantSnapshot($variant),\n", "                        'variant_snapshot' => [\n                            ...$this->variantSnapshot($variant),\n                            'pricing' => $resolved['pricing_snapshot'],\n                        ],\n")

replace_once('backend/app/Services/Checkout/OrderService.php', "use App\\Services\\AuditRecorder;\n", "use App\\Services\\AuditRecorder;\nuse App\\Services\\B2B\\WholesalePricingService;\n")
replace_once('backend/app/Services/Checkout/OrderService.php', "        private readonly RostaHubOperationsService $hubOperations,\n", "        private readonly RostaHubOperationsService $hubOperations,\n        private readonly WholesalePricingService $wholesalePricing,\n")
replace_once('backend/app/Services/Checkout/OrderService.php', "                ->with(['product.roastery', 'product.latestRoastBatch'])\n", "                ->with(['product.roastery', 'product.latestRoastBatch', 'wholesaleTiers' => static fn ($query) => $query->where('is_active', true)->orderBy('min_weight_grams')])\n")
replace_once('backend/app/Services/Checkout/OrderService.php', "                    $product = $variant->product;\n                    if (\n                        ! $variant->is_active\n                        || $variant->price !== $quoteItem->unit_price\n", "                    $product = $variant->product;\n                    $pricing = $this->wholesalePricing->resolve($user, $variant, (int) $quoteItem->quantity);\n                    $quotedPricing = is_array($quoteItem->variant_snapshot)\n                        ? ($quoteItem->variant_snapshot['pricing'] ?? null)\n                        : null;\n                    $pricingChanged = $pricing['unit_price'] !== (int) $quoteItem->unit_price\n                        || ! is_array($quotedPricing)\n                        || ! $this->wholesalePricing->snapshotMatches($pricing['snapshot'], $quotedPricing);\n                    if (\n                        ! $variant->is_active\n                        || $pricingChanged\n")

replace_once('backend/routes/api.php', "use App\\Http\\Controllers\\Admin\\AdminContentAuthorController;\n", "use App\\Http\\Controllers\\Admin\\AdminCafeController;\nuse App\\Http\\Controllers\\Admin\\AdminContentAuthorController;\n")
replace_once('backend/routes/api.php', "use App\\Http\\Controllers\\Catalog\\SearchController;\n", "use App\\Http\\Controllers\\Cafe\\CafeAccountController;\nuse App\\Http\\Controllers\\Cafe\\CafeApplicationController;\nuse App\\Http\\Controllers\\Cafe\\CafeDirectoryController;\nuse App\\Http\\Controllers\\Catalog\\SearchController;\n")
replace_once('backend/routes/api.php', "use App\\Http\\Controllers\\Seller\\SellerVariantController;\n", "use App\\Http\\Controllers\\Seller\\SellerVariantController;\nuse App\\Http\\Controllers\\Seller\\SellerWholesaleTierController;\n")
replace_once('backend/routes/api.php', "    Route::get('/roasteries/{slug}', [RoasteryController::class, 'show'])->where('slug', '[A-Za-z0-9\\p{Arabic}_-]+')->name('api.v1.roasteries.show');\n", "    Route::get('/roasteries/{slug}', [RoasteryController::class, 'show'])->where('slug', '[A-Za-z0-9\\p{Arabic}_-]+')->name('api.v1.roasteries.show');\n    Route::get('/cafes', [CafeDirectoryController::class, 'index'])->name('api.v1.cafes.index');\n    Route::get('/cafes/{slug}', [CafeDirectoryController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('api.v1.cafes.show');\n")
replace_once('backend/routes/api.php', "        Route::delete('/me/addresses/{addressId}', [AddressController::class, 'destroy'])->where('addressId', '[A-Za-z0-9._:-]+')->name('api.v1.addresses.destroy');\n", "        Route::delete('/me/addresses/{addressId}', [AddressController::class, 'destroy'])->where('addressId', '[A-Za-z0-9._:-]+')->name('api.v1.addresses.destroy');\n        Route::post('/cafes/apply', [CafeApplicationController::class, 'store'])->name('api.v1.cafes.apply');\n        Route::get('/me/cafes', [CafeAccountController::class, 'index'])->name('api.v1.me.cafes.index');\n        Route::patch('/me/cafes/{cafeId}', [CafeAccountController::class, 'update'])->where('cafeId', '[A-Za-z0-9._:-]+')->name('api.v1.me.cafes.update');\n")
replace_once('backend/routes/api.php', "                Route::patch('/products/{productId}/variants/{variantId}', [SellerVariantController::class, 'update'])->where(['productId' => '[A-Za-z0-9._:-]+', 'variantId' => '[A-Za-z0-9._:-]+'])->name('api.v1.seller.variants.update');\n", "                Route::patch('/products/{productId}/variants/{variantId}', [SellerVariantController::class, 'update'])->where(['productId' => '[A-Za-z0-9._:-]+', 'variantId' => '[A-Za-z0-9._:-]+'])->name('api.v1.seller.variants.update');\n                Route::get('/products/{productId}/variants/{variantId}/wholesale-tiers', [SellerWholesaleTierController::class, 'index'])->where(['productId' => '[A-Za-z0-9._:-]+', 'variantId' => '[A-Za-z0-9._:-]+'])->name('api.v1.seller.wholesale_tiers.index');\n                Route::put('/products/{productId}/variants/{variantId}/wholesale-tiers', [SellerWholesaleTierController::class, 'replace'])->where(['productId' => '[A-Za-z0-9._:-]+', 'variantId' => '[A-Za-z0-9._:-]+'])->name('api.v1.seller.wholesale_tiers.replace');\n")
replace_once('backend/routes/api.php', "            Route::patch('/roasteries/{roasteryId}/status', [AdminRoasteryController::class, 'setStatus'])->where('roasteryId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.roasteries.status');\n", "            Route::patch('/roasteries/{roasteryId}/status', [AdminRoasteryController::class, 'setStatus'])->where('roasteryId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.roasteries.status');\n            Route::get('/cafes', [AdminCafeController::class, 'index'])->name('api.v1.admin.cafes.index');\n            Route::patch('/cafes/{cafeId}/status', [AdminCafeController::class, 'setStatus'])->where('cafeId', '[A-Za-z0-9._:-]+')->name('api.v1.admin.cafes.status');\n")

# The bootstrap is one-shot; remove its own source/workflow from the generated commit.
(ROOT / '.github/workflows/ps12-bootstrap.yml').unlink()
Path(__file__).unlink()
