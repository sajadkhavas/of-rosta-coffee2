import { describe, expect, test } from "bun:test";
import fs from "node:fs";

describe("R5J Hub operations boundary", () => {
  test("browser sends actions but never authoritative operator, money or timestamps", () => {
    const api = fs.readFileSync("src/lib/api/hub-operations.ts", "utf8");
    expect(api).toContain("idempotency_key");
    expect(api).toContain("operator_id");
    expect(api).not.toContain("provider_hub_id");
    expect(api).not.toContain("settlement_owner");
    expect(api).not.toContain("private_evidence");
    const transition = api.slice(api.indexOf("export async function transitionHubWorkItem"));
    expect(transition).not.toContain("handed_off_at:");
  });
  test("customer surface renders only safe public Hub progress", () => {
    const page = fs.readFileSync("src/routes/orders.$id.tsx", "utf8");
    expect(page).toContain("hubOperation.label");
    expect(page).not.toContain("assignedOperator");
    expect(page).not.toContain("privateEvidence");
  });
});
