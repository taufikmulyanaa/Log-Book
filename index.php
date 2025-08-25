<?php
/**
 * Main Entry Point for R&D Logbook System
 * Place this file in the root directory (/log-book/index.php)
 */

// Security check - ensure this is the main entry point
if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
    http_response_code(403);
    die('Direct access not allowed.');
}

// Include configuration
require_once __DIR__ . '/config/init.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: public/index.php');
    exit();
} else {
    // User not logged in, redirect to login
    header('Location: public/login.php');
    exit();
}
?>