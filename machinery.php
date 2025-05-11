<?php
/**
 * SAMAPE - Machinery Management
 * Handles listing, adding, editing, and deleting machinery
 */

// Page title
$page_title = "Gestão de Maquinário";
$page_description = "Cadastro e gerenciamento de maquinário";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in
require_login();

// Initialize variables
$error = '';
$success = '';
$machinery_list = [];
$machinery = null;
$action = 'list';
$client_id = 0;
$client_name = '';

// Handle different actions (list, add, edit, delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

// Get client_id if specified
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $client_id = (int)$_GET['client_id'];
    
    // Get client name
    try {
        $stmt = $db->prepare("SELECT nome FROM clientes WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $client_name = $client['nome'];
            $page_description = "Maquinário de " . htmlspecialchars($client_name);
        } else {
            $_SESSION['error'] = "Cliente não encontrado.";
            header("Location: " . BASE_URL . "/clients.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching client: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados do cliente.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/machinery.php");
        exit;
    }
    
    // Handle machinery addition
    if (isset($_POST['add_machinery'])) {
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        $tipo = trim($_POST['tipo']);
        $marca = trim($_POST['marca']);
        $modelo = trim($_POST['modelo']);
        $numero_serie = trim($_POST['numero_serie']);
        $ano = trim($_POST['ano']);
        $ultima_manutencao = !empty($_POST['ultima_manutencao']) ? format_date_mysql($_POST['ultima_manutencao']) : null;
        
        // Basic validation
        if (empty($cliente_id) || empty($tipo) || empty($marca) || empty($modelo)) {
            $_SESSION['error'] = "Por favor, preencha todos os campos obrigatórios.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO maquinarios (cliente_id, tipo, marca, modelo, numero_serie, ano, ultima_manutencao) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cliente_id, $tipo, $marca, $modelo, $numero_serie, $ano, $ultima_manutencao]);
                
                $machinery_id = $db->lastInsertId();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'maquinario_adicionado', "Maquinário ID: $machinery_id, Cliente ID: $cliente_id");
                
                $_SESSION['success'] = "Maquinário adicionado com sucesso.";
                header("Location: " . BASE_URL . "/machinery.php" . ($cliente_id ? "?client_id=$cliente_id" : ""));
                exit;
                
            } catch (PDOException $e) {
                error_log("Error adding machinery: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao adicionar maquinário. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the form
        header("Location: " . BASE_URL . "/machinery.php?action=add" . ($cliente_id ? "&client_id=$cliente_id" : ""));
        exit;
    }
    
    // Handle machinery update
    if (isset($_POST['update_machinery']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        $tipo = trim($_POST['tipo']);
        $marca = trim($_POST['marca']);
        $modelo = trim($_POST['modelo']);
        $numero_serie = trim($_POST['numero_serie']);
        $ano = trim($_POST['ano']);
        $ultima_manutencao = !empty($_POST['ultima_manutencao']) ? format_date_mysql($_POST['ultima_manutencao']) : null;
        
        // Basic validation
        if (empty($cliente_id) || empty($tipo) || empty($marca) || empty($modelo)) {
            $_SESSION['error'] = "Por favor, preencha todos os campos obrigatórios.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE maquinarios SET cliente_id = ?, tipo = ?, marca = ?, modelo = ?, numero_serie = ?, ano = ?, ultima_manutencao = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$cliente_id, $tipo, $marca, $modelo, $numero_serie, $ano, $ultima_manutencao, $id]);
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'maquinario_atualizado', "Maquinário ID: $id, Cliente ID: $cliente_id");
                
                $_SESSION['success'] = "Maquinário atualizado com sucesso.";
                header("Location: " . BASE_URL . "/machinery.php" . ($cliente_id ? "?client_id=$cliente_id" : ""));
                exit;
                
            } catch (PDOException $e) {
                error_log("Error updating machinery: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao atualizar maquinário. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the edit form
        header("Location: " . BASE_URL . "/machinery.php?action=edit&id=$id" . ($cliente_id ? "&client_id=$cliente_id" : ""));
        exit;
    }
    
    // Handle machinery deletion
    if (isset($_POST['delete_machinery']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        
        try {
            // Check if machinery is used in service orders
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM ordens_servico WHERE maquinario_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $_SESSION['error'] = "Este maquinário está associado a ordens de serviço. Não é possível removê-lo.";
                header("Location: " . BASE_URL . "/machinery.php" . ($cliente_id ? "?client_id=$cliente_id" : ""));
                exit;
            }
            
            // Delete the machinery
            $stmt = $db->prepare("DELETE FROM maquinarios WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'maquinario_removido', "Maquinário ID: $id");
            
            $_SESSION['success'] = "Maquinário removido com sucesso.";
            
        } catch (PDOException $e) {
            error_log("Error deleting machinery: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao remover maquinário.";
        }
        
        header("Location: " . BASE_URL . "/machinery.php" . ($cliente_id ? "?client_id=$cliente_id" : ""));
        exit;
    }
}

// Handle edit action - load machinery data
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT m.*, c.nome as client_name FROM maquinarios m JOIN clientes c ON m.cliente_id = c.id WHERE m.id = ?");
        $stmt->execute([$id]);
        $machinery = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$machinery) {
            $_SESSION['error'] = "Maquinário não encontrado.";
            header("Location: " . BASE_URL . "/machinery.php" . ($client_id ? "?client_id=$client_id" : ""));
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching machinery: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados do maquinário.";
        header("Location: " . BASE_URL . "/machinery.php" . ($client_id ? "?client_id=$client_id" : ""));
        exit;
    }
}

// Fetch machinery list for display
if ($action === 'list') {
    try {
        // Pagination setup
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Build query based on whether we're filtering by client
        $query_count = "SELECT COUNT(*) as count FROM maquinarios m JOIN clientes c ON m.cliente_id = c.id";
        $query = "SELECT m.*, c.nome as client_name FROM maquinarios m JOIN clientes c ON m.cliente_id = c.id";
        
        if ($client_id) {
            $query_count .= " WHERE m.cliente_id = ?";
            $query .= " WHERE m.cliente_id = ?";
        }
        
        $query .= " ORDER BY c.nome, m.tipo, m.marca LIMIT ? OFFSET ?";
        
        // Get total count for pagination
        $stmt = $db->prepare($query_count);
        if ($client_id) {
            $stmt->execute([$client_id]);
        } else {
            $stmt->execute();
        }
        $total_machinery = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_pages = ceil($total_machinery / $limit);
        
        // Get machinery list
        $stmt = $db->prepare($query);
        if ($client_id) {
            $stmt->bindValue(1, $client_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        $machinery_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching machinery: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar lista de maquinários.";
    }
}

// Get clients list for dropdown
$clients = [];
try {
    $stmt = $db->query("SELECT id, nome FROM clientes ORDER BY nome");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching clients: " . $e->getMessage());
}

// Include page header
include_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Machinery List Page -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <?= $client_id ? "Maquinário de " . htmlspecialchars($client_name) : "Lista de Maquinários" ?>
        </h5>
        <div>
            <a href="<?= BASE_URL ?>/machinery.php?action=add<?= $client_id ? "&client_id=$client_id" : "" ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Novo Maquinário
            </a>
            <a href="<?= BASE_URL ?>/includes/export.php?type=machinery<?= $client_id ? "&client_id=$client_id" : "" ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Exportar
            </a>
            <?php if ($client_id): ?>
            <a href="<?= BASE_URL ?>/clients.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Voltar para Clientes
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <!-- Search and filters -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar maquinários...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <?php if (!$client_id): ?>
            <div class="col-md-6">
                <select class="form-select" id="client-filter" onchange="window.location.href=this.value">
                    <option value="<?= BASE_URL ?>/machinery.php">Todos os Clientes</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= BASE_URL ?>/machinery.php?client_id=<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Machinery table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped searchable-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if (!$client_id): ?>
                        <th>Cliente</th>
                        <?php endif; ?>
                        <th>Tipo</th>
                        <th>Marca/Modelo</th>
                        <th>Número de Série</th>
                        <th>Ano</th>
                        <th>Última Manutenção</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($machinery_list)): ?>
                    <tr>
                        <td colspan="<?= $client_id ? 7 : 8 ?>" class="text-center">Nenhum maquinário encontrado.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($machinery_list as $m): ?>
                    <tr>
                        <td><?= $m['id'] ?></td>
                        <?php if (!$client_id): ?>
                        <td><?= htmlspecialchars($m['client_name']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($m['tipo']) ?></td>
                        <td><?= htmlspecialchars($m['marca']) ?> <?= htmlspecialchars($m['modelo']) ?></td>
                        <td><?= htmlspecialchars($m['numero_serie']) ?></td>
                        <td><?= htmlspecialchars($m['ano']) ?></td>
                        <td><?= format_date($m['ultima_manutencao']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>/machinery.php?action=edit&id=<?= $m['id'] ?><?= $client_id ? "&client_id=$client_id" : "" ?>" class="btn btn-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $m['id'] ?>" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $m['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $m['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $m['id'] ?>">Confirmar Exclusão</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Tem certeza que deseja excluir o maquinário <strong><?= htmlspecialchars($m['tipo']) ?> <?= htmlspecialchars($m['marca']) ?> <?= htmlspecialchars($m['modelo']) ?></strong>?
                                            <p class="text-danger mt-2">Esta ação não poderá ser desfeita.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="<?= BASE_URL ?>/machinery.php" method="post">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                <?php if ($client_id): ?>
                                                <input type="hidden" name="cliente_id" value="<?= $client_id ?>">
                                                <?php endif; ?>
                                                <button type="submit" name="delete_machinery" class="btn btn-danger">Excluir</button>
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
                    <a class="page-link" href="<?= BASE_URL ?>/machinery.php?page=<?= $page - 1 ?><?= $client_id ? "&client_id=$client_id" : "" ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/machinery.php?page=<?= $i ?><?= $client_id ? "&client_id=$client_id" : "" ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/machinery.php?page=<?= $page + 1 ?><?= $client_id ? "&client_id=$client_id" : "" ?>" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>
<!-- Add Machinery Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Adicionar Novo Maquinário</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/machinery.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label required">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required <?= $client_id ? 'disabled' : '' ?>>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $client_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($client_id): ?>
                    <input type="hidden" name="cliente_id" value="<?= $client_id ?>">
                    <?php endif; ?>
                    <div class="invalid-feedback">
                        Por favor, selecione um cliente.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="tipo" class="form-label required">Tipo de Maquinário</label>
                    <input type="text" class="form-control" id="tipo" name="tipo" required>
                    <div class="invalid-feedback">
                        Por favor, informe o tipo de maquinário.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="marca" class="form-label required">Marca</label>
                    <input type="text" class="form-control" id="marca" name="marca" required>
                    <div class="invalid-feedback">
                        Por favor, informe a marca.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="modelo" class="form-label required">Modelo</label>
                    <input type="text" class="form-control" id="modelo" name="modelo" required>
                    <div class="invalid-feedback">
                        Por favor, informe o modelo.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="numero_serie" class="form-label">Número de Série</label>
                    <input type="text" class="form-control" id="numero_serie" name="numero_serie">
                </div>
                <div class="col-md-6">
                    <label for="ano" class="form-label">Ano de Fabricação</label>
                    <input type="number" class="form-control" id="ano" name="ano" min="1900" max="<?= date('Y') ?>">
                    <div class="invalid-feedback">
                        Por favor, informe um ano válido.
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="ultima_manutencao" class="form-label">Última Manutenção</label>
                <input type="date" class="form-control" id="ultima_manutencao" name="ultima_manutencao" max="<?= date('Y-m-d') ?>">
                <div class="invalid-feedback">
                    Data inválida.
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/machinery.php<?= $client_id ? "?client_id=$client_id" : "" ?>" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="add_machinery" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && $machinery): ?>
<!-- Edit Machinery Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Editar Maquinário</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/machinery.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            <input type="hidden" name="id" value="<?= $machinery['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label required">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required <?= $client_id ? 'disabled' : '' ?>>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $machinery['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($client_id): ?>
                    <input type="hidden" name="cliente_id" value="<?= $client_id ?>">
                    <?php endif; ?>
                    <div class="invalid-feedback">
                        Por favor, selecione um cliente.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="tipo" class="form-label required">Tipo de Maquinário</label>
                    <input type="text" class="form-control" id="tipo" name="tipo" value="<?= htmlspecialchars($machinery['tipo']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe o tipo de maquinário.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="marca" class="form-label required">Marca</label>
                    <input type="text" class="form-control" id="marca" name="marca" value="<?= htmlspecialchars($machinery['marca']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe a marca.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="modelo" class="form-label required">Modelo</label>
                    <input type="text" class="form-control" id="modelo" name="modelo" value="<?= htmlspecialchars($machinery['modelo']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe o modelo.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="numero_serie" class="form-label">Número de Série</label>
                    <input type="text" class="form-control" id="numero_serie" name="numero_serie" value="<?= htmlspecialchars($machinery['numero_serie']) ?>">
                </div>
                <div class="col-md-6">
                    <label for="ano" class="form-label">Ano de Fabricação</label>
                    <input type="number" class="form-control" id="ano" name="ano" min="1900" max="<?= date('Y') ?>" value="<?= htmlspecialchars($machinery['ano']) ?>">
                    <div class="invalid-feedback">
                        Por favor, informe um ano válido.
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="ultima_manutencao" class="form-label">Última Manutenção</label>
                <input type="date" class="form-control" id="ultima_manutencao" name="ultima_manutencao" max="<?= date('Y-m-d') ?>" value="<?= $machinery['ultima_manutencao'] ?>">
                <div class="invalid-feedback">
                    Data inválida.
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/machinery.php<?= $client_id ? "?client_id=$client_id" : "" ?>" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="update_machinery" class="btn btn-primary">Atualizar</button>
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
