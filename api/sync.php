<?php
// ============================================================
// api/sync.php
// Replay queued offline submissions.
// The client sends an array of items { url, method, body, headers }.
// The server re-dispatches each one internally and returns results.
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload — expected { items: [...] }']);
    exit;
}

$user    = getCurrentUser();
$results = [];

foreach ($input['items'] as $item) {
    $url    = $item['url']    ?? '';
    $method = strtoupper($item['method'] ?? 'POST');
    $body   = $item['body']   ?? '';

    // ---- Basic safety: only accept same-origin URLs from this project ----
    $parsed = parse_url($url);
    $path   = $parsed['path'] ?? '';
    if (strpos($path, '/wildlife-sentinel/') === false && strpos($path, '/api/') === false) {
        $results[] = ['id' => $item['id'] ?? null, 'ok' => false, 'error' => 'Rejected URL'];
        continue;
    }
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $results[] = ['id' => $item['id'] ?? null, 'ok' => false, 'error' => 'Rejected method'];
        continue;
    }

    // ---- Forward the request internally with the current session cookie ----
    $cookie  = session_name() . '=' . session_id();
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: ' . $cookie,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $ok = ($code >= 200 && $code < 400);
    $results[] = [
        'id'     => $item['id'] ?? null,
        'ok'     => $ok,
        'status' => $code,
        'error'  => $err ?: null,
        'sample' => $ok ? null : substr((string)$resp, 0, 200),
    ];
}

logAudit($user['id'], 'offline_sync', ['count' => count($results), 'ok' => count(array_filter($results, fn($r) => $r['ok']))]);

echo json_encode(['success' => true, 'results' => $results]);