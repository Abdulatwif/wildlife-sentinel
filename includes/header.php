<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d3b22">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Wildlife Sentinel">
    
    <title><?= $pageTitle ?? 'Wildlife Sentinel' ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Page Transitions -->
    <link rel="stylesheet" href="assets/css/transitions.css">

    <!-- ============================================================
         OFFLINE / PWA
         ============================================================ -->
    <link rel="manifest" href="manifest.webmanifest">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🇿🇲</text></svg>">
    
    <!-- Leaflet CSS (for map pages) -->
    <?php if (isset($includeMap) && $includeMap): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <?php endif; ?>
    
    <style>
        /* Ensure transitions work on all devices */
        .page-transition-slide-out .main-content,
        .page-transition-slide-out .app-container,
        .page-transition-slide-out .content,
        .page-transition-slide-out .section,
        .page-transition-slide-out .header,
        .page-transition-slide-out .sidebar {
            will-change: transform, opacity;
        }
        
        /* Prevent flash of unstyled content */
        .page-transition-slide-in .main-content,
        .page-transition-slide-in .app-container,
        .page-transition-slide-in .content,
        .page-transition-slide-in .section,
        .page-transition-slide-in .header,
        .page-transition-slide-in .sidebar {
            animation: slideInRight 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards !important;
        }

        /* ============================================================
           OFFLINE INDICATORS
           ============================================================ */

        /* Small dot on the top-right that shows current connection */
        #ws-conn-indicator {
            position: fixed;
            top: 12px;
            right: 12px;
            z-index: 99998;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 50px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            background: rgba(34,197,94,0.15);
            color: #4ade80;
            border: 1px solid rgba(34,197,94,0.35);
            transition: all 0.3s;
            pointer-events: none;
            opacity: 0;
        }
        #ws-conn-indicator.show { opacity: 1; }
        #ws-conn-indicator .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 6px currentColor;
        }
        #ws-conn-indicator.offline {
            background: rgba(250,204,21,0.15);
            color: #facc15;
            border-color: rgba(250,204,21,0.35);
        }
        @media (max-width: 768px) {
            #ws-conn-indicator { top: 8px; right: 8px; font-size: 10px; }
        }

        /* Online/offline toast */
        #ws-conn-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translate(-50%, 20px);
            z-index: 99998;
            padding: 10px 18px;
            background: #166534;
            color: white;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 13px;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 90vw;
        }
        #ws-conn-toast.show { opacity: 1; transform: translate(-50%, 0); }
        #ws-conn-toast.warn { background: #92400e; }
    </style>
</head>
<body>

    <!-- Live connection indicator (top-right, only shown when offline) -->
    <div id="ws-conn-indicator" aria-live="polite">
        <span class="dot"></span>
        <span id="ws-conn-indicator-text">Online</span>
    </div>

    <!-- Transition toast (bottom-center) -->
    <div id="ws-conn-toast" role="status" aria-live="polite"></div>

<!-- The rest of your body starts here (sidebar, main content, etc.) -->