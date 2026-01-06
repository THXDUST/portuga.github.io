<?php
/**
 * Test script to verify menu_items table structure
 * Checks if all required columns exist for menu item operations
 * 
 * WARNING: This is a diagnostic tool for troubleshooting only.
 * DO NOT deploy this script to production servers.
 * Delete this file after completing diagnosis.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$results = [
    'success' => true,
    'table_exists' => false,
    'columns' => [],
    'missing_columns' => [],
    'errors' => []
];

try {
    $conn = getDBConnection();
    
    // Check if menu_items table exists
    $stmt = $conn->query("
        SELECT EXISTS (
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'menu_items'
        ) as table_exists
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['table_exists'] = (bool)$result['table_exists'];
    
    if (!$results['table_exists']) {
        $results['success'] = false;
        $results['errors'][] = 'Table menu_items does not exist';
        echo json_encode($results, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Get all columns in menu_items table
    $stmt = $conn->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = 'public' 
        AND table_name = 'menu_items'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnNames = array_column($columns, 'column_name');
    $results['columns'] = $columns;
    
    // Required columns for menu item operations
    $requiredColumns = [
        'id',
        'group_id',
        'name',
        'description',
        'price',
        'image_url',
        'ingredients',
        'is_available',
        'delivery_enabled',
        'display_order',
        'created_at',
        'updated_at',
        'image_data',
        'image_mime_type'
    ];
    
    // Check which columns are missing
    foreach ($requiredColumns as $required) {
        if (!in_array($required, $columnNames)) {
            $results['missing_columns'][] = $required;
            $results['success'] = false;
        }
    }
    
    if (!empty($results['missing_columns'])) {
        $results['errors'][] = 'Missing required columns: ' . implode(', ', $results['missing_columns']);
        $results['recommendation'] = 'Run migrations using /api/admin/run_migrations.php';
    }
    
} catch (PDOException $e) {
    $results['success'] = false;
    $results['errors'][] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $results['success'] = false;
    $results['errors'][] = 'Error: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
