<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPolicy;
use App\Models\CommissionPolicyRule;
use App\Models\TaxPolicy;
use App\Models\TaxPolicyRule;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Finance\FinancialPolicyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFinancialPolicyController extends Controller
{
    public function index(Request $request, CatalogAccess $access): JsonResponse
    {
        $this->administrator($request, $access);

        return ApiResponse::success([
            'tax' => TaxPolicy::query()->with('rules')->latest('version')->get()->map(fn (TaxPolicy $policy) => $this->payload($policy)),
            'commission' => CommissionPolicy::query()->with('rules')->latest('version')->get()->map(fn (CommissionPolicy $policy) => $this->payload($policy)),
        ]);
    }

    public function storeTax(Request $request, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        $actor = $this->administrator($request, $access);

        return ApiResponse::success($this->payload($service->createTax($actor, $this->validated($request, true), $request)), 201);
    }

    public function updateTax(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        $actor = $this->administrator($request, $access);

        return ApiResponse::success($this->payload($service->updateTax(
            TaxPolicy::query()->findOrFail($policyId), $actor, $this->validated($request, true), $request,
        )));
    }

    public function storeCommission(Request $request, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        $actor = $this->administrator($request, $access);

        return ApiResponse::success($this->payload($service->createCommission($actor, $this->validated($request, false), $request)), 201);
    }

    public function updateCommission(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        $actor = $this->administrator($request, $access);

        return ApiResponse::success($this->payload($service->updateCommission(
            CommissionPolicy::query()->findOrFail($policyId), $actor, $this->validated($request, false), $request,
        )));
    }

    public function submitTax(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        return $this->transition($request, $access, $service, TaxPolicy::query()->findOrFail($policyId), 'submit');
    }

    public function publishTax(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        return $this->transition($request, $access, $service, TaxPolicy::query()->findOrFail($policyId), 'publish');
    }

    public function submitCommission(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        return $this->transition($request, $access, $service, CommissionPolicy::query()->findOrFail($policyId), 'submit');
    }

    public function publishCommission(Request $request, string $policyId, CatalogAccess $access, FinancialPolicyService $service): JsonResponse
    {
        return $this->transition($request, $access, $service, CommissionPolicy::query()->findOrFail($policyId), 'publish');
    }

    private function transition(Request $request, CatalogAccess $access, FinancialPolicyService $service, TaxPolicy|CommissionPolicy $policy, string $action): JsonResponse
    {
        $actor = $this->administrator($request, $access);
        $result = $action === 'submit'
            ? $service->submit($policy, $actor, $request)
            : $service->publish($policy, $actor, $request);

        return ApiResponse::success($this->payload($result));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $tax): array
    {
        $rules = [
            'currency' => ['required', 'in:IRR'],
            'rounding_mode' => ['required', 'in:floor,half_up'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'rules' => ['required', 'array', 'min:1', 'max:64'],
            'rules.*.code' => ['required', 'string', 'regex:/^[a-z0-9_.-]+$/', 'distinct', 'max:64'],
            'rules.*.component' => ['required', 'in:product,packaging,grinding,shipping'],
            'rules.*.rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'rules.*.priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'rules.*.applicability' => ['nullable', 'array'],
        ];
        if ($tax) {
            $rules['rules.*.jurisdiction'] = ['required', 'string', 'max:64'];
        } else {
            $rules['rules.*.owner_type'] = ['nullable', 'in:roastery,rosta'];
        }

        return $request->validate($rules);
    }

    private function administrator(Request $request, CatalogAccess $access): User
    {
        /** @var User $actor */
        $actor = $request->user();
        $access->assertAdministrator($actor);

        return $actor;
    }

    /** @return array<string, mixed> */
    private function payload(TaxPolicy|CommissionPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'type' => $policy instanceof TaxPolicy ? 'tax' : 'commission',
            'version' => $policy->version,
            'status' => $policy->status,
            'currency' => $policy->currency,
            'rounding_mode' => $policy->rounding_mode,
            'effective_from' => $policy->effective_from?->toIso8601String(),
            'effective_to' => $policy->effective_to?->toIso8601String(),
            'created_by' => $policy->created_by,
            'submitted_by' => $policy->submitted_by,
            'published_by' => $policy->published_by,
            'checksum' => $policy->checksum,
            'change_reason' => $policy->change_reason,
            'rules' => $policy->rules->map(fn (TaxPolicyRule|CommissionPolicyRule $rule) => $rule->only([
                'id', 'code', 'component', 'jurisdiction', 'owner_type', 'rate_basis_points', 'priority', 'applicability',
            ]))->values(),
        ];
    }
}
