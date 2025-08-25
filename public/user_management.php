<?php
// public/user_management.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Check if user has permission to access user management
if (function_exists('hasPermission')) {
    if (!hasPermission('user_management')) {
        http_response_code(403);
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong>Access Denied:</strong> Administrator privileges required to access this page.
              </div>';
        echo '<script>setTimeout(() => { window.location.href = "index.php"; }, 3000);</script>';
        require_once __DIR__ . '/../templates/footer.php';
        exit();
    }
} else {
    // Fallback check if permissions not loaded
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'administrator') {
        http_response_code(403);
        die('Access denied. Administrator privileges required.');
    }
}

// Fetch all users with statistics
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(le.id) as total_entries,
           MAX(le.entry_date) as last_activity,
           COUNT(CASE WHEN le.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as entries_last_30_days
    FROM users u 
    LEFT JOIN logbook_entries le ON u.id = le.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<style>
    /* DataTables styling to match theme */
    .dataTables_wrapper { font-family: 'Inter', sans-serif; }
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_length { display: none !important; }
    
    /* Pagination styling */
    .dataTables_wrapper .dataTables_paginate { 
        margin-top: 1.5rem !important; 
        float: none !important; 
        text-align: center !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button { 
        border-radius: 0.375rem !important; 
        padding: 0.5rem 0.75rem !important; 
        margin: 0 0.125rem !important; 
        border: 1px solid hsl(214.3, 31.8%, 91.4%) !important; 
        color: hsl(222.2, 84%, 4.9%) !important; 
        font-size: 0.75rem !important; 
        background: white !important;
        transition: all 0.2s ease !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover { 
        background: hsl(210, 40%, 96.1%) !important; 
        border-color: hsl(221.2, 83.2%, 53.3%) !important; 
        color: hsl(221.2, 83.2%, 53.3%) !important; 
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { 
        background: hsl(221.2, 83.2%, 53.3%) !important; 
        border-color: hsl(221.2, 83.2%, 53.3%) !important; 
        color: white !important; 
        font-weight: 600 !important; 
    }
    
    /* Table styling */
    #usersTable thead th { 
        background-color: hsl(210, 40%, 96.1%) !important; 
        color: hsl(215.4, 16.3%, 46.9%) !important; 
        font-weight: 600 !important; 
        font-size: 0.75rem !important; 
        text-transform: uppercase;
        letter-spacing: 0.025em;
        border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important;
    }
    #usersTable tbody tr { 
        border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important; 
        font-size: 0.75rem !important; 
        transition: all 0.2s ease !important;
    }
    #usersTable tbody tr:hover { 
        background-color: hsl(210, 40%, 96.1%) !important; 
    }
    
    /* Action button styling */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
    }
    .action-btn-edit {
        background-color: #f0fdf4;
        color: #16a34a;
        border-color: #dcfce7;
    }
    .action-btn-edit:hover {
        background-color: #dcfce7;
        color: #15803d;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(22, 163, 74, 0.2);
        text-decoration: none;
    }
    .action-btn-delete {
        background-color: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }
    .action-btn-delete:hover {
        background-color: #fecaca;
        color: #b91c1c;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);
    }
    .action-btn-disabled {
        background-color: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
        opacity: 0.5;
    }
    
    /* Modal styling */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        align-items: center;
        justify-content: center;
        z-index: 50;
        padding: 1rem;
        backdrop-filter: blur(4px);
    }
    .modal-content {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid hsl(214.3, 31.8%, 91.4%);
        width: 100%;
        max-width: 28rem;
        max-height: 90vh;
        overflow-y: auto;
        animation: modalSlideIn 0.3s ease-out;
    }
    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(-1rem) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%);
        background: hsl(210, 40%, 98%);
        border-radius: 0.75rem 0.75rem 0 0;
    }
    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: hsl(222.2, 84%, 4.9%);
    }
    .modal-close {
        color: hsl(215.4, 16.3%, 46.9%);
        transition: color 0.15s ease;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 0.375rem;
    }
    .modal-close:hover {
        color: hsl(222.2, 84%, 4.9%);
        background: rgba(0,0,0,0.1);
    }
    .modal-body {
        padding: 1.5rem;
    }
</style>

<div class="card">
    <div class="card-header text-base flex justify-between items-center">
        <span>User Management</span>
        <button onclick="showAddUserModal()" class="btn btn-primary text-xs !py-1 !px-2">
            <i class="fas fa-user-plus mr-1"></i>Add User
        </button>
    </div>
    
    <div class="p-6 space-y-6">
        <!-- Quick Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-blue-800"><?php echo count($users); ?></div>
                <div class="text-xs text-blue-600">Total Users</div>
            </div>
            <div class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-green-800"><?php echo count(array_filter($users, fn($u) => $u['is_active'])); ?></div>
                <div class="text-xs text-green-600">Active Users</div>
            </div>
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-purple-800"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'administrator')); ?></div>
                <div class="text-xs text-purple-600">Administrators</div>
            </div>
            <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-orange-800"><?php echo count(array_filter($users, fn($u) => $u['entries_last_30_days'] > 0)); ?></div>
                <div class="text-xs text-orange-600">Active This Month</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-6">
            <div>
                <label for="userSearch" class="form-label text-xs">Search Users</label>
                <input type="text" id="userSearch" placeholder="Search by name or username..." class="form-input text-xs">
            </div>
            <div>
                <label for="roleFilter" class="form-label text-xs">Filter by Role</label>
                <select id="roleFilter" class="form-select text-xs">
                    <option value="">All Roles</option>
                    <option value="administrator">Administrator</option>
                    <option value="contributor">Contributor</option>
                    <option value="viewer">Viewer</option>
                </select>
            </div>
            <div>
                <label for="statusFilter" class="form-label text-xs">Filter by Status</label>
                <select id="statusFilter" class="form-select text-xs">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <table id="usersTable" class="table w-full text-xs">
                <thead>
                    <tr>
                        <th class="p-2 text-left">User</th>
                        <th class="p-2 text-left">Contact</th>
                        <th class="p-2 text-left">Role</th>
                        <th class="p-2 text-left">Status</th>
                        <th class="p-2 text-right">Entries</th>
                        <th class="p-2 text-left">Last Activity</th>
                        <th class="p-2 text-left">Created</th>
                        <th class="p-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-muted-foreground">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-users text-2xl opacity-50"></i>
                                    <p>No users found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-accent/50">
                            <td class="p-2 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-xs font-semibold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate"><?php echo esc_html($user['name']); ?></div>
                                        <div class="text-muted-foreground text-[10px] truncate">@<?php echo esc_html($user['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <div class="text-xs">
                                    <?php if ($user['email']): ?>
                                        <div class="text-foreground"><?php echo esc_html($user['email']); ?></div>
                                    <?php else: ?>
                                        <div class="text-muted-foreground italic">No email</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php if (function_exists('getRoleBadge')): ?>
                                    <?php echo getRoleBadge($user['role']); ?>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 text-[10px] rounded-full font-medium <?php 
                                        echo $user['role'] === 'administrator' ? 'bg-red-100 text-red-800' : 
                                            ($user['role'] === 'contributor' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); 
                                    ?>">
                                        <?php echo ucfirst(esc_html($user['role'])); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-medium <?php 
                                    echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; 
                                ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="p-2 text-right">
                                <div class="font-medium"><?php echo number_format($user['total_entries']); ?></div>
                                <?php if ($user['entries_last_30_days'] > 0): ?>
                                    <div class="text-[10px] text-green-600">+<?php echo $user['entries_last_30_days']; ?> this month</div>
                                <?php else: ?>
                                    <div class="text-[10px] text-muted-foreground">No recent activity</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php if ($user['last_activity']): ?>
                                    <div class="text-xs"><?php echo date('M j, Y', strtotime($user['last_activity'])); ?></div>
                                    <div class="text-[10px] text-muted-foreground"><?php echo date('H:i', strtotime($user['last_activity'])); ?></div>
                                <?php else: ?>
                                    <div class="text-xs text-muted-foreground italic">Never</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <div class="text-xs"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                <div class="text-[10px] text-muted-foreground"><?php echo date('H:i', strtotime($user['created_at'])); ?></div>
                            </td>
                            <td class="p-2 text-center whitespace-nowrap">
                                <div class="flex justify-center gap-1">
                                    <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                            class="action-btn action-btn-edit" 
                                            title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo esc_html($user['name']); ?>')" 
                                            class="action-btn action-btn-delete" 
                                            title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="action-btn action-btn-disabled" 
                                            title="Cannot delete yourself" 
                                            disabled>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" class="modal-overlay hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="userModalTitle" class="modal-title">Add New User</h3>
            <button onclick="hideUserModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="userForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="user_id" id="user_id_field" value="">
                <input type="hidden" name="action" value="create_update">
                
                <div>
                    <label for="user_name" class="form-label text-xs">Full Name *</label>
                    <input type="text" id="user_name" name="name" required 
                           class="form-input text-xs" placeholder="Enter full name">
                </div>
                
                <div>
                    <label for="user_username" class="form-label text-xs">Username *</label>
                    <input type="text" id="user_username" name="username" required 
                           class="form-input text-xs" placeholder="Enter username">
                </div>
                
                <div>
                    <label for="user_email" class="form-label text-xs">Email Address</label>
                    <input type="email" id="user_email" name="email" 
                           class="form-input text-xs" placeholder="Enter email address">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="user_role" class="form-label text-xs">Role *</label>
                        <select id="user_role" name="role" required class="form-select text-xs">
                            <?php 
                            if (function_exists('getAvailableRoles')) {
                                $availableRoles = getAvailableRoles();
                            } else {
                                // Fallback roles
                                $availableRoles = [
                                    'administrator' => 'Administrator',
                                    'contributor' => 'Contributor',
                                    'viewer' => 'Viewer'
                                ];
                                // Remove admin option if not admin
                                if ($_SESSION['user_role'] !== 'administrator') {
                                    unset($availableRoles['administrator']);
                                }
                            }
                            ?>
                            <?php foreach ($availableRoles as $roleKey => $roleName): ?>
                                <option value="<?php echo $roleKey; ?>"><?php echo $roleName; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-muted-foreground mt-1">
                            Only administrators can create other administrators
                        </p>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center">
                            <input type="checkbox" id="user_active" name="is_active" checked 
                                   class="mr-2 rounded border-border">
                            <span class="text-xs">Account is active</span>
                        </label>
                    </div>
                </div>
                
                <div id="passwordSection">
                    <label for="user_password" class="form-label text-xs">Password *</label>
                    <input type="password" id="user_password" name="password" 
                           class="form-input text-xs" placeholder="Enter password">
                    <p class="text-[10px] text-muted-foreground mt-1">
                        Leave blank when editing to keep current password
                    </p>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="hideUserModal()" 
                            class="btn btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success text-xs">
                        <i class="fas fa-save mr-1"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmModal" class="modal-overlay hidden">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="modal-title text-red-600">Confirm Delete</h3>
            <button onclick="hideConfirmModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-medium text-foreground">Delete User Account</h4>
                    <p class="text-xs text-muted-foreground mt-1">This action cannot be undone.</p>
                </div>
            </div>
            <p id="confirmMessage" class="text-sm text-muted-foreground mb-6">
                Are you sure you want to delete this user account? All associated data will be preserved but the user will no longer be able to access the system.
            </p>
            <div class="flex justify-end gap-3">
                <button onclick="hideConfirmModal()" class="btn btn-secondary text-xs">
                    Cancel
                </button>
                <button id="confirmButton" onclick="executeConfirmedAction()" 
                        class="btn btn-danger text-xs">
                    <i class="fas fa-trash mr-1"></i>Delete User
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    let table = $('#usersTable').DataTable({ 
        "pageLength": 15, 
        "order": [[6, "desc"]], // Created column
        "columnDefs": [
            { "orderable": false, "targets": [7] }, // Actions column
            { "searchable": false, "targets": [7] }
        ],
        "language": {
            "info": "Showing _START_ to _END_ of _TOTAL_ users",
            "zeroRecords": "No matching users found",
            "paginate": {
                "next": '<i class="fas fa-chevron-right"></i>',
                "previous": '<i class="fas fa-chevron-left"></i>',
                "first": '<i class="fas fa-angle-double-left"></i>',
                "last": '<i class="fas fa-angle-double-right"></i>'
            }
        },
        "dom": 'rt<"mt-4"p>',
        "drawCallback": function() {
            let info = this.api().page.info();
            // Optional: Update info display if needed
        }
    });

    // Search functionality
    $('#userSearch').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Role filter
    $('#roleFilter').on('change', function() {
        table.column(2).search(this.value).draw();
    });

    // Status filter  
    $('#statusFilter').on('change', function() {
        let searchTerm = this.value === 'active' ? 'Active' : 
                        this.value === 'inactive' ? 'Inactive' : '';
        table.column(3).search(searchTerm).draw();
    });
});

// Modal Functions
function showAddUserModal() {
    $('#userModalTitle').text('Add New User');
    $('#userForm')[0].reset();
    $('#user_id_field').val('');
    $('#user_password').prop('required', true);
    $('#userModal').removeClass('hidden').addClass('flex');
}

function hideUserModal() {
    $('#userModal').addClass('hidden').removeClass('flex');
}

function editUser(userId) {
    fetch(`get_user_data.php?id=${userId}`)
        .then(response => response.json())
        .then(user => {
            $('#userModalTitle').text('Edit User');
            $('#user_id_field').val(user.id);
            $('#user_name').val(user.name);
            $('#user_username').val(user.username);
            $('#user_email').val(user.email || '');
            $('#user_role').val(user.role);
            $('#user_active').prop('checked', user.is_active == 1);
            $('#user_password').val('').prop('required', false);
            $('#userModal').removeClass('hidden').addClass('flex');
        })
        .catch(error => {
            console.error('Error fetching user data:', error);
            showNotification('Error loading user data', 'error');
        });
}

$('#userForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
    submitBtn.disabled = true;
    
    fetch('user_actions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message, 'success');
                hideUserModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error: ' + result.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Network error occurred', 'error');
        })
        .finally(() => {
            // Reset button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
});

// Confirmation Modal Functions
let pendingAction = null;

function showConfirmModal(title, message, action) {
    document.getElementById('confirmMessage').textContent = message;
    pendingAction = action;
    document.getElementById('confirmModal').classList.remove('hidden');
    document.getElementById('confirmModal').classList.add('flex');
}

function hideConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    document.getElementById('confirmModal').classList.remove('flex');
    pendingAction = null;
}

function executeConfirmedAction() {
    if (pendingAction) {
        pendingAction();
    }
    hideConfirmModal();
}

function deleteUser(userId, userName) {
    const action = () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', userId);
        formData.append('csrf_token', '<?php echo esc_html($_SESSION['csrf_token']); ?>');
        
        fetch('user_actions.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('Error: ' + result.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Network error occurred', 'error');
            });
    };
    
    showConfirmModal(
        'Delete User Account',
        `Are you sure you want to delete "${userName}"? This action cannot be undone and the user will lose access to the system.`,
        action
    );
}

// Notification function
function showNotification(message, type = 'success') {
    const iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
    const bgClass = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    
    // Remove existing notification
    const existingNotification = document.getElementById('notification-toast');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.id = 'notification-toast';
    notification.className = `fixed top-20 right-6 px-4 py-3 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm ${bgClass} text-white`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas fa-${iconClass} w-4 h-4"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
        notification.classList.add('translate-x-0');
    }, 100);
    
    // Animate out and remove
    setTimeout(() => {
        notification.classList.remove('translate-x-0');
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideUserModal();
        hideConfirmModal();
    }
});

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const userModal = document.getElementById('userModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (e.target === userModal && userModal.classList.contains('flex')) {
        hideUserModal();
    }
    if (e.target === confirmModal && confirmModal.classList.contains('flex')) {
        hideConfirmModal();
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>