import { queryOptions } from "@tanstack/react-query";
import type { Address, AddressInput, ApiResource, AuthUser } from "./contracts";
import { apiFetch, isForbiddenError, isUnauthenticatedError } from "./client";
import { queryKeys } from "./query-keys";

export type AuthMode = "login" | "register" | "recover";
export type OtpPurpose = "login" | "register" | "verify_mobile";

export interface OtpRequestResult {
  requestId: string;
  expiresIn: number;
  retryAfter: number;
}

interface OtpRequestWire {
  data: { request_id: string; expires_in: number; retry_after: number };
}

interface AddressWire {
  id: string;
  title?: string | null;
  recipient_name: string;
  recipient_mobile: string;
  province: string;
  city: string;
  address_line: string;
  postal_code?: string | null;
  is_default?: boolean;
}

export function toEnglishDigits(value: string): string {
  return value
    .replace(/[۰-۹]/g, (digit) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String("٠١٢٣٤٥٦٧٨٩".indexOf(digit)));
}

export function normalizeIranMobile(value: string): string {
  const compact = toEnglishDigits(value).replace(/[\s()-]/g, "");
  if (compact.startsWith("+98")) return `0${compact.slice(3)}`;
  if (compact.startsWith("0098")) return `0${compact.slice(4)}`;
  if (compact.startsWith("98") && compact.length === 12) return `0${compact.slice(2)}`;
  return compact;
}

export function isValidIranMobile(value: string): boolean {
  return /^09\d{9}$/.test(normalizeIranMobile(value));
}

export function modeToPurpose(mode: AuthMode): OtpPurpose {
  return mode === "register" ? "register" : "login";
}

function mapAddress(value: AddressWire): Address {
  return {
    id: value.id,
    title: value.title ?? null,
    recipientName: value.recipient_name,
    recipientMobile: value.recipient_mobile,
    province: value.province,
    city: value.city,
    addressLine: value.address_line,
    postalCode: value.postal_code ?? null,
    isDefault: Boolean(value.is_default),
  };
}

function addressPayload(value: AddressInput): Record<string, unknown> {
  return {
    title: value.title || null,
    recipient_name: value.recipientName,
    recipient_mobile: normalizeIranMobile(value.recipientMobile),
    province: value.province,
    city: value.city,
    address_line: value.addressLine,
    postal_code: value.postalCode || null,
    is_default: value.isDefault,
  };
}

export async function requestOtp(input: {
  mobile: string;
  purpose: OtpPurpose;
}): Promise<OtpRequestResult> {
  const response = await apiFetch<OtpRequestWire>("/auth/otp/request", {
    method: "POST",
    body: { mobile: normalizeIranMobile(input.mobile), purpose: input.purpose },
  });
  return {
    requestId: response.data.request_id,
    expiresIn: response.data.expires_in,
    retryAfter: response.data.retry_after,
  };
}

export async function verifyOtp(input: { requestId: string; code: string }): Promise<AuthUser> {
  const response = await apiFetch<ApiResource<AuthUser>>("/auth/otp/verify", {
    method: "POST",
    body: { request_id: input.requestId, code: toEnglishDigits(input.code).trim() },
  });
  return response.data;
}

export async function getCurrentUser(): Promise<AuthUser> {
  const response = await apiFetch<ApiResource<AuthUser>>("/me");
  return response.data;
}

export async function updateCurrentUser(input: {
  name?: string | null;
  email?: string | null;
}): Promise<AuthUser> {
  const response = await apiFetch<ApiResource<AuthUser>>("/me", {
    method: "PATCH",
    body: { name: input.name || null, email: input.email || null },
  });
  return response.data;
}

export async function logout(): Promise<void> {
  await apiFetch<void>("/auth/logout", { method: "POST" });
}

export async function listAddresses(): Promise<Address[]> {
  const response = await apiFetch<{ data: AddressWire[] }>("/me/addresses");
  return response.data.map(mapAddress);
}

export async function createAddress(input: AddressInput): Promise<Address> {
  const response = await apiFetch<{ data: AddressWire }>("/me/addresses", {
    method: "POST",
    body: addressPayload(input),
  });
  return mapAddress(response.data);
}

export async function updateAddress(id: string, input: AddressInput): Promise<Address> {
  const response = await apiFetch<{ data: AddressWire }>(
    `/me/addresses/${encodeURIComponent(id)}`,
    { method: "PATCH", body: addressPayload(input) },
  );
  return mapAddress(response.data);
}

export async function deleteAddress(id: string): Promise<void> {
  await apiFetch<void>(`/me/addresses/${encodeURIComponent(id)}`, { method: "DELETE" });
}

export const currentUserQueryOptions = () =>
  queryOptions({
    queryKey: queryKeys.auth.me(),
    queryFn: getCurrentUser,
    staleTime: 60_000,
    retry: (attempt, error) =>
      !isUnauthenticatedError(error) && !isForbiddenError(error) && attempt < 1,
  });

export const addressesQueryOptions = () =>
  queryOptions({
    queryKey: queryKeys.profile.addresses(),
    queryFn: listAddresses,
    staleTime: 30_000,
    retry: (attempt, error) =>
      !isUnauthenticatedError(error) && !isForbiddenError(error) && attempt < 1,
  });
