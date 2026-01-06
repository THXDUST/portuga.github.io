<?php
/**
 * Dish Image Server
 * Serves dish images from database BLOB or returns default.png fallback
 */

// Disable any error display to prevent corrupting image output
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/database.php';

// Get database connection
function getDBConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'portuga_db';
    $username = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '5432';
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

// Get dish ID from query parameter
$dishId = $_GET['id'] ?? null;

if (!$dishId) {
    // No ID provided, serve default image
    serveDefaultImage();
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        serveDefaultImage();
        exit;
    }
    
    // Query for image data
    $stmt = $pdo->prepare("
        SELECT image_data, image_mime_type, image_url 
        FROM menu_items 
        WHERE id = ?
    ");
    $stmt->execute([$dishId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        // Item not found, serve default
        serveDefaultImage();
        exit;
    }
    
    // Priority: 1) BLOB data, 2) image_url (legacy), 3) default
    // Check if image_data exists and is not empty (trim whitespace for safety)
    if (!empty($item['image_data']) && trim($item['image_data']) !== '') {
        // Serve from BLOB - decode Base64 first
        $imageData = base64_decode($item['image_data'], true); // Strict mode
        
        // Validate decoded data is not empty and decode was successful
        if ($imageData !== false && strlen($imageData) > 0) {
            $mimeType = $item['image_mime_type'] ?: 'image/jpeg';
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . strlen($imageData));
            header('Cache-Control: public, max-age=86400'); // Cache for 1 day
            echo $imageData;
        } else {
            // Invalid base64 or empty data, fall back to default
            serveDefaultImage();
        }
    } elseif (!empty($item['image_url']) && trim($item['image_url']) !== '') {
        // Redirect to legacy image URL
        header('Location: ' . $item['image_url']);
    } else {
        // No image, serve default
        serveDefaultImage();
    }
    
} catch (Exception $e) {
    serveDefaultImage();
}

function serveDefaultImage() {
    $defaultImagePath = __DIR__ . '/../images/default.png';
    
    // Check if default.png exists
    if (file_exists($defaultImagePath)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($defaultImagePath));
        header('Cache-Control: public, max-age=86400');
        readfile($defaultImagePath);
    } else {
        // Generate a simple placeholder image if default.png doesn't exist
        header('Content-Type: image/png');
        $img = imagecreatetruecolor(300, 300);
        $bg = imagecolorallocate($img, 240, 240, 240);
        $fg = imagecolorallocate($img, 100, 100, 100);
        imagefilledrectangle($img, 0, 0, 300, 300, $bg);
        $text = 'No Image';
        imagestring($img, 5, 110, 145, $text, $fg);
        imagepng($img);
        imagedestroy($img);
    }
}
