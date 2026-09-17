// ============================================================
// supervisor/assets/js/ai-tracker.js
// ------------------------------------------------------------
// Polls the AI anomalies endpoint and dispatches:
//   - document event "ai:anomalies"   { anomalies, stats, zone_id, ts }
//   - window.SupervisorMap.refreshData(...) if that helper exists
//
// Public API (unchanged):
//   window.AITracker.startAITracking(zoneId)
//   window.AITracker.stopAITracking()
//
// Extras:
//   window.AITracker.status()
//   window.AITracker.triggerOnce()
//   window.AITracker.setInterval(ms)
//
// Behaviour:
//   - 60s normal cadence
//   - 15s when at least one critical anomaly is active
//   - 120s backoff after 3+ consecutive failures
//   - pauses when tab is hidden, resumes on visibility
//   - uses ETag / If-None-Match to skip unchanged payloads
//   - aborts stale requests to avoid overlap
//   - optional beep on new critical anomalies
// ============================================================

(function () {
    'use strict';

    // ------------------------------------------------------------
    // CONFIG
    // ------------------------------------------------------------
    const CADENCE_NORMAL   = 60000;   // 60s
    const CADENCE_URGENT   = 15000;   // 15s when critical active
    const CADENCE_BACKOFF  = 120000;  // 120s after repeated failures
    const MAX_FAILURES     = 3;
    const ENDPOINT         = 'map.php'; // ?ajax=ai_scan&zone_id=...

    // ------------------------------------------------------------
    // STATE
    // ------------------------------------------------------------
    let currentZoneId   = null;
    let intervalId      = null;
    let inflight        = null;   // AbortController for current request
    let etag            = null;   // last ETag from server
    let lastPayload     = null;   // last successful response body
    let failures        = 0;
    let cadence         = CADENCE_NORMAL;
    let paused          = false;
    let lastAnomalyIds  = new Set(); // for detecting new critical ones

    // ------------------------------------------------------------
    // UTILITIES
    // ------------------------------------------------------------
    function log(...args) {
        if (window.AITrackerDebug) console.log('[AITracker]', ...args);
    }

    function warn(...args) {
        console.warn('[AITracker]', ...args);
    }

    function beep() {
        if (!window.SupervisorSoundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) { /* ignore */ }
    }

    function scheduleNext(delay) {
        if (intervalId) clearTimeout(intervalId);
        intervalId = setTimeout(() => {
            if (!paused) requestScan(currentZoneId);
        }, delay);
    }

    // ------------------------------------------------------------
    // REQUEST SCAN
    // ------------------------------------------------------------
    function requestScan(zoneId) {
        if (!zoneId) return;
        if (paused) return;

        // Abort any inflight request
        if (inflight) {
            try { inflight.abort(); } catch (e) {}
            inflight = null;
        }

        const controller = new AbortController();
        inflight = controller;

        const url = ENDPOINT + '?ajax=ai_scan&zone_id=' + encodeURIComponent(zoneId);
        const headers = { 'Accept': 'application/json' };
        if (etag) headers['If-None-Match'] = etag;

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
            headers,
        })
            .then(response => {
                // 304 = unchanged — short-circuit
                if (response.status === 304) {
                    return { __notModified: true };
                }
                // Capture ETag for next time
                const newEtag = response.headers.get('ETag');
                if (newEtag) etag = newEtag;

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                const ct = response.headers.get('Content-Type') || '';
                if (ct.indexOf('application/json') === -1) {
                    throw new Error('Unexpected content-type: ' + ct);
                }
                return response.json();
            })
            .then(data => {
                inflight = null;

                if (data && data.__notModified) {
                    log('304 Not Modified — nothing changed');
                    onSuccess(false);
                    return;
                }

                if (!data || !data.success) {
                    onFailure('payload has success=false');
                    return;
                }

                onSuccess(true, data);
            })
            .catch(err => {
                inflight = null;
                if (err && err.name === 'AbortError') return; // expected
                onFailure(err && err.message ? err.message : 'network error');
            });
    }

    // ------------------------------------------------------------
    // SUCCESS / FAILURE HANDLERS
    // ------------------------------------------------------------
    function onSuccess(fresh, data) {
        failures = 0;

        if (!fresh || !data) {
            // Nothing new — keep current cadence
            scheduleNext(cadence);
            return;
        }

        lastPayload = data;
        const anomalies = Array.isArray(data.anomalies) ? data.anomalies : [];

        // Detect new critical anomalies
        let hasCritical = false;
        let newCritical  = false;
        const seenNow = new Set();
        anomalies.forEach(a => {
            const id = a.id != null ? a.id : (a.type + '|' + a.location_lat + ',' + a.location_lng);
            seenNow.add(id);
            const level = (a.severity || a.threat_level || '').toLowerCase();
            if (level === 'critical' || level === 'high') hasCritical = true;
            if ((level === 'critical') && !lastAnomalyIds.has(id)) newCritical = true;
        });
        lastAnomalyIds = seenNow;

        // 1) Dispatch to any listeners
        try {
            document.dispatchEvent(new CustomEvent('ai:anomalies', {
                detail: {
                    zone_id: currentZoneId,
                    anomalies: anomalies,
                    stats: data.stats || null,
                    ts: Date.now(),
                    new_critical: newCritical,
                }
            }));
        } catch (e) { /* ignore */ }

        // 2) Bridge into the map helper if it exists
        if (window.SupervisorMap && typeof window.SupervisorMap.refreshData === 'function') {
            try {
                window.SupervisorMap.refreshData({
                    anomalies: anomalies,
                    stats: data.stats || null,
                });
            } catch (e) {
                warn('SupervisorMap.refreshData threw:', e);
            }
        }

        // 3) Beep on new critical
        if (newCritical) {
            log('New CRITICAL anomaly — beeping');
            beep();
        }

        // 4) Adjust cadence
        const targetCadence = hasCritical ? CADENCE_URGENT : CADENCE_NORMAL;
        if (targetCadence !== cadence) {
            log('Cadence →', targetCadence, 'ms');
            cadence = targetCadence;
        }

        scheduleNext(cadence);
    }

    function onFailure(reason) {
        failures++;
        warn('Scan failed (' + failures + '):', reason);

        if (failures >= MAX_FAILURES) {
            cadence = CADENCE_BACKOFF;
            log('Backing off to', cadence, 'ms');
        } else {
            cadence = CADENCE_NORMAL;
        }

        scheduleNext(cadence);
    }

    // ------------------------------------------------------------
    // VISIBILITY
    // ------------------------------------------------------------
    function handleVisibility() {
        if (document.hidden) {
            paused = true;
            log('Tab hidden — pausing');
            if (intervalId) { clearTimeout(intervalId); intervalId = null; }
        } else {
            paused = false;
            log('Tab visible — resuming');
            if (currentZoneId) requestScan(currentZoneId);
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibility);
    }

    // ------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------
    function startAITracking(zoneId) {
        if (!zoneId) {
            warn('startAITracking called without a zoneId');
            return;
        }
        currentZoneId = zoneId;
        cadence = CADENCE_NORMAL;
        failures = 0;
        etag = null;
        lastAnomalyIds = new Set();

        log('Starting AI tracking for zone', zoneId);
        requestScan(zoneId);
    }

    function stopAITracking() {
        log('Stopping AI tracking');
        if (intervalId) {
            clearTimeout(intervalId);
            intervalId = null;
        }
        if (inflight) {
            try { inflight.abort(); } catch (e) {}
            inflight = null;
        }
        currentZoneId = null;
    }

    function triggerOnce() {
        if (currentZoneId) requestScan(currentZoneId);
    }

    function status() {
        return {
            running: intervalId !== null || inflight !== null,
            zone_id: currentZoneId,
            cadence_ms: cadence,
            failures: failures,
            paused: paused,
            has_etag: !!etag,
            last_ts: lastPayload ? Date.now() : null,
            last_count: lastPayload && Array.isArray(lastPayload.anomalies)
                ? lastPayload.anomalies.length
                : 0,
        };
    }

    function setIntervalMs(ms) {
        const n = parseInt(ms, 10);
        if (!isNaN(n) && n >= 5000 && n <= 600000) {
            cadence = n;
            log('Cadence manually set to', n, 'ms');
        }
    }

    window.AITracker = {
        startAITracking,
        stopAITracking,
        triggerOnce,
        status,
        setInterval: setIntervalMs,
    };

    log('Loaded');
})();