import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract } from "./schemas";

const sellerKpisSchema = z
  .object({
    pending_acceptance: z.number().int().nonnegative(),
    active_fulfillment: z.number().int().nonnegative(),
    active_shipping: z.number().int().nonnegative(),
    open_incidents: z.number().int().nonnegative(),
  })
  .strict();

const sellerWorkspaceSchema = z
  .object({
    data: z
      .object({
        items: z.array(
          z
            .object({
              roastery: z
                .object({
                  id: z.string().trim().min(1).max(240),
                  name: z.string().trim().min(1).max(160),
                  status: z.enum(["pending", "verified", "suspended", "rejected"]),
                })
                .strict(),
              access_roles: z.array(z.string().trim().min(1).max(80)).max(20),
              kpis: sellerKpisSchema,
            })
            .strict(),
        ),
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

export async function getSellerWorkspace(): Promise<SellerWorkspace> {
  return parseContract(
    sellerWorkspaceSchema,
    await apiFetch<unknown>("/seller/workspace"),
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

export const sellerWorkspaceQueryOptions = () =>
  queryOptions({
    queryKey: ["seller", "workspace"],
    queryFn: getSellerWorkspace,
    staleTime: 15_000,
  });

export const adminWorkspaceQueryOptions = () =>
  queryOptions({
    queryKey: ["admin", "operations", "workspace"],
    queryFn: getAdminWorkspace,
    staleTime: 10_000,
  });
