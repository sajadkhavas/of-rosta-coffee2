import { describe, expect, test } from "bun:test";
import { productFiltersToSearch } from "../../src/lib/api/catalog";

describe("catalog filter query contract", () => {
  test("serializes booleans using Laravel accepted 1 and 0 values", () => {
    expect(productFiltersToSearch({ available: true }).get("available")).toBe("1");
    expect(productFiltersToSearch({ available: false }).get("available")).toBe("0");
  });

  test("omits availability when the filter is not specified", () => {
    expect(productFiltersToSearch({}).has("available")).toBe(false);
  });

  test("keeps quiz pagination within the authoritative request bounds", () => {
    const search = productFiltersToSearch({
      available: true,
      sort: "recommended",
      page: 1,
      perPage: 100,
    });

    expect(search.toString()).toBe("available=1&sort=recommended&page=1&per_page=100");
  });
});
