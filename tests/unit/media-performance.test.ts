import { describe, expect, test } from "bun:test";
import { bestMediaUrl, mediaSrcSet } from "../../src/lib/catalog-format";
import type { MediaAsset } from "../../src/lib/api/contracts";

const media: MediaAsset = {
  id: "media-1",
  alt: "دانه قهوه",
  width: 1200,
  height: 800,
  sources: [
    { url: "https://cdn.rosta.shop/coffee-480.webp", width: 480, format: "webp" },
    { url: "https://cdn.rosta.shop/coffee-1200.webp", width: 1200, format: "webp" },
    { url: "https://cdn.rosta.shop/coffee-1200.jpg", width: 1200, format: "jpeg" },
  ],
};

describe("responsive public media", () => {
  test("keeps the largest source as the fallback", () => {
    expect(bestMediaUrl(media)).toContain("1200");
  });

  test("builds a width-ordered srcset from one compatible format family", () => {
    expect(mediaSrcSet(media)).toBe(
      "https://cdn.rosta.shop/coffee-480.webp 480w, https://cdn.rosta.shop/coffee-1200.webp 1200w",
    );
  });

  test("returns no srcset when media has no usable sources", () => {
    expect(mediaSrcSet(null)).toBeUndefined();
  });
});
