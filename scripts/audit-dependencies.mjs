import { appendFileSync, readFileSync, writeFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { resolve } from "node:path";

const root = process.cwd();
const policyPath = resolve(root, "security/dependency-audit-exceptions.json");
const reportPath = resolve(root, "dependency-audit.log");
const MAX_EXCEPTION_DAYS = 30;

function fail(message) {
  console.error(`Dependency audit policy error: ${message}`);
  process.exit(2);
}

let policy;
try {
  policy = JSON.parse(readFileSync(policyPath, "utf8"));
} catch (error) {
  fail(`cannot read ${policyPath}: ${error instanceof Error ? error.message : String(error)}`);
}

if (policy.schemaVersion !== 1 || !Array.isArray(policy.exceptions)) {
  fail("policy must use schemaVersion 1 and an exceptions array");
}

const now = Date.now();
const ignores = [];
for (const [index, exception] of policy.exceptions.entries()) {
  const prefix = `exceptions[${index}]`;
  for (const field of ["advisoryId", "reason", "owner", "expiresAt"]) {
    if (typeof exception?.[field] !== "string" || exception[field].trim() === "") {
      fail(`${prefix}.${field} must be a non-empty string`);
    }
  }

  const expiresAt = Date.parse(exception.expiresAt);
  if (!Number.isFinite(expiresAt)) fail(`${prefix}.expiresAt must be an ISO-8601 date`);
  if (expiresAt <= now) fail(`${prefix} expired at ${exception.expiresAt}`);
  if (expiresAt - now > MAX_EXCEPTION_DAYS * 24 * 60 * 60 * 1000) {
    fail(`${prefix} expires more than ${MAX_EXCEPTION_DAYS} days from now`);
  }

  ignores.push(exception.advisoryId.trim());
}

const args = ["audit", "--audit-level=high"];
for (const advisoryId of ignores) args.push("--ignore", advisoryId);

const result = spawnSync("bun", args, {
  cwd: root,
  encoding: "utf8",
  env: process.env,
});

const output = [result.stdout ?? "", result.stderr ?? ""].filter(Boolean).join("");
writeFileSync(reportPath, output || "bun audit produced no output\n", "utf8");
process.stdout.write(output);

if (process.env.GITHUB_STEP_SUMMARY) {
  appendFileSync(
    process.env.GITHUB_STEP_SUMMARY,
    [
      "## Bun dependency security audit",
      "",
      `Command: \`bun ${args.join(" ")}\``,
      `Policy exceptions: ${ignores.length}`,
      `Exit code: ${result.status ?? 1}`,
      "",
      "```text",
      (output || "bun audit produced no output").slice(0, 12000),
      "```",
      "",
    ].join("\n"),
  );
}

if (result.error) fail(`unable to execute bun audit: ${result.error.message}`);
process.exit(result.status ?? 1);
