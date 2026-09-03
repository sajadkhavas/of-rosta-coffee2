<?php

namespace Tests\Feature\Growth;

use App\Exceptions\ApiDomainException;
use App\Models\AuditLog;
use App\Models\GrowthLead;
use App\Models\GrowthPartner;
use App\Models\PartnerAttribution;
use App\Models\User;
use App\Services\Growth\GrowthLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GrowthLeadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_partner_can_claim_normalized_lead_without_pii_in_audit_metadata(): void
    {
        $partner = $this->activePartner('09120000001', 'partner-1@example.test', 'partner-one');

        $lead = $this->service()->claim($partner, [
            'type' => GrowthLead::TYPE_CUSTOMER,
            'name' => 'مشتری تست',
            'mobile' => '+989121234567',
            'email' => 'LEAD@example.test',
            'meta' => [
                'campaign' => 'launch-01',
                'mobile' => '09129999999',
            ],
        ], $partner->user);

        $this->assertSame('09121234567', $lead->mobile);
        $this->assertSame('lead@example.test', $lead->email);
        $this->assertSame(GrowthLead::STATUS_LEAD, $lead->status);
        $this->assertSame(['campaign' => 'launch-01'], $lead->meta);

        $audit = AuditLog::query()->where('action', 'growth.lead.claimed')->firstOrFail();
        $auditPayload = json_encode($audit->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('09121234567', $auditPayload);
        $this->assertStringNotContainsString('lead@example.test', $auditPayload);
        $this->assertSame((string) $partner->id, $audit->metadata['partner_id']);
        $this->assertSame((string) $lead->id, $audit->metadata['lead_id']);
    }

    public function test_same_partner_duplicate_claim_is_idempotent(): void
    {
        $partner = $this->activePartner('09120000002', 'partner-2@example.test', 'partner-two');

        $first = $this->service()->claim($partner, [
            'type' => GrowthLead::TYPE_CUSTOMER,
            'mobile' => '09121234568',
        ]);

        $second = $this->service()->claim($partner, [
            'type' => GrowthLead::TYPE_CUSTOMER,
            'mobile' => '00989121234568',
            'name' => 'نباید رکورد دوم بسازد',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, GrowthLead::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'growth.lead.claimed')->count());
    }

    public function test_other_partner_cannot_take_an_already_claimed_identity(): void
    {
        $firstPartner = $this->activePartner('09120000003', 'partner-3@example.test', 'partner-three');
        $secondPartner = $this->activePartner('09120000004', 'partner-4@example.test', 'partner-four');

        $this->service()->claim($firstPartner, [
            'type' => GrowthLead::TYPE_ROASTERY,
            'mobile' => '09121234569',
        ]);

        try {
            $this->service()->claim($secondPartner, [
                'type' => GrowthLead::TYPE_ROASTERY,
                'mobile' => '+989121234569',
            ]);
            $this->fail('A claimed lead must not be reassigned to another partner.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('growth.lead_already_claimed', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }

        $this->assertSame(1, GrowthLead::query()->count());
    }

    public function test_pending_partner_and_exact_self_referral_are_blocked(): void
    {
        $user = User::factory()->create([
            'mobile' => '09121234570',
            'email' => 'self@example.test',
        ]);

        $pending = GrowthPartner::query()->create([
            'user_id' => $user->id,
            'code' => 'pending-partner',
            'status' => GrowthPartner::STATUS_PENDING,
        ]);

        try {
            $this->service()->claim($pending, [
                'type' => GrowthLead::TYPE_CUSTOMER,
                'mobile' => '09123334444',
            ]);
            $this->fail('A pending partner must not claim leads.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('growth.partner_not_active', $exception->errorCode);
        }

        $pending->update([
            'status' => GrowthPartner::STATUS_ACTIVE,
            'terms_version' => 'ps11-v1',
            'terms_accepted_at' => now(),
            'activated_at' => now(),
        ]);

        foreach ([
            ['mobile' => '+989121234570'],
            ['email' => 'SELF@example.test'],
        ] as $identity) {
            try {
                $this->service()->claim($pending->fresh(), [
                    'type' => GrowthLead::TYPE_CUSTOMER,
                    ...$identity,
                ]);
                $this->fail('Exact self-referral must be blocked.');
            } catch (ApiDomainException $exception) {
                $this->assertSame('growth.self_referral_forbidden', $exception->errorCode);
            }
        }

        $this->assertSame(0, GrowthLead::query()->count());
    }

    public function test_attribution_is_idempotent_for_same_partner_and_exclusive_across_partners(): void
    {
        $firstPartner = $this->activePartner('09120000005', 'partner-5@example.test', 'partner-five');
        $secondPartner = $this->activePartner('09120000006', 'partner-6@example.test', 'partner-six');
        $subject = User::factory()->create();

        $lead = $this->service()->claim($firstPartner, [
            'type' => GrowthLead::TYPE_CUSTOMER,
            'mobile' => '09121234571',
        ]);

        $first = $this->service()->attribute(
            $firstPartner,
            'user',
            (string) $subject->id,
            $lead,
            $firstPartner->user,
            ['campaign' => 'launch-02', 'email' => 'drop-me@example.test'],
        );

        $second = $this->service()->attribute(
            $firstPartner,
            'user',
            (string) $subject->id,
            $lead,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(['campaign' => 'launch-02'], $first->context);
        $this->assertSame(1, PartnerAttribution::query()->count());

        try {
            $this->service()->attribute($secondPartner, 'user', (string) $subject->id);
            $this->fail('An attributed subject must not be reassigned.');
        } catch (ApiDomainException $exception) {
            $this->assertSame('growth.attribution_already_claimed', $exception->errorCode);
            $this->assertSame(409, $exception->status);
        }
    }

    private function service(): GrowthLeadService
    {
        return $this->app->make(GrowthLeadService::class);
    }

    private function activePartner(string $mobile, string $email, string $code): GrowthPartner
    {
        $user = User::factory()->create([
            'mobile' => $mobile,
            'email' => $email,
        ]);

        return GrowthPartner::query()->create([
            'user_id' => $user->id,
            'code' => $code,
            'status' => GrowthPartner::STATUS_ACTIVE,
            'terms_version' => 'ps11-v1',
            'terms_accepted_at' => now(),
            'activated_at' => now(),
        ]);
    }
}
