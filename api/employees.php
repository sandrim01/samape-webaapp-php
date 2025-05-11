<?php
/**
 * SAMAPE API - Employees
 * Handles AJAX requests for employees data
 */

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has required permissions
if (!has_permission([ROLE_ADMIN, ROLE_MANAGER])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_employees':
        getEmployees();
        break;
    
    case 'get_details':
        getEmployeeDetails();
        break;
    
    case 'search':
        searchEmployees();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get list of employees
 */
function getEmployees() {
    global $db;
    
    $active_only = isset($_GET['active_only']) && $_GET['active_only'] === '1';
    
    try {
        $query = "SELECT id, nome, cargo, email, ativo FROM funcionarios";
        
        if ($active_only) {
            $query .= " WHERE ativo = 1";
        }
        
        $query .= " ORDER BY nome";
        
        $stmt = $db->query($query);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert ativo to boolean
        foreach ($employees as &$employee) {
            $employee['ativo'] = (bool)$employee['ativo'];
        }
        
        echo json_encode(['success' => true, 'employees' => $employees]);
    } catch (PDOException $e) {
        error_log("Error getting employees: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed information for a specific employee
 */
function getEmployeeDetails() {
    global $db;
    
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        return;
    }
    
    $employee_id = (int)$_GET['id'];
    
    try {
        // Get employee information
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            return;
        }
        
        // Convert ativo to boolean
        $employee['ativo'] = (bool)$employee['ativo'];
        
        // Get service orders that this employee is assigned to
        $stmt = $db->prepare("
            SELECT 
                os.id, 
                os.descricao, 
                os.status, 
                os.data_abertura, 
                os.data_fechamento,
                c.nome as client_name
            FROM os_funcionarios osf
            JOIN ordens_servico os ON osf.ordem_id = os.id
            JOIN clientes c ON os.cliente_id = c.id
            WHERE osf.funcionario_id = ?
            ORDER BY os.data_abertura DESC
            LIMIT 10
        ");
        $stmt->execute([$employee_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates for display
        foreach ($orders as &$order) {
            $order['data_abertura_formatada'] = format_date($order['data_abertura']);
            $order['data_fechamento_formatada'] = format_date($order['data_fechamento']);
        }
        
        // Combine all data
        $result = [
            'employee' => $employee,
            'orders' => $orders
        ];
        
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (PDOException $e) {
        error_log("Error getting employee details: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Search employees by name, email, or role
 */
function searchEmployees() {
    global $db;
    
    if (!isset($_GET['term']) || empty($_GET['term'])) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        return;
    }
    
    $term = '%' . $_GET['term'] . '%';
    
    try {
        $stmt = $db->prepare("
            SELECT id, nome, cargo, email, ativo
            FROM funcionarios
            WHERE nome LIKE ? OR email LIKE ? OR cargo LIKE ?
            ORDER BY nome
            LIMIT 10
        ");
        $stmt->execute([$term, $term, $term]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert ativo to boolean
        foreach ($employees as &$employee) {
            $employee['ativo'] = (bool)$employee['ativo'];
        }
        
        echo json_encode(['success' => true, 'employees' => $employees]);
    } catch (PDOException $e) {
        error_log("Error searching employees: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
