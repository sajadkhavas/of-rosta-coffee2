import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const statusSchema = z.enum(["pending_review", "verified", "rejected"]);
const entityTypeSchema = z.enum(["individual", "company"]);

const sellerProfileSchema = z
  .object({
    id: z.string().min(1),
    roastery_id: z.string().min(1),
    entity_type: entityTypeSchema,
    legal_name: z.string(),
    account_holder_name: z.string(),
    iban_masked: z.string(),
    status: statusSchema,
    submitted_at: z.string().nullable(),
    reviewed_at: z.string().nullable(),
    review_note: z.string().nullable(),
  })
  .strict();

const sellerEnvelopeSchema = z
  .object({
    profile: sellerProfileSchema.nullable(),
  })
  .strict();

const adminProfileSchema = z
  .object({
    id: z.string().min(1),
    roastery: z
      .object({
        id: z.string().min(1),
        name: z.string().nullable(),
      })
      .strict(),
    entity_type: entityTypeSchema,
    legal_name: z.string(),
    account_holder_name: z.string(),
    iban: z.string(),
    iban_masked: z.string(),
    status: statusSchema,
    submitted_at: z.string().nullable(),
    reviewed_at: z.string().nullable(),
    review_note: z.string().nullable(),
  })
  .strict();

const adminListSchema = z
  .object({
    items: z.array(adminProfileSchema).max(200),
  })
  .strict();

export type SettlementProfileStatus = z.infer<typeof statusSchema>;
export type SettlementEntityType = z.infer<typeof entityTypeSchema>;
export type SellerSettlementProfile = z.infer<typeof sellerProfileSchema>;
export type AdminSettlementProfile = z.infer<typeof adminProfileSchema>;

export async function getSellerSettlementProfile(
  roasteryId: string,
): Promise<SellerSettlementProfile | null> {
  const raw = await apiFetch(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/settlement-profile`,
  );
  return parseContract(resourceSchema(sellerEnvelopeSchema), raw, "پروفایل تسویه روستری").data
    .profile;
}

export async function updateSellerSettlementProfile(
  roasteryId: string,
  input: {
    entityType: SettlementEntityType;
    legalName: string;
    accountHolderName: string;
    iban: string;
  },
): Promise<SellerSettlementProfile> {
  const raw = await apiFetch(
    `/seller/roasteries/${encodeURIComponent(roasteryId)}/settlement-profile`,
    {
      method: "PUT",
      body: {
        entity_type: input.entityType,
        legal_name: input.legalName.trim(),
        account_holder_name: input.accountHolderName.trim(),
        iban: input.iban.replace(/\s+/g, "").toUpperCase(),
      },
    },
  );
  const response = parseContract(
    resourceSchema(sellerEnvelopeSchema),
    raw,
    "ثبت پروفایل تسویه روستری",
  );
  if (!response.data.profile) throw new Error("پروفایل تسویه پس از ثبت بازگردانده نشد.");
  return response.data.profile;
}

export async function listAdminSettlementProfiles(
  status: SettlementProfileStatus | "all" = "pending_review",
): Promise<AdminSettlementProfile[]> {
  const search = new URLSearchParams();
  if (status !== "all") search.set("status", status);
  const raw = await apiFetch(
    `/admin/finance/settlement-profiles${search.size ? `?${search}` : ""}`,
  );
  return parseContract(resourceSchema(adminListSchema), raw, "فهرست پروفایل‌های تسویه").data.items;
}

export async function reviewAdminSettlementProfile(
  profileId: string,
  decision: "verified" | "rejected",
  note?: string,
): Promise<AdminSettlementProfile> {
  const raw = await apiFetch(
    `/admin/finance/settlement-profiles/${encodeURIComponent(profileId)}`,
    {
      method: "PATCH",
      body: { decision, note: note?.trim() || null },
    },
  );
  return parseContract(resourceSchema(adminProfileSchema), raw, "بررسی پروفایل تسویه").data;
}
