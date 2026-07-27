import type { OrderStatus, SubOrderStatus } from "@/lib/api/contracts";

const dateTimeFormatter = new Intl.DateTimeFormat("fa-IR", {
  year: "numeric",
  month: "long",
  day: "numeric",
  hour: "2-digit",
  minute: "2-digit",
});

export function formatAccountDate(value?: string | null): string {
  if (!value) return "ثبت نشده";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : dateTimeFormatter.format(date);
}

export const orderStatusLabels: Record<OrderStatus, string> = {
  draft: "پیش‌نویس",
  awaiting_payment: "در انتظار پرداخت",
  paid: "پرداخت‌شده",
  processing: "در حال پردازش",
  partially_shipped: "بخشی ارسال‌شده",
  shipped: "ارسال‌شده",
  partially_delivered: "بخشی تحویل‌شده",
  delivered: "تحویل‌شده",
  partially_cancelled: "بخشی لغوشده",
  cancelled: "لغوشده",
  refunded: "بازپرداخت‌شده",
};

export const subOrderStatusLabels: Record<SubOrderStatus, string> = {
  awaiting_payment: "در انتظار پرداخت",
  pending_acceptance: "در انتظار پرداخت (قدیمی)",
  accepted: "تأییدشده",
  rejected: "ردشده",
  preparing: "در حال آماده‌سازی",
  ready_to_ship: "آماده ارسال",
  shipped: "ارسال‌شده",
  delivered: "تحویل‌شده",
  cancelled: "لغوشده",
  refund_pending: "در انتظار بازپرداخت",
  refunded: "بازپرداخت‌شده",
};

export function statusBadgeClass(status: OrderStatus | SubOrderStatus): string {
  if (["delivered", "paid", "accepted"].includes(status)) {
    return "border-emerald-400/40 bg-emerald-950/30 text-emerald-300";
  }
  if (["cancelled", "rejected", "partially_cancelled"].includes(status)) {
    return "border-red-400/40 bg-red-950/30 text-red-300";
  }
  if (["refunded", "refund_pending"].includes(status)) {
    return "border-blue-400/40 bg-blue-950/30 text-blue-300";
  }
  return "border-amber-400/40 bg-amber-950/30 text-amber-300";
}
