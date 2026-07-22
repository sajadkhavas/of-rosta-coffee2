import type {
  CurrencyCode,
  OrderStatus,
  PaymentStatus,
} from "@/lib/api/contracts";

export interface PaymentExpectationShape {
  paymentId: string;
  orderId: string;
  amount: number;
  currency: CurrencyCode;
}

export interface VerifiedPaymentResult {
  paymentId: string;
  status: PaymentStatus;
  orderId: string;
  orderStatus: OrderStatus;
  amount: number;
  currency: CurrencyCode;
  verifiedAt: string | null;
}

const PAID_ORDER_STATUSES = new Set<OrderStatus>([
  "paid",
  "processing",
  "partially_shipped",
  "shipped",
  "partially_delivered",
  "delivered",
  "partially_cancelled",
  "refund_pending",
]);

export function isConsistentVerifiedPaid(
  result: VerifiedPaymentResult,
  expectation: PaymentExpectationShape | null,
): boolean {
  if (!expectation) return false;
  return (
    result.status === "paid" &&
    PAID_ORDER_STATUSES.has(result.orderStatus) &&
    Boolean(result.verifiedAt) &&
    result.paymentId === expectation.paymentId &&
    result.orderId === expectation.orderId &&
    result.amount === expectation.amount &&
    result.currency === expectation.currency
  );
}
