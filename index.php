<?php
/**
 * SAMAPE - System Entry Point
 * Redirects to login or dashboard based on authentication status
 */

// Include initialization file
require_once 'config/init.php';

// Redirect to dashboard if user is logged in, otherwise to login page
// Using HTML meta refresh as a fallback since headers might already be sent
if (isset($_SESSION['user_id'])) {
    $redirect_url = BASE_URL . "/dashboard.php";
} else {
    $redirect_url = BASE_URL . "/login.php";
}

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
?>
