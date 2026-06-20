/**
 * LifeLine PWA Service Worker
 * Strategy:
 *   - App shell (CSS/JS/vendor) → Cache First (long TTL, busted by ?v= fingerprint)
 *   - HTML pages              → Network First, fall back to /offline.php if offline
 *   - API endpoints           → Network Only (stale blood data is dangerous)
 */

const CACHE_NAME   = 'lifeline-shell-v1';
const OFFLINE_URL  = '/offline.php';

const SHELL_ASSETS = [
  '/assets/css/style.css',
  '/assets/js/app.js',
  '/assets/vendor/jquery-3.7.1.min.js',
  OFFLINE_URL,
];

// ─── Install: pre-cache the app shell ────────────────────────────────────────
self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(SHELL_ASSETS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

// ─── Activate: delete old caches ─────────────────────────────────────────────
self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys
          .filter(function (k) { return k !== CACHE_NAME; })
          .map(function (k)   { return caches.delete(k); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

// ─── Fetch ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', function (event) {
  var url = new URL(event.request.url);

  // Never intercept cross-origin or API/SSE requests.
  if (url.origin !== self.location.origin)            return;
  if (url.pathname.startsWith('/api/'))               return;
  if (url.pathname === '/api/stream.php')             return;

  // Static assets with a ?v= fingerprint → Cache First.
  if (url.searchParams.has('v') || isShellAsset(url.pathname)) {
    event.respondWith(cacheFirst(event.request));
    return;
  }

  // HTML navigation → Network First, offline fallback.
  if (event.request.mode === 'navigate') {
    event.respondWith(networkFirstWithOfflineFallback(event.request));
    return;
  }

  // Everything else (images without ?v=) → Network First, no fallback.
  event.respondWith(networkFirst(event.request));
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function isShellAsset(pathname) {
  return SHELL_ASSETS.some(function (a) { return pathname === a; });
}

function cacheFirst(request) {
  return caches.open(CACHE_NAME).then(function (cache) {
    return cache.match(request).then(function (cached) {
      if (cached) return cached;
      return fetch(request).then(function (response) {
        if (response.ok) cache.put(request, response.clone());
        return response;
      });
    });
  });
}

function networkFirst(request) {
  return fetch(request).catch(function () {
    return caches.match(request);
  });
}

function networkFirstWithOfflineFallback(request) {
  return fetch(request).catch(function () {
    return caches.match(request).then(function (cached) {
      return cached || caches.match(OFFLINE_URL);
    });
  });
}
