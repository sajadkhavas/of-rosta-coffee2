import { queryOptions } from "@tanstack/react-query";
import type {
  ApiCollection,
  ApiLinks,
  ApiMeta,
  CurrencyCode,
  OrderDetail,
  OrderLine,
  OrderStatus,
  OrderSummary,
  ShipmentSummary,
  SubOrderStatus,
  SubOrderSummary,
} from "./contracts";
import { apiFetch, isForbiddenError, isUnauthenticatedError } from "./client";
import { queryKeys } from "./query-keys";

interface WireMedia { sources?: Array<{ url?: string }> }
interface WireLine {
  id: string;
  product: { id: string; name: string; slug: string; primary_image?: WireMedia | null };
  variant: {
    id: string;
    sku: string;
    weight_grams: number;
    price: number;
    currency?: CurrencyCode;
  };
  quantity: number;
  line_total: number;
}
interface WireShipment {
  id: string;
  carrier?: string | null;
  tracking_code?: string | null;
  status: string;
  shipped_at?: string | null;
  delivered_at?: string | null;
}
interface WireSubOrder {
  id: string;
  status: SubOrderStatus;
  roastery: { id: string; name: string; slug: string };
  items: WireLine[];
  subtotal: number;
  shipping_total: number;
  shipment?: WireShipment | null;
}
interface WireAddress {
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
interface WireOrder {
  id: string;
  order_number: string;
  status: OrderStatus;
  placed_at?: string | null;
  grand_total: number;
  currency: CurrencyCode;
  sub_orders: WireSubOrder[];
  address?: WireAddress | null;
  subtotal?: number;
  shipping_total?: number;
  discount_total?: number;
}

export interface OrderListParams {
  page?: number;
  perPage?: number;
  status?: OrderStatus | "all";
}

export interface OrderListResult {
  items: OrderSummary[];
  meta?: ApiMeta;
  links?: ApiLinks;
}

function mapLine(value: WireLine): OrderLine {
  return {
    id: value.id,
    product: {
      id: value.product.id,
      name: value.product.name,
      slug: value.product.slug,
      imageUrl: value.product.primary_image?.sources?.[0]?.url ?? null,
    },
    variant: {
      id: value.variant.id,
      sku: value.variant.sku,
      weightGrams: value.variant.weight_grams,
      price: value.variant.price,
      currency: value.variant.currency ?? "IRR",
    },
    quantity: value.quantity,
    lineTotal: value.line_total,
  };
}

function mapShipment(value?: WireShipment | null): ShipmentSummary | null {
  if (!value) return null;
  return {
    id: value.id,
    carrier: value.carrier ?? null,
    trackingCode: value.tracking_code ?? null,
    status: value.status,
    shippedAt: value.shipped_at ?? null,
    deliveredAt: value.delivered_at ?? null,
  };
}

function mapSubOrder(value: WireSubOrder): SubOrderSummary {
  return {
    id: value.id,
    status: value.status,
    roastery: value.roastery,
    items: value.items.map(mapLine),
    subtotal: value.subtotal,
    shippingTotal: value.shipping_total,
    shipment: mapShipment(value.shipment),
  };
}

function mapOrder(value: WireOrder): OrderSummary {
  return {
    id: value.id,
    orderNumber: value.order_number,
    status: value.status,
    placedAt: value.placed_at ?? null,
    grandTotal: value.grand_total,
    currency: value.currency,
    subOrders: value.sub_orders.map(mapSubOrder),
  };
}

function mapOrderDetail(value: WireOrder): OrderDetail {
  return {
    ...mapOrder(value),
    address: value.address
      ? {
          id: value.address.id,
          title: value.address.title ?? null,
          recipientName: value.address.recipient_name,
          recipientMobile: value.address.recipient_mobile,
          province: value.address.province,
          city: value.address.city,
          addressLine: value.address.address_line,
          postalCode: value.address.postal_code ?? null,
          isDefault: Boolean(value.address.is_default),
        }
      : null,
    subtotal: value.subtotal ?? 0,
    shippingTotal: value.shipping_total ?? 0,
    discountTotal: value.discount_total ?? 0,
  };
}

export async function listOrders(params: OrderListParams = {}): Promise<OrderListResult> {
  const search = new URLSearchParams();
  if (params.page) search.set("page", String(params.page));
  if (params.perPage) search.set("per_page", String(params.perPage));
  if (params.status && params.status !== "all") search.set("status", params.status);
  const response = await apiFetch<ApiCollection<WireOrder>>(
    `/orders${search.size ? `?${search}` : ""}`,
  );
  return { items: response.data.map(mapOrder), meta: response.meta, links: response.links };
}

export async function getOrder(id: string): Promise<OrderDetail> {
  const response = await apiFetch<{ data: WireOrder }>(`/orders/${encodeURIComponent(id)}`);
  return mapOrderDetail(response.data);
}

const retryAccountQuery = (attempt: number, error: unknown) =>
  !isUnauthenticatedError(error) && !isForbiddenError(error) && attempt < 1;

export const ordersQueryOptions = (params: OrderListParams) =>
  queryOptions({
    queryKey: queryKeys.orders.list({ ...params }),
    queryFn: () => listOrders(params),
    staleTime: 20_000,
    retry: retryAccountQuery,
  });

export const orderQueryOptions = (id: string) =>
  queryOptions({
    queryKey: queryKeys.orders.detail(id),
    queryFn: () => getOrder(id),
    staleTime: 20_000,
    retry: retryAccountQuery,
  });
