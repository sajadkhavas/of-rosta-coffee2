import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  route: "src/routes/admin.finance.tsx",
  api: "src/lib/api/admin-finance.ts",
  navbar: "src/components/Navbar.tsx",
  routeTree: "src/routeTree.gen.ts",
  backendRoutes: "backend/routes/finance.php",
  dispatchController: "backend/app/Http/Controllers/Admin/AdminDispatchRefundController.php",
};

const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([name, path]) => [name, await readFile(path, "utf8")]),
  ),
);
const packageJson = JSON.parse(files.package);
const gates = [];

function gate(name, condition, evidence) {
  gates.push({ name, passed: Boolean(condition), evidence });
}

gate(
  "permanent_admin_finance_gate",
  packageJson.scripts?.["audit:admin-finance"] === "node scripts/audit-admin-finance.mjs" &&
    packageJson.scripts?.check?.includes("audit:admin-finance"),
  "The finance workspace audit must remain in the permanent frontend check chain.",
);

gate(
  "administrator_guard_and_noindex",
  files.route.includes('createFileRoute("/admin/finance")') &&
    files.route.includes('user.roles.includes("administrator")') &&
    files.route.includes('content: "noindex,nofollow"') &&
    files.backendRoutes.includes("rosta.role:administrator"),
  "Both UI and API finance surfaces must require administrator access and stay non-indexable.",
);

gate(
  "strict_runtime_contracts",
  files.api.includes("refundStatusSchema") &&
    files.api.includes("reconciliationStatusSchema") &&
    files.api.includes(".strict()") &&
    files.api.includes("refundResourceSchema.parse") &&
    files.api.includes("reconciliationResourceSchema.parse"),
  "Refund and reconciliation responses must be parsed through strict Zod schemas.",
);

gate(
  "idempotent_refund_request",
  files.route.includes("newIdempotencyKey") &&
    files.route.includes("idempotencyKey.current") &&
    files.api.includes("idempotency_key: input.idempotencyKey.trim()"),
  "The administrator form must retain one idempotency key across retries of the same request.",
);

gate(
  "dual_control_visible_and_enforced",
  files.route.includes("sameRequester") &&
    files.route.includes("تأیید ادمین دوم") &&
    files.route.includes("ثبت‌کننده درخواست نمی‌تواند") &&
    files.backendRoutes.includes("/admin/refunds/{refundId}/approve"),
  "The UI must expose the two-administrator rule instead of offering an unsafe approval action.",
);

gate(
  "authoritative_manual_resolution",
  files.route.includes("ثبت نتیجه authoritative") &&
    files.route.includes("providerReference") &&
    files.route.includes("failureCode") &&
    files.api.includes("/resolve") &&
    files.api.includes('outcome: "succeeded" | "failed" | "cancelled"'),
  "Manual resolution must capture an explicit provider outcome and authoritative reference or failure code.",
);

gate(
  "provider_payload_not_exposed",
  !files.route.includes("request_payload") &&
    !files.route.includes("response_payload") &&
    !files.api.includes("request_payload") &&
    !files.api.includes("response_payload") &&
    !files.dispatchController.includes("request_payload") &&
    !files.dispatchController.includes("response_payload"),
  "Encrypted provider payloads must never be returned to or rendered by the administrator browser.",
);

gate(
  "complete_finance_operations",
  [
    "/admin/finance/refunds",
    "/admin/finance/reconciliation",
    "/admin/orders/",
    "/approve",
    "/dispatch",
    "/resolve",
  ].every((fragment) => files.api.includes(fragment)),
  "The client must cover listing, requesting, approving, dispatching and resolving finance operations.",
);

gate(
  "loading_error_empty_states",
  files.route.includes("Skeleton") &&
    files.route.includes("EmptyState") &&
    files.route.includes('variant="danger"') &&
    files.route.includes("isLoading"),
  "The finance workspace must provide explicit loading, error and empty states.",
);

gate(
  "route_and_navigation_registered",
  files.navbar.includes('to="/admin/finance"') &&
    files.navbar.includes("isFinanceActive") &&
    files.routeTree.includes("AdminFinanceRouteImport") &&
    /["']\/admin\/finance["']/.test(files.routeTree),
  "The administrator finance route must be present in navigation and the generated route tree.",
);

gate(
  "whole_bean_boundary",
  !/grind[_-]?(selector|state)|grind_option/i.test(
    [files.route, files.api, files.navbar].join("\n"),
  ),
  "The finance workspace must not introduce grind selection or grind state.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "admin_finance_workspace=ready",
  passed: failed.length === 0,
  gates,
};

await writeFile("frontend-admin-finance-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("Administrator finance workspace audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`Administrator finance workspace audit passed (${gates.length} gates).`);
