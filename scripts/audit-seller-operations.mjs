import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  layout: "src/routes/panel.tsx",
  route: "src/routes/panel.index.tsx",
  manageRoute: "src/routes/panel.manage.tsx",
  dashboard: "src/components/seller/SellerOperationsDashboard.tsx",
  client: "src/lib/api/seller-operations.ts",
  onboarding: "src/lib/api/seller-onboarding.ts",
  stock: "src/lib/api/seller-stock-ledger.ts",
  navbar: "src/components/Navbar.tsx",
  routeTree: "src/routeTree.gen.ts",
  backendRoutes: "backend/routes/api.php",
  sellerBootstrapRoutes: "backend/routes/seller-bootstrap.php",
  fulfillmentRoutes: "backend/routes/fulfillment.php",
  mediaRoutes: "backend/routes/media-uploads.php",
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
  "permanent_seller_workspace_gate",
  packageJson.scripts?.["audit:seller-operations"] === "node scripts/audit-seller-operations.mjs" &&
    packageJson.scripts?.check?.includes("audit:seller-operations"),
  "The seller operations audit must remain in the permanent frontend check chain.",
);

gate(
  "nested_panel_layout",
  files.layout.includes('createFileRoute("/panel")') &&
    files.layout.includes("Outlet") &&
    files.manageRoute.includes('createFileRoute("/panel/manage")'),
  "The seller panel parent must render nested routes so the management workspace cannot be shadowed by the daily dashboard.",
);

gate(
  "protected_non_indexable_panel",
  files.route.includes('createFileRoute("/panel/")') &&
    files.route.includes("AccountGuard") &&
    files.route.includes('content: "noindex,nofollow"'),
  "The seller workspace index must require an authenticated account and stay outside search indexing.",
);

gate(
  "scoped_roastery_bootstrap",
  files.client.includes('apiFetch<unknown>("/seller/roasteries")') &&
    files.client.includes("access_roles") &&
    files.dashboard.includes("selectedRoasteryId") &&
    files.sellerBootstrapRoutes.includes("Route::get('/seller/roasteries'"),
  "The browser must start from the backend-scoped roastery selector instead of accepting an arbitrary roastery ID.",
);

gate(
  "whole_bean_catalog_contract",
  files.dashboard.includes("وزن‌های دانه کامل") &&
    files.client.includes("weightGrams: 50 | 100 | 250 | 500 | 1000") &&
    files.client.includes("weight_grams: input.weightGrams") &&
    !/grind[_-]?(selector|state)|grind_option/i.test(
      [files.dashboard, files.client, files.onboarding].join("\n"),
    ),
  "The panel may manage only fixed whole-bean weight variants and must never introduce grind state.",
);

gate(
  "authoritative_inventory_ledger",
  files.dashboard.includes("موجودی authoritative") &&
    files.dashboard.includes("idempotencyKey: newLedgerIdempotencyKey()") &&
    files.client.includes("/stock-adjustments") &&
    files.stock.includes('"reservation_release"') &&
    files.stock.includes("collectionSchema(stockLedgerEntrySchema)"),
  "Stock writes must use idempotent ledger adjustments and reads must parse the authoritative StockReason enum.",
);

gate(
  "fulfillment_state_machine_ui",
  files.dashboard.includes("پذیرش قراردادی خودکار") &&
    files.dashboard.includes("ready_to_ship") &&
    files.dashboard.includes("trackingCode") &&
    files.dashboard.includes("ثبت Incident برای پشتیبانی رستا") &&
    !files.dashboard.includes("رد سفارش و ارسال به Refund Pending") &&
    files.client.includes("/fulfillment") &&
    files.client.includes("/incidents") &&
    files.fulfillmentRoutes.includes("throttle:fulfillment-transition"),
  "Seller order actions must start after automatic commitment, collect tracking and escalate exceptions without rejection.",
);

gate(
  "secure_signed_media_upload",
  files.client.includes("sha256Hex") &&
    files.client.includes("crypto.subtle.digest") &&
    files.client.includes("upload_url") &&
    files.client.includes("/complete") &&
    files.dashboard.includes("Signed PUT") &&
    files.mediaRoutes.includes("/media/uploads"),
  "Media must use checksum-bound signed upload intents and backend completion, not arbitrary public URLs.",
);

gate(
  "seller_role_boundaries_visible",
  files.dashboard.includes("canEditCatalog") &&
    files.dashboard.includes("roastery_staff") &&
    files.dashboard.includes("نقش Staff نمی‌تواند") &&
    files.client.includes("SellerAccessRole"),
  "The workspace must distinguish owner/manager catalog writes from staff operational access.",
);

gate(
  "loading_error_empty_states",
  files.dashboard.includes("Skeleton") &&
    files.dashboard.includes("EmptyState") &&
    files.dashboard.includes('variant="danger"') &&
    files.dashboard.includes("isLoading"),
  "Catalog, orders and media surfaces must provide explicit loading, error and empty states.",
);

gate(
  "route_and_navigation_registered",
  files.navbar.includes('to="/panel"') &&
    files.routeTree.includes("PanelRouteImport") &&
    /["']\/panel["']/.test(files.routeTree) &&
    files.route.includes('to="/panel/manage"'),
  "The seller panel must remain reachable from navigation and link to the nested management workspace.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generatedAt: new Date().toISOString(),
  marker: "seller_operations_workspace=ready",
  passed: failed.length === 0,
  gates,
};
await writeFile("frontend-seller-operations-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("Seller operations workspace audit failed:");
  failed.forEach((item) => console.error(`- ${item.name}: ${item.evidence}`));
  process.exit(1);
}

console.log(`Seller operations workspace audit passed (${gates.length} gates).`);
