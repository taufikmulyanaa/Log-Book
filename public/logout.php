<?php
// public/logout.php
require_once __DIR__ . '/../config/init.php';

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metode tidak diizinkan.');
}

// Validasi CSRF Token
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    log_activity("CSRF token validation failed for logout attempt.");
    die('Aksi tidak diizinkan.');
}

log_activity("User logged out: " . ($_SESSION['user_name'] ?? 'Unknown'));

// Hancurkan semua data session
$_SESSION = [];

// Hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header('Location: login.php');
exit();