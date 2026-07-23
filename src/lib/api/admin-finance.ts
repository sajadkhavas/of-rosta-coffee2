import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";

const identifier = z.string().trim().min(1).max(240);
const nullableIdentifier = identifier.nullable().optional();
const nullableText = (max: number) =>
  z.string().trim().max(max).nullable().optional();
const nullableDate = z
  .string()
  .refine((value) => Number.isFinite(Date.parse(value)), "زمان نامعتبر است.")
  .nullable()
  .optional();
const money = z.number().int().positive().max(Number.MAX_SAFE_INTEGER);

export const refundStatusSchema = z.enum([
  "requested",
  "approved",
  "processing",
  "succeeded",
  "failed",
  "cancelled",
  "requires_review",
]);

export const reconciliationStatusSchema = z.enum([
  "open",
  "investigating",
  "resolved",
  "dismissed",
]);

const refundSchema = z
  .object({
    id: identifier,
    order_id: identifier,
    order_number: nullableText(120),
    payment_attempt_id: identifier,
    payment_reference_id: nullableText(190),
    status: refundStatusSchema,
    provider: z.string().trim().min(1).max(64),
    amount: money,
    currency: z.literal("IRR"),
    reason: z.string().trim().min(1).max(2_000),
    provider_reference: nullableText(190),
    provider_code: nullableText(160),
    failure_code: nullableText(160),
    failure_message: nullableText(1_000),
    requested_by: identifier,
    approved_by: nullableIdentifier,
    resolved_by: nullableIdentifier,
    approved_at: nullableDate,
    processing_at: nullableDate,
    succeeded_at: nullableDate,
    failed_at: nullableDate,
    cancelled_at: nullableDate,
    created_at: nullableDate,
  })
  .strict();

const safeDetailsSchema = z.record(z.string(), z.unknown()).nullable().optional();

const reconciliationCaseSchema = z
  .object({
    id: identifier,
    order_id: identifier,
    order_number: nullableText(120),
    payment_attempt_id: nullableIdentifier,
    refund_attempt_id: nullableIdentifier,
    kind: z.string().trim().min(1).max(80),
    status: reconciliationStatusSchema,
    severity: z.enum(["low", "medium", "high", "critical"]),
    summary: z.string().trim().min(1).max(1_000),
    details: safeDetailsSchema,
    resolution: nullableText(5_000),
    opened_by: nullableIdentifier,
    resolved_by: nullableIdentifier,
    opened_at: nullableDate,
    resolved_at: nullableDate,
  })
  .strict();

const paginationSchema = z
  .object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive().max(100),
    total: z.number().int().nonnegative(),
  })
  .strict();

function listResourceSchema<T extends z.ZodTypeAny>(item: T) {
  return z
    .object({
      data: z
        .object({
          items: z.array(item).max(100),
          pagination: paginationSchema,
        })
        .strict(),
    })
    .passthrough();
}

function itemResourceSchema<T extends z.ZodTypeAny>(item: T) {
  return z.object({ data: item }).passthrough();
}

const refundListSchema = listResourceSchema(refundSchema);
const reconciliationListSchema = listResourceSchema(reconciliationCaseSchema);
const refundResourceSchema = itemResourceSchema(refundSchema);
const reconciliationResourceSchema = itemResourceSchema(
  reconciliationCaseSchema,
);

export type AdminRefundStatus = z.infer<typeof refundStatusSchema>;
export type AdminRefund = z.infer<typeof refundSchema>;
export type AdminReconciliationStatus = z.infer<
  typeof reconciliationStatusSchema
>;
export type AdminReconciliationCase = z.infer<
  typeof reconciliationCaseSchema
>;
export type FinancePagination = z.infer<typeof paginationSchema>;

export interface FinanceList<T> {
  items: T[];
  pagination: FinancePagination;
}

export interface CreateRefundInput {
  orderId: string;
  amount?: number;
  reason: string;
  idempotencyKey: string;
}

export interface ResolveRefundInput {
  refundId: string;
  outcome: "succeeded" | "failed" | "cancelled";
  providerReference?: string;
  failureCode?: string;
  message?: string;
}

export interface ResolveReconciliationInput {
  caseId: string;
  status: "resolved" | "dismissed";
  resolution: string;
}

export async function listAdminRefunds(
  status?: AdminRefundStatus | "all",
): Promise<FinanceList<AdminRefund>> {
  const query = new URLSearchParams({ per_page: "100" });
  if (status && status !== "all") query.set("status", status);
  const payload = refundListSchema.parse(
    await apiFetch<unknown>(`/admin/finance/refunds?${query.toString()}`),
  );
  return payload.data;
}

export async function listAdminReconciliationCases(
  status?: AdminReconciliationStatus | "all",
): Promise<FinanceList<AdminReconciliationCase>> {
  const query = new URLSearchParams({ per_page: "100" });
  if (status && status !== "all") query.set("status", status);
  const payload = reconciliationListSchema.parse(
    await apiFetch<unknown>(
      `/admin/finance/reconciliation?${query.toString()}`,
    ),
  );
  return payload.data;
}

export async function createAdminRefund(
  input: CreateRefundInput,
): Promise<AdminRefund> {
  const orderId = input.orderId.trim();
  if (!orderId) throw new Error("شناسه سفارش الزامی است.");
  const payload = refundResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/orders/${encodeURIComponent(orderId)}/refunds`,
      {
        method: "POST",
        body: {
          ...(input.amount ? { amount: input.amount } : {}),
          reason: input.reason.trim(),
          idempotency_key: input.idempotencyKey.trim(),
        },
      },
    ),
  );
  return payload.data;
}

export async function approveAdminRefund(
  refundId: string,
): Promise<AdminRefund> {
  const payload = refundResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/refunds/${encodeURIComponent(refundId)}/approve`,
      { method: "POST" },
    ),
  );
  return payload.data;
}

export async function dispatchAdminRefund(
  refundId: string,
): Promise<AdminRefund> {
  const payload = refundResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/refunds/${encodeURIComponent(refundId)}/dispatch`,
      { method: "POST" },
    ),
  );
  return payload.data;
}

export async function resolveAdminRefund(
  input: ResolveRefundInput,
): Promise<AdminRefund> {
  const payload = refundResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/refunds/${encodeURIComponent(input.refundId)}/resolve`,
      {
        method: "POST",
        body: {
          outcome: input.outcome,
          provider_reference: input.providerReference?.trim() || null,
          failure_code: input.failureCode?.trim() || null,
          message: input.message?.trim() || null,
        },
      },
    ),
  );
  return payload.data;
}

export async function resolveAdminReconciliationCase(
  input: ResolveReconciliationInput,
): Promise<AdminReconciliationCase> {
  const payload = reconciliationResourceSchema.parse(
    await apiFetch<unknown>(
      `/admin/finance/reconciliation/${encodeURIComponent(input.caseId)}`,
      {
        method: "PATCH",
        body: {
          status: input.status,
          resolution: input.resolution.trim(),
        },
      },
    ),
  );
  return payload.data;
}

export function adminRefundsQueryOptions(
  status: AdminRefundStatus | "all" = "all",
) {
  return queryOptions({
    queryKey: ["admin", "finance", "refunds", status],
    queryFn: () => listAdminRefunds(status),
    staleTime: 15_000,
  });
}

export function adminReconciliationQueryOptions(
  status: AdminReconciliationStatus | "all" = "open",
) {
  return queryOptions({
    queryKey: ["admin", "finance", "reconciliation", status],
    queryFn: () => listAdminReconciliationCases(status),
    staleTime: 15_000,
  });
}
