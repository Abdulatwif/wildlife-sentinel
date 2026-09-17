// ============================================
// PAGE TRANSITION SYSTEM
// ============================================

// Add transition class to body
document.addEventListener('DOMContentLoaded', function() {
    // Add transition container if not exists
    if (!document.querySelector('.page-transition-container')) {
        const container = document.createElement('div');
        container.className = 'page-transition-container';
        document.body.prepend(container);
    }
    
    // Handle all navigation links
    document.querySelectorAll('a').forEach(function(link) {
        // Skip external links, javascript: links, and links with target="_blank"
        if (link.href && 
            !link.href.startsWith('javascript:') && 
            !link.href.startsWith('#') &&
            link.target !== '_blank') {
            
            // Only handle internal links
            const currentHost = window.location.host;
            if (link.hostname === currentHost) {
                link.addEventListener('click', function(e) {
                    // Skip if modifier keys are held
                    if (e.metaKey || e.ctrlKey || e.shiftKey) return;
                    
                    e.preventDefault();
                    const url = link.href;
                    navigateTo(url);
                });
            }
        }
    });
    
    // Handle form submissions
    document.querySelectorAll('form').forEach(function(form) {
        if (form.method === 'get') {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const action = form.action || window.location.href;
                const params = new FormData(form);
                const url = action + '?' + new URLSearchParams(params).toString();
                navigateTo(url);
            });
        }
    });
});

// Navigation function with slide transitions
function navigateTo(url) {
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'page-transition-overlay';
    document.body.appendChild(overlay);
    
    // Trigger slide out
    document.body.classList.add('page-transition-slide-out');
    
    // After animation, navigate
    setTimeout(function() {
        window.location.href = url;
    }, 400);
}

// Add page entrance animation when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('page-transition-slide-in');
    
    // Remove animation class after it completes
    setTimeout(function() {
        document.body.classList.remove('page-transition-slide-in');
    }, 500);
});

// Handle back/forward navigation
window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
        document.body.classList.add('page-transition-slide-in');
        setTimeout(function() {
            document.body.classList.remove('page-transition-slide-in');
        }, 500);
    }
});