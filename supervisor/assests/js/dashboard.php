// ============================================================
// supervisor/assets/js/dashboard.js
// Sidebar switching, notifications, helper UI
// ============================================================

(function () {
    const notifications = [];

    // ------------------------------------------------------------
    // VIEW SWITCHING
    // ------------------------------------------------------------
    function switchView(view) {
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        const btn = document.querySelector(`.nav-item[data-view="${view}"]`);
        if (btn) btn.classList.add('active');

        const titles = {
            'dashboard':  'Dashboard',
            'live-map':   'Live Map',
            'incidents':  'Incidents',
            'rangers':    'Rangers',
            'scouts':     'Community Scouts',
            'messages':   'Messages',
            'manpower':   'Manpower Requests',
            'users':      'User Management',
            'patrols':    'Patrol Routes',
            'analytics':  'Analytics',
            'audit':      'Audit Logs',
        };
        const titleEl = document.getElementById('page-title');
        if (titleEl) titleEl.textContent = titles[view] || view;

        // Route to server-side pages for full views
        if (view === 'scouts')    return (window.location.href = 'scouts.php');
        if (view === 'manpower')  return (window.location.href = 'manpower.php');
        if (view === 'users')     return (window.location.href = 'users.php');
        if (view === 'patrols')   return (window.location.href = 'patrols.php');
        if (view === 'audit')     return (window.location.href = 'audit.php');
        if (view === 'messages')  return (window.location.href = 'messages.php');

        // Dashboard / live-map / rangers stay on this page
        if (view === 'live-map') {
            const mapPanel = document.querySelector('.map-panel');
            if (mapPanel) mapPanel.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // ------------------------------------------------------------
    // NOTIFICATIONS PANEL
    // ------------------------------------------------------------
    function toggleNotifications() {
        const panel = document.getElementById('notification-panel');
        if (!panel) return;
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }

    function addNotification(notif) {
        notifications.unshift(Object.assign({ id: Date.now(), timestamp: new Date() }, notif));
        renderNotifications();

        const dot = document.getElementById('notif-count');
        if (dot) {
            dot.textContent = notifications.length;
            dot.style.display = 'inline-block';
        }
    }

    function renderNotifications() {
        const list = document.getElementById('notification-list');
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = '<p class="empty">No new notifications</p>';
            return;
        }

        list.innerHTML = notifications.map(n => `
            <div class="notification-item ${n.urgent ? 'urgent' : ''}">
                <strong>${n.title || 'Notification'}</strong>
                <p>${n.body || ''}</p>
                <span>${new Date(n.timestamp).toLocaleTimeString()}</span>
            </div>
        `).join('');
    }

    // ------------------------------------------------------------
    // SIDEBAR TOGGLE (mobile)
    // ------------------------------------------------------------
    function toggleSidebar(open) {
        const sb = document.getElementById('sidebar');
        if (!sb) return;
        if (typeof open === 'boolean') {
            sb.classList.toggle('open', open);
        } else {
            sb.classList.toggle('open');
        }
    }

    // ------------------------------------------------------------
    // EXPORTS
    // ------------------------------------------------------------
    window.switchView          = switchView;
    window.toggleNotifications = toggleNotifications;
    window.toggleSidebar       = toggleSidebar;
    window.addNotification     = addNotification;
})();