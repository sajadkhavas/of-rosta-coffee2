import { describe, expect, test } from "bun:test";
import { classifyPerformanceTier, rateWebVital } from "../../src/lib/performance";

describe("frontend performance policy", () => {
  test("disables enhanced motion for accessibility and data-saving signals", () => {
    expect(
      classifyPerformanceTier({
        reducedMotion: true,
        saveData: false,
        pointerFine: true,
        deviceMemory: 8,
        hardwareConcurrency: 8,
        effectiveType: "4g",
      }),
    ).toBe("minimal");

    expect(
      classifyPerformanceTier({
        reducedMotion: false,
        saveData: true,
        pointerFine: true,
        deviceMemory: 8,
        hardwareConcurrency: 8,
        effectiveType: "4g",
      }),
    ).toBe("minimal");
  });

  test("uses a balanced tier for moderate devices and coarse pointers", () => {
    expect(
      classifyPerformanceTier({
        reducedMotion: false,
        saveData: false,
        pointerFine: false,
        deviceMemory: 4,
        hardwareConcurrency: 4,
        effectiveType: "3g",
      }),
    ).toBe("balanced");
  });

  test("enables the complete experience only for capable clients", () => {
    expect(
      classifyPerformanceTier({
        reducedMotion: false,
        saveData: false,
        pointerFine: true,
        deviceMemory: 8,
        hardwareConcurrency: 8,
        effectiveType: "4g",
      }),
    ).toBe("full");
  });

  test("rates Core Web Vitals at their frozen boundaries", () => {
    expect(rateWebVital("LCP", 2500)).toBe("good");
    expect(rateWebVital("LCP", 2501)).toBe("needs-improvement");
    expect(rateWebVital("LCP", 4001)).toBe("poor");
    expect(rateWebVital("CLS", 0.1)).toBe("good");
    expect(rateWebVital("INP", 501)).toBe("poor");
  });
});
