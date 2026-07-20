import { apiUrl } from "@/config/site";

export interface ApiErrorPayload {
  error?: {
    code?: string;
    message?: string;
    fields?: Record<string, string[] | string>;
    request_id?: string;
  };
  message?: string;
}

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string[] | string>;
  readonly requestId?: string;

  constructor(options: {
    status: number;
    message: string;
    code?: string;
    fields?: Record<string, string[] | string>;
    requestId?: string;
    cause?: unknown;
  }) {
    super(options.message, { cause: options.cause });
    this.name = "ApiError";
    this.status = options.status;
    this.code = options.code ?? "api.unknown";
    this.fields = options.fields ?? {};
    this.requestId = options.requestId;
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, "body"> {
  body?: BodyInit | Record<string, unknown> | null;
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

export function isUnauthenticatedError(error: unknown): boolean {
  return (
    isApiError(error) &&
    (error.status === 401 ||
      error.code === "request.unauthenticated" ||
      error.code === "auth.session_expired")
  );
}

export function isForbiddenError(error: unknown): boolean {
  return isApiError(error) && (error.status === 403 || error.code === "request.forbidden");
}

export function firstFieldError(error: unknown, ...fields: string[]): string | undefined {
  if (!isApiError(error)) return undefined;
  for (const field of fields) {
    const value = error.fields[field];
    if (Array.isArray(value) && value.length > 0) return value[0];
    if (typeof value === "string" && value) return value;
  }
  return undefined;
}

function isJsonBody(body: ApiRequestOptions["body"]): body is Record<string, unknown> {
  return Boolean(
    body &&
      typeof body === "object" &&
      !(body instanceof FormData) &&
      !(body instanceof Blob) &&
      !(body instanceof URLSearchParams) &&
      !(body instanceof ArrayBuffer),
  );
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const prefix = `${name}=`;
  const item = document.cookie
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));
  if (!item) return undefined;
  return decodeURIComponent(item.slice(prefix.length));
}

async function readPayload(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined;
  const contentType = response.headers.get("content-type") ?? "";
  if (contentType.includes("application/json")) return response.json().catch(() => undefined);
  const text = await response.text();
  return text || undefined;
}

export async function apiFetch<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  headers.set("X-Requested-With", "XMLHttpRequest");

  const method = (options.method ?? "GET").toUpperCase();
  if (!["GET", "HEAD", "OPTIONS"].includes(method) && !headers.has("X-XSRF-TOKEN")) {
    const token = readCookie("XSRF-TOKEN");
    if (token) headers.set("X-XSRF-TOKEN", token);
  }

  let body = options.body;
  if (isJsonBody(body)) {
    headers.set("Content-Type", "application/json");
    body = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(apiUrl(path), {
      ...options,
      method,
      headers,
      body: body as BodyInit | null | undefined,
      credentials: options.credentials ?? "include",
    });
  } catch (cause) {
    throw new ApiError({
      status: 0,
      code: "network.unavailable",
      message: "ارتباط با سرویس رستا برقرار نشد. اتصال اینترنت یا وضعیت API را بررسی کنید.",
      cause,
    });
  }

  const payload = await readPayload(response);
  if (!response.ok) {
    const errorPayload = (payload ?? {}) as ApiErrorPayload;
    throw new ApiError({
      status: response.status,
      code:
        errorPayload.error?.code ??
        (response.status === 401
          ? "request.unauthenticated"
          : response.status === 403
            ? "request.forbidden"
            : "api.unknown"),
      message:
        errorPayload.error?.message ?? errorPayload.message ?? "در ارتباط با سرور مشکلی پیش آمد.",
      fields: errorPayload.error?.fields,
      requestId:
        errorPayload.error?.request_id ?? response.headers.get("x-request-id") ?? undefined,
    });
  }

  return payload as T;
}
