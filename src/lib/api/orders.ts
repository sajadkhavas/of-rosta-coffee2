import { queryOptions } from "@tanstack/react-query";
import type {
  ApiLinks,
  ApiMeta,
  OrderDetail,
  OrderLine,
  OrderStatus,
  OrderSummary,
  ShipmentSummary,
  SubOrderSummary,
} from "./contracts";
import { apiFetch, isForbiddenError, isUnauthenticatedError } from "./client";
import { queryKeys } from "./query-keys";
import {
  collectionSchema,
  orderDetailWireSchema,
  orderSummaryWireSchema,
  parseContract,
  parseOptionalMedia,
  resourceSchema,
  type OrderDetailWire,
  type OrderSummaryWire,
} from "./schemas";

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

type WireLine = OrderSummaryWire["sub_orders"][number]["items"][number];
type WireShipment = NonNullable<OrderSummaryWire["sub_orders"][number]["shipment"]>;
type WireSubOrder = OrderSummaryWire["sub_orders"][number];

function mapLine(value: WireLine): OrderLine {
  return {
    id: value.id,
    product: {
      id: value.product.id,
      name: value.product.name,
      slug: value.product.slug,
      imageUrl: parseOptionalMedia(value.product.primary_image)?.sources[0]?.url ?? null,
    },
    variant: {
      id: value.variant.id,
      sku: value.variant.sku,
      weightGrams: value.variant.weight_grams,
      price: value.variant.price,
      currency: value.variant.currency,
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

function mapOrder(value: OrderSummaryWire): OrderSummary {
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

function mapOrderDetail(value: OrderDetailWire): OrderDetail {
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
          isDefault: value.address.is_default,
        }
      : null,
    subtotal: value.subtotal,
    shippingTotal: value.shipping_total,
    discountTotal: value.discount_total,
  };
}

function boundedInteger(value: number | undefined, min: number, max: number): number | undefined {
  if (value === undefined || !Number.isFinite(value)) return undefined;
  return Math.min(max, Math.max(min, Math.trunc(value)));
}

export async function listOrders(params: OrderListParams = {}): Promise<OrderListResult> {
  const search = new URLSearchParams();
  const page = boundedInteger(params.page, 1, 10_000);
  const perPage = boundedInteger(params.perPage, 1, 100);
  if (page) search.set("page", String(page));
  if (perPage) search.set("per_page", String(perPage));
  if (params.status && params.status !== "all") search.set("status", params.status);

  const raw = await apiFetch(`/orders${search.size ? `?${search.toString()}` : ""}`);
  const response = parseContract(collectionSchema(orderSummaryWireSchema), raw, "فهرست سفارش‌ها");
  return { items: response.data.map(mapOrder), meta: response.meta, links: response.links };
}

export async function getOrder(id: string): Promise<OrderDetail> {
  const raw = await apiFetch(`/orders/${encodeURIComponent(id)}`);
  const response = parseContract(resourceSchema(orderDetailWireSchema), raw, "جزئیات سفارش");
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
