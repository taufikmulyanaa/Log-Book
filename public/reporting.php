<?php
// public/reporting.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Advanced reporting features that could be added
class LogbookReporting {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Usage statistics by instrument
    public function getInstrumentUsage($start_date, $end_date) {
        $sql = "SELECT i.name, COUNT(le.id) as usage_count, 
                       AVG(TIMESTAMPDIFF(HOUR, 
                           CONCAT(le.start_date, ' ', le.start_time),
                           CONCAT(le.finish_date, ' ', le.finish_time)
                       )) as avg_duration_hours
                FROM instruments i 
                LEFT JOIN logbook_entries le ON i.id = le.instrument_id 
                WHERE le.start_date BETWEEN :start_date AND :end_date
                GROUP BY i.id, i.name
                ORDER BY usage_count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        
        return $stmt->fetchAll();
    }
    
    // User productivity report
    public function getUserProductivity($start_date, $end_date) {
        $sql = "SELECT u.name, COUNT(le.id) as entries_count,
                       COUNT(CASE WHEN le.condition_after = 'Good' THEN 1 END) as successful_runs
                FROM users u 
                LEFT JOIN logbook_entries le ON u.id = le.user_id 
                WHERE le.start_date BETWEEN :start_date AND :end_date
                GROUP BY u.id, u.name
                ORDER BY entries_count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        
        return $stmt->fetchAll();
    }
    
    // Equipment maintenance alerts
    public function getMaintenanceAlerts() {
        $sql = "SELECT i.name, i.code,
                       COUNT(CASE WHEN le.condition_after = 'Need Maintenance' THEN 1 END) as maintenance_needed,
                       COUNT(CASE WHEN le.condition_after = 'Broken' THEN 1 END) as broken_count,
                       MAX(le.start_date) as last_used
                FROM instruments i 
                LEFT JOIN logbook_entries le ON i.id = le.instrument_id 
                GROUP BY i.id, i.name, i.code
                HAVING maintenance_needed > 0 OR broken_count > 0 OR last_used < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY broken_count DESC, maintenance_needed DESC";
        
        return $this->pdo->query($sql)->fetchAll();
    }
}

$reporting = new LogbookReporting($pdo);

// Get date range from request or default to last 30 days
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));

$instrument_usage = $reporting->getInstrumentUsage($start_date, $end_date);
$user_productivity = $reporting->getUserProductivity($start_date, $end_date);
$maintenance_alerts = $reporting->getMaintenanceAlerts();
?>

<div class="card">
    <div class="card-header text-base">
        Reports & Analytics
    </div>
    
    <div class="p-6 space-y-6">
        <!-- Date Range Filter Section -->
        <div class="border-b pb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="form-label text-xs">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo esc_html($start_date); ?>" 
                           class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">End Date</label>
                    <input type="date" name="end_date" value="<?php echo esc_html($end_date); ?>" 
                           class="form-input text-xs">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary text-xs w-full">
                        <i class="fas fa-chart-line mr-1"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Instrument Usage Statistics Section -->
        <div>
            <h3 class="text-sm font-semibold mb-4">Instrument Usage Statistics</h3>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-2 text-left">Instrument</th>
                            <th class="p-2 text-right">Usage Count</th>
                            <th class="p-2 text-right">Avg Duration (hrs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($instrument_usage)): ?>
                            <tr>
                                <td colspan="3" class="p-8 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-chart-bar text-2xl opacity-50"></i>
                                        <p>No data available for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instrument_usage as $usage): ?>
                            <tr class="border-t border-border">
                                <td class="p-2"><?php echo esc_html($usage['name']); ?></td>
                                <td class="p-2 text-right"><?php echo (int)$usage['usage_count']; ?></td>
                                <td class="p-2 text-right"><?php echo number_format($usage['avg_duration_hours'] ?? 0, 1); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Productivity Section -->
        <div>
            <h3 class="text-sm font-semibold mb-4">User Productivity Report</h3>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-2 text-left">User</th>
                            <th class="p-2 text-right">Total Entries</th>
                            <th class="p-2 text-right">Successful Runs</th>
                            <th class="p-2 text-right">Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($user_productivity)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-users text-2xl opacity-50"></i>
                                        <p>No user activity data available for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($user_productivity as $user): ?>
                            <tr class="border-t border-border">
                                <td class="p-2"><?php echo esc_html($user['name']); ?></td>
                                <td class="p-2 text-right"><?php echo (int)$user['entries_count']; ?></td>
                                <td class="p-2 text-right"><?php echo (int)$user['successful_runs']; ?></td>
                                <td class="p-2 text-right">
                                    <?php 
                                    $success_rate = $user['entries_count'] > 0 ? round(($user['successful_runs'] / $user['entries_count']) * 100, 1) : 0;
                                    echo $success_rate . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Maintenance Alerts Section -->
        <?php if (!empty($maintenance_alerts)): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-yellow-800 mb-3">
                <i class="fas fa-exclamation-triangle mr-2"></i>Maintenance Alerts
            </h3>
            <div class="space-y-2 text-xs">
                <?php foreach ($maintenance_alerts as $alert): ?>
                <div class="bg-white p-3 rounded border border-yellow-200">
                    <div class="flex justify-between items-start">
                        <div>
                            <strong><?php echo esc_html($alert['name']); ?></strong> 
                            <span class="text-muted-foreground">(<?php echo esc_html($alert['code']); ?>)</span>
                        </div>
                        <div class="text-right text-xs">
                            <?php if ($alert['broken_count'] > 0): ?>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full">
                                    <i class="fas fa-times-circle mr-1"></i>Broken (<?php echo (int)$alert['broken_count']; ?> reports)
                                </span>
                            <?php elseif ($alert['maintenance_needed'] > 0): ?>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                                    <i class="fas fa-wrench mr-1"></i>Needs Maintenance (<?php echo (int)$alert['maintenance_needed']; ?> reports)
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full">
                                    <i class="fas fa-clock mr-1"></i>Not used since <?php echo esc_html(date('M j, Y', strtotime($alert['last_used']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>