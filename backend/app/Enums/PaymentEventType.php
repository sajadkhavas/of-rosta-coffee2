<?php

namespace App\Enums;

enum PaymentEventType: string
{
    case Requested = 'requested';
    case RedirectIssued = 'redirect_issued';
    case CallbackReceived = 'callback_received';
    case VerificationRequested = 'verification_requested';
    case VerifiedPaid = 'verified_paid';
    case VerifiedFailed = 'verified_failed';
    case VerifiedCancelled = 'verified_cancelled';
    case RefundRequested = 'refund_requested';
    case Refunded = 'refunded';
    case ReconciliationRequired = 'reconciliation_required';
}
