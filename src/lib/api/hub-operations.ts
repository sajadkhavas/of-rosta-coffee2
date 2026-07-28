import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";

const id = z.string().trim().min(1).max(240);
const date = z.string().nullable().optional();
export const hubWorkStatusSchema = z.enum([
  "awaiting_inbound",
  "received",
  "assigned",
  "grinding",
  "quality_check",
  "rework_required",
  "packaging",
  "ready_for_outbound",
  "handed_off",
  "cancelled",
]);
export const hubActionSchema = z.enum([
  "receive",
  "start_grinding",
  "submit_quality_check",
  "quality_pass",
  "quality_fail",
  "restart_grinding",
  "mark_ready",
  "handoff",
  "cancel",
]);
const legSchema = z
  .object({
    id,
    route_type: z.string(),
    status: z.string(),
    tracking_code: z.string().nullable().optional(),
    delivered_at: date,
    picked_up_at: date,
  })
  .strict()
  .nullable();
const actionSchema = z
  .object({
    id,
    action: z.string(),
    from_status: z.string().nullable().optional(),
    to_status: z.string(),
    public_label: z.string(),
    actor: z.object({ id, name: z.string().nullable().optional() }).strict().nullable(),
    occurred_at: z.string(),
  })
  .strict();
const workItemSchema = z
  .object({
    id,
    order_id: id,
    order_number: z.string().nullable().optional(),
    sub_order_id: id,
    order_item_service_id: id,
    hub: z
      .object({
        id,
        code: z.string().nullable().optional(),
        name: z.string().nullable().optional(),
      })
      .strict(),
    status: hubWorkStatusSchema,
    public_label: z.string(),
    revision: z.number().int().nonnegative(),
    assigned_operator: z.object({ id, name: z.string().nullable().optional() }).strict().nullable(),
    profile: z
      .object({ id, name: z.string(), version: z.number().int().positive() })
      .strict()
      .nullable(),
    weight_grams: z.number().int().positive(),
    quantity: z.number().int().positive(),
    inbound_leg: legSchema,
    outbound_leg: legSchema,
    received_at: date,
    assigned_at: date,
    grinding_started_at: date,
    quality_checked_at: date,
    ready_at: date,
    handed_off_at: date,
    actions: z.array(actionSchema).max(200),
  })
  .strict();
const paginationSchema = z
  .object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive(),
    total: z.number().int().nonnegative(),
  })
  .strict();
const adminListSchema = z
  .object({
    data: z
      .object({ items: z.array(workItemSchema).max(100), pagination: paginationSchema })
      .strict(),
  })
  .passthrough();
const operatorListSchema = z
  .object({ data: z.object({ items: z.array(workItemSchema).max(100) }).strict() })
  .passthrough();
const itemSchema = z.object({ data: workItemSchema }).passthrough();
const operatorsSchema = z
  .object({
    data: z
      .object({
        items: z.array(z.object({ id, name: z.string().nullable().optional() }).strict()).max(200),
      })
      .strict(),
  })
  .passthrough();

export type HubWorkStatus = z.infer<typeof hubWorkStatusSchema>;
export type HubAction = z.infer<typeof hubActionSchema>;
export type HubWorkItem = z.infer<typeof workItemSchema>;
export type HubOperator = z.infer<typeof operatorsSchema>["data"]["items"][number];

export async function listHubWorkItems(
  isAdmin: boolean,
  status?: HubWorkStatus | "all",
): Promise<HubWorkItem[]> {
  const query = new URLSearchParams();
  if (status && status !== "all") query.set("status", status);
  query.set("per_page", "100");
  const path = isAdmin ? `/admin/hub-operations/work-items?${query}` : "/hub-operations/work-items";
  const raw = await apiFetch<unknown>(path);
  return isAdmin ? adminListSchema.parse(raw).data.items : operatorListSchema.parse(raw).data.items;
}
export async function listHubOperators(): Promise<HubOperator[]> {
  return operatorsSchema.parse(await apiFetch<unknown>("/admin/hub-operations/operators")).data
    .items;
}
export async function assignHubWorkItem(input: {
  workItemId: string;
  operatorId: string;
  note?: string;
}): Promise<HubWorkItem> {
  return itemSchema.parse(
    await apiFetch<unknown>(
      `/admin/hub-operations/work-items/${encodeURIComponent(input.workItemId)}/assign`,
      {
        method: "POST",
        body: {
          operator_id: input.operatorId,
          idempotency_key: `hub-assign:${input.workItemId}:${crypto.randomUUID()}`,
          note: input.note?.trim() || null,
        },
      },
    ),
  ).data;
}
export async function transitionHubWorkItem(input: {
  workItemId: string;
  action: HubAction;
  isAdmin: boolean;
  note?: string;
}): Promise<HubWorkItem> {
  const prefix = input.isAdmin ? "/admin/hub-operations" : "/hub-operations";
  return itemSchema.parse(
    await apiFetch<unknown>(
      `${prefix}/work-items/${encodeURIComponent(input.workItemId)}/transition`,
      {
        method: "POST",
        body: {
          action: input.action,
          idempotency_key: `hub-transition:${input.workItemId}:${input.action}:${crypto.randomUUID()}`,
          evidence: input.note ? { note: input.note.trim() } : null,
        },
      },
    ),
  ).data;
}
export const hubWorkItemsQueryOptions = (isAdmin: boolean, status: HubWorkStatus | "all") =>
  queryOptions({
    queryKey: ["hub-operations", isAdmin ? "admin" : "operator", status],
    queryFn: () => listHubWorkItems(isAdmin, status),
    staleTime: 10_000,
  });
export const hubOperatorsQueryOptions = () =>
  queryOptions({
    queryKey: ["hub-operations", "operators"],
    queryFn: listHubOperators,
    staleTime: 60_000,
  });
