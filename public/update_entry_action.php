<?php
// public/update_entry_action.php
require_once __DIR__ . '/../config/init.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die('Method Not Allowed'); }
if (!isset($_SESSION['user_id'])) { die('Unauthorized'); }
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { die('CSRF Token Error'); }

$entry_id = filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT);
if (!$entry_id) {
    die('Invalid entry ID.');
}

// Check if user can edit this entry
$stmt = $pdo->prepare("SELECT user_id FROM logbook_entries WHERE id = :id");
$stmt->execute(['id' => $entry_id]);
$entry = $stmt->fetch();

if (!$entry) { die('Entry not found.'); }

if ($entry['user_id'] != $_SESSION['user_id'] && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
    die('You do not have permission to edit this entry.');
}

// Get and validate input
$instrument_id = filter_input(INPUT_POST, 'instrument_id', FILTER_VALIDATE_INT);
// $activity dihapus
$sample_name = trim($_POST['sample_name'] ?? null);
$trial_code = trim($_POST['trial_code'] ?? null);
$start_date = trim($_POST['start_date'] ?? '');
$start_time = trim($_POST['start_time'] ?? '');
$finish_date = !empty($_POST['finish_date']) ? trim($_POST['finish_date']) : null;
$finish_time = !empty($_POST['finish_time']) ? trim($_POST['finish_time']) : null;
$condition_after = trim($_POST['condition_after'] ?? null);
$remark = trim($_POST['remark'] ?? null);
$status = trim($_POST['status'] ?? 'Not Complete');

// Validate required fields (activity dihapus dari validasi)
if (empty($instrument_id) || empty($start_date) || empty($start_time)) {
    die('Required fields cannot be empty.');
}

// Prepare data for update (activity dihapus dari array)
$data = [
    'instrument_id' => $instrument_id,
    'sample_name' => $sample_name,
    'trial_code' => $trial_code,
    'start_date' => $start_date,
    'start_time' => $start_time,
    'finish_date' => $finish_date,
    'finish_time' => $finish_time,
    'condition_after' => $condition_after,
    'remark' => $remark,
    'status' => $status,
    'entry_id' => $entry_id
];

// Update query (activity dihapus dari query)
$sql = "UPDATE logbook_entries SET 
        instrument_id = :instrument_id,
        sample_name = :sample_name,
        trial_code = :trial_code,
        start_date = :start_date,
        start_time = :start_time,
        finish_date = :finish_date,
        finish_time = :finish_time,
        condition_after = :condition_after,
        remark = :remark,
        status = :status
        WHERE id = :entry_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    log_activity("Entry updated (ID: {$entry_id}) by user ID: {$_SESSION['user_id']}");
} catch (PDOException $e) {
    log_activity("Error updating entry: " . $e->getMessage());
    die("Failed to update entry. Error: " . $e->getMessage());
}

header('Location: logbook_list.php?message=Entry updated successfully');
exit();
?>