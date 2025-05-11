<?php
/**
 * SAMAPE API - Service Orders
 * Handles AJAX requests for service orders data
 */

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'status_counts':
        getServiceOrderStatusCounts();
        break;
    
    case 'trend_data':
        getServiceOrderTrendData();
        break;
    
    case 'client_distribution':
        getClientDistribution();
        break;
    
    case 'get_details':
        getServiceOrderDetails();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get counts of service orders by status
 */
function getServiceOrderStatusCounts() {
    global $db;
    
    try {
        $counts = [
            'total' => 0,
            'aberta' => 0,
            'em_andamento' => 0,
            'concluida' => 0,
            'cancelada' => 0
        ];
        
        // Get total count
        $stmt = $db->query("SELECT COUNT(*) as count FROM ordens_servico");
        $counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get counts by status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM ordens_servico GROUP BY status");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            $counts[$result['status']] = (int)$result['count'];
        }
        
        echo json_encode(['success' => true, 'counts' => $counts]);
    } catch (PDOException $e) {
        error_log("Error getting service order counts: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get trend data of service orders over time
 */
function getServiceOrderTrendData() {
    global $db;
    
    try {
        // Get data for the last 6 months
        $data = [];
        
        // Current month and year
        $currentMonth = date('m');
        $currentYear = date('Y');
        
        // Loop through the last 6 months
        for ($i = 0; $i < 6; $i++) {
            $month = $currentMonth - $i;
            $year = $currentYear;
            
            // Adjust year if month is negative
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
            
            // Format month and year
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $periodLabel = date('M/Y', strtotime($startDate));
            
            // Get opened orders for this month
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM ordens_servico 
                WHERE data_abertura BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $opened = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Get closed orders for this month
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM ordens_servico 
                WHERE data_fechamento BETWEEN ? AND ?
                AND status IN (?, ?)
            ");
            $stmt->execute([$startDate, $endDate, STATUS_COMPLETED, STATUS_CANCELLED]);
            $closed = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Add to data array (in reverse order so oldest first)
            $data[5 - $i] = [
                'period' => $periodLabel,
                'opened' => (int)$opened,
                'closed' => (int)$closed
            ];
        }
        
        // Re-index array
        $data = array_values($data);
        
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        error_log("Error getting service order trend data: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get distribution of service orders by client
 */
function getClientDistribution() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT 
                c.id,
                c.nome as name,
                COUNT(os.id) as count
            FROM clientes c
            JOIN ordens_servico os ON c.id = os.cliente_id
            GROUP BY c.id
            ORDER BY count DESC
        ");
        
        $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert count to int
        foreach ($distribution as &$item) {
            $item['count'] = (int)$item['count'];
        }
        
        echo json_encode(['success' => true, 'distribution' => $distribution]);
    } catch (PDOException $e) {
        error_log("Error getting client distribution: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get details for a specific service order
 */
function getServiceOrderDetails() {
    global $db;
    
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid service order ID']);
        return;
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Get service order details
        $stmt = $db->prepare("
            SELECT 
                os.*,
                c.nome as client_name,
                m.tipo as machinery_type,
                m.marca as machinery_brand,
                m.modelo as machinery_model,
                m.numero_serie as machinery_serial
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            JOIN maquinarios m ON os.maquinario_id = m.id
            WHERE os.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Service order not found']);
            return;
        }
        
        // Get assigned employees
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.nome,
                f.cargo
            FROM os_funcionarios osf
            JOIN funcionarios f ON osf.funcionario_id = f.id
            WHERE osf.ordem_id = ?
        ");
        $stmt->execute([$id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order['employees'] = $employees;
        
        // Format dates for display
        $order['data_abertura_formatada'] = format_date($order['data_abertura']);
        $order['data_fechamento_formatada'] = format_date($order['data_fechamento']);
        
        echo json_encode(['success' => true, 'order' => $order]);
    } catch (PDOException $e) {
        error_log("Error getting service order details: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
