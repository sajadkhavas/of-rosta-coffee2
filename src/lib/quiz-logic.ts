export type BrewMethod = "espresso" | "moka" | "french_press" | "pour_over" | "cold_brew" | "unknown";
export type RoastPref = "light" | "medium" | "dark" | "recommend";
export type Adventure = "safe" | "balanced" | "adventurous";
export type Experience = "beginner" | "some" | "pro";

export interface TasteProfile {
  brewMethod: BrewMethod | null;
  roast: RoastPref | null;
  adventure: Adventure | null;
  flavors: string[];
  experience: Experience | null;
}

export const EMPTY_PROFILE: TasteProfile = {
  brewMethod: null,
  roast: null,
  adventure: null,
  flavors: [],
  experience: null,
};

export interface QuizGuestSession {
  attemptId: string;
  guestToken: string;
  version: number;
}

const SESSION_KEY = "rosta:quiz-session:v1";

export function createOpaqueGuestToken(): string {
  if (typeof crypto === "undefined" || typeof crypto.randomUUID !== "function") {
    throw new Error("Secure browser randomness is unavailable.");
  }
  return `${crypto.randomUUID()}${crypto.randomUUID()}`;
}

export function createIdempotencyKey(): string {
  if (typeof crypto === "undefined" || typeof crypto.randomUUID !== "function") {
    throw new Error("Secure browser randomness is unavailable.");
  }
  return crypto.randomUUID();
}

export function saveQuizSession(session: QuizGuestSession): void {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

export function loadQuizSession(): QuizGuestSession | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(SESSION_KEY);
  if (!raw) return null;
  try {
    const value = JSON.parse(raw) as Partial<QuizGuestSession>;
    if (typeof value.attemptId !== "string" || typeof value.guestToken !== "string" || typeof value.version !== "number" || value.guestToken.length < 32) return null;
    return { attemptId: value.attemptId, guestToken: value.guestToken, version: value.version };
  } catch {
    return null;
  }
}

export function clearQuizSession(): void {
  if (typeof window !== "undefined") window.localStorage.removeItem(SESSION_KEY);
}

/** Backward-compatible import only. Quiz answers are no longer persisted in browser storage. */
export function loadProfile(): TasteProfile | null {
  return null;
}
