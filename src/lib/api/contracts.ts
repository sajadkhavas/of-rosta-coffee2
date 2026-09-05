export type IsoDateTime = string;
export type CurrencyCode = "IRR";

export interface ApiMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

export interface ApiLinks {
  first?: string | null;
  last?: string | null;
  prev?: string | null;
  next?: string | null;
}

export interface ApiCollection<T> {
  data: T[];
  meta?: ApiMeta;
  links?: ApiLinks;
}

export interface ApiResource<T> {
  data: T;
}

export type ProductStatus = "draft" | "review" | "published" | "archived";
export type RoastLevel = "light" | "medium" | "dark";
export type ProcessingMethod = "washed" | "natural" | "honey" | "other";
export type PackagingFeeMode = "free" | "fixed";

export interface PackagingPolicy {
  mode: PackagingFeeMode;
  feeAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label: string;
}

export interface MediaAsset {
  id: string;
  alt: string;
  width: number;
  height: number;
  blurDataUrl?: string | null;
  variantVersion?: string | null;
  sources: Array<{
    url: string;
    width: number;
    height?: number;
    format: "avif" | "webp" | "jpeg" | "png";
    sizeBytes?: number;
    checksumSha256?: string;
  }>;
}

export interface RoasterySummary {
  id: string;
  name: string;
  slug: string;
  city?: string | null;
  isVerified: boolean;
  logo?: MediaAsset | null;
  cover?: MediaAsset | null;
  preparationTime?: { minHours: number; maxHours: number } | null;
  rating?: { value: number; count: number } | null;
}

export interface WholesalePriceTier {
  minWeightGrams: 5000 | 10000 | 20000 | 50000;
  unitPrice: number;
}

export interface ProductVariantPricing {
  version: "ps12-wholesale-tier-v1";
  mode: "retail" | "wholesale";
  retailUnitPrice: number;
  appliedUnitPrice: number;
  totalWeightGrams: number;
  cafeId?: string | null;
  tierMinWeightGrams?: number | null;
}

export interface ProductVariant {
  id: string;
  sku: string;
  weightGrams: 50 | 100 | 250 | 500 | 1000;
  price: number;
  compareAtPrice?: number | null;
  currency: CurrencyCode;
  isAvailable: boolean;
  availableQuantity?: number | null;
  wholesaleTiers?: WholesalePriceTier[];
  pricing?: ProductVariantPricing | null;
}

export interface RoastBatchSummary {
  id: string;
  batchCode: string;
  roastedAt: IsoDateTime;
  availableFrom?: IsoDateTime | null;
}

export interface ProductSummary {
  id: string;
  name: string;
  slug: string;
  shortDescription?: string | null;
  origin: { id: string; name: string; countryCode?: string | null };
  processingMethod: ProcessingMethod;
  roastLevel: RoastLevel;
  arabicaPercentage: number;
  tastingNotes: string[];
  packaging: PackagingPolicy;
  primaryImage?: MediaAsset | null;
  roastery: RoasterySummary;
  variants: ProductVariant[];
  latestRoastBatch?: RoastBatchSummary | null;
  status: ProductStatus;
}

export interface ProductDetail extends ProductSummary {
  description: string;
  gallery: MediaAsset[];
  brewingSuggestions: string[];
  seo: { title?: string | null; description?: string | null };
}

export interface RoasteryDetail extends RoasterySummary {
  description: string;
  shippingPolicy?: string | null;
}

export type SearchCatalogType = "all" | "products" | "roasteries" | "content";

export interface SearchCatalogResult {
  products: ProductSummary[];
  roasteries: RoasterySummary[];
  suggestions: string[];
}

export interface ProductFilters {
  query?: string;
  origin?: string[];
  roastLevel?: RoastLevel[];
  processingMethod?: ProcessingMethod[];
  roastery?: string[];
  minPrice?: number;
  maxPrice?: number;
  weights?: ProductVariant["weightGrams"][];
  available?: boolean;
  sort?: "recommended" | "newest" | "price_asc" | "price_desc";
  page?: number;
  perPage?: number;
}

export interface CommerceServiceLine {
  id: string;
  type: string;
  providerType: string;
  grindingProfile?: {
    id: string;
    code: string;
    version: number;
    name: string;
    brewMethod: string;
  } | null;
  serviceFee: number;
  packagingFee: number;
  taxAmount: number;
  totalAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label?: string | null;
}

export interface CartLine {
  id: string;
  product: ProductSummary;
  variant: ProductVariant;
  quantity: number;
  lineTotal: number;
  services: CommerceServiceLine[];
}

export interface CartShipmentGroup {
  id: string;
  roastery: RoasterySummary;
  items: CartLine[];
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingCost?: number | null;
  shippingTotal: number;
  discountTotal: number;
  taxTotal: number;
  grandTotal: number;
  currency: CurrencyCode;
}

export interface CartQuote {
  id: string;
  expiresAt: IsoDateTime;
  roasteryId?: string | null;
  groups: CartShipmentGroup[];
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingTotal: number;
  discountTotal: number;
  grandTotal: number;
  currency: CurrencyCode;
  warnings: Array<{ code: string; message: string; cartItemId?: string }>;
}

export type PaymentStatus = "pending" | "paid" | "failed" | "cancelled" | "refunded";

export interface PaymentRequestResult {
  paymentId: string;
  redirectUrl: string;
}

export type OrderStatus =
  | "draft"
  | "awaiting_payment"
  | "paid"
  | "processing"
  | "partially_shipped"
  | "shipped"
  | "partially_delivered"
  | "delivered"
  | "partially_cancelled"
  | "cancelled"
  | "refunded";

export type SubOrderStatus =
  | "awaiting_payment"
  | "pending_acceptance"
  | "accepted"
  | "rejected"
  | "preparing"
  | "ready_to_ship"
  | "shipped"
  | "delivered"
  | "cancelled"
  | "refund_pending"
  | "refunded";

export interface OrderProductLine {
  id: string;
  name: string;
  slug: string;
  imageUrl?: string | null;
}

export interface OrderVariantLine {
  id: string;
  sku: string;
  weightGrams: number;
  price: number;
  currency: CurrencyCode;
}

export interface HubOperationSummary {
  status: string;
  label: string;
  receivedAt?: IsoDateTime | null;
  readyAt?: IsoDateTime | null;
  handedOffAt?: IsoDateTime | null;
}

export interface OrderItemServiceSummary {
  id: string;
  type: string;
  providerType: string;
  status: string;
  grindingProfile?: {
    id: string;
    code: string;
    version: number;
    name: string;
    brewMethod: string;
  } | null;
  serviceFee: number;
  packagingFee: number;
  shippingFee: number;
  taxAmount: number;
  totalAmount: number;
  currency: CurrencyCode;
  isFree: boolean;
  label?: string | null;
  hubOperation?: HubOperationSummary | null;
}

export interface OrderLine {
  id: string;
  product: OrderProductLine;
  variant: OrderVariantLine;
  quantity: number;
  lineTotal: number;
  services: OrderItemServiceSummary[];
}

export interface ShipmentSummary {
  id: string;
  carrier?: string | null;
  trackingCode?: string | null;
  status: string;
  shippedAt?: IsoDateTime | null;
  deliveredAt?: IsoDateTime | null;
}

export interface ShipmentDeliveryConfirmationSummary {
  source: "customer" | "administrator" | "carrier";
  proofType: string;
  confirmedAt: IsoDateTime;
}

export interface ShipmentLegSummary {
  id: string;
  routeType: string;
  sequence: number;
  isFinal: boolean;
  status: string;
  carrier?: string | null;
  trackingCode?: string | null;
  totalAmount: number;
  currency: CurrencyCode;
  plannedAt?: IsoDateTime | null;
  pickedUpAt?: IsoDateTime | null;
  deliveredAt?: IsoDateTime | null;
  deliveryConfirmation?: ShipmentDeliveryConfirmationSummary | null;
}

export interface DeliverySummary {
  confirmedAt?: IsoDateTime | null;
  disputeWindowEndsAt?: IsoDateTime | null;
  customerCanConfirm: boolean;
  settlementState: "not_delivered" | "dispute_hold" | "blocked" | "released";
  settlementHoldCode?: string | null;
  settlementReleasedAt?: IsoDateTime | null;
}

export interface FulfillmentIncidentSummary {
  id: string;
  status: "open" | "resolved";
  code: string;
  severity: "medium" | "high" | "critical";
  resolution?: "resume_fulfillment" | "cancel_and_refund" | null;
  reportedAt: IsoDateTime;
  resolvedAt?: IsoDateTime | null;
}

export interface FulfillmentCommitmentSummary {
  acceptanceMode: "awaiting_payment" | "automatic_contractual";
  committedAt?: IsoDateTime | null;
  preparationDueAt?: IsoDateTime | null;
  handoffDueAt?: IsoDateTime | null;
  slaStatus: string;
  isBreached: boolean;
}

export interface SubOrderSummary {
  id: string;
  status: SubOrderStatus;
  acceptanceStatus: string;
  customerCancellable: boolean;
  fulfillment: FulfillmentCommitmentSummary;
  delivery: DeliverySummary;
  incidents: FulfillmentIncidentSummary[];
  roastery: Pick<RoasterySummary, "id" | "name" | "slug">;
  items: OrderLine[];
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingTotal: number;
  discountTotal: number;
  taxTotal: number;
  grandTotal: number;
  currency: CurrencyCode;
  shipment?: ShipmentSummary | null;
  shipmentLegs: ShipmentLegSummary[];
}

export interface OrderSummary {
  id: string;
  orderNumber: string;
  status: OrderStatus;
  placedAt?: IsoDateTime | null;
  grandTotal: number;
  currency: CurrencyCode;
  subOrders: SubOrderSummary[];
}

export interface OrderDetail extends OrderSummary {
  address?: Address | null;
  subtotal: number;
  packagingTotal: number;
  grindingTotal: number;
  shippingTotal: number;
  discountTotal: number;
}

export interface AuthUser {
  id: string;
  mobile: string;
  name?: string | null;
  email?: string | null;
  roles: string[];
}

export interface AddressInput {
  title?: string | null;
  recipientName: string;
  recipientMobile: string;
  province: string;
  city: string;
  addressLine: string;
  postalCode?: string | null;
  isDefault: boolean;
}

export interface Address extends AddressInput {
  id: string;
}
