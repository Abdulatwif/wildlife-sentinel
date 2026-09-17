<?php
// ============================================================
// includes/sidebar.php
// Wildlife Sentinel — Role-Aware Sidebar (v3.2)
// ------------------------------------------------------------
// Works from any subfolder. Auto-detects root prefix.
// Highlights active page. Shows live badge counts.
// Supports: admin | zone_supervisor | ranger | scout | tourism
// ------------------------------------------------------------
// Offline support:
//   - PWA manifest is linked in <head> below
//   - assets/js/offline-manager.js is loaded at the end
// ------------------------------------------------------------
// Logout:
//   - One universal entry that works for every role
// ============================================================

// Logo URL
$sidebarLogoUrl = 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSNUo8sFW1IUxMnFDN_ZE2dSEegfrRcFqyzgZfg1L2I1g&s';

// ------------------------------------------------------------
// USER (guarded against null)
// ------------------------------------------------------------
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
if (!$currentUser) {
    $currentUser = [
        'id'        => 0,
        'full_name' => 'Guest',
        'role'      => 'guest',
        'zone_id'   => 0,
    ];
}

$role = $currentUser['role'] ?? 'guest';
$pdo  = getDB();

// ------------------------------------------------------------
// ROOT PREFIX DETECTION
// ------------------------------------------------------------
$scriptPath  = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$currentPage = basename($scriptPath);
$currentDir  = basename(dirname($scriptPath));

$rootPrefix = '';
if (preg_match('#^(.*?/wildlife-sentinel)(/.*)?$#i', $scriptPath, $m)) {
    $rest  = $m[2] ?? '';
    $depth = substr_count(trim($rest, '/'), '/');
    $rootPrefix = str_repeat('../', $depth);
} else {
    $dir   = rtrim(dirname($scriptPath), '/');
    $parts = array_values(array_filter(explode('/', $dir), fn($p) => $p !== '' && $p !== '.'));
    if (!empty($parts)) array_pop($parts);
    $rootPrefix = str_repeat('../', count($parts));
}

// ------------------------------------------------------------
// Safe count helper
// ------------------------------------------------------------
if (!function_exists('sb_count')) {
    function sb_count(PDO $pdo, string $sql, array $params = []): int {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            if (!$row) return 0;
            return (int)($row['count'] ?? $row['c'] ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// ------------------------------------------------------------
// BADGE COUNTS
// ------------------------------------------------------------
$userId = (int)($currentUser['id'] ?? 0);
$zoneId = (int)($currentUser['zone_id'] ?? 0);

$notifCount = ($userId > 0 && function_exists('getUnreadNotificationCount'))
    ? (int)getUnreadNotificationCount($userId) : 0;

$msgCount = $userId > 0
    ? sb_count($pdo, "SELECT COUNT(*) as count FROM messages WHERE (recipient_id = ? OR is_broadcast = 1) AND is_read = 0", [$userId])
    : 0;

$incidentCount = 0;
if (in_array($role, ['ranger','zone_supervisor','admin'], true)) {
    if ($role === 'admin') {
        $incidentCount = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE status = 'reported'");
    } elseif ($role === 'zone_supervisor') {
        $incidentCount = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE zone_id = ? AND status = 'reported'", [$zoneId]);
    } elseif ($role === 'ranger') {
        $incidentCount = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE acknowledged_by = ? AND status IN ('acknowledged','in_progress')", [$userId]);
    }
}

$availableParksCount = 0;
if ($role === 'admin') {
    $availableParksCount = sb_count($pdo, "SELECT COUNT(*) as count FROM zones WHERE park_type IN ('national_park','gma') AND is_registered = 0");
}

$rangerCount = 0; $scoutCount = 0;
if ($role === 'zone_supervisor') {
    $rangerCount = sb_count($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'ranger' AND is_active = 1", [$zoneId]);
    $scoutCount  = sb_count($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'scout'  AND is_active = 1", [$zoneId]);
}

$manpowerCount = 0;
if ($role === 'zone_supervisor') {
    $manpowerCount = sb_count($pdo, "
        SELECT COUNT(*) as count FROM messages
        WHERE message_type = 'manpower_request' AND is_read = 0
          AND (zone_id = ? OR zone_id IS NULL)
    ", [$zoneId]);
}

$aiAlertCount = 0;
if ($role === 'admin') {
    $aiAlertCount = sb_count($pdo, "SELECT COUNT(*) as count FROM ai_alerts WHERE is_acknowledged = 0");
} elseif ($role === 'zone_supervisor') {
    $aiAlertCount = sb_count($pdo, "SELECT COUNT(*) as count FROM ai_alerts WHERE zone_id = ? AND is_acknowledged = 0", [$zoneId]);
}

$activeAlarmCount = 0;
if ($role === 'admin') {
    $activeAlarmCount = sb_count($pdo, "SELECT COUNT(*) as count FROM alarm_triggers WHERE stopped_at IS NULL");
} elseif ($role === 'zone_supervisor') {
    $activeAlarmCount = sb_count($pdo, "SELECT COUNT(*) as count FROM alarm_triggers WHERE zone_id = ? AND stopped_at IS NULL", [$zoneId]);
}

$myReportsCount = 0; $myReportsActive = 0; $scoutReportsResolved = 0;
if (in_array($role, ['tourism','scout'], true)) {
    $myReportsCount  = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ?", [$userId]);
    $myReportsActive = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? AND status NOT IN ('resolved','closed')", [$userId]);
    if ($role === 'scout') {
        $scoutReportsResolved = sb_count($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? AND status = 'resolved'", [$userId]);
    }
}

// ------------------------------------------------------------
// Nav helpers
// ------------------------------------------------------------
if (!function_exists('navActive')) {
    function navActive(array $pages, string $currentPage, string $currentDir = '', array $dirs = []): string {
        if (in_array($currentPage, $pages, true)) {
            if (empty($dirs) || in_array($currentDir, $dirs, true)) return 'active';
        }
        return '';
    }
}
if (!function_exists('navLink')) {
    function navLink(string $href, string $icon, string $label, int $badge = 0, string $badgeClass = '', bool $isActive = false): void {
        $cls = $isActive ? 'active' : '';
        $badgeHtml = '';
        if ($badge > 0) {
            $bcls = $badgeClass ? ' ' . $badgeClass : '';
            $badgeHtml = '<span class="nav-badge' . $bcls . '">' . (int)$badge . '</span>';
        }
        echo '<li class="nav-item ' . $cls . '">';
        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">';
        echo '<span class="nav-icon">' . $icon . '</span>';
        echo '<span class="nav-label">' . htmlspecialchars($label) . '</span>';
        echo $badgeHtml;
        echo '</a></li>';
    }
}
?>
<link rel="manifest" href="<?= htmlspecialchars($rootPrefix, ENT_QUOTES) ?>manifest.webmanifest">
<meta name="theme-color" content="#0d3b22">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Wildlife Sentinel">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span class="brand-logo"><img src="<?= htmlspecialchars($sidebarLogoUrl, ENT_QUOTES) ?>" alt="Wildlife Sentinel"></span>
            <span class="brand-text">Wildlife Sentinel</span>
        </div>
        <button class="close-sidebar" onclick="closeSidebar()">&times;</button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr((string)$currentUser['full_name'], 0, 1) ?: '?')) ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars((string)$currentUser['full_name']) ?></div>
            <div class="user-role"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?></div>
        </div>
    </div>

    <div class="sidebar-conn" id="sidebarConn" style="display:none;">
        <span class="conn-dot"></span>
        <span class="conn-text">Offline — queuing</span>
    </div>

    <ul class="nav-menu">
        <li class="nav-divider">Main</li>

        <?php if ($role === 'admin'): ?>
            <?php navLink($rootPrefix.'admin/dashboard.php','📊','Dashboard',0,'',navActive(['dashboard.php'],$currentPage,$currentDir,['admin'])==='active'); ?>
        <?php elseif ($role === 'zone_supervisor'): ?>
            <?php navLink($rootPrefix.'supervisor/dashboard.php','📊','Dashboard',0,'',navActive(['dashboard.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php elseif ($role === 'ranger'): ?>
            <?php navLink($rootPrefix.'ranger/dashboard.php','📊','Dashboard',0,'',navActive(['dashboard.php'],$currentPage,$currentDir,['ranger'])==='active'); ?>
        <?php elseif ($role === 'scout'): ?>
            <?php navLink($rootPrefix.'scout/dashboard.php','📊','Dashboard',0,'',navActive(['dashboard.php'],$currentPage,$currentDir,['scout'])==='active'); ?>
        <?php elseif ($role === 'tourism'): ?>
            <?php navLink($rootPrefix.'tourism/dashboard.php','📊','Dashboard',0,'',navActive(['dashboard.php'],$currentPage,$currentDir,['tourism'])==='active'); ?>
        <?php endif; ?>

        <?php if (in_array($role, ['ranger','zone_supervisor','admin'], true)): ?>
            <?php
            if ($role === 'admin')                $incHref = $rootPrefix.'admin/incidents.php';
            elseif ($role === 'zone_supervisor')  $incHref = $rootPrefix.'supervisor/incidents.php';
            else                                  $incHref = $rootPrefix.'ranger/incidents.php';
            navLink($incHref,'🚨','Incidents',$incidentCount,'urgent',navActive(['incidents.php'],$currentPage,$currentDir)==='active');
            ?>
        <?php endif; ?>

        <?php if (in_array($role, ['ranger','zone_supervisor','admin'], true)): ?>
            <?php
            $mapHref = $role === 'ranger' ? $rootPrefix.'ranger/map.php'
                     : ($role === 'zone_supervisor' ? $rootPrefix.'supervisor/map.php'
                     : $rootPrefix.'admin/map.php');
            navLink($mapHref,'📍','Live Map',0,'',navActive(['map.php'],$currentPage)==='active');
            ?>
        <?php endif; ?>

        <?php
        $msgLink = $rootPrefix.'messages.php';
        if ($role === 'admin')               $msgLink = $rootPrefix.'admin/messages.php';
        elseif ($role === 'zone_supervisor') $msgLink = $rootPrefix.'supervisor/messages.php';
        elseif ($role === 'ranger')          $msgLink = $rootPrefix.'ranger/messages.php';
        elseif ($role === 'scout')           $msgLink = $rootPrefix.'scout/messages.php';
        elseif ($role === 'tourism')         $msgLink = $rootPrefix.'tourism/messages.php';
        ?>
        <?php navLink($msgLink,'💬','Messages',$msgCount,'',navActive(['messages.php'],$currentPage)==='active'); ?>

        <?php if ($role === 'ranger'): ?>
        <li class="nav-divider">Ranger Tools</li>
        <?php navLink($rootPrefix.'ranger/request-manpower.php','🆘','Request Manpower',0,'',navActive(['request-manpower.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'ranger/notifications.php','🔔','Notifications',$notifCount,'',navActive(['notifications.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'ranger/profile.php','👤','My Profile',0,'',navActive(['profile.php'],$currentPage)==='active'); ?>
        <?php endif; ?>

        <?php if (in_array($role, ['scout','tourism'], true)): ?>
        <li class="nav-divider">Reporting</li>
        <?php
        $reportHref  = $role === 'scout' ? $rootPrefix.'scout/report.php'       : $rootPrefix.'tourism/report.php';
        $myRepHref   = $role === 'scout' ? $rootPrefix.'scout/my-reports.php'   : $rootPrefix.'tourism/my-reports.php';
        $notifHref   = $role === 'scout' ? $rootPrefix.'scout/notifications.php': $rootPrefix.'tourism/notifications.php';
        $profileHref = $role === 'scout' ? $rootPrefix.'scout/profile.php'      : $rootPrefix.'tourism/settings.php';
        ?>
        <?php navLink($reportHref,'✏️','Report Incident',0,'',navActive(['report.php'],$currentPage)==='active'); ?>
        <?php navLink($myRepHref,'📋','My Reports',$myReportsActive,'',navActive(['my-reports.php'],$currentPage)==='active'); ?>

        <?php if ($role === 'tourism'): ?>
            <?php navLink($rootPrefix.'tourism/safety-map.php','🛡️','Safety Map',0,'',navActive(['safety-map.php'],$currentPage)==='active'); ?>
        <?php endif; ?>

        <?php navLink($notifHref,'🔔','Notifications',$notifCount,'',navActive(['notifications.php'],$currentPage)==='active'); ?>
        <?php navLink($profileHref,'👤','My Profile',0,'',navActive(['profile.php','settings.php'],$currentPage)==='active'); ?>
        <?php endif; ?>

        <?php if ($role === 'zone_supervisor'): ?>
        <li class="nav-divider">Supervisor Tools</li>
        <?php navLink($rootPrefix.'supervisor/rangers.php','🛡️','My Rangers',$rangerCount,'',navActive(['rangers.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/scouts.php','👥','Community Scouts',$scoutCount,'',navActive(['scouts.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/manpower.php','🆘','Manpower Requests',$manpowerCount,'urgent',navActive(['manpower.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/patrols.php','🛤️','Patrol Routes',0,'',navActive(['patrols.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/users.php','👤','User Management',0,'',navActive(['users.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/audit.php','📈','Audit Logs',0,'',navActive(['audit.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/zone-settings.php','⚙️','Zone Settings',$activeAlarmCount,'urgent',navActive(['zone-settings.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>

        <li class="nav-divider">AI & Surveillance</li>
        <?php navLink($rootPrefix.'supervisor/ai-dashboard.php','🤖','AI Detection',$aiAlertCount,'urgent',navActive(['ai-dashboard.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/cctv-cameras.php','📹','CCTV Cameras',0,'',navActive(['cctv-cameras.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/alarm-systems.php','🔔','Alarm Systems',$activeAlarmCount,'urgent',navActive(['alarm-systems.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/sms-gateway.php','📱','SMS Gateway',0,'',navActive(['sms-gateway.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/simulation.php','🎮','Simulation',0,'',navActive(['simulation.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>

        <li class="nav-divider">Account</li>
        <?php navLink($rootPrefix.'supervisor/notifications.php','🔔','Notifications',$notifCount,'',navActive(['notifications.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php navLink($rootPrefix.'supervisor/settings.php','⚙️','Settings',0,'',navActive(['settings.php','profile.php'],$currentPage,$currentDir,['supervisor'])==='active'); ?>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <li class="nav-divider">Management</li>
        <?php navLink($rootPrefix.'admin/users.php','👥','User Management',0,'',navActive(['users.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/rangers.php','🛡️','Rangers',0,'',navActive(['rangers.php'],$currentPage,$currentDir,['admin'])==='active'); ?>
        <?php navLink($rootPrefix.'admin/zones.php','🗺️','Zone Management',0,'',navActive(['zones.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/register-zone.php','🏛️','Register Zone',$availableParksCount,'',navActive(['register-zone.php'],$currentPage)==='active'); ?>

        <li class="nav-divider">AI & Surveillance</li>
        <?php navLink($rootPrefix.'admin/ai-dashboard.php','🤖','AI Detection',$aiAlertCount,'urgent',navActive(['ai-dashboard.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/cctv-cameras.php','📹','CCTV Cameras',0,'',navActive(['cctv-cameras.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/alarm-systems.php','🔔','Alarm Systems',$activeAlarmCount,'urgent',navActive(['alarm-systems.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/sms-gateway.php','📱','SMS Gateway',0,'',navActive(['sms-gateway.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/simulation.php','🎮','Simulation',0,'',navActive(['simulation.php'],$currentPage)==='active'); ?>

        <li class="nav-divider">System</li>
        <?php navLink($rootPrefix.'admin/settings.php','⚙️','System Settings',0,'',navActive(['settings.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/audit.php','📈','Audit Logs',0,'',navActive(['audit.php'],$currentPage,$currentDir,['admin'])==='active'); ?>
        <?php navLink($rootPrefix.'admin/notifications.php','🔔','Notifications',$notifCount,'',navActive(['notifications.php'],$currentPage)==='active'); ?>
        <?php navLink($rootPrefix.'admin/profile.php','👤','My Profile',0,'',navActive(['profile.php'],$currentPage)==='active'); ?>
        <?php endif; ?>

        <?php if ($role !== 'guest' && $userId > 0): ?>
        <li class="nav-divider">Session</li>
        <?php navLink($rootPrefix.'logout.php','🚪','Logout'); ?>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="sidebar-version">v3.2 AI</div>
        <div class="sidebar-status">
            <span class="status-dot"></span>
            <span class="status-text">Online</span>
        </div>
    </div>
</nav>

<style>
.sidebar { width: 280px; min-width: 280px; background: linear-gradient(180deg, #0d3b22 0%, #1a5c3a 100%); color: white; position: fixed; top: 0; left: 0; bottom: 0; z-index: 1000; overflow-y: auto; display: flex; flex-direction: column; transition: transform 0.3s ease; box-shadow: 2px 0 20px rgba(0,0,0,0.15); }
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

.sidebar-header { padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.sidebar-brand { display: flex; align-items: center; gap: 10px; }
.sidebar-brand .brand-logo { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.15); overflow: hidden; flex-shrink: 0; padding: 3px; }
.sidebar-brand .brand-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.sidebar-brand .brand-text { font-size: 17px; font-weight: 700; letter-spacing: -0.5px; }
.close-sidebar { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.7; padding: 0 4px; }
.close-sidebar:hover { opacity: 1; }

.sidebar-user { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.sidebar-user .user-avatar { width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: white; flex-shrink: 0; }
.sidebar-user .user-info { flex: 1; min-width: 0; }
.sidebar-user .user-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-user .user-role { font-size: 11px; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; }

.sidebar-conn { margin: 10px 14px 4px; padding: 8px 12px; border-radius: 10px; background: rgba(250,204,21,0.12); border: 1px solid rgba(250,204,21,0.3); color: #facc15; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 8px; animation: connPulse 2s infinite; }
.sidebar-conn .conn-dot { width: 8px; height: 8px; border-radius: 50%; background: #facc15; box-shadow: 0 0 8px #facc15; }
@keyframes connPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

.nav-menu { list-style: none; padding: 8px 12px; flex: 1; overflow-y: auto; }
.nav-item { position: relative; margin-bottom: 2px; }
.nav-item a { display: flex; align-items: center; padding: 10px 14px; color: rgba(255,255,255,0.75); text-decoration: none; transition: all 0.3s; gap: 12px; border-radius: 10px; font-size: 14px; }
.nav-item a:hover { background: rgba(255,255,255,0.08); color: white; }
.nav-item.active a { background: rgba(255,255,255,0.15); color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.nav-item.active a::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 24px; background: #4ade80; border-radius: 0 4px 4px 0; }
.nav-icon { font-size: 18px; width: 24px; text-align: center; flex-shrink: 0; }
.nav-label { flex: 1; }
.nav-badge { background: #dc3545; color: white; font-size: 11px; padding: 2px 10px; border-radius: 12px; font-weight: 600; min-width: 20px; text-align: center; }
.nav-badge.urgent { animation: pulseBadge 1.5s infinite; }
@keyframes pulseBadge { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
.nav-divider { padding: 12px 14px 6px; color: rgba(255,255,255,0.35); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }

.sidebar-footer { padding: 14px 20px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; font-size: 12px; flex-shrink: 0; }
.sidebar-version { opacity: 0.4; }
.sidebar-status { display: flex; align-items: center; gap: 6px; }
.sidebar-status .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; animation: pulseDot 2s infinite; transition: background 0.3s; }
.sidebar-status .status-text { opacity: 0.6; }
.sidebar-status.is-offline .status-dot { background: #facc15; }
.sidebar-status.is-offline .status-text { color: #facc15; opacity: 1; }
@keyframes pulseDot { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }

.sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
.sidebar-overlay.show { display: block; }

.main-content { margin-left: 280px; flex: 1; min-height: 100vh; background: #f0f2f5; width: calc(100% - 280px); transition: margin-left 0.3s ease; }

@media (max-width: 1024px) {
    .sidebar { width: 260px; min-width: 260px; }
    .main-content { margin-left: 260px; width: calc(100% - 260px); }
}
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); width: 280px; min-width: 280px; }
    .sidebar.open { transform: translateX(0); }
    .close-sidebar { display: block; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .sidebar-overlay.show { display: block; }
}
@media (max-width: 480px) { .sidebar { width: 100%; min-width: 100%; max-width: 320px; } }

.menu-toggle { display: none; background: none; border: none; font-size: 24px; cursor: pointer; margin-right: 12px; color: var(--gray-700, #495057); padding: 4px 8px; border-radius: 8px; transition: background 0.2s; }
.menu-toggle:hover { background: var(--gray-100, #f8f9fa); }
@media (max-width: 768px) { .menu-toggle { display: block; } }
</style>

<script>
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const o = document.getElementById('sidebarOverlay');
    if (!s) return;
    s.classList.toggle('open');
    if (o) o.classList.toggle('show');
}
function closeSidebar() {
    const s = document.getElementById('sidebar');
    const o = document.getElementById('sidebarOverlay');
    if (!s) return;
    s.classList.remove('open');
    if (o) o.classList.remove('show');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
window.addEventListener('resize', () => {
    const s = document.getElementById('sidebar');
    const o = document.getElementById('sidebarOverlay');
    if (window.innerWidth > 768 && s) {
        s.classList.remove('open');
        if (o) o.classList.remove('show');
    }
});
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.nav-item a').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
    });
});
console.log('✅ Sidebar loaded — v3.2 AI');
</script>

<script>
(function () {
    const depth = (location.pathname.match(/\//g) || []).length - 1;
    const prefix = '../'.repeat(Math.max(0, depth));

    const s = document.createElement('script');
    s.src = prefix + 'assets/js/offline-manager.js';
    s.defer = true;
    s.onerror = function () {};
    document.body.appendChild(s);

    function setSidebarOnline(isOnline) {
        const conn     = document.getElementById('sidebarConn');
        const statusEl = document.querySelector('.sidebar-status');
        const textEl   = statusEl ? statusEl.querySelector('.status-text') : null;
        if (conn) conn.style.display = isOnline ? 'none' : 'flex';
        if (statusEl) statusEl.classList.toggle('is-offline', !isOnline);
        if (textEl) textEl.textContent = isOnline ? 'Online' : 'Offline';
    }
    setSidebarOnline(navigator.onLine);
    window.addEventListener('online',  () => setSidebarOnline(true));
    window.addEventListener('offline', () => setSidebarOnline(false));
    document.addEventListener('ws:online',  () => setSidebarOnline(true));
    document.addEventListener('ws:offline', () => setSidebarOnline(false));
})();
</script>