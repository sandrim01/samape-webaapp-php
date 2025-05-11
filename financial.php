<?php
/**
 * SAMAPE - Financial Management
 * Handles listing, adding, and reporting of financial transactions
 */

// Page title
$page_title = "Gestão Financeira";
$page_description = "Controle de entradas e saídas financeiras";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in with appropriate permissions
require_permission([ROLE_ADMIN, ROLE_MANAGER]);

// Initialize variables
$error = '';
$success = '';
$transactions = [];
$transaction = null;
$action = 'list';

// Set default date filters (current month)
$start_date = date('Y-m-01'); // First day of current month
$end_date = date('Y-m-t');    // Last day of current month

// Handle different actions (list, add, report)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

// Process custom date filters if provided
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $start_date = format_date_mysql($_GET['start_date']);
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $end_date = format_date_mysql($_GET['end_date']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/financial.php");
        exit;
    }
    
    // Handle transaction addition
    if (isset($_POST['add_transaction'])) {
        $tipo = $_POST['tipo'];
        $valor = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor']);
        $descricao = trim($_POST['descricao']);
        $data = format_date_mysql($_POST['data']);
        
        // Basic validation
        if (empty($tipo) || empty($valor) || empty($data)) {
            $_SESSION['error'] = "Tipo, valor e data são campos obrigatórios.";
        } elseif (!is_numeric($valor) || $valor <= 0) {
            $_SESSION['error'] = "O valor deve ser um número positivo.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO financeiro (tipo, valor, descricao, data) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tipo, $valor, $descricao, $data]);
                
                $transaction_id = $db->lastInsertId();
                
                // Log the activity
                $tipo_texto = ($tipo == TRANSACTION_INCOME) ? 'entrada' : 'saída';
                log_activity($_SESSION['user_id'], 'transacao_financeira_adicionada', "ID: $transaction_id, Tipo: $tipo_texto, Valor: R$ $valor");
                
                $_SESSION['success'] = "Transação financeira registrada com sucesso.";
                header("Location: " . BASE_URL . "/financial.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Error adding financial transaction: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao registrar transação financeira. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the form
        header("Location: " . BASE_URL . "/financial.php?action=add");
        exit;
    }
    
    // Handle transaction deletion
    if (isset($_POST['delete_transaction']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            // Get transaction details for logging
            $stmt = $db->prepare("SELECT tipo, valor FROM financeiro WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                // Delete the transaction
                $stmt = $db->prepare("DELETE FROM financeiro WHERE id = ?");
                $stmt->execute([$id]);
                
                // Log the activity
                $tipo_texto = ($transaction['tipo'] == TRANSACTION_INCOME) ? 'entrada' : 'saída';
                log_activity($_SESSION['user_id'], 'transacao_financeira_removida', "ID: $id, Tipo: $tipo_texto, Valor: R$ {$transaction['valor']}");
                
                $_SESSION['success'] = "Transação financeira removida com sucesso.";
            } else {
                $_SESSION['error'] = "Transação não encontrada.";
            }
            
        } catch (PDOException $e) {
            error_log("Error deleting financial transaction: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao remover transação financeira.";
        }
        
        header("Location: " . BASE_URL . "/financial.php");
        exit;
    }
}

// Fetch financial data for display
if ($action === 'list' || $action === 'report') {
    try {
        // Calculate summary
        $summary = [
            'total_income' => 0,
            'total_expense' => 0,
            'net_result' => 0
        ];
        
        // Get income total
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_INCOME, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_income'] = $result['total'] ?: 0;
        
        // Get expense total
        $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
        $stmt->execute([TRANSACTION_EXPENSE, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_expense'] = $result['total'] ?: 0;
        
        // Calculate net result
        $summary['net_result'] = $summary['total_income'] - $summary['total_expense'];
        
        // Pagination setup for transaction list
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Build query based on filters
        $query_where = " WHERE data BETWEEN ? AND ?";
        $query_params = [$start_date, $end_date];
        
        // Filter by transaction type if specified
        if (isset($_GET['tipo']) && in_array($_GET['tipo'], [TRANSACTION_INCOME, TRANSACTION_EXPENSE])) {
            $query_where .= " AND tipo = ?";
            $query_params[] = $_GET['tipo'];
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as count FROM financeiro" . $query_where;
        $stmt = $db->prepare($count_query);
        $stmt->execute($query_params);
        $total_transactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_pages = ceil($total_transactions / $limit);
        
        // Get transactions for current page
        $query = "SELECT * FROM financeiro" . $query_where . " ORDER BY data DESC, id DESC LIMIT ? OFFSET ?";
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($query_params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For reports, get monthly data for charts
        if ($action === 'report') {
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
                $start_date_month = sprintf('%04d-%02d-01', $year, $month);
                $end_date_month = date('Y-m-t', strtotime($start_date_month));
                $month_label = date('M/Y', strtotime($start_date_month));
                
                // Get income for this month
                $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
                $stmt->execute([TRANSACTION_INCOME, $start_date_month, $end_date_month]);
                $income = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
                
                // Get expense for this month
                $stmt = $db->prepare("SELECT SUM(valor) as total FROM financeiro WHERE tipo = ? AND data BETWEEN ? AND ?");
                $stmt->execute([TRANSACTION_EXPENSE, $start_date_month, $end_date_month]);
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
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching financial data: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados financeiros.";
    }
}

// Include page header
include_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Financial Transactions List Page -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Resumo Financeiro</h5>
        <div>
            <a href="<?= BASE_URL ?>/financial.php?action=add" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Nova Transação
            </a>
            <a href="<?= BASE_URL ?>/financial.php?action=report" class="btn btn-info btn-sm">
                <i class="fas fa-chart-bar"></i> Relatórios
            </a>
            <a href="<?= BASE_URL ?>/includes/export.php?type=financial" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Exportar
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card card-body bg-success text-white mb-3">
                    <h5 class="card-title">Total de Receitas</h5>
                    <h3><?= format_currency($summary['total_income']) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-body bg-danger text-white mb-3">
                    <h5 class="card-title">Total de Despesas</h5>
                    <h3><?= format_currency($summary['total_expense']) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-body <?= $summary['net_result'] >= 0 ? 'bg-primary' : 'bg-dark' ?> text-white mb-3">
                    <h5 class="card-title">Resultado Líquido</h5>
                    <h3><?= format_currency($summary['net_result']) ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Date filters -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card card-body">
                    <form method="get" action="<?= BASE_URL ?>/financial.php" class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label">Tipo de Transação</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos</option>
                                <option value="<?= TRANSACTION_INCOME ?>" <?= isset($_GET['tipo']) && $_GET['tipo'] === TRANSACTION_INCOME ? 'selected' : '' ?>>Receitas</option>
                                <option value="<?= TRANSACTION_EXPENSE ?>" <?= isset($_GET['tipo']) && $_GET['tipo'] === TRANSACTION_EXPENSE ? 'selected' : '' ?>>Despesas</option>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="<?= BASE_URL ?>/financial.php" class="btn btn-secondary">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Transações Financeiras</h5>
    </div>
    <div class="card-body">
        <!-- Transactions table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhuma transação encontrada para o período selecionado.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= $t['id'] ?></td>
                        <td><?= format_date($t['data']) ?></td>
                        <td><?= get_transaction_label($t['tipo']) ?></td>
                        <td><?= htmlspecialchars($t['descricao']) ?></td>
                        <td class="text-end <?= $t['tipo'] === TRANSACTION_INCOME ? 'text-success' : 'text-danger' ?>">
                            <?= format_currency($t['valor']) ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $t['id'] ?>" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $t['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $t['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $t['id'] ?>">Confirmar Exclusão</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Tem certeza que deseja excluir esta transação financeira?</p>
                                            <div class="card card-body">
                                                <p><strong>Data:</strong> <?= format_date($t['data']) ?></p>
                                                <p><strong>Tipo:</strong> <?= $t['tipo'] === TRANSACTION_INCOME ? 'Receita' : 'Despesa' ?></p>
                                                <p><strong>Valor:</strong> <?= format_currency($t['valor']) ?></p>
                                                <p><strong>Descrição:</strong> <?= htmlspecialchars($t['descricao']) ?></p>
                                            </div>
                                            <p class="text-danger mt-2">Esta ação não poderá ser desfeita.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="<?= BASE_URL ?>/financial.php" method="post">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <button type="submit" name="delete_transaction" class="btn btn-danger">Excluir</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Navegação de páginas">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/financial.php?page=<?= $page - 1 ?><?= isset($_GET['start_date']) ? "&start_date=" . $_GET['start_date'] : "" ?><?= isset($_GET['end_date']) ? "&end_date=" . $_GET['end_date'] : "" ?><?= isset($_GET['tipo']) ? "&tipo=" . $_GET['tipo'] : "" ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/financial.php?page=<?= $i ?><?= isset($_GET['start_date']) ? "&start_date=" . $_GET['start_date'] : "" ?><?= isset($_GET['end_date']) ? "&end_date=" . $_GET['end_date'] : "" ?><?= isset($_GET['tipo']) ? "&tipo=" . $_GET['tipo'] : "" ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/financial.php?page=<?= $page + 1 ?><?= isset($_GET['start_date']) ? "&start_date=" . $_GET['start_date'] : "" ?><?= isset($_GET['end_date']) ? "&end_date=" . $_GET['end_date'] : "" ?><?= isset($_GET['tipo']) ? "&tipo=" . $_GET['tipo'] : "" ?>" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>
<!-- Add Financial Transaction Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Nova Transação Financeira</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/financial.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="tipo" class="form-label required">Tipo de Transação</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Selecione o tipo</option>
                        <option value="<?= TRANSACTION_INCOME ?>">Receita</option>
                        <option value="<?= TRANSACTION_EXPENSE ?>">Despesa</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione o tipo de transação.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="data" class="form-label required">Data</label>
                    <input type="date" class="form-control" id="data" name="data" value="<?= date('Y-m-d') ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe a data da transação.
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="valor" class="form-label required">Valor</label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="text" class="form-control" id="valor" name="valor" data-mask="currency" required>
                </div>
                <div class="invalid-feedback">
                    Por favor, informe o valor da transação.
                </div>
            </div>
            
            <div class="mb-3">
                <label for="descricao" class="form-label required">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3" required></textarea>
                <div class="invalid-feedback">
                    Por favor, informe uma descrição para a transação.
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/financial.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="add_transaction" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'report'): ?>
<!-- Financial Reports Page -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Relatórios Financeiros</h5>
        <div>
            <a href="<?= BASE_URL ?>/financial.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="<?= BASE_URL ?>/includes/export.php?type=financial" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Exportar Dados
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Date filters -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card card-body">
                    <form method="get" action="<?= BASE_URL ?>/financial.php" class="row g-3">
                        <input type="hidden" name="action" value="report">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label">Tipo de Transação</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos</option>
                                <option value="<?= TRANSACTION_INCOME ?>" <?= isset($_GET['tipo']) && $_GET['tipo'] === TRANSACTION_INCOME ? 'selected' : '' ?>>Receitas</option>
                                <option value="<?= TRANSACTION_EXPENSE ?>" <?= isset($_GET['tipo']) && $_GET['tipo'] === TRANSACTION_EXPENSE ? 'selected' : '' ?>>Despesas</option>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="<?= BASE_URL ?>/financial.php?action=report" class="btn btn-secondary">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-body bg-success text-white mb-3">
                    <h5 class="card-title">Total de Receitas</h5>
                    <h3><?= format_currency($summary['total_income']) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-body bg-danger text-white mb-3">
                    <h5 class="card-title">Total de Despesas</h5>
                    <h3><?= format_currency($summary['total_expense']) ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-body <?= $summary['net_result'] >= 0 ? 'bg-primary' : 'bg-dark' ?> text-white mb-3">
                    <h5 class="card-title">Resultado Líquido</h5>
                    <h3><?= format_currency($summary['net_result']) ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Monthly Chart -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Evolução Financeira - Últimos 6 Meses</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="financialChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Type Distribution Chart -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Distribuição de Receitas</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($summary['total_income'] > 0): ?>
                        <div class="chart-container">
                            <canvas id="incomeChart"></canvas>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            Não há dados de receitas para o período selecionado.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Distribuição de Despesas</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($summary['total_expense'] > 0): ?>
                        <div class="chart-container">
                            <canvas id="expenseChart"></canvas>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            Não há dados de despesas para o período selecionado.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Set validation flag for the footer to include validation script
$use_validation = true;

// Set charts flag for the footer to include charts script
$use_charts = ($action === 'report');

// Define page-specific JavaScript for charts
if ($action === 'report') {
    $page_script = "
    // Monthly financial chart
    const monthlyData = " . json_encode($monthly_data) . ";
    const labels = monthlyData.map(item => item.month);
    const incomeData = monthlyData.map(item => item.income);
    const expenseData = monthlyData.map(item => item.expense);
    const netData = monthlyData.map(item => item.income - item.expense);
    
    // Financial Chart
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Receitas',
                    data: incomeData,
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'Despesas',
                    data: expenseData,
                    backgroundColor: 'rgba(220, 53, 69, 0.5)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1,
                    order: 1
                },
                {
                    label: 'Resultado',
                    data: netData,
                    type: 'line',
                    backgroundColor: 'rgba(13, 110, 253, 0.5)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgb(13, 110, 253)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(13, 110, 253)',
                    pointRadius: 4,
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Mês'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Valor (R$)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw;
                            return `${label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                        }
                    }
                }
            }
        }
    });
    
    // Income and Expense charts (if data available)
    if (document.getElementById('incomeChart')) {
        // Get income categories
        fetch('/api/financial.php?action=income_categories&start_date=" . $start_date . "&end_date=" . $end_date . "')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories.length > 0) {
                    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
                    new Chart(incomeCtx, {
                        type: 'pie',
                        data: {
                            labels: data.categories.map(item => item.category),
                            datasets: [{
                                data: data.categories.map(item => item.total),
                                backgroundColor: [
                                    '#28a745', '#20c997', '#17a2b8', '#0d6efd', '#6610f2', 
                                    '#6f42c1', '#d63384', '#fd7e14', '#ffc107', '#198754'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
    }
    
    if (document.getElementById('expenseChart')) {
        // Get expense categories
        fetch('/api/financial.php?action=expense_categories&start_date=" . $start_date . "&end_date=" . $end_date . "')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.categories.length > 0) {
                    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
                    new Chart(expenseCtx, {
                        type: 'pie',
                        data: {
                            labels: data.categories.map(item => item.category),
                            datasets: [{
                                data: data.categories.map(item => item.total),
                                backgroundColor: [
                                    '#dc3545', '#fd7e14', '#ffc107', '#6f42c1', '#d63384', 
                                    '#20c997', '#0d6efd', '#6610f2', '#17a2b8', '#198754'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
    }
    ";
}

// Include page footer
include_once 'includes/footer.php';
?>
