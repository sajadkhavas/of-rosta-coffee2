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
  parseContract,
  parseOptionalMedia,
  resourceSchema,
  type OrderDetailWire,
  type OrderSummaryWire,
} from "./schemas";
import {
  authoritativeOrderDetailWireSchema,
  authoritativeOrderSummaryWireSchema,
} from "./financial-contracts";

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

type OrderListWire = OrderSummaryWire | OrderDetailWire;
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
    services: value.services.map((service) => ({
      id: service.id,
      type: service.type,
      providerType: service.provider_type,
      status: service.status,
      grindingProfile: service.grinding_profile
        ? {
            id: service.grinding_profile.id,
            code: service.grinding_profile.code,
            version: service.grinding_profile.version,
            name: service.grinding_profile.name,
            brewMethod: service.grinding_profile.brew_method,
          }
        : null,
      serviceFee: service.service_fee,
      packagingFee: service.packaging_fee,
      shippingFee: service.shipping_fee,
      taxAmount: service.tax_amount,
      totalAmount: service.total_amount,
      currency: service.currency,
      isFree: service.is_free,
      label: service.label ?? null,
      hubOperation: service.hub_operation
        ? {
            status: service.hub_operation.status,
            label: service.hub_operation.label,
            receivedAt: service.hub_operation.received_at ?? null,
            readyAt: service.hub_operation.ready_at ?? null,
            handedOffAt: service.hub_operation.handed_off_at ?? null,
          }
        : null,
    })),
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
    acceptanceStatus: value.acceptance_status,
    customerCancellable: value.customer_cancellable,
    fulfillment: {
      acceptanceMode: value.fulfillment.acceptance_mode,
      committedAt: value.fulfillment.committed_at ?? null,
      preparationDueAt: value.fulfillment.preparation_due_at ?? null,
      handoffDueAt: value.fulfillment.handoff_due_at ?? null,
      slaStatus: value.fulfillment.sla_status,
      isBreached: value.fulfillment.is_breached,
    },
    delivery: {
      confirmedAt: value.delivery.confirmed_at ?? null,
      disputeWindowEndsAt: value.delivery.dispute_window_ends_at ?? null,
      customerCanConfirm: value.delivery.customer_can_confirm,
      settlementState: value.delivery.settlement_state,
      settlementHoldCode: value.delivery.settlement_hold_code ?? null,
      settlementReleasedAt: value.delivery.settlement_released_at ?? null,
    },
    incidents: value.incidents.map((incident) => ({
      id: incident.id,
      status: incident.status,
      code: incident.code,
      severity: incident.severity,
      resolution: incident.resolution ?? null,
      reportedAt: incident.reported_at,
      resolvedAt: incident.resolved_at ?? null,
    })),
    roastery: value.roastery,
    items: value.items.map(mapLine),
    subtotal: value.subtotal,
    packagingTotal: value.packaging_total,
    grindingTotal: value.grinding_total,
    shippingTotal: value.shipping_total,
    discountTotal: value.discount_total,
    taxTotal: value.tax_total,
    grandTotal: value.grand_total,
    currency: value.currency,
    shipment: mapShipment(value.shipment),
    shipmentLegs: value.shipment_legs.map((leg) => ({
      id: leg.id,
      routeType: leg.route_type,
      sequence: leg.sequence,
      isFinal: leg.is_final,
      status: leg.status,
      carrier: leg.carrier ?? null,
      trackingCode: leg.tracking_code ?? null,
      totalAmount: leg.total_amount,
      currency: leg.currency,
      plannedAt: leg.planned_at ?? null,
      pickedUpAt: leg.picked_up_at ?? null,
      deliveredAt: leg.delivered_at ?? null,
      deliveryConfirmation: leg.delivery_confirmation
        ? {
            source: leg.delivery_confirmation.source,
            proofType: leg.delivery_confirmation.proof_type,
            confirmedAt: leg.delivery_confirmation.confirmed_at,
          }
        : null,
    })),
  };
}

export function mapOrderSummary(value: OrderListWire): OrderSummary {
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

export function mapOrderDetail(value: OrderDetailWire): OrderDetail {
  return {
    ...mapOrderSummary(value),
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
    packagingTotal: value.packaging_total,
    grindingTotal: value.grinding_total,
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
  const response = parseContract(
    collectionSchema(authoritativeOrderSummaryWireSchema.or(authoritativeOrderDetailWireSchema)),
    raw,
    "فهرست سفارش‌ها",
  );
  return {
    items: response.data.map(mapOrderSummary),
    meta: response.meta,
    links: response.links,
  };
}

export async function getOrder(id: string): Promise<OrderDetail> {
  const raw = await apiFetch(`/orders/${encodeURIComponent(id)}`);
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    raw,
    "جزئیات سفارش",
  );
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

export async function confirmOrderDelivery(input: {
  orderId: string;
  shipmentLegId: string;
  idempotencyKey: string;
  customerNote?: string;
}): Promise<OrderDetail> {
  const raw = await apiFetch(
    `/orders/${encodeURIComponent(input.orderId)}/shipment-legs/${encodeURIComponent(input.shipmentLegId)}/delivery-confirmations`,
    {
      method: "POST",
      body: {
        idempotency_key: input.idempotencyKey,
        proof_type: "customer_acknowledgement",
        proof_payload: null,
        customer_note: input.customerNote?.trim() || null,
      },
    },
  );
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    raw,
    "تأیید دریافت سفارش",
  );
  return mapOrderDetail(response.data);
}

export async function cancelOrder(orderId: string, reason?: string): Promise<OrderDetail> {
  const raw = await apiFetch(`/orders/${encodeURIComponent(orderId)}/cancel`, {
    method: "POST",
    body: { reason: reason?.trim() || null },
  });
  const response = parseContract(
    resourceSchema(authoritativeOrderDetailWireSchema),
    raw,
    "لغو سفارش",
  );
  return mapOrderDetail(response.data);
}
