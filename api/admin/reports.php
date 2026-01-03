<?php
/**
 * Reports API
 * Handles report generation and retrieval
 */

require_once __DIR__ . '/base.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($conn, $action);
            break;
        case 'POST':
            handlePost($conn, $action);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGet($conn, $action) {
    switch ($action) {
        case 'revenue':
            // Revenue report
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $groupBy = $_GET['group_by'] ?? 'day'; // day, week, month
            
            // PostgreSQL date format patterns
            $dateFormat = $groupBy === 'month' ? 'YYYY-MM' : ($groupBy === 'week' ? 'IYYY-IW' : 'YYYY-MM-DD');
            
            $stmt = $conn->prepare("
                SELECT 
                    TO_CHAR(created_at, ?) as period,
                    COUNT(*) as order_count,
                    SUM(total) as revenue,
                    AVG(total) as avg_order_value,
                    SUM(CASE WHEN status = 'finalizado' THEN total ELSE 0 END) as completed_revenue
                FROM orders
                WHERE created_at >= ?::date AND created_at < (?::date + INTERVAL '1 day')
                GROUP BY period
                ORDER BY period
            ");
            $stmt->execute([$dateFormat, $dateFrom, $dateTo]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate comparison with previous period
            $daysDiff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
            $prevDateFrom = date('Y-m-d', strtotime($dateFrom . " -$daysDiff days"));
            $prevDateTo = date('Y-m-d', strtotime($dateTo . " -$daysDiff days"));
            
            $stmt = $conn->prepare("
                SELECT SUM(total) as prev_revenue
                FROM orders
                WHERE created_at >= ?::date AND created_at < (?::date + INTERVAL '1 day')
                AND status = 'finalizado'
            ");
            $stmt->execute([$prevDateFrom, $prevDateTo]);
            $prevResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendSuccess([
                'data' => $data,
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'comparison' => $prevResult
            ]);
            break;
            
        case 'popular-items':
            // Most ordered items report
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            $limit = intval($_GET['limit'] ?? 10);
            
            $stmt = $conn->prepare("
                SELECT 
                    oi.item_name,
                    SUM(oi.quantity) as total_quantity,
                    COUNT(DISTINCT oi.order_id) as order_count,
                    SUM(oi.subtotal) as total_revenue
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE o.created_at >= ?::date AND o.created_at < (?::date + INTERVAL '1 day')
                GROUP BY oi.item_name
                ORDER BY total_quantity DESC
                LIMIT ?
            ");
            $stmt->execute([$dateFrom, $dateTo, $limit]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendSuccess($data);
            break;
            
        case 'customer-flow':
            // Customer flow by hour and day
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // By hour
            $stmt = $conn->prepare("
                SELECT 
                    EXTRACT(HOUR FROM created_at) as hour,
                    COUNT(*) as order_count,
                    AVG(total) as avg_order_value
                FROM orders
                WHERE created_at >= ?::date AND created_at < (?::date + INTERVAL '1 day')
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $byHour = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // By day of week
            $stmt = $conn->prepare("
                SELECT 
                    TO_CHAR(created_at, 'Day') as day_name,
                    EXTRACT(DOW FROM created_at) as day_num,
                    COUNT(*) as order_count,
                    SUM(total) as revenue
                FROM orders
                WHERE created_at >= ?::date AND created_at < (?::date + INTERVAL '1 day')
                GROUP BY day_name, day_num
                ORDER BY day_num
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendSuccess([
                'by_hour' => $byHour,
                'by_day' => $byDay
            ]);
            break;
            
        case 'saved-reports':
            // List saved reports
            $type = $_GET['type'] ?? null;
            
            $sql = "
                SELECT r.*, u.full_name as created_by_name
                FROM reports r
                LEFT JOIN users u ON r.created_by = u.id
                WHERE 1=1
            ";
            
            if ($type) {
                $sql .= " AND r.report_type = ?";
                $stmt = $conn->prepare($sql . " ORDER BY r.created_at DESC");
                $stmt->execute([$type]);
            } else {
                $result = $conn->query($sql . " ORDER BY r.created_at DESC");
            }
            
            $reports = $result->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON data
            foreach ($reports as &$report) {
                $report['data'] = json_decode($report['data'], true);
            }
            
            sendSuccess($reports);
            break;
            
        default:
            sendError('Invalid action');
    }
}

function handlePost($conn, $action) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'save':
            // Save report
            validateRequired($data, ['report_type', 'report_name', 'date_from', 'date_to', 'data']);
            
            $stmt = $conn->prepare("
                INSERT INTO reports (report_type, report_name, date_from, date_to, data, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $dataJson = json_encode($data['data']);
            $createdBy = $_SESSION['user_id'] ?? null;
            
            if ($stmt->execute([$data['report_type'], $data['report_name'], $data['date_from'], $data['date_to'], $dataJson, $createdBy])) {
                sendSuccess(['id' => $conn->lastInsertId()], 'Report saved successfully');
            } else {
                sendError('Failed to save report');
            }
            break;
            
        default:
            sendError('Invalid action');
    }
}
