// ============================================================
// supervisor/assets/js/supervisor-map.js
// Renders the supervisor live map:
//   - Zone boundary + 500m buffer polygon
//   - Scout markers (online / offline)
//   - Ranger markers (on-duty / off-duty) with live GPS
//   - Patrol route trails (last 2h)
//   - Incident markers (severity-colored)
//   - AI anomaly circles
//   - Live WebSocket updates
// ============================================================

(function () {
    let map = null;
    let layers = {
        zoneBoundary: null,
        bufferZone:   null,
        scouts:       null,
        rangers:      null,
        patrolRoutes: null,
        incidents:    null,
        aiAnomalies:  null,
    };
    let currentData = null;
    let ws = null;

    // ------------------------------------------------------------
    // INIT
    // ------------------------------------------------------------
    function initMap(containerId, data) {
        currentData = data;

        const center = (data.zone && data.zone.center_lat && data.zone.center_lng)
            ? [data.zone.center_lat, data.zone.center_lng]
            : [-13.0, 31.5];

        map = L.map(containerId, {
            center: center,
            zoom: 12,
            zoomControl: true,
            attributionControl: false,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        // Layer groups
        layers.zoneBoundary = L.layerGroup().addTo(map);
        layers.bufferZone   = L.layerGroup().addTo(map);
        layers.scouts       = L.layerGroup().addTo(map);
        layers.rangers      = L.layerGroup().addTo(map);
        layers.patrolRoutes = L.layerGroup().addTo(map);
        layers.incidents    = L.layerGroup().addTo(map);
        layers.aiAnomalies  = L.layerGroup().addTo(map);

        // Render everything
        renderZoneBoundary(data.zone);
        renderBufferZone(data.zone && data.zone.buffer_geojson);
        renderScouts(data.scouts || []);
        renderRangers(data.rangers || []);
        renderPatrolRoutes(data.rangers || []);
        renderIncidents(data.incidents || []);
        renderAIAnomalies(data.ai_anomalies || []);

        // Fit to zone bounds
        fitToZone(data.zone);

        // Map controls + legend
        addMapControls();
        addMapLegend();

        // WebSocket
        if (data.ws_url) {
            connectWebSocket(data.ws_url);
        }

        return map;
    }

    // ------------------------------------------------------------
    // FIT TO ZONE
    // ------------------------------------------------------------
    function fitToZone(zone) {
        if (!zone) return;
        try {
            let bounds = null;
            if (zone.boundary_geojson) {
                bounds = L.geoJSON(zone.boundary_geojson).getBounds();
            }
            if (zone.buffer_geojson) {
                const bufBounds = L.geoJSON(zone.buffer_geojson).getBounds();
                bounds = bounds ? bounds.extend(bufBounds) : bufBounds;
            }
            if (bounds) map.fitBounds(bounds, { padding: [30, 30] });
        } catch (e) {
            console.warn('fitToZone failed:', e);
        }
    }

    // ------------------------------------------------------------
    // ZONE BOUNDARY (green)
    // ------------------------------------------------------------
    function renderZoneBoundary(zone) {
        if (!zone || !zone.boundary_geojson) return;
        layers.zoneBoundary.clearLayers();

        L.geoJSON(zone.boundary_geojson, {
            style: {
                color: '#1B5E20',
                fillColor: '#1B5E20',
                fillOpacity: 0.08,
                weight: 3,
            },
            onEachFeature: (feature, layer) => {
                layer.bindPopup(`
                    <div style="font-family:sans-serif">
                        <strong style="color:#1B5E20">${zone.name || 'Zone'}</strong><br>
                        Type: ${(zone.park_type || 'other').toUpperCase()}<br>
                        Buffer: ${zone.buffer_radius || 500}m
                    </div>
                `);
            },
        }).addTo(layers.zoneBoundary);
    }

    // ------------------------------------------------------------
    // 500m BUFFER (orange dashed)
    // ------------------------------------------------------------
    function renderBufferZone(bufferGeojson) {
        if (!bufferGeojson) return;
        layers.bufferZone.clearLayers();

        L.geoJSON(bufferGeojson, {
            style: {
                color: '#FF6F00',
                fillColor: '#FF6F00',
                fillOpacity: 0.12,
                weight: 2,
                dashArray: '6,6',
            },
        }).addTo(layers.bufferZone);
    }

    // ------------------------------------------------------------
    // SCOUTS
    // ------------------------------------------------------------
    function renderScouts(scouts) {
        layers.scouts.clearLayers();

        scouts.forEach(scout => {
            const loc = scout.location || (scout.current_lat && scout.current_lng
                ? { lat: parseFloat(scout.current_lat), lng: parseFloat(scout.current_lng) }
                : null);
            if (!loc) return;

            const online = !!scout.is_online;
            const icon = L.divIcon({
                className: 'custom-scout-marker',
                html: `
                    <div style="position:relative">
                        <div style="
                            background:${online ? '#0277BD' : '#9E9E9E'};
                            width:22px;height:22px;border-radius:50%;
                            border:3px solid white;
                            box-shadow:0 2px 6px rgba(0,0,0,0.3);
                            display:flex;align-items:center;justify-content:center;
                            color:white;font-size:11px;font-weight:bold;
                        ">S</div>
                        ${online ? `
                            <div style="
                                position:absolute;top:-2px;right:-2px;
                                width:10px;height:10px;background:#4CAF50;
                                border-radius:50%;border:2px solid white;
                            "></div>
                        ` : ''}
                    </div>
                `,
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            });

            const marker = L.marker([loc.lat, loc.lng], { icon });

            marker.bindPopup(`
                <div style="font-family:sans-serif;min-width:200px">
                    <strong>👤 ${scout.full_name || 'Scout'}</strong><br>
                    Status: ${online ? '🟢 Online' : '⚪ Offline'}<br>
                    📞 ${scout.phone || 'N/A'}<br>
                    📍 ${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}
                    ${scout.active_incident_id ? `<br>🚨 Responding to #${scout.active_incident_id}` : ''}
                    <br><br>
                    <button onclick="window.SupervisorMap.centerOn(${loc.lat},${loc.lng})"
                        style="background:#0277BD;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">
                        📍 Center
                    </button>
                </div>
            `);

            marker.addTo(layers.scouts);
        });
    }

    // ------------------------------------------------------------
    // RANGERS
    // ------------------------------------------------------------
    function renderRangers(rangers) {
        layers.rangers.clearLayers();

        rangers.forEach(ranger => {
            const loc = ranger.location || (ranger.current_lat && ranger.current_lng
                ? { lat: parseFloat(ranger.current_lat), lng: parseFloat(ranger.current_lng) }
                : null);
            if (!loc) return;

            const onDuty = !!ranger.is_on_duty;
            const icon = L.divIcon({
                className: 'custom-ranger-marker',
                html: `
                    <div style="position:relative">
                        <div style="
                            background:${onDuty ? '#2E7D32' : '#9E9E9E'};
                            width:26px;height:26px;border-radius:50%;
                            border:3px solid white;
                            box-shadow:0 2px 6px rgba(0,0,0,0.4);
                            display:flex;align-items:center;justify-content:center;
                            color:white;font-size:12px;font-weight:bold;
                        ">R</div>
                        ${onDuty ? `
                            <div style="
                                position:absolute;top:-2px;right:-2px;
                                width:10px;height:10px;background:#4CAF50;
                                border-radius:50%;border:2px solid white;
                            "></div>
                        ` : ''}
                    </div>
                `,
                iconSize: [26, 26],
                iconAnchor: [13, 13],
            });

            const marker = L.marker([loc.lat, loc.lng], { icon });

            marker.bindPopup(`
                <div style="font-family:sans-serif;min-width:220px">
                    <strong>🛡️ ${ranger.full_name || 'Ranger'}</strong><br>
                    Badge: ${ranger.badge_number || 'N/A'}<br>
                    Status: ${onDuty ? '🟢 On Duty' : '⚪ Off Duty'}<br>
                    ⚡ Speed: ${(loc.speed || 0).toFixed(1)} m/s<br>
                    🧭 Heading: ${(loc.heading || 0).toFixed(0)}°<br>
                    📍 ${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}
                    ${ranger.current_incident_id ? `<br>🚨 Responding to #${ranger.current_incident_id}` : ''}
                    <br><br>
                    <button onclick="window.SupervisorMap.viewRangerRoute('${ranger.id}')"
                        style="background:#2E7D32;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;">
                        🛤️ View Route
                    </button>
                </div>
            `);

            marker.addTo(layers.rangers);
        });
    }

    // ------------------------------------------------------------
    // PATROL ROUTES
    // ------------------------------------------------------------
    function renderPatrolRoutes(rangers) {
        layers.patrolRoutes.clearLayers();

        rangers.forEach(ranger => {
            const route = ranger.patrol_route || [];
            if (route.length < 2) return;

            const points = route.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
            const onDuty = !!ranger.is_on_duty;

            L.polyline(points, {
                color: onDuty ? '#2E7D32' : '#9E9E9E',
                weight: 4,
                opacity: 0.7,
                dashArray: onDuty ? null : '5,5',
            }).bindPopup(`
                <strong>🛤️ Patrol Route</strong><br>
                Ranger: ${ranger.full_name}<br>
                Points: ${points.length}<br>
                Status: ${onDuty ? 'On Duty' : 'Off Duty'}
            `).addTo(layers.patrolRoutes);
        });
    }

    // ------------------------------------------------------------
    // INCIDENTS
    // ------------------------------------------------------------
    function renderIncidents(incidents) {
        layers.incidents.clearLayers();

        const colors = {
            low: '#4CAF50', medium: '#FF9800',
            high: '#FF5722', critical: '#C62828',
        };

        incidents.forEach(inc => {
            const loc = inc.location || (inc.location_lat && inc.location_lng
                ? { lat: parseFloat(inc.location_lat), lng: parseFloat(inc.location_lng) }
                : null);
            if (!loc) return;

            const icon = L.divIcon({
                className: 'custom-incident-marker',
                html: `
                    <div style="
                        background:${colors[inc.severity] || '#FF9800'};
                        width:28px;height:28px;border-radius:50%;
                        border:3px solid white;
                        box-shadow:0 2px 8px rgba(0,0,0,0.4);
                        display:flex;align-items:center;justify-content:center;
                        color:white;font-weight:bold;
                    ">!</div>
                `,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            });

            const marker = L.marker([loc.lat, loc.lng], { icon });

            marker.bindPopup(`
                <div style="font-family:sans-serif;min-width:220px">
                    <strong>🚨 Incident #${inc.id}</strong><br>
                    Type: ${inc.category}<br>
                    Severity: <span style="color:${colors[inc.severity]};font-weight:bold">${(inc.severity || '').toUpperCase()}</span><br>
                    Status: ${inc.status}<br>
                    Reporter: ${inc.reporter_name || 'N/A'}<br>
                    📞 ${inc.reporter_phone || 'N/A'}
                    ${inc.responder_name ? `<br>Responder: ${inc.responder_name}` : ''}
                </div>
            `);

            marker.addTo(layers.incidents);
        });
    }

    // ------------------------------------------------------------
    // AI ANOMALIES
    // ------------------------------------------------------------
    function renderAIAnomalies(anomalies) {
        layers.aiAnomalies.clearLayers();

        anomalies.forEach(a => {
            const loc = a.location || (a.location_lat && a.location_lng
                ? { lat: parseFloat(a.location_lat), lng: parseFloat(a.location_lng) }
                : null);
            if (!loc) return;

            L.circle([loc.lat, loc.lng], {
                radius: a.radius_meters || 200,
                color: '#9C27B0',
                fillColor: '#9C27B0',
                fillOpacity: 0.15,
                weight: 2,
                dashArray: '4,4',
            }).bindPopup(`
                <div style="font-family:sans-serif">
                    <strong>🤖 AI Anomaly</strong><br>
                    Type: ${(a.type || '').replace(/_/g, ' ')}<br>
                    Subject: ${a.subject_name || 'Unknown'} (${a.subject_role || 'N/A'})<br>
                    Confidence: ${Math.round((a.confidence || 0) * 100)}%<br>
                    Severity: ${a.severity}<br>
                    ${a.description || ''}
                </div>
            `).addTo(layers.aiAnomalies);
        });
    }

    // ------------------------------------------------------------
    // CONTROLS (toggle layers)
    // ------------------------------------------------------------
    function addMapControls() {
        const controls = L.control({ position: 'topleft' });

        controls.onAdd = function () {
            const div = L.DomUtil.create('div', 'map-controls');
            div.innerHTML = `
                <button class="map-toggle active" data-layer="bufferZone">🟠 Buffer</button>
                <button class="map-toggle active" data-layer="scouts">🔵 Scouts</button>
                <button class="map-toggle active" data-layer="rangers">🟢 Rangers</button>
                <button class="map-toggle active" data-layer="patrolRoutes">🛤️ Routes</button>
                <button class="map-toggle active" data-layer="aiAnomalies">🤖 AI</button>
                <button class="map-toggle active" data-layer="incidents">🚨 Incidents</button>
            `;

            L.DomEvent.disableClickPropagation(div);

            div.querySelectorAll('.map-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const name = btn.dataset.layer;
                    if (map.hasLayer(layers[name])) {
                        map.removeLayer(layers[name]);
                        btn.classList.remove('active');
                    } else {
                        map.addLayer(layers[name]);
                        btn.classList.add('active');
                    }
                });
            });

            return div;
        };

        controls.addTo(map);
    }

    // ------------------------------------------------------------
    // LEGEND
    // ------------------------------------------------------------
    function addMapLegend() {
        const legend = L.control({ position: 'bottomright' });

        legend.onAdd = function () {
            const div = L.DomUtil.create('div', 'map-legend');
            div.innerHTML = `
                <div class="legend-item"><span style="background:#1B5E20"></span>Zone Boundary</div>
                <div class="legend-item"><span style="background:#FF6F00;opacity:0.5"></span>500m Buffer</div>
                <div class="legend-item"><span style="background:#0277BD"></span>Scout (Online)</div>
                <div class="legend-item"><span style="background:#9E9E9E"></span>Scout (Offline)</div>
                <div class="legend-item"><span style="background:#2E7D32"></span>Ranger (On Duty)</div>
                <div class="legend-item"><span style="background:#C62828"></span>Incident</div>
                <div class="legend-item"><span style="background:#9C27B0"></span>AI Anomaly</div>
            `;
            return div;
        };

        legend.addTo(map);
    }

    // ------------------------------------------------------------
    // WEBSOCKET
    // ------------------------------------------------------------
    function connectWebSocket(wsUrl) {
        try {
            ws = io(wsUrl, {
                auth: {
                    userId: (window.USER && window.USER.id) || 0,
                    role:   (window.USER && window.USER.role) || 'guest',
                    zoneId: window.ACTIVE_ZONE_ID || 0,
                },
            });

            ws.on('connect', () => console.log('✅ WS connected'));

            ws.on('scout-location', (data) => {
                if (!currentData || !currentData.scouts) return;
                const s = currentData.scouts.find(x => x.id == data.scout_id);
                if (s) {
                    s.location = data.location;
                    s.is_online = true;
                    renderScouts(currentData.scouts);
                }
            });

            ws.on('ranger-location', (data) => {
                if (!currentData || !currentData.rangers) return;
                const r = currentData.rangers.find(x => x.id == data.ranger_id);
                if (r) {
                    r.location = data.location;
                    r.current_incident_id = data.incident_id || r.current_incident_id;
                    renderRangers(currentData.rangers);
                }
            });

            ws.on('ai-anomaly', (anomaly) => {
                if (!currentData) return;
                if (!currentData.ai_anomalies) currentData.ai_anomalies = [];
                currentData.ai_anomalies.unshift(anomaly);
                renderAIAnomalies(currentData.ai_anomalies);
            });
        } catch (e) {
            console.warn('WebSocket unavailable:', e);
        }
    }

    // ------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------
    function centerOn(lat, lng) {
        if (map && lat && lng) map.setView([lat, lng], 15);
    }

    function viewRangerRoute(rangerId) {
        if (!currentData || !currentData.rangers) return;
        const r = currentData.rangers.find(x => x.id == rangerId);
        if (!r || !r.patrol_route || r.patrol_route.length < 2) {
            alert('No patrol route available for this ranger');
            return;
        }
        const points = r.patrol_route.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
        map.fitBounds(L.latLngBounds(points), { padding: [50, 50] });
    }

    function refreshData(newData) {
        currentData = newData;
        renderScouts(newData.scouts || []);
        renderRangers(newData.rangers || []);
        renderPatrolRoutes(newData.rangers || []);
        renderIncidents(newData.incidents || []);
        renderAIAnomalies(newData.ai_anomalies || []);
    }

    // ------------------------------------------------------------
    // EXPORT
    // ------------------------------------------------------------
    window.SupervisorMap = {
        initMap,
        renderScouts,
        renderRangers,
        renderPatrolRoutes,
        renderIncidents,
        renderAIAnomalies,
        centerOn,
        viewRangerRoute,
        refreshData,
    };
})();