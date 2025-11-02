<?php
/**
 * Monthly Inventory Report API
 * Provides data for the monthly inventory reports
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

require_once '../Includes/connection.php';
require_once '../Includes/MonthlyInventoryManager.php';
require_once '../Includes/period_helper.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'get_monthly_report';
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    
    $monthlyInventory = new MonthlyInventoryManager($conn);
    
    switch ($action) {
        case 'get_monthly_summary':
            // Get period information
            $stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$periodInfo) {
                $currentYear = date('Y');
                $currentMonth = date('n');
                $isCurrentOrPast = ($year < $currentYear) || ($year == $currentYear && $month <= $currentMonth);
                
                // Get the latest existing period
                $stmt = $conn->query("
                    SELECT year, month 
                    FROM monthly_inventory_periods 
                    ORDER BY year DESC, month DESC 
                    LIMIT 1
                ");
                $latestPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Calculate if selected month is the next month after latest period
                $isNextMonth = false;
                if ($latestPeriod) {
                    $latestDate = mktime(0, 0, 0, $latestPeriod['month'], 1, $latestPeriod['year']);
                    $selectedDate = mktime(0, 0, 0, $month, 1, $year);
                    $nextMonthDate = strtotime('+1 month', $latestDate);
                    $isNextMonth = ($selectedDate == $nextMonthDate);
                }
                
                // Only create if it's current/past AND it's the next month in sequence
                if ($isCurrentOrPast && $isNextMonth) {
                    try {
                        $periodId = createMonthlyPeriod($conn, $year, $month);
                        $stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE id = ?");
                        $stmt->execute([$periodId]);
                        $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'No data available for the selected period'
                        ]);
                        break;
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No data available for the selected period'
                    ]);
                    break;
                }
            }
            
            $periodId = $periodInfo['id'];
            
            // Get summary statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(mis.beginning_quantity) as total_beginning,
                    SUM(mis.new_delivery_total) as total_deliveries,
                    SUM(mis.sales_total) as total_sales,
                    SUM(mis.removals_total) as total_removals,
                    SUM(mis.ending_quantity) as total_ending,
                    SUM(mis.ending_quantity * i.price) as total_value
                FROM monthly_inventory_snapshots mis
                LEFT JOIN inventory i ON mis.item_code = i.item_code
                WHERE mis.period_id = ?
            ");
            $stmt->execute([$periodId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'period' => $periodInfo,
                'summary' => $summary,
                'month_name' => date('F Y', mktime(0, 0, 0, $month, 1, $year))
            ]);
            break;
            
        case 'get_monthly_inventory':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(5, min(100, intval($_GET['limit']))) : 20;
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            
            // Get period information
            $stmt = $conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$periodInfo) {
                // Auto-create the period ONLY if it's current month or past
                $currentYear = date('Y');
                $currentMonth = date('n');
                $isCurrentOrPast = ($year < $currentYear) || ($year == $currentYear && $month <= $currentMonth);
                
                if ($isCurrentOrPast) {
                    try {
                        $periodId = createMonthlyPeriod($conn, $year, $month);
                        $periodInfo = ['id' => $periodId];
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'No data available for the selected period'
                        ]);
                        break;
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No data available for the selected period'
                    ]);
                    break;
                }
            }
            
            $periodId = $periodInfo['id'];
            
            // Build WHERE clause for filtering
            $whereClause = "WHERE mis.period_id = ?";
            $params = [$periodId];
            
            if ($search) {
                $whereClause .= " AND (i.item_name LIKE ? OR mis.item_code LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($category) {
                $whereClause .= " AND i.category = ?";
                $params[] = $category;
            }
            
            // Get total count
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM monthly_inventory_snapshots mis
                JOIN inventory i ON mis.item_code = i.item_code
                $whereClause
            ");
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated data
            $stmt = $conn->prepare("
                SELECT 
                    mis.item_code,
                    i.item_name,
                    i.category,
                    mis.beginning_quantity,
                    mis.new_delivery_total,
                    mis.sales_total,
                    mis.removals_total,
                    mis.ending_quantity,
                    i.price,
                    (mis.ending_quantity * i.price) as ending_value
                FROM monthly_inventory_snapshots mis
                JOIN inventory i ON mis.item_code = i.item_code
                $whereClause
                ORDER BY i.category, i.item_name
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPages = ceil($totalRecords / $limit);
            
            echo json_encode([
                'success' => true,
                'data' => $inventoryData,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit
                ]
            ]);
            break;
            
        case 'get_available_periods':
            $stmt = $conn->query("
                SELECT year, month, period_start, period_end, is_closed
                FROM monthly_inventory_periods 
                ORDER BY year DESC, month DESC
            ");
            $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formattedPeriods = array_map(function($period) {
                return [
                    'year' => $period['year'],
                    'month' => $period['month'],
                    'display_name' => date('F Y', mktime(0, 0, 0, $period['month'], 1, $period['year'])),
                    'period_start' => $period['period_start'],
                    'period_end' => $period['period_end'],
                    'is_closed' => (bool)$period['is_closed']
                ];
            }, $periods);
            
            echo json_encode([
                'success' => true,
                'periods' => $formattedPeriods
            ]);
            break;
            
        case 'get_categories':
            $stmt = $conn->prepare("
                SELECT DISTINCT i.category
                FROM monthly_inventory_snapshots mis
                JOIN inventory i ON mis.item_code = i.item_code
                JOIN monthly_inventory_periods mip ON mis.period_id = mip.id
                WHERE mip.year = ? AND mip.month = ?
                ORDER BY i.category
            ");
            $stmt->execute([$year, $month]);
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        case 'get_removal_details':
            // Get period information
            $stmt = $conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$periodInfo) {
                echo json_encode([
                    'success' => true,
                    'removals' => []
                ]);
                break;
            }
            
            $periodId = $periodInfo['id'];
            
            // Get all removals for the period with item details
            $stmt = $conn->prepare("
                SELECT 
                    ir.id,
                    ir.item_code,
                    i.item_name,
                    ir.quantity_removed,
                    ir.removal_reason,
                    ir.removed_at,
                    ir.pullout_order_number
                FROM inventory_removals ir
                JOIN inventory i ON ir.item_code COLLATE utf8mb4_unicode_ci = i.item_code COLLATE utf8mb4_unicode_ci
                WHERE ir.period_id = ?
                ORDER BY ir.removed_at DESC
            ");
            $stmt->execute([$periodId]);
            $removals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'removals' => $removals
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>