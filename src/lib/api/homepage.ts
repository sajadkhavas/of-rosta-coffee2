import { queryOptions } from "@tanstack/react-query";
import { isApiError } from "./client";
import { listProducts, listRoasteries } from "./catalog";
import { getContentByPath, type ContentBlock, type ContentEntry } from "./content";

export interface HomeFaq {
  question: string;
  answer: string;
}

export interface HomepageData {
  products: Awaited<ReturnType<typeof listProducts>>["items"];
  roasteries: Awaited<ReturnType<typeof listRoasteries>>["items"];
  productCount: number;
  roasteryCount: number;
  faqs: HomeFaq[];
  editorial: ContentEntry | null;
}

type FaqBlock = Extract<ContentBlock, { type: "faq" }>;

async function optionalHomepageContent(): Promise<ContentEntry | null> {
  try {
    return await getContentByPath("/");
  } catch (error) {
    if (isApiError(error) && error.status === 404) return null;
    throw error;
  }
}

function extractFaqs(entry: ContentEntry | null): HomeFaq[] {
  if (!entry) return [];
  return entry.body
    .filter((block): block is FaqBlock => block.type === "faq")
    .flatMap((block) => block.items)
    .slice(0, 12)
    .map((item) => ({ question: item.question, answer: item.answer }));
}

export async function getHomepageData(): Promise<HomepageData> {
  const [products, roasteries, editorial] = await Promise.all([
    listProducts({ sort: "newest", page: 1, perPage: 8 }),
    listRoasteries({ page: 1, perPage: 3 }),
    optionalHomepageContent(),
  ]);

  return {
    products: products.items,
    roasteries: roasteries.items,
    productCount: products.meta?.total ?? products.items.length,
    roasteryCount: roasteries.meta?.total ?? roasteries.items.length,
    faqs: extractFaqs(editorial),
    editorial,
  };
}

export const homepageQueryOptions = () =>
  queryOptions({
    queryKey: ["public", "homepage", "live"],
    queryFn: getHomepageData,
    staleTime: 60_000,
  });
