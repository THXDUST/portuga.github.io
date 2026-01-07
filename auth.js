/**
 * Authentication JavaScript
 * Handles login, registration, and OAuth flows
 */

// API Base URL
const API_BASE = window.location.origin;

// Get CSRF token on page load
let csrfToken = '';

document.addEventListener('DOMContentLoaded', async () => {
    await fetchCSRFToken();
    initializeAuthForms();
    checkLoginStatus();
    displayURLMessages();
});

/**
 * Fetch CSRF token from server
 */
async function fetchCSRFToken() {
    try {
        const response = await fetch(`${API_BASE}/api/auth/get-csrf-token.php`);
        const data = await response.json();
        csrfToken = data.token;
    } catch (error) {
        console.error('Failed to fetch CSRF token:', error);
        // Generate a temporary token on client side as fallback
        csrfToken = generateTempToken();
    }
}

/**
 * Generate temporary token (fallback)
 */
function generateTempToken() {
    return Array.from(crypto.getRandomValues(new Uint8Array(32)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Initialize authentication forms
 */
function initializeAuthForms() {
    // IGNORAR se estiver na página admin
    if (window.location.pathname.includes('admin.html')) {
        console.log('⚠️ [AUTH] Skipping auth forms on admin page');
        return;
    }
    
    // Registration form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistration);
        
        // Real-time password strength check
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', checkPasswordStrength);
        }
        
        // Real-time password match check
        const confirmPasswordInput = document.getElementById('confirm-password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
    }
    
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // OAuth buttons
    initializeOAuthButtons();
}

/**
 * Handle registration form submission
 */
async function handleRegistration(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('.auth-btn');
    const fullName = document.getElementById('full-name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    const termsAccepted = document.getElementById('terms').checked;
    
    // Clear previous errors
    clearErrors();
    
    // Validate inputs
    if (!fullName) {
        showError('full-name', 'Full name is required');
        return;
    }
    
    if (!email) {
        showError('email', 'Email is required');
        return;
    }
    
    if (!validateEmail(email)) {
        showError('email', 'Please enter a valid email address');
        return;
    }
    
    if (!password) {
        showError('password', 'Password is required');
        return;
    }
    
    if (password !== confirmPassword) {
        showError('confirm-password', 'Passwords do not match');
        return;
    }
    
    if (!termsAccepted) {
        showAlert('You must accept the terms of use', 'error');
        return;
    }
    
    // Show loading state
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    try {
        const response = await fetch(`${API_BASE}/api/auth/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                full_name: fullName,
                email: email,
                password: password,
                confirm_password: confirmPassword,
                terms_accepted: termsAccepted,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            form.reset();
            
            // Redirect to login page after 2 seconds
            setTimeout(() => {
                window.location.href = '/login.html';
            }, 2000);
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showAlert('An error occurred during registration. Please try again.', 'error');
    } finally {
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
}

/**
 * Handle login form submission
 */
async function handleLogin(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('.auth-btn');
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const rememberMe = document.getElementById('remember-me')?.checked || false;
    
    // Clear previous errors
    clearErrors();
    
    // Validate inputs
    if (!email) {
        showError('email', 'Email is required');
        return;
    }
    
    if (!password) {
        showError('password', 'Password is required');
        return;
    }
    
    // Show loading state
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    try {
        const response = await fetch(`${API_BASE}/api/auth/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: email,
                password: password,
                remember_me: rememberMe,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store COMPLETE user data in localStorage
            localStorage.setItem('user', JSON.stringify(data.user));
            localStorage.setItem('session_token', data.session_token);
            
            // Store full user info for immediate access
            // This ensures userInfo is always available for authenticated users
            localStorage.setItem('userInfo', JSON.stringify(data.user));
            
            showAlert(data.message, 'success');
            
            // Redirect based on user type (from API) or to default page after 1 second
            const redirectUrl = data.redirect_url || '/index.html';
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1000);
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAlert('An error occurred during login. Please try again.', 'error');
    } finally {
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
}

/**
 * Check password strength
 */
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthIndicator = document.getElementById('password-strength');
    const strengthFill = document.querySelector('.strength-fill');
    const strengthText = document.querySelector('.strength-text');
    
    if (!password) {
        strengthIndicator.classList.remove('show');
        return;
    }
    
    strengthIndicator.classList.add('show');
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) strength++;
    else feedback.push('at least 8 characters');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('an uppercase letter');
    
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('a lowercase letter');
    
    // Number check
    if (/[0-9]/.test(password)) strength++;
    else feedback.push('a number');
    
    // Update UI
    strengthFill.className = 'strength-fill';
    
    if (strength <= 2) {
        strengthFill.classList.add('weak');
        strengthText.textContent = 'Weak password. Add: ' + feedback.join(', ');
        strengthText.style.color = '#dc3545';
    } else if (strength === 3) {
        strengthFill.classList.add('medium');
        strengthText.textContent = 'Medium password. Add: ' + feedback.join(', ');
        strengthText.style.color = '#ffc107';
    } else {
        strengthFill.classList.add('strong');
        strengthText.textContent = 'Strong password!';
        strengthText.style.color = '#28a745';
    }
}

/**
 * Check if passwords match
 */
function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    if (!confirmPassword) return;
    
    if (password === confirmPassword) {
        document.getElementById('confirm-password').classList.remove('error');
        document.getElementById('confirm-password').classList.add('success');
        hideError('confirm-password');
    } else {
        document.getElementById('confirm-password').classList.remove('success');
        document.getElementById('confirm-password').classList.add('error');
        showError('confirm-password', 'Passwords do not match');
    }
}

/**
 * Initialize OAuth buttons
 */
function initializeOAuthButtons() {
    const oauthButtons = document.querySelectorAll('.oauth-btn');
    
    oauthButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const provider = btn.dataset.provider;
            initiateOAuth(provider);
        });
    });
}

/**
 * Initiate OAuth flow
 */
function initiateOAuth(provider) {
    const clientIds = {
        google: '151705503793-58conb3193si0a8njile4bjpdn77nps6.apps.googleusercontent.com',
        facebook: 'YOUR_FACEBOOK_APP_ID',
        instagram: 'YOUR_INSTAGRAM_CLIENT_ID'
    };
    
    const redirectUri = `${API_BASE}/api/auth/oauth-callback.php?provider=${provider}`;
    const state = generateTempToken();
    
    // Store state in localStorage for verification
    localStorage.setItem('oauth_state', state);
    
    let authUrl = '';
    
    switch (provider) {
        case 'google':
            authUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
                `client_id=${clientIds.google}&` +
                `redirect_uri=${encodeURIComponent(redirectUri)}&` +
                `response_type=code&` +
                `scope=email profile&` +
                `state=${state}`;
            break;
        case 'facebook':
            authUrl = `https://www.facebook.com/v12.0/dialog/oauth?` +
                `client_id=${clientIds.facebook}&` +
                `redirect_uri=${encodeURIComponent(redirectUri)}&` +
                `scope=email&` +
                `state=${state}`;
            break;
        case 'instagram':
            authUrl = `https://api.instagram.com/oauth/authorize?` +
                `client_id=${clientIds.instagram}&` +
                `redirect_uri=${encodeURIComponent(redirectUri)}&` +
                `scope=user_profile,user_media&` +
                `response_type=code&` +
                `state=${state}`;
            break;
    }
    
    if (authUrl) {
        window.location.href = authUrl;
    }
}

/**
 * Check login status
 */
function checkLoginStatus() {
    const user = localStorage.getItem('user');
    
    if (user && window.location.pathname.includes('login.html')) {
        // Already logged in, redirect to home
        window.location.href = '/index.html';
    }
}

/**
 * Display messages from URL parameters
 */
function displayURLMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('verified') === 'success') {
        showAlert('Email verified successfully! You can now log in.', 'success');
    } else if (urlParams.get('verified') === 'already') {
        showAlert('Your email is already verified. Please log in.', 'info');
    } else if (urlParams.get('login') === 'success') {
        showAlert('Login successful!', 'success');
    }
}

/**
 * Show error message for a field
 */
function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElement = field.parentElement.querySelector('.error-message');
    
    field.classList.add('error');
    
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
}

/**
 * Hide error message for a field
 */
function hideError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = field.parentElement.querySelector('.error-message');
    
    field.classList.remove('error');
    
    if (errorElement) {
        errorElement.classList.remove('show');
    }
}

/**
 * Clear all errors
 */
function clearErrors() {
    document.querySelectorAll('.error-message').forEach(el => {
        el.classList.remove('show');
    });
    
    document.querySelectorAll('input').forEach(input => {
        input.classList.remove('error');
    });
    
    const alertContainer = document.getElementById('alert-container');
    if (alertContainer) {
        alertContainer.innerHTML = '';
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    let alertContainer = document.getElementById('alert-container');
    
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alert-container';
        const authCard = document.querySelector('.auth-card');
        if (authCard) {
            authCard.insertBefore(alertContainer, authCard.firstChild);
        }
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alert);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

/**
 * Validate email format
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Logout function
 */
async function logout() {
    try {
        await fetch(`${API_BASE}/api/auth/logout.php`, {
            method: 'POST'
        });
    } catch (error) {
        console.error('Logout error:', error);
    } finally {
        localStorage.removeItem('user');
        localStorage.removeItem('session_token');
        window.location.href = '/login.html';
    }
}

// Export logout function for use in other scripts
window.logout = logout;

/**
 * Check if user is currently logged in
 * @returns {boolean} True if user is logged in
 */
function isUserLoggedIn() {
    const user = localStorage.getItem('user');
    const sessionToken = localStorage.getItem('session_token');
    return !!(user && sessionToken);
}

/**
 * Get current logged in user data from localStorage
 * @returns {object|null} User data or null if not logged in
 */
function getCurrentUser() {
    const userStr = localStorage.getItem('user');
    if (!userStr) return null;
    try {
        return JSON.parse(userStr);
    } catch (error) {
        console.error('Error parsing user data:', error);
        return null;
    }
}

/**
 * Fetch fresh user info from server including permissions
 * @returns {Promise<object|null>} User data with permissions or null
 */
async function fetchUserInfo() {
    try {
        const response = await fetch(`${API_BASE}/api/auth/get-user-info.php`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            // Update localStorage with fresh data
            localStorage.setItem('userInfo', JSON.stringify(data.data));
            return data.data;
        }
        
        return null;
    } catch (error) {
        console.error('Error fetching user info:', error);
        return null;
    }
}

/**
 * Check if user has a specific permission
 * @param {string} permissionName Permission name to check
 * @returns {boolean} True if user has permission
 */
function hasPermission(permissionName) {
    const userInfoStr = localStorage.getItem('userInfo');
    if (!userInfoStr) return false;
    
    try {
        const userInfo = JSON.parse(userInfoStr);
        return Boolean(userInfo.permissionMap && userInfo.permissionMap[permissionName]);
    } catch (error) {
        console.error('Error checking permission:', error);
        return false;
    }
}

/**
 * Check if user has admin access
 * @returns {boolean} True if user has admin panel access
 */
function hasAdminAccess() {
    const userInfoStr = localStorage.getItem('userInfo');
    if (!userInfoStr) return false;
    
    try {
        const userInfo = JSON.parse(userInfoStr);
        return Boolean(userInfo.hasAdminAccess);
    } catch (error) {
        console.error('Error checking admin access:', error);
        return false;
    }
}

/**
 * Update navigation UI based on login status
 */
async function updateNavigationUI() {
    // Check if user is logged in
    if (!isUserLoggedIn()) {
        // Show login button, hide logout/profile
        updateNavMenuForGuest();
        return;
    }
    
    // Fetch fresh user info
    const userInfo = await fetchUserInfo();
    
    if (!userInfo) {
        // Session expired or error, treat as guest
        localStorage.removeItem('user');
        localStorage.removeItem('session_token');
        localStorage.removeItem('userInfo');
        updateNavMenuForGuest();
        return;
    }
    
    // User is logged in, update UI
    updateNavMenuForUser(userInfo);
}

/**
 * Update navigation menu for guest users
 */
function updateNavMenuForGuest() {
    const navMenus = document.querySelectorAll('#nav-menu, #admin-nav');
    
    navMenus.forEach(navMenu => {
        // Remove any existing user-specific items
        const existingUserItems = navMenu.querySelectorAll('.user-menu-item, .admin-menu-item');
        existingUserItems.forEach(item => item.remove());
        
        // Ensure login link exists
        const loginLink = navMenu.querySelector('a[href="login.html"]');
        if (!loginLink) {
            const li = document.createElement('li');
            li.innerHTML = '<a href="login.html">Login</a>';
            navMenu.appendChild(li);
        }
    });
}

/**
 * Update navigation menu for logged in users
 */
function updateNavMenuForUser(userInfo) {
    const navMenus = document.querySelectorAll('#nav-menu, #admin-nav');
    
    navMenus.forEach(navMenu => {
        // Remove login link
        const loginLink = navMenu.querySelector('a[href="login.html"]');
        if (loginLink) {
            loginLink.parentElement.remove();
        }
        
        // Remove any existing user items to avoid duplicates
        const existingUserItems = navMenu.querySelectorAll('.user-menu-item, .admin-menu-item');
        existingUserItems.forEach(item => item.remove());
        
        // Add "Meus Pedidos" link
        const pedidosLi = document.createElement('li');
        pedidosLi.className = 'user-menu-item';
        pedidosLi.innerHTML = '<a href="pedidos.html">Meus Pedidos</a>';
        navMenu.appendChild(pedidosLi);
        
        // Add "Meu Perfil" link
        const perfilLi = document.createElement('li');
        perfilLi.className = 'user-menu-item';
        perfilLi.innerHTML = '<a href="perfil.html">Meu Perfil</a>';
        navMenu.appendChild(perfilLi);
        
        // Add user profile/logout button
        const userLi = document.createElement('li');
        userLi.className = 'user-menu-item';
        // Extract first name from full name
        const firstName = userInfo.full_name ? userInfo.full_name.split(' ')[0] : 'Usuário';
        userLi.innerHTML = `
            <span style="color: #e8c13f; margin-right: 10px;">${firstName}</span>
            <button onclick="logout()" style="background: transparent; border: 1px solid #e8c13f; color: #e8c13f; padding: 5px 15px; border-radius: 5px; cursor: pointer;">Sair</button>
        `;
        navMenu.appendChild(userLi);
        
        // Add admin link if user has admin access
        if (userInfo.hasAdminAccess) {
            // Check if admin link already exists (if not commented out)
            const existingAdminLink = navMenu.querySelector('a[href="admin.html"]');
            if (!existingAdminLink) {
                const adminLi = document.createElement('li');
                adminLi.className = 'admin-menu-item';
                adminLi.innerHTML = '<a href="admin.html">Admin</a>';
                // Insert before user profile item
                navMenu.insertBefore(adminLi, userLi);
            }
        } else {
            // Remove admin link if user doesn't have access
            const adminLinks = navMenu.querySelectorAll('a[href="admin.html"]');
            adminLinks.forEach(link => link.parentElement.remove());
        }
    });
}

// Export functions for global use
window.isUserLoggedIn = isUserLoggedIn;
window.getCurrentUser = getCurrentUser;
window.fetchUserInfo = fetchUserInfo;
window.hasPermission = hasPermission;
window.hasAdminAccess = hasAdminAccess;
window.updateNavigationUI = updateNavigationUI;
