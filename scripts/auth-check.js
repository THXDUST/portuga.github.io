/**
 * Global Authentication Check
 * Automatically loads on all pages to update UI based on login status
 */

(function() {
    'use strict';
    
    // Run on page load
    document.addEventListener('DOMContentLoaded', async () => {
        // Update navigation UI
        if (typeof updateNavigationUI === 'function') {
            await updateNavigationUI();
        }
        
        // Show hardcoded user badge if applicable
        showHardcodedUserBadge();
    });
    
    /**
     * Show badge for hardcoded test users
     */
    function showHardcodedUserBadge() {
        const userStr = localStorage.getItem('user');
        if (!userStr) return;
        
        try {
            const user = JSON.parse(userStr);
            
            if (user.is_hardcoded) {
                // Create badge element
                const badge = document.createElement('div');
                badge.id = 'hardcoded-user-badge';
                badge.style.cssText = `
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    background: #ffc107;
                    color: #333;
                    padding: 8px 15px;
                    border-radius: 20px;
                    font-size: 0.85rem;
                    font-weight: bold;
                    z-index: 9999;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                `;
                badge.innerHTML = `🧪 Test User: ${user.user_type || 'unknown'}`;
                
                // Check if badge doesn't already exist
                if (!document.getElementById('hardcoded-user-badge')) {
                    document.body.appendChild(badge);
                }
            }
        } catch (error) {
            console.error('Error checking hardcoded user:', error);
        }
    }
})();
