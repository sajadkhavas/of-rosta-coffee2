import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";

export const sellerMembershipRoleSchema = z.enum([
  "owner",
  "manager",
  "catalog",
  "fulfillment",
  "finance",
  "support",
]);
const permissionSchema = z.enum([
  "workspace.read",
  "organization.read",
  "organization.manage",
  "catalog.read",
  "catalog.write",
  "inventory.read",
  "inventory.write",
  "orders.read",
  "fulfillment.write",
  "finance.read",
  "availability.read",
  "availability.write",
  "promotion.read",
  "promotion.write",
]);
const availabilitySchema = z
  .object({
    timezone: z.string().min(1).max(80),
    status: z.enum(["open", "outside_hours", "temporarily_closed"]),
    operating_now: z.boolean(),
    accepting_orders: z.boolean(),
    public_reason: z.string().max(180).nullable(),
    closed_until: z.string().nullable(),
    next_open_at: z.string().nullable(),
    order_policy: z.enum(["accepting_new_orders", "new_orders_blocked_by_temporary_closure"]),
  })
  .strict();
const memberSchema = z
  .object({
    id: z.string().min(1),
    user_id: z.string().min(1),
    name: z.string().max(120).nullable(),
    mobile: z.string().max(32).nullable(),
    role: sellerMembershipRoleSchema,
    is_locked: z.boolean(),
    created_at: z.string().nullable(),
  })
  .strict();
const invitationSchema = z
  .object({
    id: z.string().min(1),
    mobile: z.string().max(32).nullable(),
    role: sellerMembershipRoleSchema,
    status: z.enum(["pending", "accepted", "revoked", "expired"]),
    expires_at: z.string(),
    created_at: z.string().nullable(),
  })
  .strict();
const weeklyHourSchema = z
  .object({
    weekday: z.number().int().min(0).max(6),
    is_closed: z.boolean(),
    opens_at: z
      .string()
      .regex(/^\d{2}:\d{2}$/)
      .nullable(),
    closes_at: z
      .string()
      .regex(/^\d{2}:\d{2}$/)
      .nullable(),
  })
  .strict();
const exceptionSchema = z
  .object({
    local_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    is_closed: z.boolean(),
    opens_at: z
      .string()
      .regex(/^\d{2}:\d{2}$/)
      .nullable(),
    closes_at: z
      .string()
      .regex(/^\d{2}:\d{2}$/)
      .nullable(),
    public_reason: z.string().max(180).nullable(),
  })
  .strict();
const closureSchema = z
  .object({
    id: z.string().min(1),
    starts_at: z.string(),
    ends_at: z.string(),
    public_reason: z.string().max(180).nullable(),
    blocks_new_orders: z.boolean(),
    is_active: z.boolean(),
    revoked_at: z.string().nullable(),
  })
  .strict();
const promotionSchema = z
  .object({
    id: z.string().min(1),
    name: z.string().min(1).max(160),
    status: z.enum(["draft", "scheduled", "paused", "expired"]),
    starts_at: z.string().nullable(),
    ends_at: z.string().nullable(),
    pricing_applied: z.literal(false),
  })
  .strict();

const organizationSchema = z
  .object({
    data: z
      .object({
        roastery_id: z.string().min(1),
        timezone: z.string().min(1).max(80),
        role: sellerMembershipRoleSchema.nullable(),
        permissions: z.array(permissionSchema).max(30),
        availability: availabilitySchema,
        members: z.array(memberSchema).max(200),
        invitations: z.array(invitationSchema).max(100),
        weekly_hours: z.array(weeklyHourSchema).max(7),
        exceptions: z.array(exceptionSchema).max(365),
        closures: z.array(closureSchema).max(100),
        promotions: z.array(promotionSchema).max(100),
      })
      .strict(),
  })
  .passthrough();

export type SellerMembershipRole = z.infer<typeof sellerMembershipRoleSchema>;
export type SellerOrganization = z.infer<typeof organizationSchema>["data"];
export type WeeklyHourInput = z.infer<typeof weeklyHourSchema>;
export type ScheduleExceptionInput = z.infer<typeof exceptionSchema>;

export async function getSellerOrganization(roasteryId: string): Promise<SellerOrganization> {
  return organizationSchema.parse(
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/organization`),
  ).data;
}

export const sellerOrganizationQueryOptions = (roasteryId: string) =>
  queryOptions({
    queryKey: ["seller", "organization", roasteryId],
    queryFn: () => getSellerOrganization(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 10_000,
  });

export async function createSellerInvitation(
  roasteryId: string,
  mobile: string,
  role: SellerMembershipRole,
): Promise<{ token: string }> {
  const response = z
    .object({
      data: z.object({ token: z.string().regex(/^[a-f0-9]{64}$/), invitation: invitationSchema }),
    })
    .passthrough()
    .parse(
      await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/invitations`, {
        method: "POST",
        body: { mobile, role },
      }),
    );
  return { token: response.data.token };
}

export async function acceptSellerInvitation(token: string): Promise<void> {
  await apiFetch<unknown>("/seller/invitations/accept", {
    method: "POST",
    body: { token: token.trim().toLowerCase() },
  });
}

export async function revokeSellerInvitation(roasteryId: string, inviteId: string): Promise<void> {
  await apiFetch<unknown>(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/invitations/${encodeURIComponent(inviteId)}`,
    { method: "DELETE" },
  );
}

export async function updateSellerMemberRole(
  roasteryId: string,
  membershipId: string,
  role: SellerMembershipRole,
): Promise<void> {
  await apiFetch<unknown>(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/members/${encodeURIComponent(membershipId)}`,
    { method: "PATCH", body: { role } },
  );
}

export async function removeSellerMember(roasteryId: string, membershipId: string): Promise<void> {
  await apiFetch<unknown>(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/members/${encodeURIComponent(membershipId)}`,
    { method: "DELETE" },
  );
}

export async function updateSellerSchedule(
  roasteryId: string,
  timezone: string,
  weeklyHours: WeeklyHourInput[],
  exceptions: ScheduleExceptionInput[],
): Promise<void> {
  await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/schedule`, {
    method: "PUT",
    body: { timezone, weekly_hours: weeklyHours, exceptions },
  });
}

export async function createSellerClosure(
  roasteryId: string,
  input: { startsAt: string; endsAt: string; publicReason?: string; blocksNewOrders: boolean },
): Promise<void> {
  await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/closures`, {
    method: "POST",
    body: {
      starts_at: input.startsAt,
      ends_at: input.endsAt,
      public_reason: input.publicReason?.trim() || null,
      blocks_new_orders: input.blocksNewOrders,
    },
  });
}

export async function revokeSellerClosure(roasteryId: string, closureId: string): Promise<void> {
  await apiFetch<unknown>(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/closures/${encodeURIComponent(closureId)}`,
    { method: "DELETE" },
  );
}

export async function createSellerPromotion(
  roasteryId: string,
  input: { name: string; startsAt?: string; endsAt?: string },
): Promise<void> {
  await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/promotions`, {
    method: "POST",
    body: {
      name: input.name.trim(),
      starts_at: input.startsAt || null,
      ends_at: input.endsAt || null,
    },
  });
}

export async function updateSellerPromotionStatus(
  roasteryId: string,
  promotionId: string,
  status: "draft" | "scheduled" | "paused" | "expired",
): Promise<void> {
  await apiFetch<unknown>(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/promotions/${encodeURIComponent(promotionId)}`,
    { method: "PATCH", body: { status } },
  );
}
