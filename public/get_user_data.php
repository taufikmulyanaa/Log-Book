<?php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid User ID']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, username, email, role, is_active FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode($user);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>