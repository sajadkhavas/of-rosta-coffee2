const CACHE_VERSION = "rosta-static-v5";
const OFFLINE_URL = "/offline.html";
const PRECACHE_URLS = [OFFLINE_URL, "/manifest.json", "/icon-192.png", "/icon-512.png"];
const PRIVATE_PREFIXES = [
  "/api",
  "/auth",
  "/cart",
  "/checkout",
  "/profile",
  "/orders",
  "/forbidden",
];
const CACHEABLE_DESTINATIONS = new Set(["font", "image"]);

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_URLS)));
});

self.addEventListener("message", (event) => {
  if (event.data?.type === "ROSTA_SKIP_WAITING") self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))),
      )
      .then(() => self.clients.claim()),
  );
});

function isPrivateRequest(url) {
  return PRIVATE_PREFIXES.some(
    (prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`),
  );
}

function mayCache(response) {
  if (!response?.ok || response.type !== "basic") return false;
  const cacheControl = response.headers.get("cache-control") ?? "";
  return !/no-store|private/i.test(cacheControl);
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin || isPrivateRequest(url)) return;

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request, { cache: "no-store" }).catch(async () => {
        const fallback = await caches.match(OFFLINE_URL);
        return fallback || Response.error();
      }),
    );
    return;
  }

  if (request.destination === "script" || request.destination === "style") {
    event.respondWith(
      fetch(request)
        .then(async (response) => {
          if (mayCache(response)) {
            const cache = await caches.open(CACHE_VERSION);
            await cache.put(request, response.clone());
          }
          return response;
        })
        .catch(async () => (await caches.match(request)) || Response.error()),
    );
    return;
  }

  if (CACHEABLE_DESTINATIONS.has(request.destination)) {
    event.respondWith(
      caches.open(CACHE_VERSION).then(async (cache) => {
        const cached = await cache.match(request);
        if (cached) return cached;
        const response = await fetch(request);
        if (mayCache(response)) await cache.put(request, response.clone());
        return response;
      }),
    );
  }
});
