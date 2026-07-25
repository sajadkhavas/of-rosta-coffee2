import { readFile, writeFile } from "node:fs/promises";

const paths = {
  package: "package.json",
  domain: "docs/r5/r5a-domain-contract.md",
  states: "docs/r5/r5a-state-machines.md",
  ledger: "docs/r5/r5a-ledger-refund-contract.md",
  api: "docs/r5/r5a-api-acceptance-contract.md",
};

const files = Object.fromEntries(
  await Promise.all(
    Object.entries(paths).map(async ([key, path]) => [key, await readFile(path, "utf8")]),
  ),
);

const packageJson = JSON.parse(files.package);
const gates = [];
const gate = (name, passed, evidence) => gates.push({ name, passed: Boolean(passed), evidence });
const hasAll = (source, fragments) => fragments.every((fragment) => source.includes(fragment));

const scripts = packageJson.scripts ?? {};
gate(
  "permanent_r5a_gate",
  scripts["audit:r5a"] === "node scripts/audit-r5a-domain-contracts.mjs" &&
    scripts.check?.includes("audit:r5a"),
  "R5A must remain part of the permanent frontend check chain.",
);

gate(
  "one_payment_multi_roastery_contract",
  hasAll(files.domain, [
    "products from multiple roasteries in one cart",
    "one checkout and one payment",
    "one parent order and one sub-order per roastery",
    "duplicate parent orders, duplicate sub-orders or duplicate allocations",
  ]),
  "The marketplace contract must preserve one customer payment with independently controlled roastery sub-orders.",
);

gate(
  "whole_bean_inventory_service_boundary",
  hasAll(files.domain, [
    "Product inventory remains whole-bean inventory",
    "Grinding is an order-item service",
    "must not be added to:",
    "stock ledger identity",
    "inventory reservation identity",
  ]),
  "Grinding may be introduced only as an order-item service, never as product or inventory state.",
);

gate(
  "optional_packaging_contract",
  hasAll(files.domain, [
    "Packaging fees are optional per product and may be zero",
    "Roastery packaging: free",
    "Rosta Hub packaging is always free",
    "forces the roastery packaging allocation for that item to zero",
  ]) &&
    hasAll(files.api, [
      "packaging_fee_mode: free | fixed",
      "Zero must not be omitted",
      "explicit free Hub packaging",
    ]),
  "Roastery packaging must remain optional and visible, while Rosta Hub packaging remains explicitly free.",
);

gate(
  "grinding_provider_eligibility_contract",
  hasAll(files.domain, [
    "grinding_capability = available | unavailable",
    "Tehran or Karaj",
    "The frontend may explain eligibility but Laravel is authoritative",
  ]) &&
    hasAll(files.api, [
      "hub_zone_ineligible",
      "hub_capacity_unavailable",
      "unsupported_grind_profile",
    ]),
  "Grinding provider and Hub eligibility must be authoritative, visible and geographically bounded.",
);

gate(
  "customer_cancellation_closes_on_acceptance",
  hasAll(files.domain, [
    "awaiting_roastery_acceptance",
    "customer_cancellable = false",
    "If cancellation commits first, acceptance is rejected",
    "If acceptance commits first, cancellation is rejected",
  ]) &&
    hasAll(files.states, [
      "cancelled_by_customer",
      "After `accepted`, every customer cancellation endpoint returns a domain conflict",
      "Acceptance, rejection and customer cancellation lock the same sub-order row",
    ]) &&
    hasAll(files.api, [
      "customer_cancellation_closed",
      "After acceptance, return a domain conflict without side effects",
    ]),
  "Customer cancellation must exist only before roastery acceptance and must be race-safe.",
);

gate(
  "seller_rejection_is_independent_partial_refund",
  hasAll(files.domain, [
    "A roastery rejection is a seller action, not a customer cancellation",
    "leave accepted or completed sibling sub-orders unchanged",
  ]) &&
    hasAll(files.ledger, [
      "Roastery rejection refund",
      "preserve accepted sibling sub-orders",
    ]),
  "Seller rejection must refund only the affected sub-order and must not cancel accepted siblings.",
);

gate(
  "settlement_ownership_contract",
  hasAll(files.ledger, [
    "rosta_hub_grinding",
    "rosta_route_shipping",
    "No Rosta Hub grinding or Rosta-route shipping amount can enter roastery payable balance",
    "refunded_parent_total <= captured_parent_total",
  ]) &&
    hasAll(files.domain, [
      "The roastery receives only the product allocation",
      "No Rosta Hub grinding or Rosta-route shipping amount can enter roastery payable balance",
    ]),
  "Every monetary line must have an immutable owner and refunds must remain bounded by capture.",
);

gate(
  "customer_tracking_contract",
  hasAll(files.domain, [
    "one card per roastery sub-order",
    "one card per Rosta service fulfilment",
    "All timeline events are append-only",
  ]) &&
    hasAll(files.states, [
      "Transition audit record",
      "parent aggregate recalculation",
    ]) &&
    hasAll(files.api, [
      "customer-visible events",
      "cancellation capability derived by backend",
    ]),
  "Customers must receive truthful child-level tracking backed by append-only events.",
);

gate(
  "r5a_is_contract_only",
  hasAll(files.domain, [
    "no production feature implementation is introduced in R5A",
    "ROSTA_R5A_DOMAIN_CONTRACT_COMPLETE",
  ]) &&
    hasAll(files.api, [
      "R5A commits contracts and a permanent audit only",
      "Those begin in R5B and later phases",
    ]),
  "R5A must stop at approved contracts and a permanent audit; implementation begins in R5B.",
);

const failed = gates.filter((item) => !item.passed);
const report = {
  generated_at: new Date().toISOString(),
  passed: failed.length === 0,
  checked_files: Object.values(paths),
  gates,
  failures: failed.map((item) => item.name),
  marker: failed.length === 0 ? "ROSTA_R5A_DOMAIN_CONTRACT_COMPLETE" : null,
};

await writeFile("r5a-domain-contract-audit.json", `${JSON.stringify(report, null, 2)}\n`);

if (failed.length > 0) {
  console.error("R5A domain contract audit failed:");
  for (const item of failed) console.error(`- ${item.name}: ${item.evidence}`);
  process.exit(1);
}

console.log(`R5A domain contract audit passed (${gates.length} gates).`);
console.log("ROSTA_R5A_DOMAIN_CONTRACT_COMPLETE");
