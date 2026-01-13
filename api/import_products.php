<?php
/**
 * CSV Product Import Endpoint
 * 
 * Accepts multipart/form-data POST with a CSV file and imports products
 * into the database (menu_groups and menu_items tables).
 * 
 * Expected CSV format:
 * - Headers: '#', 'Descrição', 'Grupo', 'Custo', 'Venda', 'Ativo'
 * - Prices in Brazilian format: R$ 1.234,56
 * 
 * Authentication: Optional API key via IMPORT_API_KEY header or parameter
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, IMPORT_API_KEY');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Initialize log file
$logFile = __DIR__ . '/../logs/import_products.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Check API key if configured
$configuredApiKey = getenv('IMPORT_API_KEY');
if ($configuredApiKey) {
    $providedKey = $_SERVER['HTTP_IMPORT_API_KEY'] ?? $_POST['api_key'] ?? $_GET['api_key'] ?? null;
    
    if (!$providedKey || $providedKey !== $configuredApiKey) {
        logMessage("Unauthorized import attempt - invalid or missing API key");
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Valid API key required.']);
        exit;
    }
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = $_FILES['file']['error'] ?? 'No file uploaded';
    logMessage("Upload error: $error");
    http_response_code(400);
    echo json_encode(['error' => 'File upload failed', 'details' => $error]);
    exit;
}

$uploadedFile = $_FILES['file']['tmp_name'];
$filename = $_FILES['file']['name'];

logMessage("Starting import from file: $filename");

/**
 * Normalize Brazilian price format to decimal
 * Examples: "R$ 1.234,56" -> 1234.56, "45,00" -> 45.00
 */
function normalizePrice($value) {
    if (empty($value)) {
        return 0.00;
    }
    
    // Remove currency symbols and spaces
    $value = preg_replace('/[^\d,.-]/', '', $value);
    
    // Handle Brazilian format: 1.234,56 -> 1234.56
    if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
        // Has both . and , - assume Brazilian format
        $value = str_replace('.', '', $value); // Remove thousand separator
        $value = str_replace(',', '.', $value); // Convert decimal separator
    } elseif (strpos($value, ',') !== false) {
        // Only comma - assume it's decimal separator
        $value = str_replace(',', '.', $value);
    }
    
    return floatval($value);
}

/**
 * Parse CSV file and return rows
 */
function parseCSV($filePath) {
    $rows = [];
    $headers = [];
    
    if (($handle = fopen($filePath, 'r')) === false) {
        return ['error' => 'Could not open CSV file'];
    }
    
    // Read header row
    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        return ['error' => 'Could not read CSV headers'];
    }
    
    // Normalize header names (trim and lowercase for matching)
    $normalizedHeaders = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);
    
    // Map expected columns
    $columnMap = [
        'pdv_code' => -1,
        'name' => -1,
        'group' => -1,
        'cost' => -1,
        'price' => -1,
        'active' => -1
    ];
    
    foreach ($normalizedHeaders as $index => $header) {
        if (in_array($header, ['#', 'código', 'codigo', 'code'])) {
            $columnMap['pdv_code'] = $index;
        } elseif (in_array($header, ['descrição', 'descricao', 'description', 'nome', 'name'])) {
            $columnMap['name'] = $index;
        } elseif (in_array($header, ['grupo', 'group', 'categoria', 'category'])) {
            $columnMap['group'] = $index;
        } elseif (in_array($header, ['custo', 'cost'])) {
            $columnMap['cost'] = $index;
        } elseif (in_array($header, ['venda', 'preço', 'preco', 'price'])) {
            $columnMap['price'] = $index;
        } elseif (in_array($header, ['ativo', 'active', 'disponível', 'disponivel', 'available'])) {
            $columnMap['active'] = $index;
        }
    }
    
    // Validate required columns
    if ($columnMap['name'] === -1 || $columnMap['price'] === -1) {
        fclose($handle);
        return ['error' => 'CSV must have at least "Descrição" and "Venda" columns'];
    }
    
    // Read data rows
    $rowNum = 1;
    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }
        
        $row = [
            'row_number' => $rowNum,
            'pdv_code' => $columnMap['pdv_code'] >= 0 ? trim($data[$columnMap['pdv_code']] ?? '') : '',
            'name' => $columnMap['name'] >= 0 ? trim($data[$columnMap['name']] ?? '') : '',
            'group' => $columnMap['group'] >= 0 ? trim($data[$columnMap['group']] ?? '') : 'Geral',
            'cost' => $columnMap['cost'] >= 0 ? trim($data[$columnMap['cost']] ?? '0') : '0',
            'price' => $columnMap['price'] >= 0 ? trim($data[$columnMap['price']] ?? '0') : '0',
            'active' => $columnMap['active'] >= 0 ? trim($data[$columnMap['active']] ?? 'Sim') : 'Sim',
        ];
        
        // Skip rows without name
        if (empty($row['name'])) {
            continue;
        }
        
        $rows[] = $row;
    }
    
    fclose($handle);
    return $rows;
}

/**
 * Get or create menu group
 */
function getOrCreateGroup($pdo, $groupName) {
    // Search for existing group (case-insensitive)
    $stmt = $pdo->prepare("SELECT id FROM menu_groups WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$groupName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result['id'];
    }
    
    // Create new group
    $stmt = $pdo->prepare(
        "INSERT INTO menu_groups (name, display_order, is_active, created_at, updated_at) 
         VALUES (?, 0, TRUE, NOW(), NOW()) 
         RETURNING id"
    );
    $stmt->execute([$groupName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['id'];
}

/**
 * Upsert menu item
 */
function upsertMenuItem($pdo, $row, $groupId) {
    $pdvCode = empty($row['pdv_code']) ? null : intval($row['pdv_code']);
    $name = $row['name'];
    $cost = normalizePrice($row['cost']);
    $price = normalizePrice($row['price']);
    $isActive = in_array(strtolower(trim($row['active'])), ['sim', 'yes', 'true', '1', 'ativo']);
    
    // Try to find existing item by pdv_code or by name+group
    $existingItem = null;
    
    if ($pdvCode !== null) {
        $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE pdv_code = ? LIMIT 1");
        $stmt->execute([$pdvCode]);
        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$existingItem) {
        $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE LOWER(name) = LOWER(?) AND group_id = ? LIMIT 1");
        $stmt->execute([$name, $groupId]);
        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($existingItem) {
        // Update existing item
        $stmt = $pdo->prepare(
            "UPDATE menu_items 
             SET price = ?, cost = ?, is_available = ?, group_id = ?, pdv_code = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$price, $cost, $isActive, $groupId, $pdvCode, $existingItem['id']]);
        return ['action' => 'updated', 'id' => $existingItem['id']];
    } else {
        // Insert new item
        $stmt = $pdo->prepare(
            "INSERT INTO menu_items 
             (group_id, name, price, cost, pdv_code, is_available, display_order, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
             RETURNING id"
        );
        $stmt->execute([$groupId, $name, $price, $cost, $pdvCode, $isActive]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['action' => 'inserted', 'id' => $result['id']];
    }
}

// Main import logic
try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Parse CSV
    $rows = parseCSV($uploadedFile);
    
    if (isset($rows['error'])) {
        throw new Exception($rows['error']);
    }
    
    logMessage("Parsed " . count($rows) . " rows from CSV");
    
    // Begin transaction
    $pdo->beginTransaction();
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($rows as $row) {
        try {
            // Get or create group
            $groupId = getOrCreateGroup($pdo, $row['group']);
            
            // Upsert menu item
            $result = upsertMenuItem($pdo, $row, $groupId);
            
            if ($result['action'] === 'updated') {
                $updated++;
            } else {
                $imported++;
            }
            
        } catch (Exception $e) {
            $skipped++;
            $errors[] = [
                'row' => $row['row_number'],
                'name' => $row['name'],
                'error' => $e->getMessage()
            ];
            logMessage("Error on row {$row['row_number']} ({$row['name']}): " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    $response = [
        'success' => true,
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'total_rows' => count($rows),
        'errors' => $errors,
        'details_url' => null
    ];
    
    logMessage("Import completed: $imported inserted, $updated updated, $skipped skipped");
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    logMessage("Import failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Import failed',
        'message' => $e->getMessage()
    ]);
}
