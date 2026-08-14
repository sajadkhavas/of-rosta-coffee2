<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Exceptions\ApiDomainException;
use App\Models\CommissionPolicy;
use App\Models\TaxPolicy;
use App\Models\User;
use App\Services\Finance\FinancialTruthEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class FinancialTruthPolicyTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_production_mode_fails_closed_without_an_effective_policy_pair(): void
    {
        config()->set('rosta.finance.require_policies', true);

        $this->expectException(ApiDomainException::class);
        app(FinancialTruthEngine::class)->calculate([
            ['key' => 'product:1', 'component' => 'product', 'owner_type' => 'roastery', 'gross_amount' => 100],
        ], now()->toImmutable());
    }

    public function test_dual_control_publishes_immutable_effective_policies_and_engine_conserves_money(): void
    {
        $author = User::factory()->create();
        $approver = User::factory()->create();
        $this->authenticateWithRole($author, Role::Administrator);

        $tax = $this->postJson('/api/v1/admin/finance/tax-policies', $this->taxPayload())
            ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');
        $this->postJson('/api/v1/admin/finance/tax-policies/'.$tax['id'].'/submit')
            ->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->postJson('/api/v1/admin/finance/tax-policies/'.$tax['id'].'/publish')
            ->assertConflict()->assertJsonPath('error.code', 'finance.policy_dual_control');

        $commission = $this->postJson('/api/v1/admin/finance/commission-policies', $this->commissionPayload())
            ->assertCreated()->json('data');
        $this->postJson('/api/v1/admin/finance/commission-policies/'.$commission['id'].'/submit')->assertOk();

        $this->authenticateWithRole($approver, Role::Administrator);
        $this->postJson('/api/v1/admin/finance/tax-policies/'.$tax['id'].'/publish')
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->postJson('/api/v1/admin/finance/commission-policies/'.$commission['id'].'/publish')
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->patchJson('/api/v1/admin/finance/tax-policies/'.$tax['id'], $this->taxPayload())
            ->assertConflict()->assertJsonPath('error.code', 'finance.policy_immutable');

        config()->set('rosta.finance.require_policies', true);
        $result = app(FinancialTruthEngine::class)->calculate([
            [
                'key' => 'product:1',
                'component' => 'product',
                'owner_type' => 'roastery',
                'gross_amount' => 10_100,
                'discount_amount' => 100,
            ],
        ], now()->toImmutable());

        $this->assertSame(1_000, $result['totals']['tax']);
        $this->assertSame(500, $result['totals']['commission']);
        $this->assertSame(10_500, $result['totals']['payable']);
        $this->assertSame(
            $result['totals']['gross'] - $result['totals']['discount'] + $result['totals']['tax'],
            $result['totals']['payable'] + $result['totals']['commission'],
        );
        $this->assertSame('authoritative', $result['snapshot']['status']);
        $this->assertNotNull(TaxPolicy::query()->firstOrFail()->checksum);
        $this->assertNotNull(CommissionPolicy::query()->firstOrFail()->checksum);
        $this->assertDatabaseCount('audit_logs', 6);
    }

    /** @return array<string, mixed> */
    private function taxPayload(): array
    {
        return [
            'currency' => 'IRR',
            'rounding_mode' => 'half_up',
            'effective_from' => now()->subMinute()->toIso8601String(),
            'effective_to' => now()->addYear()->toIso8601String(),
            'change_reason' => 'Fixture-only rate for deterministic boundary verification.',
            'rules' => [[
                'code' => 'fixture.product.tax',
                'component' => 'product',
                'jurisdiction' => 'fixture-only',
                'rate_basis_points' => 1_000,
                'priority' => 10,
                'applicability' => null,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function commissionPayload(): array
    {
        return [
            'currency' => 'IRR',
            'rounding_mode' => 'half_up',
            'effective_from' => now()->subMinute()->toIso8601String(),
            'effective_to' => now()->addYear()->toIso8601String(),
            'change_reason' => 'Fixture-only rate for deterministic boundary verification.',
            'rules' => [[
                'code' => 'fixture.product.commission',
                'component' => 'product',
                'owner_type' => 'roastery',
                'rate_basis_points' => 500,
                'priority' => 10,
                'applicability' => null,
            ]],
        ];
    }
}
