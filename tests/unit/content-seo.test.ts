import { describe, expect, test } from "bun:test";
import { contentBlockSchema } from "../../src/lib/api/content";

describe("structured SEO content contract", () => {
  test("accepts safe editorial blocks", () => {
    expect(
      contentBlockSchema.parse({
        type: "faq",
        items: [
          {
            question: "قهوه چگونه ارسال می‌شود؟",
            answer: "تمام محصولات رستا فقط به‌صورت دانه کامل ارسال می‌شوند.",
          },
        ],
      }),
    ).toEqual({
      type: "faq",
      items: [
        {
          question: "قهوه چگونه ارسال می‌شود؟",
          answer: "تمام محصولات رستا فقط به‌صورت دانه کامل ارسال می‌شوند.",
        },
      ],
    });
  });

  test("rejects raw html blocks and undeclared fields", () => {
    expect(() =>
      contentBlockSchema.parse({
        type: "html",
        html: "<script>alert(1)</script>",
      }),
    ).toThrow();

    expect(() =>
      contentBlockSchema.parse({
        type: "paragraph",
        text: "متن معتبر",
        html: "<b>متن</b>",
      }),
    ).toThrow();
  });

  test("rejects comparison rows with a different column count", () => {
    expect(() =>
      contentBlockSchema.parse({
        type: "comparison_table",
        columns: ["ویژگی", "گزینه اول", "گزینه دوم"],
        rows: [["اسیدیته", "زیاد"]],
      }),
    ).toThrow("تعداد سلول‌های جدول با ستون‌ها برابر نیست.");
  });

  test("bounds product and roastery relationship identifiers", () => {
    expect(() =>
      contentBlockSchema.parse({
        type: "product_grid",
        product_slugs: [],
      }),
    ).toThrow();

    expect(() =>
      contentBlockSchema.parse({
        type: "roastery_spotlight",
        roastery_slug: "x".repeat(241),
      }),
    ).toThrow();
  });
});
