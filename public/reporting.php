<?php
// public/advanced_reporting.php
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

<div class="space-y-4">
    <div class="bg-card p-4 rounded-lg border border-border">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?php echo esc_html($start_date); ?>" 
                       class="bg-muted border border-border rounded-md px-3 py-1.5 text-xs">
            </div>
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">End Date</label>
                <input type="date" name="end_date" value="<?php echo esc_html($end_date); ?>" 
                       class="bg-muted border border-border rounded-md px-3 py-1.5 text-xs">
            </div>
            <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-md text-xs hover:bg-primary/90">
                Generate Report
            </button>
        </form>
    </div>

    <div class="bg-card p-4 rounded-lg border border-border">
        <h3 class="text-base font-semibold mb-3">Instrument Usage Statistics</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-2 text-left">Instrument</th>
                        <th class="p-2 text-right">Usage Count</th>
                        <th class="p-2 text-right">Avg Duration (hrs)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instrument_usage as $usage): ?>
                    <tr class="border-t border-border">
                        <td class="p-2"><?php echo esc_html($usage['name']); ?></td>
                        <td class="p-2 text-right"><?php echo (int)$usage['usage_count']; ?></td>
                        <td class="p-2 text-right"><?php echo number_format($usage['avg_duration_hours'] ?? 0, 1); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($maintenance_alerts)): ?>
    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
        <h3 class="text-base font-semibold text-yellow-800 mb-3">🚨 Maintenance Alerts</h3>
        <div class="space-y-2 text-xs">
            <?php foreach ($maintenance_alerts as $alert): ?>
            <div class="bg-white p-2 rounded border border-yellow-200">
                <strong><?php echo esc_html($alert['name']); ?></strong> (<?php echo esc_html($alert['code']); ?>)
                <?php if ($alert['broken_count'] > 0): ?>
                    <span class="text-red-600 ml-2">⚠️ Reported broken <?php echo (int)$alert['broken_count']; ?> times</span>
                <?php elseif ($alert['maintenance_needed'] > 0): ?>
                    <span class="text-orange-600 ml-2">🔧 Needs maintenance (<?php echo (int)$alert['maintenance_needed']; ?> reports)</span>
                <?php else: ?>
                    <span class="text-gray-600 ml-2">📅 Not used since <?php echo esc_html($alert['last_used']); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>