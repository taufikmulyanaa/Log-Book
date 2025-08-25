<?php
// public/user_management.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Check if user has admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

// Fetch all users
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(le.id) as total_entries,
           MAX(le.entry_date) as last_activity
    FROM users u 
    LEFT JOIN logbook_entries le ON u.id = le.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">

<div class="space-y-6">
    <div class="bg-card p-6 rounded-xl border border-border shadow-lg">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-foreground">User Management</h1>
                <p class="text-muted-foreground mt-1">Manage system users and permissions</p>
            </div>
            <button onclick="showAddUserModal()" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition-colors">
                <i class="fas fa-user-plus w-4 h-4"></i>
                Add User
            </button>
        </div>
    </div>

    <div class="bg-card p-6 rounded-xl border border-border shadow-lg">
        <div class="overflow-x-auto">
            <table id="usersTable" class="display table-auto w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Username</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Role</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Total Entries</th>
                        <th class="p-3">Last Activity</th>
                        <th class="p-3">Created</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="p-3 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3"><span class="text-white text-xs font-semibold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span></div>
                                <?php echo esc_html($user['name']); ?>
                            </div>
                        </td>
                        <td class="p-3 whitespace-nowrap"><?php echo esc_html($user['username']); ?></td>
                        <td class="p-3 whitespace-nowrap"><?php echo esc_html($user['email'] ?? 'N/A'); ?></td>
                        <td class="p-3 whitespace-nowrap"><span class="px-2 py-1 text-xs rounded-full <?php echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : ($user['role'] === 'user' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>"><?php echo ucfirst(esc_html($user['role'])); ?></span></td>
                        <td class="p-3 whitespace-nowrap"><span class="px-2 py-1 text-xs rounded-full <?php echo $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td class="p-3 text-center"><?php echo (int)$user['total_entries']; ?></td>
                        <td class="p-3 whitespace-nowrap"><?php echo $user['last_activity'] ? date('M j, Y', strtotime($user['last_activity'])) : 'Never'; ?></td>
                        <td class="p-3 whitespace-nowrap"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td class="p-3 whitespace-nowrap">
                            <div class="flex gap-2">
                                <button onclick="editUser(<?php echo $user['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-1" title="Edit"><i class="fas fa-edit text-sm"></i></button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-600 hover:text-red-800 p-1" title="Delete"><i class="fas fa-trash text-sm"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="userModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4">
    <div class="bg-card rounded-xl border border-border shadow-xl p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6"><h3 id="userModalTitle" class="text-lg font-semibold text-foreground">Add New User</h3><button onclick="hideUserModal()" class="text-muted-foreground hover:text-foreground"><i class="fas fa-times w-5 h-5"></i></button></div>
        <form id="userForm">
            <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="user_id" id="user_id_field" value="">
            <input type="hidden" name="action" value="create_update">
            <div class="space-y-4">
                <div><label for="user_name" class="block text-sm font-medium text-muted-foreground mb-1">Full Name</label><input type="text" id="user_name" name="name" required class="w-full bg-muted border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"></div>
                <div><label for="user_username" class="block text-sm font-medium text-muted-foreground mb-1">Username</label><input type="text" id="user_username" name="username" required class="w-full bg-muted border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"></div>
                <div><label for="user_email" class="block text-sm font-medium text-muted-foreground mb-1">Email</label><input type="email" id="user_email" name="email" class="w-full bg-muted border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"></div>
                <div><label for="user_role" class="block text-sm font-medium text-muted-foreground mb-1">Role</label><select id="user_role" name="role" required class="w-full bg-muted border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"><option value="user">User</option><option value="admin">Administrator</option><option value="viewer">Viewer</option></select></div>
                <div id="passwordSection"><label for="user_password" class="block text-sm font-medium text-muted-foreground mb-1">Password</label><input type="password" id="user_password" name="password" class="w-full bg-muted border border-border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"><p class="text-xs text-muted-foreground mt-1">Leave blank when editing to keep current password</p></div>
                <div><label class="flex items-center"><input type="checkbox" id="user_active" name="is_active" checked class="mr-2 rounded border-border text-primary focus:ring-primary"><span class="text-sm text-muted-foreground">Account is active</span></label></div>
            </div>
            <div class="mt-6 flex justify-end space-x-3"><button type="button" onclick="hideUserModal()" class="px-4 py-2 text-sm font-medium border border-border rounded-lg hover:bg-accent transition-colors">Cancel</button><button type="submit" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Save User</button></div>
        </form>
    </div>
</div>

<div id="confirmModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4"><div class="bg-card rounded-xl border border-border shadow-xl p-6 w-full max-w-md"><div class="flex items-center mb-4"><div id="confirmIcon" class="p-2 rounded-full mr-3"><i class="fas fa-question-circle text-lg"></i></div><h3 id="confirmTitle" class="text-lg font-semibold text-foreground">Confirm Action</h3></div><p id="confirmMessage" class="text-muted-foreground mb-6">Are you sure?</p><div class="flex justify-end gap-3"><button type="button" onclick="hideConfirmModal()" class="px-4 py-2 text-sm font-medium border border-border rounded-lg hover:bg-accent transition-colors">Cancel</button><button type="button" id="confirmButton" onclick="executeConfirmedAction()" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors">Confirm</button></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({ "pageLength": 10, "order": [[7, "desc"]], "columnDefs": [{ "orderable": false, "targets": [8] }] });
});

// --- UPDATED JAVASCRIPT ---
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
        });
}

$('#userForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('user_actions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        });
});

let pendingAction = null;
function showConfirmModal(title, message, action, btnClass) {
    $('#confirmTitle').text(title);
    $('#confirmMessage').text(message);
    $('#confirmButton').attr('class', `px-4 py-2 text-sm font-medium rounded-lg transition-colors ${btnClass}`);
    pendingAction = action;
    $('#confirmModal').removeClass('hidden').addClass('flex');
}
function hideConfirmModal() {
    $('#confirmModal').addClass('hidden').removeClass('flex');
}
function executeConfirmedAction() {
    if (pendingAction) pendingAction();
    hideConfirmModal();
}

function deleteUser(userId) {
    const action = () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('user_id', userId);
        formData.append('csrf_token', '<?php echo esc_html($_SESSION['csrf_token']); ?>');
        fetch('user_actions.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                alert(result.message);
                if(result.success) location.reload();
            });
    };
    showConfirmModal('Delete User', 'Are you sure you want to delete this user? This cannot be undone.', action, 'bg-red-600 text-white hover:bg-red-700');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>