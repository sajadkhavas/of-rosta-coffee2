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

export interface MediaAsset {
  id: string;
  alt: string;
  width: number;
  height: number;
  blurDataUrl?: string | null;
  sources: Array<{
    url: string;
    width: number;
    format: "avif" | "webp" | "jpeg" | "png";
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

export interface ProductVariant {
  id: string;
  sku: string;
  weightGrams: 50 | 100 | 250 | 500 | 1000;
  price: number;
  compareAtPrice?: number | null;
  currency: CurrencyCode;
  isAvailable: boolean;
  availableQuantity?: number | null;
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

export interface CartLine {
  id: string;
  product: ProductSummary;
  variant: ProductVariant;
  quantity: number;
  lineTotal: number;
}

export interface CartShipmentGroup {
  roastery: RoasterySummary;
  items: CartLine[];
  subtotal: number;
  shippingCost?: number | null;
}

export interface CartQuote {
  id: string;
  expiresAt: IsoDateTime;
  roasteryId?: string | null;
  groups: CartShipmentGroup[];
  subtotal: number;
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
  | "refund_pending"
  | "refunded";

export type SubOrderStatus =
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

export interface OrderLine {
  id: string;
  product: OrderProductLine;
  variant: OrderVariantLine;
  quantity: number;
  lineTotal: number;
}

export interface ShipmentSummary {
  id: string;
  carrier?: string | null;
  trackingCode?: string | null;
  status: string;
  shippedAt?: IsoDateTime | null;
  deliveredAt?: IsoDateTime | null;
}

export interface SubOrderSummary {
  id: string;
  status: SubOrderStatus;
  roastery: Pick<RoasterySummary, "id" | "name" | "slug">;
  items: OrderLine[];
  subtotal: number;
  shippingTotal: number;
  shipment?: ShipmentSummary | null;
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
