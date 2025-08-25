<?php
// public/instrument_actions.php
require_once __DIR__ . '/../config/init.php';
header('Content-Type: application/json');

// Check if permissions are loaded, if not use fallback
if (!function_exists('hasPermission')) {
    function hasPermission($permission) {
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['administrator', 'contributor']);
    }
}

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !hasPermission('settings_edit')) {
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
$instrument_id = filter_input(INPUT_POST, 'instrument_id', FILTER_VALIDATE_INT);

// Parameter columns for validation
$parameter_columns = [
    'MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 
    'Diluent', 'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'
];

try {
    switch ($action) {
        case 'create_update':
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $description = trim($_POST['description'] ?? '') ?: null;
            $location = trim($_POST['location'] ?? '') ?: null;
            $status = $_POST['status'] ?? 'active';
            $parameters = $_POST['parameters'] ?? [];

            // Validate required fields
            if (empty($name) || empty($code)) {
                throw new Exception("Name and Code are required.");
            }

            // Validate status
            $validStatuses = ['active', 'maintenance', 'inactive'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status selected.");
            }

            // Validate instrument code format
            if (!preg_match('/^[A-Z0-9-_]+$/', $code)) {
                throw new Exception("Instrument code can only contain uppercase letters, numbers, hyphens, and underscores.");
            }

            // Check if code is already taken (but allow current instrument in edit mode)
            $checkSql = "SELECT id FROM instruments WHERE code = ? AND id != ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$code, $instrument_id ?: 0]);
            if ($checkStmt->fetch()) {
                throw new Exception("Instrument code already exists.");
            }

            // Prepare parameter data
            $parameterData = [];
            foreach ($parameter_columns as $param) {
                $parameterData[$param] = in_array($param, $parameters) ? 1 : 0;
            }

            if ($instrument_id) {
                // Update existing instrument
                $sql = "UPDATE instruments SET 
                        name = :name, 
                        code = :code, 
                        description = :description, 
                        location = :location, 
                        status = :status,
                        " . implode(' = :', array_keys($parameterData)) . " = :" . implode(', ', array_keys($parameterData)) . "
                        WHERE id = :id";
                
                $params = [
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'location' => $location,
                    'status' => $status,
                    'id' => $instrument_id
                ];
                $params = array_merge($params, $parameterData);
                
                $pdo->prepare($sql)->execute($params);
                
                log_activity("Instrument (ID: $instrument_id, Code: $code) updated by user ID: {$_SESSION['user_id']}");
                echo json_encode(['success' => true, 'message' => 'Instrument updated successfully.']);

            } else {
                // Create new instrument
                $columns = ['name', 'code', 'description', 'location', 'status'] + array_keys($parameterData);
                $placeholders = ':' . implode(', :', $columns);
                $sql = "INSERT INTO instruments (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                
                $params = [
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'location' => $location,
                    'status' => $status
                ];
                $params = array_merge($params, $parameterData);
                
                $pdo->prepare($sql)->execute($params);
                $new_id = $pdo->lastInsertId();
                
                log_activity("Instrument (ID: $new_id, Code: $code) created by user ID: {$_SESSION['user_id']}");
                echo json_encode(['success' => true, 'message' => 'Instrument created successfully.']);
            }
            break;

        case 'delete':
            if (!$instrument_id) {
                throw new Exception("Invalid instrument ID.");
            }
            
            // Check if instrument exists and get details
            $checkInstrument = $pdo->prepare("SELECT name, code FROM instruments WHERE id = ?");
            $checkInstrument->execute([$instrument_id]);
            $instrumentData = $checkInstrument->fetch();
            
            if (!$instrumentData) {
                throw new Exception("Instrument not found.");
            }

            // Check if instrument is being used in logbook entries
            $checkUsage = $pdo->prepare("SELECT COUNT(*) FROM logbook_entries WHERE instrument_id = ?");
            $checkUsage->execute([$instrument_id]);
            $usageCount = $checkUsage->fetchColumn();

            if ($usageCount > 0) {
                // Instead of deleting, set to inactive status
                $pdo->prepare("UPDATE instruments SET status = 'inactive' WHERE id = ?")
                    ->execute([$instrument_id]);
                
                log_activity("Instrument (ID: $instrument_id, Code: {$instrumentData['code']}) deactivated (has $usageCount entries) by user ID: {$_SESSION['user_id']}");
                echo json_encode([
                    'success' => true, 
                    'message' => "Instrument has been deactivated instead of deleted due to existing usage records ($usageCount entries)."
                ]);
            } else {
                // Safe to delete - no usage records
                $pdo->prepare("DELETE FROM instruments WHERE id = ?")
                    ->execute([$instrument_id]);
                
                log_activity("Instrument (ID: $instrument_id, Code: {$instrumentData['code']}) deleted by user ID: {$_SESSION['user_id']}");
                echo json_encode(['success' => true, 'message' => 'Instrument deleted successfully.']);
            }
            break;

        case 'toggle_status':
            if (!$instrument_id) {
                throw new Exception("Invalid instrument ID.");
            }
            
            // Get current status
            $currentStatus = $pdo->prepare("SELECT status FROM instruments WHERE id = ?");
            $currentStatus->execute([$instrument_id]);
            $status = $currentStatus->fetchColumn();
            
            if ($status === false) {
                throw new Exception("Instrument not found.");
            }
            
            // Toggle between active and inactive
            $newStatus = ($status === 'active') ? 'inactive' : 'active';
            $pdo->prepare("UPDATE instruments SET status = ? WHERE id = ?")
                ->execute([$newStatus, $instrument_id]);
            
            $statusText = ucfirst($newStatus);
            log_activity("Instrument (ID: $instrument_id) status changed to $newStatus by user ID: {$_SESSION['user_id']}");
            echo json_encode(['success' => true, 'message' => "Instrument status changed to $statusText."]);
            break;

        case 'bulk_update_status':
            $instrument_ids = $_POST['instrument_ids'] ?? [];
            $new_status = $_POST['new_status'] ?? '';
            
            if (empty($instrument_ids) || !in_array($new_status, ['active', 'maintenance', 'inactive'])) {
                throw new Exception("Invalid bulk update parameters.");
            }
            
            // Validate all IDs are integers
            $validated_ids = array_filter(array_map('intval', $instrument_ids));
            if (empty($validated_ids)) {
                throw new Exception("No valid instrument IDs provided.");
            }
            
            $placeholders = str_repeat('?,', count($validated_ids) - 1) . '?';
            $sql = "UPDATE instruments SET status = ? WHERE id IN ($placeholders)";
            
            $params = array_merge([$new_status], $validated_ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $affected_rows = $stmt->rowCount();
            log_activity("Bulk status update: $affected_rows instruments set to $new_status by user ID: {$_SESSION['user_id']}");
            
            echo json_encode([
                'success' => true, 
                'message' => "$affected_rows instruments updated to " . ucfirst($new_status) . " status."
            ]);
            break;

        case 'import_instruments':
            // Handle CSV import of instruments
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No valid CSV file uploaded.");
            }
            
            $file_path = $_FILES['csv_file']['tmp_name'];
            if (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                throw new Exception("Only CSV files are allowed.");
            }
            
            $imported_count = 0;
            $error_count = 0;
            $errors = [];
            
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                
                // Validate required columns
                $required_columns = ['name', 'code', 'status'];
                $missing_columns = array_diff($required_columns, array_map('strtolower', $header));
                
                if (!empty($missing_columns)) {
                    throw new Exception("Missing required columns: " . implode(', ', $missing_columns));
                }
                
                $row_number = 1;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row_number++;
                    
                    if (count($header) !== count($data)) {
                        $errors[] = "Row $row_number: Column count mismatch";
                        $error_count++;
                        continue;
                    }
                    
                    $row_data = array_combine(array_map('strtolower', $header), $data);
                    
                    try {
                        // Validate required fields
                        if (empty($row_data['name']) || empty($row_data['code'])) {
                            throw new Exception("Name and Code are required");
                        }
                        
                        // Check for duplicate code
                        $check_stmt = $pdo->prepare("SELECT id FROM instruments WHERE code = ?");
                        $check_stmt->execute([strtoupper($row_data['code'])]);
                        if ($check_stmt->fetch()) {
                            throw new Exception("Duplicate instrument code: " . $row_data['code']);
                        }
                        
                        // Prepare insert data
                        $insert_data = [
                            'name' => $row_data['name'],
                            'code' => strtoupper($row_data['code']),
                            'description' => $row_data['description'] ?? null,
                            'location' => $row_data['location'] ?? null,
                            'status' => in_array($row_data['status'], ['active', 'maintenance', 'inactive']) 
                                       ? $row_data['status'] : 'active'
                        ];
                        
                        // Add parameter data
                        foreach ($parameter_columns as $param) {
                            $param_key = strtolower($param);
                            $insert_data[$param] = isset($row_data[$param_key]) 
                                                 ? (int)filter_var($row_data[$param_key], FILTER_VALIDATE_BOOLEAN) 
                                                 : 0;
                        }
                        
                        // Insert instrument
                        $columns = array_keys($insert_data);
                        $placeholders = ':' . implode(', :', $columns);
                        $sql = "INSERT INTO instruments (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                        
                        $pdo->prepare($sql)->execute($insert_data);
                        $imported_count++;
                        
                    } catch (Exception $e) {
                        $errors[] = "Row $row_number: " . $e->getMessage();
                        $error_count++;
                    }
                }
                
                fclose($handle);
            }
            
            log_activity("Instrument CSV import completed: $imported_count imported, $error_count errors by user ID: {$_SESSION['user_id']}");
            
            $message = "$imported_count instruments imported successfully";
            if ($error_count > 0) {
                $message .= ", $error_count errors occurred";
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'imported_count' => $imported_count,
                'error_count' => $error_count,
                'errors' => array_slice($errors, 0, 10) // Limit errors shown
            ]);
            break;

        default:
            throw new Exception("Invalid action specified.");
    }

} catch (PDOException $e) {
    log_activity("Database error in instrument_actions.php: " . $e->getMessage());
    http_response_code(500);
    
    // Check for specific database constraint errors
    if ($e->getCode() == '23000') {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'Instrument code already exists.']);
        } elseif (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete instrument that has associated records.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database constraint violation.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>