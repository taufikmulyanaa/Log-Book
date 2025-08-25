<?php
// public/get_instrument_details.php
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
    // Get instrument basic data
    $stmt = $pdo->prepare("SELECT * FROM instruments WHERE id = :id");
    $stmt->execute(['id' => $instrument_id]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instrument) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Instrument not found']);
        exit();
    }

    // Get usage statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_usage,
            MAX(entry_date) as last_used,
            COUNT(CASE WHEN entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as usage_last_30_days,
            COUNT(CASE WHEN entry_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as usage_last_7_days,
            COUNT(CASE WHEN condition_after = 'Good' THEN 1 END) as good_condition_count,
            COUNT(CASE WHEN condition_after = 'Need Maintenance' THEN 1 END) as maintenance_needed_count,
            COUNT(CASE WHEN condition_after = 'Broken' THEN 1 END) as broken_count,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_runs,
            AVG(CASE 
                WHEN finish_date IS NOT NULL AND finish_time IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, 
                    CONCAT(start_date, ' ', start_time), 
                    CONCAT(finish_date, ' ', finish_time)
                ) 
            END) as avg_duration_minutes
        FROM logbook_entries 
        WHERE instrument_id = :id
    ");
    $stats_stmt->execute(['id' => $instrument_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent entries (last 10)
    $recent_stmt = $pdo->prepare("
        SELECT le.*, u.name as user_name 
        FROM logbook_entries le
        JOIN users u ON le.user_id = u.id
        WHERE le.instrument_id = :id 
        ORDER BY le.entry_date DESC 
        LIMIT 10
    ");
    $recent_stmt->execute(['id' => $instrument_id]);
    $recent_entries = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top users of this instrument
    $top_users_stmt = $pdo->prepare("
        SELECT u.name, COUNT(*) as usage_count
        FROM logbook_entries le
        JOIN users u ON le.user_id = u.id
        WHERE le.instrument_id = :id
        GROUP BY u.id, u.name
        ORDER BY usage_count DESC
        LIMIT 5
    ");
    $top_users_stmt->execute(['id' => $instrument_id]);
    $top_users = $top_users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parameter columns for display
    $parameter_columns = [
        'MobilePhase' => 'Mobile Phase',
        'Speed' => 'Speed',
        'ElectrodeType' => 'Electrode Type',
        'Result' => 'Result',
        'WavelengthScan' => 'Wavelength Scan',
        'Diluent' => 'Diluent',
        'Lamp' => 'Lamp',
        'Column' => 'Column',
        'Apparatus' => 'Apparatus',
        'Medium' => 'Medium',
        'TotalVolume' => 'Total Volume',
        'VesselQuantity' => 'Vessel Quantity'
    ];

    $active_parameters = [];
    foreach ($parameter_columns as $param_key => $param_name) {
        if ($instrument[$param_key]) {
            $active_parameters[] = $param_name;
        }
    }

    // Calculate health status
    $health_status = 'Good';
    $health_class = 'text-green-600';
    if ($stats['broken_count'] > 0) {
        $health_status = 'Needs Attention - Broken Reports';
        $health_class = 'text-red-600';
    } elseif ($stats['maintenance_needed_count'] > 0) {
        $health_status = 'Needs Maintenance';
        $health_class = 'text-yellow-600';
    } elseif ($stats['usage_last_30_days'] == 0) {
        $health_status = 'Unused (30 days)';
        $health_class = 'text-gray-600';
    }

    // Generate HTML content
    ob_start();
    ?>
    <div class="space-y-6">
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-medium text-muted-foreground">Instrument Name</label>
                <div class="text-sm font-medium mt-1"><?php echo esc_html($instrument['name']); ?></div>
            </div>
            <div>
                <label class="text-xs font-medium text-muted-foreground">Instrument Code</label>
                <div class="text-sm font-mono mt-1 text-blue-600"><?php echo esc_html($instrument['code']); ?></div>
            </div>
        </div>

        <!-- Usage Statistics -->
        <div>
            <h4 class="text-sm font-semibold border-b pb-2 mb-3">Usage Statistics</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="text-lg font-bold text-blue-800"><?php echo number_format($stats['total_usage']); ?></div>
                    <div class="text-xs text-blue-600">Total Uses</div>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="text-lg font-bold text-green-800"><?php echo number_format($stats['usage_last_30_days']); ?></div>
                    <div class="text-xs text-green-600">Last 30 Days</div>
                </div>
                <div class="text-center p-3 bg-purple-50 rounded-lg">
                    <div class="text-lg font-bold text-purple-800"><?php echo number_format($stats['completed_runs']); ?></div>
                    <div class="text-xs text-purple-600">Completed Runs</div>
                </div>
                <div class="text-center p-3 bg-orange-50 rounded-lg">
                    <div class="text-lg font-bold text-orange-800">
                        <?php echo $stats['avg_duration_minutes'] ? number_format($stats['avg_duration_minutes'], 0) . 'm' : 'N/A'; ?>
                    </div>
                    <div class="text-xs text-orange-600">Avg Duration</div>
                </div>
            </div>
        </div>

        <!-- Health Status -->
        <div>
            <h4 class="text-sm font-semibold border-b pb-2 mb-3">Health Status</h4>
            <div class="flex items-center gap-4">
                <div class="flex items-center">
                    <span class="w-3 h-3 rounded-full bg-current <?php echo $health_class; ?> mr-2"></span>
                    <span class="text-sm <?php echo $health_class; ?> font-medium"><?php echo $health_status; ?></span>
                </div>
                <?php if ($stats['last_used']): ?>
                <div class="text-sm text-muted-foreground">
                    Last used: <?php echo date('M j, Y H:i', strtotime($stats['last_used'])); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($stats['maintenance_needed_count'] > 0 || $stats['broken_count'] > 0): ?>
            <div class="mt-2 text-sm">
                <?php if ($stats['broken_count'] > 0): ?>
                <div class="text-red-600">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?php echo $stats['broken_count']; ?> broken condition report(s)
                </div>
                <?php endif; ?>
                <?php if ($stats['maintenance_needed_count'] > 0): ?>
                <div class="text-yellow-600">
                    <i class="fas fa-wrench mr-1"></i>
                    <?php echo $stats['maintenance_needed_count']; ?> maintenance needed report(s)
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Active Parameters -->
        <?php if (!empty($active_parameters)): ?>
        <div>
            <h4 class="text-sm font-semibold border-b pb-2 mb-3">Active Parameters</h4>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($active_parameters as $param): ?>
                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?php echo esc_html($param); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Users -->
        <?php if (!empty($top_users)): ?>
        <div>
            <h4 class="text-sm font-semibold border-b pb-2 mb-3">Top Users</h4>
            <div class="space-y-2">
                <?php foreach ($top_users as $index => $user): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-semibold">
                            <?php echo $index + 1; ?>
                        </div>
                        <span class="text-sm"><?php echo esc_html($user['name']); ?></span>
                    </div>
                    <span class="text-xs text-muted-foreground"><?php echo $user['usage_count']; ?> uses</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <?php if (!empty($recent_entries)): ?>
        <div>
            <h4 class="text-sm font-semibold border-b pb-2 mb-3">Recent Activity (Last 10 Uses)</h4>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($recent_entries as $entry): ?>
                <div class="flex items-center justify-between p-2 border border-gray-200 rounded text-xs">
                    <div>
                        <div class="font-medium"><?php echo esc_html($entry['log_book_code'] ?: 'No Code'); ?></div>
                        <div class="text-muted-foreground">
                            by <?php echo esc_html($entry['user_name']); ?>
                            <?php if ($entry['sample_name']): ?>
                            • <?php echo esc_html($entry['sample_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div><?php echo date('M j', strtotime($entry['entry_date'])); ?></div>
                        <div class="text-muted-foreground"><?php echo date('H:i', strtotime($entry['entry_date'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    $html_content = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html_content,
        'instrument' => $instrument,
        'stats' => $stats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>