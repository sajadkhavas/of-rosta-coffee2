<?php

namespace App\Services\Cafe;

use App\Enums\CafeStatus;
use App\Enums\Role;
use App\Exceptions\ApiDomainException;
use App\Models\Cafe;
use App\Models\CafeMembership;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditRecorder;
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
