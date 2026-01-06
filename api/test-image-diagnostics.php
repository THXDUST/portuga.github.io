<?php
/**
 * Image Diagnostics Endpoint
 * Tests the complete image upload/retrieval flow
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => [
        'connected' => false,
        'error' => null
    ],
    'schema' => [
        'menu_items_exists' => false,
        'image_data_column_exists' => false,
        'image_data_column_type' => null,
        'image_mime_type_column_exists' => false
    ],
    'data' => [
        'total_items' => 0,
        'items_with_images' => 0,
        'items_list' => []
    ],
    'test_item' => null,
    'recommendations' => []
];

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        $diagnostics['database']['error'] = 'Failed to connect to database';
        $diagnostics['recommendations'][] = 'Check database credentials in environment variables';
        echo json_encode($diagnostics, JSON_PRETTY_PRINT);
        exit;
    }
    
    $diagnostics['database']['connected'] = true;
    
    // Check if menu_items table exists
    $stmt = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'menu_items'
        ) as exists
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $diagnostics['schema']['menu_items_exists'] = (bool)$result['exists'];
    
    if (!$diagnostics['schema']['menu_items_exists']) {
        $diagnostics['recommendations'][] = 'Run database migrations: access /run_migrations.html';
        $diagnostics['recommendations'][] = 'Or manually run: database/setup.sql';
        echo json_encode($diagnostics, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Check if image columns exist and get their types
    $stmt = $pdo->query("
        SELECT column_name, data_type, character_maximum_length 
        FROM information_schema.columns 
        WHERE table_name = 'menu_items' 
        AND column_name IN ('image_data', 'image_mime_type')
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        if ($col['column_name'] === 'image_data') {
            $diagnostics['schema']['image_data_column_exists'] = true;
            $diagnostics['schema']['image_data_column_type'] = $col['data_type'];
            
            if ($col['data_type'] !== 'text') {
                $diagnostics['recommendations'][] = 
                    "WARNING: image_data column is type '{$col['data_type']}' but should be 'text'. " .
                    "Run migration: database/migrations/002_fix_image_data_column_type.sql";
            }
        } elseif ($col['column_name'] === 'image_mime_type') {
            $diagnostics['schema']['image_mime_type_column_exists'] = true;
        }
    }
    
    if (!$diagnostics['schema']['image_data_column_exists']) {
        $diagnostics['recommendations'][] = 
            'Image columns missing! Run migration: database/migrations/001_add_menu_item_image_columns.sql';
    }
    
    // Count items and items with images
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            COUNT(CASE WHEN image_data IS NOT NULL AND image_data != '' THEN 1 END) as items_with_images
        FROM menu_items
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    $diagnostics['data']['total_items'] = (int)$counts['total_items'];
    $diagnostics['data']['items_with_images'] = (int)$counts['items_with_images'];
    
    if ($diagnostics['data']['total_items'] === 0) {
        $diagnostics['recommendations'][] = 'No items in database. Create items via admin panel: /admin.html';
    }
    
    // Get list of all items with image status
    $stmt = $pdo->query("
        SELECT 
            id, 
            name, 
            group_id,
            CASE WHEN image_data IS NOT NULL AND image_data != '' THEN true ELSE false END as has_image_data,
            CASE WHEN image_data IS NOT NULL AND image_data != '' THEN LENGTH(image_data) ELSE 0 END as image_data_size,
            image_mime_type,
            CASE WHEN image_url IS NOT NULL AND image_url != '' THEN true ELSE false END as has_image_url,
            is_available
        FROM menu_items
        ORDER BY id
        LIMIT 20
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as &$item) {
        $item['id'] = (int)$item['id'];
        $item['group_id'] = (int)$item['group_id'];
        $item['has_image_data'] = (bool)$item['has_image_data'];
        $item['image_data_size'] = (int)$item['image_data_size'];
        $item['has_image_url'] = (bool)$item['has_image_url'];
        $item['is_available'] = (bool)$item['is_available'];
        
        // Validate base64 if image data exists
        if ($item['has_image_data'] && $item['image_data_size'] > 0) {
            $stmt2 = $pdo->prepare("SELECT SUBSTRING(image_data, 1, 100) as sample FROM menu_items WHERE id = ?");
            $stmt2->execute([$item['id']]);
            $sample = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            // Check if it looks like valid base64
            $isBase64 = preg_match('/^[A-Za-z0-9+\/]+=*$/', $sample['sample']);
            $item['image_data_valid_base64'] = $isBase64;
            
            if (!$isBase64) {
                $item['warning'] = 'Image data does not appear to be valid Base64';
            }
        }
    }
    
    $diagnostics['data']['items_list'] = $items;
    
    // Test specific item (ID from query param or 1)
    $testId = $_GET['test_id'] ?? 1;
    
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            group_id,
            CASE WHEN image_data IS NOT NULL AND image_data != '' THEN true ELSE false END as has_image_data,
            CASE WHEN image_data IS NOT NULL AND image_data != '' THEN LENGTH(image_data) ELSE 0 END as image_data_size,
            image_mime_type,
            image_url,
            is_available
        FROM menu_items
        WHERE id = ?
    ");
    $stmt->execute([$testId]);
    $testItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testItem) {
        $testItem['id'] = (int)$testItem['id'];
        $testItem['group_id'] = (int)$testItem['group_id'];
        $testItem['has_image_data'] = (bool)$testItem['has_image_data'];
        $testItem['image_data_size'] = (int)$testItem['image_data_size'];
        $testItem['is_available'] = (bool)$testItem['is_available'];
        
        // Try to decode the actual image data
        if ($testItem['has_image_data']) {
            $stmt2 = $pdo->prepare("SELECT image_data FROM menu_items WHERE id = ?");
            $stmt2->execute([$testId]);
            $imgData = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            $decoded = base64_decode($imgData['image_data'], true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $testItem['image_decode_success'] = true;
                $testItem['image_decoded_size'] = strlen($decoded);
                
                // Check if it's a valid image by looking at magic bytes
                $magic = bin2hex(substr($decoded, 0, 4));
                if (substr($magic, 0, 4) === 'ffd8') {
                    $testItem['image_format'] = 'JPEG';
                } elseif (substr($magic, 0, 8) === '89504e47') {
                    $testItem['image_format'] = 'PNG';
                } else {
                    $testItem['image_format'] = 'Unknown (magic: ' . $magic . ')';
                }
                
                $testItem['test_url'] = "/api/dish-image.php?id={$testId}";
                $testItem['expected_result'] = 'Should display the image';
            } else {
                $testItem['image_decode_success'] = false;
                $testItem['warning'] = 'Failed to decode image_data as Base64';
            }
        } else {
            $testItem['expected_result'] = 'Should fall back to default.png (no image_data)';
        }
        
        $diagnostics['test_item'] = $testItem;
    } else {
        $diagnostics['test_item'] = [
            'error' => "Item with ID={$testId} not found",
            'recommendation' => 'Check if the item exists or try a different ID'
        ];
    }
    
    // Generate recommendations based on findings
    if ($diagnostics['data']['total_items'] > 0 && $diagnostics['data']['items_with_images'] === 0) {
        $diagnostics['recommendations'][] = 
            'Items exist but none have images. Upload images via admin panel: /admin.html → Menu → Edit Item → Upload Image';
    }
    
    if (empty($diagnostics['recommendations'])) {
        $diagnostics['recommendations'][] = 'Everything looks good! If images still not showing, check browser console for errors.';
    }
    
} catch (PDOException $e) {
    $diagnostics['database']['error'] = $e->getMessage();
    $diagnostics['recommendations'][] = 'Database error: ' . $e->getMessage();
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
