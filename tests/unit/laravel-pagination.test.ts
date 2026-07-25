import { describe, expect, test } from "bun:test";
import { z } from "zod";
import { laravelCollectionSchema } from "../../src/lib/api/pagination";

const schema = laravelCollectionSchema(
  z
    .object({
      id: z.string().min(1),
    })
    .strict(),
);

const standardPayload = {
  data: [{ id: "product-1" }],
  links: {
    first: "http://127.0.0.1:8000/api/v1/products?page=1",
    last: "http://127.0.0.1:8000/api/v1/products?page=1",
    prev: null,
    next: null,
  },
  meta: {
    current_page: 1,
    from: 1,
    last_page: 1,
    links: [
      { url: null, label: "&laquo; Previous", page: null, active: false },
      {
        url: "http://127.0.0.1:8000/api/v1/products?page=1",
        label: "1",
        page: 1,
        active: true,
      },
      { url: null, label: "Next &raquo;", page: null, active: false },
    ],
    path: "http://127.0.0.1:8000/api/v1/products",
    per_page: 24,
    to: 1,
    total: 1,
  },
};

describe("Laravel pagination contract", () => {
  test("accepts the standard Laravel 13 resource collection metadata", () => {
    const result = schema.parse(standardPayload);

    expect(result.data).toEqual([{ id: "product-1" }]);
    expect(result.meta?.current_page).toBe(1);
    expect(result.meta?.links?.[1]?.page).toBe(1);
    expect(result.meta?.links?.[1]?.active).toBe(true);
    expect(result.links?.next).toBeNull();
  });

  test("accepts null from, to and link page values for an empty collection", () => {
    const result = schema.parse({
      ...standardPayload,
      data: [],
      meta: {
        ...standardPayload.meta,
        from: null,
        to: null,
        total: 0,
      },
    });

    expect(result.meta?.from).toBeNull();
    expect(result.meta?.to).toBeNull();
    expect(result.meta?.links?.[0]?.page).toBeNull();
    expect(result.meta?.total).toBe(0);
  });

  test("rejects pagination metadata outside bounded Laravel values", () => {
    expect(() =>
      schema.parse({
        ...standardPayload,
        meta: {
          ...standardPayload.meta,
          per_page: 10_000,
        },
      }),
    ).toThrow();

    expect(() =>
      schema.parse({
        ...standardPayload,
        meta: {
          ...standardPayload.meta,
          links: Array.from({ length: 101 }, (_, index) => ({
            url: null,
            label: String(index),
            page: null,
            active: false,
          })),
        },
      }),
    ).toThrow();
  });
});
