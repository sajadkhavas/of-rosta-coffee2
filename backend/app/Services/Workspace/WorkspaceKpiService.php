<?php

namespace App\Services\Workspace;

use App\Enums\NotificationStatus;
use App\Enums\ProductStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\RoasteryStatus;
use App\Enums\SubOrderStatus;
use App\Models\FinancialReconciliationCase;
use App\Models\FulfillmentIncident;
use App\Models\NotificationOutbox;
use App\Models\Product;
use App\Models\Roastery;
use App\Models\SubOrder;

final class WorkspaceKpiService
{
    /**
     * KPI values are authoritative server-side aggregates. The browser may format
     * and present these values, but must not reconstruct them from queue lengths.
     *
     * @return array<string, int>
     */
    public function seller(Roastery $roastery): array
    {
        $scope = SubOrder::query()->where('roastery_id', $roastery->id);

        return [
            'pending_acceptance' => (clone $scope)
                ->where('status', SubOrderStatus::PendingAcceptance->value)
                ->count(),
            'active_fulfillment' => (clone $scope)
                ->whereIn('status', [
                    SubOrderStatus::Accepted->value,
                    SubOrderStatus::Preparing->value,
                    SubOrderStatus::ReadyToShip->value,
                ])
                ->count(),
            'active_shipping' => (clone $scope)
                ->where('status', SubOrderStatus::Shipped->value)
                ->count(),
            'open_incidents' => FulfillmentIncident::query()
                ->where('roastery_id', $roastery->id)
                ->where('status', 'open')
                ->count(),
        ];
    }

    /** @return array<string, int> */
    public function admin(): array
    {
        return [
            'pending_roasteries' => Roastery::query()
                ->where('status', RoasteryStatus::Pending->value)
                ->count(),
            'products_in_review' => Product::query()
                ->where('status', ProductStatus::Review->value)
                ->count(),
            'open_fulfillment_incidents' => FulfillmentIncident::query()
                ->where('status', 'open')
                ->count(),
            'failed_notifications' => NotificationOutbox::query()
                ->where('status', NotificationStatus::Failed->value)
                ->count(),
            'open_financial_reconciliation' => FinancialReconciliationCase::query()
                ->whereIn('status', [
                    ReconciliationStatus::Open->value,
                    ReconciliationStatus::Investigating->value,
                ])
                ->count(),
        ];
    }
}
