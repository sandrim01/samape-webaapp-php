<?php
/**
 * SAMAPE - Employees Management
 * Handles listing, adding, editing, and deleting employees
 */

// Page title
$page_title = "Gestão de Funcionários";
$page_description = "Cadastro e gerenciamento de funcionários";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in with appropriate permissions
require_permission([ROLE_ADMIN, ROLE_MANAGER]);

// Initialize variables
$error = '';
$success = '';
$employees_list = [];
$employee = null;
$action = 'list';

// Handle different actions (list, add, edit, delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/employees.php");
        exit;
    }
    
    // Handle employee addition
    if (isset($_POST['add_employee'])) {
        $nome = trim($_POST['nome']);
        $cargo = trim($_POST['cargo']);
        $email = trim($_POST['email']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Basic validation
        if (empty($nome) || empty($cargo)) {
            $_SESSION['error'] = "Nome e cargo são campos obrigatórios.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "O email informado é inválido.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO funcionarios (nome, cargo, email, ativo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $cargo, $email, $ativo]);
                
                $employee_id = $db->lastInsertId();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'funcionario_adicionado', "Funcionário ID: $employee_id");
                
                $_SESSION['success'] = "Funcionário adicionado com sucesso.";
                header("Location: " . BASE_URL . "/employees.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Error adding employee: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao adicionar funcionário. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the form
        header("Location: " . BASE_URL . "/employees.php?action=add");
        exit;
    }
    
    // Handle employee update
    if (isset($_POST['update_employee']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome']);
        $cargo = trim($_POST['cargo']);
        $email = trim($_POST['email']);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Basic validation
        if (empty($nome) || empty($cargo)) {
            $_SESSION['error'] = "Nome e cargo são campos obrigatórios.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "O email informado é inválido.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE funcionarios SET nome = ?, cargo = ?, email = ?, ativo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$nome, $cargo, $email, $ativo, $id]);
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'funcionario_atualizado', "Funcionário ID: $id");
                
                $_SESSION['success'] = "Funcionário atualizado com sucesso.";
                header("Location: " . BASE_URL . "/employees.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Error updating employee: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao atualizar funcionário. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the edit form
        header("Location: " . BASE_URL . "/employees.php?action=edit&id=$id");
        exit;
    }
    
    // Handle employee status toggle
    if (isset($_POST['toggle_employee_status']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            // Get current status
            $stmt = $db->prepare("SELECT ativo FROM funcionarios WHERE id = ?");
            $stmt->execute([$id]);
            $current_status = $stmt->fetch(PDO::FETCH_ASSOC)['ativo'];
            
            // Toggle status
            $new_status = $current_status ? 0 : 1;
            
            $stmt = $db->prepare("UPDATE funcionarios SET ativo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            
            // Log the activity
            $status_text = $new_status ? "ativado" : "desativado";
            log_activity($_SESSION['user_id'], 'funcionario_status_alterado', "Funcionário ID: $id - $status_text");
            
            $_SESSION['success'] = "Status do funcionário alterado com sucesso.";
            
        } catch (PDOException $e) {
            error_log("Error toggling employee status: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao alterar status do funcionário.";
        }
        
        header("Location: " . BASE_URL . "/employees.php");
        exit;
    }
}

// Handle edit action - load employee data
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            $_SESSION['error'] = "Funcionário não encontrado.";
            header("Location: " . BASE_URL . "/employees.php");
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching employee: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados do funcionário.";
        header("Location: " . BASE_URL . "/employees.php");
        exit;
    }
}

// Fetch employees list for display
if ($action === 'list') {
    try {
        // Pagination setup
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Build query based on filters
        $query_where = "";
        $query_params = [];
        
        // Apply active/inactive filter if set
        if (isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'])) {
            $status_filter = ($_GET['status'] === 'active') ? 1 : 0;
            $query_where = " WHERE ativo = ?";
            $query_params[] = $status_filter;
        }
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as count FROM funcionarios" . $query_where;
        $stmt = $db->prepare($count_query);
        if (!empty($query_params)) {
            $stmt->execute($query_params);
        } else {
            $stmt->execute();
        }
        $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_pages = ceil($total_employees / $limit);
        
        // Get employees for current page
        $query = "SELECT * FROM funcionarios" . $query_where . " ORDER BY nome LIMIT ? OFFSET ?";
        $query_params[] = $limit;
        $query_params[] = $offset;
        
        $stmt = $db->prepare($query);
        $stmt->execute($query_params);
        $employees_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar lista de funcionários.";
    }
}

// Include page header
include_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Employees List Page -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Lista de Funcionários</h5>
        <div>
            <a href="<?= BASE_URL ?>/employees.php?action=add" class="btn btn-success btn-sm">
                <i class="fas fa-user-plus"></i> Novo Funcionário
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Search and filters -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar funcionários...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <select class="form-select" id="status-filter" onchange="window.location.href=this.value">
                    <option value="<?= BASE_URL ?>/employees.php">Todos os Funcionários</option>
                    <option value="<?= BASE_URL ?>/employees.php?status=active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>
                        Funcionários Ativos
                    </option>
                    <option value="<?= BASE_URL ?>/employees.php?status=inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>
                        Funcionários Inativos
                    </option>
                </select>
            </div>
        </div>
        
        <!-- Employees table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped searchable-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Cargo</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees_list)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum funcionário encontrado.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees_list as $emp): ?>
                    <tr>
                        <td><?= $emp['id'] ?></td>
                        <td><?= htmlspecialchars($emp['nome']) ?></td>
                        <td><?= htmlspecialchars($emp['cargo']) ?></td>
                        <td><?= htmlspecialchars($emp['email']) ?></td>
                        <td>
                            <span class="badge bg-<?= $emp['ativo'] ? 'success' : 'danger' ?>">
                                <?= $emp['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>/employees.php?action=edit&id=<?= $emp['id'] ?>" class="btn btn-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="<?= BASE_URL ?>/employees.php" method="post" class="d-inline">
                                <?= csrf_token_input() ?>
                                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                <button type="submit" name="toggle_employee_status" class="btn btn-<?= $emp['ativo'] ? 'warning' : 'success' ?> btn-sm" title="<?= $emp['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                    <i class="fas fa-<?= $emp['ativo'] ? 'ban' : 'check' ?>"></i>
                                </button>
                            </form>
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
                    <a class="page-link" href="<?= BASE_URL ?>/employees.php?page=<?= $page - 1 ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/employees.php?page=<?= $i ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/employees.php?page=<?= $page + 1 ?><?= isset($_GET['status']) ? "&status=" . $_GET['status'] : "" ?>" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>
<!-- Add Employee Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Adicionar Novo Funcionário</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/employees.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label required">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                    <div class="invalid-feedback">
                        Por favor, informe o nome do funcionário.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="cargo" class="form-label required">Cargo</label>
                    <input type="text" class="form-control" id="cargo" name="cargo" required>
                    <div class="invalid-feedback">
                        Por favor, informe o cargo do funcionário.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email">
                    <div class="invalid-feedback">
                        Por favor, informe um email válido.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" checked>
                        <label class="form-check-label" for="ativo">Funcionário Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/employees.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="add_employee" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && $employee): ?>
<!-- Edit Employee Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Editar Funcionário</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/employees.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            <input type="hidden" name="id" value="<?= $employee['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label required">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($employee['nome']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe o nome do funcionário.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="cargo" class="form-label required">Cargo</label>
                    <input type="text" class="form-control" id="cargo" name="cargo" value="<?= htmlspecialchars($employee['cargo']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe o cargo do funcionário.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($employee['email']) ?>">
                    <div class="invalid-feedback">
                        Por favor, informe um email válido.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" <?= $employee['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativo">Funcionário Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/employees.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="update_employee" class="btn btn-primary">Atualizar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
// Set validation flag for the footer to include validation script
$use_validation = true;

// Include page footer
include_once 'includes/footer.php';
?>
