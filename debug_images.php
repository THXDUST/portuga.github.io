<?php
/**
 * Debug script to check menu items and images in database
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Debug: Menu Items & Images</h1>\n";
echo "<pre>\n";

try {
    // Get database connection
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'portuga_db';
    $username = getenv('DB_USER') ?: 'postgres';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '5432';
    
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connected successfully\n\n";
    
    // Check menu_items table
    echo "=== MENU ITEMS TABLE ===\n";
    $stmt = $pdo->query("
        SELECT id, group_id, name, price, 
               CASE WHEN image_data IS NOT NULL THEN 'YES' ELSE 'NO' END as has_image_data,
               CASE WHEN image_data IS NOT NULL THEN LENGTH(image_data) ELSE 0 END as image_data_size,
               image_mime_type,
               CASE WHEN image_url IS NOT NULL THEN 'YES' ELSE 'NO' END as has_image_url,
               is_available
        FROM menu_items
        ORDER BY id
    ");
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo "⚠️  NO ITEMS FOUND IN DATABASE\n";
        echo "The menu_items table is empty!\n";
    } else {
        echo "Found " . count($items) . " items:\n\n";
        foreach ($items as $item) {
            echo "ID: {$item['id']}\n";
            echo "  Name: {$item['name']}\n";
            echo "  Group ID: {$item['group_id']}\n";
            echo "  Price: R$ {$item['price']}\n";
            echo "  Has image_data: {$item['has_image_data']}";
            if ($item['has_image_data'] === 'YES') {
                echo " ({$item['image_data_size']} bytes)";
                // Check if it's base64 - use consistent sample size
                $sampleSize = min(50, $item['image_data_size']);
                $stmt2 = $pdo->prepare("SELECT SUBSTRING(image_data, 1, ?) as sample FROM menu_items WHERE id = ?");
                $stmt2->execute([$sampleSize, $item['id']]);
                $sample = $stmt2->fetch(PDO::FETCH_ASSOC);
                $isBase64 = preg_match('/^[A-Za-z0-9+\/]+=*$/', $sample['sample']);
                echo $isBase64 ? " [Base64 ✓]" : " [NOT Base64 ⚠️]";
            }
            echo "\n";
            echo "  MIME type: " . ($item['image_mime_type'] ?: 'NULL') . "\n";
            echo "  Has image_url: {$item['has_image_url']}\n";
            echo "  Available: " . ($item['is_available'] ? 'YES' : 'NO') . "\n";
            echo "\n";
        }
    }
    
    echo "\n=== MENU GROUPS TABLE ===\n";
    $stmt = $pdo->query("
        SELECT id, name, parent_id, is_active
        FROM menu_groups
        ORDER BY parent_id NULLS FIRST, id
    ");
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($groups)) {
        echo "⚠️  NO GROUPS FOUND IN DATABASE\n";
    } else {
        echo "Found " . count($groups) . " groups:\n\n";
        foreach ($groups as $group) {
            $indent = $group['parent_id'] ? "  ↳ " : "";
            echo "{$indent}ID: {$group['id']} - {$group['name']}";
            if ($group['parent_id']) {
                echo " (parent: {$group['parent_id']})";
            }
            echo " [" . ($group['is_active'] ? 'Active' : 'Inactive') . "]\n";
        }
    }
    
    echo "\n=== ID 1 SPECIFIC CHECK ===\n";
    
    // Check if ID 1 exists in menu_items
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = 1");
    $stmt->execute();
    $item1 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item1) {
        echo "✅ Item ID=1 EXISTS in menu_items\n";
        echo "   Name: {$item1['name']}\n";
        echo "   Group ID: {$item1['group_id']}\n";
        if ($item1['image_data']) {
            echo "   Image data: EXISTS (" . strlen($item1['image_data']) . " bytes)\n";
            echo "   MIME type: {$item1['image_mime_type']}\n";
            
            // Try to decode and check
            $decoded = base64_decode($item1['image_data'], true);
            if ($decoded !== false) {
                echo "   Base64 decode: SUCCESS (" . strlen($decoded) . " bytes)\n";
                // Check if it's a valid image
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_buffer($finfo, $decoded);
                finfo_close($finfo);
                echo "   Detected MIME: $mimeType\n";
            } else {
                echo "   ⚠️  Base64 decode: FAILED (data is not valid Base64)\n";
            }
        } else {
            echo "   ⚠️  NO image_data (image_data is NULL or empty)\n";
        }
    } else {
        echo "❌ Item ID=1 DOES NOT EXIST in menu_items\n";
        echo "   The dish-image.php endpoint will fall back to default.png\n";
    }
    
    // Check if ID 1 exists in menu_groups
    $stmt = $pdo->prepare("SELECT * FROM menu_groups WHERE id = 1");
    $stmt->execute();
    $group1 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($group1) {
        echo "\nℹ️  Group ID=1 EXISTS: {$group1['name']}\n";
        echo "   (This is a GROUP, not an ITEM - cannot have images)\n";
    }
    
    echo "\n=== TEST dish-image.php ENDPOINT ===\n";
    echo "To test: Open in browser: /api/dish-image.php?id=1\n";
    echo "Expected: Should show the image from menu_items.id=1\n";
    echo "If fallback: Item doesn't exist OR image_data is NULL/invalid\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
