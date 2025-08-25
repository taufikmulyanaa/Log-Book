<?php
// public/index.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Basic statistics
$total_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries")->fetchColumn();
$total_instruments = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$last_log_date = $pdo->query("SELECT MAX(entry_date) FROM logbook_entries")->fetchColumn();

// Advanced metrics
$completed_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE finish_date IS NOT NULL")->fetchColumn();
$in_progress_logs = $total_logs - $completed_logs;
$completion_rate = $total_logs > 0 ? round(($completed_logs / $total_logs) * 100, 1) : 0;

// Time-based statistics
$today_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE DATE(entry_date) = CURRENT_DATE()")->fetchColumn();
$this_week_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE YEARWEEK(entry_date) = YEARWEEK(CURRENT_DATE())")->fetchColumn();
$this_month_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE MONTH(entry_date) = MONTH(CURRENT_DATE()) AND YEAR(entry_date) = YEAR(CURRENT_DATE())")->fetchColumn();
$last_month_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE MONTH(entry_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(entry_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))")->fetchColumn();
$month_growth = $last_month_logs > 0 ? round((($this_month_logs - $last_month_logs) / $last_month_logs) * 100, 1) : ($this_month_logs > 0 ? 100 : 0);

// Instrument usage and health statistics
$active_instruments = $pdo->query("SELECT COUNT(DISTINCT instrument_id) FROM logbook_entries WHERE DATE(entry_date) >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)")->fetchColumn();
$maintenance_needed = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE condition_after = 'Need Maintenance' AND DATE(entry_date) >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)")->fetchColumn();
$broken_equipment = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE condition_after = 'Broken' AND DATE(entry_date) >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)")->fetchColumn();

// Top performing instruments
$top_instruments = $pdo->query("
    SELECT i.name, i.code, COUNT(le.id) as usage_count,
           AVG(CASE WHEN le.condition_after = 'Good' THEN 1 ELSE 0 END) * 100 as success_rate,
           MAX(le.entry_date) as last_used
    FROM instruments i 
    LEFT JOIN logbook_entries le ON i.id = le.instrument_id 
    WHERE le.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY i.id, i.name, i.code 
    ORDER BY usage_count DESC 
    LIMIT 6
")->fetchAll();

// User activity leaderboard
$user_activity = $pdo->query("
    SELECT u.name, COUNT(le.id) as entries_count,
           COUNT(CASE WHEN le.condition_after = 'Good' THEN 1 END) as successful_runs,
           MAX(le.entry_date) as last_activity,
           ROUND(AVG(CASE WHEN le.condition_after = 'Good' THEN 1 ELSE 0 END) * 100, 1) as success_rate
    FROM users u 
    LEFT JOIN logbook_entries le ON u.id = le.user_id 
    WHERE le.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.id, u.name 
    HAVING entries_count > 0
    ORDER BY entries_count DESC 
    LIMIT 8
")->fetchAll();

// Recent critical activities
$critical_activities = $pdo->query("
    SELECT le.*, u.name as user_name, i.name as instrument_name, i.code as instrument_code
    FROM logbook_entries le 
    JOIN users u ON le.user_id = u.id 
    JOIN instruments i ON le.instrument_id = i.id 
    WHERE (le.condition_after IN ('Need Maintenance', 'Broken') OR le.status = 'Not Complete')
    AND le.entry_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY 
        CASE le.condition_after 
            WHEN 'Broken' THEN 1 
            WHEN 'Need Maintenance' THEN 2 
            ELSE 3 
        END,
        le.entry_date DESC 
    LIMIT 8
")->fetchAll();

// Daily activity chart data (last 30 days)
$daily_activity = $pdo->query("
    SELECT DATE(entry_date) as date, COUNT(*) as count 
    FROM logbook_entries 
    WHERE entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY DATE(entry_date) 
    ORDER BY DATE(entry_date)
")->fetchAll();

// Condition status breakdown
$condition_stats = $pdo->query("
    SELECT 
        condition_after,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM logbook_entries WHERE condition_after IS NOT NULL), 1) as percentage
    FROM logbook_entries 
    WHERE condition_after IS NOT NULL 
    AND entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY condition_after
")->fetchAll();

// Monthly trend data
$monthly_trend = $pdo->query("
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') as month,
        COUNT(*) as total_entries,
        COUNT(CASE WHEN condition_after = 'Good' THEN 1 END) as successful_entries
    FROM logbook_entries 
    WHERE entry_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

// Prepare chart data for JavaScript
$chart_labels = array_map(function($item) { return date('M j', strtotime($item['date'])); }, $daily_activity);
$chart_data = array_map(function($item) { return (int)$item['count']; }, $daily_activity);

$monthly_labels = array_map(function($item) { return date('M Y', strtotime($item['month'] . '-01')); }, $monthly_trend);
$monthly_data = array_map(function($item) { return (int)$item['total_entries']; }, $monthly_trend);
$monthly_success = array_map(function($item) { return (int)$item['successful_entries']; }, $monthly_trend);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card">
    <div class="card-header text-base">
        Laboratory Dashboard & Analytics
    </div>
    
    <div class="p-6 space-y-6">
        <!-- Overview Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="config-section">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-muted-foreground">Total Entries</p>
                        <p class="text-2xl font-bold mt-1 text-foreground"><?php echo number_format($total_logs); ?></p>
                        <div class="flex items-center mt-2">
                            <?php if ($month_growth != 0): ?>
                                <span class="text-xs flex items-center gap-1 <?php echo $month_growth > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <i class="fas fa-arrow-<?php echo $month_growth > 0 ? 'up' : 'down'; ?>"></i>
                                    <?php echo abs($month_growth); ?>% vs last month
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-muted-foreground">No change from last month</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-book-open text-blue-600 text-lg"></i>
                    </div>
                </div>
            </div>

            <div class="config-section">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-muted-foreground">Completion Rate</p>
                        <p class="text-2xl font-bold mt-1 text-foreground"><?php echo $completion_rate; ?>%</p>
                        <div class="flex items-center mt-2">
                            <div class="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                            </div>
                            <span class="text-xs text-muted-foreground"><?php echo $completed_logs; ?>/<?php echo $total_logs; ?></span>
                        </div>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-lg"></i>
                    </div>
                </div>
            </div>

            <div class="config-section">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-muted-foreground">Active Instruments</p>
                        <p class="text-2xl font-bold mt-1 text-foreground"><?php echo $active_instruments; ?></p>
                        <div class="mt-2">
                            <span class="text-xs text-muted-foreground">of <?php echo $total_instruments; ?> total instruments</span>
                        </div>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-microscope text-purple-600 text-lg"></i>
                    </div>
                </div>
            </div>

            <div class="config-section">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-xs font-medium text-muted-foreground">This Month</p>
                        <p class="text-2xl font-bold mt-1 text-foreground"><?php echo $this_month_logs; ?></p>
                        <div class="flex justify-between text-xs text-muted-foreground mt-2">
                            <span>Week: <?php echo $this_week_logs; ?></span>
                            <span>Today: <?php echo $today_logs; ?></span>
                        </div>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-lg">
                        <i class="fas fa-calendar-alt text-orange-600 text-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 config-section">
                <div class="config-header">Activity Trends & Performance</div>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h4 class="text-sm font-medium">Daily Activity (Last 30 Days)</h4>
                        <div class="flex gap-4 text-xs">
                            <span class="flex items-center gap-1"><span class="w-3 h-3 bg-blue-500 rounded"></span>Daily Entries</span>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t space-y-4">
                    <h4 class="text-sm font-medium">Monthly Performance Comparison</h4>
                    <div style="height: 180px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="config-section">
                <div class="config-header">Equipment Health Status</div>
                <div class="space-y-4">
                    <?php if (!empty($condition_stats)): ?>
                        <?php foreach ($condition_stats as $stat): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg <?php 
                                echo $stat['condition_after'] === 'Good' ? 'bg-green-50 border border-green-200' : 
                                    ($stat['condition_after'] === 'Need Maintenance' ? 'bg-yellow-50 border border-yellow-200' : 'bg-red-50 border border-red-200'); 
                            ?>">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full <?php 
                                        echo $stat['condition_after'] === 'Good' ? 'bg-green-500' : 
                                            ($stat['condition_after'] === 'Need Maintenance' ? 'bg-yellow-500' : 'bg-red-500'); 
                                    ?>"></div>
                                    <div>
                                        <div class="text-sm font-medium"><?php echo esc_html($stat['condition_after']); ?></div>
                                        <div class="text-xs text-muted-foreground"><?php echo $stat['percentage']; ?>% of reports</div>
                                    </div>
                                </div>
                                <div class="text-lg font-bold"><?php echo $stat['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted-foreground">
                            <i class="fas fa-chart-pie text-2xl mb-2 opacity-50"></i>
                            <p class="text-sm">No condition data available</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Equipment Alerts -->
                    <?php if ($maintenance_needed > 0 || $broken_equipment > 0): ?>
                        <div class="mt-4 pt-4 border-t">
                            <h5 class="text-sm font-medium text-red-600 mb-2">⚠️ Attention Required</h5>
                            <?php if ($broken_equipment > 0): ?>
                                <div class="text-xs text-red-600 mb-1">🔧 <?php echo $broken_equipment; ?> broken equipment reported</div>
                            <?php endif; ?>
                            <?php if ($maintenance_needed > 0): ?>
                                <div class="text-xs text-yellow-600">🛠️ <?php echo $maintenance_needed; ?> equipment needs maintenance</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Performance and Activity Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="config-section">
                <div class="config-header">Top Performing Instruments (30 Days)</div>
                <div class="space-y-3">
                    <?php if (!empty($top_instruments)): ?>
                        <?php foreach ($top_instruments as $index => $instrument): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg hover:bg-accent/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 bg-muted rounded-full flex items-center justify-center">
                                        <span class="text-xs font-bold text-foreground"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate"><?php echo esc_html($instrument['name']); ?></div>
                                        <div class="text-xs text-muted-foreground"><?php echo esc_html($instrument['code']); ?></div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-sm font-medium"><?php echo $instrument['usage_count']; ?> uses</div>
                                    <div class="text-xs <?php echo $instrument['success_rate'] >= 80 ? 'text-green-600' : 'text-yellow-600'; ?>">
                                        <?php echo round($instrument['success_rate']); ?>% success
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-6 text-muted-foreground">
                            <i class="fas fa-microscope text-2xl mb-2 opacity-50"></i>
                            <p class="text-sm">No instrument activity in the last 30 days</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="config-section">
                <div class="config-header">User Activity Leaderboard (30 Days)</div>
                <div class="space-y-3">
                    <?php if (!empty($user_activity)): ?>
                        <?php foreach ($user_activity as $index => $user): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg hover:bg-accent/50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-bold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium truncate"><?php echo esc_html($user['name']); ?></div>
                                        <div class="text-xs text-muted-foreground">
                                            Last: <?php echo date('M j', strtotime($user['last_activity'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-sm font-medium"><?php echo $user['entries_count']; ?> entries</div>
                                    <div class="text-xs <?php echo $user['success_rate'] >= 80 ? 'text-green-600' : 'text-yellow-600'; ?>">
                                        <?php echo $user['success_rate']; ?>% success
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-6 text-muted-foreground">
                            <i class="fas fa-users text-2xl mb-2 opacity-50"></i>
                            <p class="text-sm">No user activity in the last 30 days</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Critical Issues and Recent Activity -->
        <?php if (!empty($critical_activities)): ?>
        <div class="config-section">
            <div class="config-header">Critical Issues & Incomplete Activities</div>
            <div class="space-y-2">
                <?php foreach ($critical_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-3 rounded-lg border-l-4 <?php 
                        echo $activity['condition_after'] === 'Broken' ? 'border-red-500 bg-red-50' : 
                            ($activity['condition_after'] === 'Need Maintenance' ? 'border-yellow-500 bg-yellow-50' : 'border-blue-500 bg-blue-50'); 
                    ?>">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center <?php 
                            echo $activity['condition_after'] === 'Broken' ? 'bg-red-500' : 
                                ($activity['condition_after'] === 'Need Maintenance' ? 'bg-yellow-500' : 'bg-blue-500'); 
                        ?>">
                            <i class="fas fa-<?php 
                                echo $activity['condition_after'] === 'Broken' ? 'times' : 
                                    ($activity['condition_after'] === 'Need Maintenance' ? 'wrench' : 'clock'); 
                            ?> text-white text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-sm font-medium"><?php echo esc_html($activity['instrument_name']); ?></div>
                                    <div class="text-xs text-muted-foreground">
                                        by <?php echo esc_html($activity['user_name']); ?> • 
                                        <?php echo date('M j, H:i', strtotime($activity['entry_date'])); ?>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    echo $activity['condition_after'] === 'Broken' ? 'bg-red-100 text-red-800' : 
                                        ($activity['condition_after'] === 'Need Maintenance' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); 
                                ?>">
                                    <?php echo esc_html($activity['condition_after'] ?: $activity['status']); ?>
                                </span>
                            </div>
                            <?php if ($activity['sample_name']): ?>
                                <div class="text-xs text-muted-foreground mt-1">
                                    Sample: <?php echo esc_html($activity['sample_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Daily Entries',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(0, 0, 0, 0.1)' }, 
                    ticks: { font: { size: 10 } }
                },
                x: { 
                    grid: { display: false }, 
                    ticks: { font: { size: 10 } }
                }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
    });

    // Monthly Comparison Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                label: 'Total Entries',
                data: <?php echo json_encode($monthly_data); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1
            }, {
                label: 'Successful',
                data: <?php echo json_encode($monthly_success); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    display: true,
                    position: 'top',
                    labels: { font: { size: 10 } }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(0, 0, 0, 0.1)' },
                    ticks: { font: { size: 10 } }
                },
                x: { 
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
});
</script>

<style>
.config-section {
    border: 1px solid hsl(214.3, 31.8%, 91.4%);
    border-radius: 0.75rem;
    padding: 1.5rem;
    background: hsl(210, 40%, 98%);
}
.config-header {
    font-weight: 600;
    color: hsl(222.2, 84%, 4.9%);
    border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%);
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
}
</style>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>