<?php
require_once __DIR__ . '/../config/init.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
// CSRF check
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF Token.']));
}


// Set header to return JSON
header('Content-Type: application/json');

// Get POST data
$instrument_id = filter_input(INPUT_POST, 'instrument_id', FILTER_VALIDATE_INT);
$parameter = $_POST['parameter'] ?? null;
// Handle 'true'/'false' string from FormData
$is_checked_str = $_POST['is_checked'] ?? 'false';
$is_checked = ($is_checked_str === 'true');


// Validate inputs
$allowed_parameters = [
    'MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 'Diluent', 
    'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'
];

if (!$instrument_id || !in_array($parameter, $allowed_parameters)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid input provided.']));
}

// Prepare and execute the update query
try {
    // Note: Parameter name is whitelisted, so it's safe to use directly in the query here.
    $sql = "UPDATE instruments SET `{$parameter}` = :is_checked WHERE id = :instrument_id";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':is_checked' => $is_checked ? 1 : 0,
        ':instrument_id' => $instrument_id
    ]);

    if ($stmt->rowCount() > 0) {
        log_activity("Matrix updated for instrument ID {$instrument_id}. Parameter '{$parameter}' set to " . ($is_checked ? 'true' : 'false'));
        echo json_encode(['success' => true, 'message' => 'Matrix updated successfully.']);
    } else {
        // This can happen if the value was already the same in the DB
        echo json_encode(['success' => true, 'message' => 'No change needed.']);
    }

} catch (PDOException $e) {
    log_activity("Error updating matrix: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}