import { describe, expect, test } from "bun:test";
import { formatRoastDate } from "../../src/lib/catalog-format";

describe("catalog SSR formatting contract", () => {
  test("formats roast dates in the canonical Tehran timezone across the local-day boundary", () => {
    expect(formatRoastDate("2026-08-31T20:00:00Z")).toBe("۹ شهریور ۱۴۰۵");
    expect(formatRoastDate("2026-08-31T21:00:00Z")).toBe("۱۰ شهریور ۱۴۰۵");
  });

  test("keeps invalid or missing roast timestamps fail-closed", () => {
    expect(formatRoastDate()).toBeNull();
    expect(formatRoastDate(null)).toBeNull();
    expect(formatRoastDate("not-a-date")).toBeNull();
  });
});
