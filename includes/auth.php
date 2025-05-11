<?php
/**
 * Authentication and authorization functions
 */

/**
 * Verify if the user is logged in
 * Redirects to login page if not authenticated
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        // Store the requested URL for redirect after login
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Check if current user has required role
 * @param string|array $roles Required role(s)
 * @return bool True if user has permission, false otherwise
 */
function has_permission($roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    // If $roles is a string, convert to array
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require specific role to access a page
 * @param string|array $roles Required role(s)
 */
function require_permission($roles) {
    require_login();
    
    if (!has_permission($roles)) {
        $_SESSION['error'] = "Você não tem permissão para acessar esta página.";
        header("Location: " . BASE_URL . "/dashboard.php");
        exit;
    }
}

/**
 * Login a user
 * @param string $username User's username or email
 * @param string $password User's password
 * @return bool True if login successful, false otherwise
 */
function login_user($username, $password) {
    global $db;
    
    try {
        // Check if the input is a username or email based on format
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // It's an email
            $stmt = $db->prepare("SELECT id, nome, email, senha_hash, papel FROM usuarios WHERE email = ?");
        } else {
            // It's a username
            $stmt = $db->prepare("SELECT id, nome, email, senha_hash, papel FROM usuarios WHERE username = ?");
        }
        
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['senha_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['papel'];
            $_SESSION['last_activity'] = time();
            
            // Log activity
            log_activity($user['id'], 'login', 'Usuário fez login no sistema');
            
            return true;
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Logout the current user
 */
function logout_user() {
    // Log the logout activity if user is logged in
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'Usuário fez logout do sistema');
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Start a new session for flash messages
    session_start();
    $_SESSION['success'] = "Logout realizado com sucesso.";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

/**
 * Log user activity
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $description Description of the action
 */
function log_activity($user_id, $action, $description = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("INSERT INTO logs (usuario_id, acao, datahora) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$user_id, $action . ($description ? ': ' . $description : '')]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Generate CSRF token input field for forms
 * @return string HTML input field with CSRF token
 */
function csrf_token_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Validate CSRF token from POST request
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}
?>
