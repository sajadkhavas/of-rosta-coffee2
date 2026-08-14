<?php

namespace App\Services\Finance;

use App\Exceptions\ApiDomainException;
use App\Models\CommissionPolicy;
use App\Models\CommissionPolicyRule;
use App\Models\TaxPolicy;
use App\Models\TaxPolicyRule;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinancialPolicyService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function createTax(User $actor, array $data, Request $request): TaxPolicy
    {
        /** @var TaxPolicy $policy */
        $policy = $this->create(TaxPolicy::class, 'tax_policy_id', $actor, $data, $request);

        return $policy;
    }

    /** @param array<string, mixed> $data */
    public function createCommission(User $actor, array $data, Request $request): CommissionPolicy
    {
        /** @var CommissionPolicy $policy */
        $policy = $this->create(CommissionPolicy::class, 'commission_policy_id', $actor, $data, $request);

        return $policy;
    }

    /** @param array<string, mixed> $data */
    public function updateTax(TaxPolicy $policy, User $actor, array $data, Request $request): TaxPolicy
    {
        /** @var TaxPolicy $updated */
        $updated = $this->update($policy, 'tax_policy_id', $actor, $data, $request);

        return $updated;
    }

    /** @param array<string, mixed> $data */
    public function updateCommission(CommissionPolicy $policy, User $actor, array $data, Request $request): CommissionPolicy
    {
        /** @var CommissionPolicy $updated */
        $updated = $this->update($policy, 'commission_policy_id', $actor, $data, $request);

        return $updated;
    }

    public function submit(TaxPolicy|CommissionPolicy $policy, User $actor, Request $request): TaxPolicy|CommissionPolicy
    {
        if ($policy->status !== 'draft') {
            throw new ApiDomainException('finance.policy_immutable', 'فقط پیش‌نویس قابل ارسال برای تایید است.', 409);
        }
        if ($policy->effective_from === null || $policy->rules()->count() === 0) {
            throw new ApiDomainException('finance.policy_incomplete', 'تاریخ اثر و حداقل یک قاعده الزامی است.', 422);
        }

        $policy->forceFill([
            'status' => 'pending_approval',
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ])->save();
        $this->audit->record('finance.policy.submitted', $actor, $policy, [
            'policy_type' => $policy instanceof TaxPolicy ? 'tax' : 'commission',
            'version' => $policy->version,
        ], $request);

        return $policy->refresh()->load('rules');
    }

    public function publish(TaxPolicy|CommissionPolicy $policy, User $actor, Request $request): TaxPolicy|CommissionPolicy
    {
        return DB::transaction(function () use ($policy, $actor, $request): TaxPolicy|CommissionPolicy {
            /** @var TaxPolicy|CommissionPolicy $locked */
            $locked = $policy::query()->lockForUpdate()->findOrFail($policy->id);
            if ($locked->status !== 'pending_approval') {
                throw new ApiDomainException('finance.policy_not_pending', 'سیاست برای انتشار در انتظار تایید نیست.', 409);
            }
            if ($locked->created_by === $actor->id) {
                throw new ApiDomainException('finance.policy_dual_control', 'نویسنده نمی‌تواند سیاست مالی خود را منتشر کند.', 409);
            }
            if ($locked->effective_from === null || $locked->rules()->count() === 0) {
                throw new ApiDomainException('finance.policy_incomplete', 'سیاست مالی ناقص است.', 422);
            }

            $overlap = $locked::query()
                ->whereKeyNot($locked->id)
                ->where('status', 'published')
                ->where('currency', $locked->currency)
                ->where(function ($query) use ($locked): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', $locked->effective_from);
                })
                ->when($locked->effective_to !== null, function ($query) use ($locked): void {
                    $query->where('effective_from', '<', $locked->effective_to);
                })
                ->exists();
            if ($overlap) {
                throw new ApiDomainException('finance.policy_overlap', 'بازه اثر این سیاست با نسخه منتشرشده هم‌پوشانی دارد.', 409);
            }

            $checksum = hash('sha256', json_encode($this->checksumPayload($locked), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $locked->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
                'checksum' => $checksum,
            ])->save();
            $this->audit->record('finance.policy.published', $actor, $locked, [
                'policy_type' => $locked instanceof TaxPolicy ? 'tax' : 'commission',
                'version' => $locked->version,
                'checksum' => $checksum,
            ], $request);

            return $locked->refresh()->load('rules');
        });
    }

    /**
     * @param class-string<TaxPolicy|CommissionPolicy> $model
     * @param array<string, mixed> $data
     */
    private function create(string $model, string $foreignKey, User $actor, array $data, Request $request): Model
    {
        return DB::transaction(function () use ($model, $foreignKey, $actor, $data, $request): Model {
            $version = ((int) $model::query()->lockForUpdate()->max('version')) + 1;
            /** @var TaxPolicy|CommissionPolicy $policy */
            $policy = $model::query()->create([
                ...$this->attributes($data),
                'version' => $version,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);
            $this->replaceRules($policy, $foreignKey, $data['rules']);
            $this->audit->record('finance.policy.created', $actor, $policy, [
                'policy_type' => $policy instanceof TaxPolicy ? 'tax' : 'commission',
                'version' => $version,
            ], $request);

            return $policy->refresh()->load('rules');
        });
    }

    /** @param array<string, mixed> $data */
    private function update(TaxPolicy|CommissionPolicy $policy, string $foreignKey, User $actor, array $data, Request $request): Model
    {
        if ($policy->status !== 'draft') {
            throw new ApiDomainException('finance.policy_immutable', 'سیاست ارسال‌شده یا منتشرشده تغییرپذیر نیست.', 409);
        }

        return DB::transaction(function () use ($policy, $foreignKey, $actor, $data, $request): Model {
            $policy->fill($this->attributes($data))->save();
            $policy->rules()->delete();
            $this->replaceRules($policy, $foreignKey, $data['rules']);
            $this->audit->record('finance.policy.updated', $actor, $policy, [
                'policy_type' => $policy instanceof TaxPolicy ? 'tax' : 'commission',
                'version' => $policy->version,
            ], $request);

            return $policy->refresh()->load('rules');
        });
    }

    /** @param array<string, mixed> $data */
    private function attributes(array $data): array
    {
        return [
            'currency' => $data['currency'],
            'rounding_mode' => $data['rounding_mode'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'change_reason' => $data['change_reason'],
        ];
    }

    /** @param list<array<string, mixed>> $rules */
    private function replaceRules(TaxPolicy|CommissionPolicy $policy, string $foreignKey, array $rules): void
    {
        foreach ($rules as $rule) {
            $policy->rules()->create([$foreignKey => $policy->id, ...$rule]);
        }
    }

    /** @return array<string, mixed> */
    private function checksumPayload(TaxPolicy|CommissionPolicy $policy): array
    {
        return [
            'type' => $policy instanceof TaxPolicy ? 'tax' : 'commission',
            'version' => $policy->version,
            'currency' => $policy->currency,
            'rounding_mode' => $policy->rounding_mode,
            'effective_from' => $policy->effective_from?->toIso8601String(),
            'effective_to' => $policy->effective_to?->toIso8601String(),
            'rules' => $policy->rules()->orderBy('priority')->orderBy('code')->get()->map(
                fn (TaxPolicyRule|CommissionPolicyRule $rule): array => $rule->only([
                    'code', 'component', 'jurisdiction', 'owner_type', 'rate_basis_points', 'priority', 'applicability',
                ]),
            )->all(),
        ];
    }
}
