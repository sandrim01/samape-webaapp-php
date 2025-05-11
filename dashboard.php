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
require_once 'includes/gamification.php';

// Require user to be logged in
require_login();

// Get database connection
$db = $GLOBALS['db'];

// Get service order statistics
$order_counts = get_service_order_counts();

// Get financial summary for the current month
$current_month = date('m');
$current_year = date('Y');
$financial_summary = get_monthly_financial_summary($current_year, $current_month);

// Get recent activity logs
$recent_logs = get_recent_logs(10);

// Create default achievements if they don't exist
create_default_achievements($db);

// Get user gamification data
$user_achievements = get_user_achievements($db, $_SESSION['user_id']);
$employee_id = get_employee_id_from_user($db, $_SESSION['user_id']);
$user_stats = null;

if ($employee_id) {
    $user_stats = get_employee_stats($db, $employee_id);
}

// Get top employees for dashboard
$top_employees = get_leaderboard($db, 'points');
$top_employees = array_slice($top_employees, 0, 3);

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

<!-- Gamification Row -->
<div class="row mb-4">
    <!-- User Gamification Stats -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-trophy"></i> Seu Progresso
                </h5>
            </div>
            <div class="card-body">
                <?php if ($user_stats): ?>
                <div class="text-center mb-4">
                    <div class="user-level-badge">
                        <span class="level"><?= $user_stats['level'] ?></span>
                    </div>
                    <h4 class="mt-2">Nível <?= $user_stats['level'] ?></h4>
                    <p>
                        <strong><?= $user_stats['points'] ?></strong> pontos acumulados
                    </p>
                    
                    <!-- Progress to next level -->
                    <?php 
                    $next_level = min($user_stats['level'] + 1, 10);
                    $points_for_next = $next_level * 100;
                    $current_level_min = ($user_stats['level'] - 1) * 100;
                    $progress = ($user_stats['points'] - $current_level_min) / ($points_for_next - $current_level_min) * 100;
                    ?>
                    
                    <?php if ($user_stats['level'] < 10): ?>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%;" 
                            aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">
                        <?= $points_for_next - $user_stats['points'] ?> pontos para o Nível <?= $next_level ?>
                    </small>
                    <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-crown"></i> Você atingiu o nível máximo!
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6 text-center">
                        <div class="stat-circle">
                            <i class="fas fa-tools"></i>
                            <span><?= $user_stats['services_completed'] ?></span>
                        </div>
                        <p class="mt-1">Serviços Concluídos</p>
                    </div>
                    <div class="col-6 text-center">
                        <div class="stat-circle">
                            <i class="fas fa-star"></i>
                            <span><?= number_format($user_stats['avg_satisfaction'], 1) ?></span>
                        </div>
                        <p class="mt-1">Avaliação Média</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Você ainda não tem dados de progresso registrados. Complete ordens de serviço para ganhar pontos e subir de nível.
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="<?= BASE_URL ?>/gamification.php" class="btn btn-outline-primary">
                        <i class="fas fa-medal"></i> Ver Todas as Conquistas
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Employees / Achievements -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-award"></i> Top Técnicos
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>Técnico</th>
                                <th class="text-center">Nível</th>
                                <th class="text-center">Pontos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_employees as $index => $emp): ?>
                            <tr>
                                <td>
                                    <?php if($index === 0): ?>
                                    <i class="fas fa-trophy text-warning"></i>
                                    <?php elseif($index === 1): ?>
                                    <i class="fas fa-trophy text-secondary"></i>
                                    <?php elseif($index === 2): ?>
                                    <i class="fas fa-trophy text-danger"></i>
                                    <?php else: ?>
                                    <?= $index + 1 ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($emp['nome']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($emp['cargo']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $emp['level'] ?></span>
                                </td>
                                <td class="text-center"><?= $emp['points'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-3">
                    <a href="<?= BASE_URL ?>/gamification.php?action=leaderboard" class="btn btn-outline-success">
                        <i class="fas fa-list"></i> Ver Classificação Completa
                    </a>
                </div>
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
