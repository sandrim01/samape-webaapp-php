<?php
/**
 * SAMAPE - Login Page
 * Handles user authentication
 */

// Page title
$page_title = "Login";

// Include initialization file
require_once 'config/init.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token()) {
        $_SESSION['error'] = "Erro de validação do formulário. Tente novamente.";
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Por favor, preencha todos os campos.";
    } else {
        // Attempt login
        if (login_user($username, $password)) {
            // Successful login, redirect to originally requested page or dashboard
            $redirect_to = $_SESSION['redirect_to'] ?? BASE_URL . "/dashboard.php";
            unset($_SESSION['redirect_to']);
            
            header("Location: " . $redirect_to);
            exit;
        } else {
            $_SESSION['error'] = "Email ou senha incorretos. Tente novamente.";
        }
    }
}

// Include page header
include_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="login-container">
            <div class="login-logo text-center mb-4">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="SAMAPE Logo" class="img-fluid mb-4" style="max-height: 200px;">
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4">Acesso ao Sistema</h4>
                    
                    <form method="POST" action="<?= BASE_URL ?>/login.php" class="needs-validation" novalidate>
                        <?= csrf_token_input() ?>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Nome de Usuário</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Nome de usuário" required>
                            </div>
                            <div class="invalid-feedback">
                                Por favor, informe seu nome de usuário.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Senha</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Por favor, informe sua senha.
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Lembrar acesso</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Entrar
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Em caso de dificuldade, contate o administrador do sistema.</small>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">&copy; <?= date('Y') ?> - SAMAPE - Assistência Técnica</small>
            </div>
        </div>
    </div>
</div>

<?php
// Set validation flag for the footer to include validation script
$use_validation = true;

// Include page footer
include_once 'includes/footer.php';
?>
