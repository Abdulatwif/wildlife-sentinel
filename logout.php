<?php
// ============================================================
// logout.php — Wildlife Sentinel
// ------------------------------------------------------------
// Universal logout for every role: admin | zone_supervisor |
// ranger | scout | tourism.
//
// Flow:
//   1. GET  logout.php              → show confirmation page
//   2. POST logout.php confirm=1    → run the logout
//   3. GET  logout.php?confirm=1    → also runs the logout
//                                     (for legacy links)
//
// Safe behaviour (only after confirmation):
//   - Marks the user offline in the DB (best-effort)
//   - Writes an audit log entry (best-effort)
//   - Clears every session variable
//   - Deletes the session cookie
//   - Destroys the session on the server
//   - Redirects to login.php?logout=success
// ============================================================

require_once __DIR__ . '/includes/functions.php';

// ------------------------------------------------------------
// Has the user confirmed?
//   - Accept POST (form button) with confirm=1
//   - Accept GET  (link) with ?confirm=1
// ------------------------------------------------------------
$confirmed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === '1') {
    $confirmed = true;
} elseif (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    $confirmed = true;
}

// ------------------------------------------------------------
// STEP 1 — Not yet confirmed → show the confirmation page
// ------------------------------------------------------------
if (!$confirmed) {

    // Capture the URL to return to if the user clicks "Cancel".
    // Falls back to the role's dashboard, then to login.php.
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    // Only trust same-site referers (basic safety)
    if ($referer !== '' && strpos($referer, $_SERVER['HTTP_HOST'] ?? '') === false) {
        $referer = '';
    }

    $userName = $_SESSION['user_name'] ?? 'Guest';
    $userRole = $_SESSION['user_role'] ?? '';

    $roleLabels = [
        'admin'            => 'Administrator',
        'zone_supervisor'  => 'Zone Supervisor',
        'ranger'           => 'Ranger',
        'scout'            => 'Scout',
        'tourism'          => 'Tourism',
    ];
    $roleLabel = $roleLabels[$userRole] ?? ucfirst($userRole);

    // Where to send the user if they cancel
    $cancelUrl = $referer !== '' ? $referer : 'login.php';

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#0d3b22">
        <title>Confirm Logout — Wildlife Sentinel</title>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                min-height: 100vh; min-height: 100dvh;
                background: linear-gradient(135deg, #0a1a0f 0%, #1a5c3a 50%, #0d3b22 100%);
                display: flex; align-items: center; justify-content: center;
                padding: 20px; position: relative; overflow: hidden;
            }

            body::before {
                content: '';
                position: absolute; inset: 0;
                background:
                    radial-gradient(circle at 20% 50%, rgba(74,222,128,0.06) 0%, transparent 50%),
                    radial-gradient(circle at 80% 50%, rgba(74,222,128,0.06) 0%, transparent 50%);
                z-index: 0;
            }

            .card {
                position: relative; z-index: 1;
                background: rgba(255,255,255,0.06);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 22px;
                padding: 34px 30px 28px;
                border: 1px solid rgba(255,255,255,0.1);
                box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                max-width: 420px;
                width: 100%;
                text-align: center;
                animation: slideUp 0.45s ease;
            }

            @keyframes slideUp {
                from { opacity: 0; transform: translateY(24px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            .icon-wrap {
                width: 72px; height: 72px;
                margin: 0 auto 18px;
                border-radius: 20px;
                display: flex; align-items: center; justify-content: center;
                font-size: 34px;
                background: linear-gradient(135deg, rgba(250,204,21,0.18), rgba(220,53,69,0.12));
                border: 2px solid rgba(250,204,21,0.3);
                box-shadow: 0 8px 30px rgba(250,204,21,0.15);
            }

            h1 {
                font-family: 'Playfair Display', serif;
                font-size: 22px;
                font-weight: 800;
                color: #fff;
                letter-spacing: -0.3px;
                margin-bottom: 8px;
            }

            .subtitle {
                color: rgba(255,255,255,0.65);
                font-size: 13.5px;
                line-height: 1.6;
                margin-bottom: 6px;
            }

            .user-chip {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 14px;
                border-radius: 50px;
                background: rgba(74,222,128,0.12);
                border: 1px solid rgba(74,222,128,0.25);
                color: #4ade80;
                font-size: 12px;
                font-weight: 600;
                margin: 12px 0 20px;
            }

            .user-chip .role {
                color: rgba(255,255,255,0.55);
                font-weight: 500;
            }

            .actions {
                display: flex;
                gap: 10px;
                flex-direction: column;
            }

            .btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 13px 18px;
                border-radius: 12px;
                font-size: 14.5px;
                font-weight: 600;
                cursor: pointer;
                border: none;
                text-decoration: none;
                font-family: inherit;
                transition: all 0.22s ease;
                min-height: 48px;
            }

            .btn-danger {
                background: linear-gradient(135deg, #dc3545, #b02a37);
                color: #fff;
                box-shadow: 0 6px 24px rgba(220,53,69,0.35);
            }
            .btn-danger:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 32px rgba(220,53,69,0.5);
            }

            .btn-ghost {
                background: rgba(255,255,255,0.06);
                color: rgba(255,255,255,0.85);
                border: 1px solid rgba(255,255,255,0.14);
            }
            .btn-ghost:hover {
                background: rgba(255,255,255,0.12);
                border-color: rgba(255,255,255,0.25);
            }

            .hint {
                margin-top: 16px;
                font-size: 11px;
                color: rgba(255,255,255,0.35);
                line-height: 1.5;
            }

            @media (max-width: 480px) {
                .card { padding: 26px 22px 22px; border-radius: 18px; }
                h1 { font-size: 19px; }
                .icon-wrap { width: 62px; height: 62px; font-size: 28px; }
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrap">🚪</div>
            <h1>Confirm Logout</h1>
            <p class="subtitle">
                You're about to end your session. Any unsaved changes will be lost.
            </p>

            <?php if ($userName && $userName !== 'Guest'): ?>
                <div class="user-chip">
                    👤 <?= htmlspecialchars($userName) ?>
                    <?php if ($roleLabel): ?>
                        <span class="role">· <?= htmlspecialchars($roleLabel) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="actions">
                <!-- YES → runs the logout -->
                <form method="POST" action="logout.php" style="margin:0;">
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="btn btn-danger" style="width:100%;">
                        🚪 Yes, log me out
                    </button>
                </form>

                <!-- NO → goes back -->
                <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-ghost">
                    ← Cancel, stay signed in
                </a>
            </div>

            <p class="hint">
                🔒 For your security, always log out on shared devices.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ============================================================
// STEP 2 — User confirmed → run the actual logout
// ============================================================

// ------------------------------------------------------------
// Capture identity BEFORE destroying the session (for audit)
// ------------------------------------------------------------
$userId   = $_SESSION['user_id']   ?? null;
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// ------------------------------------------------------------
// Mark user offline + write audit log (best-effort)
// ------------------------------------------------------------
if ($userId) {
    try {
        $pdo = getDB();

        // Mark the user as offline
        $pdo->prepare("UPDATE users SET is_online = 0, last_online = NOW() WHERE id = ?")
            ->execute([$userId]);

        // Audit the logout
        if (function_exists('logAudit')) {
            logAudit($userId, 'logout', [
                'role' => $userRole,
                'name' => $userName,
            ]);
        }
    } catch (Throwable $e) {
        // Never block the logout if the DB is unavailable
    }
}

// ------------------------------------------------------------
// Clear the session data
// ------------------------------------------------------------
$_SESSION = [];

// ------------------------------------------------------------
// Delete the session cookie in the browser
// ------------------------------------------------------------
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path']     ?? '/',
            'domain'   => $params['domain']   ?? '',
            'secure'   => $params['secure']   ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// ------------------------------------------------------------
// Destroy the session on the server
// ------------------------------------------------------------
session_destroy();

// ------------------------------------------------------------
// Start a fresh session so we can flash the logout message
// ------------------------------------------------------------
session_start();
session_regenerate_id(true);
$_SESSION['flash_logout'] = true;

// ------------------------------------------------------------
// Redirect to login.php (works from any subfolder)
// ------------------------------------------------------------
$script   = $_SERVER['PHP_SELF'] ?? '';
$dir      = rtrim(str_replace('\\', '/', dirname($script)), '/');
$loginUrl = $dir . '/login.php?logout=success';

header('Location: ' . $loginUrl);
exit();