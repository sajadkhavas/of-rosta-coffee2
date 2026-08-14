<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SellerOrganizationOpenApiTest extends TestCase
{
    public function test_seller_organization_openapi_covers_routes_roles_and_pricing_boundary(): void
    {
        $path = dirname(__DIR__, 3).'/docs/openapi/rosta-v1-seller-organization.yaml';
        $contract = file_get_contents($path);
        $this->assertIsString($contract);

        foreach ([
            '/seller/invitations/accept:',
            '/seller/roasteries/{roasteryId}/organization:',
            '/seller/roasteries/{roasteryId}/members:',
            '/seller/roasteries/{roasteryId}/members/{membershipId}:',
            '/seller/roasteries/{roasteryId}/invitations:',
            '/seller/roasteries/{roasteryId}/invitations/{inviteId}:',
            '/seller/roasteries/{roasteryId}/schedule:',
            '/seller/roasteries/{roasteryId}/closures:',
            '/seller/roasteries/{roasteryId}/closures/{closureId}:',
            '/seller/roasteries/{roasteryId}/promotions:',
            '/seller/roasteries/{roasteryId}/promotions/{promotionId}:',
            '/admin/seller-organizations/roasteries/{roasteryId}:',
            '/admin/seller-organizations/invitations/{inviteId}/revoke:',
            '/admin/seller-organizations/memberships/{membershipId}/lock:',
            'enum: [owner, manager, catalog, fulfillment, finance, support]',
            'enum: [draft, scheduled, paused, expired]',
            'new_orders_blocked_by_temporary_closure',
            'impersonation is false',
        ] as $needle) {
            $this->assertStringContainsString($needle, $contract, 'Missing OpenAPI contract: '.$needle);
        }

        $promotionStart = strpos($contract, '    PromotionInput:');
        $this->assertNotFalse($promotionStart);
        $promotionSection = substr($contract, $promotionStart);
        foreach (['discount:', 'discount_percent:', 'price:', 'amount:', 'coupon:', 'rate:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $promotionSection);
        }
        $this->assertStringContainsString('additionalProperties: false', $promotionSection);
    }
}
