import { z } from "zod";
import type {
  ProductSummary,
  ProductVariant,
  RoastBatchSummary,
  RoasterySummary,
} from "./contracts";
import { apiFetch } from "./client";
import {
  parseContract,
  parseOptionalMedia,
  publicProductSummaryWireSchema,
  resourceSchema,
  type ProductSummaryWire,
  type ProductVariantWire,
  type RoastBatchWire,
  type RoasterySummaryWire,
} from "./schemas";

const identifier = z.string().trim().min(1).max(240);
const optionSchema = z
  .object({ value: z.string().min(1).max(100), label: z.string().min(1).max(200) })
  .strict();
const questionSchema = z
  .object({
    key: z.string().min(1).max(100),
    type: z.enum(["single", "multi"]),
    title: z.string().min(1).max(300),
    required: z.boolean().optional(),
    max_selections: z.number().int().min(1).max(10).optional(),
    options: z.array(optionSchema).min(1).max(30),
  })
  .strict();
const versionSchema = z
  .object({
    id: identifier,
    version: z.number().int().positive(),
    title: z.string().min(1).max(160),
    questions: z.array(questionSchema).min(1).max(20),
    checksum: z.string().regex(/^[a-f0-9]{64}$/),
  })
  .strict();
const attemptSchema = z
  .object({
    id: identifier,
    version: z.number().int().positive(),
    version_checksum: z.string().regex(/^[a-f0-9]{64}$/),
    answers: z.record(z.union([z.string(), z.array(z.string())])),
    score_profile: z.record(z.unknown()),
    synced: z.boolean(),
    completed_at: z.string().nullable(),
  })
  .strict();
const recommendationItemSchema = z
  .object({
    product: publicProductSummaryWireSchema,
    score: z.number().int(),
    reasons: z.array(z.string().min(1).max(500)).max(20),
  })
  .strict();
const recommendationsSchema = z
  .object({
    items: z.array(recommendationItemSchema).max(12),
    catalog_checked_at: z.string(),
    stale_safe: z.literal(true),
  })
  .strict();
const submissionSchema = z
  .object({ attempt: attemptSchema, recommendations: recommendationsSchema })
  .strict();
const profileSchema = z.object({ items: z.array(attemptSchema).max(50) }).strict();

export type QuizQuestion = z.infer<typeof questionSchema>;
export type QuizVersion = z.infer<typeof versionSchema>;
export type QuizAttempt = z.infer<typeof attemptSchema>;
export type QuizAnswers = Record<string, string | string[]>;
export interface QuizRecommendation {
  product: ProductSummary;
  score: number;
  reasons: string[];
}
export interface QuizSubmission {
  attempt: QuizAttempt;
  recommendations: QuizRecommendation[];
  catalogCheckedAt: string;
}

function mapRoastery(value: RoasterySummaryWire): RoasterySummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    city: value.city ?? null,
    isVerified: value.is_verified,
    logo: parseOptionalMedia(value.logo),
    cover: parseOptionalMedia(value.cover),
    preparationTime: value.preparation_time
      ? { minHours: value.preparation_time.min_hours, maxHours: value.preparation_time.max_hours }
      : null,
    rating: value.rating ? { ...value.rating } : null,
  };
}
function mapVariant(value: ProductVariantWire): ProductVariant {
  return {
    id: value.id,
    sku: value.sku,
    weightGrams: value.weight_grams,
    price: value.price,
    compareAtPrice: value.compare_at_price ?? null,
    currency: value.currency,
    isAvailable: value.is_available,
    availableQuantity: value.available_quantity ?? null,
  };
}
function mapBatch(value?: RoastBatchWire | null): RoastBatchSummary | null {
  return value
    ? {
        id: value.id,
        batchCode: value.batch_code,
        roastedAt: value.roasted_at,
        availableFrom: value.available_from ?? null,
      }
    : null;
}
function mapProduct(value: ProductSummaryWire): ProductSummary {
  return {
    id: value.id,
    name: value.name,
    slug: value.slug,
    shortDescription: value.short_description ?? null,
    origin: {
      id: value.origin.id,
      name: value.origin.name,
      countryCode: value.origin.country_code ?? null,
    },
    processingMethod: value.processing_method,
    roastLevel: value.roast_level,
    arabicaPercentage: value.arabica_percentage,
    tastingNotes: value.tasting_notes,
    packaging: {
      mode: value.packaging.mode,
      feeAmount: value.packaging.fee_amount,
      currency: value.packaging.currency,
      isFree: value.packaging.is_free,
      label: value.packaging.label,
    },
    primaryImage: parseOptionalMedia(value.primary_image),
    roastery: mapRoastery(value.roastery),
    variants: value.variants.map(mapVariant),
    latestRoastBatch: mapBatch(value.latest_roast_batch),
    status: value.status,
  };
}

export async function getCurrentQuiz(): Promise<QuizVersion> {
  return parseContract(
    resourceSchema(versionSchema),
    await apiFetch<unknown>("/quiz/current"),
    "نسخه فعال کوییز",
  ).data;
}

export async function submitQuizAttempt(input: {
  answers: QuizAnswers;
  guestToken: string;
  idempotencyKey: string;
}): Promise<QuizSubmission> {
  const data = parseContract(
    resourceSchema(submissionSchema),
    await apiFetch<unknown>("/quiz/attempts", {
      method: "POST",
      body: {
        answers: input.answers,
        guest_token: input.guestToken,
        idempotency_key: input.idempotencyKey,
      },
    }),
    "ثبت نتیجه کوییز",
  ).data;
  return {
    attempt: data.attempt,
    recommendations: data.recommendations.items.map((item) => ({
      product: mapProduct(item.product),
      score: item.score,
      reasons: item.reasons,
    })),
    catalogCheckedAt: data.recommendations.catalog_checked_at,
  };
}

export async function syncQuizAttempt(input: {
  attemptId: string;
  guestToken: string;
  idempotencyKey: string;
}): Promise<QuizAttempt> {
  return parseContract(
    resourceSchema(attemptSchema),
    await apiFetch<unknown>(`/quiz/attempts/${encodeURIComponent(input.attemptId)}/sync`, {
      method: "POST",
      body: { guest_token: input.guestToken, idempotency_key: input.idempotencyKey },
    }),
    "همگام‌سازی نتیجه کوییز",
  ).data;
}

export async function deleteGuestQuizAttempt(attemptId: string, guestToken: string): Promise<void> {
  await apiFetch(`/quiz/attempts/${encodeURIComponent(attemptId)}`, {
    method: "DELETE",
    headers: { "X-Quiz-Guest-Token": guestToken },
  });
}

export async function listMyQuizAttempts(): Promise<QuizAttempt[]> {
  return parseContract(
    resourceSchema(profileSchema),
    await apiFetch<unknown>("/me/quiz-attempts"),
    "تاریخچه کوییز",
  ).data.items;
}

export async function deleteMyQuizAttempt(attemptId: string): Promise<void> {
  await apiFetch(`/me/quiz-attempts/${encodeURIComponent(attemptId)}`, { method: "DELETE" });
}
