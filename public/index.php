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

// This month statistics
$this_month_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE MONTH(entry_date) = MONTH(CURRENT_DATE()) AND YEAR(entry_date) = YEAR(CURRENT_DATE())")->fetchColumn();
$last_month_logs = $pdo->query("SELECT COUNT(*) FROM logbook_entries WHERE MONTH(entry_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(entry_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))")->fetchColumn();
$month_growth = $last_month_logs > 0 ? round((($this_month_logs - $last_month_logs) / $last_month_logs) * 100, 1) : 0;

// Instrument usage statistics
$instrument_usage = $pdo->query("
    SELECT i.name, i.code, COUNT(le.id) as usage_count,
           AVG(CASE WHEN le.condition_after = 'Good' THEN 1 ELSE 0 END) * 100 as good_condition_rate
    FROM instruments i 
    LEFT JOIN logbook_entries le ON i.id = le.instrument_id 
    GROUP BY i.id, i.name, i.code 
    ORDER BY usage_count DESC 
    LIMIT 5
")->fetchAll();

// Recent activities (last 7 days with more details)
$recent_activities = $pdo->query("
    SELECT le.*, u.name as user_name, i.name as instrument_name, i.code as instrument_code
    FROM logbook_entries le 
    JOIN users u ON le.user_id = u.id 
    JOIN instruments i ON le.instrument_id = i.id 
    WHERE le.entry_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY le.entry_date DESC 
    LIMIT 5
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
    GROUP BY condition_after
")->fetchAll();

// Top users activity
$user_activity = $pdo->query("
    SELECT u.name, COUNT(le.id) as log_count,
           MAX(le.entry_date) as last_activity
    FROM users u 
    LEFT JOIN logbook_entries le ON u.id = le.user_id 
    GROUP BY u.id, u.name 
    ORDER BY log_count DESC 
    LIMIT 5
")->fetchAll();

// Prepare chart data for JavaScript
$chart_labels = array_map(function($item) { return date('M j', strtotime($item['date'])); }, $daily_activity);
$chart_data = array_map(function($item) { return (int)$item['count']; }, $daily_activity);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Total Log Book</p>
                    <p class="text-xl font-bold mt-1 text-foreground"><?php echo number_format($total_logs); ?></p>
                    <?php if ($month_growth != 0): ?>
                        <p class="text-[10px] mt-1 flex items-center gap-1 <?php echo $month_growth > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <i class="fas fa-arrow-<?php echo $month_growth > 0 ? 'up' : 'down'; ?> text-[10px]"></i>
                            <?php echo abs($month_growth); ?>% vs last month
                        </p>
                    <?php endif; ?>
                </div>
                <div class="bg-blue-100 p-2 rounded-lg">
                    <i class="fas fa-book-open text-blue-600 text-base"></i>
                </div>
            </div>
        </div>

        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Completion Rate</p>
                    <p class="text-xl font-bold mt-1 text-foreground"><?php echo $completion_rate; ?>%</p>
                    <p class="text-[10px] mt-1 text-muted-foreground"><?php echo $completed_logs; ?> of <?php echo $total_logs; ?> completed</p>
                </div>
                <div class="bg-green-100 p-2 rounded-lg">
                    <i class="fas fa-check-circle text-green-600 text-base"></i>
                </div>
            </div>
        </div>

        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">Instruments</p>
                    <p class="text-xl font-bold mt-1 text-foreground"><?php echo $total_instruments; ?></p>
                    <p class="text-[10px] mt-1 text-muted-foreground">Laboratory equipment</p>
                </div>
                <div class="bg-purple-100 p-2 rounded-lg">
                    <i class="fas fa-microscope text-purple-600 text-base"></i>
                </div>
            </div>
        </div>

        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-muted-foreground">This Month</p>
                    <p class="text-xl font-bold mt-1 text-foreground"><?php echo $this_month_logs; ?></p>
                    <p class="text-[10px] mt-1 text-muted-foreground">New entries</p>
                </div>
                <div class="bg-orange-100 p-2 rounded-lg">
                    <i class="fas fa-calendar-alt text-orange-600 text-base"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-base font-semibold text-foreground">Activity Trend</h3>
                    <p class="text-xs text-muted-foreground">Daily logbook entries (Last 30 days)</p>
                </div>
                <div class="text-xs text-muted-foreground">
                    <i class="fas fa-chart-line mr-1"></i>
                    Analytics
                </div>
            </div>
            <div style="height: 250px;">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-base font-semibold text-foreground">Equipment Status</h3>
                    <p class="text-xs text-muted-foreground">After usage condition</p>
                </div>
            </div>
            
            <div class="space-y-3">
                <?php foreach ($condition_stats as $stat): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full <?php 
                                echo $stat['condition_after'] === 'Good' ? 'bg-green-500' : 
                                    ($stat['condition_after'] === 'Need Maintenance' ? 'bg-yellow-500' : 'bg-red-500'); 
                            ?>"></div>
                            <span class="text-xs text-foreground"><?php echo esc_html($stat['condition_after']); ?></span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-foreground"><?php echo $stat['count']; ?></div>
                            <div class="text-[10px] text-muted-foreground"><?php echo $stat['percentage']; ?>%</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-4 flex justify-center">
                <div style="width: 100px; height: 100px;">
                    <canvas id="conditionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-base font-semibold text-foreground">Most Used Instruments</h3>
                    <p class="text-xs text-muted-foreground">Equipment utilization ranking</p>
                </div>
                <a href="settings.php" class="text-xs text-primary hover:underline">Manage</a>
            </div>
            
            <div class="space-y-3">
                <?php foreach ($instrument_usage as $index => $instrument): ?>
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-muted rounded-full flex items-center justify-center">
                            <span class="text-xs font-medium text-foreground"><?php echo $index + 1; ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-foreground truncate"><?php echo esc_html($instrument['name']); ?></p>
                                    <p class="text-[10px] text-muted-foreground"><?php echo esc_html($instrument['code']); ?></p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-medium text-foreground"><?php echo $instrument['usage_count']; ?> uses</p>
                                    <p class="text-[10px] text-green-600"><?php echo round($instrument['good_condition_rate']); ?>% good</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-base font-semibold text-foreground">Recent Activities</h3>
                    <p class="text-xs text-muted-foreground">Latest logbook entries</p>
                </div>
                <a href="logbook_list.php" class="text-xs text-primary hover:underline">View All</a>
            </div>
            
            <div class="space-y-2">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-6">
                        <i class="fas fa-inbox text-muted-foreground text-xl mb-2"></i>
                        <p class="text-xs text-muted-foreground">No recent activities</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-start gap-2 p-2 rounded-lg hover:bg-accent/50 transition-colors">
                            <div class="flex-shrink-0 w-1.5 h-1.5 bg-blue-500 rounded-full mt-1.5"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs font-medium text-foreground"><?php echo esc_html($activity['instrument_name']); ?></p>
                                        <p class="text-[10px] text-muted-foreground">by <?php echo esc_html($activity['user_name']); ?></p>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground flex-shrink-0">
                                        <?php echo date('M j, H:i', strtotime($activity['entry_date'])); ?>
                                    </div>
                                </div>
                                <?php if ($activity['sample_name']): ?>
                                    <p class="text-[10px] text-muted-foreground mt-0.5">Sample: <?php echo esc_html($activity['sample_name']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
        <h3 class="text-base font-semibold text-foreground mb-3">Quick Actions</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="entry.php" class="flex flex-col items-center gap-2 p-3 rounded-lg border border-border hover:bg-accent/50 transition-colors group">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <i class="fas fa-plus text-blue-600 text-sm"></i>
                </div>
                <span class="text-xs font-medium text-foreground">New Entry</span>
            </a>
            
            <a href="logbook_list.php" class="flex flex-col items-center gap-2 p-3 rounded-lg border border-border hover:bg-accent/50 transition-colors group">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                    <i class="fas fa-table text-green-600 text-sm"></i>
                </div>
                <span class="text-xs font-medium text-foreground">View List</span>
            </a>
            
            <a href="settings.php" class="flex flex-col items-center gap-2 p-3 rounded-lg border border-border hover:bg-accent/50 transition-colors group">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                    <i class="fas fa-cogs text-purple-600 text-sm"></i>
                </div>
                <span class="text-xs font-medium text-foreground">Settings</span>
            </a>
            
            <a href="reporting.php" class="flex flex-col items-center gap-2 p-3 rounded-lg border border-border hover:bg-accent/50 transition-colors group">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                    <i class="fas fa-chart-bar text-orange-600 text-sm"></i>
                </div>
                <span class="text-xs font-medium text-foreground">Reports</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Daily Entries',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: 'hsl(221.2, 83.2%, 53.3%)',
                backgroundColor: 'hsla(221.2, 83.2%, 53.3%, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 2,
                borderWidth: 1.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'hsl(214.3, 31.8%, 91.4%)' }, ticks: { font: { size: 8 } } },
                x: { grid: { display: false }, ticks: { font: { size: 8 } } }
            }
        }
    });

    // Condition Chart
    const conditionCtx = document.getElementById('conditionChart').getContext('2d');
    const conditionData = <?php echo json_encode(array_values(array_map(function($item) { return (int)$item['count']; }, $condition_stats))); ?>;
    const conditionLabels = <?php echo json_encode(array_values(array_map(function($item) { return $item['condition_after']; }, $condition_stats))); ?>;
    
    new Chart(conditionCtx, {
        type: 'doughnut',
        data: {
            labels: conditionLabels,
            datasets: [{
                data: conditionData,
                backgroundColor: ['hsl(142.1, 76.2%, 36.3%)', 'hsl(47.9, 95.8%, 53.1%)', 'hsl(0, 84.2%, 60.2%)'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false }, tooltip: { bodyFont: { size: 10 } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>