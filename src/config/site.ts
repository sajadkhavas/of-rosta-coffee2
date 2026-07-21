const DEFAULT_SITE_URL = "https://rosta.shop";
const DEFAULT_API_URL = "http://localhost:8000/api/v1";
const LOCAL_HOSTS = new Set(["localhost", "127.0.0.1", "[::1]"]);
const CONTROL_OR_BACKSLASH = /[\\\u0000-\u001f\u007f]/;

function isLocalHostname(hostname: string): boolean {
  return LOCAL_HOSTS.has(hostname.toLowerCase());
}

function normalizeConfiguredUrl(value: string, label: string): string {
  const candidate = value.trim();
  if (!candidate || CONTROL_OR_BACKSLASH.test(candidate)) {
    throw new Error(`${label} معتبر نیست.`);
  }

  let url: URL;
  try {
    url = new URL(candidate);
  } catch {
    throw new Error(`${label} باید یک URL کامل و معتبر باشد.`);
  }

  if (url.username || url.password || url.search || url.hash) {
    throw new Error(
      `${label} نباید شامل اطلاعات ورود، Query یا Fragment باشد.`,
    );
  }

  const localHttp = url.protocol === "http:" && isLocalHostname(url.hostname);
  if (url.protocol !== "https:" && !localHttp) {
    throw new Error(`${label} در محیط تولید باید HTTPS باشد.`);
  }

  return url.toString().replace(/\/+$/, "");
}

function parsePaymentRedirectHosts(
  value: string | undefined,
  siteUrl: string,
  apiBaseUrl: string,
) {
  const hosts = new Set<string>([
    new URL(siteUrl).host.toLowerCase(),
    new URL(apiBaseUrl).host.toLowerCase(),
  ]);

  for (const rawHost of (value ?? "").split(",")) {
    const host = rawHost.trim().toLowerCase();
    if (!host) continue;
    if (
      CONTROL_OR_BACKSLASH.test(host) ||
      host.includes("://") ||
      host.includes("/") ||
      host.includes("@")
    ) {
      throw new Error(
        "VITE_PAYMENT_REDIRECT_HOSTS باید فقط شامل Hostهای جداشده با ویرگول باشد.",
      );
    }
    hosts.add(host);
  }

  return Object.freeze([...hosts]);
}

const configuredSiteUrl = normalizeConfiguredUrl(
  import.meta.env.VITE_SITE_URL || DEFAULT_SITE_URL,
  "VITE_SITE_URL",
);
const configuredApiUrl = normalizeConfiguredUrl(
  import.meta.env.VITE_API_URL || DEFAULT_API_URL,
  "VITE_API_URL",
);

export const siteConfig = {
  name: "رستا",
  description:
    "پلتفرم در حال توسعه برای کشف و مقایسه ساختاریافته دانه کامل قهوه.",
  siteUrl: configuredSiteUrl,
  apiUrl: configuredApiUrl,
  paymentRedirectHosts: parsePaymentRedirectHosts(
    import.meta.env.VITE_PAYMENT_REDIRECT_HOSTS,
    configuredSiteUrl,
    configuredApiUrl,
  ),
  allowIndexing: import.meta.env.VITE_ALLOW_INDEXING === "true",
  locale: "fa_IR",
  language: "fa",
  direction: "rtl" as const,
  currency: "IRR",
  themeColor: "#0A0400",
  socialImagePath: "/og-home.png",
} as const;

export function absoluteUrl(path = "/"): string {
  if (/^https?:\/\//i.test(path)) return new URL(path).toString();
  if (CONTROL_OR_BACKSLASH.test(path) || path.startsWith("//")) {
    throw new Error("مسیر عمومی نامعتبر است.");
  }
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  return `${siteConfig.siteUrl}${normalizedPath}`;
}

export function apiUrl(path = "/"): string {
  const candidate = path.trim();
  if (
    !candidate ||
    CONTROL_OR_BACKSLASH.test(candidate) ||
    candidate.startsWith("//") ||
    /^[a-z][a-z\d+.-]*:/i.test(candidate) ||
    candidate.includes("#")
  ) {
    throw new Error("مسیر API نامعتبر است.");
  }

  const pathname = candidate.split("?", 1)[0];
  const decodedSegments = pathname.split("/").map((segment) => {
    try {
      return decodeURIComponent(segment);
    } catch {
      throw new Error("مسیر API دارای Encoding نامعتبر است.");
    }
  });
  if (decodedSegments.some((segment) => segment === "." || segment === "..")) {
    throw new Error("مسیر API اجازه خروج از محدوده تعریف‌شده را ندارد.");
  }

  const normalizedPath = candidate.startsWith("/")
    ? candidate
    : `/${candidate}`;
  return `${siteConfig.apiUrl}${normalizedPath}`;
}

export function assertApprovedPaymentRedirect(value: string): string {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error("آدرس انتقال درگاه معتبر نیست.");
  }

  const localHttp = url.protocol === "http:" && isLocalHostname(url.hostname);
  const secureProtocol = url.protocol === "https:" || localHttp;
  const approvedHost = siteConfig.paymentRedirectHosts.includes(
    url.host.toLowerCase(),
  );

  if (!secureProtocol || !approvedHost || url.username || url.password) {
    throw new Error("آدرس انتقال درگاه خارج از فهرست مجاز است.");
  }

  return url.toString();
}
