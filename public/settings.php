<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Data untuk tab Instrument Matrix
$stmt = $pdo->query("SELECT * FROM instruments ORDER BY name ASC");
$instruments = $stmt->fetchAll();
$parameter_columns = [
    'MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 'Diluent', 
    'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'
];

// Data untuk tab Audit Trail
function parse_log_file($file_path, $pdo) {
    $users_map = [];
    try {
        $users_stmt = $pdo->query("SELECT id, name, username FROM users");
        foreach ($users_stmt->fetchAll() as $user) {
            $users_map[$user['id']] = $user['name'];
        }
    } catch (PDOException $e) {
        // Abaikan jika query gagal
    }

    if (!file_exists($file_path) || !is_readable($file_path)) {
        return [];
    }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_entries = [];

    foreach ($lines as $line) {
        $entry = ['timestamp' => 'N/A', 'message' => $line, 'user' => 'System'];
        if (preg_match('/^\[(.*?)\]\s-\s(.*)$/', $line, $matches)) {
            $entry['timestamp'] = $matches[1];
            $entry['message'] = $matches[2];
        }

        if (preg_match('/by user ID: (\d+)/', $entry['message'], $user_matches)) {
            $user_id = $user_matches[1];
            $entry['user'] = $users_map[$user_id] ?? "User ID: $user_id";
        } elseif (preg_match('/User logged out: (.*)/', $entry['message'], $user_matches)) {
            $entry['user'] = $user_matches[1];
        }

        $log_entries[] = $entry;
    }
    return array_reverse($log_entries);
}

// System Information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
];

$log_file = __DIR__ . '/../logs/app.log';
$logs = parse_log_file($log_file, $pdo);

// Database statistics
try {
    $db_stats = [
        'total_entries' => $pdo->query("SELECT COUNT(*) FROM logbook_entries")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_instruments' => $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn(),
        'db_size' => $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'size_mb' FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $db_stats = ['error' => 'Unable to fetch database statistics'];
}
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<style>
    .tab-link { transition: all 0.2s ease-in-out; }
    .tab-link.active { background-color: hsl(210, 40%, 96.1%); border-color: hsl(221.2, 83.2%, 53.3%); color: hsl(221.2, 83.2%, 53.3%); font-weight: 600; }
    .matrix-checkbox { appearance: none; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 0.25rem; width: 1rem; height: 1rem; cursor: pointer; position: relative; transition: all 0.2s; }
    .matrix-checkbox:checked { background-color: hsl(221.2, 83.2%, 53.3%); border-color: hsl(221.2, 83.2%, 53.3%); }
    .matrix-checkbox:checked::after { content: '✓'; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 0.6rem; font-weight: bold; }
    .matrix-table { font-size: 0.75rem; }
    .matrix-table th, .matrix-table td { padding: 0.375rem; font-size: 0.75rem; line-height: 1.2; }
    .matrix-table th { font-weight: 500; white-space: nowrap; }
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info { display: none; }
    .config-section { border: 1px solid hsl(214.3, 31.8%, 91.4%); border-radius: 0.5rem; padding: 1rem; background: hsl(210, 40%, 98%); }
    .config-header { font-weight: 600; color: hsl(222.2, 84%, 4.9%); border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%); padding-bottom: 0.5rem; margin-bottom: 1rem; }
    .config-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%); }
    .config-item:last-child { border-bottom: none; }
    .status-indicator { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
    .status-active { background-color: #10b981; }
    .status-inactive { background-color: #ef4444; }
    .status-warning { background-color: #f59e0b; }
</style>

<div class="card">
    <div class="card-header text-base">
        System Settings & Configuration
    </div>
    
    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Navigation Sidebar -->
            <div class="lg:col-span-1">
                <div class="space-y-1 sticky top-4">
                    <div class="text-xs font-medium text-muted-foreground mb-2 px-2">GENERAL</div>
                    <button data-tab="overview" class="tab-link active w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-tachometer-alt w-4 mr-2"></i>Overview</button>
                    <button data-tab="general" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-sliders-h w-4 mr-2"></i>General</button>
                    <button data-tab="security" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-shield-alt w-4 mr-2"></i>Security</button>
                    
                    <div class="text-xs font-medium text-muted-foreground mb-2 px-2 mt-4">USER & ACCESS</div>
                    <button data-tab="users" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-users-cog w-4 mr-2"></i>User Management</button>
                    <button data-tab="integrations" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-puzzle-piece w-4 mr-2"></i>Authentication</button>
                    
                    <div class="text-xs font-medium text-muted-foreground mb-2 px-2 mt-4">NOTIFICATIONS</div>
                    <button data-tab="email" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-envelope w-4 mr-2"></i>Email Settings</button>
                    <button data-tab="alerts" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-bell w-4 mr-2"></i>System Alerts</button>
                    
                    <div class="text-xs font-medium text-muted-foreground mb-2 px-2 mt-4">DATA & SYSTEM</div>
                    <button data-tab="matrix" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-microscope w-4 mr-2"></i>Instrument Matrix</button>
                    <button data-tab="backup" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-database w-4 mr-2"></i>Backup & Export</button>
                    <button data-tab="maintenance" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-tools w-4 mr-2"></i>Maintenance</button>
                    <button data-tab="audit" class="tab-link w-full text-left px-3 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50 rounded-r-md"><i class="fas fa-history w-4 mr-2"></i>Audit Trail</button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-4">
                <!-- Overview Tab -->
                <div id="overview" class="tab-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="config-section">
                            <div class="text-center">
                                <i class="fas fa-database text-blue-500 text-2xl mb-2"></i>
                                <div class="text-lg font-bold"><?php echo number_format($db_stats['total_entries'] ?? 0); ?></div>
                                <div class="text-xs text-muted-foreground">Total Entries</div>
                            </div>
                        </div>
                        <div class="config-section">
                            <div class="text-center">
                                <i class="fas fa-users text-green-500 text-2xl mb-2"></i>
                                <div class="text-lg font-bold"><?php echo number_format($db_stats['total_users'] ?? 0); ?></div>
                                <div class="text-xs text-muted-foreground">Active Users</div>
                            </div>
                        </div>
                        <div class="config-section">
                            <div class="text-center">
                                <i class="fas fa-microscope text-purple-500 text-2xl mb-2"></i>
                                <div class="text-lg font-bold"><?php echo number_format($db_stats['total_instruments'] ?? 0); ?></div>
                                <div class="text-xs text-muted-foreground">Instruments</div>
                            </div>
                        </div>
                        <div class="config-section">
                            <div class="text-center">
                                <i class="fas fa-hdd text-orange-500 text-2xl mb-2"></i>
                                <div class="text-lg font-bold"><?php echo ($db_stats['db_size'] ?? '0') . ' MB'; ?></div>
                                <div class="text-xs text-muted-foreground">Database Size</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="config-section">
                            <div class="config-header">System Information</div>
                            <div class="space-y-3">
                                <div class="config-item">
                                    <span class="text-sm">PHP Version</span>
                                    <span class="text-sm font-medium"><?php echo $system_info['php_version']; ?></span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Server Software</span>
                                    <span class="text-sm font-medium"><?php echo $system_info['server_software']; ?></span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Upload Limit</span>
                                    <span class="text-sm font-medium"><?php echo $system_info['upload_max_filesize']; ?></span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Memory Limit</span>
                                    <span class="text-sm font-medium"><?php echo $system_info['memory_limit']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="config-section">
                            <div class="config-header">System Status</div>
                            <div class="space-y-3">
                                <div class="config-item">
                                    <span class="text-sm flex items-center"><span class="status-indicator status-active"></span>Database Connection</span>
                                    <span class="text-sm text-green-600 font-medium">Connected</span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm flex items-center"><span class="status-indicator status-active"></span>File Upload</span>
                                    <span class="text-sm text-green-600 font-medium">Enabled</span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm flex items-center"><span class="status-indicator status-warning"></span>Email Notifications</span>
                                    <span class="text-sm text-yellow-600 font-medium">Not Configured</span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm flex items-center"><span class="status-indicator status-inactive"></span>Maintenance Mode</span>
                                    <span class="text-sm text-muted-foreground font-medium">Disabled</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Settings Tab -->
                <div id="general" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Application Settings</h3>
                    <div class="config-section space-y-4">
                        <div class="config-header">Basic Configuration</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label text-xs">Application Name</label>
                                <input type="text" value="R&D Logbook System" class="form-input text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Timezone</label>
                                <select class="form-select text-xs">
                                    <option selected>Asia/Jakarta</option>
                                    <option>Asia/Singapore</option>
                                    <option>UTC</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">Date Format</label>
                                <select class="form-select text-xs">
                                    <option selected>Y-m-d (2024-01-15)</option>
                                    <option>d/m/Y (15/01/2024)</option>
                                    <option>m/d/Y (01/15/2024)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">Time Format</label>
                                <select class="form-select text-xs">
                                    <option selected>24 Hour (15:30)</option>
                                    <option>12 Hour (3:30 PM)</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-3 border-t">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2">
                                <span class="text-sm">Enable Maintenance Mode</span>
                            </label>
                            <p class="text-xs text-muted-foreground mt-1">When enabled, only administrators can access the system</p>
                        </div>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Security Configuration</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="config-section">
                            <div class="config-header">Session Management</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">Session Timeout (minutes)</label>
                                    <input type="number" value="30" min="5" max="480" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Max Login Attempts</label>
                                    <input type="number" value="5" min="3" max="10" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Lockout Duration (minutes)</label>
                                    <input type="number" value="15" min="5" max="60" class="form-input text-xs">
                                </div>
                            </div>
                        </div>
                        
                        <div class="config-section">
                            <div class="config-header">Password Policy</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">Minimum Password Length</label>
                                    <input type="number" value="8" min="6" max="20" class="form-input text-xs">
                                </div>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" checked class="mr-2">
                                        <span class="text-xs">Require uppercase letters</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" checked class="mr-2">
                                        <span class="text-xs">Require numbers</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" class="mr-2">
                                        <span class="text-xs">Require special characters</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Management Tab -->
                <div id="users" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">User Management</h3>
                    <div class="config-section text-center">
                        <i class="fas fa-users text-4xl text-muted-foreground mb-4"></i>
                        <p class="text-sm text-muted-foreground mb-4">Manage users, roles, and permissions on a dedicated page.</p>
                        <a href="user_management.php" class="btn btn-primary text-xs">
                            <i class="fas fa-external-link-alt mr-1"></i>Go to User Management
                        </a>
                    </div>
                </div>

                <!-- Authentication Tab -->
                <div id="integrations" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Authentication Settings</h3>
                    <div class="config-section space-y-4">
                        <div class="config-header">Authentication Method</div>
                        <div class="space-y-3">
                            <label class="flex items-center p-3 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <input type="radio" name="auth_method" value="local" checked class="mr-3">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">Local Authentication</div>
                                    <div class="text-xs text-muted-foreground">Use built-in user database</div>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <input type="radio" name="auth_method" value="ldap" class="mr-3">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">LDAP Integration</div>
                                    <div class="text-xs text-muted-foreground">Connect with Active Directory or LDAP server</div>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <input type="radio" name="auth_method" value="oauth" class="mr-3">
                                <div class="flex-1">
                                    <div class="font-medium text-sm">Microsoft 365 OAuth</div>
                                    <div class="text-xs text-muted-foreground">Single sign-on with Office 365</div>
                                </div>
                            </label>
                        </div>
                        
                        <div id="ldap-config" class="hidden border-t pt-4">
                            <div class="config-header">LDAP Configuration</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label text-xs">LDAP Host</label>
                                    <input type="text" placeholder="ldap.company.com" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Port</label>
                                    <input type="number" value="389" class="form-input text-xs">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label text-xs">Base DN</label>
                                    <input type="text" placeholder="dc=company,dc=com" class="form-input text-xs">
                                </div>
                            </div>
                        </div>
                        
                        <div id="oauth-config" class="hidden border-t pt-4">
                            <div class="config-header">Microsoft 365 OAuth Configuration</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">Tenant ID</label>
                                    <input type="text" placeholder="Enter Tenant ID" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Client ID</label>
                                    <input type="text" placeholder="Enter Client ID" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Client Secret</label>
                                    <input type="password" placeholder="Enter Client Secret" class="form-input text-xs">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings Tab -->
                <div id="email" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Email Configuration</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="config-section">
                            <div class="config-header">SMTP Settings</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">SMTP Host</label>
                                    <input type="text" placeholder="smtp.gmail.com" class="form-input text-xs">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label text-xs">Port</label>
                                        <input type="number" value="587" class="form-input text-xs">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Security</label>
                                        <select class="form-select text-xs">
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label text-xs">Username</label>
                                    <input type="email" placeholder="your-email@company.com" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Password</label>
                                    <input type="password" placeholder="App Password" class="form-input text-xs">
                                </div>
                                <div>
                                    <button class="btn btn-secondary text-xs w-full">
                                        <i class="fas fa-paper-plane mr-1"></i>Test Connection
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="config-section">
                            <div class="config-header">Email Templates</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">From Name</label>
                                    <input type="text" value="R&D Logbook System" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">From Email</label>
                                    <input type="email" placeholder="noreply@company.com" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Subject Template</label>
                                    <input type="text" value="Logbook Notification: {{subject}}" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Email Body Template</label>
                                    <textarea rows="4" class="form-textarea text-xs" placeholder="Hello {{user_name}},&#10;&#10;This is a notification..."></textarea>
                                </div>
                                <p class="text-xs text-muted-foreground">Available variables: {{user_name}}, {{entry_code}}, {{instrument}}, {{date}}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Alerts Tab -->
                <div id="alerts" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">System Alert Configuration</h3>
                    <div class="space-y-4">
                        <div class="config-section">
                            <div class="config-header">Maintenance Alerts</div>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 border border-border rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium">Equipment Maintenance Reminders</div>
                                        <div class="text-xs text-muted-foreground">Send alerts when equipment needs maintenance</div>
                                    </div>
                                    <input type="checkbox" checked class="toggle-switch">
                                </label>
                                <label class="flex items-center justify-between p-3 border border-border rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium">Overdue Equipment Alerts</div>
                                        <div class="text-xs text-muted-foreground">Alert when equipment hasn't been used for 30 days</div>
                                    </div>
                                    <input type="checkbox" class="toggle-switch">
                                </label>
                            </div>
                        </div>

                        <div class="config-section">
                            <div class="config-header">User Activity Alerts</div>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 border border-border rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium">Failed Login Attempts</div>
                                        <div class="text-xs text-muted-foreground">Notify administrators of suspicious login activity</div>
                                    </div>
                                    <input type="checkbox" checked class="toggle-switch">
                                </label>
                                <label class="flex items-center justify-between p-3 border border-border rounded-lg">
                                    <div>
                                        <div class="text-sm font-medium">Data Export Activities</div>
                                        <div class="text-xs text-muted-foreground">Log and notify when data is exported</div>
                                    </div>
                                    <input type="checkbox" class="toggle-switch">
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instrument Matrix Tab -->
                <div id="matrix" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Instrument Data Matrix</h3>
                    <div class="config-section">
                        <div class="config-header">Parameter Configuration</div>
                        <p class="text-xs text-muted-foreground mb-4">Configure which parameters are available for each instrument. Check the boxes to enable parameters for specific instruments.</p>
                        <div class="overflow-x-auto">
                            <table class="matrix-table min-w-full border-collapse border border-border">
                                <thead class="bg-muted">
                                    <tr>
                                        <th class="border border-border text-left sticky left-0 bg-muted z-10 p-2">Instrument Name</th>
                                        <?php foreach ($parameter_columns as $param): ?>
                                            <th class="border border-border whitespace-nowrap text-center"><?php echo esc_html(preg_replace('/(?<!^)([A-Z])/', ' $1', $param)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($instruments as $instrument): ?>
                                    <tr class="hover:bg-accent/50">
                                        <td class="border border-border text-left font-medium sticky left-0 bg-card hover:bg-accent/50 z-10 p-2">
                                            <div class="truncate" title="<?php echo esc_html($instrument['name']); ?>"><?php echo esc_html($instrument['name']); ?></div>
                                        </td>
                                        <?php foreach ($parameter_columns as $param): ?>
                                            <td class="border border-border text-center">
                                                <input type="checkbox" class="matrix-checkbox" data-instrument-id="<?php echo (int)$instrument['id']; ?>" data-parameter="<?php echo esc_html($param); ?>" <?php echo $instrument[$param] ? 'checked' : ''; ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Backup & Export Tab -->
                <div id="backup" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Backup & Data Management</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="config-section">
                            <div class="config-header">Database Backup</div>
                            <div class="space-y-4">
                                <div class="config-item">
                                    <span class="text-sm">Last Backup</span>
                                    <span class="text-sm text-muted-foreground">Never</span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Backup Size</span>
                                    <span class="text-sm text-muted-foreground">-</span>
                                </div>
                                <div class="flex gap-2">
                                    <button class="btn btn-primary text-xs flex-1">
                                        <i class="fas fa-download mr-1"></i>Create Backup
                                    </button>
                                    <button class="btn btn-secondary text-xs flex-1">
                                        <i class="fas fa-upload mr-1"></i>Restore
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="config-section">
                            <div class="config-header">Auto Backup Settings</div>
                            <div class="space-y-3">
                                <div>
                                    <label class="form-label text-xs">Backup Schedule</label>
                                    <select class="form-select text-xs">
                                        <option>Disabled</option>
                                        <option>Daily</option>
                                        <option>Weekly</option>
                                        <option>Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label text-xs">Retention Period (days)</label>
                                    <input type="number" value="30" min="1" max="365" class="form-input text-xs">
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" class="mr-2">
                                        <span class="text-xs">Compress backup files</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <div class="config-header">Data Export Options</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="text-center p-4 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <i class="fas fa-file-csv text-green-500 text-2xl mb-2"></i>
                                <div class="text-sm font-medium">Export CSV</div>
                                <div class="text-xs text-muted-foreground">Export all data as CSV</div>
                            </div>
                            <div class="text-center p-4 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <i class="fas fa-file-excel text-green-600 text-2xl mb-2"></i>
                                <div class="text-sm font-medium">Export Excel</div>
                                <div class="text-xs text-muted-foreground">Export with formatting</div>
                            </div>
                            <div class="text-center p-4 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <i class="fas fa-file-archive text-gray-500 text-2xl mb-2"></i>
                                <div class="text-sm font-medium">Full Archive</div>
                                <div class="text-xs text-muted-foreground">Complete system backup</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Tab -->
                <div id="maintenance" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">System Maintenance</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="config-section">
                            <div class="config-header">Cache Management</div>
                            <div class="space-y-3">
                                <div class="config-item">
                                    <span class="text-sm">Application Cache</span>
                                    <button class="btn btn-secondary text-xs">
                                        <i class="fas fa-trash mr-1"></i>Clear
                                    </button>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Session Data</span>
                                    <button class="btn btn-secondary text-xs">
                                        <i class="fas fa-broom mr-1"></i>Clean
                                    </button>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Temporary Files</span>
                                    <button class="btn btn-secondary text-xs">
                                        <i class="fas fa-eraser mr-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="config-section">
                            <div class="config-header">Log Management</div>
                            <div class="space-y-3">
                                <div class="config-item">
                                    <span class="text-sm">Application Logs</span>
                                    <span class="text-xs text-muted-foreground"><?php echo file_exists($log_file) ? number_format(filesize($log_file) / 1024, 1) . ' KB' : '0 KB'; ?></span>
                                </div>
                                <div class="config-item">
                                    <span class="text-sm">Error Logs</span>
                                    <span class="text-xs text-muted-foreground">0 KB</span>
                                </div>
                                <div class="pt-2">
                                    <button class="btn btn-warning text-xs w-full">
                                        <i class="fas fa-archive mr-1"></i>Archive Old Logs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <div class="config-header">Database Optimization</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <button class="btn btn-secondary text-xs p-4 h-auto">
                                <div class="text-center">
                                    <i class="fas fa-database text-blue-500 text-xl mb-2"></i>
                                    <div class="font-medium">Optimize Tables</div>
                                    <div class="text-xs text-muted-foreground mt-1">Improve database performance</div>
                                </div>
                            </button>
                            <button class="btn btn-secondary text-xs p-4 h-auto">
                                <div class="text-center">
                                    <i class="fas fa-chart-line text-green-500 text-xl mb-2"></i>
                                    <div class="font-medium">Analyze Indexes</div>
                                    <div class="text-xs text-muted-foreground mt-1">Check index efficiency</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Audit Trail Tab -->
                <div id="audit" class="tab-content hidden space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">System Audit Trail</h3>
                    <div class="config-section">
                        <div class="flex justify-between items-center mb-4">
                            <div class="config-header !border-none !pb-0 !mb-0">Activity Log</div>
                            <input type="text" id="auditSearch" placeholder="Search logs..." class="form-input text-xs w-64">
                        </div>
                        <div class="overflow-x-auto">
                            <table id="auditTable" class="display table-auto w-full text-xs" style="width:100%">
                                <thead>
                                    <tr>
                                        <th class="p-2 w-40">Timestamp</th>
                                        <th class="p-2 w-32">User</th>
                                        <th class="p-2 text-left">Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($logs, 0, 100) as $log): ?>
                                    <tr>
                                        <td class="p-2 whitespace-nowrap text-muted-foreground"><?php echo esc_html($log['timestamp']); ?></td>
                                        <td class="p-2 whitespace-nowrap"><?php echo esc_html($log['user']); ?></td>
                                        <td class="p-2"><?php echo esc_html($log['message']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="notification-toast" class="hidden fixed top-20 right-6 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm"></div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    let auditTable = null;

    // Tab switching functionality
    tabLinks.forEach(link => {
        link.addEventListener('click', () => {
            const tabId = link.dataset.tab;
            tabLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            tabContents.forEach(content => {
                content.id === tabId ? content.classList.remove('hidden') : content.classList.add('hidden');
            });
            
            // Initialize audit table when needed
            if (tabId === 'audit' && !auditTable) {
                auditTable = $('#auditTable').DataTable({
                    "pageLength": 25,
                    "order": [[0, "desc"]],
                    "dom": 'rt<"mt-4"p>',
                });
                $('#auditSearch').on('keyup', function(){
                    auditTable.search(this.value).draw();
                });
            }
        });
    });

    // Authentication method switching
    const authRadios = document.querySelectorAll('input[name="auth_method"]');
    const ldapConfig = document.getElementById('ldap-config');
    const oauthConfig = document.getElementById('oauth-config');
    
    authRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            ldapConfig.classList.add('hidden');
            oauthConfig.classList.add('hidden');
            
            if (e.target.value === 'ldap') {
                ldapConfig.classList.remove('hidden');
            } else if (e.target.value === 'oauth') {
                oauthConfig.classList.remove('hidden');
            }
        });
    });

    // Matrix checkbox functionality
    const checkboxes = document.querySelectorAll('.matrix-checkbox:not([disabled])');
    const notificationToast = document.getElementById('notification-toast');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
            const formData = new FormData();
            formData.append('instrument_id', this.dataset.instrumentId);
            formData.append('parameter', this.dataset.parameter);
            formData.append('is_checked', this.checked ? 'true' : 'false');
            formData.append('csrf_token', '<?php echo esc_html($_SESSION['csrf_token']); ?>');
            
            try {
                const response = await fetch('update_matrix_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showNotification('Matrix updated successfully!', 'success');
                } else {
                    this.checked = !this.checked;
                    showNotification(result.message || 'Update failed.', 'error');
                }
            } catch (error) {
                this.checked = !this.checked;
                showNotification('Network error occurred.', 'error');
            }
        });
    });

    function showNotification(message, type = 'success') {
        notificationToast.textContent = message;
        notificationToast.className = `fixed top-20 right-6 px-4 py-2 rounded-lg shadow-lg transition-transform transform z-50 text-sm ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        notificationToast.classList.remove('hidden', 'translate-x-full');
        notificationToast.classList.add('translate-x-0');
        
        setTimeout(() => {
            notificationToast.classList.remove('translate-x-0');
            notificationToast.classList.add('translate-x-full');
            setTimeout(() => notificationToast.classList.add('hidden'), 300);
        }, 3000);
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>