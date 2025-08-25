<?php
require_once __DIR__ . '/../config/init.php';

// --- Security & Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: import.php?import_error=' . urlencode('Invalid request method.')); exit(); }
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { header('Location: import.php?import_error=' . urlencode('CSRF token mismatch.')); exit(); }
if (!isset($_SESSION['import_preview_data'])) { header('Location: import.php?import_error=' . urlencode('No import data found in session.')); exit(); }

$preview_data = $_SESSION['import_preview_data'];
unset($_SESSION['import_preview_data']);

// --- Pre-fetch Data for Mapping ---
try {
    $users_map = [];
    foreach ($pdo->query("SELECT id, name FROM users")->fetchAll() as $user) { $users_map[trim(strtolower($user['name']))] = $user['id']; }
    $instruments_map = [];
    foreach ($pdo->query("SELECT id, name FROM instruments")->fetchAll() as $instrument) { $instruments_map[trim(strtolower($instrument['name']))] = $instrument['id']; }
} catch (PDOException $e) { header('Location: import.php?import_error=' . urlencode('Database error.')); exit(); }

// --- Process and Insert Valid Data ---
$success_count = 0;
$pdo->beginTransaction();

try {
    $sql = "INSERT INTO logbook_entries (user_id, instrument_id, start_date, start_time, sample_name, trial_code, finish_date, finish_time, condition_after, remark, status, activity, mobile_phase_val, speed_val, electrode_type_val, result_val, wavelength_scan_val, diluent_val, lamp_val, column_val, apparatus_val, medium_val, total_volume_val, vessel_quantity_val) VALUES (:user_id, :instrument_id, :start_date, :start_time, :sample_name, :trial_code, :finish_date, :finish_time, :condition_after, :remark, :status, :activity, :mobile_phase_val, :speed_val, :electrode_type_val, :result_val, :wavelength_scan_val, :diluent_val, :lamp_val, :column_val, :apparatus_val, :medium_val, :total_volume_val, :vessel_quantity_val)";
    $stmt = $pdo->prepare($sql);

    foreach ($preview_data as $validated_row) {
        if ($validated_row['is_valid']) {
            $row = $validated_row['data'];
            
            $data_to_insert = [
                'user_id' => $users_map[trim(strtolower($row['UserName']))],
                'instrument_id' => $instruments_map[trim(strtolower($row['Instruments']))],
                'start_date' => !empty($row['StartDate']) ? date('Y-m-d', strtotime($row['StartDate'])) : null,
                'start_time' => !empty($row['StartTime']) && $row['StartTime'] != '1900-01-01' ? date('H:i:s', strtotime($row['StartTime'])) : null,
                'sample_name' => $row['SampleName'] ?? null,
                'trial_code' => $row['TrialCode'] ?? null,
                'finish_date' => !empty($row['FinishDate']) ? date('Y-m-d', strtotime($row['FinishDate'])) : null,
                'finish_time' => !empty($row['FinishTime']) && $row['FinishTime'] != '1900-01-01' ? date('H:i:s', strtotime($row['FinishTime'])) : null,
                'condition_after' => $row['Condition'] ?? null,
                'remark' => $row['Remark'] ?? null,
                'status' => $row['Status'] ?? 'Not Complete',
                'activity' => $row['Remark'] ?? 'Imported Data',
                'mobile_phase_val' => $row['MobilePhase'] ?? null,
                'speed_val' => $row['Speed'] ?? null,
                'electrode_type_val' => $row['ElectrodeType'] ?? null,
                'result_val' => $row['Result'] ?? null,
                'wavelength_scan_val' => $row['WavelengthScan'] ?? null, // Use the correct, normalized key
                'diluent_val' => $row['Diluent'] ?? null,
                'lamp_val' => $row['Lamp'] ?? null,
                'column_val' => $row['Column'] ?? null,
                'apparatus_val' => $row['Apparatus'] ?? null,
                'medium_val' => $row['Medium'] ?? null,
                'total_volume_val' => $row['TotalVolume'] ?? null,
                'vessel_quantity_val' => $row['VesselQuantity'] ?? null,
            ];

            $stmt->execute($data_to_insert);
            $success_count++;
        }
    }

    $pdo->commit();
    log_activity("$success_count rows imported from CSV by user ID: {$_SESSION['user_id']}");
    header("Location: import.php?success=$success_count");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    log_activity("Error during CSV import: " . $e->getMessage());
    header('Location: import.php?import_error=' . urlencode('An error occurred during import: ' . $e->getMessage()));
    exit();
}