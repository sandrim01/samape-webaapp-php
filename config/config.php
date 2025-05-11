<?php
/**
 * Global configuration settings
 */

// Application settings
define('APP_NAME', 'SAMAPE - Sistema de Gestão');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Session configuration
define('SESSION_LIFETIME', 3600); // Session lifetime in seconds (1 hour)
define('SESSION_NAME', 'SAMAPE_SESSION');

// User roles
define('ROLE_ADMIN', 'administrador');
define('ROLE_MANAGER', 'gerente');
define('ROLE_EMPLOYEE', 'funcionario');

// Service order status
define('STATUS_OPEN', 'aberta');
define('STATUS_IN_PROGRESS', 'em_andamento');
define('STATUS_COMPLETED', 'concluida');
define('STATUS_CANCELLED', 'cancelada');

// Financial transaction types
define('TRANSACTION_INCOME', 'entrada');
define('TRANSACTION_EXPENSE', 'saida');

// Error reporting in development
// Set to 0 for production
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Default timezone
date_default_timezone_set('America/Sao_Paulo');

// Configure secure session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Secure in HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_name(SESSION_NAME);

// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) { // After 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Define CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}