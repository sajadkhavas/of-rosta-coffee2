import type { AuthMode, OtpPurpose } from "@/lib/api/identity";

const STORAGE_KEY = "rosta.pending-otp.v1";

export interface PendingOtpFlow {
  requestId: string;
  mobile: string;
  purpose: OtpPurpose;
  mode: AuthMode;
  expiresAt: number;
  retryAt: number;
  redirect: string;
}

export function savePendingOtp(flow: PendingOtpFlow): void {
  if (typeof sessionStorage === "undefined") return;
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(flow));
}

export function loadPendingOtp(): PendingOtpFlow | null {
  if (typeof sessionStorage === "undefined") return null;
  const raw = sessionStorage.getItem(STORAGE_KEY);
  if (!raw) return null;
  try {
    const value = JSON.parse(raw) as PendingOtpFlow;
    if (!value.requestId || !value.mobile || !value.expiresAt) return null;
    return value;
  } catch {
    return null;
  }
}

export function clearPendingOtp(): void {
  if (typeof sessionStorage !== "undefined") sessionStorage.removeItem(STORAGE_KEY);
}

export function safeRedirect(value: unknown, fallback = "/profile"): string {
  if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//"))
    return fallback;
  if (value.startsWith("/auth")) return fallback;
  return value;
}
