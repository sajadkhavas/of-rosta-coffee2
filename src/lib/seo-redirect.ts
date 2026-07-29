import { apiUrl } from "@/config/site";

interface SeoRedirectPayload {
  data?: {
    redirect?: {
      destination_path?: unknown;
      status_code?: unknown;
      is_active?: unknown;
    } | null;
  };
}

const PERMANENT_REDIRECT_STATUSES = new Set([301, 308]);

function hasControlOrBackslash(value: string): boolean {
  for (const character of value) {
    const code = character.charCodeAt(0);
    if (character === "\\" || code <= 0x1f || code === 0x7f) return true;
  }
  return false;
}

function safeInternalDestination(requestUrl: URL, value: unknown): URL | null {
  if (
    typeof value !== "string" ||
    !value.startsWith("/") ||
    value.startsWith("//") ||
    hasControlOrBackslash(value)
  ) {
    return null;
  }

  try {
    const destination = new URL(value, requestUrl.origin);
    return destination.origin === requestUrl.origin ? destination : null;
  } catch {
    return null;
  }
}

export async function resolveSeoRedirect(
  request: Request,
  fetchImplementation: typeof fetch = fetch,
): Promise<Response | null> {
  if (request.method !== "GET" && request.method !== "HEAD") return null;

  const requestUrl = new URL(request.url);
  let response: Response;
  try {
    response = await fetchImplementation(
      apiUrl(`/seo/redirects/resolve?path=${encodeURIComponent(requestUrl.pathname)}`),
      {
        method: "GET",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        redirect: "error",
      },
    );
  } catch {
    return null;
  }

  if (!response.ok) return null;

  let payload: SeoRedirectPayload;
  try {
    payload = (await response.json()) as SeoRedirectPayload;
  } catch {
    return null;
  }

  const redirect = payload.data?.redirect;
  const status = redirect?.status_code;
  if (
    !redirect ||
    redirect.is_active === false ||
    typeof status !== "number" ||
    !PERMANENT_REDIRECT_STATUSES.has(status)
  ) {
    return null;
  }

  const destination = safeInternalDestination(requestUrl, redirect.destination_path);
  if (!destination || destination.pathname === requestUrl.pathname) return null;

  return new Response(null, {
    status,
    headers: {
      Location: destination.toString(),
      "Cache-Control": "public, max-age=3600",
    },
  });
}
