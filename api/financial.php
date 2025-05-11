<?php
/**
 * SAMAPE API - Financial
 * Handles AJAX requests for financial data
 */

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has permission
if (!has_permission([ROLE_ADMIN, ROLE_MANAGER])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'monthly_data':
        getMonthlyData();
        break;
    
    case 'income_categories':
        getIncomeCategories();
        break;
        
    case 'expense_categories':
        getExpenseCategories();
        break;
    
    case 'summary':
        getFinancialSummary();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get monthly financial data for the last 6 months
 */
function getMonthlyData() {
    global $db;
    
    try {
        // Get monthly summary for the last 6 months
        $monthly_data = [];
        
        // Current month and year
        $current_month = date('m');
        $current_year = date('Y');
        
        // Loop through the last 6 months
        for ($i = 0; $i < 6; $i++) {
            $month = $current_month - $i;
            $year = $current_year;
            
            // Adjust year if month is negative
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
            
            // Format month and year
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            $month_label = date('M/Y', strtotime($start_date));
            
            // Get income for this month
            $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
            $stmt->execute([TRANSACTION_INCOME, $start_date, $end_date]);
            $income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
            
            // Get expense for this month
            $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
            $stmt->execute([TRANSACTION_EXPENSE, $start_date, $end_date]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
            
            // Add to data array (in reverse order so oldest first)
            $monthly_data[5 - $i] = [
                'month' => $month_label,
                'income' => (float)$income,
                'expense' => (float)$expense
            ];
        }
        
        // Re-index array
        $monthly_data = array_values($monthly_data);
        
        echo json_encode(['success' => true, 'months' => $monthly_data]);
    } catch (PDOException $e) {
        error_log("Error getting monthly financial data: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get income transactions grouped by category
 */
function getIncomeCategories() {
    global $db;
    
    try {
        // Get date range from request
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
        
        // Group income transactions by description keywords
        $stmt = $db->prepare("
            SELECT 
                descricao,
                SUM(valor) as total
            FROM financeiro 
            WHERE tipo = ? AND data BETWEEN ? AND ?
            GROUP BY descricao
            ORDER BY total DESC
        ");
        $stmt->execute([TRANSACTION_INCOME, $start_date, $end_date]);
        $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Categorize based on keywords in description
        $categories = categorizeTransactions($raw_data, 'income');
        
        echo json_encode(['success' => true, 'categories' => $categories]);
    } catch (PDOException $e) {
        error_log("Error getting income categories: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get expense transactions grouped by category
 */
function getExpenseCategories() {
    global $db;
    
    try {
        // Get date range from request
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
        
        // Group expense transactions by description keywords
        $stmt = $db->prepare("
            SELECT 
                descricao,
                SUM(valor) as total
            FROM financeiro 
            WHERE tipo = ? AND data BETWEEN ? AND ?
            GROUP BY descricao
            ORDER BY total DESC
        ");
        $stmt->execute([TRANSACTION_EXPENSE, $start_date, $end_date]);
        $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Categorize based on keywords in description
        $categories = categorizeTransactions($raw_data, 'expense');
        
        echo json_encode(['success' => true, 'categories' => $categories]);
    } catch (PDOException $e) {
        error_log("Error getting expense categories: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get overall financial summary
 */
function getFinancialSummary() {
    global $db;
    
    try {
        // Get date range from request
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
        
        // Get income total
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_INCOME, $start_date, $end_date]);
        $income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        // Get expense total
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_EXPENSE, $start_date, $end_date]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        // Calculate net result
        $net_result = $income - $expense;
        
        $summary = [
            'income' => (float)$income,
            'expense' => (float)$expense,
            'net_result' => (float)$net_result,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
        
        echo json_encode(['success' => true, 'summary' => $summary]);
    } catch (PDOException $e) {
        error_log("Error getting financial summary: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Helper function to categorize transactions based on keywords
 * @param array $transactions List of transactions with description and total
 * @param string $type Type of transaction ('income' or 'expense')
 * @return array Categorized data
 */
function categorizeTransactions($transactions, $type) {
    // Defined categories and their keywords
    $categories = [];
    
    if ($type === 'income') {
        $category_keywords = [
            'Serviços de Manutenção' => ['manutencao', 'servico', 'reparo', 'os', 'ordem'],
            'Venda de Peças' => ['peca', 'venda', 'componente', 'reposicao'],
            'Consultoria Técnica' => ['consultoria', 'avaliacao', 'laudo', 'parecer'],
            'Instalação' => ['instalacao', 'montagem', 'setup'],
            'Treinamento' => ['treinamento', 'curso', 'capacitacao']
        ];
    } else { // expense
        $category_keywords = [
            'Salários' => ['salario', 'folha', 'pagamento'],
            'Materiais' => ['material', 'peca', 'componente', 'insumo', 'estoque'],
            'Aluguel' => ['aluguel', 'locacao', 'imovel'],
            'Serviços' => ['servico', 'terceirizado', 'prestacao'],
            'Impostos' => ['imposto', 'taxa', 'tributo'],
            'Equipamentos' => ['equipamento', 'ferramenta', 'maquina'],
            'Combustível' => ['combustivel', 'gasolina', 'diesel', 'transporte'],
            'Utilidades' => ['agua', 'luz', 'energia', 'telefone', 'internet']
        ];
    }
    
    // Initialize categories
    $categorized = [];
    foreach (array_keys($category_keywords) as $category) {
        $categorized[$category] = 0;
    }
    $categorized['Outros'] = 0;
    
    // Categorize each transaction
    foreach ($transactions as $transaction) {
        $description = strtolower($transaction['descricao']);
        $categorized_this = false;
        
        foreach ($category_keywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, strtolower($keyword)) !== false) {
                    $categorized[$category] += (float)$transaction['total'];
                    $categorized_this = true;
                    break;
                }
            }
            if ($categorized_this) break;
        }
        
        if (!$categorized_this) {
            $categorized['Outros'] += (float)$transaction['total'];
        }
    }
    
    // Format into array for chart
    $result = [];
    foreach ($categorized as $category => $total) {
        if ($total > 0) {
            $result[] = [
                'category' => $category,
                'total' => $total
            ];
        }
    }
    
    // Sort by total (descending)
    usort($result, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    return $result;
}
?>
