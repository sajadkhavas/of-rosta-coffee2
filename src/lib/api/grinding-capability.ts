import { queryOptions } from "@tanstack/react-query";
import { z } from "zod";
import { apiFetch } from "./client";
import { collectionSchema, parseContract, resourceSchema } from "./schemas";

const identifier = z.string().trim().min(1).max(240);
const money = z.number().int().nonnegative().safe();
const weight = z.union([
  z.literal(50),
  z.literal(100),
  z.literal(250),
  z.literal(500),
  z.literal(1000),
]);

export const grindingProfileWireSchema = z
  .object({
    id: identifier,
    code: z.string().trim().min(1).max(100),
    version: z.number().int().min(1).max(65_535),
    public_name: z.string().trim().min(1).max(160),
    brew_method: z.string().trim().min(1).max(100),
  })
  .strict();

export const grindingCapabilityWireSchema = z
  .object({
    availability: z.enum(["available", "unavailable"]),
    is_available: z.boolean(),
    is_active: z.boolean(),
    fee_mode: z.enum(["free", "fixed"]),
    fee_amount: money,
    currency: z.literal("IRR"),
    is_free: z.boolean(),
    label: z.string().trim().min(1).max(240),
    preparation_minutes: z.number().int().nonnegative().max(10_080),
    capacity_per_day: z.number().int().positive().max(1_000_000).nullable(),
    supported_weights: z.array(weight).max(5),
    profiles: z.array(grindingProfileWireSchema).max(20),
  })
  .strict()
  .superRefine((value, context) => {
    if (value.is_available !== (value.is_active && value.availability === "available")) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["is_available"],
        message: "وضعیت قابلیت آسیاب ناسازگار است.",
      });
    }
    if (value.is_free !== (value.fee_mode === "free" || value.fee_amount === 0)) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["is_free"],
        message: "وضعیت هزینه آسیاب ناسازگار است.",
      });
    }
    if (value.fee_mode === "free" && value.fee_amount !== 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["fee_amount"],
        message: "آسیاب رایگان باید مبلغ صفر داشته باشد.",
      });
    }
    if (value.fee_mode === "fixed" && value.fee_amount < 1) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["fee_amount"],
        message: "هزینه ثابت آسیاب باید مثبت باشد.",
      });
    }
    if (value.is_available && (!value.profiles.length || !value.supported_weights.length)) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["profiles"],
        message: "قابلیت فعال به پروفایل و وزن پشتیبانی‌شده نیاز دارد.",
      });
    }
  });

export interface GrindingProfile {
  id: string;
  code: string;
  version: number;
  publicName: string;
  brewMethod: string;
}

export interface GrindingCapability {
  availability: "available" | "unavailable";
  isAvailable: boolean;
  isActive: boolean;
  feeMode: "free" | "fixed";
  feeAmount: number;
  currency: "IRR";
  isFree: boolean;
  label: string;
  preparationMinutes: number;
  capacityPerDay: number | null;
  supportedWeights: Array<50 | 100 | 250 | 500 | 1000>;
  profiles: GrindingProfile[];
}

export interface UpsertGrindingCapabilityInput {
  availability: "available" | "unavailable";
  feeMode: "free" | "fixed";
  feeAmount: number;
  preparationMinutes: number;
  capacityPerDay: number | null;
  supportedWeights: Array<50 | 100 | 250 | 500 | 1000>;
  grindingProfileIds: string[];
  isActive: boolean;
}

type GrindingProfileWire = z.infer<typeof grindingProfileWireSchema>;
type GrindingCapabilityWire = z.infer<typeof grindingCapabilityWireSchema>;

function mapProfile(value: GrindingProfileWire): GrindingProfile {
  return {
    id: value.id,
    code: value.code,
    version: value.version,
    publicName: value.public_name,
    brewMethod: value.brew_method,
  };
}

export function mapGrindingCapability(value: GrindingCapabilityWire): GrindingCapability {
  return {
    availability: value.availability,
    isAvailable: value.is_available,
    isActive: value.is_active,
    feeMode: value.fee_mode,
    feeAmount: value.fee_amount,
    currency: value.currency,
    isFree: value.is_free,
    label: value.label,
    preparationMinutes: value.preparation_minutes,
    capacityPerDay: value.capacity_per_day,
    supportedWeights: value.supported_weights,
    profiles: value.profiles.map(mapProfile),
  };
}

export async function listGrindingProfiles(): Promise<GrindingProfile[]> {
  const response = parseContract(
    collectionSchema(grindingProfileWireSchema),
    await apiFetch<unknown>("/grinding-profiles"),
    "پروفایل‌های آسیاب",
  );
  return response.data.map(mapProfile);
}

export async function getPublicGrindingCapability(
  roasterySlug: string,
): Promise<GrindingCapability | null> {
  const response = parseContract(
    resourceSchema(grindingCapabilityWireSchema.nullable()),
    await apiFetch<unknown>(`/roasteries/${encodeURIComponent(roasterySlug)}/grinding-capability`),
    "قابلیت آسیاب روستری",
  );
  return response.data ? mapGrindingCapability(response.data) : null;
}

export async function getSellerGrindingCapability(
  roasteryId: string,
): Promise<GrindingCapability | null> {
  const response = parseContract(
    resourceSchema(grindingCapabilityWireSchema.nullable()),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/grinding-capability`,
    ),
    "تنظیم آسیاب روستری",
  );
  return response.data ? mapGrindingCapability(response.data) : null;
}

export async function updateSellerGrindingCapability(
  roasteryId: string,
  input: UpsertGrindingCapabilityInput,
): Promise<GrindingCapability> {
  const response = parseContract(
    resourceSchema(grindingCapabilityWireSchema),
    await apiFetch<unknown>(
      `/seller/roasteries/${encodeURIComponent(roasteryId)}/grinding-capability`,
      {
        method: "PATCH",
        body: {
          availability: input.availability,
          fee_mode: input.feeMode,
          fee_amount: input.feeMode === "fixed" ? input.feeAmount : 0,
          preparation_minutes: input.preparationMinutes,
          capacity_per_day: input.capacityPerDay,
          supported_weights: [...new Set(input.supportedWeights)].sort((a, b) => a - b),
          grinding_profile_ids: [...new Set(input.grindingProfileIds)],
          is_active: input.isActive,
        },
      },
    ),
    "ذخیره تنظیم آسیاب روستری",
  );
  return mapGrindingCapability(response.data);
}

export const grindingProfilesQueryOptions = () =>
  queryOptions({
    queryKey: ["catalog", "grinding-profiles"],
    queryFn: listGrindingProfiles,
    staleTime: 10 * 60_000,
  });

export const publicGrindingCapabilityQueryOptions = (roasterySlug: string) =>
  queryOptions({
    queryKey: ["catalog", "roasteries", roasterySlug, "grinding-capability"],
    queryFn: () => getPublicGrindingCapability(roasterySlug),
    staleTime: 60_000,
  });

export const sellerGrindingCapabilityQueryOptions = (roasteryId: string) =>
  queryOptions({
    queryKey: ["seller", "roasteries", roasteryId, "grinding-capability"],
    queryFn: () => getSellerGrindingCapability(roasteryId),
    enabled: Boolean(roasteryId),
    staleTime: 30_000,
  });
