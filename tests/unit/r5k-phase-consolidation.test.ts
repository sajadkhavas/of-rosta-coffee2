import { describe, expect, test } from "bun:test";
import fs from "node:fs";

describe("R5K canonical program closure", () => {
  test("defines exactly ten canonical phases in execution order", () => {
    const phases = fs.readFileSync("docs/PHASES.md", "utf8");
    const phaseRegister = phases.split("## Evidence matrix")[0];
    const ids = [...phaseRegister.matchAll(/^\| C([0-9]) \|/gm)].map((match) => match[1]);
    expect(ids).toEqual(["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"]);
  });

  test("keeps one immutable integration to release path", () => {
    const phases = fs.readFileSync("docs/PHASES.md", "utf8");
    const workflow = fs.readFileSync(".github/workflows/staging-deploy.yml", "utf8");
    expect(phases).toContain("integration/rosta-r5-marketplace");
    expect(phases).toContain("integration/rosta-release-candidate");
    expect(workflow).toContain("origin/integration/rosta-release-candidate");
    expect(workflow).not.toContain("origin/integration/rosta-r-program");
    expect(workflow).not.toContain("agent/phase-22");
  });

  test("retires duplicate deployment and mock paths", () => {
    for (const path of [
      "backend/docker-compose.staging.yml",
      "backend/scripts/deploy-staging.sh",
      "scripts/deploy-staging-frontend.sh",
      "src/data/mock-orders.ts",
    ]) {
      expect(fs.existsSync(path)).toBe(false);
    }
    expect(fs.existsSync("deploy/staging/docker-compose.yml")).toBe(true);
    expect(fs.existsSync("deploy/staging/deploy.sh")).toBe(true);
  });
});
