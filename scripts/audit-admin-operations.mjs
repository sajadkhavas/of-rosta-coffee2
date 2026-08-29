import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  route: "src/routes/admin.operations.tsx",
  workspaceRoute: "src/routes/admin.workspace.tsx",
  client: "src/lib/api/admin-operations.ts",
  workspaceClient: "src/lib/api/workspaces.ts",
  workspaceService: "backend/app/Services/Workspace/WorkspaceKpiService.php",
  navbar: "src/components/Navbar.tsx",
  routeTree: "src/routeTree.gen.ts",
  controller: "backend/app/Http/Controllers/Admin/AdminOperationsController.php",
  roasteries: "backend/app/Http/Controllers/Admin/AdminRoasteryController.php",
  products: "backend/app/Http/Controllers/Admin/AdminProductController.php",
  routes: "backend/routes/admin-operations.php",
};
const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);
const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, condition, evidence) =>
  gates.push({ name, passed: Boolean(condition), evidence });

gate(
  "permanent_admin_operations_gate",
  packageJson.scripts?.["audit:admin-operations"] === "node scripts/audit-admin-operations.mjs" &&
    packageJson.scripts?.check?.includes("audit:admin-operations"),
  "Admin operations audit must remain in bun run check.",
);
gate(
  "administrator_only_non_indexable_route",
  files.route.includes('createFileRoute("/admin/operations")') &&
    files.route.includes('user.roles.includes("administrator")') &&
    files.route.includes('content: "noindex,nofollow"'),
  "The operations workspace must be administrator-only and non-indexable.",
);
gate(
  "server_defined_admin_workspace_kpis",
  files.workspaceRoute.includes('createFileRoute("/admin/workspace")') &&
    files.workspaceRoute.includes('user.roles.includes("administrator")') &&
    files.workspaceRoute.includes('content: "noindex,nofollow"') &&
    files.workspaceRoute.includes("adminWorkspaceQueryOptions") &&
    files.workspaceClient.includes('apiFetch<unknown>("/admin/operations/workspace")') &&
    files.controller.includes("WorkspaceKpiService") &&
    files.routes.includes("/workspace") &&
    files.workspaceService.includes("open_financial_reconciliation"),
  "PS5.4 admin KPIs must be composed by the backend, parsed by a typed client and rendered only to administrators.",
);
gate(
  "admin_workspace_does_not_recompute_money",
  files.workspaceRoute.includes("هیچ مبلغ، نرخ یا حقیقت مالی را در مرورگر بازسازی نمی‌کند") &&
    !/(gross_total|net_total|commission_total|gmv|revenue)/i.test(files.workspaceClient),
  "The admin workspace may count reconciliation cases but must never reconstruct financial amounts in the browser.",
);
gate(
  "admin_workspace_accessibility",
  files.workspaceRoute.includes("Skeleton") &&
    files.workspaceRoute.includes('variant="danger"') &&
    files.workspaceRoute.includes('aria-live="polite"') &&
    files.workspaceRoute.includes("focus-visible:ring-2"),
  "The final admin composition needs loading/error/success semantics, live KPI output and keyboard-visible navigation.",
);
gate(
  "catalog_moderation_queues",
  files.route.includes("RoasteriesQueue") &&
    files.route.includes("ProductsQueue") &&
    files.client.includes("setRoasteryStatus") &&
    files.client.includes("setProductStatus") &&
    files.roasteries.includes("'status' => $roastery->status->value") &&
    files.products.includes("$query->where('status', $status)"),
  "Roastery and product moderation must use private filterable backend queues.",
);
gate(
  "review_and_inquiry_operations",
  files.route.includes("ReviewsQueue") &&
    files.route.includes("InquiriesQueue") &&
    files.client.includes("moderateReview") &&
    files.client.includes("setInquiryStatus"),
  "Review moderation and inquiry lifecycle actions must remain wired to authoritative APIs.",
);
gate(
  "redacted_notification_boundary",
  files.controller.includes("destination_hint") &&
    files.controller.includes("maskDestination") &&
    !files.controller.includes("'payload' => $item->payload") &&
    files.route.includes("Payload پیام هرگز به مرورگر ارسال نمی‌شود"),
  "Notification destinations must be masked and encrypted payloads must never reach the browser.",
);
gate(
  "append_only_audit_visibility",
  files.controller.includes("redactMetadata") &&
    files.controller.includes("AuditLog::query()") &&
    files.route.includes("Audit Log فقط‌خواندنی") &&
    !files.client.includes("updateAudit"),
  "Audit log access must be read-only with metadata redaction.",
);
gate(
  "strict_runtime_contracts",
  files.client.includes(".strict()") &&
    files.client.includes("reviewStatusSchema") &&
    files.client.includes("inquiryStatusSchema") &&
    files.client.includes("notificationStatusSchema") &&
    files.workspaceClient.includes(".strict()"),
  "Admin operations and workspace responses must be parsed through strict runtime schemas.",
);
gate(
  "route_and_navigation_registered",
  files.routes.includes("rosta.role:administrator") &&
    files.navbar.includes('to="/admin/operations"') &&
    files.routeTree.includes("AdminOperationsRouteImport"),
  "Operations routes must be server-protected and registered in navigation and the generated route tree.",
);

const failed = gates.filter((item) => !item.passed);
await writeFile(
  "frontend-admin-operations-audit.json",
  `${JSON.stringify({ generatedAt: new Date().toISOString(), marker: "admin_operations_workspace=ready", passed: failed.length === 0, gates }, null, 2)}\n`,
);
if (failed.length) {
  console.error("Admin operations audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}
console.log(`Admin operations audit passed (${gates.length} gates).`);
