<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Increase limits for large file processing
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '180');

$preview_data = [];
$show_preview = false;
$error_message = null;
$file_stats = null;

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
        $file_size = filesize($file_path);
        
        // Check file size (limit to 50MB for safety)
        if ($file_size > 50 * 1024 * 1024) {
            $error_message = 'File too large. Maximum size is 50MB.';
        } else {
            // Fetch users and instruments for validation
            $users_map = [];
            foreach ($pdo->query("SELECT id, name, username FROM users")->fetchAll() as $user) {
                $users_map[trim(strtolower($user['name']))] = $user['id'];
                $users_map[trim(strtolower($user['username']))] = $user['id'];
            }
            
            $instruments_map = [];
            foreach ($pdo->query("SELECT id, name, code FROM instruments")->fetchAll() as $instrument) {
                $instruments_map[trim(strtolower($instrument['name']))] = $instrument['id'];
                $instruments_map[trim(strtolower($instrument['code']))] = $instrument['id'];
            }

            // Enhanced CSV reading with memory-efficient approach
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $header = fgetcsv($handle, 4096, ",");
                $row_number = 1;
                $preview_count = 0;
                $max_preview = 100; // Only preview first 100 rows
                $total_rows = 0;
                $valid_rows = 0;
                $error_rows = 0;

                // Validate header
                $required_columns = ['StartDate', 'UserName', 'Instruments'];
                $missing_columns = [];
                foreach ($required_columns as $req_col) {
                    if (!in_array($req_col, $header)) {
                        $missing_columns[] = $req_col;
                    }
                }

                if (!empty($missing_columns)) {
                    $error_message = "Missing required columns: " . implode(', ', $missing_columns);
                    fclose($handle);
                } else {
                    // Process CSV with memory management
                    while (($data_row = fgetcsv($handle, 4096, ",")) !== FALSE) {
                        $row_number++;
                        $total_rows++;
                        
                        // Reset time limit for each batch
                        if ($total_rows % 100 == 0) {
                            set_time_limit(30);
                        }
                        
                        if (count($header) != count($data_row)) {
                            $error_rows++;
                            if ($preview_count < $max_preview) {
                                $preview_data[] = [
                                    'data' => array_pad($data_row, count($header), ''), 
                                    'is_valid' => false, 
                                    'message' => "Column count mismatch on row $row_number.",
                                    'row_number' => $row_number
                                ];
                                $preview_count++;
                            }
                            continue;
                        }
                        
                        $row = array_combine($header, $data_row);
                        
                        // Handle typo in column name
                        if (isset($row['WavelenghtScan']) && !isset($row['WavelengthScan'])) {
                            $row['WavelengthScan'] = $row['WavelenghtScan'];
                            unset($row['WavelenghtScan']);
                        }

                        $validated_row = [
                            'data' => $row, 
                            'is_valid' => true, 
                            'message' => 'Ready to import',
                            'row_number' => $row_number
                        ];
                        
                        // Enhanced validation
                        $errors = [];
                        
                        // Validate UserName
                        $user_name = trim(strtolower($row['UserName'] ?? ''));
                        if (empty($user_name)) {
                            $errors[] = "User name is empty";
                        } elseif (!isset($users_map[$user_name])) {
                            $errors[] = "User '{$row['UserName']}' not found (will use admin fallback)";
                        }
                        
                        // Validate Instruments
                        $instrument_name = trim(strtolower($row['Instruments'] ?? ''));
                        if (empty($instrument_name)) {
                            $errors[] = "Instrument is empty";
                        } elseif (!isset($instruments_map[$instrument_name])) {
                            $errors[] = "Instrument '{$row['Instruments']}' not found";
                        }
                        
                        // Validate StartDate
                        if (empty($row['StartDate']) || !strtotime($row['StartDate'])) {
                            $errors[] = "Invalid or missing start date";
                        }
                        
                        // Validate StartTime
                        if (empty($row['StartTime']) || $row['StartTime'] === '1900-01-01') {
                            $errors[] = "Invalid or missing start time";
                        }

                        if (!empty($errors)) {
                            $validated_row['is_valid'] = false;
                            $validated_row['message'] = implode('; ', $errors);
                            $error_rows++;
                        } else {
                            $valid_rows++;
                        }
                        
                        // Only add to preview if within limit
                        if ($preview_count < $max_preview) {
                            $preview_data[] = $validated_row;
                            $preview_count++;
                        }
                    }
                    
                    fclose($handle);

                    // Store file statistics
                    $file_stats = [
                        'total_rows' => $total_rows,
                        'valid_rows' => $valid_rows,
                        'error_rows' => $error_rows,
                        'file_size' => $file_size,
                        'preview_rows' => $preview_count
                    ];

                    if (!empty($preview_data)) {
                        // For large files, store only valid data to save memory
                        $session_data = [];
                        rewind($handle = fopen($file_path, "r"));
                        $header = fgetcsv($handle, 4096, ",");
                        $row_number = 1;
                        
                        while (($data_row = fgetcsv($handle, 4096, ",")) !== FALSE) {
                            $row_number++;
                            
                            if (count($header) != count($data_row)) continue;
                            
                            $row = array_combine($header, $data_row);
                            
                            // Handle typo
                            if (isset($row['WavelenghtScan']) && !isset($row['WavelengthScan'])) {
                                $row['WavelengthScan'] = $row['WavelenghtScan'];
                                unset($row['WavelenghtScan']);
                            }

                            // Basic validation for storage
                            $user_name = trim(strtolower($row['UserName'] ?? ''));
                            $instrument_name = trim(strtolower($row['Instruments'] ?? ''));
                            $has_start_date = !empty($row['StartDate']) && strtotime($row['StartDate']);
                            $has_start_time = !empty($row['StartTime']) && $row['StartTime'] !== '1900-01-01';
                            
                            $is_valid = !empty($instrument_name) && 
                                       isset($instruments_map[$instrument_name]) && 
                                       $has_start_date && 
                                       $has_start_time;

                            $session_data[] = [
                                'data' => $row,
                                'is_valid' => $is_valid,
                                'message' => $is_valid ? 'Ready to import' : 'Has validation errors',
                                'row_number' => $row_number
                            ];
                        }
                        
                        fclose($handle);
                        $_SESSION['import_preview_data'] = $session_data;
                        $show_preview = true;
                    } else {
                        $error_message = "Could not read any data from the CSV file.";
                    }
                }
            } else {
                $error_message = "Could not open the CSV file.";
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

<div class="card">
    <div class="card-header text-base">
        Import Logbook Data
    </div>
    
    <div class="p-6 space-y-6">
        <?php if ($error_message): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                    <div>
                        <h4 class="font-medium text-red-800">Import Error</h4>
                        <p class="text-sm text-red-700 mt-1"><?php echo esc_html($error_message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <div>
                        <h4 class="font-medium text-green-800">Import Successful</h4>
                        <p class="text-sm text-green-700 mt-1"><?php echo esc_html(urldecode($_GET['success'])); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['import_error'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-times-circle text-red-500 mr-2"></i>
                    <div>
                        <h4 class="font-medium text-red-800">Import Failed</h4>
                        <p class="text-sm text-red-700 mt-1"><?php echo esc_html(urldecode($_GET['import_error'])); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_preview): ?>
            <!-- File Statistics -->
            <?php if ($file_stats): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-medium text-blue-800 mb-3">File Analysis</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="text-center">
                            <div class="text-lg font-bold text-blue-900"><?php echo number_format($file_stats['total_rows']); ?></div>
                            <div class="text-blue-700">Total Rows</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600"><?php echo number_format($file_stats['valid_rows']); ?></div>
                            <div class="text-green-700">Valid Rows</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-red-600"><?php echo number_format($file_stats['error_rows']); ?></div>
                            <div class="text-red-700">Error Rows</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-gray-600"><?php echo number_format($file_stats['file_size'] / 1024 / 1024, 1); ?> MB</div>
                            <div class="text-gray-700">File Size</div>
                        </div>
                    </div>
                    <?php if ($file_stats['total_rows'] > 100): ?>
                        <p class="text-xs text-blue-600 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Showing preview of first <?php echo $file_stats['preview_rows']; ?> rows. All <?php echo number_format($file_stats['total_rows']); ?> rows will be processed during import.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Data Preview -->
            <div class="border border-border rounded-lg overflow-hidden">
                <div class="bg-muted p-4 border-b">
                    <h4 class="font-medium text-foreground">Data Preview</h4>
                    <p class="text-sm text-muted-foreground">Review the data before confirming import</p>
                </div>
                
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full text-xs">
                        <thead class="bg-muted sticky top-0">
                            <tr>
                                <th class="p-2 text-left">#</th>
                                <th class="p-2 text-left">Status</th>
                                <th class="p-2 text-left">User</th>
                                <th class="p-2 text-left">Instrument</th>
                                <th class="p-2 text-left">Sample</th>
                                <th class="p-2 text-left">Start Date</th>
                                <th class="p-2 text-left">Start Time</th>
                                <th class="p-2 text-left">Issues</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $index => $row): ?>
                                <tr class="border-t <?php echo !$row['is_valid'] ? 'bg-red-50' : 'hover:bg-accent/50'; ?>">
                                    <td class="p-2 font-mono"><?php echo $row['row_number']; ?></td>
                                    <td class="p-2">
                                        <?php if ($row['is_valid']): ?>
                                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">Ready</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-red-100 text-red-800">Error</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-2 truncate max-w-24" title="<?php echo esc_html($row['data']['UserName'] ?? ''); ?>">
                                        <?php echo esc_html($row['data']['UserName'] ?? ''); ?>
                                    </td>
                                    <td class="p-2 truncate max-w-32" title="<?php echo esc_html($row['data']['Instruments'] ?? ''); ?>">
                                        <?php echo esc_html($row['data']['Instruments'] ?? ''); ?>
                                    </td>
                                    <td class="p-2 truncate max-w-24" title="<?php echo esc_html($row['data']['SampleName'] ?? ''); ?>">
                                        <?php echo esc_html($row['data']['SampleName'] ?? ''); ?>
                                    </td>
                                    <td class="p-2"><?php echo esc_html($row['data']['StartDate'] ?? ''); ?></td>
                                    <td class="p-2"><?php echo esc_html($row['data']['StartTime'] ?? ''); ?></td>
                                    <td class="p-2 text-xs <?php echo $row['is_valid'] ? 'text-green-600' : 'text-red-600'; ?>" title="<?php echo esc_html($row['message']); ?>">
                                        <?php echo esc_html(strlen($row['message']) > 30 ? substr($row['message'], 0, 30) . '...' : $row['message']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Import Actions -->
            <div class="flex justify-between items-center pt-4 border-t">
                <a href="import.php?cancel=1" class="btn btn-secondary text-xs">
                    <i class="fas fa-arrow-left mr-1"></i>Cancel Import
                </a>
                
                <form action="import_confirm_action.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn btn-primary text-xs" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin mr-1\'></i>Processing...'; this.disabled=true;">
                        <i class="fas fa-upload mr-1"></i>
                        Confirm Import (<?php echo number_format($file_stats['valid_rows'] ?? count($preview_data)); ?> records)
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- Upload Form -->
            <div class="border border-border rounded-lg p-6">
                <form action="import.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="csv_file" class="form-label text-sm">Select CSV File</label>
                            <input type="file" name="csv_file" id="csv_file" 
                                   class="mt-2 block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors" 
                                   required accept=".csv">
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-medium text-blue-800 mb-2">CSV Format Requirements:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• <strong>Required columns:</strong> StartDate, StartTime, UserName, Instruments</li>
                                <li>• <strong>Date format:</strong> YYYY-MM-DD or DD/MM/YYYY</li>
                                <li>• <strong>Time format:</strong> HH:MM:SS or HH:MM</li>
                                <li>• <strong>UserName:</strong> Must match existing users (or will use admin fallback)</li>
                                <li>• <strong>Instruments:</strong> Must match existing instrument names or codes</li>
                                <li>• <strong>File size limit:</strong> Maximum 50MB</li>
                            </ul>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-medium text-yellow-800 mb-2">⚠️ Large File Processing:</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Files with 1000+ rows may take several minutes to process</li>
                                <li>• Please do not close the browser during import</li>
                                <li>• Ensure all UserName and Instruments exist before import</li>
                                <li>• Invalid records will be skipped with detailed logging</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary text-sm">
                            <i class="fas fa-upload mr-2"></i>
                            Upload and Preview
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File size validation on client side
    const fileInput = document.getElementById('csv_file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 50 * 1024 * 1024; // 50MB
                if (file.size > maxSize) {
                    alert('File too large. Maximum size is 50MB.');
                    this.value = '';
                    return;
                }
                
                // Show file info
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                console.log(`Selected file: ${file.name} (${sizeMB} MB)`);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>