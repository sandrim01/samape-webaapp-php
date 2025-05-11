<?php
/**
 * SAMAPE API - Clients
 * Handles AJAX requests for clients data
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
    case 'get_machinery':
        getClientMachinery();
        break;
    
    case 'search':
        searchClients();
        break;
    
    case 'get_details':
        getClientDetails();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get machinery for a specific client
 */
function getClientMachinery() {
    global $db;
    
    if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
        return;
    }
    
    $client_id = (int)$_GET['client_id'];
    
    try {
        $stmt = $db->prepare("
            SELECT id, tipo, marca, modelo, numero_serie, ano
            FROM maquinarios
            WHERE cliente_id = ?
            ORDER BY tipo, marca, modelo
        ");
        $stmt->execute([$client_id]);
        $machinery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'machinery' => $machinery]);
    } catch (PDOException $e) {
        error_log("Error getting client machinery: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Search clients by name, email, or CNPJ
 */
function searchClients() {
    global $db;
    
    if (!isset($_GET['term']) || empty($_GET['term'])) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        return;
    }
    
    $term = '%' . $_GET['term'] . '%';
    
    try {
        $stmt = $db->prepare("
            SELECT id, nome, email, cnpj, telefone
            FROM clientes
            WHERE nome LIKE ? OR email LIKE ? OR cnpj LIKE ? OR telefone LIKE ?
            ORDER BY nome
            LIMIT 10
        ");
        $stmt->execute([$term, $term, $term, $term]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'clients' => $clients]);
    } catch (PDOException $e) {
        error_log("Error searching clients: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed information for a specific client
 */
function getClientDetails() {
    global $db;
    
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
        return;
    }
    
    $client_id = (int)$_GET['id'];
    
    try {
        // Get client information
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            echo json_encode(['success' => false, 'message' => 'Client not found']);
            return;
        }
        
        // Get client's machinery
        $stmt = $db->prepare("
            SELECT id, tipo, marca, modelo, numero_serie, ano, ultima_manutencao
            FROM maquinarios
            WHERE cliente_id = ?
            ORDER BY tipo, marca, modelo
        ");
        $stmt->execute([$client_id]);
        $machinery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get client's service orders
        $stmt = $db->prepare("
            SELECT 
                id, descricao, status, data_abertura, data_fechamento, valor_total
            FROM ordens_servico
            WHERE cliente_id = ?
            ORDER BY data_abertura DESC
            LIMIT 10
        ");
        $stmt->execute([$client_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates for display
        foreach ($machinery as &$machine) {
            $machine['ultima_manutencao_formatada'] = format_date($machine['ultima_manutencao']);
        }
        
        foreach ($orders as &$order) {
            $order['data_abertura_formatada'] = format_date($order['data_abertura']);
            $order['data_fechamento_formatada'] = format_date($order['data_fechamento']);
        }
        
        // Combine all data
        $result = [
            'client' => $client,
            'machinery' => $machinery,
            'orders' => $orders
        ];
        
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (PDOException $e) {
        error_log("Error getting client details: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
