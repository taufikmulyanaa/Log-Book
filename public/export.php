<?php
// public/export.php
require_once __DIR__ . '/../config/init.php';

// Check permissions
if (function_exists('hasPermission') && !hasPermission('logbook_export')) {
    http_response_code(403);
    die('Access denied. Export permission required.');
}

// Security checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access.');
}

$export_type = $_GET['type'] ?? 'csv';
$export_format = $_GET['format'] ?? 'logbook'; // logbook, instruments, users
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

// Validate export type
$allowed_types = ['csv', 'excel', 'pdf'];
if (!in_array($export_type, $allowed_types)) {
    die('Invalid export type.');
}

// Validate export format
$allowed_formats = ['logbook', 'instruments', 'users'];
if (!in_array($export_format, $allowed_formats)) {
    die('Invalid export format.');
}

// Set headers based on export type
$timestamp = date('Y-m-d_H-i-s');
$filename = "RD_Logbook_{$export_format}_{$timestamp}";

try {
    switch ($export_format) {
        case 'logbook':
            exportLogbookData($pdo, $export_type, $filename, $date_from, $date_to);
            break;
        case 'instruments':
            exportInstrumentData($pdo, $export_type, $filename);
            break;
        case 'users':
            exportUserData($pdo, $export_type, $filename);
            break;
    }
} catch (Exception $e) {
    log_activity("Export failed: " . $e->getMessage() . " by user ID: {$_SESSION['user_id']}");
    http_response_code(500);
    die('Export failed: ' . $e->getMessage());
}

/**
 * Export logbook entries data
 */
function exportLogbookData($pdo, $export_type, $filename, $date_from = null, $date_to = null) {
    // Build query with optional date filtering
    $sql = "
        SELECT 
            le.log_book_code,
            i.name as instrument_name,
            i.code as instrument_code,
            u.name as user_name,
            le.sample_name,
            le.trial_code,
            le.start_date,
            le.start_time,
            le.finish_date,
            le.finish_time,
            le.condition_after,
            le.status,
            le.remark,
            le.mobile_phase_val,
            le.speed_val,
            le.electrode_type_val,
            le.result_val,
            le.wavelength_scan_val,
            le.diluent_val,
            le.lamp_val,
            le.column_val,
            le.apparatus_val,
            le.medium_val,
            le.total_volume_val,
            le.vessel_quantity_val,
            le.entry_date
        FROM logbook_entries le
        JOIN instruments i ON le.instrument_id = i.id
        JOIN users u ON le.user_id = u.id
    ";
    
    $params = [];
    $where_conditions = [];
    
    if ($date_from && $date_to) {
        $where_conditions[] = "le.start_date BETWEEN :date_from AND :date_to";
        $params['date_from'] = $date_from;
        $params['date_to'] = $date_to;
    } elseif ($date_from) {
        $where_conditions[] = "le.start_date >= :date_from";
        $params['date_from'] = $date_from;
    } elseif ($date_to) {
        $where_conditions[] = "le.start_date <= :date_to";
        $params['date_to'] = $date_to;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    $sql .= " ORDER BY le.start_date DESC, le.start_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        die('No data found for export.');
    }
    
    switch ($export_type) {
        case 'csv':
            exportToCSV($data, $filename);
            break;
        case 'excel':
            exportToExcel($data, $filename, 'Logbook Entries');
            break;
        case 'pdf':
            exportToPDF($data, $filename, 'R&D Logbook Entries Report');
            break;
    }
    
    // Log the export activity
    log_activity("Logbook data exported ({$export_type}) by user ID: {$_SESSION['user_id']} - " . count($data) . " records");
}

/**
 * Export instrument data
 */
function exportInstrumentData($pdo, $export_type, $filename) {
    $sql = "
        SELECT 
            i.*,
            COUNT(le.id) as total_usage,
            MAX(le.entry_date) as last_used,
            COUNT(CASE WHEN le.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as usage_last_30_days,
            COUNT(CASE WHEN le.condition_after = 'Good' THEN 1 END) as good_condition_count,
            COUNT(CASE WHEN le.condition_after = 'Need Maintenance' THEN 1 END) as maintenance_needed_count,
            COUNT(CASE WHEN le.condition_after = 'Broken' THEN 1 END) as broken_count
        FROM instruments i 
        LEFT JOIN logbook_entries le ON i.id = le.instrument_id 
        GROUP BY i.id 
        ORDER BY i.name ASC
    ";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        die('No instrument data found for export.');
    }
    
    switch ($export_type) {
        case 'csv':
            exportToCSV($data, $filename);
            break;
        case 'excel':
            exportToExcel($data, $filename, 'Instruments');
            break;
        case 'pdf':
            exportToPDF($data, $filename, 'R&D Instruments Report');
            break;
    }
    
    log_activity("Instrument data exported ({$export_type}) by user ID: {$_SESSION['user_id']} - " . count($data) . " records");
}

/**
 * Export user data
 */
function exportUserData($pdo, $export_type, $filename) {
    // Check if user has permission to export user data
    if (!function_exists('hasPermission') || !hasPermission('user_management')) {
        die('Access denied. Admin permission required to export user data.');
    }
    
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.username,
            u.email,
            u.role,
            u.is_active,
            u.created_at,
            COUNT(le.id) as total_entries,
            MAX(le.entry_date) as last_activity,
            COUNT(CASE WHEN le.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as entries_last_30_days
        FROM users u 
        LEFT JOIN logbook_entries le ON u.id = le.user_id 
        GROUP BY u.id 
        ORDER BY u.name ASC
    ";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        die('No user data found for export.');
    }
    
    // Remove sensitive data for export
    foreach ($data as &$row) {
        // Don't export password or sensitive fields
        unset($row['password']);
        
        // Format boolean values
        $row['is_active'] = $row['is_active'] ? 'Active' : 'Inactive';
        
        // Format dates
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        $row['last_activity'] = $row['last_activity'] ? date('Y-m-d H:i:s', strtotime($row['last_activity'])) : 'Never';
    }
    
    switch ($export_type) {
        case 'csv':
            exportToCSV($data, $filename);
            break;
        case 'excel':
            exportToExcel($data, $filename, 'Users');
            break;
        case 'pdf':
            exportToPDF($data, $filename, 'R&D Users Report');
            break;
    }
    
    log_activity("User data exported ({$export_type}) by user ID: {$_SESSION['user_id']} - " . count($data) . " records");
}

/**
 * Export to CSV format
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    if (!empty($data)) {
        // Write header row
        fputcsv($output, array_keys($data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit();
}

/**
 * Export to Excel format (simplified CSV with .xlsx extension)
 * Note: This is a basic implementation. For true Excel format, use PhpSpreadsheet library
 */
function exportToExcel($data, $filename, $sheet_name = 'Data') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // For now, export as CSV with Excel headers
    // In production, consider using PhpSpreadsheet for true Excel format
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding
    fwrite($output, "\xEF\xBB\xBF");
    
    if (!empty($data)) {
        // Write header row
        fputcsv($output, array_keys($data[0]), "\t");
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row, "\t");
        }
    }
    
    fclose($output);
    exit();
}

/**
 * Export to PDF format
 * Note: This is a basic HTML-to-PDF implementation
 * For production, consider using libraries like TCPDF, DOMPDF, or mPDF
 */
function exportToPDF($data, $filename, $title = 'Export Report') {
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"{$filename}.pdf\"");
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Simple HTML to PDF conversion (basic implementation)
    // In production, use a proper PDF library
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($title); ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
            h1 { color: #005294; border-bottom: 2px solid #005294; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 30px; }
            .footer { margin-top: 30px; text-align: center; font-size: 8px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Total Records: <?php echo count($data); ?></p>
        </div>
        
        <?php if (!empty($data)): ?>
        <table>
            <thead>
                <tr>
                    <?php foreach (array_keys($data[0]) as $header): ?>
                    <th><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                    <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>R&D Logbook Management System - Confidential Document</p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // For basic PDF, we'll output HTML and let the browser handle PDF conversion
    // In production, use a proper PDF library
    header('Content-Type: text/html');
    echo $html;
    exit();
}
?>