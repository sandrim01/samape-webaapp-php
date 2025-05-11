<?php
/**
 * SAMAPE - Logout Page
 * Handles user logout
 */

// Include initialization file
require_once 'config/init.php';

// Logout user
logout_user();
// The logout_user function already handles session destruction and redirection
?>
