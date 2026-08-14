import { z } from "zod";
import { apiFetch } from "./client";
import { parseContract, resourceSchema } from "./schemas";

const id = z.string().min(1).max(240);
const reportSchema = z
  .object({
    id,
    review_id: id,
    reason: z.string().min(1).max(32),
    evidence: z.string().max(500).nullable().optional(),
    status: z.enum(["open", "reviewing", "resolved", "dismissed"]),
    created_at: z.string().nullable().optional(),
    moderated_at: z.string().nullable().optional(),
    resolution_reason: z.string().max(500).nullable().optional(),
  })
  .strict();
const replySchema = z
  .object({
    id,
    review_id: id,
    body: z.string().min(1).max(5000),
    status: z.enum(["visible", "hidden", "rejected"]),
    updated_at: z.string().nullable().optional(),
  })
  .strict();
const versionSchema = z
  .object({
    id,
    version: z.number().int().positive(),
    status: z.enum(["draft", "published", "archived"]),
    title: z.string().min(1).max(160),
    questions: z.array(z.record(z.unknown())).min(1).max(20),
    scoring_profile: z.record(z.unknown()),
    recommendation_rules: z.record(z.unknown()),
    checksum: z.string().length(64),
    published_at: z.string().nullable().optional(),
    archived_at: z.string().nullable().optional(),
  })
  .strict();
const reportsSchema = z.object({ items: z.array(reportSchema).max(100) }).strict();
const repliesSchema = z.object({ items: z.array(replySchema).max(100) }).strict();
const versionsSchema = z.object({ items: z.array(versionSchema).max(100) }).strict();

export type AdminReport = z.infer<typeof reportSchema>;
export type AdminReply = z.infer<typeof replySchema>;
export type AdminQuizVersion = z.infer<typeof versionSchema>;

export async function adminReports(status: AdminReport["status"] = "open") {
  return parseContract(
    resourceSchema(reportsSchema),
    await apiFetch<unknown>(`/admin/review-reports?status=${encodeURIComponent(status)}`),
    "صف گزارش‌ها",
  ).data.items;
}
export async function decideReport(
  idValue: string,
  status: AdminReport["status"],
  reason?: string,
) {
  return parseContract(
    resourceSchema(reportSchema),
    await apiFetch<unknown>(`/admin/review-reports/${encodeURIComponent(idValue)}`, {
      method: "PATCH",
      body: { status, resolution_reason: reason?.trim() || null },
    }),
    "تصمیم گزارش",
  ).data;
}
export async function adminReplies(status: AdminReply["status"] = "visible") {
  return parseContract(
    resourceSchema(repliesSchema),
    await apiFetch<unknown>(`/admin/review-replies?status=${encodeURIComponent(status)}`),
    "صف پاسخ‌ها",
  ).data.items;
}
export async function decideReply(idValue: string, status: AdminReply["status"], reason?: string) {
  return parseContract(
    resourceSchema(replySchema),
    await apiFetch<unknown>(`/admin/review-replies/${encodeURIComponent(idValue)}`, {
      method: "PATCH",
      body: { status, reason: reason?.trim() || null },
    }),
    "تصمیم پاسخ",
  ).data;
}
export async function adminQuizVersions(): Promise<AdminQuizVersion[]> {
  return parseContract(
    resourceSchema(versionsSchema),
    await apiFetch<unknown>("/admin/quiz/versions"),
    "نسخه‌های کوییز",
  ).data.items;
}
export async function cloneQuizDraft(source: AdminQuizVersion): Promise<AdminQuizVersion> {
  return parseContract(
    resourceSchema(versionSchema),
    await apiFetch<unknown>("/admin/quiz/versions", {
      method: "POST",
      body: {
        title: `${source.title} - نسخه جدید`,
        questions: source.questions,
        scoring_profile: source.scoring_profile,
        recommendation_rules: source.recommendation_rules,
      },
    }),
    "ساخت draft کوییز",
  ).data;
}
export async function publishQuizVersion(idValue: string): Promise<AdminQuizVersion> {
  return parseContract(
    resourceSchema(versionSchema),
    await apiFetch<unknown>(`/admin/quiz/versions/${encodeURIComponent(idValue)}/publish`, {
      method: "POST",
    }),
    "انتشار کوییز",
  ).data;
}
export async function archiveQuizVersion(idValue: string): Promise<AdminQuizVersion> {
  return parseContract(
    resourceSchema(versionSchema),
    await apiFetch<unknown>(`/admin/quiz/versions/${encodeURIComponent(idValue)}/archive`, {
      method: "POST",
    }),
    "بایگانی کوییز",
  ).data;
}
