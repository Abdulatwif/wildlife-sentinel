// supervisor/assets/js/supervisor-map.js
// Handles: zone boundary, 500m buffer, scouts, rangers, patrol routes, incidents, AI anomalies

let map = null;
let layers = {
    zoneBoundary: null,
    bufferZone: null,
    scouts: null,
    rangers: null,
    patrolRoutes: null,
    incidents: null,
    aiAnomalies: null
};
let currentData = null;
let ws = null;

// ============================================================
// INITIALIZE MAP
// ============================================================
function initMap(containerId, data) {
    currentData = data;

    const center = data.zone.center_lat && data.zone.center_lng
        ? [data.zone.center_lat, data.zone.center_lng]
        : [-1.286389, 36.817223];

    map = L.map(containerId, {
        center: center,
        zoom: 13,
        zoomControl: true,
        attributionControl: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    // Layer groups for toggling
    layers.zoneBoundary = L.layerGroup().addTo(map);
    layers.bufferZone   = L.layerGroup().addTo(map);
    layers.scouts       = L.layerGroup().addTo(map);
    layers.rangers      = L.layerGroup().addTo(map);
    layers.patrolRoutes = L.layerGroup().addTo(map);
    layers.incidents    = L.layerGroup().addTo(map);
    layers.aiAnomalies  = L.layerGroup().addTo(map);

    // Render all layers
    renderZoneBoundary(data.zone);
    renderBufferZone(data.zone.buffer_geojson);
    renderScouts(data.scouts);
    renderRangers(data.rangers);
    renderPatrolRoutes(data.rangers);
    renderIncidents(data.incidents);
    renderAIAnomalies(data.ai_anomalies);

    // Fit map to zone boundary
    if (data.zone.boundary_geojson) {
        const bounds = L.geoJSON(data.zone.boundary_geojson).getBounds();
        if (data.zone.buffer_geojson) {
            bounds.extend(L.geoJSON(data.zone.buffer_geojson).getBounds());
        }
        map.fitBounds(bounds, { padding: [30, 30] });
    }

    // Connect WebSocket for real-time updates
    connectWebSocket(data.ws_url);

    // Add legend + controls
    addMapControls();
    addMapLegend();

    return map;
}

// ============================================================
// RENDER: ZONE BOUNDARY (green)
// ============================================================
function renderZoneBoundary(zone) {
    if (!zone.boundary_geojson) return;
    layers.zoneBoundary.clearLayers();

    L.geoJSON(zone.boundary_geojson, {
        style: {
            color: '#1B5E20',
            fillColor: '#1B5E20',
            fillOpacity: 0.08,
            weight: 3
        },
        onEachFeature: (feature, layer) => {
            layer.bindPopup(`
                <div style="font-family:sans-serif">
                    <strong style="color:#1B5E20">${zone.name}</strong><br>
                    Area: ${zone.area_km2 || 'N/A'} km²<br>
                    Buffer: 500m monitoring zone
                </div>
            `);
        }
    }).addTo(layers.zoneBoundary);
}

// ============================================================
// RENDER: 500m BUFFER ZONE (orange dashed)
// ============================================================
function renderBufferZone(bufferGeojson) {
    if (!bufferGeojson) return;
    layers.bufferZone.clearLayers();

    L.geoJSON(bufferGeojson, {
        style: {
            color: '#FF6F00',
            fillColor: '#FF6F00',
            fillOpacity: 0.12,
            weight: 2,
            dashArray: '6,6'
        }
    }).addTo(layers.bufferZone);
}

// ============================================================
// RENDER: SCOUTS (blue markers, green dot if online)
// ============================================================
function renderScouts(scouts) {
    layers.scouts.clearLayers();

    scouts.forEach(scout => {
        if (!scout.location) return;

        const icon = L.divIcon({
            className: 'custom-scout-marker',
            html: `
                <div style="position:relative">
                    <div style="
                        background:${scout.is_online ? '#0277BD' : '#9E9E9E'};
                        width:22px;height:22px;border-radius:50%;
                        border:3px solid white;
                        box-shadow:0 2px 6px rgba(0,0,0,0.3);
                        display:flex;align-items:center;justify-content:center;
                        color:white;font-size:11px;font-weight:bold;
                    ">S</div>
                    ${scout.is_online ? `
                        <div style="
                            position:absolute;top:-2px;right:-2px;
                            width:10px;height:10px;background:#4CAF50;
                            border-radius:50%;border:2px solid white;
                            animation:pulse 2s infinite;
                        "></div>
                    ` : ''}
                </div>
            `,
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        const marker = L.marker([scout.location.lat, scout.location.lng], { icon });

        const lastSeen = scout.last_seen
            ? new Date(scout.last_seen).toLocaleTimeString()
            : 'Unknown';

        marker.bindPopup(`
            <div style="font-family:sans-serif;min-width:200px">
                <strong>👤 ${scout.full_name}</strong><br>
                Status: ${scout.is_online ? '🟢 Online' : '⚪ Offline'}<br>
                Last seen: ${lastSeen}<br>
                📞 ${scout.phone || 'N/A'}<br>
                📍 ${scout.location.lat.toFixed(5)}, ${scout.location.lng.toFixed(5)}
                ${scout.active_incident_id ? `<br>🚨 Responding to incident #${scout.active_incident_id}` : ''}
                <br><br>
                <button onclick="messageUser('${scout.id}')" style="
                    background:#1B5E20;color:white;border:none;
                    padding:5px 10px;border-radius:4px;cursor:pointer;
                ">💬 Message</button>
                <button onclick="centerOn(${scout.location.lat},${scout.location.lng})" style="
                    background:#0277BD;color:white;border:none;
                    padding:5px 10px;border-radius:4px;cursor:pointer;margin-left:5px;
                ">📍 Center</button>
            </div>
        `);

        marker.addTo(layers.scouts);
    });
}

// ============================================================
// RENDER: RANGERS (green markers, pulsing if on duty)
// ============================================================
function renderRangers(rangers) {
    layers.rangers.clearLayers();

    rangers.forEach(ranger => {
        if (!ranger.location) return;

        const icon = L.divIcon({
            className: 'custom-ranger-marker',
            html: `
                <div style="position:relative">
                    <div style="
                        background:${ranger.is_on_duty ? '#2E7D32' : '#9E9E9E'};
                        width:26px;height:26px;border-radius:50%;
                        border:3px solid white;
                        box-shadow:0 2px 6px rgba(0,0,0,0.4);
                        display:flex;align-items:center;justify-content:center;
                        color:white;font-size:12px;font-weight:bold;
                    ">R</div>
                    ${ranger.is_on_duty ? `
                        <div style="
                            position:absolute;top:-2px;right:-2px;
                            width:10px;height:10px;background:#4CAF50;
                            border-radius:50%;border:2px solid white;
                            animation:pulse 2s infinite;
                        "></div>
                    ` : ''}
                </div>
            `,
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });

        const marker = L.marker([ranger.location.lat, ranger.location.lng], { icon });

        marker.bindPopup(`
            <div style="font-family:sans-serif;min-width:220px">
                <strong>🛡️ ${ranger.full_name}</strong><br>
                Badge: ${ranger.badge_number || 'N/A'}<br>
                Status: ${ranger.is_on_duty ? '🟢 On Duty' : '⚪ Off Duty'}<br>
                ⚡ Speed: ${(ranger.location.speed || 0).toFixed(1)} m/s<br>
                🧭 Heading: ${(ranger.location.heading || 0).toFixed(0)}°<br>
                📍 ${ranger.location.lat.toFixed(5)}, ${ranger.location.lng.toFixed(5)}<br>
                Last update: ${new Date(ranger.location_updated).toLocaleTimeString()}
                ${ranger.current_incident_id ? `<br>🚨 Responding to #${ranger.current_incident_id}` : ''}
                <br><br>
                <button onclick="messageUser('${ranger.id}')" style="
                    background:#1B5E20;color:white;border:none;
                    padding:5px 10px;border-radius:4px;cursor:pointer;
                ">💬 Message</button>
                <button onclick="viewRangerRoute('${ranger.id}')" style="
                    background:#0277BD;color:white;border:none;
                    padding:5px 10px;border-radius:4px;cursor:pointer;margin-left:5px;
                ">🛤️ Route</button>
            </div>
        `);

        marker.addTo(layers.rangers);
    });
}

// ============================================================
// RENDER: PATROL ROUTES (trail polylines)
// ============================================================
function renderPatrolRoutes(rangers) {
    layers.patrolRoutes.clearLayers();

    rangers.forEach(ranger => {
        if (!ranger.patrol_route || ranger.patrol_route.length < 2) return;

        const points = ranger.patrol_route.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);

        L.polyline(points, {
            color: ranger.is_on_duty ? '#2E7D32' : '#9E9E9E',
            weight: 4,
            opacity: 0.7,
            dashArray: ranger.is_on_duty ? null : '5,5'
        }).bindPopup(`
            <strong>🛤️ Patrol Route</strong><br>
            Ranger: ${ranger.full_name}<br>
            Points: ${points.length}<br>
            Status: ${ranger.is_on_duty ? 'On Duty' : 'Off Duty'}
        `).addTo(layers.patrolRoutes);
    });
}

// ============================================================
// RENDER: INCIDENTS (severity-colored)
// ============================================================
function renderIncidents(incidents) {
    layers.incidents.clearLayers();

    const colors = {
        low: '#4CAF50',
        medium: '#FF9800',
        high: '#FF5722',
        critical: '#C62828'
    };

    incidents.forEach(inc => {
        const icon = L.divIcon({
            className: 'custom-incident-marker',
            html: `
                <div style="
                    background:${colors[inc.severity]};
                    width:28px;height:28px;border-radius:50%;
                    border:3px solid white;
                    box-shadow:0 2px 8px rgba(0,0,0,0.4);
                    display:flex;align-items:center;justify-content:center;
                    color:white;font-weight:bold;
                ">!</div>
            `,
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });

        const marker = L.marker([inc.location.lat, inc.location.lng], { icon });

        marker.bindPopup(`
            <div style="font-family:sans-serif;min-width:220px">
                <strong>🚨 Incident #${inc.id}</strong><br>
                Type: ${inc.category}<br>
                Severity: <span style="color:${colors[inc.severity]};font-weight:bold">
                    ${inc.severity.toUpperCase()}
                </span><br>
                Status: ${inc.status}<br>
                Reporter: ${inc.reporter_name} (${inc.reporter_role})<br>
                📞 ${inc.reporter_phone || 'N/A'}<br>
                Reported: ${new Date(inc.reported_at).toLocaleString()}
                ${inc.responder_name ? `<br>Responder: ${inc.responder_name}` : ''}
                <br><br>
                <button onclick="viewIncident('${inc.id}')" style="
                    background:#C62828;color:white;border:none;
                    padding:5px 10px;border-radius:4px;cursor:pointer;
                ">View Details</button>
            </div>
        `);

        marker.addTo(layers.incidents);
    });
}

// ============================================================
// RENDER: AI ANOMALIES (purple circles)
// ============================================================
function renderAIAnomalies(anomalies) {
    layers.aiAnomalies.clearLayers();

    anomalies.forEach(a => {
        L.circle([a.location.lat, a.location.lng], {
            radius: a.radius_meters || 200,
            color: '#9C27B0',
            fillColor: '#9C27B0',
            fillOpacity: 0.15,
            weight: 2,
            dashArray: '4,4'
        }).bindPopup(`
            <div style="font-family:sans-serif">
                <strong>🤖 AI Anomaly</strong><br>
                Type: ${a.type}<br>
                Subject: ${a.subject_name || 'Unknown'} (${a.subject_role || 'N/A'})<br>
                Confidence: ${(a.confidence * 100).toFixed(0)}%<br>
                Severity: ${a.severity}<br>
                Detected: ${new Date(a.detected_at).toLocaleString()}<br>
                ${a.description}
            </div>
        `).addTo(layers.aiAnomalies);
    });
}

// ============================================================
// MAP CONTROLS (toggle layers)
// ============================================================
function addMapControls() {
    const controls = L.control({ position: 'topleft' });

    controls.onAdd = function() {
        const div = L.DomUtil.create('div', 'map-controls');
        div.innerHTML = `
            <button class="map-toggle active" data-layer="bufferZone">🟠 500m Buffer</button>
            <button class="map-toggle active" data-layer="scouts">🔵 Scouts</button>
            <button class="map-toggle active" data-layer="rangers">🟢 Rangers</button>
            <button class="map-toggle active" data-layer="patrolRoutes">🛤️ Routes</button>
            <button class="map-toggle active" data-layer="aiAnomalies">🤖 AI</button>
            <button class="map-toggle" data-layer="incidents">🚨 Incidents</button>
        `;

        L.DomEvent.disableClickPropagation(div);

        div.querySelectorAll('.map-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const layerName = btn.dataset.layer;
                if (map.hasLayer(layers[layerName])) {
                    map.removeLayer(layers[layerName]);
                    btn.classList.remove('active');
                } else {
                    map.addLayer(layers[layerName]);
                    btn.classList.add('active');
                }
            });
        });

        return div;
    };

    controls.addTo(map);
}

// ============================================================
// MAP LEGEND
// ============================================================
function addMapLegend() {
    const legend = L.control({ position: 'bottomright' });

    legend.onAdd = function() {
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

// ============================================================
// WEBSOCKET: REAL-TIME UPDATES
// ============================================================
function connectWebSocket(wsUrl) {
    if (!wsUrl) return;

    ws = io(wsUrl, {
        auth: { userId: window.USER.id, role: window.USER.role, zoneId: window.ACTIVE_ZONE_ID }
    });

    ws.on('connect', () => {
        console.log('✅ WebSocket connected');
        updateConnectionStatus(true);
    });

    ws.on('disconnect', () => {
        console.log('❌ WebSocket disconnected');
        updateConnectionStatus(false);
    });

    // ---- Scout location update ----
    ws.on('scout-location', (data) => {
        updateScoutOnMap(data.scout_id, data.location);
    });

    ws.on('scout-online', (data) => {
        addNotification({
            title: '👤 Scout Online',
            body: `${data.scout_name} is now online`,
            type: 'scout'
        });
    });

    ws.on('scout-offline', (data) => {
        updateScoutStatus(data.scout_id, false);
    });

    // ---- Ranger location update ----
    ws.on('ranger-location', (data) => {
        updateRangerOnMap(data.ranger_id, data.location, data.incident_id);
    });

    // ---- AI anomaly ----
    ws.on('ai-anomaly', (anomaly) => {
        addAIAnomaly(anomaly);
        addNotification({
            title: '🤖 AI Anomaly Detected',
            body: `${anomaly.type} - ${anomaly.description}`,
            type: 'ai',
            urgent: anomaly.severity === 'high' || anomaly.severity === 'critical'
        });
    });

    // ---- Manpower request ----
    ws.on('manpower-request', (request) => {
        addNotification({
            title: '🆘 Manpower Request',
            body: `${request.sender_name} needs backup`,
            type: 'manpower',
            urgent: true
        });
        incrementBadge('badge-manpower');
    });
}

// ============================================================
// LIVE UPDATES (called by WebSocket handlers)
// ============================================================
function updateScoutOnMap(scoutId, location) {
    if (!currentData) return;
    const scout = currentData.scouts.find(s => s.id == scoutId);
    if (scout) {
        scout.location = location;
        scout.is_online = true;
        renderScouts(currentData.scouts);
    }
}

function updateScoutStatus(scoutId, isOnline) {
    if (!currentData) return;
    const scout = currentData.scouts.find(s => s.id == scoutId);
    if (scout) {
        scout.is_online = isOnline;
        renderScouts(currentData.scouts);
    }
}

function updateRangerOnMap(rangerId, location, incidentId) {
    if (!currentData) return;
    const ranger = currentData.rangers.find(r => r.id == rangerId);
    if (ranger) {
        ranger.location = location;
        ranger.current_incident_id = incidentId;
        // Append to patrol route
        ranger.patrol_route = ranger.patrol_route || [];
        ranger.patrol_route.push({
            lat: location.lat,
            lng: location.lng,
            heading: location.heading,
            speed: location.speed,
            timestamp: new Date().toISOString()
        });
        renderRangers(currentData.rangers);
        renderPatrolRoutes(currentData.rangers);
    }
}

function addAIAnomaly(anomaly) {
    if (!currentData) return;
    currentData.ai_anomalies.unshift(anomaly);
    renderAIAnomalies(currentData.ai_anomalies);
}

// ============================================================
// HELPERS
// ============================================================
function centerOn(lat, lng) {
    if (map) map.setView([lat, lng], 16);
}

function viewRangerRoute(rangerId) {
    const ranger = currentData.rangers.find(r => r.id == rangerId);
    if (!ranger || !ranger.patrol_route || ranger.patrol_route.length < 2) {
        alert('No patrol route available for this ranger');
        return;
    }
    const points = ranger.patrol_route.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);
    const bounds = L.latLngBounds(points);
    map.fitBounds(bounds, { padding: [50, 50] });
}

function messageUser(userId) {
    // Open message modal — handled in dashboard.js
    if (window.openMessageModal) {
        window.openMessageModal(userId);
    }
}

function viewIncident(incidentId) {
    if (window.openIncidentModal) {
        window.openIncidentModal(incidentId);
    }
}

function updateConnectionStatus(isOnline) {
    const el = document.getElementById('conn-status');
    if (!el) return;
    el.innerHTML = isOnline
        ? '<i class="fas fa-wifi online"></i><span>Online</span>'
        : '<i class="fas fa-wifi offline"></i><span>Offline</span>';
}

// Export for dashboard.js
window.SupervisorMap = {
    initMap,
    renderScouts,
    renderRangers,
    renderPatrolRoutes,
    renderIncidents,
    renderAIAnomalies,
    centerOn,
    viewRangerRoute
};