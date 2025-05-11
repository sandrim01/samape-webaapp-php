<?php
/**
 * Export functionality for data exports
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/init.php';
require_login();

// Check if export type is specified
if (!isset($_GET['type'])) {
    $_SESSION['error'] = "Tipo de exportação não especificado.";
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$export_type = $_GET['type'];

switch ($export_type) {
    case 'service_orders':
        export_service_orders();
        break;
    case 'clients':
        export_clients();
        break;
    case 'machinery':
        export_machinery();
        break;
    case 'financial':
        export_financial();
        break;
    default:
        $_SESSION['error'] = "Tipo de exportação inválido.";
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
}

/**
 * Export service orders to CSV
 */
function export_service_orders() {
    global $db;
    
    try {
        $query = "
            SELECT 
                os.id,
                c.nome as cliente,
                os.descricao,
                os.status,
                os.data_abertura,
                os.data_fechamento,
                os.valor_total,
                m.tipo as maquina_tipo,
                m.marca as maquina_marca,
                m.modelo as maquina_modelo,
                m.numero_serie
            FROM ordens_servico os
            LEFT JOIN clientes c ON os.cliente_id = c.id
            LEFT JOIN maquinarios m ON os.maquinario_id = m.id
            ORDER BY os.id DESC
        ";
        
        $stmt = $db->query($query);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($orders as $order) {
            $row = [
                'ID' => $order['id'],
                'Cliente' => $order['cliente'],
                'Descrição' => $order['descricao'],
                'Status' => translate_status($order['status']),
                'Data Abertura' => format_date($order['data_abertura']),
                'Data Fechamento' => format_date($order['data_fechamento']),
                'Valor Total' => $order['valor_total'],
                'Máquina' => $order['maquina_tipo'] . ' ' . $order['maquina_marca'] . ' ' . $order['maquina_modelo'],
                'Número de Série' => $order['numero_serie']
            ];
            
            $data[] = $row;
        }
        
        $headers = [
            'ID',
            'Cliente',
            'Descrição',
            'Status',
            'Data Abertura',
            'Data Fechamento',
            'Valor Total',
            'Máquina',
            'Número de Série'
        ];
        
        $filename = 'ordens_servico_' . date('Y-m-d') . '.csv';
        export_to_csv($data, $headers, $filename);
    } catch (PDOException $e) {
        error_log("Error exporting service orders: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao exportar ordens de serviço.";
        header("Location: " . BASE_URL . "/service_orders.php");
        exit;
    }
}

/**
 * Export clients to CSV
 */
function export_clients() {
    global $db;
    
    try {
        $query = "
            SELECT 
                id,
                nome,
                cnpj,
                telefone,
                email,
                endereco,
                created_at
            FROM clientes
            ORDER BY nome
        ";
        
        $stmt = $db->query($query);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($clients as $client) {
            $row = [
                'ID' => $client['id'],
                'Nome' => $client['nome'],
                'CNPJ/CPF' => $client['cnpj'],
                'Telefone' => $client['telefone'],
                'Email' => $client['email'],
                'Endereço' => $client['endereco'],
                'Data Cadastro' => format_date($client['created_at'])
            ];
            
            $data[] = $row;
        }
        
        $headers = [
            'ID',
            'Nome',
            'CNPJ/CPF',
            'Telefone',
            'Email',
            'Endereço',
            'Data Cadastro'
        ];
        
        $filename = 'clientes_' . date('Y-m-d') . '.csv';
        export_to_csv($data, $headers, $filename);
    } catch (PDOException $e) {
        error_log("Error exporting clients: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao exportar clientes.";
        header("Location: " . BASE_URL . "/clients.php");
        exit;
    }
}

/**
 * Export machinery to CSV
 */
function export_machinery() {
    global $db;
    
    try {
        $query = "
            SELECT 
                m.id,
                c.nome as cliente,
                m.tipo,
                m.marca,
                m.modelo,
                m.numero_serie,
                m.ano,
                m.ultima_manutencao
            FROM maquinarios m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            ORDER BY c.nome, m.tipo, m.marca
        ";
        
        $stmt = $db->query($query);
        $machinery = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($machinery as $item) {
            $row = [
                'ID' => $item['id'],
                'Cliente' => $item['cliente'],
                'Tipo' => $item['tipo'],
                'Marca' => $item['marca'],
                'Modelo' => $item['modelo'],
                'Número de Série' => $item['numero_serie'],
                'Ano' => $item['ano'],
                'Última Manutenção' => format_date($item['ultima_manutencao'])
            ];
            
            $data[] = $row;
        }
        
        $headers = [
            'ID',
            'Cliente',
            'Tipo',
            'Marca',
            'Modelo',
            'Número de Série',
            'Ano',
            'Última Manutenção'
        ];
        
        $filename = 'maquinarios_' . date('Y-m-d') . '.csv';
        export_to_csv($data, $headers, $filename);
    } catch (PDOException $e) {
        error_log("Error exporting machinery: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao exportar maquinários.";
        header("Location: " . BASE_URL . "/machinery.php");
        exit;
    }
}

/**
 * Export financial data to CSV
 */
function export_financial() {
    global $db;
    
    try {
        $query = "
            SELECT 
                id,
                tipo,
                valor,
                descricao,
                data,
                created_at
            FROM financeiro
            ORDER BY data DESC
        ";
        
        $stmt = $db->query($query);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($transactions as $transaction) {
            $row = [
                'ID' => $transaction['id'],
                'Tipo' => $transaction['tipo'] == TRANSACTION_INCOME ? 'Entrada' : 'Saída',
                'Valor' => $transaction['valor'],
                'Descrição' => $transaction['descricao'],
                'Data' => format_date($transaction['data']),
                'Data Registro' => format_date($transaction['created_at'])
            ];
            
            $data[] = $row;
        }
        
        $headers = [
            'ID',
            'Tipo',
            'Valor',
            'Descrição',
            'Data',
            'Data Registro'
        ];
        
        $filename = 'financeiro_' . date('Y-m-d') . '.csv';
        export_to_csv($data, $headers, $filename);
    } catch (PDOException $e) {
        error_log("Error exporting financial data: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao exportar dados financeiros.";
        header("Location: " . BASE_URL . "/financial.php");
        exit;
    }
}

/**
 * Translate status code to readable text
 * @param string $status Status code
 * @return string Human-readable status
 */
function translate_status($status) {
    $translations = [
        STATUS_OPEN => 'Aberta',
        STATUS_IN_PROGRESS => 'Em Andamento',
        STATUS_COMPLETED => 'Concluída',
        STATUS_CANCELLED => 'Cancelada'
    ];
    
    return isset($translations[$status]) ? $translations[$status] : $status;
}
?>
