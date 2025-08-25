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
    // 1. Ambil semua user untuk mapping ID ke Nama
    $users_map = [];
    try {
        $users_stmt = $pdo->query("SELECT id, name, username FROM users");
        foreach ($users_stmt->fetchAll() as $user) {
            $users_map[$user['id']] = $user['name'];
        }
    } catch (PDOException $e) {
        // Abaikan jika query gagal, parsing tetap berjalan tanpa nama user
    }

    // 2. Baca file log
    if (!file_exists($file_path) || !is_readable($file_path)) {
        return [];
    }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_entries = [];

    // 3. Parsing setiap baris
    foreach ($lines as $line) {
        $entry = ['timestamp' => 'N/A', 'message' => $line, 'user' => 'System']; // Default
        if (preg_match('/^\[(.*?)\]\s-\s(.*)$/', $line, $matches)) {
            $entry['timestamp'] = $matches[1];
            $entry['message'] = $matches[2];
        }

        // Coba ekstrak user dari pesan log
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
$log_file = __DIR__ . '/../logs/app.log';
$logs = parse_log_file($log_file, $pdo);
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
    /* Hide default datatables controls */
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info {
        display: none;
    }
</style>

<div class="bg-card p-6 rounded-xl border border-border shadow-lg space-y-6">
    <div>
        <h1 class="text-xl font-bold text-foreground">Settings</h1>
        <p class="text-sm text-muted-foreground mt-1">Manage your application settings and configurations.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="col-span-1">
            <div class="flex flex-col space-y-2">
                <button data-tab="general" class="tab-link active w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-sliders-h w-4 mr-2"></i> General</button>
                <button data-tab="users" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-users-cog w-4 mr-2"></i> User Management</button>
                <button data-tab="integrations" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-puzzle-piece w-4 mr-2"></i> Integrations</button>
                <button data-tab="email" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-envelope w-4 mr-2"></i> Email Notifications</button>
                <button data-tab="backup" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-database w-4 mr-2"></i> Backup</button>
                <button data-tab="audit" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-history w-4 mr-2"></i> Audit Trail</button>
                <button data-tab="matrix" class="tab-link w-full text-left px-4 py-2 text-sm border-l-4 border-transparent hover:bg-muted/50"><i class="fas fa-microscope w-4 mr-2"></i> Instrument Matrix</button>
            </div>
        </div>

        <div class="col-span-1 md:col-span-3">
            <div id="general" class="tab-content space-y-4">
                <h3 class="text-lg font-semibold text-foreground">General Settings</h3>
                <div class="bg-muted/50 p-4 rounded-lg border border-border space-y-3">
                    <div><label class="block text-xs font-medium text-muted-foreground">Application Name</label><input type="text" value="R&D Logbook System" class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-sm" disabled></div>
                    <div><label class="block text-xs font-medium text-muted-foreground">Timezone</label><input type="text" value="Asia/Jakarta" class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-sm" disabled></div>
                    <div><label class="flex items-center"><input type="checkbox" class="mr-2" disabled><span class="text-sm text-muted-foreground">Enable Maintenance Mode</span></label></div>
                </div>
            </div>

            <div id="users" class="tab-content hidden space-y-4">
                <h3 class="text-lg font-semibold text-foreground">User Management</h3>
                <div class="bg-muted/50 p-4 rounded-lg border border-border text-center">
                    <p class="text-sm text-muted-foreground mb-4">Manage users, roles, and permissions on a dedicated page.</p>
                    <a href="user_management.php" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Go to User Management</a>
                </div>
            </div>

            <div id="integrations" class="tab-content hidden space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Authentication Integrations</h3>
                <div class="bg-muted/50 p-4 rounded-lg border border-border space-y-3">
                    <p class="text-sm text-muted-foreground">Allow users to log in using external authentication providers.</p>
                    <div class="flex space-x-4">
                        <label class="flex items-center"><input type="radio" name="auth_method" value="ldap" class="mr-2" checked> LDAP</label>
                        <label class="flex items-center"><input type="radio" name="auth_method" value="oauth" class="mr-2"> Microsoft 365 OAuth2</label>
                    </div>
                    <div id="ldap-settings" class="pt-4 border-t space-y-3">
                        <h4 class="font-medium text-foreground">LDAP Configuration</h4>
                        <div><label class="block text-xs font-medium text-muted-foreground">LDAP Host</label><input type="text" placeholder="e.g., ldap.company.com" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-muted-foreground">Base DN</label><input type="text" placeholder="e.g., dc=company,dc=com" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                    </div>
                    <div id="oauth-settings" class="hidden pt-4 border-t space-y-3">
                        <h4 class="font-medium text-foreground">Microsoft 365 OAuth2 Configuration</h4>
                        <div><label class="block text-xs font-medium text-muted-foreground">Tenant ID</label><input type="text" placeholder="Enter Tenant ID" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-muted-foreground">Client ID</label><input type="text" placeholder="Enter Client ID" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-muted-foreground">Client Secret</label><input type="password" placeholder="Enter Client Secret" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                    </div>
                </div>
            </div>

            <div id="email" class="tab-content hidden space-y-4">
                 <h3 class="text-lg font-semibold text-foreground">Email Notifications</h3>
                 <div class="bg-muted/50 p-4 rounded-lg border border-border space-y-3">
                    <h4 class="font-medium text-foreground">SMTP Settings</h4>
                    <p class="text-xs text-muted-foreground">Configure SMTP settings for sending system emails.</p>
                    <div><label class="block text-xs font-medium text-muted-foreground">SMTP Host</label><input type="text" placeholder="e.g., smtp.gmail.com" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                 </div>
                 <div class="bg-muted/50 p-4 rounded-lg border border-border space-y-3">
                    <h4 class="font-medium text-foreground">Email Templates</h4>
                    <div><label class="block text-xs font-medium text-muted-foreground">Email Subject</label><input type="text" value="Logbook Notification: {{subject}}" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm"></div>
                    <div><label class="block text-xs font-medium text-muted-foreground">Email Body</label><textarea rows="6" class="mt-1 w-full bg-card border border-border rounded-md px-3 py-1.5 text-sm">Hello {{user_name}},\n\nThis is a notification regarding the logbook entry {{entry_code}}.\n\nDetails:\n{{details}}\n\nThank you,\nLogbook System</textarea></div>
                    <p class="text-xs text-muted-foreground">Use placeholders like {{user_name}}, {{entry_code}}, etc.</p>
                 </div>
            </div>
            
            <div id="backup" class="tab-content hidden space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Backup</h3>
                <div class="bg-muted/50 p-4 rounded-lg border border-border">
                    <p class="text-sm text-muted-foreground mb-4">Create backups of your application files and database.</p>
                    <div class="flex items-center justify-between"><p class="text-sm">Last backup: <span class="font-medium">Never</span></p><button class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"><i class="fas fa-download mr-2"></i> Backup Now</button></div>
                </div>
            </div>

            <div id="audit" class="tab-content hidden space-y-4">
                <div class="bg-card p-4 rounded-xl border border-border">
                    <div class="flex justify-between items-center border-b border-border pb-3">
                        <div>
                            <h2 class="text-lg font-bold text-foreground">Audit Trail</h2>
                            <p class="text-xs text-muted-foreground mt-1">A chronological record of system activities.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card p-4 rounded-xl border border-border">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label for="auditSearch" class="block text-xs font-medium text-muted-foreground">Search</label>
                            <input type="text" id="auditSearch" placeholder="Search logs..." class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-xs">
                        </div>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <div id="auditTableInfo" class="text-xs text-muted-foreground"></div>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="auditTable" class="display table-auto w-full text-xs" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="p-2 w-40">Timestamp</th>
                                    <th class="p-2 w-32">Who</th>
                                    <th class="p-2 text-left">Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
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

            <div id="matrix" class="tab-content hidden">
                <h3 class="text-lg font-semibold text-foreground mb-4">Instrument Data Matrix</h3>
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

    tabLinks.forEach(link => {
        link.addEventListener('click', () => {
            const tabId = link.dataset.tab;
            tabLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            tabContents.forEach(content => {
                content.id === tabId ? content.classList.remove('hidden') : content.classList.add('hidden');
            });
            
            if (tabId === 'audit' && !auditTable) {
                auditTable = $('#auditTable').DataTable({
                    "pageLength": 25,
                    "order": [[0, "desc"]],
                    "dom": 'rt<"mt-4"p>',
                    "drawCallback": function(settings) {
                        var api = this.api();
                        var info = api.page.info();
                        $('#auditTableInfo').html(`Showing ${info.start + 1} to ${info.end} of ${info.recordsTotal} entries`);
                    }
                });
                $('#auditSearch').on('keyup', function(){
                    auditTable.search(this.value).draw();
                });
            }
        });
    });

    const authRadios = document.querySelectorAll('input[name="auth_method"]');
    const ldapSettings = document.getElementById('ldap-settings');
    const oauthSettings = document.getElementById('oauth-settings');
    authRadios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.value === 'ldap') {
                ldapSettings.style.display = 'block';
                oauthSettings.style.display = 'none';
            } else {
                ldapSettings.style.display = 'none';
                oauthSettings.style.display = 'block';
            }
        });
    });

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
                    showNotification('Matrix updated!', 'success');
                } else {
                    this.checked = !this.checked;
                    showNotification(result.message || 'Failed.', 'error');
                }
            } catch (error) {
                this.checked = !this.checked;
                showNotification('Network error.', 'error');
            }
        });
    });

    function showNotification(message, type = 'success') {
        notificationToast.textContent = message;
        notificationToast.className = `fixed top-20 right-6 px-4 py-2 rounded-lg shadow-lg transition-transform transform z-50 text-sm ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
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