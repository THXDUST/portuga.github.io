<?php
/**
 * OAuth Callback Handler
 * Handles OAuth callbacks from Google, Facebook, and Instagram
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';

// Get provider and code from URL
$provider = $_GET['provider'] ?? '';
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($provider) || empty($code)) {
    die('Invalid OAuth callback. Missing provider or authorization code.');
}

// Validate provider
$allowedProviders = ['google', 'facebook', 'instagram'];
if (!in_array($provider, $allowedProviders)) {
    die('Invalid OAuth provider.');
}

// Verify state parameter to prevent CSRF attacks
startSecureSession();
if (!isset($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== $state) {
    die('Invalid state parameter. Possible CSRF attack.');
}

try {
    // Exchange authorization code for access token
    $accessToken = getOAuthAccessToken($provider, $code);
    
    // Get user information from OAuth provider
    $oauthUser = getOAuthUserInfo($provider, $accessToken);
    
    if (!$oauthUser || !isset($oauthUser['email'])) {
        throw new Exception('Failed to retrieve user information from ' . ucfirst($provider));
    }
    
    $pdo = getDBConnection();
    
    // Check if user already exists with this OAuth provider
    $stmt = $pdo->prepare("
        SELECT id, is_active 
        FROM users 
        WHERE oauth_provider = ? AND oauth_id = ?
    ");
    $stmt->execute([$provider, $oauthUser['id']]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        // User exists, check if active
        if (!$existingUser['is_active']) {
            die('Your account has been deactivated. Please contact support.');
        }
        
        $userId = $existingUser['id'];
        
    } else {
        // Check if email already exists with different provider
        $stmt = $pdo->prepare("SELECT id, oauth_provider FROM users WHERE email = ?");
        $stmt->execute([$oauthUser['email']]);
        $emailUser = $stmt->fetch();
        
        if ($emailUser && $emailUser['oauth_provider'] !== 'none') {
            die('An account with this email already exists using ' . ucfirst($emailUser['oauth_provider']) . ' login.');
        } else if ($emailUser) {
            die('An account with this email already exists. Please login with your email and password.');
        }
        
        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, oauth_provider, oauth_id, email_verified)
            VALUES (?, ?, ?, ?, TRUE)
        ");
        $stmt->execute([
            $oauthUser['name'],
            $oauthUser['email'],
            $provider,
            $oauthUser['id']
        ]);
        
        $userId = $pdo->lastInsertId();
    }
    
    // Create session
    createSession($userId, true);
    
    // Redirect to dashboard or home page
    header('Location: /index.html?login=success');
    exit();
    
} catch (Exception $e) {
    error_log("OAuth callback failed: " . $e->getMessage());
    die('OAuth authentication failed: ' . $e->getMessage());
}

/**
 * Exchange authorization code for access token
 */
function getOAuthAccessToken($provider, $code) {
    $clientId = getenv(strtoupper($provider) . '_CLIENT_ID');
    $clientSecret = getenv(strtoupper($provider) . '_CLIENT_SECRET');
    $redirectUri = getenv(strtoupper($provider) . '_REDIRECT_URI');
    
    if (!$clientId || !$clientSecret) {
        throw new Exception('OAuth credentials not configured for ' . $provider);
    }
    
    $tokenUrl = '';
    $params = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    switch ($provider) {
        case 'google':
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            break;
        case 'facebook':
            $tokenUrl = 'https://graph.facebook.com/v12.0/oauth/access_token';
            break;
        case 'instagram':
            $tokenUrl = 'https://api.instagram.com/oauth/access_token';
            break;
    }
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to get access token from ' . $provider);
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Get user information from OAuth provider
 */
function getOAuthUserInfo($provider, $accessToken) {
    $userInfoUrl = '';
    
    switch ($provider) {
        case 'google':
            $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
            break;
        case 'facebook':
            $userInfoUrl = 'https://graph.facebook.com/me?fields=id,name,email';
            break;
        case 'instagram':
            $userInfoUrl = 'https://graph.instagram.com/me?fields=id,username';
            break;
    }
    
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Failed to get user info from ' . $provider);
    }
    
    $data = json_decode($response, true);
    
    // Normalize user data
    return [
        'id' => $data['id'],
        'name' => $data['name'] ?? $data['username'] ?? 'User',
        'email' => $data['email'] ?? null
    ];
}
