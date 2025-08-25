<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
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
            $role = in_array($_POST['role'], ['admin', 'user', 'viewer']) ? $_POST['role'] : 'user';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($username)) {
                throw new Exception("Name and Username are required.");
            }

            if ($user_id) { // Update
                $sql = "UPDATE users SET name = ?, username = ?, email = ?, role = ?, is_active = ? WHERE id = ?";
                $params = [$name, $username, $email, $role, $is_active, $user_id];
                $pdo->prepare($sql)->execute($params);

                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
                }
                log_activity("User (ID: $user_id) updated by Admin (ID: {$_SESSION['user_id']})");
                echo json_encode(['success' => true, 'message' => 'User updated successfully.']);

            } else { // Create
                if (empty($password)) throw new Exception("Password is required for new user.");
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
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            log_activity("User (ID: $user_id) deleted by Admin (ID: {$_SESSION['user_id']})");
            echo json_encode(['success' => true, 'message' => 'User deleted.']);
            break;

        default:
            throw new Exception("Invalid action.");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>