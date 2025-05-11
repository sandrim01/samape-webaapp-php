<?php
/**
 * SAMAPE - Clients Management
 * Handles listing, adding, editing, and deleting clients
 */

// Page title
$page_title = "Gestão de Clientes";
$page_description = "Cadastro e gerenciamento de clientes";

// Include initialization file
require_once 'config/init.php';

// Require user to be logged in
require_login();

// Initialize variables
$error = '';
$success = '';
$clients = [];
$client = null;
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
        header("Location: " . BASE_URL . "/clients.php");
        exit;
    }
    
    // Handle client addition
    if (isset($_POST['add_client'])) {
        $nome = trim($_POST['nome']);
        $cnpj = trim($_POST['cnpj']);
        $telefone = trim($_POST['telefone']);
        $email = trim($_POST['email']);
        $endereco = trim($_POST['endereco']);
        
        // Basic validation
        if (empty($nome)) {
            $_SESSION['error'] = "O nome do cliente é obrigatório.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO clientes (nome, cnpj, telefone, email, endereco) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $cnpj, $telefone, $email, $endereco]);
                
                $client_id = $db->lastInsertId();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'cliente_adicionado', "Cliente ID: $client_id");
                
                $_SESSION['success'] = "Cliente adicionado com sucesso.";
                header("Location: " . BASE_URL . "/clients.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Error adding client: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao adicionar cliente. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the form
        header("Location: " . BASE_URL . "/clients.php?action=add");
        exit;
    }
    
    // Handle client update
    if (isset($_POST['update_client']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome']);
        $cnpj = trim($_POST['cnpj']);
        $telefone = trim($_POST['telefone']);
        $email = trim($_POST['email']);
        $endereco = trim($_POST['endereco']);
        
        // Basic validation
        if (empty($nome)) {
            $_SESSION['error'] = "O nome do cliente é obrigatório.";
        } else {
            try {
                $stmt = $db->prepare("UPDATE clientes SET nome = ?, cnpj = ?, telefone = ?, email = ?, endereco = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$nome, $cnpj, $telefone, $email, $endereco, $id]);
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'cliente_atualizado', "Cliente ID: $id");
                
                $_SESSION['success'] = "Cliente atualizado com sucesso.";
                header("Location: " . BASE_URL . "/clients.php");
                exit;
                
            } catch (PDOException $e) {
                error_log("Error updating client: " . $e->getMessage());
                $_SESSION['error'] = "Erro ao atualizar cliente. Verifique os dados e tente novamente.";
            }
        }
        
        // Redirect back to the edit form
        header("Location: " . BASE_URL . "/clients.php?action=edit&id=$id");
        exit;
    }
    
    // Handle client deletion
    if (isset($_POST['delete_client']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            // Check if client has machinery
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM maquinarios WHERE cliente_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $_SESSION['error'] = "Este cliente possui maquinários registrados. Remova-os primeiro.";
                header("Location: " . BASE_URL . "/clients.php");
                exit;
            }
            
            // Check if client has service orders
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM ordens_servico WHERE cliente_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $_SESSION['error'] = "Este cliente possui ordens de serviço. Não é possível removê-lo.";
                header("Location: " . BASE_URL . "/clients.php");
                exit;
            }
            
            // Delete the client
            $stmt = $db->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'cliente_removido', "Cliente ID: $id");
            
            $_SESSION['success'] = "Cliente removido com sucesso.";
            
        } catch (PDOException $e) {
            error_log("Error deleting client: " . $e->getMessage());
            $_SESSION['error'] = "Erro ao remover cliente.";
        }
        
        header("Location: " . BASE_URL . "/clients.php");
        exit;
    }
}

// Handle edit action - load client data
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            $_SESSION['error'] = "Cliente não encontrado.";
            header("Location: " . BASE_URL . "/clients.php");
            exit;
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching client: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar dados do cliente.";
        header("Location: " . BASE_URL . "/clients.php");
        exit;
    }
}

// Fetch all clients for listing
if ($action === 'list') {
    try {
        // Pagination setup
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;
        
        // Get total count for pagination
        $stmt = $db->query("SELECT COUNT(*) as count FROM clientes");
        $total_clients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_pages = ceil($total_clients / $limit);
        
        // Get clients for current page
        $stmt = $db->prepare("SELECT * FROM clientes ORDER BY nome LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching clients: " . $e->getMessage());
        $_SESSION['error'] = "Erro ao carregar lista de clientes.";
    }
}

// Include page header
include_once 'includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Client List Page -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Lista de Clientes</h5>
        <div>
            <a href="<?= BASE_URL ?>/clients.php?action=add" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Novo Cliente
            </a>
            <a href="<?= BASE_URL ?>/includes/export.php?type=clients" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Exportar
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Search and filters -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" id="search-input" class="form-control" placeholder="Buscar clientes...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Clients table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped searchable-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>CNPJ/CPF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum cliente encontrado.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['nome']) ?></td>
                        <td><?= htmlspecialchars($c['cnpj']) ?></td>
                        <td><?= htmlspecialchars($c['telefone']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>/machinery.php?client_id=<?= $c['id'] ?>" class="btn btn-info btn-sm" title="Maquinários">
                                <i class="fas fa-cogs"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/clients.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-primary btn-sm" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $c['id'] ?>" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?= $c['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $c['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel<?= $c['id'] ?>">Confirmar Exclusão</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            Tem certeza que deseja excluir o cliente <strong><?= htmlspecialchars($c['nome']) ?></strong>?
                                            <p class="text-danger mt-2">Esta ação não poderá ser desfeita.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="<?= BASE_URL ?>/clients.php" method="post">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                                <button type="submit" name="delete_client" class="btn btn-danger">Excluir</button>
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
                    <a class="page-link" href="<?= BASE_URL ?>/clients.php?page=<?= $page - 1 ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/clients.php?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= BASE_URL ?>/clients.php?page=<?= $page + 1 ?>" aria-label="Próximo">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>
<!-- Add Client Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Adicionar Novo Cliente</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/clients.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label required">Nome/Razão Social</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                    <div class="invalid-feedback">
                        Por favor, informe o nome do cliente.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ/CPF</label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" data-mask="document" data-validate="document">
                    <div class="invalid-feedback">
                        CNPJ/CPF inválido.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" data-mask="phone" data-validate="phone">
                    <div class="invalid-feedback">
                        Telefone inválido.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email">
                    <div class="invalid-feedback">
                        Email inválido.
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="endereco" class="form-label">Endereço</label>
                <textarea class="form-control" id="endereco" name="endereco" rows="3"></textarea>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/clients.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="add_client" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'edit' && $client): ?>
<!-- Edit Client Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Editar Cliente</h5>
    </div>
    <div class="card-body">
        <form action="<?= BASE_URL ?>/clients.php" method="post" class="needs-validation" novalidate>
            <?= csrf_token_input() ?>
            <input type="hidden" name="id" value="<?= $client['id'] ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label required">Nome/Razão Social</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($client['nome']) ?>" required>
                    <div class="invalid-feedback">
                        Por favor, informe o nome do cliente.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ/CPF</label>
                    <input type="text" class="form-control" id="cnpj" name="cnpj" value="<?= htmlspecialchars($client['cnpj']) ?>" data-mask="document" data-validate="document">
                    <div class="invalid-feedback">
                        CNPJ/CPF inválido.
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($client['telefone']) ?>" data-mask="phone" data-validate="phone">
                    <div class="invalid-feedback">
                        Telefone inválido.
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($client['email']) ?>">
                    <div class="invalid-feedback">
                        Email inválido.
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="endereco" class="form-label">Endereço</label>
                <textarea class="form-control" id="endereco" name="endereco" rows="3"><?= htmlspecialchars($client['endereco']) ?></textarea>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/clients.php" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" name="update_client" class="btn btn-primary">Atualizar</button>
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
