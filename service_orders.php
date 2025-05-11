<?php
/**
 * SAMAPE - Service Orders Management
 * Handles listing, adding, editing, and managing service orders
 */

// Page title
$page_title = "Ordens de Serviço";
$page_description = "Gerenciamento de ordens de serviço";

// Include initialization file
require_once 'config/init.php';
require_once 'includes/gamification.php';

// Require user to be logged in
require_login();

// Initialize variables
$error = '';
$success = '';
$service_orders = [];
$service_order = null;
$employees = [];
$clients = [];
$machinery = [];
$action = 'list';
$client_id = 0;

// Handle different actions (list, add, edit, view, close)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

// Get client_id if specified
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int)$_GET['client_id'];
}

// Get all employees for dropdown/checkboxes
try {
    $employees = get_employees_list();
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage());
}

// Get all clients for dropdown
try {
    $clients = get_clients_list();
} catch (PDOException $e) {
    error_log("Error fetching clients: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/service_orders.php");
        exit;
    }
    
    // Handle service order creation
    if (isset($_POST['add_service_order'])) {
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        $maquinario_id = isset($_POST['maquinario_id']) ? (int)$_POST['maquinario_id'] : 0;
        $descricao = trim($_POST['descricao']);
        $status = STATUS_OPEN; // Initially open
        $data_abertura = format_date_mysql($_POST['data_abertura'] ?? date('d/m/Y'));
        $funcionarios = isset($_POST['funcionarios']) ? $_POST['funcionarios'] : [];
        
        // Basic validation
        if (empty($cliente_id) || empty($maquinario_id) || empty($descricao)) {
            $_SESSION['error'] = "Por favor, preencha todos os campos obrigatórios.";
        } else {
            try {
                $db->beginTransaction();
                
                // Create service order
                $stmt = $db->prepare("INSERT INTO ordens_servico (cliente_id, maquinario_id, descricao, status, data_abertura) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$cliente_id, $maquinario_id, $descricao, $status, $data_abertura]);
                
                $order_id = $db->lastInsertId();
                
                // Associate employees if selected
                if (!empty($funcionarios)) {
                    $stmt = $db->prepare("INSERT INTO os_funcionarios (ordem_id, funcionario_id) VALUES (?, ?)");
                    foreach ($funcionarios as $funcionario_id) {
                        $stmt->execute([$order_id, $funcionario_id]);
                    }
                }
                
                $db->commit();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'os_criada', "OS ID: $order_id, Cliente ID: $cliente_id");
                
                $_SESSION['success'] = "Ordem de serviço criada com sucesso.";
                header("Location: " . BASE_URL . "/service_orders.php");
                exit;
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Error creating service order: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao criar ordem de serviço. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the form
        header("Location: " . BASE_URL . "/service_orders.php?action=add");
        exit;
    }
    
    // Handle service order update
    if (isset($_POST['update_service_order']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $descricao = trim($_POST['descricao']);
        $status = $_POST['status'];
        $funcionarios = isset($_POST['funcionarios']) ? $_POST['funcionarios'] : [];
        
        try {
            $db->beginTransaction();
            
            // Check if we're closing the order
            $data_fechamento = null;
            $valor_total = null;
            
            if ($status === STATUS_COMPLETED) {
                $data_fechamento = date('Y-m-d');
                $valor_total = isset($_POST['valor_total']) ? 
                    str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_total']) : 0;
                
                // Add to financial records if we're completing the order with a value
                if ($valor_total > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO financeiro (tipo, valor, descricao, data) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        TRANSACTION_INCOME, 
                        $valor_total, 
                        "Pagamento de OS #$id", 
                        $data_fechamento
                    ]);
                }
                
                // Get employees associated with this service order for gamification
                $stmt = $db->prepare("SELECT funcionario_id FROM os_funcionarios WHERE ordem_id = ?");
                $stmt->execute([$id]);
                $employee_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                // Get satisfaction rating if provided
                $satisfaction_rating = null;
                if (isset($_POST['satisfaction_rating']) && is_numeric($_POST['satisfaction_rating'])) {
                    $satisfaction_rating = min(5, max(0, (float)$_POST['satisfaction_rating']));
                    
                    // Update the satisfaction rating in the service order
                    $stmt = $db->prepare("UPDATE ordens_servico SET satisfaction_rating = ? WHERE id = ?");
                    $stmt->execute([$satisfaction_rating, $id]);
                }
                
                // Update employee gamification stats
                if (!empty($employee_ids)) {
                    update_employee_stats_after_service($db, $id, $employee_ids, $satisfaction_rating);
                }
            }
            
            // Update service order
            $stmt = $db->prepare("
                UPDATE ordens_servico 
                SET descricao = ?, status = ?, data_fechamento = ?, valor_total = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$descricao, $status, $data_fechamento, $valor_total, $id]);
            
            // Update associated employees
            // First, remove all current associations
            $stmt = $db->prepare("DELETE FROM os_funcionarios WHERE ordem_id = ?");
            $stmt->execute([$id]);
            
            // Then add new associations
            if (!empty($funcionarios)) {
                $stmt = $db->prepare("INSERT INTO os_funcionarios (ordem_id, funcionario_id) VALUES (?, ?)");
                foreach ($funcionarios as $funcionario_id) {
                    $stmt->execute([$id, $funcionario_id]);
                }
            }
            
            $db->commit();
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'os_atualizada', "OS ID: $id, Status: $status");
            
            $_SESSION['success'] = "Ordem de serviço atualizada com sucesso.";
            header("Location: " . BASE_URL . "/service_orders.php");
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Error updating service order: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao atualizar ordem de serviço. Verifique os dados e tente novamente.";
        }
        
        // Redirect back to the edit form
        header("Location: " . BASE_URL . "/service_orders.php?action=edit&id=$id");
        exit;
    }
}

// Handle edit action - load service order data
if (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        // Get service order details
        $stmt = $db->prepare("
            SELECT os.*, c.nome as client_name, m.tipo as machinery_type, m.marca as machinery_brand, 
                   m.modelo as machinery_model, m.numero_serie as machinery_serial
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            JOIN maquinarios m ON os.maquinario_id = m.id
            WHERE os.id = ?
        ");
        $stmt->execute([$id]);
        $service_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service_order) {
            $_SESSION['error'] = "Ordem de serviço não encontrada.";
            header("Location: " . BASE_URL . "/service_orders.php");
            exit;
        }
        
        // Get assigned employees
        $stmt = $db->prepare("
            SELECT funcionario_id 
            FROM os_funcionarios 
            WHERE ordem_id = ?
        ");
        $stmt->execute([$id]);
        $assigned_employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $service_order['funcionarios'] = $assigned_employees;
        
    } catch (PDOException $e) {
        error_log("Error fetching service order: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados da ordem de serviço.";
        header("Location: " . BASE_URL . "/service_orders.php");
        exit;
    }
}

// Fetch service orders for listing
if ($action === 'list') {
    try {
        // Pagination setup
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Build query based on filters
        $where_clauses = [];
        $query_params = [];
        
        // Filter by client if specified
        if ($client_id) {
            $where_clauses[] = "os.cliente_id = ?";
            $query_params[] = $client_id;
        }
        
        // Filter by status if specified
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status_filter = $_GET['status'];
            $where_clauses[] = "os.status = ?";
            $query_params[] = $status_filter;
        }
        
        // Build WHERE clause
        $where_sql = "";
        if (!empty($where_clauses)) {
            $where_sql = " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as count FROM ordens_servico os" . $where_sql;
        $stmt = $db->prepare($count_query);
        if (!empty($query_params)) {
            $stmt->execute($query_params);
        } else {
            $stmt->execute();
        }
        $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_pages = ceil($total_orders / $limit);
        
        // Build main query
        $query = "
            SELECT 
                os.id, os.cliente_id, os.descricao, os.status, os.data_abertura, os.data_fechamento, os.valor_total,
                c.nome as client_name, m.tipo as machinery_type, m.marca as machinery_brand, m.modelo as machinery_model
            FROM ordens_servico os
            JOIN clientes c ON os.cliente_id = c.id
            JOIN maquinarios m ON os.maquinario_id = m.id
        " . $where_sql . "
            ORDER BY os.data_abertura DESC, os.id DESC
            LIMIT ? OFFSET ?
        ";
        
        // Add pagination parameters
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($query_params);
        $service_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching service orders: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar lista de ordens de serviço.";
    }
}

// Include page header
include_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Service Orders List Page -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Lista de Ordens de Serviço</h5>
        <div>
            <a href="<?= BASE_URL ?>/service_orders.php?action=add" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Nova OS
            </a>
            <a href="<?= BASE_URL ?>/includes/export.php?type=service_orders" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Exportar
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar OS...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="client-filter" onchange="window.location.href=this.value">
                    <option value="<?= BASE_URL ?>/service_orders.php">Todos os Clientes</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= BASE_URL ?>/service_orders.php?client_id=<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="status-filter" onchange="window.location.href=this.value">
                    <option value="<?= BASE_URL ?>/service_orders.php<?= $client_id ? "?client_id=$client_id" : "" ?>">Todos os Status</option>
                    <option value="<?= BASE_URL ?>/service_orders.php?status=<?= STATUS_OPEN ?><?= $client_id ? "&client_id=$client_id" : "" ?>" <?= isset($_GET['status']) && $_GET['status'] == STATUS_OPEN ? 'selected' : '' ?>>
                        Abertas
                    </option>
                    <option value="<?= BASE_URL ?>/service_orders.php?status=<?= STATUS_IN_PROGRESS ?><?= $client_id ? "&client_id=$client_id" : "" ?>" <?= isset($_GET['status']) && $_GET['status'] == STATUS_IN_PROGRESS ? 'selected' : '' ?>>
                        Em Andamento
                    </option>
                    <option value="<?= BASE_URL ?>/service_orders.php?status=<?= STATUS_COMPLETED ?><?= $client_id ? "&client_id=$client_id" : "" ?>" <?= isset($_GET['status']) && $_GET['status'] == STATUS_COMPLETED ? 'selected' : '' ?>>
                        Concluídas
                    </option>
                    <option value="<?= BASE_URL ?>/service_orders.php?status=<?= STATUS_CANCELLED ?><?= $client_id ? "&client_id=$client_id" : "" ?>" <?= isset($_GET['status']) && $_GET['status'] == STATUS_CANCELLED ? 'selected' : '' ?>>
                        Canceladas
                    </option>
                </select>
            </div>
        </div>
        
        <!-- Service Orders table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped searchable-table">
                <thead>
                    <tr>
                        <th>OS #</th>
                        <th>Cliente</th>
                        <th>Maquinário</th>
                        <th>Status</th>
                        <th>Abertura</th>
                        <th>Fechamento</th>
                        <th>Valor</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($service_orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Nenhuma ordem de serviço encontrada.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($service_orders as $os): ?>
                    <tr>
                        <td><?= $os['id'] ?></td>
                        <td><?= htmlspecialchars($os['client_name']) ?></td>
                        <td><?= htmlspecialchars($os['machinery_type']) ?> <?= htmlspecialchars($os['machinery_brand']) ?> <?= htmlspecialchars($os['machinery_model']) ?></td>
                        <td><?= get_status_label($os['status']) ?></td>
                        <td><?= format_date($os['data_abertura']) ?></td>
                        <td><?= format_date($os['data_fechamento']) ?></td>
                        <td><?= $os['valor_total'] ? format_currency($os['valor_total']) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>/service_orders.php?action=view&id=<?= $os['id'] ?>" class="btn btn-info btn-sm" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($os['status'] != STATUS_COMPLETED && $os['status'] != STATUS_CANCELLED): ?>
                            <a href="<?= BASE_URL ?>/service_orders.php?action=edit&id=<?= $os['id'] ?>" class="btn btn-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <a href="#" class="btn btn-secondary btn-sm btn-print" title="Imprimir" data-os-id="<?= $os['id'] ?>">
                                <i class="fas fa-print"></i>
                            </a>
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
                    <a class="page-link" href="<?= BASE_URL ?>/service_orders.php?page=<?= $page - 1 ?><?= $client_id ? "&client_id=$client_id" : "" ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/service_orders.php?page=<?= $i ?><?= $client_id ? "&client_id=$client_id" : "" ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/service_orders.php?page=<?= $page + 1 ?><?= $client_id ? "&client_id=$client_id" : "" ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>
<!-- Add Service Order Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Nova Ordem de Serviço</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/service_orders.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label required">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione um cliente.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="maquinario_id" class="form-label required">Maquinário</label>
                    <select class="form-select" id="maquinario_id" name="maquinario_id" required disabled>
                        <option value="">Selecione o cliente primeiro</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione um maquinário.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_abertura" class="form-label required">Data de Abertura</label>
                    <input type="date" class="form-control" id="data_abertura" name="data_abertura" value="<?= date('Y-m-d') ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe a data de abertura.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Técnicos Responsáveis</label>
                    <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                        <?php foreach ($employees as $emp): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="funcionarios[]" value="<?= $emp['id'] ?>" id="emp_<?= $emp['id'] ?>">
                            <label class="form-check-label" for="emp_<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['nome']) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="descricao" class="form-label required">Descrição do Serviço</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="4" required></textarea>
                <div class="invalid-feedback">
                    Por favor, informe a descrição do serviço.
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/service_orders.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="add_service_order" class="btn btn-primary">Criar OS</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && $service_order): ?>
<!-- Edit Service Order Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Editar Ordem de Serviço #<?= $service_order['id'] ?></h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/service_orders.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            <input type="hidden" name="id" value="<?= $service_order['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Cliente</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($service_order['client_name']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Maquinário</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($service_order['machinery_type']) ?> <?= htmlspecialchars($service_order['machinery_brand']) ?> <?= htmlspecialchars($service_order['machinery_model']) ?>" disabled>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Data de Abertura</label>
                    <input type="text" class="form-control" value="<?= format_date($service_order['data_abertura']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label required">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="<?= STATUS_OPEN ?>" <?= $service_order['status'] == STATUS_OPEN ? 'selected' : '' ?>>Aberta</option>
                        <option value="<?= STATUS_IN_PROGRESS ?>" <?= $service_order['status'] == STATUS_IN_PROGRESS ? 'selected' : '' ?>>Em Andamento</option>
                        <option value="<?= STATUS_COMPLETED ?>" <?= $service_order['status'] == STATUS_COMPLETED ? 'selected' : '' ?>>Concluída</option>
                        <option value="<?= STATUS_CANCELLED ?>" <?= $service_order['status'] == STATUS_CANCELLED ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3" id="valor_container" style="<?= $service_order['status'] !== STATUS_COMPLETED ? 'display: none;' : '' ?>">
                <label for="valor_total" class="form-label">Valor Total</label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="text" class="form-control" id="valor_total" name="valor_total" data-mask="currency" value="<?= $service_order['valor_total'] ? number_format($service_order['valor_total'], 2, ',', '.') : '' ?>">
                </div>
            </div>
            
            <div class="mb-3" id="satisfaction_container" style="<?= $service_order['status'] !== STATUS_COMPLETED ? 'display: none;' : '' ?>">
                <label for="satisfaction_rating" class="form-label">Avaliação do Cliente</label>
                <div class="rating-stars">
                    <?php
                    $current_rating = isset($service_order['satisfaction_rating']) ? (int)$service_order['satisfaction_rating'] : 0;
                    for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="satisfaction_rating" id="rating-<?= $i ?>" value="<?= $i ?>" <?= $current_rating === $i ? 'checked' : '' ?>>
                        <label for="rating-<?= $i ?>"><i class="fas fa-star"></i></label>
                    <?php endfor; ?>
                </div>
                <div class="rating-text small text-muted mt-1">
                    Selecione de 1 a 5 estrelas para classificar a satisfação do cliente com o serviço
                </div>
            </div>
            
            <div class="mb-3">
                <label for="descricao" class="form-label required">Descrição do Serviço</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?= htmlspecialchars($service_order['descricao']) ?></textarea>
                <div class="invalid-feedback">
                    Por favor, informe a descrição do serviço.
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Técnicos Responsáveis</label>
                <div class="border rounded p-2" style="max-height: 150px; overflow-y: auto;">
                    <?php foreach ($employees as $emp): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="funcionarios[]" value="<?= $emp['id'] ?>" id="emp_<?= $emp['id'] ?>" <?= in_array($emp['id'], $service_order['funcionarios']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="emp_<?= $emp['id'] ?>">
                            <?= htmlspecialchars($emp['nome']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/service_orders.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="update_service_order" class="btn btn-primary">Atualizar OS</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $service_order): ?>
<!-- View Service Order Details -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Ordem de Serviço #<?= $service_order['id'] ?></h5>
        <div>
            <?php if ($service_order['status'] != STATUS_COMPLETED && $service_order['status'] != STATUS_CANCELLED): ?>
            <a href="<?= BASE_URL ?>/service_orders.php?action=edit&id=<?= $service_order['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Editar
            </a>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm btn-print">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">Detalhes do Cliente</h6>
                <p><strong>Cliente:</strong> <?= htmlspecialchars($service_order['client_name']) ?></p>
                <p><strong>Maquinário:</strong> <?= htmlspecialchars($service_order['machinery_type']) ?> <?= htmlspecialchars($service_order['machinery_brand']) ?> <?= htmlspecialchars($service_order['machinery_model']) ?></p>
                <p><strong>Nº de Série:</strong> <?= htmlspecialchars($service_order['machinery_serial']) ?></p>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">Detalhes da OS</h6>
                <p><strong>Status:</strong> <?= get_status_label($service_order['status']) ?></p>
                <p><strong>Data de Abertura:</strong> <?= format_date($service_order['data_abertura']) ?></p>
                <p><strong>Data de Fechamento:</strong> <?= $service_order['data_fechamento'] ? format_date($service_order['data_fechamento']) : 'Em aberto' ?></p>
                <?php if ($service_order['status'] == STATUS_COMPLETED): ?>
                <p><strong>Valor Total:</strong> <?= format_currency($service_order['valor_total']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="fw-bold">Descrição do Serviço</h6>
                <div class="p-3 bg-light rounded">
                    <?= nl2br(htmlspecialchars($service_order['descricao'])) ?>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="fw-bold">Técnicos Responsáveis</h6>
                <div class="p-3 bg-light rounded">
                    <?php
                    $assigned_employees_list = [];
                    foreach ($employees as $emp) {
                        if (in_array($emp['id'], $service_order['funcionarios'])) {
                            $assigned_employees_list[] = htmlspecialchars($emp['nome']);
                        }
                    }
                    if (empty($assigned_employees_list)) {
                        echo '<p class="text-muted mb-0">Nenhum técnico atribuído</p>';
                    } else {
                        echo '<p class="mb-0">' . implode(', ', $assigned_employees_list) . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="<?= BASE_URL ?>/service_orders.php" class="btn btn-secondary">Voltar para Lista</a>
    </div>
</div>

<!-- Print Template (hidden) -->
<div id="print-template" class="d-none">
    <div class="print-header">
        <h1>SAMAPE - Assistência Técnica</h1>
        <h2>Ordem de Serviço #<?= $service_order['id'] ?></h2>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-6">
            <h4>Cliente</h4>
            <p><strong>Nome:</strong> <?= htmlspecialchars($service_order['client_name']) ?></p>
            <p><strong>Maquinário:</strong> <?= htmlspecialchars($service_order['machinery_type']) ?> <?= htmlspecialchars($service_order['machinery_brand']) ?> <?= htmlspecialchars($service_order['machinery_model']) ?></p>
            <p><strong>Nº de Série:</strong> <?= htmlspecialchars($service_order['machinery_serial']) ?></p>
        </div>
        <div class="col-6">
            <h4>Detalhes da OS</h4>
            <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $service_order['status'])) ?></p>
            <p><strong>Data de Abertura:</strong> <?= format_date($service_order['data_abertura']) ?></p>
            <p><strong>Data de Fechamento:</strong> <?= $service_order['data_fechamento'] ? format_date($service_order['data_fechamento']) : 'Em aberto' ?></p>
            <?php if ($service_order['status'] == STATUS_COMPLETED): ?>
            <p><strong>Valor Total:</strong> <?= format_currency($service_order['valor_total']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h4>Descrição do Serviço</h4>
            <div class="p-3 border rounded">
                <?= nl2br(htmlspecialchars($service_order['descricao'])) ?>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <h4>Técnicos Responsáveis</h4>
            <div class="p-3 border rounded">
                <?php
                if (empty($assigned_employees_list)) {
                    echo '<p>Nenhum técnico atribuído</p>';
                } else {
                    echo '<p>' . implode(', ', $assigned_employees_list) . '</p>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <div class="row mt-5">
        <div class="col-6">
            <div class="border-top pt-2">
                <p class="text-center">Assinatura do Técnico</p>
            </div>
        </div>
        <div class="col-6">
            <div class="border-top pt-2">
                <p class="text-center">Assinatura do Cliente</p>
            </div>
        </div>
    </div>
    
    <div class="print-footer">
        <p>SAMAPE - Sistema de Assistência Técnica e Manutenção de Maquinário Pesado</p>
        <p>Impresso em <?= date('d/m/Y H:i:s') ?></p>
    </div>
</div>
<?php endif; ?>

<?php
// Set validation flag for the footer to include validation script
$use_validation = true;

// Define page-specific JavaScript
$page_script = "
// Show/hide valor field based on status
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const valorContainer = document.getElementById('valor_container');
    
    if (statusSelect && valorContainer) {
        statusSelect.addEventListener('change', function() {
            if (this.value === '" . STATUS_COMPLETED . "') {
                valorContainer.style.display = 'block';
            } else {
                valorContainer.style.display = 'none';
            }
        });
    }
    
    // Handle print button
    const printButtons = document.querySelectorAll('.btn-print');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });
});
";

// Include page footer
include_once 'includes/footer.php';
?>

<script>
// Add scripts for service order page
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide valor and satisfaction fields based on status
    const statusSelect = document.getElementById('status');
    const valorContainer = document.getElementById('valor_container');
    const satisfactionContainer = document.getElementById('satisfaction_container');
    
    if (statusSelect && valorContainer && satisfactionContainer) {
        statusSelect.addEventListener('change', function() {
            const isCompleted = this.value === '<?= STATUS_COMPLETED ?>';
            valorContainer.style.display = isCompleted ? 'block' : 'none';
            satisfactionContainer.style.display = isCompleted ? 'block' : 'none';
        });
    }
});
</script>
