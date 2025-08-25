<?php
// public/delete_entry_action.php
require_once __DIR__ . '/../config/init.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { die('Method Not Allowed'); }
if (!isset($_SESSION['user_id'])) { die('Unauthorized'); }
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { die('CSRF Token Error'); }

$entry_id = filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT);
if (!$entry_id) {
    die('Invalid entry ID.');
}

// Check if user can delete this entry
$stmt = $pdo->prepare("SELECT user_id, log_book_code FROM logbook_entries WHERE id = :id");
$stmt->execute(['id' => $entry_id]);
$entry = $stmt->fetch();

if (!$entry) {
    die('Entry not found.');
}

// Basic permission check - user can only delete their own entries
if ($entry['user_id'] != $_SESSION['user_id']) {
    die('You can only delete your own entries.');
}

try {
    // Delete the logbook entry
    $stmt = $pdo->prepare("DELETE FROM logbook_entries WHERE id = :id");
    $stmt->execute(['id' => $entry_id]);
    
    log_activity("Entry deleted (ID: {$entry_id}, Code: {$entry['log_book_code']}) by user ID: {$_SESSION['user_id']}");
} catch (PDOException $e) {
    log_activity("Error deleting entry: " . $e->getMessage());
    die("Failed to delete entry. Error: " . $e->getMessage());
}

// Redirect with success message
header('Location: logbook_list.php?message=Entry deleted successfully');
exit();
?>