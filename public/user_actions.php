<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');

// Check if permissions are loaded, if not use fallback
if (!function_exists('hasPermission')) {
    function hasPermission($permission) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'administrator';
    }
    function getAvailableRoles() {
        $roles = [
            'administrator' => 'Administrator',
            'contributor' => 'Contributor', 
            'viewer' => 'Viewer'
        ];
        if ($_SESSION['user_role'] !== 'administrator') {
            unset($roles['administrator']);
        }
        return $roles;
    }
    define('ROLE_ADMINISTRATOR', 'administrator');
    define('ROLE_CONTRIBUTOR', 'contributor');
    define('ROLE_VIEWER', 'viewer');
}

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !hasPermission('user_management')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token']);
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

try {
    switch ($action) {
        case 'create_update':
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
            $role = $_POST['role'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'] ?? '';

            // Validate role against available roles
            $validRoles = array_keys(getAvailableRoles());
            if (!in_array($role, $validRoles)) {
                throw new Exception("Invalid role selected.");
            }

            // Check if user can assign administrator role
            if ($role === 'administrator' && $_SESSION['user_role'] !== 'administrator') {
                throw new Exception("Only administrators can create other administrators.");
            }

            if (empty($name) || empty($username)) {
                throw new Exception("Name and Username are required.");
            }

            // Check if username is already taken (but allow current user in edit mode)
            $checkSql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$username, $user_id ?: 0]);
            if ($checkStmt->fetch()) {
                throw new Exception("Username already exists.");
            }

            if ($user_id) { // Update existing user
                $sql = "UPDATE users SET name = ?, username = ?, email = ?, role = ?, is_active = ? WHERE id = ?";
                $params = [$name, $username, $email, $role, $is_active, $user_id];
                $pdo->prepare($sql)->execute($params);

                // Update password if provided
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                }
                
                log_activity("User (ID: $user_id) updated by Admin (ID: {$_SESSION['user_id']})");
                echo json_encode(['success' => true, 'message' => 'User updated successfully.']);

            } else { // Create new user
                if (empty($password)) {
                    throw new Exception("Password is required for new user.");
                }
                
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (name, username, email, role, password, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$name, $username, $email, $role, $hash, $is_active];
                $pdo->prepare($sql)->execute($params);
                $new_id = $pdo->lastInsertId();
                
                log_activity("User (ID: $new_id) created by Admin (ID: {$_SESSION['user_id']})");
                echo json_encode(['success' => true, 'message' => 'User created successfully.']);
            }
            break;

        case 'delete':
            if (!$user_id || $user_id == $_SESSION['user_id']) {
                throw new Exception("Invalid request or cannot delete yourself.");
            }
            
            // Check if user exists
            $checkUser = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $checkUser->execute([$user_id]);
            $userData = $checkUser->fetch();
            
            if (!$userData) {
                throw new Exception("User not found.");
            }
            
            // Delete the user
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            
            log_activity("User (ID: $user_id, Name: {$userData['name']}) deleted by Admin (ID: {$_SESSION['user_id']})");
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
            break;

        case 'toggle_status':
            if (!$user_id || $user_id == $_SESSION['user_id']) {
                throw new Exception("Invalid request or cannot deactivate yourself.");
            }
            
            $currentStatus = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
            $currentStatus->execute([$user_id]);
            $status = $currentStatus->fetchColumn();
            
            if ($status === false) {
                throw new Exception("User not found.");
            }
            
            $newStatus = $status ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $user_id]);
            
            $statusText = $newStatus ? 'activated' : 'deactivated';
            log_activity("User (ID: $user_id) $statusText by Admin (ID: {$_SESSION['user_id']})");
            echo json_encode(['success' => true, 'message' => "User $statusText successfully."]);
            break;

        default:
            throw new Exception("Invalid action specified.");
    }
} catch (PDOException $e) {
    log_activity("Database error in user_actions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>