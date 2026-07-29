import { absoluteUrl, siteConfig } from "@/config/site";
import type { ContentEntry } from "@/lib/api/content";

export interface SeoHeadInput {
  title: string;
  description?: string | null;
  path: string;
  index?: boolean;
  follow?: boolean;
  type?: "website" | "article" | "product";
  image?: string | null;
  publishedAt?: string | null;
  modifiedAt?: string | null;
  jsonLd?: unknown[];
}

function safeJson(value: unknown): string {
  return JSON.stringify(value).replace(/</g, "\\u003c");
}

export function seoHead(input: SeoHeadInput) {
  const canonical = absoluteUrl(input.path);
  const description = input.description?.trim() || siteConfig.description;
  const image = input.image ? absoluteUrl(input.image) : absoluteUrl(siteConfig.socialImagePath);
  const index = input.index ?? siteConfig.allowIndexing;
  const follow = input.follow ?? true;

  return {
    meta: [
      { title: input.title },
      { name: "description", content: description },
      {
        name: "robots",
        content: `${index ? "index" : "noindex"},${follow ? "follow" : "nofollow"}`,
      },
      { property: "og:site_name", content: siteConfig.name },
      { property: "og:type", content: input.type ?? "website" },
      { property: "og:locale", content: siteConfig.locale },
      { property: "og:title", content: input.title },
      { property: "og:description", content: description },
      { property: "og:url", content: canonical },
      { property: "og:image", content: image },
      { property: "og:image:alt", content: input.title },
      ...(!input.image
        ? [
            { property: "og:image:width", content: "1200" },
            { property: "og:image:height", content: "630" },
          ]
        : []),
      { name: "twitter:card", content: "summary_large_image" },
      { name: "twitter:title", content: input.title },
      { name: "twitter:description", content: description },
      { name: "twitter:image", content: image },
      ...(input.publishedAt
        ? [{ property: "article:published_time", content: input.publishedAt }]
        : []),
      ...(input.modifiedAt
        ? [{ property: "article:modified_time", content: input.modifiedAt }]
        : []),
    ],
    links: [{ rel: "canonical", href: canonical }],
    scripts: (input.jsonLd ?? []).map((value) => ({
      type: "application/ld+json",
      children: safeJson(value),
    })),
  };
}

export function contentSeoHead(entry: ContentEntry) {
  const article = ["article", "guide", "comparison"].includes(entry.type);
  const jsonLd: unknown[] = [
    {
      "@context": "https://schema.org",
      "@type": entry.seo.schema_type,
      headline: entry.title,
      description: entry.seo.description ?? entry.excerpt ?? undefined,
      url: absoluteUrl(entry.seo.canonical_path),
      datePublished: entry.published_at ?? undefined,
      dateModified: entry.updated_at ?? entry.published_at ?? undefined,
      inLanguage: siteConfig.language,
      author: entry.author
        ? {
            "@type": "Person",
            name: entry.author.name,
          }
        : undefined,
      publisher: {
        "@type": "Organization",
        name: siteConfig.name,
        url: siteConfig.siteUrl,
        logo: {
          "@type": "ImageObject",
          url: absoluteUrl("/icon-512.png"),
        },
      },
      keywords: entry.keywords.join(", ") || undefined,
      image: entry.seo.og_media_url ?? undefined,
    },
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        {
          "@type": "ListItem",
          position: 1,
          name: "خانه",
          item: absoluteUrl("/"),
        },
        {
          "@type": "ListItem",
          position: 2,
          name: entry.title,
          item: absoluteUrl(entry.seo.canonical_path),
        },
      ],
    },
  ];

  const faqBlocks = entry.body.filter((block) => block.type === "faq");
  const faqItems = faqBlocks.flatMap((block) => (block.type === "faq" ? block.items : []));
  if (faqItems.length) {
    jsonLd.push({
      "@context": "https://schema.org",
      "@type": "FAQPage",
      mainEntity: faqItems.map((item) => ({
        "@type": "Question",
        name: item.question,
        acceptedAnswer: { "@type": "Answer", text: item.answer },
      })),
    });
  }

  return seoHead({
    title: entry.seo.title,
    description: entry.seo.description,
    path: entry.seo.canonical_path,
    index: siteConfig.allowIndexing && entry.seo.robots_index,
    follow: entry.seo.robots_follow,
    type: article ? "article" : "website",
    image: entry.seo.og_media_url,
    publishedAt: entry.published_at,
    modifiedAt: entry.updated_at,
    jsonLd,
  });
}
