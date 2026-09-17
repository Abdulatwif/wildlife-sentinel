// ============================================
// TOUCH SWIPE NAVIGATION FOR MOBILE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    let touchStartX = 0;
    let touchEndX = 0;
    let touchStartY = 0;
    let touchEndY = 0;
    let isSwiping = false;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
        isSwiping = false;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        const deltaX = Math.abs(e.changedTouches[0].screenX - touchStartX);
        const deltaY = Math.abs(e.changedTouches[0].screenY - touchStartY);
        
        // Detect if user is swiping horizontally
        if (deltaX > 20 && deltaX > deltaY) {
            isSwiping = true;
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        touchEndY = e.changedTouches[0].screenY;
        
        const diffX = touchStartX - touchEndX;
        const diffY = touchStartY - touchEndY;
        
        // Only trigger if horizontal swipe and not a vertical scroll
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50 && isSwiping) {
            // Swipe left - go to next page (if exists)
            if (diffX > 0) {
                handleSwipeLeft();
            }
            // Swipe right - go to previous page
            else if (diffX < 0) {
                handleSwipeRight();
            }
        }
        
        isSwiping = false;
    }, { passive: true });
});

function handleSwipeLeft() {
    // Check for "Next" button or pagination
    const nextBtn = document.querySelector('.pagination .next, .pagination .page-link:last-child, .btn-next');
    if (nextBtn && !nextBtn.disabled) {
        nextBtn.click();
    } else {
        // Try to find a "View All" link
        const viewAll = document.querySelector('.view-all');
        if (viewAll) {
            viewAll.click();
        }
    }
}

function handleSwipeRight() {
    // Check for "Previous" button or pagination
    const prevBtn = document.querySelector('.pagination .prev, .pagination .page-link:first-child, .btn-prev');
    if (prevBtn && !prevBtn.disabled) {
        prevBtn.click();
    } else {
        // Go back in history
        if (window.history.length > 1) {
            window.history.back();
        }
    }
}

console.log('✅ Mobile swipe navigation enabled');