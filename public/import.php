<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

$preview_data = [];
$show_preview = false;
$error_message = null;

// Handle file upload for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = 'CSRF token mismatch.';
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'File upload error.';
    } elseif (strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $error_message = 'Invalid file type. Only CSV is allowed.';
    } else {
        $file_path = $_FILES['csv_file']['tmp_name'];
        
        // Fetch users and instruments for validation
        $users_map = [];
        foreach ($pdo->query("SELECT id, name FROM users")->fetchAll() as $user) {
            $users_map[trim(strtolower($user['name']))] = $user['id'];
        }
        $instruments_map = [];
        foreach ($pdo->query("SELECT id, name FROM instruments")->fetchAll() as $instrument) {
            $instruments_map[trim(strtolower($instrument['name']))] = $instrument['id'];
        }

        // Read and validate CSV
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            $row_number = 1;

            while (($data_row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_number++;
                if (count($header) != count($data_row)) {
                     $preview_data[] = [ 'data' => array_pad($data_row, count($header), ''), 'is_valid' => false, 'message' => "Column count mismatch on row $row_number."];
                     continue;
                }
                $row = array_combine($header, $data_row);
                
                // FIX: Normalize misspelled header key
                if (isset($row['WavelenghtScan']) && !isset($row['WavelengthScan'])) {
                    $row['WavelengthScan'] = $row['WavelenghtScan'];
                    unset($row['WavelenghtScan']);
                }

                $validated_row = ['data' => $row, 'is_valid' => true, 'message' => 'Ready to import'];
                
                // Validate UserName and Instruments
                $user_name = trim(strtolower($row['UserName']));
                $instrument_name = trim(strtolower($row['Instruments']));

                if (!isset($users_map[$user_name])) {
                    $validated_row['is_valid'] = false;
                    $validated_row['message'] = "User '{$row['UserName']}' not found.";
                }
                if (!isset($instruments_map[$instrument_name])) {
                    $validated_row['is_valid'] = false;
                    $validated_row['message'] = "Instrument '{$row['Instruments']}' not found.";
                }
                
                $preview_data[] = $validated_row;
            }
            fclose($handle);

            if (!empty($preview_data)) {
                $_SESSION['import_preview_data'] = $preview_data;
                $show_preview = true;
            } else {
                 $error_message = "Could not read any data from the CSV file.";
            }
        }
    }
}

// Clear preview session data if navigating back
if (isset($_GET['cancel'])) {
    unset($_SESSION['import_preview_data']);
    header('Location: import.php');
    exit();
}
?>

<div class="bg-card p-6 rounded-xl border border-border shadow-lg space-y-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Import Logbook Data</h1>
        <p class="text-sm text-muted-foreground mt-1">
            <?php echo $show_preview ? 'Step 2: Review and confirm the data to be imported.' : 'Step 1: Upload a CSV file to begin.'; ?>
        </p>
    </div>

    <?php if ($error_message): ?>
         <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
            <p class="font-bold">Import Complete</p>
            <p><?php echo (int)$_GET['success']; ?> rows were successfully imported.</p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['import_error'])): ?>
         <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p class="font-bold">Import Failed</p>
            <p><?php echo htmlspecialchars(urldecode($_GET['import_error'])); ?></p>
        </div>
    <?php endif; ?>


    <?php if ($show_preview): ?>
        <div class="space-y-4">
            <div class="overflow-x-auto border rounded-lg">
                <table class="w-full text-xs">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-left">User</th>
                            <th class="p-2 text-left">Instrument</th>
                            <th class="p-2 text-left">Sample Name</th>
                            <th class="p-2 text-left">Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $row): ?>
                            <tr class="border-t <?php echo !$row['is_valid'] ? 'bg-red-50' : ''; ?>">
                                <td class="p-2">
                                    <?php if ($row['is_valid']): ?>
                                        <span class="px-2 py-1 text-[10px] rounded-full bg-green-100 text-green-800">Ready</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-[10px] rounded-full bg-red-100 text-red-800" title="<?php echo esc_html($row['message']); ?>">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-2"><?php echo esc_html($row['data']['UserName']); ?></td>
                                <td class="p-2"><?php echo esc_html($row['data']['Instruments']); ?></td>
                                <td class="p-2"><?php echo esc_html($row['data']['SampleName']); ?></td>
                                <td class="p-2"><?php echo esc_html($row['data']['StartDate']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 border-t pt-4 flex justify-between items-center">
                <a href="import.php?cancel=1" class="px-4 py-2 text-sm font-medium border rounded-lg hover:bg-gray-50">Cancel</a>
                <form action="import_confirm_action.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check-circle w-4 h-4"></i>
                        Confirm and Import Valid Data
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="bg-muted/50 p-4 rounded-lg border border-border">
            <form action="import.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                <label for="csv_file" class="block text-sm font-medium text-gray-700">CSV File</label>
                <div class="mt-1"><input type="file" name="csv_file" id="csv_file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required accept=".csv"></div>
                <p class="mt-2 text-xs text-muted-foreground">Please ensure the CSV has the correct columns: StartDate, StartTime, UserName, Instruments, etc.</p>
                <div class="mt-6 border-t pt-4">
                     <button type="submit" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                        <i class="fas fa-upload w-4 h-4"></i>
                        Upload and Preview
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>