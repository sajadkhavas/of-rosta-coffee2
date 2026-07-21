<?php

namespace App\Services\Payments;

use App\Enums\LedgerAccount;
use App\Enums\LedgerTransactionType;
use App\Exceptions\ApiDomainException;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;

final class LedgerService
{
    public function recordSale(PaymentAttempt $payment, Order $order): LedgerTransaction
    {
        $reference = 'sale:'.$payment->id;
        $existing = LedgerTransaction::query()
            ->where('reference_key', $reference)
            ->first();

        if ($existing instanceof LedgerTransaction) {
            return $existing->load('entries');
        }

        $commission = $this->percentage(
            $payment->amount,
            (int) config('rosta.payments.platform_commission_basis_points', 1000),
        );
        $sellerPayable = $payment->amount - $commission;

        return DB::transaction(function () use (
            $payment,
            $order,
            $reference,
            $commission,
            $sellerPayable,
        ): LedgerTransaction {
            $transaction = LedgerTransaction::query()->create([
                'order_id' => $order->id,
                'payment_attempt_id' => $payment->id,
                'type' => LedgerTransactionType::Sale,
                'reference_key' => $reference,
                'currency' => $payment->currency,
                'metadata' => [
                    'gross' => $payment->amount,
                    'commission' => $commission,
                    'seller_payable' => $sellerPayable,
                ],
            ]);

            $entries = [[
                'account' => LedgerAccount::GatewayClearing,
                'debit' => $payment->amount,
                'credit' => 0,
                'roastery_id' => null,
            ]];

            if ($commission > 0) {
                $entries[] = [
                    'account' => LedgerAccount::PlatformRevenue,
                    'debit' => 0,
                    'credit' => $commission,
                    'roastery_id' => null,
                ];
            }

            if ($sellerPayable > 0) {
                $entries[] = [
                    'account' => LedgerAccount::SellerPayable,
                    'debit' => 0,
                    'credit' => $sellerPayable,
                    'roastery_id' => $order->roastery_id,
                ];
            }

            $this->appendBalanced($transaction, $entries);

            return $transaction->load('entries');
        });
    }

    public function recordRefund(
        PaymentRefund $refund,
        PaymentAttempt $payment,
        Order $order,
    ): LedgerTransaction {
        $reference = 'refund:'.$refund->id;
        $existing = LedgerTransaction::query()
            ->where('reference_key', $reference)
            ->first();

        if ($existing instanceof LedgerTransaction) {
            return $existing->load('entries');
        }

        if ($refund->amount !== $payment->amount) {
            throw new ApiDomainException(
                'refund.partial_not_supported',
                'در نسخه فعلی فقط بازگشت کامل وجه پشتیبانی می‌شود.',
                409,
            );
        }

        $commission = $this->percentage(
            $refund->amount,
            (int) config('rosta.payments.platform_commission_basis_points', 1000),
        );
        $sellerPayable = $refund->amount - $commission;

        return DB::transaction(function () use (
            $refund,
            $payment,
            $order,
            $reference,
            $commission,
            $sellerPayable,
        ): LedgerTransaction {
            $transaction = LedgerTransaction::query()->create([
                'order_id' => $order->id,
                'payment_attempt_id' => $payment->id,
                'type' => LedgerTransactionType::Refund,
                'reference_key' => $reference,
                'currency' => $refund->currency,
                'metadata' => [
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                ],
            ]);

            $entries = [[
                'account' => LedgerAccount::GatewayClearing,
                'debit' => 0,
                'credit' => $refund->amount,
                'roastery_id' => null,
            ]];

            if ($commission > 0) {
                $entries[] = [
                    'account' => LedgerAccount::PlatformRevenue,
                    'debit' => $commission,
                    'credit' => 0,
                    'roastery_id' => null,
                ];
            }

            if ($sellerPayable > 0) {
                $entries[] = [
                    'account' => LedgerAccount::SellerPayable,
                    'debit' => $sellerPayable,
                    'credit' => 0,
                    'roastery_id' => $order->roastery_id,
                ];
            }

            $this->appendBalanced($transaction, $entries);

            return $transaction->load('entries');
        });
    }

    /**
     * @param list<array{account: LedgerAccount, debit: int, credit: int, roastery_id: string|null}> $entries
     */
    private function appendBalanced(
        LedgerTransaction $transaction,
        array $entries,
    ): void {
        $debits = array_sum(array_column($entries, 'debit'));
        $credits = array_sum(array_column($entries, 'credit'));

        if ($debits !== $credits || $debits <= 0) {
            throw new ApiDomainException(
                'ledger.unbalanced',
                'تراکنش دفتر مالی تراز نیست.',
                500,
            );
        }

        foreach ($entries as $entry) {
            LedgerEntry::query()->create([
                'transaction_id' => $transaction->id,
                'roastery_id' => $entry['roastery_id'],
                'account' => $entry['account'],
                'debit' => $entry['debit'],
                'credit' => $entry['credit'],
                'currency' => $transaction->currency,
                'metadata' => [],
            ]);
        }
    }

    private function percentage(int $amount, int $basisPoints): int
    {
        $safeBasisPoints = max(0, min(10_000, $basisPoints));
        $whole = intdiv($amount, 10_000) * $safeBasisPoints;
        $remainder = intdiv(($amount % 10_000) * $safeBasisPoints, 10_000);

        return $whole + $remainder;
    }
}
