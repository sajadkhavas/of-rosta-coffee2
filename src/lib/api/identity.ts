import { queryOptions } from "@tanstack/react-query";
import type { Address, AddressInput, AuthUser } from "./contracts";
import { apiFetch, isForbiddenError, isUnauthenticatedError } from "./client";
import { queryKeys } from "./query-keys";
import {
  addressWireSchema,
  authUserSchema,
  otpRequestResultSchema,
  parseContract,
  resourceSchema,
  type AddressWire,
} from "./schemas";

export type AuthMode = "login" | "register" | "recover";
export type OtpPurpose = "login" | "register" | "verify_mobile";

export interface OtpRequestResult {
  requestId: string;
  expiresIn: number;
  retryAfter: number;
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
  if (compact.startsWith("98") && compact.length === 12) {
    return `0${compact.slice(2)}`;
  }
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
    isDefault: value.is_default,
  };
}

function addressPayload(value: AddressInput): Record<string, unknown> {
  const recipientMobile = normalizeIranMobile(value.recipientMobile);
  if (!isValidIranMobile(recipientMobile)) {
    throw new Error("شماره موبایل گیرنده معتبر نیست.");
  }

  return {
    title: value.title?.trim() || null,
    recipient_name: value.recipientName.trim(),
    recipient_mobile: recipientMobile,
    province: value.province.trim(),
    city: value.city.trim(),
    address_line: value.addressLine.trim(),
    postal_code: value.postalCode?.trim() || null,
    is_default: value.isDefault,
  };
}

export async function requestOtp(input: {
  mobile: string;
  purpose: OtpPurpose;
}): Promise<OtpRequestResult> {
  const mobile = normalizeIranMobile(input.mobile);
  if (!isValidIranMobile(mobile)) {
    throw new Error("شماره موبایل ایران معتبر نیست.");
  }

  const raw = await apiFetch("/auth/otp/request", {
    method: "POST",
    body: { mobile, purpose: input.purpose },
  });
  const response = parseContract(resourceSchema(otpRequestResultSchema), raw, "درخواست OTP");
  return {
    requestId: response.data.request_id,
    expiresIn: response.data.expires_in,
    retryAfter: response.data.retry_after,
  };
}

export async function verifyOtp(input: { requestId: string; code: string }): Promise<AuthUser> {
  const code = toEnglishDigits(input.code).trim();
  if (!/^\d{6}$/.test(code)) {
    throw new Error("کد تأیید باید دقیقاً شش رقم باشد.");
  }

  const raw = await apiFetch("/auth/otp/verify", {
    method: "POST",
    body: { request_id: input.requestId, code },
  });
  return parseContract(resourceSchema(authUserSchema), raw, "تأیید OTP").data;
}

export async function getCurrentUser(): Promise<AuthUser> {
  const raw = await apiFetch("/me", { suppressSessionExpiryEvent: true });
  return parseContract(resourceSchema(authUserSchema), raw, "حساب کاربری").data;
}

export async function updateCurrentUser(input: {
  name?: string | null;
  email?: string | null;
}): Promise<AuthUser> {
  const raw = await apiFetch("/me", {
    method: "PATCH",
    body: {
      name: input.name?.trim() || null,
      email: input.email?.trim() || null,
    },
  });
  return parseContract(resourceSchema(authUserSchema), raw, "ویرایش حساب کاربری").data;
}

export async function logout(): Promise<void> {
  await apiFetch("/auth/logout", {
    method: "POST",
    suppressSessionExpiryEvent: true,
  });
}

export async function listAddresses(): Promise<Address[]> {
  const raw = await apiFetch("/me/addresses");
  const response = parseContract(
    resourceSchema(addressWireSchema.array().max(100)),
    raw,
    "فهرست آدرس‌ها",
  );
  return response.data.map(mapAddress);
}

export async function createAddress(input: AddressInput): Promise<Address> {
  const raw = await apiFetch("/me/addresses", {
    method: "POST",
    body: addressPayload(input),
  });
  const response = parseContract(resourceSchema(addressWireSchema), raw, "ایجاد آدرس");
  return mapAddress(response.data);
}

export async function updateAddress(id: string, input: AddressInput): Promise<Address> {
  const raw = await apiFetch(`/me/addresses/${encodeURIComponent(id)}`, {
    method: "PATCH",
    body: addressPayload(input),
  });
  const response = parseContract(resourceSchema(addressWireSchema), raw, "ویرایش آدرس");
  return mapAddress(response.data);
}

export async function deleteAddress(id: string): Promise<void> {
  await apiFetch(`/me/addresses/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
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
