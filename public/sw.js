const CACHE_VERSION = "v6";
const PRECACHE = `rosta-precache-${CACHE_VERSION}`;
const STATIC_CACHE = `rosta-static-${CACHE_VERSION}`;
const MEDIA_CACHE = `rosta-media-${CACHE_VERSION}`;
const OFFLINE_URL = "/offline.html";
const PRECACHE_URLS = [
  OFFLINE_URL,
  "/manifest.json",
  "/icon-192.png",
  "/icon-512.png",
  "/icon-512-maskable.png",
];
const PRIVATE_PREFIXES = [
  "/api",
  "/admin",
  "/auth",
  "/cart",
  "/checkout",
  "/forbidden",
  "/orders",
  "/panel",
  "/profile",
  "/hub",
];
const ACTIVE_CACHES = new Set([PRECACHE, STATIC_CACHE, MEDIA_CACHE]);
const MEDIA_LIMIT = 80;

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(PRECACHE).then((cache) => cache.addAll(PRECACHE_URLS)));
});

self.addEventListener("message", (event) => {
  if (event.data?.type === "ROSTA_SKIP_WAITING") self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith("rosta-") && !ACTIVE_CACHES.has(key))
            .map((key) => caches.delete(key)),
        ),
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
  if (!response?.ok || !["basic", "cors"].includes(response.type)) return false;
  const cacheControl = response.headers.get("cache-control") ?? "";
  return !/no-store|private/i.test(cacheControl);
}

async function trimCache(cacheName, maxEntries) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  await Promise.all(
    keys.slice(0, Math.max(0, keys.length - maxEntries)).map((key) => cache.delete(key)),
  );
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (mayCache(response)) await cache.put(request, response.clone());
  return response;
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const network = fetch(request)
    .then(async (response) => {
      if (mayCache(response)) {
        await cache.put(request, response.clone());
        await trimCache(cacheName, MEDIA_LIMIT);
      }
      return response;
    })
    .catch(() => undefined);
  return cached || (await network) || Response.error();
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin || isPrivateRequest(url)) return;

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request, { cache: "no-store" }).catch(async () => {
        return (await caches.match(OFFLINE_URL)) || Response.error();
      }),
    );
    return;
  }

  if (url.pathname === "/sw.js") return;

  if (request.destination === "script" || request.destination === "style") {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  if (request.destination === "font" || request.destination === "image") {
    event.respondWith(staleWhileRevalidate(request, MEDIA_CACHE));
  }
});
