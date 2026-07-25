import { writeFile } from "node:fs/promises";

const apiBase = process.env.R3B_API_BASE ?? "http://127.0.0.1:8000/api/v1";
const backendBase = process.env.R3B_BACKEND_BASE ?? "http://127.0.0.1:8000";
const frontendBase = process.env.R3B_FRONTEND_BASE ?? "http://127.0.0.1:3000";
const expectedContract = process.env.R3B_CONTRACT_VERSION ?? "2026-07-21-phase-6";
const reportPath = process.env.R3B_REPORT_PATH ?? "/tmp/rosta-r3b-runtime.json";

const checks = [];

function record(name, passed, evidence) {
  checks.push({ name, passed, evidence });
}

async function response(url, init = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10_000);
  try {
    return await fetch(url, {
      redirect: "manual",
      ...init,
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeout);
  }
}

async function json(url, init = {}) {
  const result = await response(url, init);
  const body = await result.json().catch(() => null);
  return { result, body };
}

async function html(path) {
  const result = await response(`${frontendBase}${path}`);
  const body = await result.text();
  return { result, body };
}

function htmlEvidence(result, body, expected) {
  return [
    `status=${result.status}`,
    `content_type=${result.headers.get("content-type") ?? "missing"}`,
    `body_length=${body.length}`,
    `expected_content=${body.includes(expected)}`,
  ].join("; ");
}

try {
  const live = await json(`${apiBase}/health/live`, {
    headers: { "X-Request-ID": "r3b-live-probe" },
  });
  record(
    "api_liveness",
    live.result.status === 200 && live.body?.data?.status === "ok",
    `status=${live.result.status}; canonical_status=${live.body?.data?.status ?? "missing"}`,
  );
  record(
    "contract_header",
    live.result.headers.get("x-rosta-contract-version") === expectedContract &&
      live.body?.data?.contract_version === expectedContract,
    "API header and body expose the frozen contract version.",
  );
  record(
    "request_id_round_trip",
    live.result.headers.get("x-request-id") === "r3b-live-probe",
    "Validated request IDs survive the API boundary.",
  );

  const ready = await json(`${apiBase}/health/ready`);
  record(
    "api_readiness",
    ready.result.status === 200 &&
      ready.body?.data?.status === "ready" &&
      ready.body?.data?.checks?.database === true &&
      ready.body?.data?.checks?.redis === true,
    `status=${ready.result.status}; canonical_status=${ready.body?.data?.status ?? "missing"}`,
  );

  const products = await json(`${apiBase}/products`);
  const productsText = JSON.stringify(products.body);
  record(
    "public_catalog",
    products.result.status === 200 &&
      productsText.includes("ethiopia-sidamo-whole-bean") &&
      [100, 250, 500].every((weight) => productsText.includes(`\"weight_grams\":${weight}`)),
    `status=${products.result.status}; live_slug=${productsText.includes("ethiopia-sidamo-whole-bean")}`,
  );

  const roasteries = await json(`${apiBase}/roasteries`);
  const roasteriesText = JSON.stringify(roasteries.body);
  record(
    "public_roasteries",
    roasteries.result.status === 200 && roasteriesText.includes("rosta-roastery"),
    `status=${roasteries.result.status}; live_slug=${roasteriesText.includes("rosta-roastery")}`,
  );

  const cors = await response(`${apiBase}/products`, {
    method: "OPTIONS",
    headers: {
      Origin: frontendBase,
      "Access-Control-Request-Method": "GET",
      "Access-Control-Request-Headers": "X-Request-ID",
    },
  });
  record(
    "credentialed_cors",
    cors.status >= 200 &&
      cors.status < 300 &&
      cors.headers.get("access-control-allow-origin") === frontendBase &&
      cors.headers.get("access-control-allow-credentials") === "true",
    `status=${cors.status}; origin=${cors.headers.get("access-control-allow-origin") ?? "missing"}; credentials=${cors.headers.get("access-control-allow-credentials") ?? "missing"}`,
  );

  const csrf = await response(`${backendBase}/sanctum/csrf-cookie`, {
    headers: {
      Accept: "application/json",
      Origin: frontendBase,
      Referer: `${frontendBase}/`,
    },
  });
  const setCookies = typeof csrf.headers.getSetCookie === "function"
    ? csrf.headers.getSetCookie()
    : [csrf.headers.get("set-cookie") ?? ""];
  record(
    "csrf_cookie_boundary",
    csrf.status === 204 && setCookies.some((cookie) => cookie.startsWith("XSRF-TOKEN=")),
    `status=${csrf.status}; xsrf_cookie=${setCookies.some((cookie) => cookie.startsWith("XSRF-TOKEN="))}`,
  );

  const unauthenticated = await json(`${apiBase}/me`, {
    headers: {
      Accept: "application/json",
      Origin: frontendBase,
      Referer: `${frontendBase}/`,
    },
  });
  record(
    "unauthenticated_session_boundary",
    unauthenticated.result.status === 401 &&
      unauthenticated.result.headers.get("location") === null &&
      unauthenticated.result.headers.get("access-control-allow-origin") === frontendBase,
    `status=${unauthenticated.result.status}; redirect=${unauthenticated.result.headers.get("location") ?? "none"}`,
  );

  const homepage = await html("/");
  record(
    "ssr_homepage",
    homepage.result.status === 200 &&
      homepage.result.headers.get("content-type")?.includes("text/html") === true &&
      homepage.body.includes("قهوه‌ای که زنده است"),
    htmlEvidence(homepage.result, homepage.body, "قهوه‌ای که زنده است"),
  );

  const productsPage = await html("/products");
  record(
    "ssr_catalog",
    productsPage.result.status === 200 && productsPage.body.includes("اتیوپی سیدامو دانه کامل"),
    htmlEvidence(productsPage.result, productsPage.body, "اتیوپی سیدامو دانه کامل"),
  );

  const productPage = await html("/products/ethiopia-sidamo-whole-bean");
  record(
    "ssr_product_detail",
    productPage.result.status === 200 && productPage.body.includes("اتیوپی سیدامو دانه کامل"),
    htmlEvidence(productPage.result, productPage.body, "اتیوپی سیدامو دانه کامل"),
  );

  const roasteriesPage = await html("/roasteries");
  record(
    "ssr_roasteries",
    roasteriesPage.result.status === 200 && roasteriesPage.body.includes("روستری رستا"),
    htmlEvidence(roasteriesPage.result, roasteriesPage.body, "روستری رستا"),
  );

  const robots = await response(`${frontendBase}/robots.txt`);
  const robotsBody = await robots.text();
  record(
    "indexing_disabled",
    robots.status === 200 && /disallow\s*:\s*\//i.test(robotsBody),
    `status=${robots.status}; disallow_all=${/disallow\s*:\s*\//i.test(robotsBody)}`,
  );

  const readyForAcceptance = checks.every((check) => check.passed);
  const report = {
    ready: readyForAcceptance,
    generated_at: new Date().toISOString(),
    contract_version: expectedContract,
    api_origin: new URL(apiBase).origin,
    frontend_origin: new URL(frontendBase).origin,
    checks,
    failures: checks.filter((check) => !check.passed).map((check) => check.name),
    marker: readyForAcceptance ? "ROSTA_R3B_INTEGRATED_RUNTIME_COMPLETE" : null,
  };
  await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");

  if (!readyForAcceptance) {
    console.error(JSON.stringify(report, null, 2));
    process.exit(1);
  }

  console.log(JSON.stringify(report, null, 2));
} catch (error) {
  const report = {
    ready: false,
    generated_at: new Date().toISOString(),
    contract_version: expectedContract,
    checks,
    error: error instanceof Error ? error.message : String(error),
    marker: null,
  };
  await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
  console.error(JSON.stringify(report, null, 2));
  process.exit(1);
}
