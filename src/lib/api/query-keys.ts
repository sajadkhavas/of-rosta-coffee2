import type { ProductFilters } from "@/lib/api/contracts";

export const queryKeys = {
  auth: {
    all: ["auth"] as const,
    me: () => ["auth", "me"] as const,
    sessions: () => ["auth", "sessions"] as const,
  },
  products: {
    all: ["products"] as const,
    list: (filters: ProductFilters) => ["products", "list", filters] as const,
    detail: (slug: string) => ["products", "detail", slug] as const,
    related: (slug: string) => ["products", "related", slug] as const,
  },
  roasteries: {
    all: ["roasteries"] as const,
    list: (params?: Record<string, unknown>) => ["roasteries", "list", params ?? {}] as const,
    detail: (slug: string) => ["roasteries", "detail", slug] as const,
    products: (slug: string, params?: Record<string, unknown>) =>
      ["roasteries", slug, "products", params ?? {}] as const,
  },
  search: {
    all: ["search"] as const,
    results: (query: string, type = "all") => ["search", query, type] as const,
    suggestions: (query: string) => ["search", "suggestions", query] as const,
  },
  cart: {
    all: ["cart"] as const,
    current: () => ["cart", "current"] as const,
    quote: () => ["cart", "quote"] as const,
  },
  orders: {
    all: ["orders"] as const,
    list: (params?: Record<string, unknown>) => ["orders", "list", params ?? {}] as const,
    detail: (id: string) => ["orders", "detail", id] as const,
  },
  profile: {
    all: ["profile"] as const,
    addresses: () => ["profile", "addresses"] as const,
    taste: () => ["profile", "taste"] as const,
    wishlist: () => ["profile", "wishlist"] as const,
  },
} as const;
