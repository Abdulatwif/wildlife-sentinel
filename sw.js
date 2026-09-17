/* ============================================================
   Wildlife Sentinel — Service Worker
   ------------------------------------------------------------
   - Pre-caches the app shell so pages open instantly offline
   - Runtime-caches same-origin GETs (stale-while-revalidate)
   - Serves offline.html when a navigation fails
   - Queues failed POSTs so they can be replayed later
   ============================================================ */

const VERSION  = 'v1.0.0';
const SHELL    = `ws-shell-${VERSION}`;
const RUNTIME  = `ws-runtime-${VERSION}`;
const PAGE_CACHE = `ws-pages-${VERSION}`;

// Files pre-cached on install
const SHELL_ASSETS = [
    './offline.html',
    './index.php',
    './login.php',
    './manifest.webmanifest'
];

// ---- Install: pre-cache the shell ----
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL).then((cache) => cache.addAll(SHELL_ASSETS).catch(() => null))
    );
    self.skipWaiting();
});

// ---- Activate: clean old caches ----
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => ![SHELL, RUNTIME, PAGE_CACHE].includes(k))
                .map((k) => caches.delete(k))
        ))
    );
    self.clients.claim();
});

// ---- Fetch handler ----
self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Only handle same-origin
    if (url.origin !== self.location.origin) return;

    // Never cache API mutations or AJAX endpoints that must be fresh
    const mustBeFresh = url.pathname.includes('/api/') ||
                        url.pathname.includes('/ajax') ||
                        url.search.includes('ajax=');

    // ---------- Non-GET (POST/PUT/DELETE): try, if fail → queue ----------
    if (req.method !== 'GET') {
        event.respondWith(
            fetch(req.clone()).catch(async () => {
                const body = await req.clone().text().catch(() => '');
                const headers = {};
                req.headers.forEach((v, k) => { headers[k] = v; });

                const queued = {
                    id: 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
                    url: req.url,
                    method: req.method,
                    headers: headers,
                    body: body,
                    ts: Date.now()
                };

                // Notify the page so it can show a toast
                const clients = await self.clients.matchAll({ type: 'window' });
                clients.forEach((c) => c.postMessage({ type: 'ws-offline-queued', item: queued }));

                // Response that looks like an accepted request
                return new Response(JSON.stringify({
                    success: false,
                    offline: true,
                    queued: queued.id,
                    message: 'Saved offline — will sync when online.'
                }), {
                    status: 202,
                    headers: { 'Content-Type': 'application/json' }
                });
            })
        );
        return;
    }

    // ---------- GET ----------
    // API calls: network-first
    if (mustBeFresh) {
        event.respondWith(
            fetch(req).catch(() => caches.match(req).then((r) => r || new Response(
                JSON.stringify({ success: false, offline: true }),
                { status: 503, headers: { 'Content-Type': 'application/json' } }
            )))
        );
        return;
    }

    // Navigation requests: network-first, fallback to cache, then offline page
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(PAGE_CACHE).then((c) => c.put(req, copy)).catch(() => null);
                    return res;
                })
                .catch(() => {
                    return caches.match(req).then((cached) => {
                        if (cached) return cached;
                        return caches.match('./offline.html');
                    });
                })
        );
        return;
    }

    // Static assets: stale-while-revalidate
    event.respondWith(
        caches.match(req).then((cached) => {
            const fetched = fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(RUNTIME).then((c) => c.put(req, copy)).catch(() => null);
                return res;
            }).catch(() => cached);
            return cached || fetched;
        })
    );
});

// ---- Messages from the page ----
self.addEventListener('message', (event) => {
    if (!event.data) return;
    if (event.data.type === 'ws-skip-waiting') self.skipWaiting();
    if (event.data.type === 'ws-clear-cache') {
        caches.keys().then((keys) => keys.forEach((k) => caches.delete(k)));
    }
});