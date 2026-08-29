import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract } from "./schemas";

const sellerWorkspaceSchema = z
  .object({
    data: z
      .object({
        roastery_id: z.string().trim().min(1).max(240),
        kpis: z
          .object({
            pending_acceptance: z.number().int().nonnegative(),
            active_fulfillment: z.number().int().nonnegative(),
            active_shipping: z.number().int().nonnegative(),
            open_incidents: z.number().int().nonnegative(),
          })
          .strict(),
        generated_at: z.string().datetime({ offset: true }),
      })
      .strict(),
  })
  .passthrough();

const adminWorkspaceSchema = z
  .object({
    data: z
      .object({
        kpis: z
          .object({
            pending_roasteries: z.number().int().nonnegative(),
            products_in_review: z.number().int().nonnegative(),
            open_fulfillment_incidents: z.number().int().nonnegative(),
            failed_notifications: z.number().int().nonnegative(),
            open_financial_reconciliation: z.number().int().nonnegative(),
          })
          .strict(),
        generated_at: z.string().datetime({ offset: true }),
      })
      .strict(),
  })
  .passthrough();

export type SellerWorkspace = z.infer<typeof sellerWorkspaceSchema>["data"];
export type AdminWorkspace = z.infer<typeof adminWorkspaceSchema>["data"];

export async function getSellerWorkspace(roasteryId: string): Promise<SellerWorkspace> {
  return parseContract(
    sellerWorkspaceSchema,
    await apiFetch<unknown>(`/seller/roasteries/${encodeURIComponent(roasteryId)}/workspace`),
    "شاخص‌های پنل روستری",
  ).data;
}

export async function getAdminWorkspace(): Promise<AdminWorkspace> {
  return parseContract(
    adminWorkspaceSchema,
    await apiFetch<unknown>("/admin/operations/workspace"),
    "شاخص‌های پنل ادمین",
  ).data;
}

export const sellerWorkspaceQueryOptions = (roasteryId: string) =>
  queryOptions({
    queryKey: ["seller", "roasteries", roasteryId, "workspace"],
    queryFn: () => getSellerWorkspace(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 15_000,
  });

export const adminWorkspaceQueryOptions = () =>
  queryOptions({
    queryKey: ["admin", "operations", "workspace"],
    queryFn: getAdminWorkspace,
    staleTime: 10_000,
  });
