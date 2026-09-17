<?php
// ============================================================
// api/ai-ingest.php
// Fast ingest endpoint — accepts detection events and runs
// them through the AI engine.
// ------------------------------------------------------------
// Auth: API key in header `X-WS-Key` OR admin session.
// Body: JSON { zone_id, camera_id, detection_type, confidence, ... }
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ai_engine.php';

// ------------------------------------------------------------
// Auth
// ------------------------------------------------------------
$authed = false;
$apiKey = $_SERVER['HTTP_X_WS_KEY'] ?? '';
if ($apiKey !== '') {
    $stored = getSetting('ai_ingest_key', '');
    if ($stored !== '' && hash_equals($stored, $apiKey)) $authed = true;
}
if (!$authed) {
    // Fall back to admin session
    if (function_exists('isLoggedIn') && isLoggedIn() && hasRole('admin')) $authed = true;
}
if (!$authed) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

// ------------------------------------------------------------
// Parse
// ------------------------------------------------------------
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_json']);
    exit;
}

// ------------------------------------------------------------
// Run engine
// ------------------------------------------------------------
try {
    $engine = new AIEngine();
    $result = $engine->analyze($input);
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Throwable $e) {
    error_log('[WS-AI-INGEST] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'engine_error']);
}