const KEY = "rosta_taste_profile";

export interface TasteProfile {
  roastLevel?: string;
  method?: string;
  flavor?: string;
  savedAt: number;
}

export function loadProfile(): TasteProfile | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = window.localStorage.getItem(KEY);
    return raw ? (JSON.parse(raw) as TasteProfile) : null;
  } catch {
    return null;
  }
}

export function saveProfile(p: Omit<TasteProfile, "savedAt">) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(KEY, JSON.stringify({ ...p, savedAt: Date.now() }));
}

export function hasProfile(): boolean {
  return loadProfile() !== null;
}
