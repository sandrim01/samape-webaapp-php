<?php
/**
 * SAMAPE - Gamification Dashboard
 * Shows achievements, points, levels and leaderboards
 */

// Page title
$page_title = "Gamificação";
$page_description = "Conquistas, pontos e classificações";

// Load charts
$use_charts = true;

// Include initialization file
require_once 'config/init.php';
require_once 'includes/gamification.php';

// Require user to be logged in
require_login();

// Get database connection
$db = $GLOBALS['db'];

// Initialize variables
$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$board_type = isset($_GET['type']) ? $_GET['type'] : 'points';

// Create default achievements if they don't exist
create_default_achievements($db);

// Check for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/gamification.php");
        exit;
    }
    
    // Add achievement
    if (isset($_POST['add_achievement'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $points = (int)$_POST['points'];
        
        if (empty($name) || empty($description) || empty($icon) || $points < 0) {
            $_SESSION['error'] = "Todos os campos são obrigatórios e os pontos devem ser um número positivo.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO achievements (name, description, icon, points) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $icon, $points]);
                
                $_SESSION['success'] = "Conquista adicionada com sucesso.";
                header("Location: " . BASE_URL . "/gamification.php?action=achievements");
                exit;
            } catch (PDOException $e) {
                error_log("Error adding achievement: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao adicionar conquista.";
            }
        }
    }
    
    // Give achievement to user
    if (isset($_POST['give_achievement'])) {
        $user_id = (int)$_POST['user_id'];
        $achievement_id = (int)$_POST['achievement_id'];
        
        if (award_achievement($db, $user_id, $achievement_id)) {
            $_SESSION['success'] = "Conquista concedida com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao conceder conquista ou usuário já possui esta conquista.";
        }
        
        header("Location: " . BASE_URL . "/gamification.php?action=manage");
        exit;
    }
    
    // Add points to employee
    if (isset($_POST['add_points'])) {
        $employee_id = (int)$_POST['employee_id'];
        $points = (int)$_POST['points'];
        $reason = trim($_POST['reason']);
        
        if (empty($reason) || $points <= 0) {
            $_SESSION['error'] = "Você deve fornecer um motivo e uma quantidade positiva de pontos.";
        } else {
            if (add_points_to_employee($db, $employee_id, $points)) {
                // Log the activity
                $employee = $db->prepare("SELECT nome FROM funcionarios WHERE id = ?");
                $employee->execute([$employee_id]);
                $emp = $employee->fetch(PDO::FETCH_ASSOC);
                log_activity($_SESSION['user_id'], 'pontos_adicionados', "Adicionados $points pontos para {$emp['nome']} - Motivo: $reason");
                
                $_SESSION['success'] = "Pontos adicionados com sucesso.";
            } else {
                $_SESSION['error'] = "Erro ao adicionar pontos.";
            }
        }
        
        header("Location: " . BASE_URL . "/gamification.php?action=manage");
        exit;
    }
}

// Get leaderboard data
$leaderboard = get_leaderboard($db, $board_type);

// Get all employees
$stmt = $db->prepare("
    SELECT 
        f.id, 
        f.nome, 
        f.cargo,
        COALESCE(es.points, 0) as points,
        COALESCE(es.level, 1) as level,
        COALESCE(es.services_completed, 0) as services_completed,
        COALESCE(es.avg_satisfaction, 0) as avg_satisfaction
    FROM funcionarios f
    LEFT JOIN employee_stats es ON f.id = es.employee_id
    WHERE f.ativo = 1
    ORDER BY f.nome
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all achievements
$all_achievements = get_all_achievements($db);

// Get current user's achievements
$user_achievements = get_user_achievements($db, $_SESSION['user_id']);

// Get employee-specific data if an employee ID is provided
$employee_data = null;
if ($employee_id) {
    // Get employee details
    $stmt = $db->prepare("
        SELECT 
            f.id, 
            f.nome, 
            f.cargo,
            COALESCE(es.points, 0) as points,
            COALESCE(es.level, 1) as level,
            COALESCE(es.services_completed, 0) as services_completed,
            COALESCE(es.avg_satisfaction, 0) as avg_satisfaction
        FROM funcionarios f
        LEFT JOIN employee_stats es ON f.id = es.employee_id
        WHERE f.id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get related user
    $stmt = $db->prepare("
        SELECT u.id, u.nome
        FROM usuarios u
        JOIN funcionarios f ON LOWER(u.email) = LOWER(f.email)
        WHERE f.id = ?
    ");
    $stmt->execute([$employee_id]);
    $related_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related_user) {
        // Get employee's achievements
        $employee_achievements = get_user_achievements($db, $related_user['id']);
    } else {
        $employee_achievements = [];
    }
    
    // Get employee's service orders
    $stmt = $db->prepare("
        SELECT 
            os.id,
            os.data_abertura,
            os.data_fechamento,
            os.status,
            os.satisfaction_rating,
            c.nome as cliente_nome,
            m.tipo as maquinario_tipo,
            m.marca as maquinario_marca,
            m.modelo as maquinario_modelo
        FROM ordens_servico os
        JOIN os_funcionarios osf ON os.id = osf.ordem_id
        JOIN clientes c ON os.cliente_id = c.id
        JOIN maquinarios m ON os.maquinario_id = m.id
        WHERE osf.funcionario_id = ?
        ORDER BY os.data_abertura DESC
        LIMIT 10
    ");
    $stmt->execute([$employee_id]);
    $employee_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Include page header
include_once 'includes/header.php';
?>

<!-- Main Content -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'achievements' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=achievements">
                            <i class="fas fa-award"></i> Conquistas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'leaderboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=leaderboard">
                            <i class="fas fa-trophy"></i> Classificação
                        </a>
                    </li>
                    <?php if (has_permission([ROLE_ADMIN, ROLE_MANAGER])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $action == 'manage' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=manage">
                            <i class="fas fa-cogs"></i> Gerenciar
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="card-body">
                <?php if ($action == 'dashboard'): ?>
                <!-- Gamification Dashboard -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-circle"></i> Seu Progresso
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get current user's employee stats
                                $employee_id = get_employee_id_from_user($db, $_SESSION['user_id']);
                                $stats = $employee_id ? get_employee_stats($db, $employee_id) : null;
                                ?>
                                
                                <?php if ($stats): ?>
                                <div class="text-center mb-4">
                                    <div class="user-level-badge">
                                        <span class="level"><?= $stats['level'] ?></span>
                                    </div>
                                    <h4 class="mt-2">Nível <?= $stats['level'] ?></h4>
                                    <p>
                                        <strong><?= $stats['points'] ?></strong> pontos acumulados
                                    </p>
                                    
                                    <!-- Progress to next level -->
                                    <?php 
                                    $next_level = min($stats['level'] + 1, 10);
                                    $points_for_next = $next_level * 100;
                                    $current_level_min = ($stats['level'] - 1) * 100;
                                    $progress = ($stats['points'] - $current_level_min) / ($points_for_next - $current_level_min) * 100;
                                    ?>
                                    
                                    <?php if ($stats['level'] < 10): ?>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%;" 
                                            aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?= $points_for_next - $stats['points'] ?> pontos para o Nível <?= $next_level ?>
                                    </small>
                                    <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-crown"></i> Você atingiu o nível máximo!
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="row mb-3">
                                    <div class="col-6 text-center">
                                        <div class="stat-circle">
                                            <i class="fas fa-tools"></i>
                                            <span><?= $stats['services_completed'] ?></span>
                                        </div>
                                        <p class="mt-1">Serviços Concluídos</p>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="stat-circle">
                                            <i class="fas fa-star"></i>
                                            <span><?= number_format($stats['avg_satisfaction'], 1) ?></span>
                                        </div>
                                        <p class="mt-1">Avaliação Média</p>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Você ainda não tem dados de progresso registrados.
                                </div>
                                <?php endif; ?>
                                
                                <!-- Recent Achievements -->
                                <h6 class="mt-4"><i class="fas fa-award"></i> Suas Conquistas Recentes</h6>
                                <?php if (!empty($user_achievements)): ?>
                                <ul class="list-group">
                                    <?php foreach(array_slice($user_achievements, 0, 3) as $achievement): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="fas <?= htmlspecialchars($achievement['icon']) ?> text-primary me-2"></i>
                                        <div>
                                            <strong><?= htmlspecialchars($achievement['name']) ?></strong>
                                            <small class="d-block text-muted">Conquistado em <?= date('d/m/Y', strtotime($achievement['earned_at'])) ?></small>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>/gamification.php?action=achievements" class="btn btn-sm btn-outline-primary">
                                        Ver Todas as Conquistas
                                    </a>
                                </div>
                                <?php else: ?>
                                <p class="text-muted">Você ainda não possui conquistas.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Leaderboard Preview -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-trophy"></i> Classificação de Técnicos
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th>Técnico</th>
                                                <th class="text-center">Nível</th>
                                                <th class="text-center">Pontos</th>
                                                <th class="text-center">Serviços</th>
                                                <th class="text-center">Satisfação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach(array_slice($leaderboard, 0, 5) as $index => $row): ?>
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
                                                    <a href="<?= BASE_URL ?>/gamification.php?action=profile&employee_id=<?= $row['id'] ?>">
                                                        <?= htmlspecialchars($row['nome']) ?>
                                                    </a>
                                                    <small class="text-muted d-block"><?= htmlspecialchars($row['cargo']) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?= $row['level'] ?></span>
                                                </td>
                                                <td class="text-center"><?= $row['points'] ?></td>
                                                <td class="text-center"><?= $row['services_completed'] ?></td>
                                                <td class="text-center">
                                                    <?php 
                                                    $rating = $row['avg_satisfaction'];
                                                    for($i=1; $i<=5; $i++) {
                                                        if($i <= round($rating)) {
                                                            echo '<i class="fas fa-star text-warning"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star text-warning"></i>';
                                                        }
                                                    }
                                                    ?>
                                                    <small class="text-muted d-block"><?= number_format($rating, 1) ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>/gamification.php?action=leaderboard" class="btn btn-sm btn-outline-success">
                                        Ver Classificação Completa
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Next Achievements -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-flag-checkered"></i> Próximas Conquistas
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get unearned achievements
                                $earned_ids = array_column($user_achievements, 'id');
                                $unearned = array_filter($all_achievements, function($a) use ($earned_ids) {
                                    return !in_array($a['id'], $earned_ids);
                                });
                                ?>
                                
                                <?php if (!empty($unearned)): ?>
                                <div class="row">
                                    <?php foreach(array_slice($unearned, 0, 3) as $achievement): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="achievement-icon mb-2">
                                                    <i class="fas <?= htmlspecialchars($achievement['icon']) ?>"></i>
                                                </div>
                                                <h6><?= htmlspecialchars($achievement['name']) ?></h6>
                                                <p class="text-muted small"><?= htmlspecialchars($achievement['description']) ?></p>
                                                <div class="badge bg-secondary"><?= $achievement['points'] ?> pontos</div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>/gamification.php?action=achievements" class="btn btn-sm btn-outline-info">
                                        Ver Todas as Conquistas
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Parabéns! Você conquistou todas as conquistas disponíveis.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'achievements'): ?>
                <!-- Achievements Page -->
                <h4 class="mb-4">Conquistas e Recompensas</h4>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-medal"></i> Suas Conquistas (<?= count($user_achievements) ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($user_achievements)): ?>
                                <div class="row">
                                    <?php foreach($user_achievements as $achievement): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="achievement-item">
                                                    <div class="achievement-icon earned">
                                                        <i class="fas <?= htmlspecialchars($achievement['icon']) ?>"></i>
                                                    </div>
                                                    <div class="achievement-details">
                                                        <h6><?= htmlspecialchars($achievement['name']) ?></h6>
                                                        <p class="text-muted small"><?= htmlspecialchars($achievement['description']) ?></p>
                                                        <div class="achievement-info">
                                                            <span class="badge bg-success"><?= $achievement['points'] ?> pontos</span>
                                                            <small class="text-muted">Conquistado em <?= date('d/m/Y', strtotime($achievement['earned_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Você ainda não conquistou nenhuma conquista. Continue trabalhando para obter sua primeira conquista!
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-unlock-alt"></i> Conquistas Disponíveis
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get unearned achievements
                                $earned_ids = array_column($user_achievements, 'id');
                                $unearned = array_filter($all_achievements, function($a) use ($earned_ids) {
                                    return !in_array($a['id'], $earned_ids);
                                });
                                ?>
                                
                                <?php if (!empty($unearned)): ?>
                                <div class="row">
                                    <?php foreach($unearned as $achievement): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="achievement-item">
                                                    <div class="achievement-icon locked">
                                                        <i class="fas <?= htmlspecialchars($achievement['icon']) ?>"></i>
                                                    </div>
                                                    <div class="achievement-details">
                                                        <h6><?= htmlspecialchars($achievement['name']) ?></h6>
                                                        <p class="text-muted small"><?= htmlspecialchars($achievement['description']) ?></p>
                                                        <div class="achievement-info">
                                                            <span class="badge bg-secondary"><?= $achievement['points'] ?> pontos</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Parabéns! Você conquistou todas as conquistas disponíveis.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'leaderboard'): ?>
                <!-- Leaderboard Page -->
                <h4 class="mb-4">Classificação dos Técnicos</h4>
                
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link <?= $board_type == 'points' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=leaderboard&type=points">
                                    <i class="fas fa-star"></i> Por Pontos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $board_type == 'services' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=leaderboard&type=services">
                                    <i class="fas fa-tools"></i> Por Serviços
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= $board_type == 'satisfaction' ? 'active' : '' ?>" href="<?= BASE_URL ?>/gamification.php?action=leaderboard&type=satisfaction">
                                    <i class="fas fa-smile"></i> Por Satisfação
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Técnico</th>
                                        <th class="text-center">Nível</th>
                                        <th class="text-center">Pontos</th>
                                        <th class="text-center">Serviços</th>
                                        <th class="text-center">Satisfação</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($leaderboard as $index => $row): ?>
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
                                            <?= htmlspecialchars($row['nome']) ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($row['cargo']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $row['level'] ?></span>
                                        </td>
                                        <td class="text-center"><?= $row['points'] ?></td>
                                        <td class="text-center"><?= $row['services_completed'] ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $rating = $row['avg_satisfaction'];
                                            for($i=1; $i<=5; $i++) {
                                                if($i <= round($rating)) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                            <small class="text-muted d-block"><?= number_format($rating, 1) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= BASE_URL ?>/gamification.php?action=profile&employee_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'profile' && $employee_data): ?>
                <!-- Employee Profile -->
                <h4 class="mb-4">Perfil do Técnico</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-circle"></i> Informações do Técnico
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="user-level-badge">
                                        <span class="level"><?= $employee_data['level'] ?></span>
                                    </div>
                                    <h4 class="mt-2"><?= htmlspecialchars($employee_data['nome']) ?></h4>
                                    <p class="text-muted"><?= htmlspecialchars($employee_data['cargo']) ?></p>
                                    <p>
                                        <strong><?= $employee_data['points'] ?></strong> pontos acumulados
                                    </p>
                                    
                                    <!-- Progress to next level -->
                                    <?php 
                                    $next_level = min($employee_data['level'] + 1, 10);
                                    $points_for_next = $next_level * 100;
                                    $current_level_min = ($employee_data['level'] - 1) * 100;
                                    $progress = ($employee_data['points'] - $current_level_min) / ($points_for_next - $current_level_min) * 100;
                                    ?>
                                    
                                    <?php if ($employee_data['level'] < 10): ?>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%;" 
                                            aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?= $points_for_next - $employee_data['points'] ?> pontos para o Nível <?= $next_level ?>
                                    </small>
                                    <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-crown"></i> Nível máximo alcançado!
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <hr>
                                
                                <div class="row mb-3">
                                    <div class="col-6 text-center">
                                        <div class="stat-circle">
                                            <i class="fas fa-tools"></i>
                                            <span><?= $employee_data['services_completed'] ?></span>
                                        </div>
                                        <p class="mt-1">Serviços Concluídos</p>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="stat-circle">
                                            <i class="fas fa-star"></i>
                                            <span><?= number_format($employee_data['avg_satisfaction'], 1) ?></span>
                                        </div>
                                        <p class="mt-1">Avaliação Média</p>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="<?= BASE_URL ?>/gamification.php?action=leaderboard" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-trophy"></i> Ver Classificação
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Employee Achievements -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-award"></i> Conquistas do Técnico
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($employee_achievements) && !empty($employee_achievements)): ?>
                                <div class="row">
                                    <?php foreach($employee_achievements as $achievement): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body text-center">
                                                <div class="achievement-icon earned mb-2">
                                                    <i class="fas <?= htmlspecialchars($achievement['icon']) ?>"></i>
                                                </div>
                                                <h6><?= htmlspecialchars($achievement['name']) ?></h6>
                                                <p class="text-muted small"><?= htmlspecialchars($achievement['description']) ?></p>
                                                <div class="badge bg-success"><?= $achievement['points'] ?> pontos</div>
                                                <div class="text-muted small mt-1">
                                                    <?= date('d/m/Y', strtotime($achievement['earned_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Este técnico ainda não conquistou nenhuma conquista.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Service Orders -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clipboard-list"></i> Ordens de Serviço Recentes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($employee_services) && !empty($employee_services)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>OS #</th>
                                                <th>Cliente</th>
                                                <th>Equipamento</th>
                                                <th>Status</th>
                                                <th>Data</th>
                                                <th>Avaliação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($employee_services as $service): ?>
                                            <tr>
                                                <td><?= $service['id'] ?></td>
                                                <td><?= htmlspecialchars($service['cliente_nome']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($service['maquinario_tipo']) ?>
                                                    <small class="text-muted d-block">
                                                        <?= htmlspecialchars($service['maquinario_marca']) ?> 
                                                        <?= htmlspecialchars($service['maquinario_modelo']) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    switch($service['status']) {
                                                        case 'aberta':
                                                            echo '<span class="badge bg-secondary">Aberta</span>';
                                                            break;
                                                        case 'em_andamento':
                                                            echo '<span class="badge bg-primary">Em Andamento</span>';
                                                            break;
                                                        case 'concluida':
                                                            echo '<span class="badge bg-success">Concluída</span>';
                                                            break;
                                                        case 'cancelada':
                                                            echo '<span class="badge bg-danger">Cancelada</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">' . htmlspecialchars($service['status']) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="text-muted">Abertura:</span> <?= date('d/m/Y', strtotime($service['data_abertura'])) ?>
                                                    <?php if ($service['data_fechamento']): ?>
                                                    <br>
                                                    <span class="text-muted">Fechamento:</span> <?= date('d/m/Y', strtotime($service['data_fechamento'])) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($service['satisfaction_rating']): ?>
                                                    <?php 
                                                    $rating = $service['satisfaction_rating'];
                                                    for($i=1; $i<=5; $i++) {
                                                        if($i <= $rating) {
                                                            echo '<i class="fas fa-star text-warning"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star text-warning"></i>';
                                                        }
                                                    }
                                                    ?>
                                                    <?php else: ?>
                                                    <span class="text-muted">Não avaliado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Este técnico ainda não participou de nenhuma ordem de serviço.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($action == 'manage' && has_permission([ROLE_ADMIN, ROLE_MANAGER])): ?>
                <!-- Gamification Management -->
                <h4 class="mb-4">Gerenciamento de Gamificação</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <!-- Add Achievement Form -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle"></i> Adicionar Nova Conquista
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= BASE_URL ?>/gamification.php?action=manage">
                                    <?= csrf_token_input() ?>
                                    <input type="hidden" name="add_achievement" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome da Conquista</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descrição</label>
                                        <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="icon" class="form-label">Ícone (FontAwesome)</label>
                                                <input type="text" class="form-control" id="icon" name="icon" placeholder="fa-trophy" required>
                                                <div class="form-text">
                                                    <small>Digite a classe do <a href="https://fontawesome.com/v5/search?m=free&o=r" target="_blank">FontAwesome</a> (ex: fa-trophy)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="points" class="form-label">Pontos</label>
                                                <input type="number" class="form-control" id="points" name="points" min="0" value="10" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Salvar Conquista
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Add Points to Employee Form -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle"></i> Adicionar Pontos a um Técnico
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= BASE_URL ?>/gamification.php?action=manage">
                                    <?= csrf_token_input() ?>
                                    <input type="hidden" name="add_points" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="employee_id" class="form-label">Selecione o Técnico</label>
                                        <select class="form-select" id="employee_id" name="employee_id" required>
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($employees as $employee): ?>
                                            <option value="<?= $employee['id'] ?>">
                                                <?= htmlspecialchars($employee['nome']) ?> 
                                                (Nível <?= $employee['level'] ?>, <?= $employee['points'] ?> pontos)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="points" class="form-label">Pontos a Adicionar</label>
                                                <input type="number" class="form-control" id="points" name="points" min="1" value="5" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="reason" class="form-label">Motivo</label>
                                                <input type="text" class="form-control" id="reason" name="reason" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus-circle"></i> Adicionar Pontos
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Award Achievement Form -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-award"></i> Conceder Conquista Manualmente
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?= BASE_URL ?>/gamification.php?action=manage">
                                    <?= csrf_token_input() ?>
                                    <input type="hidden" name="give_achievement" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">Selecione o Usuário</label>
                                        <select class="form-select" id="user_id" name="user_id" required>
                                            <option value="">-- Selecione --</option>
                                            <?php
                                            // Get all users
                                            $stmt = $db->query("SELECT id, nome, email FROM usuarios ORDER BY nome");
                                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach($users as $user):
                                            ?>
                                            <option value="<?= $user['id'] ?>">
                                                <?= htmlspecialchars($user['nome']) ?> 
                                                (<?= htmlspecialchars($user['email']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="achievement_id" class="form-label">Selecione a Conquista</label>
                                        <select class="form-select" id="achievement_id" name="achievement_id" required>
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($all_achievements as $achievement): ?>
                                            <option value="<?= $achievement['id'] ?>">
                                                <?= htmlspecialchars($achievement['name']) ?> 
                                                (<?= $achievement['points'] ?> pontos)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-info">
                                            <i class="fas fa-award"></i> Conceder Conquista
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- List All Achievements -->
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list"></i> Conquistas Disponíveis
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Descrição</th>
                                                <th>Pontos</th>
                                                <th>Ícone</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($all_achievements as $achievement): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($achievement['name']) ?></td>
                                                <td><?= htmlspecialchars($achievement['description']) ?></td>
                                                <td><?= $achievement['points'] ?></td>
                                                <td><i class="fas <?= htmlspecialchars($achievement['icon']) ?>"></i></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Ação inválida ou você não tem permissão para acessar esta página.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Gamification Styles */
.user-level-badge {
    width: 80px;
    height: 80px;
    background-color: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    border: 4px solid #0056b3;
    font-size: 2.5rem;
    font-weight: bold;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.stat-circle {
    width: 60px;
    height: 60px;
    background-color: #f8f9fa;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    border: 2px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-circle i {
    font-size: 1.2rem;
    color: #6c757d;
}

.stat-circle span {
    font-weight: bold;
    font-size: 1rem;
    margin-top: 2px;
}

.achievement-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 1.5rem;
}

.achievement-icon.earned {
    background-color: #28a745;
    color: white;
    border: 2px solid #218838;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.achievement-icon.locked {
    background-color: #6c757d;
    color: white;
    border: 2px solid #5a6268;
    opacity: 0.7;
}

.achievement-item {
    display: flex;
    align-items: center;
}

.achievement-details {
    margin-left: 15px;
    flex: 1;
}

.achievement-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
}
</style>

<?php
// Include page footer
include_once 'includes/footer.php';
?>