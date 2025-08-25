<?php
require_once __DIR__ . '/../config/init.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die('Method Not Allowed'); }
if (!isset($_SESSION['user_id'])) { die('Unauthorized'); }
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { die('CSRF Token Error'); }

// Sanitize and validate inputs
$user_id = $_SESSION['user_id'];
$instrument_id = filter_input(INPUT_POST, 'instrument_id', FILTER_VALIDATE_INT);
// $activity dihapus
$log_book_code = trim($_POST['log_book_code'] ?? '');
$sample_name = trim($_POST['sample_name'] ?? null);
$trial_code = trim($_POST['trial_code'] ?? null);
$start_date = trim($_POST['start_date'] ?? '');
$start_time = trim($_POST['start_time'] ?? '');
$finish_date = !empty($_POST['finish_date']) ? trim($_POST['finish_date']) : null;
$finish_time = !empty($_POST['finish_time']) ? trim($_POST['finish_time']) : null;
$condition_after = trim($_POST['condition_after'] ?? null);
$remark = trim($_POST['remark'] ?? null);
$status = trim($_POST['status'] ?? 'Not Complete');
$params = $_POST['params'] ?? [];

// Basic validation (activity dihapus dari validasi)
if (empty($instrument_id) || empty($start_date) || empty($start_time)) {
    header('Location: entry.php?error=' . urlencode('Instrument and Start Date/Time are required.'));
    exit();
}

// Prepare data for insertion (activity dihapus dari array)
$data = [
    'user_id' => $user_id,
    'instrument_id' => $instrument_id,
    'log_book_code' => $log_book_code,
    'sample_name' => $sample_name,
    'trial_code' => $trial_code,
    'start_date' => $start_date,
    'start_time' => $start_time,
    'finish_date' => $finish_date,
    'finish_time' => $finish_time,
    'condition_after' => $condition_after,
    'remark' => $remark,
    'status' => $status,
];

// Whitelist and sanitize dynamic parameters
$parameter_columns = [
    'MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 
    'Diluent', 'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'
];
foreach ($parameter_columns as $param) {
    $snake_case_param = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $param));
    $db_column_name = $snake_case_param . '_val';
    $data[$db_column_name] = isset($params[$param]) && is_string($params[$param]) ? trim($params[$param]) : null;
}

// Build SQL statement
$columns = "`" . implode("`, `", array_keys($data)) . "`";
$placeholders = ":" . implode(", :", array_keys($data));
$sql = "INSERT INTO logbook_entries ($columns) VALUES ($placeholders)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    log_activity("New logbook entry created (ID: {$pdo->lastInsertId()}) by user ID: {$user_id}");
} catch (PDOException $e) {
    log_activity("Error creating logbook entry: " . $e->getMessage());
    header('Location: entry.php?error=' . urlencode('Database error.'));
    exit();
}

// Redirect on success
header('Location: logbook_list.php?success=1');
exit();