<?php
/**
 * SAMAPE - Dashboard
 * Main dashboard with system overview
 */

// Page title
$page_title = "Dashboard";
$page_description = "Visão geral do sistema";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in
require_login();

// Get service order statistics
$order_counts = get_service_order_counts();

// Get financial summary for the current month
$current_month = date('m');
$current_year = date('Y');
$financial_summary = get_monthly_financial_summary($current_year, $current_month);

// Get recent activity logs
$recent_logs = get_recent_logs(10);

// Include page header
include_once 'includes/header.php';
?>

<!-- Dashboard Overview -->
<div class="row mb-4">
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card card-counter primary">
            <i class="fas fa-clipboard-list"></i>
            <span class="count-numbers"><?= $order_counts['total'] ?></span>
            <span class="count-name">Ordens de Serviço</span>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card card-counter warning">
            <i class="fas fa-cogs"></i>
            <span class="count-numbers"><?= $order_counts['em_andamento'] ?></span>
            <span class="count-name">Em Andamento</span>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card card-counter success">
            <i class="fas fa-check-circle"></i>
            <span class="count-numbers"><?= $order_counts['concluida'] ?></span>
            <span class="count-name">Concluídas</span>
        </div>
    </div>
    
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card card-counter info">
            <i class="fas fa-chart-line"></i>
            <span class="count-numbers"><?= format_currency($financial_summary['total_income']) ?></span>
            <span class="count-name">Faturamento</span>
        </div>
    </div>
</div>

<div class="row">
    <!-- Service Orders Chart -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Ordens de Serviço por Status</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="serviceOrdersChart"></canvas>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="<?= BASE_URL ?>/service_orders.php" class="btn btn-sm btn-primary">Ver Todas</a>
            </div>
        </div>
    </div>
    
    <!-- Financial Summary -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title">Resumo Financeiro do Mês</h5>
            </div>
            <div class="card-body">
                <h4 class="text-center mb-4"><?= date('F Y') ?></h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Receitas</th>
                                <td class="text-success"><?= format_currency($financial_summary['total_income']) ?></td>
                            </tr>
                            <tr>
                                <th>Despesas</th>
                                <td class="text-danger"><?= format_currency($financial_summary['total_expenses']) ?></td>
                            </tr>
                            <tr>
                                <th>Resultado</th>
                                <td class="<?= $financial_summary['net_result'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= format_currency($financial_summary['net_result']) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="<?= BASE_URL ?>/financial.php" class="btn btn-sm btn-primary">Ver Detalhes</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activity -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Atividades Recentes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_logs)): ?>
                <div class="alert alert-info">
                    Nenhuma atividade recente encontrada.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Ação</th>
                                <th>Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['nome'] ?? 'Usuário Desconhecido') ?></td>
                                <td><?= htmlspecialchars($log['acao']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($log['datahora'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if (has_permission([ROLE_ADMIN])): ?>
            <div class="card-footer text-end">
                <a href="<?= BASE_URL ?>/logs.php" class="btn btn-sm btn-primary">Ver Todos os Logs</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pending Tasks -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Pendências e Alertas</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Ordens em Aberto</h6>
                    <p class="mb-0">Há <?= $order_counts['aberta'] ?> ordens de serviço abertas aguardando atendimento.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Set charts flag for the footer to include charts script
$use_charts = true;

// Define page-specific JavaScript
$page_script = "
// Initialize the dashboard charts when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Service orders chart data
    const serviceOrdersData = {
        labels: ['Abertas', 'Em Andamento', 'Concluídas', 'Canceladas'],
        datasets: [{
            data: [{$order_counts['aberta']}, {$order_counts['em_andamento']}, {$order_counts['concluida']}, {$order_counts['cancelada']}],
            backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#dc3545'],
            borderWidth: 1
        }]
    };
    
    // Create the service orders chart
    const serviceOrdersCtx = document.getElementById('serviceOrdersChart').getContext('2d');
    new Chart(serviceOrdersCtx, {
        type: 'pie',
        data: serviceOrdersData,
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
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `\${label}: \${value} (\${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
});
";

// Include page footer
include_once 'includes/footer.php';
?>
