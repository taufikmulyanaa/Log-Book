<?php
// public/get_instrument_data.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$instrument_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$instrument_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Instrument ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM instruments WHERE id = :id");
    $stmt->execute(['id' => $instrument_id]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($instrument) {
        echo json_encode($instrument);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Instrument not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>