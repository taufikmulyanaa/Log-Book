<?php
// public/delete_entry_action.php
require_once __DIR__ . '/../config/init.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    die('Method Not Allowed'); 
}
if (!isset($_SESSION['user_id'])) { 
    die('Unauthorized'); 
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { 
    die('CSRF Token Error'); 
}

$entry_id = filter_input(INPUT_POST, 'entry_id', FILTER_VALIDATE_INT);
if (!$entry_id) {
    die('Invalid entry ID.');
}

// Check if user has permission to delete entries
if (function_exists('hasPermission')) {
    if (!hasPermission('entry_delete_own') && !hasPermission('entry_delete_all')) {
        die('You do not have permission to delete entries.');
    }
} else {
    // Fallback permission check
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['administrator', 'contributor'])) {
        die('You do not have permission to delete entries.');
    }
}

// Get entry details
$stmt = $pdo->prepare("SELECT user_id, log_book_code, status FROM logbook_entries WHERE id = :id");
$stmt->execute(['id' => $entry_id]);
$entry = $stmt->fetch();

if (!$entry) {
    die('Entry not found.');
}

// Check if user can delete this specific entry
$canDelete = false;

if (function_exists('canDeleteEntry')) {
    $canDelete = canDeleteEntry($entry);
} else {
    // Fallback permission logic
    $userRole = $_SESSION['user_role'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($userRole === 'administrator') {
        // Administrators can delete any entry
        $canDelete = true;
    } elseif ($userRole === 'contributor' && $entry['user_id'] == $userId) {
        // Contributors can delete their own entries
        $canDelete = true;
    } else {
        $canDelete = false;
    }
}

if (!$canDelete) {
    die('You can only delete your own entries.');
}

// Additional validation: Check if entry is completed
if ($entry['status'] === 'Completed') {
    // Only administrators can delete completed entries
    $userRole = $_SESSION['user_role'] ?? '';
    if ($userRole !== 'administrator') {
        die('Completed entries cannot be deleted. Contact an administrator if deletion is necessary.');
    }
}

try {
    // Delete the logbook entry
    $stmt = $pdo->prepare("DELETE FROM logbook_entries WHERE id = :id");
    $result = $stmt->execute(['id' => $entry_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Log the deletion activity
        $userRole = $_SESSION['user_role'] ?? 'unknown';
        log_activity("Entry deleted (ID: {$entry_id}, Code: {$entry['log_book_code']}) by user ID: {$_SESSION['user_id']} (Role: {$userRole})");
        
        // Redirect with success message
        $message = "Entry #{$entry['log_book_code']} deleted successfully.";
        header('Location: logbook_list.php?message=' . urlencode($message));
        exit();
    } else {
        throw new Exception('Failed to delete entry. Entry may have already been deleted.');
    }
    
} catch (PDOException $e) {
    // Log the error
    log_activity("Error deleting entry (ID: {$entry_id}): " . $e->getMessage());
    
    // Check if it's a foreign key constraint error
    if ($e->getCode() == '23000') {
        die("Cannot delete this entry because it has related data. Please contact an administrator.");
    }
    
    die("Failed to delete entry. Database error occurred.");
} catch (Exception $e) {
    log_activity("Error deleting entry (ID: {$entry_id}): " . $e->getMessage());
    die("Failed to delete entry: " . $e->getMessage());
}
?>