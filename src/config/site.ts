const DEFAULT_SITE_URL = "https://rosta.shop";
const DEFAULT_API_URL = "http://localhost:8000/api/v1";

function normalizeBaseUrl(value: string): string {
  return value.replace(/\/+$/, "");
}

export const siteConfig = {
  name: "رستا",
  description: "پلتفرم در حال توسعه برای کشف و مقایسه ساختاریافته دانه کامل قهوه.",
  siteUrl: normalizeBaseUrl(import.meta.env.VITE_SITE_URL || DEFAULT_SITE_URL),
  apiUrl: normalizeBaseUrl(import.meta.env.VITE_API_URL || DEFAULT_API_URL),
  allowIndexing: import.meta.env.VITE_ALLOW_INDEXING === "true",
  locale: "fa_IR",
  language: "fa",
  direction: "rtl" as const,
  currency: "IRR",
  themeColor: "#0A0400",
  socialImagePath: "/og-home.png",
} as const;

export function absoluteUrl(path = "/"): string {
  if (/^https?:\/\//i.test(path)) return path;
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${siteConfig.siteUrl}${normalizedPath}`;
}

export function apiUrl(path = "/"): string {
  if (/^https?:\/\//i.test(path)) return path;
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${siteConfig.apiUrl}${normalizedPath}`;
}
