<?php
// ============================================================
// index.php
// Wildlife Sentinel — Public Landing Page
// ------------------------------------------------------------
// - Static landing page with animated hero + info panels
// - "Sign In" opens a modal that POSTs to login.php
//   (no duplicate auth handler here — login.php owns auth)
// - Already-logged-in users are redirected to their dashboard
// - NEW: Logo fills its container
// - NEW: Typing animal-words animation in the hero
// ============================================================

require_once __DIR__ . '/includes/functions.php';

// ------------------------------------------------------------
// LOGO
// ------------------------------------------------------------
$logoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQRJ1SEb61M41c5T48BOpfo5-MwLboJ0OBe0-6JOEdL7A&s=10';

// ------------------------------------------------------------
// Redirect already-logged-in users
// ------------------------------------------------------------
if (isset($_SESSION['user_id'])) {
    $u = getCurrentUser();
    if ($u) {
        $dashboardMap = [
            'admin'            => 'admin/dashboard.php',
            'zone_supervisor'  => 'supervisor/dashboard.php',
            'ranger'           => 'ranger/dashboard.php',
            'scout'            => 'scout/dashboard.php',
            'tourism'          => 'tourism/dashboard.php',
        ];
        header('Location: ' . ($dashboardMap[$u['role']] ?? 'admin/dashboard.php'));
        exit();
    } else {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

// ------------------------------------------------------------
// Flash messages via query string
// ------------------------------------------------------------
$flashType = '';
$flashMsg  = '';

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $flashType = 'success';
    $flashMsg  = 'You have been logged out successfully.';
} elseif (isset($_GET['timeout'])) {
    $flashType = 'warning';
    $flashMsg  = 'Your session expired due to inactivity. Please sign in again.';
} elseif (isset($_GET['error'])) {
    $flashType = 'danger';
    $flashMsg  = (string)$_GET['error'];
}

$openLoginOnLoad = ($flashType !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#07130a">
    <title>Wildlife Sentinel — Protecting Zambia's Wildlife</title>
    <link rel="icon" href="<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        :root {
            --green-950: #06170d;
            --green-900: #0a2415;
            --green-800: #103a23;
            --green-700: #14532d;
            --green-600: #166534;
            --green-500: #22c55e;
            --green-400: #4ade80;
            --accent: #facc15;
            --text: #e7f5ec;
            --text-soft: rgba(231, 245, 236, 0.72);
            --text-dim: rgba(231, 245, 236, 0.45);
            --border: rgba(255, 255, 255, 0.08);
        }

        html, body {
            height: 100%; width: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background: var(--green-950);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; }

        /* ============================================
           HERO
           ============================================ */
        .hero {
            position: relative;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .hero-bg { position: absolute; inset: 0; z-index: 0; }

        .hero-slide {
            position: absolute; inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.6s ease-in-out;
            transform: scale(1.05);
        }
        .hero-slide.active { opacity: 1; animation: slowZoom 12s ease-in-out infinite alternate; }
        @keyframes slowZoom { from { transform: scale(1); } to { transform: scale(1.08); } }

        .hero-slide:nth-child(1)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDlRpedivdrhMWOtcwzLfYjW0yRUkVha1Q8R4Ym6nXOw&s=10'); background-position: center 40%; }
        .hero-slide:nth-child(2)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4L-uq9KgyJLZWRHPJ0jhWsf2ljIt2bW0erHD1tq3Q9g&s=10'); background-position: center 35%; }
        .hero-slide:nth-child(3)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSHQOF03RFKPTpE4UrgeCweoidhEuGLIp5IQozrDwES9g&s=10'); background-position: center 40%; }
        .hero-slide:nth-child(4)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTVBLbs7AIaaUimxGpnl7Q425I2xJCCtEkWiezesIUvyQ&s=10'); background-position: center 45%; }
        .hero-slide:nth-child(5)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS-wKmV4_tl7Jzyv0xZPc4oz3LvFvpUNJ88zabMemnlEg&s=10'); background-position: center 30%; }
        .hero-slide:nth-child(6)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcST9BUADclc0JsnQLsycroDnqfm_kOrY1c0gyRgrYnd1A&s=10'); background-position: center 40%; }
        .hero-slide:nth-child(7)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQXYmvMMzcmqeK536AAyZXUrxGUycETRsX2kXm2xo9k0g&s=10'); background-position: center 45%; }
        .hero-slide:nth-child(8)  { background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTcxY1zGw_HWrqaRjkrlAznZhTo1UUcW0Bi1VSFomLNVA&s=10'); background-position: center 35%; }

        .hero-overlay {
            position: absolute; inset: 0; z-index: 1;
            background:
                linear-gradient(180deg,
                    rgba(6,23,13,0.72) 0%,
                    rgba(6,23,13,0.45) 30%,
                    rgba(6,23,13,0.65) 60%,
                    rgba(6,23,13,0.95) 100%),
                radial-gradient(circle at 80% 20%, rgba(34,197,94,0.15) 0%, transparent 50%);
        }

        /* ============================================
           TOP NAV
           ============================================ */
        .topbar {
            position: relative; z-index: 10;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 28px;
            max-width: 1400px; width: 100%;
            margin: 0 auto;
        }

        .topbar-brand {
            display: flex; align-items: center; gap: 10px;
            color: white;
            font-family: 'Fraunces', serif;
            font-weight: 700; font-size: 17px;
        }

        /* ---- LOGO now fills the entire container ---- */
        .topbar-brand .mini-logo {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(34,197,94,0.25), rgba(250,204,21,0.15));
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            padding: 0;
        }
        .topbar-brand .mini-logo img {
            width: 100%; height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            padding: 0;
            margin: 0;
            border-radius: 9px;
        }

        .topbar-nav { display: flex; align-items: center; gap: 22px; }
        .topbar-nav button {
            background: none; border: none;
            color: var(--text-soft);
            font-size: 13px; font-weight: 500;
            transition: color 0.2s;
            padding: 0;
        }
        .topbar-nav button:hover { color: white; }

        .btn-signin {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 20px;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white; border: none;
            border-radius: 50px;
            font-size: 13px; font-weight: 600;
            transition: all 0.25s;
            box-shadow: 0 6px 24px rgba(34,197,94,0.3);
        }
        .btn-signin:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 30px rgba(34,197,94,0.45);
        }

        /* ============================================
           HERO CONTENT
           ============================================ */
        .hero-content {
            position: relative; z-index: 5;
            flex: 1;
            max-width: 1400px; width: 100%;
            margin: 0 auto;
            padding: 30px 28px 60px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 40px;
            align-items: center;
        }

        .hero-text .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px;
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.3);
            border-radius: 50px;
            color: var(--green-400);
            font-size: 11px; font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .hero-text .eyebrow::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--green-400);
            box-shadow: 0 0 12px var(--green-400);
            animation: pulseDot 2s infinite;
        }
        @keyframes pulseDot {
            0%,100% { opacity: 1; }
            50%     { opacity: 0.4; }
        }

        .hero-text h1 {
            font-family: 'Fraunces', serif;
            font-weight: 800;
            font-size: clamp(2.2rem, 5.5vw, 3.6rem);
            line-height: 1.05;
            letter-spacing: -1px;
            color: white;
            margin-bottom: 14px;
            text-shadow: 0 2px 30px rgba(0,0,0,0.4);
        }
        .hero-text h1 .highlight {
            background: linear-gradient(135deg, var(--green-400), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ---- TYPING ANIMALS LINE ---- */
        .typing-line {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 22px;
            min-height: 1.6em;
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: clamp(1.1rem, 2.4vw, 1.6rem);
            letter-spacing: -0.3px;
            color: var(--text-soft);
        }

        .typing-prefix {
            color: rgba(255,255,255,0.55);
            font-weight: 400;
        }

        .typing-animal {
            display: inline-flex;
            align-items: baseline;
            gap: 8px;
            color: var(--green-400);
            background: linear-gradient(135deg, var(--green-400), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            white-space: nowrap;
        }

        .typing-emoji {
            -webkit-text-fill-color: initial;
            display: inline-block;
            font-size: 1.05em;
            transform: translateY(1px);
            animation: emojiPop 0.35s ease;
        }
        @keyframes emojiPop {
            0%   { transform: scale(0.4) translateY(1px); opacity: 0; }
            60%  { transform: scale(1.15) translateY(1px); opacity: 1; }
            100% { transform: scale(1) translateY(1px); }
        }

        .typing-cursor {
            display: inline-block;
            width: 3px;
            height: 1.05em;
            background: var(--green-400);
            margin-left: 4px;
            vertical-align: -0.15em;
            border-radius: 2px;
            animation: blink 1s step-end infinite;
            box-shadow: 0 0 10px rgba(74,222,128,0.6);
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0; }
        }

        .hero-text .tagline {
            font-size: clamp(0.95rem, 1.4vw, 1.1rem);
            color: var(--text-soft);
            line-height: 1.7;
            max-width: 560px;
            margin-bottom: 28px;
        }

        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn-primary-hero {
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white; border: none;
            border-radius: 12px;
            font-size: 14px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 10px;
            transition: all 0.25s;
            box-shadow: 0 8px 30px rgba(34,197,94,0.35);
        }
        .btn-primary-hero:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 40px rgba(34,197,94,0.5);
        }

        .btn-ghost-hero {
            padding: 14px 24px;
            background: rgba(255,255,255,0.06);
            color: white;
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 12px;
            font-size: 14px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.25s;
            backdrop-filter: blur(8px);
        }
        .btn-ghost-hero:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.25);
        }

        .hero-card {
            background: rgba(6,23,13,0.65);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 22px;
            padding: 26px 26px 22px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.5);
        }

        .hero-card h3 {
            font-family: 'Fraunces', serif;
            font-size: 17px; font-weight: 700;
            color: white;
            margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }

        .hero-card p {
            font-size: 13px;
            line-height: 1.65;
            color: var(--text-soft);
            margin-bottom: 10px;
        }
        .hero-card p:last-child { margin-bottom: 0; }

        .hero-card .divider { height: 1px; background: var(--border); margin: 18px 0; }

        .feature-chips {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 14px;
        }

        .chip {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 12px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            font-size: 11.5px;
            color: var(--text-soft);
            font-weight: 500;
            transition: all 0.2s;
        }
        .chip:hover {
            background: rgba(34,197,94,0.08);
            border-color: rgba(34,197,94,0.25);
            color: white;
        }
        .chip .chip-icon { font-size: 14px; flex-shrink: 0; }

        /* ============================================
           FOOTER
           ============================================ */
        .site-footer {
            position: relative; z-index: 5;
            background: rgba(4,14,8,0.92);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--border);
        }

        .footer-inner {
            max-width: 1400px; width: 100%;
            margin: 0 auto;
            padding: 44px 28px 24px;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 36px;
        }

        .footer-brand .footer-logo {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 12px;
        }

        /* ---- LOGO now fills the entire container ---- */
        .footer-brand .footer-logo .logo-box {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(34,197,94,0.2), rgba(250,204,21,0.12));
            border: 1px solid rgba(255,255,255,0.08);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            padding: 0;
        }
        .footer-brand .footer-logo .logo-box img {
            width: 100%; height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            padding: 0;
            margin: 0;
            border-radius: 11px;
        }
        .footer-brand .footer-logo .brand-name {
            font-family: 'Fraunces', serif;
            font-weight: 700; font-size: 15px;
            color: white;
        }

        .footer-brand p {
            font-size: 12.5px;
            line-height: 1.7;
            color: var(--text-dim);
            max-width: 320px;
        }

        .footer-col h4 {
            font-size: 11px; font-weight: 700;
            color: var(--green-400);
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin-bottom: 14px;
        }

        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }

        .footer-col ul li button {
            background: none; border: none; padding: 0;
            font-size: 12.5px;
            color: var(--text-soft);
            transition: color 0.2s;
            text-align: left;
            display: inline-flex; align-items: center; gap: 6px;
            font-family: inherit;
        }
        .footer-col ul li button:hover { color: var(--green-400); }

        .footer-bottom {
            max-width: 1400px; width: 100%;
            margin: 0 auto;
            padding: 18px 28px 24px;
            border-top: 1px solid var(--border);
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 12px;
            font-size: 11.5px;
            color: var(--text-dim);
        }

        .footer-badges { display: flex; gap: 8px; flex-wrap: wrap; }
        .badge-pill {
            padding: 4px 10px;
            background: rgba(34,197,94,0.08);
            border: 1px solid rgba(34,197,94,0.2);
            border-radius: 50px;
            font-size: 10px;
            color: var(--green-400);
            font-weight: 600;
        }

        /* ============================================
           INFO PANEL
           ============================================ */
        .panel-backdrop {
            position: fixed; inset: 0;
            z-index: 90;
            background: rgba(4,14,8,0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0; visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }
        .panel-backdrop.show { opacity: 1; visibility: visible; }

        .info-panel {
            position: fixed; top: 0; right: 0;
            height: 100vh; height: 100dvh;
            width: min(640px, 100vw);
            background: linear-gradient(180deg, #0a2415, #06170d);
            border-left: 1px solid var(--border);
            box-shadow: -20px 0 60px rgba(0,0,0,0.5);
            z-index: 100;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
        }
        .info-panel.show { transform: translateX(0); }

        .panel-header {
            position: sticky; top: 0;
            background: rgba(10,36,21,0.98);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 20px 26px;
            display: flex; align-items: center; justify-content: space-between;
            z-index: 2;
        }

        .panel-header .panel-title { display: flex; align-items: center; gap: 12px; }
        .panel-header .panel-title .pt-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: rgba(34,197,94,0.15);
            border: 1px solid rgba(34,197,94,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .panel-header .panel-title h2 {
            font-family: 'Fraunces', serif;
            font-size: 19px; font-weight: 700;
            color: white; line-height: 1.1;
        }
        .panel-header .panel-title .pt-sub {
            font-size: 11.5px;
            color: var(--text-dim);
            margin-top: 2px;
        }

        .panel-close {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            border: none;
            color: var(--text-soft);
            font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .panel-close:hover { background: rgba(255,255,255,0.14); color: white; }

        .panel-body { padding: 26px 26px 40px; flex: 1; }

        .panel-body h3 {
            font-family: 'Fraunces', serif;
            font-size: 16px; font-weight: 700;
            color: var(--green-400);
            margin-top: 24px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .panel-body h3:first-child { margin-top: 0; }

        .panel-body p {
            font-size: 13.5px;
            line-height: 1.75;
            color: var(--text-soft);
            margin-bottom: 12px;
        }

        .panel-body ul { list-style: none; margin-bottom: 16px; }
        .panel-body ul li {
            position: relative;
            padding-left: 22px;
            font-size: 13.5px;
            line-height: 1.65;
            color: var(--text-soft);
            margin-bottom: 8px;
        }
        .panel-body ul li::before {
            content: '✓';
            position: absolute; left: 0;
            color: var(--green-400);
            font-weight: 700;
        }

        .panel-body .info-box {
            background: rgba(34,197,94,0.06);
            border-left: 3px solid var(--green-400);
            border-radius: 8px;
            padding: 14px 16px;
            margin: 16px 0;
            font-size: 13px;
            line-height: 1.65;
            color: var(--text-soft);
        }

        .panel-body .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 16px 0;
        }
        .panel-body .stat-tile {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
        }
        .panel-body .stat-tile .st-icon { font-size: 24px; display: block; margin-bottom: 4px; }
        .panel-body .stat-tile .st-num {
            font-family: 'Fraunces', serif;
            font-size: 22px; font-weight: 800;
            color: var(--green-400);
            line-height: 1;
        }
        .panel-body .stat-tile .st-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-dim);
            margin-top: 4px;
            font-weight: 600;
        }

        .panel-body .tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .panel-body .tag {
            padding: 4px 10px;
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            border-radius: 50px;
            font-size: 11px;
            color: var(--green-400);
            font-weight: 600;
        }

        .panel-body .contact-grid { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
        .panel-body .contact-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: all 0.2s;
        }
        .panel-body .contact-item:hover {
            background: rgba(34,197,94,0.06);
            border-color: rgba(34,197,94,0.25);
        }
        .panel-body .contact-item .ci-icon { font-size: 22px; flex-shrink: 0; }
        .panel-body .contact-item .ci-text { flex: 1; }
        .panel-body .contact-item .ci-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-dim);
            font-weight: 600;
            margin-bottom: 3px;
        }
        .panel-body .contact-item .ci-value {
            font-size: 14px;
            color: white;
            font-weight: 500;
        }
        .panel-body .contact-item .ci-value a { color: var(--green-400); }

        .panel-body .parks-list { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }
        .panel-body .park-card {
            display: flex; gap: 14px;
            padding: 14px 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: all 0.2s;
        }
        .panel-body .park-card:hover {
            background: rgba(34,197,94,0.06);
            border-color: rgba(34,197,94,0.25);
            transform: translateX(3px);
        }
        .panel-body .park-card .pc-icon { font-size: 26px; flex-shrink: 0; }
        .panel-body .park-card .pc-body { flex: 1; min-width: 0; }
        .panel-body .park-card .pc-name {
            font-size: 14px; font-weight: 700;
            color: white; margin-bottom: 3px;
        }
        .panel-body .park-card .pc-loc { font-size: 11.5px; color: var(--text-dim); }
        .panel-body .park-card .pc-desc {
            font-size: 12.5px;
            color: var(--text-soft);
            margin-top: 6px;
            line-height: 1.55;
        }

        /* ============================================
           MODAL
           ============================================ */
        .modal-backdrop {
            position: fixed; inset: 0;
            z-index: 200;
            background: rgba(4,14,8,0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: none;
            align-items: center; justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .modal-backdrop.show { display: flex; opacity: 1; }

        .modal {
            background: linear-gradient(160deg, rgba(10,36,21,0.98), rgba(6,23,13,0.98));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 22px;
            max-width: 420px; width: 100%;
            padding: 32px 28px 26px;
            position: relative;
            box-shadow: 0 30px 80px rgba(0,0,0,0.6);
            transform: translateY(20px) scale(0.98);
            transition: transform 0.3s;
            max-height: 92vh;
            overflow-y: auto;
        }
        .modal-backdrop.show .modal { transform: translateY(0) scale(1); }

        .modal-close {
            position: absolute; top: 14px; right: 16px;
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.06);
            border: none; border-radius: 50%;
            color: var(--text-soft);
            font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.12); color: white; }

        .modal-header { text-align: center; margin-bottom: 22px; }
        .modal-header .lock-icon {
            width: 56px; height: 56px;
            margin: 0 auto 12px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(34,197,94,0.2), rgba(250,204,21,0.1));
            border: 1px solid rgba(34,197,94,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
        }
        .modal-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 22px; font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }
        .modal-header p { font-size: 12.5px; color: var(--text-dim); }

        .modal .alert {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12.5px;
            margin-bottom: 14px;
            display: flex; align-items: flex-start; gap: 8px;
        }
        .alert-danger  { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.25); color: #fecaca; }
        .alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.25); color: #86efac; }
        .alert-warning { background: rgba(255,193,7,0.14); border: 1px solid rgba(255,193,7,0.3); color: #ffe082; }

        .btn-submit {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white; border: none;
            border-radius: 12px;
            font-size: 14px; font-weight: 600;
            margin-top: 6px;
            transition: all 0.25s;
            box-shadow: 0 8px 24px rgba(34,197,94,0.25);
            min-height: 48px;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(34,197,94,0.4);
            color: white;
        }

        .modal-footer {
            text-align: center;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-dim);
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1024px) {
            .hero-content { grid-template-columns: 1fr; gap: 28px; padding-bottom: 40px; }
            .hero-card { max-width: 560px; }
            .footer-inner { grid-template-columns: 1fr 1fr; gap: 30px; }
        }

        @media (max-width: 768px) {
            .topbar { padding: 14px 18px; }
            .topbar-nav { display: none; }

            .hero-content { padding: 20px 20px 40px; }
            .hero-text h1 { font-size: 2rem; letter-spacing: -0.5px; }
            .typing-line { font-size: 1.05rem; }
            .hero-text .tagline { font-size: 0.95rem; }
            .hero-actions { flex-direction: column; width: 100%; }
            .btn-primary-hero, .btn-ghost-hero { width: 100%; justify-content: center; }

            .hero-card { padding: 22px 20px; border-radius: 18px; }
            .feature-chips { grid-template-columns: 1fr; }

            .footer-inner { grid-template-columns: 1fr; gap: 26px; padding: 32px 20px 20px; }
            .footer-bottom { flex-direction: column; text-align: center; padding: 16px 20px 24px; }

            .info-panel { width: 100vw; }
            .panel-header { padding: 16px 18px; }
            .panel-header .panel-title h2 { font-size: 17px; }
            .panel-body { padding: 20px 18px 32px; }
        }

        @media (max-width: 480px) {
            .hero-text h1 { font-size: 1.75rem; }
            .hero-text .eyebrow { font-size: 10px; padding: 5px 12px; }
            .typing-line { font-size: 0.95rem; gap: 6px; }
            .btn-signin { padding: 8px 16px; font-size: 12px; }
            .topbar-brand .mini-logo { width: 34px; height: 34px; }
            .topbar-brand { font-size: 15px; }
            .modal { padding: 26px 20px 22px; border-radius: 18px; }
            .modal-header h2 { font-size: 19px; }
            .panel-body .stat-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-slide.active,
            .hero-text .eyebrow::before,
            .typing-cursor,
            .typing-emoji {
                animation: none !important;
            }
        }

        @supports (padding: max(0px)) {
            .topbar {
                padding-left: max(28px, env(safe-area-inset-left));
                padding-right: max(28px, env(safe-area-inset-right));
                padding-top: max(18px, env(safe-area-inset-top));
            }
        }
    </style>
</head>
<body>

    <section class="hero">
        <div class="hero-bg">
            <div class="hero-slide active"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
            <div class="hero-slide"></div>
        </div>
        <div class="hero-overlay"></div>

        <header class="topbar">
            <a href="index.php" class="topbar-brand">
                <span class="mini-logo">
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>" alt="Wildlife Sentinel">
                </span>
                <span>Wildlife Sentinel</span>
            </a>

            <nav class="topbar-nav">
                <button onclick="openPanel('about')">About</button>
                <button onclick="openPanel('features')">Features</button>
                <button onclick="openPanel('contact')">Contact</button>
            </nav>

            <a href="login.php" class="btn-signin">🔐 Sign In</a>
        </header>

        <div class="hero-content">
            <div class="hero-text">
                <span class="eyebrow">Zambia Wildlife Protection</span>
                <h1>
                    Every report protects<br>
                    <span class="highlight">Zambia's wildlife.</span>
                </h1>

                <!-- TYPING ANIMALS LINE -->
                <div class="typing-line" aria-live="polite">
                    <span class="typing-prefix">Together we protect</span>
                    <span class="typing-animal">
                        <span class="typing-emoji" id="typingEmoji">🦁</span>
                        <span id="typingWord">lions</span>
                    </span>
                    <span class="typing-cursor" aria-hidden="true"></span>
                </div>

                <p class="tagline">
                    Wildlife Sentinel is a unified response platform connecting rangers, community scouts,
                    tourism operators and zone supervisors across Zambia's national parks and game
                    management areas. Report an incident in seconds — help arrives faster.
                </p>

                <div class="hero-actions">
                    <a href="login.php" class="btn-primary-hero">🔐 Sign In to Your Account</a>
                    <button class="btn-ghost-hero" onclick="openPanel('about')">📖 Learn More</button>
                </div>
            </div>

            <div class="hero-card">
                <h3>🌿 About Wildlife Sentinel</h3>
                <p>
                    A single platform for wildlife conservation in Zambia. Field teams report incidents
                    with photos and GPS location, rangers respond faster, and supervisors coordinate
                    with live maps, alarms and AI detection.
                </p>
                <p>
                    Built for national parks, game management areas, tourism lodges and community
                    conservation programs.
                </p>

                <div class="divider"></div>

                <div class="feature-chips">
                    <div class="chip"><span class="chip-icon">📍</span> Live GPS</div>
                    <div class="chip"><span class="chip-icon">🚨</span> Instant Alerts</div>
                    <div class="chip"><span class="chip-icon">🤖</span> AI Detection</div>
                    <div class="chip"><span class="chip-icon">📹</span> CCTV Systems</div>
                    <div class="chip"><span class="chip-icon">🔔</span> Zone Alarms</div>
                    <div class="chip"><span class="chip-icon">📱</span> SMS Alerts</div>
                    <div class="chip"><span class="chip-icon">🛤️</span> Patrol Routes</div>
                    <div class="chip"><span class="chip-icon">🗺️</span> Zone Mapping</div>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-logo">
                    <span class="logo-box">
                        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES) ?>" alt="Wildlife Sentinel">
                    </span>
                    <span class="brand-name">Wildlife Sentinel</span>
                </div>
                <p>
                    A Zambian conservation technology platform connecting rangers, scouts,
                    tourism operators and zone supervisors to protect wildlife and natural
                    heritage across the country.
                </p>
            </div>

            <div class="footer-col">
                <h4>About</h4>
                <ul>
                    <li><button onclick="openPanel('about')">📖 About the System</button></li>
                    <li><button onclick="openPanel('mission')">🎯 Our Mission</button></li>
                    <li><button onclick="openPanel('features')">🌟 Key Features</button></li>
                    <li><button onclick="openPanel('impact')">📊 Impact Areas</button></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><a href="login.php">🔐 Sign In</a></li>
                    <li><button onclick="openPanel('support')">✉️ Contact Support</button></li>
                    <li><button onclick="openPanel('emergency')">📞 Emergency Line</button></li>
                    <li><button onclick="openPanel('location')">📍 Our Location</button></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Zambia</h4>
                <ul>
                    <li><button onclick="openPanel('parks')">🏞️ National Parks</button></li>
                    <li><button onclick="openPanel('heritage')">🦁 Wildlife Heritage</button></li>
                    <li><button onclick="openPanel('conservation')">🌍 Conservation Areas</button></li>
                    <li><button onclick="openPanel('community')">🤝 Community Programs</button></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-badges">
                <span class="badge-pill">🇿🇲 Zambia</span>
                <span class="badge-pill">🌿 Conservation</span>
                <span class="badge-pill">🦁 Wildlife Protection</span>
            </div>
            <div>
                © <?= date('Y') ?> Wildlife Sentinel — All Rights Reserved.
            </div>
        </div>
    </footer>

    <div class="panel-backdrop" id="panelBackdrop" onclick="closePanel()"></div>
    <aside class="info-panel" id="infoPanel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="pt-icon" id="panelIcon">📖</span>
                <div>
                    <h2 id="panelTitle">About the System</h2>
                    <div class="pt-sub" id="panelSub">Wildlife Sentinel</div>
                </div>
            </div>
            <button class="panel-close" onclick="closePanel()" aria-label="Close panel">✕</button>
        </div>
        <div class="panel-body" id="panelBody"></div>
    </aside>

    <div class="modal-backdrop" id="loginModal" onclick="if (event.target === this) closeLogin()">
        <div class="modal">
            <button class="modal-close" onclick="closeLogin()" aria-label="Close">✕</button>

            <div class="modal-header">
                <div class="lock-icon">🔐</div>
                <h2>Sign In</h2>
                <p>You'll be taken to the secure sign-in page</p>
            </div>

            <?php if ($flashMsg): ?>
                <div class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES) ?>">
                    <span><?= $flashType === 'success' ? '✅' : ($flashType === 'warning' ? '⏱️' : '❌') ?></span>
                    <span><?= htmlspecialchars($flashMsg) ?></span>
                </div>
            <?php endif; ?>

            <p style="font-size:13px;line-height:1.7;color:var(--text-soft);margin-bottom:16px;">
                Wildlife Sentinel uses a dedicated login page for security, with rate-limiting
                and CSRF protection. Continue to sign in below.
            </p>

            <a href="login.php" class="btn-submit">🔐 Continue to Sign In</a>

            <div class="modal-footer">
                Don't have an account? Contact your zone administrator.
            </div>
        </div>
    </div>

    <script>
        // ============================================================
        // BACKGROUND SLIDESHOW
        // ============================================================
        (function () {
            const slides = document.querySelectorAll('.hero-slide');
            if (!slides.length) return;
            let current = 0;
            const total = slides.length;

            setInterval(() => {
                slides[current].classList.remove('active');
                current = (current + 1) % total;
                slides[current].classList.add('active');
            }, 5500);
        })();

        // ============================================================
        // TYPING ANIMALS ANIMATION
        // ============================================================
        (function () {
            const wordEl  = document.getElementById('typingWord');
            const emojiEl = document.getElementById('typingEmoji');
            if (!wordEl || !emojiEl) return;

            // Animals that call Zambia home
            const ANIMALS = [
                { word: 'lions',        emoji: '🦁' },
                { word: 'elephants',    emoji: '🐘' },
                { word: 'rhinos',       emoji: '🦏' },
                { word: 'leopards',     emoji: '🐆' },
                { word: 'giraffes',     emoji: '🦒' },
                { word: 'buffaloes',    emoji: '🐃' },
                { word: 'zebras',       emoji: '🦓' },
                { word: 'hippos',       emoji: '🦛' },
                { word: 'wild dogs',    emoji: '🐕' },
                { word: 'cheetahs',     emoji: '🐆' },
                { word: 'crocodiles',   emoji: '🐊' },
                { word: 'antelopes',    emoji: '🦌' },
                { word: 'wildebeest',   emoji: '🐂' },
                { word: 'fish eagles',  emoji: '🦅' },
                { word: 'wattled cranes',emoji:'🦩' },
                { word: 'shoebills',    emoji: '🦢' },
            ];

            const TYPE_MS       = 90;    // typing speed
            const ERASE_MS      = 45;    // erasing speed
            const HOLD_MS       = 1500;  // pause when word complete
            const BETWEEN_MS    = 250;   // pause before next word

            let animalIdx = 0;
            let charIdx   = 0;
            let phase     = 'typing';    // 'typing' | 'holding' | 'erasing' | 'between'

            // Start with a fresh word
            function setAnimal(animal) {
                emojiEl.textContent = animal.emoji;
                // re-trigger emoji pop animation
                emojiEl.style.animation = 'none';
                void emojiEl.offsetWidth;
                emojiEl.style.animation = '';
                wordEl.textContent = '';
            }

            function tick() {
                const animal = ANIMALS[animalIdx];
                const target = animal.word;

                if (phase === 'typing') {
                    if (charIdx < target.length) {
                        charIdx++;
                        wordEl.textContent = target.slice(0, charIdx);
                        setTimeout(tick, TYPE_MS + Math.random() * 60);
                    } else {
                        phase = 'holding';
                        setTimeout(tick, HOLD_MS);
                    }
                    return;
                }

                if (phase === 'holding') {
                    phase = 'erasing';
                    setTimeout(tick, 200);
                    return;
                }

                if (phase === 'erasing') {
                    if (charIdx > 0) {
                        charIdx--;
                        wordEl.textContent = target.slice(0, charIdx);
                        setTimeout(tick, ERASE_MS);
                    } else {
                        phase = 'between';
                        setTimeout(tick, BETWEEN_MS);
                    }
                    return;
                }

                if (phase === 'between') {
                    animalIdx = (animalIdx + 1) % ANIMALS.length;
                    charIdx = 0;
                    phase = 'typing';
                    setAnimal(ANIMALS[animalIdx]);
                    setTimeout(tick, 100);
                }
            }

            // Optional: honour reduced-motion preference
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reducedMotion) {
                // Show a random animal statically, no animation
                const pick = ANIMALS[Math.floor(Math.random() * ANIMALS.length)];
                emojiEl.textContent = pick.emoji;
                wordEl.textContent  = pick.word;
                return;
            }

            setAnimal(ANIMALS[animalIdx]);
            setTimeout(tick, 400);
        })();

        // ============================================================
        // PANEL CONTENT
        // ============================================================
        const PANELS = {
            about: {
                icon: '📖', title: 'About the System', sub: 'Wildlife Sentinel Overview',
                body: `
                    <p>Wildlife Sentinel is a unified wildlife protection platform designed specifically for Zambia's national parks, game management areas and surrounding communities. It connects field teams, rangers, supervisors and administrators into one coordinated response network.</p>

                    <h3>🎯 Purpose</h3>
                    <p>Zambia is home to some of Africa's most important wildlife populations — but poaching, human-wildlife conflict and habitat loss threaten them daily. Wildlife Sentinel was built to shorten the time between an incident being noticed and rangers arriving on scene.</p>

                    <h3>👥 Who Uses It</h3>
                    <ul>
                        <li><strong>Community Scouts</strong> — Report incidents from the field with photos and GPS location</li>
                        <li><strong>Tourism & Lodge Operators</strong> — Report incidents near guests and lodges</li>
                        <li><strong>Rangers</strong> — Receive alerts, view locations, and coordinate responses</li>
                        <li><strong>Zone Supervisors</strong> — Manage rangers, patrols, cameras and alarms in their zone</li>
                        <li><strong>System Administrators</strong> — Manage zones, users and system-wide configuration</li>
                    </ul>

                    <div class="info-box">
                        <strong>🇿🇲 Built for Zambia</strong><br>
                        Designed around Zambia's 20 national parks, 6+ game management areas, and the communities that live alongside them.
                    </div>

                    <h3>🔄 How It Works</h3>
                    <p>A scout or tourism operator submits a report in under 30 seconds. The system captures the reporter's identity, GPS location, photos and description — then immediately notifies every active ranger in that zone. Rangers can view the location on a live map and start turn-by-turn navigation to the site.</p>

                    <div class="tag-list">
                        <span class="tag">🌍 Nationwide Coverage</span>
                        <span class="tag">📱 Mobile-First</span>
                        <span class="tag">🔒 Secure</span>
                        <span class="tag">🇿🇲 Zambia-Focused</span>
                    </div>
                `
            },

            mission: {
                icon: '🎯', title: 'Our Mission', sub: 'Why We Build This',
                body: `
                    <p>Our mission is simple: <strong>protect Zambia's wildlife by making it easier for people to act quickly and coordinate effectively.</strong></p>

                    <h3>🌿 Core Values</h3>
                    <ul>
                        <li><strong>Speed matters</strong> — Every minute between an incident and a response costs wildlife lives</li>
                        <li><strong>Everyone contributes</strong> — Community scouts and tourism operators are the eyes and ears of conservation</li>
                        <li><strong>Data honesty</strong> — Reports are traceable, timestamped and geo-tagged for accountability</li>
                        <li><strong>Community partnership</strong> — Local people are the most important conservation partners</li>
                    </ul>

                    <h3>🎯 What We Aim To Do</h3>
                    <ul>
                        <li>Reduce poaching response time from hours to minutes</li>
                        <li>Give every ranger a live operational picture of their zone</li>
                        <li>Help supervisors plan patrols based on real incident patterns</li>
                        <li>Empower communities to report what they see without fear</li>
                        <li>Protect tourists and lodge operators with real-time safety information</li>
                    </ul>

                    <div class="info-box">
                        <strong>🌍 A Shared Responsibility</strong><br>
                        Wildlife conservation is not only a ranger's job — it's everyone's job. This platform makes that possible.
                    </div>

                    <div class="tag-list">
                        <span class="tag">🛡️ Protection</span>
                        <span class="tag">⚡ Speed</span>
                        <span class="tag">🤝 Collaboration</span>
                        <span class="tag">📊 Transparency</span>
                    </div>
                `
            },

            features: {
                icon: '🌟', title: 'Key Features', sub: 'What the System Can Do',
                body: `
                    <p>Wildlife Sentinel brings together the tools that field teams, rangers and supervisors need to protect wildlife — all in one mobile-friendly platform.</p>

                    <div class="stat-grid">
                        <div class="stat-tile"><span class="st-icon">📍</span><div class="st-num">GPS</div><div class="st-label">Live Location</div></div>
                        <div class="stat-tile"><span class="st-icon">🚨</span><div class="st-num">30s</div><div class="st-label">Report Time</div></div>
                        <div class="stat-tile"><span class="st-icon">🤖</span><div class="st-num">AI</div><div class="st-label">Detection</div></div>
                        <div class="stat-tile"><span class="st-icon">📱</span><div class="st-num">SMS</div><div class="st-label">Alerts</div></div>
                    </div>

                    <h3>📋 Incident Reporting</h3>
                    <ul>
                        <li>Report incidents with photos and GPS location in under 30 seconds</li>
                        <li>Auto-capture reporter identity for accountability</li>
                        <li>Choose category (poaching, animal distress, conflict, environmental risk)</li>
                        <li>Choose severity (low → critical)</li>
                        <li>Works offline — syncs when connection returns</li>
                    </ul>

                    <h3>🗺️ Live Maps</h3>
                    <ul>
                        <li>Zone boundary + 500m buffer view</li>
                        <li>Live ranger positions on patrol</li>
                        <li>Community scout locations</li>
                        <li>Active incident markers (color-coded by severity)</li>
                        <li>Patrol route trails (last 2 hours)</li>
                    </ul>

                    <h3>📹 Surveillance & Alarms</h3>
                    <ul>
                        <li>CCTV camera integration</li>
                        <li>AI detection (human, animal, vehicle, fire, gunshot)</li>
                        <li>Automatic alarms when AI detects threats</li>
                        <li>Zone alarms that sound on critical events</li>
                        <li>Auto-alarm if incidents aren't acknowledged in time</li>
                    </ul>

                    <h3>📱 Notifications</h3>
                    <ul>
                        <li>Instant alerts to rangers in the affected zone</li>
                        <li>SMS for critical events</li>
                        <li>Push notifications in the app</li>
                        <li>Email digests for supervisors</li>
                        <li>Offline reminder when you've been disconnected</li>
                    </ul>

                    <h3>🛤️ Ranger & Patrol Tools</h3>
                    <ul>
                        <li>Assign patrol routes from the supervisor dashboard</li>
                        <li>Automatic route trail recording</li>
                        <li>Coverage analysis of patrol areas</li>
                        <li>Manpower request system for backup</li>
                    </ul>

                    <div class="tag-list">
                        <span class="tag">📍 Live GPS</span>
                        <span class="tag">🚨 Incident Reports</span>
                        <span class="tag">🤖 AI Detection</span>
                        <span class="tag">📹 CCTV</span>
                        <span class="tag">🔔 Alarms</span>
                        <span class="tag">📱 SMS</span>
                        <span class="tag">🛤️ Patrol Routes</span>
                        <span class="tag">🗺️ Zone Mapping</span>
                    </div>
                `
            },

            impact: {
                icon: '📊', title: 'Impact Areas', sub: 'Where Wildlife Sentinel Makes a Difference',
                body: `
                    <p>Wildlife Sentinel supports conservation across several critical areas of Zambia's wildlife protection effort.</p>

                    <h3>🦏 Anti-Poaching</h3>
                    <p>Report suspected poachers, snares or illegal activity. Every report is geo-tagged and timestamped, giving rangers the evidence trail they need to respond and prosecute.</p>

                    <h3>🐘 Animal Welfare</h3>
                    <p>Report injured, stranded or distressed animals. Rangers can bring vet support, water or relocation resources based on the report.</p>

                    <h3>🐆 Human-Wildlife Conflict</h3>
                    <p>Help communities and wildlife coexist safely by reporting conflict situations before they escalate — crop raiding, livestock predation or property damage.</p>

                    <h3>🔥 Environmental Protection</h3>
                    <p>Report fires, illegal logging, water pollution and other environmental risks. Early detection saves landscapes.</p>

                    <h3>🏞️ Protected Area Management</h3>
                    <p>Supervisors use zone dashboards to plan patrols, manage ranger assignments, and monitor CCTV and AI systems.</p>

                    <div class="info-box">
                        <strong>🇿🇲 Built Around Zambian Parks</strong><br>
                        The system supports all 20 Zambian national parks including South Luangwa, Kafue, Lower Zambezi, North Luangwa, Liuwa Plain, Mosi-oa-Tunya, and many more — plus their surrounding game management areas.
                    </div>

                    <h3>🌍 Sustainable Development</h3>
                    <p>Wildlife protection supports Zambia's sustainable development goals by protecting natural heritage for future generations, supporting ecotourism and creating green jobs.</p>

                    <div class="tag-list">
                        <span class="tag">🦏 Anti-Poaching</span>
                        <span class="tag">🐘 Animal Welfare</span>
                        <span class="tag">🐆 Conflict Resolution</span>
                        <span class="tag">🔥 Environment</span>
                        <span class="tag">🏞️ Protected Areas</span>
                    </div>
                `
            },

            support: {
                icon: '✉️', title: 'Contact Support', sub: 'Get Help with the System',
                body: `
                    <p>Need help with the Wildlife Sentinel platform? Our support team is here to assist.</p>

                    <div class="contact-grid">
                        <div class="contact-item">
                            <span class="ci-icon">✉️</span>
                            <div class="ci-text">
                                <div class="ci-label">General Support</div>
                                <div class="ci-value"><a href="mailto:support@wildlife-sentinel.zm">support@wildlife-sentinel.zm</a></div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="ci-icon">🔧</span>
                            <div class="ci-text">
                                <div class="ci-label">Technical Help</div>
                                <div class="ci-value"><a href="mailto:tech@wildlife-sentinel.zm">tech@wildlife-sentinel.zm</a></div>
                            </div>
                        </div>
                        <div class="contact-item">
                            <span class="ci-icon">👤</span>
                            <div class="ci-text">
                                <div class="ci-label">Account Issues</div>
                                <div class="ci-value">Contact your zone supervisor</div>
                            </div>
                        </div>
                    </div>

                    <h3>🕐 Support Hours</h3>
                    <ul>
                        <li><strong>Monday – Friday:</strong> 08:00 – 17:00 (CAT)</li>
                        <li><strong>Saturday:</strong> 09:00 – 13:00 (CAT)</li>
                        <li><strong>Emergency line:</strong> 24/7 (see Emergency Line)</li>
                    </ul>

                    <h3>📚 Common Questions</h3>
                    <ul>
                        <li>Forgot your password? Contact your zone supervisor to reset it</li>
                        <li>Reports not showing up? Check your zone assignment with your supervisor</li>
                        <li>Photos won't upload? Try a smaller image or check your internet connection</li>
                        <li>Alarm not sounding? Make sure your browser allows sound and autoplay</li>
                    </ul>
                `
            },

            emergency: {
                icon: '📞', title: 'Emergency Line', sub: '24/7 Wildlife Emergency Contact',
                body: `
                    <p>For active wildlife emergencies, poaching incidents in progress, or threats to human safety, use the emergency numbers below immediately.</p>

                    <div class="info-box" style="background:rgba(220,53,69,0.1);border-left-color:#dc3545;">
                        <strong style="color:#fecaca;">🚨 If this is a life-threatening emergency, call 999 first.</strong><br>
                        <span style="color:var(--text-soft);">Then notify the Wildlife Authority emergency line below.</span>
                    </div>

                    <div class="contact-grid">
                        <div class="contact-item">
                            <span class="ci-icon">🚨</span>
                            <div class="ci-text"><div class="ci-label">National Emergency</div><div class="ci-value"><a href="tel:999">999</a> — Police / Ambulance / Fire</div></div>
                        </div>
                        <div class="contact-item">
                            <span class="ci-icon">🐘</span>
                            <div class="ci-text"><div class="ci-label">Wildlife Authority</div><div class="ci-value"><a href="tel:+260211278539">+260 211 278 539</a></div></div>
                        </div>
                        <div class="contact-item">
                            <span class="ci-icon">🦏</span>
                            <div class="ci-text"><div class="ci-label">Anti-Poaching Hotline</div><div class="ci-value"><a href="tel:+260971123456">+260 971 123 456</a></div></div>
                        </div>
                        <div class="contact-item">
                            <span class="ci-icon">🏞️</span>
                            <div class="ci-text"><div class="ci-label">Park Emergency</div><div class="ci-value">Dial your park control room directly</div></div>
                        </div>
                    </div>

                    <h3>📋 What to Report</h3>
                    <p>When calling, be ready to share:</p>
                    <ul>
                        <li>Your location (GPS coordinates or a clear landmark)</li>
                        <li>What you saw (poachers, injured animal, fire, etc.)</li>
                        <li>How many people/animals are involved</li>
                        <li>Direction of movement (if they're mobile)</li>
                        <li>Vehicle description (if any)</li>
                        <li>Your name and phone number (so rangers can call back)</li>
                    </ul>

                    <h3>⚠️ Stay Safe</h3>
                    <ul>
                        <li>Never confront poachers directly — you are not a ranger</li>
                        <li>Keep a safe distance from dangerous animals</li>
                        <li>If you're in danger, get to safety first, then call</li>
                        <li>Take photos from a distance if safe to do so</li>
                    </ul>
                `
            },

            location: {
                icon: '📍', title: 'Our Location', sub: 'Where Wildlife Sentinel Is Operated',
                body: `
                    <p>Wildlife Sentinel is coordinated from Lusaka, Zambia, with field operations running across all of Zambia's national parks and game management areas.</p>

                    <div class="contact-grid">
                        <div class="contact-item"><span class="ci-icon">🏢</span><div class="ci-text"><div class="ci-label">Head Office</div><div class="ci-value">Lusaka, Zambia</div></div></div>
                        <div class="contact-item"><span class="ci-icon">🌐</span><div class="ci-text"><div class="ci-label">Website</div><div class="ci-value">wildlife-sentinel.zm</div></div></div>
                        <div class="contact-item"><span class="ci-icon">📮</span><div class="ci-text"><div class="ci-label">Postal Address</div><div class="ci-value">P.O. Box 300001, Lusaka, Zambia</div></div></div>
                        <div class="contact-item"><span class="ci-icon">🕐</span><div class="ci-text"><div class="ci-label">Office Hours</div><div class="ci-value">Mon – Fri: 08:00 – 17:00 CAT</div></div></div>
                    </div>

                    <h3>🇿🇲 National Coverage</h3>
                    <p>Wildlife Sentinel supports protected areas across all regions of Zambia:</p>
                    <ul>
                        <li><strong>Eastern Province</strong> — South Luangwa National Park, Luangwa GMA</li>
                        <li><strong>Central Zambia</strong> — Kafue National Park, Blue Lagoon, Lochinvar</li>
                        <li><strong>Southern Province</strong> — Mosi-oa-Tunya (Victoria Falls)</li>
                        <li><strong>Northern Province</strong> — North Luangwa, Sumbu, Kasanka, Bangweulu</li>
                        <li><strong>Western Province</strong> — Liuwa Plain, West Lunga</li>
                        <li><strong>Zambezi Valley</strong> — Lower Zambezi National Park</li>
                    </ul>

                    <div class="info-box">
                        <strong>🌐 Everywhere with Wildlife</strong><br>
                        The platform is designed to be extended to every park, reserve and conservation area in Zambia.
                    </div>
                `
            },

            parks: {
                icon: '🏞️', title: 'National Parks', sub: "Zambia's Protected Areas",
                body: `
                    <p>Zambia is home to over 20 national parks and 30+ game management areas, covering nearly 30% of the country's land area. Wildlife Sentinel supports every one of them.</p>

                    <h3>🌟 Featured Parks</h3>
                    <div class="parks-list">
                        <div class="park-card"><span class="pc-icon">🦁</span><div class="pc-body"><div class="pc-name">South Luangwa National Park</div><div class="pc-loc">📍 Eastern Province, Zambia</div><div class="pc-desc">One of Africa's greatest wildlife sanctuaries. Home to elephants, hippos, the famous Luangwa lions, and Thornicroft's giraffe.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🦏</span><div class="pc-body"><div class="pc-name">North Luangwa National Park</div><div class="pc-loc">📍 Northern Province, Zambia</div><div class="pc-desc">Remote wilderness and Zambia's only black rhino conservation area.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🐘</span><div class="pc-body"><div class="pc-name">Kafue National Park</div><div class="pc-loc">📍 Central Zambia</div><div class="pc-desc">Zambia's largest national park, covering over 22,000 km² of pristine wilderness with exceptional biodiversity.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🌊</span><div class="pc-body"><div class="pc-name">Lower Zambezi National Park</div><div class="pc-loc">📍 Zambezi Valley, Zambia</div><div class="pc-desc">Pristine wilderness along the Zambezi River. Famous for canoe safaris and huge elephant herds.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🦓</span><div class="pc-body"><div class="pc-name">Liuwa Plain National Park</div><div class="pc-loc">📍 Western Province, Zambia</div><div class="pc-desc">Home to Africa's second-largest wildebeest migration and spectacular open plains.</div></div></div>
                        <div class="park-card"><span class="pc-icon">💦</span><div class="pc-body"><div class="pc-name">Mosi-oa-Tunya National Park</div><div class="pc-loc">📍 Livingstone, Zambia</div><div class="pc-desc">Home to white rhino and the Zambian side of Victoria Falls — a UNESCO World Heritage Site.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🦇</span><div class="pc-body"><div class="pc-name">Kasanka National Park</div><div class="pc-loc">📍 Central Province, Zambia</div><div class="pc-desc">Famous for the world's largest mammal migration — 10 million straw-coloured fruit bats every November.</div></div></div>
                        <div class="park-card"><span class="pc-icon">🌅</span><div class="pc-body"><div class="pc-name">Sumbu National Park</div><div class="pc-loc">📍 Lake Tanganyika, Zambia</div><div class="pc-desc">Pristine beaches on Lake Tanganyika with unique marine and terrestrial wildlife.</div></div></div>
                    </div>

                    <h3>🌍 Game Management Areas</h3>
                    <p>Beyond national parks, Zambia has 30+ Game Management Areas (GMAs) that act as buffer zones and wildlife corridors. Wildlife Sentinel supports GMAs the same way it supports parks.</p>

                    <div class="tag-list">
                        <span class="tag">20+ National Parks</span>
                        <span class="tag">30+ GMAs</span>
                        <span class="tag">~30% Land Protected</span>
                        <span class="tag">🇿🇲 Nationwide</span>
                    </div>
                `
            },

            heritage: {
                icon: '🦁', title: 'Wildlife Heritage', sub: "Zambia's Iconic Species",
                body: `
                    <p>Zambia is blessed with extraordinary wildlife diversity. These are the iconic species that Wildlife Sentinel helps protect.</p>

                    <h3>🦁 The Big Five</h3>
                    <ul>
                        <li><strong>African Lion</strong> — Zambia's apex predator, especially famous in South Luangwa</li>
                        <li><strong>African Elephant</strong> — Large herds in South Luangwa and Lower Zambezi</li>
                        <li><strong>African Buffalo</strong> — Massive herds throughout Zambia's parks</li>
                        <li><strong>Leopard</strong> — Elusive but abundant in Luangwa and Kafue</li>
                        <li><strong>Black Rhinoceros</strong> — Critically endangered; Zambia's last population is in North Luangwa</li>
                    </ul>

                    <h3>🐘 Other Iconic Species</h3>
                    <ul>
                        <li><strong>Thornicroft's Giraffe</strong> — Endemic to the Luangwa Valley</li>
                        <li><strong>African Wild Dog</strong> — Endangered, protected in several parks</li>
                        <li><strong>Kafue Lechwe</strong> — Antelope endemic to the Kafue Flats</li>
                        <li><strong>Wattled Crane</strong> — Zambia holds Africa's largest population</li>
                        <li><strong>Shoebill Stork</strong> — Found in the Bangweulu Wetlands</li>
                        <li><strong>African Fish Eagle</strong> — Zambia's national bird</li>
                    </ul>

                    <h3>🌿 Conservation Challenges</h3>
                    <ul>
                        <li><strong>Poaching</strong> — Especially for ivory, rhino horn and bushmeat</li>
                        <li><strong>Habitat loss</strong> — Deforestation and agricultural expansion</li>
                        <li><strong>Human-wildlife conflict</strong> — Crops, livestock and lives lost</li>
                        <li><strong>Climate change</strong> — Changing rainfall and water availability</li>
                        <li><strong>Wildlife trafficking</strong> — Zambia as a transit route</li>
                    </ul>

                    <div class="info-box">
                        <strong>🌍 Why This Matters</strong><br>
                        Zambia's wildlife is not just a national treasure — it's a global one. Every report made through Wildlife Sentinel contributes to protecting it for future generations.
                    </div>
                `
            },

            conservation: {
                icon: '🌍', title: 'Conservation Areas', sub: 'How Zambia Protects Its Wildlife',
                body: `
                    <p>Zambia uses several categories of protected areas to safeguard its wildlife and natural heritage. Wildlife Sentinel supports them all.</p>

                    <h3>🏞️ National Parks</h3>
                    <p>Strictly protected areas where human settlement is not allowed. Wildlife is fully protected by law. Zambia has over 20 national parks covering approximately 8% of the country.</p>

                    <h3>🦁 Game Management Areas (GMAs)</h3>
                    <p>Buffer zones around national parks where regulated hunting and human settlement are allowed. GMAs cover about 22% of Zambia's land area and are critical for wildlife corridors. Zambia has over 30 GMAs.</p>

                    <h3>🌿 Community Conservancies</h3>
                    <p>Areas managed by local communities for conservation and sustainable use. They allow communities to benefit directly from wildlife through tourism and other activities.</p>

                    <h3>🌊 Wetlands of International Importance</h3>
                    <p>Zambia has several Ramsar-designated wetlands including the Bangweulu Wetlands, Kafue Flats, and Lukanga Swamps. These are critical for water birds and aquatic wildlife.</p>

                    <h3>🌳 Forest Reserves</h3>
                    <p>Zambia's national forest reserves protect critical habitats and watersheds. They are often adjacent to national parks and act as additional buffers.</p>

                    <div class="info-box">
                        <strong>🌍 The Big Picture</strong><br>
                        Together, Zambia's protected areas cover approximately 30% of the country — one of the highest proportions in Africa. Wildlife Sentinel helps coordinate protection across all of them.
                    </div>

                    <h3>📊 Conservation Statistics</h3>
                    <ul>
                        <li>20+ national parks covering ~64,000 km²</li>
                        <li>30+ game management areas</li>
                        <li>8 Ramsar wetlands</li>
                        <li>480+ species of birds</li>
                        <li>230+ species of mammals</li>
                        <li>~30% of land protected</li>
                    </ul>
                `
            },

            community: {
                icon: '🤝', title: 'Community Programs', sub: 'Local People as Conservation Partners',
                body: `
                    <p>Wildlife conservation in Zambia succeeds because local communities are active partners — not passive observers. Wildlife Sentinel is built around that idea.</p>

                    <h3>👥 Community Scouts</h3>
                    <p>Community scouts are local people trained and equipped to monitor wildlife and report incidents. They're the first line of defense in many areas — and Wildlife Sentinel gives them a direct line to rangers.</p>
                    <ul>
                        <li>Receive training in wildlife monitoring</li>
                        <li>Use the app to report incidents with photos and GPS</li>
                        <li>Get notified of nearby incidents to assist if safe</li>
                        <li>Build a track record for career advancement</li>
                    </ul>

                    <h3>🏘️ Community Engagement</h3>
                    <p>Local communities benefit from wildlife conservation through:</p>
                    <ul>
                        <li>Employment as scouts, rangers and tourism staff</li>
                        <li>Revenue sharing from tourism and hunting concessions</li>
                        <li>Compensation programs for human-wildlife conflict</li>
                        <li>Community resource boards managing local wildlife</li>
                    </ul>

                    <h3>🌿 Human-Wildlife Conflict Mitigation</h3>
                    <p>Wildlife Sentinel helps reduce human-wildlife conflict through:</p>
                    <ul>
                        <li>Early warning of dangerous animals near villages</li>
                        <li>Fast response from rangers to conflict situations</li>
                        <li>Data collection to identify conflict hotspots</li>
                        <li>Coordination with community leaders and traditional authorities</li>
                    </ul>

                    <h3>🎓 Education & Awareness</h3>
                    <ul>
                        <li>Conservation awareness in local schools</li>
                        <li>Training programs for community members</li>
                        <li>Public awareness campaigns</li>
                        <li>Youth engagement through technology</li>
                    </ul>

                    <div class="info-box">
                        <strong>🇿🇲 Community First</strong><br>
                        Zambia's wildlife can only be protected if local communities benefit from and support conservation. Wildlife Sentinel was designed with that principle at its core.
                    </div>
                `
            },

            contact: {
                icon: '✉️', title: 'Contact', sub: 'Reach the Right Team',
                body: `
                    <div class="contact-grid">
                        <div class="contact-item"><span class="ci-icon">✉️</span><div class="ci-text"><div class="ci-label">General Support</div><div class="ci-value"><a href="mailto:support@wildlife-sentinel.zm">support@wildlife-sentinel.zm</a></div></div></div>
                        <div class="contact-item"><span class="ci-icon">📞</span><div class="ci-text"><div class="ci-label">Emergency Line</div><div class="ci-value"><a href="tel:999">999</a> · <a href="tel:+260211278539">+260 211 278 539</a></div></div></div>
                    </div>
                    <p style="margin-top:16px;">See the Contact Support and Emergency Line panels from the footer for full details.</p>
                `
            }
        };

        function openPanel(key) {
            const data = PANELS[key];
            if (!data) return;
            document.getElementById('panelIcon').textContent  = data.icon;
            document.getElementById('panelTitle').textContent = data.title;
            document.getElementById('panelSub').textContent   = data.sub;
            document.getElementById('panelBody').innerHTML    = data.body;
            document.getElementById('infoPanel').classList.add('show');
            document.getElementById('panelBackdrop').classList.add('show');
            document.body.style.overflow = 'hidden';
            document.querySelector('.info-panel').scrollTop = 0;
        }

        function closePanel() {
            document.getElementById('infoPanel').classList.remove('show');
            document.getElementById('panelBackdrop').classList.remove('show');
            document.body.style.overflow = '';
        }

        function openLogin() {
            document.getElementById('loginModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeLogin() {
            document.getElementById('loginModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (document.getElementById('loginModal').classList.contains('show')) {
                closeLogin();
            } else if (document.getElementById('infoPanel').classList.contains('show')) {
                closePanel();
            }
        });

        <?php if ($openLoginOnLoad): ?>
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(openLogin, 300);
        });
        <?php endif; ?>

        console.log('✅ Wildlife Sentinel landing page loaded');
    </script>

</body>
</html>