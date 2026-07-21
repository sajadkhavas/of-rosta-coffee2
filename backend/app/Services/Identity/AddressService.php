<?php

namespace App\Services\Identity;

use App\Exceptions\ApiDomainException;
use App\Models\Address;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AddressService
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, Request $request): Address
    {
        $address = DB::transaction(function () use ($user, $data): Address {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $count = Address::query()->where('user_id', $user->id)->count();
            $maximum = (int) config('rosta.addresses.max_per_user', 20);
            if ($count >= $maximum) {
                throw new ApiDomainException(
                    'profile.address_limit_reached',
                    'تعداد آدرس‌های ذخیره‌شده به سقف مجاز رسیده است.',
                    409,
                );
            }

            $makeDefault = $count === 0 || (bool) ($data['is_default'] ?? false);
            if ($makeDefault) {
                Address::query()
                    ->where('user_id', $user->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false, 'updated_at' => now()]);
            }

            return Address::query()->create([
                ...$data,
                'user_id' => $user->id,
                'is_default' => $makeDefault,
            ]);
        }, 3);

        $this->audit->record(
            'identity.address.created',
            actor: $user,
            auditable: $address,
            request: $request,
        );

        return $address;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(
        User $user,
        string $addressId,
        array $data,
        Request $request,
    ): Address {
        $address = DB::transaction(function () use (
            $user,
            $addressId,
            $data,
        ): Address {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $address = Address::query()
                ->where('user_id', $user->id)
                ->whereKey($addressId)
                ->lockForUpdate()
                ->first();

            if (! $address) {
                throw (new ModelNotFoundException)->setModel(Address::class, [$addressId]);
            }

            $requestedDefault = (bool) ($data['is_default'] ?? false);
            if ($requestedDefault) {
                Address::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false, 'updated_at' => now()]);
            } elseif ($address->is_default) {
                $replacement = Address::query()
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $address->id)
                    ->orderByDesc('updated_at')
                    ->lockForUpdate()
                    ->first();

                if ($replacement) {
                    $replacement->forceFill(['is_default' => true])->save();
                } else {
                    $data['is_default'] = true;
                }
            }

            $address->fill($data)->save();

            return $address->refresh();
        }, 3);

        $this->audit->record(
            'identity.address.updated',
            actor: $user,
            auditable: $address,
            request: $request,
        );

        return $address;
    }

    public function delete(
        User $user,
        string $addressId,
        Request $request,
    ): void {
        $address = DB::transaction(function () use ($user, $addressId): Address {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $address = Address::query()
                ->where('user_id', $user->id)
                ->whereKey($addressId)
                ->lockForUpdate()
                ->first();

            if (! $address) {
                throw (new ModelNotFoundException)->setModel(Address::class, [$addressId]);
            }

            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $replacement = Address::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('updated_at')
                    ->lockForUpdate()
                    ->first();

                $replacement?->forceFill(['is_default' => true])->save();
            }

            return $address;
        }, 3);

        $this->audit->record(
            'identity.address.deleted',
            actor: $user,
            metadata: ['address_id' => $address->id],
            request: $request,
        );
    }
}
