<?php

namespace App\Observers;

use App\Enums\RefundStatus;
use App\Models\RefundAttempt;
use App\Services\Growth\PartnerCommissionEventService;

final class RefundAttemptObserver
{
    public function __construct(
        private readonly PartnerCommissionEventService $commissionEvents,
    ) {}

    public function updated(RefundAttempt $refund): void
    {
        if (! $refund->wasChanged('status') || $refund->status !== RefundStatus::Succeeded) {
            return;
        }

        $this->commissionEvents->recordSuccessfulRefund($refund);
    }
}
