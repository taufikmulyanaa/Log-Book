<?php
// config/init.php

/**
 * File inisialisasi utama untuk aplikasi.
 * Menangani semua konfigurasi keamanan, koneksi database, dan fungsi helper.
 */

// 🔐 1. Versi & Konfigurasi
// Asumsikan php.ini sudah di-set dengan benar. Ini adalah fallback.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/app.log'); // Arahkan ke folder logs
error_reporting(E_ALL);

// Definisi konstanta keamanan
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', '15 minutes');

// Database Connection
$db_host = 'localhost';
$db_name = 'logbook_db_rnd'; // Pastikan ini sesuai dengan nama database Anda
$db_user = 'root'; // Ganti dengan user database Anda
$db_pass = '';     // Ganti dengan password Anda

$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Gunakan native prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    // Tampilkan pesan generik ke user, jangan bocorkan detail error.
    die('Terjadi masalah pada koneksi ke database. Silakan coba lagi nanti.');
}

// 🔑 3. Session & Authentication (Konfigurasi Aman)
// Panggil sebelum session_start()
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'lifetime' => 0, // Session berakhir saat browser ditutup
    'path' => '/',
    'domain' => '', // Ganti dengan domain Anda jika perlu
    'secure' => isset($_SERVER['HTTPS']), // 🌐 5. True jika menggunakan HTTPS
    'httponly' => true, // Mencegah akses cookie via JavaScript
    'samesite' => 'Strict' // 🌐 5. Proteksi CSRF paling ketat
]);

session_start();

// Regenerasi session ID secara periodik untuk mencegah session fixation
if (!isset($_SESSION['session_created_time'])) {
    $_SESSION['session_created_time'] = time();
} elseif (time() - $_SESSION['session_created_time'] > 1800) { // 30 menit
    session_regenerate_id(true); // Hapus session lama
    $_SESSION['session_created_time'] = time();
}

// 🌐 5. Fungsi Keamanan (CSRF Token)
function generate_csrf_token(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 🛡️ 2. Fungsi Keamanan (Output Escaping)
function esc_html(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 📊 7. Fungsi Logging
function log_activity(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] - {$message}" . PHP_EOL;
    
    // Buat folder logs jika belum ada
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(__DIR__ . '/../logs/app.log', $log_entry, FILE_APPEND);
}

// Inisialisasi CSRF token untuk setiap request
generate_csrf_token();