<?php
/**
 * Common utility functions used throughout the application
 */

/**
 * Sanitize input data to prevent XSS
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format date to Brazilian format (DD/MM/YYYY)
 * @param string $date Date in YYYY-MM-DD format
 * @return string Formatted date
 */
function format_date($date) {
    if (empty($date) || $date == '0000-00-00') {
        return '';
    }
    $datetime = new DateTime($date);
    return $datetime->format('d/m/Y');
}

/**
 * Convert date from Brazilian format (DD/MM/YYYY) to MySQL format (YYYY-MM-DD)
 * @param string $date Date in DD/MM/YYYY format
 * @return string Date in YYYY-MM-DD format
 */
function format_date_mysql($date) {
    if (empty($date)) {
        return null;
    }
    $parts = explode('/', $date);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $date;
}

/**
 * Format currency values
 * @param float $value Value to format
 * @return string Formatted value (R$ X.XXX,XX)
 */
function format_currency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Get user name by ID
 * @param int $user_id User ID
 * @return string User name
 */
function get_user_name($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user['nome'] : 'Usuário Desconhecido';
    } catch (PDOException $e) {
        error_log("Error getting user name: " . $e->getMessage());
        return 'Erro ao buscar usuário';
    }
}

/**
 * Get client name by ID
 * @param int $client_id Client ID
 * @return string Client name
 */
function get_client_name($client_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $client ? $client['nome'] : 'Cliente Desconhecido';
    } catch (PDOException $e) {
        error_log("Error getting client name: " . $e->getMessage());
        return 'Erro ao buscar cliente';
    }
}

/**
 * Get machinery info by ID
 * @param int $machinery_id Machinery ID
 * @return array Machinery information
 */
function get_machinery_info($machinery_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM maquinarios WHERE id = ?");
        $stmt->execute([$machinery_id]);
        $machinery = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $machinery ?: null;
    } catch (PDOException $e) {
        error_log("Error getting machinery info: " . $e->getMessage());
        return null;
    }
}

/**
 * Get service order status label with color coding
 * @param string $status Status code
 * @return string HTML span element with status label
 */
function get_status_label($status) {
    $labels = [
        STATUS_OPEN => '<span class="badge bg-primary">Aberta</span>',
        STATUS_IN_PROGRESS => '<span class="badge bg-warning">Em Andamento</span>',
        STATUS_COMPLETED => '<span class="badge bg-success">Concluída</span>',
        STATUS_CANCELLED => '<span class="badge bg-danger">Cancelada</span>'
    ];
    
    return isset($labels[$status]) ? $labels[$status] : '<span class="badge bg-secondary">Desconhecido</span>';
}

/**
 * Get financial transaction type label with color coding
 * @param string $type Transaction type
 * @return string HTML span element with type label
 */
function get_transaction_label($type) {
    if ($type == TRANSACTION_INCOME) {
        return '<span class="badge bg-success">Entrada</span>';
    } else {
        return '<span class="badge bg-danger">Saída</span>';
    }
}

/**
 * Generate pagination links
 * @param int $page Current page
 * @param int $total_pages Total number of pages
 * @param string $url Base URL for links
 * @return string HTML pagination controls
 */
function pagination($page, $total_pages, $url) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $output = '<nav aria-label="Navegação de página"><ul class="pagination">';
    
    // Previous button
    if ($page > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($page - 1) . '">&laquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $page) {
            $output .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($page < $total_pages) {
        $output .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . ($page + 1) . '">&raquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">&raquo;</a></li>';
    }
    
    $output .= '</ul></nav>';
    
    return $output;
}

/**
 * Display flash messages from session
 * @return string HTML for displaying messages
 */
function display_flash_messages() {
    $output = '';
    
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        $output .= htmlspecialchars($_SESSION['success']);
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        $output .= htmlspecialchars($_SESSION['error']);
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $output .= '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
        $output .= htmlspecialchars($_SESSION['warning']);
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
        unset($_SESSION['warning']);
    }
    
    if (isset($_SESSION['info'])) {
        $output .= '<div class="alert alert-info alert-dismissible fade show" role="alert">';
        $output .= htmlspecialchars($_SESSION['info']);
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
        unset($_SESSION['info']);
    }
    
    return $output;
}

/**
 * Get count of service orders by status
 * @return array Counts by status
 */
function get_service_order_counts() {
    global $db;
    
    $counts = [
        'total' => 0,
        'aberta' => 0,
        'em_andamento' => 0,
        'concluida' => 0,
        'cancelada' => 0
    ];
    
    try {
        // Total count
        $stmt = $db->query("SELECT COUNT(*) as count FROM ordens_servico");
        $counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Count by status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM ordens_servico GROUP BY status");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            $counts[$result['status']] = $result['count'];
        }
    } catch (PDOException $e) {
        error_log("Error getting service order counts: " . $e->getMessage());
    }
    
    return $counts;
}

/**
 * Get monthly financial summary
 * @param int $year Year (default: current year)
 * @param int $month Month (default: current month)
 * @return array Financial summary
 */
function get_monthly_financial_summary($year = null, $month = null) {
    global $db;
    
    if ($year === null) {
        $year = date('Y');
    }
    
    if ($month === null) {
        $month = date('m');
    }
    
    $summary = [
        'total_income' => 0,
        'total_expenses' => 0,
        'net_result' => 0
    ];
    
    try {
        // Calculate start and end of month
        $start_date = $year . '-' . $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        // Get income
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_INCOME, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_income'] = $result['total'] ?: 0;
        
        // Get expenses
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_EXPENSE, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_expenses'] = $result['total'] ?: 0;
        
        // Calculate net result
        $summary['net_result'] = $summary['total_income'] - $summary['total_expenses'];
    } catch (PDOException $e) {
        error_log("Error getting financial summary: " . $e->getMessage());
    }
    
    return $summary;
}

/**
 * Get recent activity logs
 * @param int $limit Number of logs to retrieve (default: 10)
 * @return array Recent logs
 */
function get_recent_logs($limit = 10) {
    global $db;
    
    $logs = [];
    
    try {
        $stmt = $db->prepare("
            SELECT l.id, l.usuario_id, l.acao, l.datahora, u.nome
            FROM logs l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            ORDER BY l.datahora DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent logs: " . $e->getMessage());
    }
    
    return $logs;
}

/**
 * Get list of employees for dropdown
 * @param bool $active_only Only include active employees
 * @return array Employee list
 */
function get_employees_list($active_only = true) {
    global $db;
    
    $employees = [];
    
    try {
        $query = "SELECT id, nome FROM funcionarios";
        if ($active_only) {
            $query .= " WHERE ativo = 1";
        }
        $query .= " ORDER BY nome";
        
        $stmt = $db->query($query);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting employees list: " . $e->getMessage());
    }
    
    return $employees;
}

/**
 * Get list of clients for dropdown
 * @return array Client list
 */
function get_clients_list() {
    global $db;
    
    $clients = [];
    
    try {
        $stmt = $db->query("SELECT id, nome FROM clientes ORDER BY nome");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting clients list: " . $e->getMessage());
    }
    
    return $clients;
}

/**
 * Get machinery list for a specific client
 * @param int $client_id Client ID
 * @return array Machinery list
 */
function get_client_machinery($client_id) {
    global $db;
    
    $machinery = [];
    
    try {
        $stmt = $db->prepare("
            SELECT id, tipo, marca, modelo, numero_serie
            FROM maquinarios
            WHERE cliente_id = ?
            ORDER BY tipo, marca, modelo
        ");
        $stmt->execute([$client_id]);
        $machinery = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting client machinery: " . $e->getMessage());
    }
    
    return $machinery;
}

/**
 * Export data to CSV
 * @param array $data Array of data rows
 * @param array $headers Column headers
 * @param string $filename Output filename
 */
function export_to_csv($data, $headers, $filename) {
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
?>
