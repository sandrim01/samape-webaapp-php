<?php
/**
 * Initialization file
 * Includes all required files and initializes the application
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration files
require_once 'config.php';
require_once 'database.php';

// Include utility files
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Ensure that the database schema exists
$database->initialize_schema();

// Check session timeout
check_session_timeout();

// Set up error handling
set_error_handler('custom_error_handler');

/**
 * Custom error handler function
 */
function custom_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }

    // Log the error
    error_log("Error ($severity): $message in $file on line $line");

    // Display error message for development
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>An error occurred. Please try again later.</div>";
    }

    // Don't execute PHP internal error handler
    return true;
}

/**
 * Check if session has timed out
 */
function check_session_timeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        // Session expired
        session_unset();     // unset $_SESSION variable
        session_destroy();   // destroy session data
        
        // Redirect to login page
        $redirect_url = BASE_URL . "/login.php?timeout=1";
        
        // Try headers first (might fail if output already started)
        if (!headers_sent()) {
            header("Location: " . $redirect_url);
            exit;
        } else {
            // Fallback to meta refresh
            echo '<meta http-equiv="refresh" content="0;url=' . $redirect_url . '">';
            echo '<script>window.location.href="' . $redirect_url . '";</script>';
            echo 'If you are not redirected automatically, please <a href="' . $redirect_url . '">click here</a>.';
            exit;
        }
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}
?>
