/* ============================================================
   Wildlife Sentinel — Offline Manager
   ------------------------------------------------------------
   - Registers the service worker
   - Shows a top banner when offline / back online
   - Persists a local queue of failed POSTs in localStorage
   - Replays them automatically when the connection returns
   - Notifies the UI through CustomEvents ("ws:offline", "ws:online", "ws:queued", "ws:synced")
   ============================================================ */

(function () {
    'use strict';

    const QUEUE_KEY = 'ws_offline_queue';
    const LAST_PAGE_KEY = 'ws_last_page';

    // ---- Remember the last page the user viewed ----
    try {
        if (location.pathname.indexOf('offline.html') === -1) {
            sessionStorage.setItem(LAST_PAGE_KEY, location.href);
        }
    } catch (e) { /* ignore */ }

    // ---- Queue helpers ----
    function readQueue() {
        try {
            const raw = localStorage.getItem(QUEUE_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function writeQueue(arr) {
        try { localStorage.setItem(QUEUE_KEY, JSON.stringify(arr)); } catch (e) {}
        window.dispatchEvent(new CustomEvent('ws:queued', { detail: { count: arr.length } }));
    }
    function enqueue(item) {
        const q = readQueue();
        q.push(item);
        writeQueue(q);
    }
    function dequeue(id) {
        const q = readQueue().filter((i) => i.id !== id);
        writeQueue(q);
    }

    // ---- Track offline/online ----
    let offlineBanner = null;
    let pendingCounter = 0;

    function ensureBanner() {
        if (offlineBanner && document.body.contains(offlineBanner)) return offlineBanner;

        offlineBanner = document.createElement('div');
        offlineBanner.id = 'ws-offline-banner';
        offlineBanner.style.cssText = `
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 99999;
            background: linear-gradient(90deg, #92400e, #b45309);
            color: #fef3c7;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 16px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        `;
        offlineBanner.innerHTML = `
            <span>📡</span>
            <span id="ws-offline-text">You are offline — reports are being queued</span>
            <button id="ws-offline-retry"
                style="background:rgba(255,255,255,0.15);border:none;color:#fef3c7;
                       padding:4px 12px;border-radius:6px;font-family:inherit;
                       font-size:12px;font-weight:600;cursor:pointer;margin-left:8px;">
                Retry
            </button>
        `;
        document.body.appendChild(offlineBanner);

        // Push the page content down so the banner doesn't hide the header
        document.body.style.transition = 'padding-top 0.3s ease';
        document.body.style.paddingTop = '0px';

        offlineBanner.querySelector('#ws-offline-retry').addEventListener('click', () => {
            flushQueue();
        });

        return offlineBanner;
    }

    function showOfflineBanner() {
        const b = ensureBanner();
        b.style.transform = 'translateY(0)';
        document.body.style.paddingTop = '42px';
        const txt = b.querySelector('#ws-offline-text');
        const q = readQueue().length;
        txt.textContent = q > 0
            ? `You are offline — ${q} report${q === 1 ? '' : 's'} queued`
            : 'You are offline — reports are being queued';
    }

    function hideOfflineBanner() {
        if (offlineBanner) {
            offlineBanner.style.transform = 'translateY(-100%)';
            document.body.style.paddingTop = '0px';
        }
    }

    // ---- Network status ----
    window.addEventListener('online', () => {
        hideOfflineBanner();
        document.dispatchEvent(new CustomEvent('ws:online'));
        setTimeout(flushQueue, 800); // tiny delay to let the network stabilise
    });

    window.addEventListener('offline', () => {
        showOfflineBanner();
        document.dispatchEvent(new CustomEvent('ws:offline'));
    });

    // Show banner on load if already offline
    if (!navigator.onLine) setTimeout(showOfflineBanner, 300);

    // ---- Service worker registration ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(
                // Path-independent — works from any subfolder
                (function () {
                    const depth = (location.pathname.match(/\//g) || []).length - 1;
                    return '../'.repeat(Math.max(0, depth)) + 'sw.js';
                })(),
                { scope: './' }
            ).then((reg) => {
                // Ask SW to take over immediately
                if (reg.waiting) reg.waiting.postMessage({ type: 'ws-skip-waiting' });
                reg.addEventListener('updatefound', () => {
                    const nw = reg.installing;
                    if (!nw) return;
                    nw.addEventListener('statechange', () => {
                        if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                            nw.postMessage({ type: 'ws-skip-waiting' });
                        }
                    });
                });
            }).catch(() => { /* SW is optional */ });

            // Listen for messages from the SW (e.g. failed POST that got queued)
            navigator.serviceWorker.addEventListener('message', (event) => {
                const d = event.data || {};
                if (d.type === 'ws-offline-queued' && d.item) {
                    enqueue(d.item);
                    showOfflineBanner();
                    document.dispatchEvent(new CustomEvent('ws:queued', { detail: { item: d.item, count: readQueue().length } }));
                }
            });
        });
    }

    // ---- Wrap fetch so POSTs failing offline get auto-queued ----
    const originalFetch = window.fetch.bind(window);
    window.fetch = async function (input, init) {
        const url = (typeof input === 'string') ? input : (input && input.url);
        const method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();

        try {
            const res = await originalFetch(input, init);
            return res;
        } catch (err) {
            // Only queue same-origin POST/PUT/DELETE, never GET
            if (method !== 'GET' && url && url.indexOf(location.origin) === 0) {
                let bodyText = '';
                try {
                    if (init && typeof init.body === 'string') bodyText = init.body;
                    else if (init && init.body instanceof FormData) {
                        const obj = {};
                        init.body.forEach((v, k) => { obj[k] = v; });
                        bodyText = JSON.stringify(obj);
                    }
                } catch (e) {}

                const item = {
                    id: 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
                    url: url,
                    method: method,
                    headers: (init && init.headers) || {},
                    body: bodyText,
                    isFormData: !!(init && init.body instanceof FormData),
                    ts: Date.now()
                };
                enqueue(item);
                showOfflineBanner();

                return new Response(JSON.stringify({
                    success: false,
                    offline: true,
                    queued: item.id,
                    message: 'Saved offline — will sync when online.'
                }), {
                    status: 202,
                    headers: { 'Content-Type': 'application/json' }
                });
            }
            throw err;
        }
    };

    // ---- Flush queued requests when back online ----
    let flushing = false;
    async function flushQueue() {
        if (flushing) return;
        if (!navigator.onLine) return;
        const q = readQueue();
        if (q.length === 0) return;

        flushing = true;
        let sent = 0, failed = 0;
        const remaining = [];

        for (const item of q) {
            try {
                const opts = {
                    method: item.method,
                    headers: item.headers || {},
                    credentials: 'same-origin'
                };

                if (item.isFormData) {
                    // Rebuild FormData from a plain object
                    const fd = new FormData();
                    try {
                        const obj = JSON.parse(item.body);
                        Object.keys(obj).forEach((k) => fd.append(k, obj[k]));
                    } catch (e) {}
                    opts.body = fd;
                    // Let the browser set the multipart boundary
                    delete opts.headers['Content-Type'];
                } else {
                    opts.body = item.body || '';
                }

                const res = await originalFetch(item.url, opts);
                if (res.ok || res.status === 202 || (res.status >= 200 && res.status < 300)) {
                    sent++;
                } else {
                    failed++;
                    remaining.push(item);
                }
            } catch (e) {
                failed++;
                remaining.push(item);
            }
        }

        writeQueue(remaining);
        flushing = false;

        document.dispatchEvent(new CustomEvent('ws:synced', {
            detail: { sent, failed, remaining: remaining.length }
        }));

        // Show a success toast if any synced
        if (sent > 0 && failed === 0) {
            showToast(`✅ ${sent} queued report${sent === 1 ? '' : 's'} synced successfully.`);
        } else if (sent > 0 && failed > 0) {
            showToast(`⚠️ Synced ${sent}, ${failed} still pending.`, true);
        }
    }

    // ---- Tiny toast ----
    function showToast(text, isWarn) {
        const t = document.createElement('div');
        t.style.cssText = `
            position: fixed;
            bottom: 24px; right: 24px;
            z-index: 99999;
            padding: 12px 18px;
            background: ${isWarn ? '#92400e' : '#166534'};
            color: #fff;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            opacity: 0;
            transform: translateY(12px);
            transition: all 0.3s ease;
        `;
        t.textContent = text;
        document.body.appendChild(t);
        requestAnimationFrame(() => {
            t.style.opacity = '1';
            t.style.transform = 'translateY(0)';
        });
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateY(12px)';
            setTimeout(() => t.remove(), 400);
        }, 4000);
    }

    // ---- Public API ----
    window.WSOffline = {
        queue: readQueue,
        flush: flushQueue,
        clear: () => writeQueue([]),
        pending: () => readQueue().length
    };
})();