<?php
/**
 * SAMAPE - System Entry Point
 * Redirects to login or dashboard based on authentication status
 */

// Include initialization file
require_once 'config/init.php';

// Redirect to dashboard if user is logged in, otherwise to login page
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/login.php");
}
exit;
?>
