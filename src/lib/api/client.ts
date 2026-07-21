import { z } from "zod";
import { apiUrl, siteConfig } from "@/config/site";

const DEFAULT_TIMEOUT_MS = 15_000;
const SAFE_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);

const errorPayloadSchema = z
  .object({
    error: z
      .object({
        code: z.string().optional(),
        message: z.string().optional(),
        fields: z.record(z.union([z.string(), z.array(z.string())])).optional(),
        request_id: z.string().optional(),
      })
      .passthrough()
      .optional(),
    message: z.string().optional(),
  })
  .passthrough();

export interface ApiErrorPayload {
  error?: {
    code?: string;
    message?: string;
    fields?: Record<string, string[] | string>;
    request_id?: string;
  };
  message?: string;
}

export type ApiFailureKind =
  | "http"
  | "timeout"
  | "aborted"
  | "network"
  | "configuration";

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fields: Record<string, string[] | string>;
  readonly requestId?: string;
  readonly retryAfterSeconds?: number;
  readonly kind: ApiFailureKind;

  constructor(options: {
    status: number;
    message: string;
    code?: string;
    fields?: Record<string, string[] | string>;
    requestId?: string;
    retryAfterSeconds?: number;
    kind?: ApiFailureKind;
    cause?: unknown;
  }) {
    super(options.message, { cause: options.cause });
    this.name = "ApiError";
    this.status = options.status;
    this.code = options.code ?? "api.unknown";
    this.fields = options.fields ?? {};
    this.requestId = options.requestId;
    this.retryAfterSeconds = options.retryAfterSeconds;
    this.kind = options.kind ?? "http";
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, "body"> {
  body?: BodyInit | Record<string, unknown> | null;
  timeoutMs?: number;
  skipCsrfRecovery?: boolean;
  suppressSessionExpiryEvent?: boolean;
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
  try {
    return decodeURIComponent(item.slice(prefix.length));
  } catch {
    return undefined;
  }
}

async function readPayload(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined;
  const contentType = response.headers.get("content-type") ?? "";
  if (contentType.includes("application/json")) return response.json().catch(() => undefined);
  const text = await response.text();
  return text || undefined;
}

function parseRetryAfter(value: string | null): number | undefined {
  if (!value) return undefined;
  const seconds = Number(value);
  if (Number.isFinite(seconds) && seconds >= 0) return Math.ceil(seconds);
  const date = Date.parse(value);
  if (!Number.isFinite(date)) return undefined;
  return Math.max(0, Math.ceil((date - Date.now()) / 1000));
}

function defaultCodeForStatus(status: number): string {
  if (status === 401) return "request.unauthenticated";
  if (status === 403) return "request.forbidden";
  if (status === 404) return "request.not_found";
  if (status === 419) return "auth.csrf_expired";
  if (status === 422) return "request.validation_failed";
  if (status === 429) return "request.rate_limited";
  if (status >= 500) return "server.unavailable";
  return "api.unknown";
}

function emitSessionExpired(): void {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new CustomEvent("rosta:session-expired"));
}

let csrfBootstrap: Promise<void> | null = null;

async function bootstrapCsrf(): Promise<void> {
  if (csrfBootstrap) return csrfBootstrap;

  const apiBase = new URL(siteConfig.apiUrl);
  const csrfUrl = `${apiBase.origin}/sanctum/csrf-cookie`;
  csrfBootstrap = fetch(csrfUrl, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  })
    .then((response) => {
      if (!response.ok && response.status !== 204) {
        throw new ApiError({
          status: response.status,
          code: "auth.csrf_bootstrap_failed",
          message: "آماده‌سازی نشست امن انجام نشد.",
        });
      }
    })
    .finally(() => {
      csrfBootstrap = null;
    });

  return csrfBootstrap;
}

async function performRequest(
  requestUrl: string,
  options: ApiRequestOptions,
  method: string,
  headers: Headers,
  body: BodyInit | null | undefined,
): Promise<Response> {
  const {
    body: _ignoredBody,
    timeoutMs: requestedTimeout,
    skipCsrfRecovery: _skipCsrfRecovery,
    suppressSessionExpiryEvent: _suppressSessionExpiryEvent,
    ...requestInit
  } = options;
  const controller = new AbortController();
  const externalSignal = requestInit.signal;
  let timedOut = false;

  const onExternalAbort = () => controller.abort(externalSignal?.reason);
  if (externalSignal?.aborted) onExternalAbort();
  else externalSignal?.addEventListener("abort", onExternalAbort, { once: true });

  const timeoutMs = Math.min(Math.max(requestedTimeout ?? DEFAULT_TIMEOUT_MS, 1_000), 120_000);
  const timer = setTimeout(() => {
    timedOut = true;
    controller.abort(new DOMException("Request timed out", "TimeoutError"));
  }, timeoutMs);

  try {
    return await fetch(requestUrl, {
      ...requestInit,
      method,
      headers,
      body,
      signal: controller.signal,
      credentials: requestInit.credentials ?? "include",
    });
  } catch (cause) {
    if (timedOut) {
      throw new ApiError({
        status: 0,
        code: "network.timeout",
        message: "پاسخ سرویس رستا بیش از حد طول کشید. دوباره تلاش کنید.",
        kind: "timeout",
        cause,
      });
    }
    if (externalSignal?.aborted) {
      throw new ApiError({
        status: 0,
        code: "request.aborted",
        message: "درخواست لغو شد.",
        kind: "aborted",
        cause,
      });
    }
    throw new ApiError({
      status: 0,
      code: "network.unavailable",
      message: "ارتباط با سرویس رستا برقرار نشد. اتصال اینترنت یا وضعیت API را بررسی کنید.",
      kind: "network",
      cause,
    });
  } finally {
    clearTimeout(timer);
    externalSignal?.removeEventListener("abort", onExternalAbort);
  }
}

export async function apiFetch<T = unknown>(
  path: string,
  options: ApiRequestOptions = {},
  csrfRetry = false,
): Promise<T> {
  let requestUrl: string;
  try {
    requestUrl = apiUrl(path);
  } catch (cause) {
    throw new ApiError({
      status: 0,
      code: "configuration.invalid_api_path",
      message: "مسیر ارتباط با API معتبر نیست.",
      kind: "configuration",
      cause,
    });
  }

  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  headers.set("X-Requested-With", "XMLHttpRequest");

  const method = (options.method ?? "GET").toUpperCase();
  if (!SAFE_METHODS.has(method) && !headers.has("X-XSRF-TOKEN")) {
    const token = readCookie("XSRF-TOKEN");
    if (token) headers.set("X-XSRF-TOKEN", token);
  }

  let body = options.body;
  if (isJsonBody(body)) {
    headers.set("Content-Type", "application/json");
    body = JSON.stringify(body);
  }

  const response = await performRequest(
    requestUrl,
    options,
    method,
    headers,
    body as BodyInit | null | undefined,
  );
  const payload = await readPayload(response);

  if (
    response.status === 419 &&
    !csrfRetry &&
    !options.skipCsrfRecovery &&
    !SAFE_METHODS.has(method)
  ) {
    await bootstrapCsrf();
    return apiFetch<T>(path, options, true);
  }

  if (!response.ok) {
    const parsedPayload = errorPayloadSchema.safeParse(payload);
    const errorPayload: ApiErrorPayload = parsedPayload.success ? parsedPayload.data : {};
    const code = errorPayload.error?.code ?? defaultCodeForStatus(response.status);

    if (
      !options.suppressSessionExpiryEvent &&
      (response.status === 401 || code === "auth.session_expired")
    ) {
      emitSessionExpired();
    }

    throw new ApiError({
      status: response.status,
      code,
      message:
        errorPayload.error?.message ?? errorPayload.message ?? "در ارتباط با سرور مشکلی پیش آمد.",
      fields: errorPayload.error?.fields,
      requestId:
        errorPayload.error?.request_id ?? response.headers.get("x-request-id") ?? undefined,
      retryAfterSeconds: parseRetryAfter(response.headers.get("retry-after")),
      kind: "http",
    });
  }

  return payload as T;
}
