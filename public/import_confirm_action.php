<?php
require_once __DIR__ . '/../config/init.php';

// Increase limits for large file processing
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '300'); // 5 minutes
set_time_limit(0); // No time limit for CLI

// --- Security & Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header('Location: import.php?import_error=' . urlencode('Invalid request method.')); 
    exit(); 
}
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { 
    header('Location: import.php?import_error=' . urlencode('CSRF token mismatch.')); 
    exit(); 
}
if (!isset($_SESSION['import_preview_data'])) { 
    header('Location: import.php?import_error=' . urlencode('No import data found in session.')); 
    exit(); 
}

$preview_data = $_SESSION['import_preview_data'];
unset($_SESSION['import_preview_data']);

// --- Enhanced Data Mapping ---
try {
    // Pre-fetch users with improved mapping
    $users_map = [];
    $users_stmt = $pdo->query("SELECT id, name, username FROM users");
    foreach ($users_stmt->fetchAll() as $user) { 
        $users_map[trim(strtolower($user['name']))] = $user['id'];
        $users_map[trim(strtolower($user['username']))] = $user['id']; // Also map by username
    }
    
    // Pre-fetch instruments with improved mapping
    $instruments_map = [];
    $instruments_stmt = $pdo->query("SELECT id, name, code FROM instruments");
    foreach ($instruments_stmt->fetchAll() as $instrument) { 
        $instruments_map[trim(strtolower($instrument['name']))] = $instrument['id'];
        $instruments_map[trim(strtolower($instrument['code']))] = $instrument['id']; // Also map by code
    }
} catch (PDOException $e) { 
    header('Location: import.php?import_error=' . urlencode('Database error during setup.')); 
    exit(); 
}

// --- Batch Processing Setup ---
$success_count = 0;
$error_count = 0;
$batch_size = 100; // Process 100 records at a time
$batch_data = [];

$pdo->beginTransaction();

try {
    // Prepare insert statement once
    $sql = "INSERT INTO logbook_entries (
        user_id, instrument_id, log_book_code, sample_name, trial_code, 
        start_date, start_time, finish_date, finish_time, condition_after, 
        remark, status, mobile_phase_val, speed_val, electrode_type_val, 
        result_val, wavelength_scan_val, diluent_val, lamp_val, column_val, 
        apparatus_val, medium_val, total_volume_val, vessel_quantity_val
    ) VALUES (
        :user_id, :instrument_id, :log_book_code, :sample_name, :trial_code,
        :start_date, :start_time, :finish_date, :finish_time, :condition_after,
        :remark, :status, :mobile_phase_val, :speed_val, :electrode_type_val,
        :result_val, :wavelength_scan_val, :diluent_val, :lamp_val, :column_val,
        :apparatus_val, :medium_val, :total_volume_val, :vessel_quantity_val
    )";
    $stmt = $pdo->prepare($sql);

    foreach ($preview_data as $index => $validated_row) {
        if (!$validated_row['is_valid']) {
            $error_count++;
            continue;
        }

        $row = $validated_row['data'];
        
        // Enhanced user mapping with fallback
        $user_name_key = trim(strtolower($row['UserName']));
        $user_id = $users_map[$user_name_key] ?? null;
        
        // If user not found, create or use admin fallback
        if (!$user_id) {
            // Try to create user or fallback to admin (ID: 1)
            $user_id = 1; // Admin fallback
            log_activity("User '{$row['UserName']}' not found, using admin fallback for import row " . ($index + 1));
        }

        // Enhanced instrument mapping
        $instrument_name_key = trim(strtolower($row['Instruments']));
        $instrument_id = $instruments_map[$instrument_name_key] ?? null;
        
        if (!$instrument_id) {
            // Skip this record if instrument not found
            log_activity("Instrument '{$row['Instruments']}' not found, skipping row " . ($index + 1));
            $error_count++;
            continue;
        }

        // Generate log_book_code if not exists
        $log_book_code = !empty($row['ID']) ? 'IMP/' . str_pad($row['ID'], 6, '0', STR_PAD_LEFT) : null;

        // Enhanced date/time processing with validation
        $start_date = null;
        $start_time = null;
        $finish_date = null;
        $finish_time = null;

        // Process start date/time
        if (!empty($row['StartDate'])) {
            try {
                $start_date = date('Y-m-d', strtotime($row['StartDate']));
                if ($start_date === '1970-01-01') $start_date = null;
            } catch (Exception $e) {
                $start_date = null;
            }
        }

        if (!empty($row['StartTime']) && $row['StartTime'] !== '1900-01-01') {
            try {
                $start_time = date('H:i:s', strtotime($row['StartTime']));
                if ($start_time === '00:00:00' && $row['StartTime'] !== '00:00:00') $start_time = null;
            } catch (Exception $e) {
                $start_time = null;
            }
        }

        // Process finish date/time
        if (!empty($row['FinishDate'])) {
            try {
                $finish_date = date('Y-m-d', strtotime($row['FinishDate']));
                if ($finish_date === '1970-01-01') $finish_date = null;
            } catch (Exception $e) {
                $finish_date = null;
            }
        }

        if (!empty($row['FinishTime']) && $row['FinishTime'] !== '1900-01-01') {
            try {
                $finish_time = date('H:i:s', strtotime($row['FinishTime']));
                if ($finish_time === '00:00:00' && $row['FinishTime'] !== '00:00:00') $finish_time = null;
            } catch (Exception $e) {
                $finish_time = null;
            }
        }

        // Skip if no start date/time (required fields)
        if (!$start_date || !$start_time) {
            log_activity("Missing start date/time for row " . ($index + 1) . ", skipping");
            $error_count++;
            continue;
        }

        // Prepare data for insertion
        $data_to_insert = [
            'user_id' => $user_id,
            'instrument_id' => $instrument_id,
            'log_book_code' => $log_book_code,
            'sample_name' => !empty($row['SampleName']) ? $row['SampleName'] : null,
            'trial_code' => !empty($row['TrialCode']) ? $row['TrialCode'] : null,
            'start_date' => $start_date,
            'start_time' => $start_time,
            'finish_date' => $finish_date,
            'finish_time' => $finish_time,
            'condition_after' => !empty($row['Condition']) ? $row['Condition'] : null,
            'remark' => !empty($row['Remark']) ? $row['Remark'] : null,
            'status' => !empty($row['Status']) ? $row['Status'] : 'Not Complete',
            'mobile_phase_val' => !empty($row['MobilePhase']) ? $row['MobilePhase'] : null,
            'speed_val' => !empty($row['Speed']) ? $row['Speed'] : null,
            'electrode_type_val' => !empty($row['ElectrodeType']) ? $row['ElectrodeType'] : null,
            'result_val' => !empty($row['Result']) ? $row['Result'] : null,
            // Handle typo in CSV column name
            'wavelength_scan_val' => !empty($row['WavelengthScan']) ? $row['WavelengthScan'] : (!empty($row['WavelenghtScan']) ? $row['WavelenghtScan'] : null),
            'diluent_val' => !empty($row['Diluent']) ? $row['Diluent'] : null,
            'lamp_val' => !empty($row['Lamp']) ? $row['Lamp'] : null,
            'column_val' => !empty($row['Column']) ? $row['Column'] : null,
            'apparatus_val' => !empty($row['Apparatus']) ? $row['Apparatus'] : null,
            'medium_val' => !empty($row['Medium']) ? $row['Medium'] : null,
            'total_volume_val' => !empty($row['TotalVolume']) ? $row['TotalVolume'] : null,
            'vessel_quantity_val' => !empty($row['VesselQuantity']) ? $row['VesselQuantity'] : null,
        ];

        // Add to batch
        $batch_data[] = $data_to_insert;

        // Process batch when it reaches batch_size
        if (count($batch_data) >= $batch_size) {
            $processed = processBatch($stmt, $batch_data);
            $success_count += $processed;
            $batch_data = []; // Reset batch
            
            // Optional: Clear memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    // Process remaining batch
    if (!empty($batch_data)) {
        $processed = processBatch($stmt, $batch_data);
        $success_count += $processed;
    }

    $pdo->commit();
    
    log_activity("CSV import completed: $success_count successful, $error_count errors by user ID: {$_SESSION['user_id']}");
    
    // Success message with details
    $message = "Import completed successfully! $success_count records imported";
    if ($error_count > 0) {
        $message .= ", $error_count records skipped due to errors";
    }
    
    header("Location: import.php?success=" . urlencode($message));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_activity("Error during CSV import: " . $e->getMessage());
    header('Location: import.php?import_error=' . urlencode('Import failed: ' . $e->getMessage()));
    exit();
}

/**
 * Process a batch of records
 */
function processBatch($stmt, $batch_data) {
    $processed = 0;
    
    foreach ($batch_data as $data) {
        try {
            $stmt->execute($data);
            $processed++;
        } catch (PDOException $e) {
            // Log individual record errors but continue processing
            log_activity("Error inserting record: " . $e->getMessage() . " - Data: " . json_encode($data));
        }
    }
    
    return $processed;
}
?>