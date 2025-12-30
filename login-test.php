<?php
/**
 * Debug script for login issues
 * Place this file in the root directory and access it via browser
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

// Test credentials - CHANGE THESE to your test account
$testEmail = 'cintiamaru20@gmail.com';  // ← MUDE AQUI
$testPassword = 'vinteVINTE20';          // ← MUDE AQUI

echo "<h2>Login Debug Tool</h2>";
echo "<hr>";

// 1. Check ENCRYPTION_KEY
echo "<h3>1. ENCRYPTION_KEY Check</h3>";
echo "ENCRYPTION_KEY: " . (defined('ENCRYPTION_KEY') ? '✓ Defined' : '✗ NOT DEFINED') . "<br>";
echo "Value: " .  ENCRYPTION_KEY . "<br>";
echo "<hr>";

// 2. Check email processing
echo "<h3>2. Email Processing</h3>";
$emailTrimmed = trim($testEmail);
$emailSanitized = sanitizeInput($testEmail);
echo "Original: " . htmlspecialchars($testEmail) . "<br>";
echo "Trimmed only: " . htmlspecialchars($emailTrimmed) . "<br>";
echo "Sanitized:  " . htmlspecialchars($emailSanitized) . "<br>";
echo "Are they equal? " . ($emailTrimmed === $emailSanitized ?  '✓ YES' : '✗ NO') . "<br>";
echo "<hr>";

// 3. Check database entry
echo "<h3>3. Database Check</h3>";
try {
    $pdo = getDBConnection();
    
    // Search with trimmed email
    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$emailTrimmed]);
    $userTrimmed = $stmt->fetch();
    
    // Search with sanitized email
    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$emailSanitized]);
    $userSanitized = $stmt->fetch();
    
    if ($userTrimmed) {
        echo "✓ User found with TRIMMED email<br>";
        echo "User ID: " . $userTrimmed['id'] . "<br>";
        echo "Email in DB: " . htmlspecialchars($userTrimmed['email']) . "<br>";
        echo "Password hash:  " . substr($userTrimmed['password_hash'], 0, 50) . "...<br>";
    } else {
        echo "✗ User NOT found with TRIMMED email<br>";
    }
    
    echo "<br>";
    
    if ($userSanitized) {
        echo "✓ User found with SANITIZED email<br>";
        echo "User ID: " . $userSanitized['id'] . "<br>";
        echo "Email in DB: " . htmlspecialchars($userSanitized['email']) . "<br>";
    } else {
        echo "✗ User NOT found with SANITIZED email<br>";
    }
    
    echo "<hr>";
    
    // 4. Test password verification
    if ($userTrimmed || $userSanitized) {
        echo "<h3>4. Password Verification Test</h3>";
        $user = $userTrimmed ?: $userSanitized;
        $storedHash = $user['password_hash'];
        $emailInDB = $user['email'];
        
        echo "<strong>Testing with email from DB:  " . htmlspecialchars($emailInDB) . "</strong><br><br>";
        
        // Test 1: Verify with email from DB
        $result1 = verifyPassword($testPassword, $storedHash, $emailInDB);
        echo "Test 1 - With email from DB: " . ($result1 ? '✓ SUCCESS' : '✗ FAILED') . "<br>";
        
        // Test 2: Verify with trimmed email
        $result2 = verifyPassword($testPassword, $storedHash, $emailTrimmed);
        echo "Test 2 - With trimmed email: " . ($result2 ?  '✓ SUCCESS' : '✗ FAILED') . "<br>";
        
        // Test 3: Verify with sanitized email
        $result3 = verifyPassword($testPassword, $storedHash, $emailSanitized);
        echo "Test 3 - With sanitized email: " . ($result3 ?  '✓ SUCCESS' : '✗ FAILED') . "<br>";
        
        echo "<hr>";
        
        // 5. Manual hash recreation
        echo "<h3>5. Manual Hash Recreation</h3>";
        
        // Check hash format
        if (strpos($storedHash, ': ') !== false) {
            list($bcryptHash, $storedHmac) = explode(':', $storedHash, 2);
            echo "✓ Hash format is correct (contains ':')<br>";
            echo "Bcrypt part: " . substr($bcryptHash, 0, 30) . "...<br>";
            echo "HMAC part: " . substr($storedHmac, 0, 30) . "...<br><br>";
            
            // Test bcrypt verification
            $bcryptValid = password_verify($testPassword, $bcryptHash);
            echo "Bcrypt verification: " . ($bcryptValid ? '✓ VALID' : '✗ INVALID') . "<br>";
            
            if ($bcryptValid) {
                // Test HMAC with different email variations
                echo "<br><strong>HMAC Tests:</strong><br>";
                
                $hmac1 = hash_hmac('sha256', $bcryptHash .  $emailInDB, ENCRYPTION_KEY);
                $match1 = hash_equals($storedHmac, $hmac1);
                echo "With DB email: " . ($match1 ? '✓ MATCH' : '✗ NO MATCH') . "<br>";
                
                $hmac2 = hash_hmac('sha256', $bcryptHash . $emailTrimmed, ENCRYPTION_KEY);
                $match2 = hash_equals($storedHmac, $hmac2);
                echo "With trimmed email:  " . ($match2 ? '✓ MATCH' : '✗ NO MATCH') . "<br>";
                
                $hmac3 = hash_hmac('sha256', $bcryptHash . $emailSanitized, ENCRYPTION_KEY);
                $match3 = hash_equals($storedHmac, $hmac3);
                echo "With sanitized email: " . ($match3 ? '✓ MATCH' : '✗ NO MATCH') . "<br>";
            }
        } else {
            echo "✗ Hash format is INVALID (missing ':')<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "<hr>";
echo "<p><strong>Instructions:</strong> Copy the output above and share it to help diagnose the issue.</p>";
?>