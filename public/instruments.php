<?php
// public/instruments.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Check permissions
if (function_exists('hasPermission') && !hasPermission('settings_view')) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong>Access Denied:</strong> You do not have permission to manage instruments.
          </div>';
    require_once __DIR__ . '/../templates/footer.php';
    exit();
}

// Fetch instruments with usage statistics
$stmt = $pdo->query("
    SELECT i.*, 
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
");
$instruments = $stmt->fetchAll();

// Parameter columns for matrix display
$parameter_columns = [
    'MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 
    'Diluent', 'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'
];
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<style>
    .status-active { background-color: #10b981; }
    .status-maintenance { background-color: #f59e0b; }
    .status-inactive { background-color: #ef4444; }
    .matrix-checkbox { 
        appearance: none; 
        background-color: #f1f5f9; 
        border: 1px solid #cbd5e1; 
        border-radius: 0.25rem; 
        width: 1rem; 
        height: 1rem; 
        cursor: pointer; 
        position: relative; 
        transition: all 0.2s; 
    }
    .matrix-checkbox:checked { 
        background-color: hsl(221.2, 83.2%, 53.3%); 
        border-color: hsl(221.2, 83.2%, 53.3%); 
    }
    .matrix-checkbox:checked::after { 
        content: '✓'; 
        color: white; 
        position: absolute; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%); 
        font-size: 0.6rem; 
        font-weight: bold; 
    }
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter, 
    .dataTables_wrapper .dataTables_info { 
        display: none; 
    }
</style>

<div class="card">
    <div class="card-header text-base flex justify-between items-center">
        <span>Instrument Management</span>
        <button onclick="showAddInstrumentModal()" class="btn btn-primary text-xs !py-1 !px-2">
            <i class="fas fa-plus mr-1"></i>Add Instrument
        </button>
    </div>
    
    <div class="p-6 space-y-6">
        <!-- Quick Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-blue-800"><?php echo count($instruments); ?></div>
                <div class="text-xs text-blue-600">Total Instruments</div>
            </div>
            <div class="bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-green-800">
                    <?php echo count(array_filter($instruments, fn($i) => $i['status'] === 'active')); ?>
                </div>
                <div class="text-xs text-green-600">Active</div>
            </div>
            <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-yellow-800">
                    <?php echo count(array_filter($instruments, fn($i) => $i['status'] === 'maintenance')); ?>
                </div>
                <div class="text-xs text-yellow-600">Maintenance</div>
            </div>
            <div class="bg-gradient-to-r from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-4 text-center">
                <div class="text-lg font-bold text-orange-800">
                    <?php echo count(array_filter($instruments, fn($i) => $i['usage_last_30_days'] > 0)); ?>
                </div>
                <div class="text-xs text-orange-600">Used This Month</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-6">
            <div>
                <label for="instrumentSearch" class="form-label text-xs">Search Instruments</label>
                <input type="text" id="instrumentSearch" placeholder="Search by name or code..." class="form-input text-xs">
            </div>
            <div>
                <label for="statusFilter" class="form-label text-xs">Filter by Status</label>
                <select id="statusFilter" class="form-select text-xs">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label for="usageFilter" class="form-label text-xs">Filter by Usage</label>
                <select id="usageFilter" class="form-select text-xs">
                    <option value="">All Usage</option>
                    <option value="recent">Used This Month</option>
                    <option value="unused">Not Used This Month</option>
                </select>
            </div>
        </div>

        <!-- Instruments Table -->
        <div class="table-container">
            <table id="instrumentsTable" class="table w-full text-xs">
                <thead>
                    <tr>
                        <th class="p-2 text-left">Instrument</th>
                        <th class="p-2 text-left">Code</th>
                        <th class="p-2 text-left">Status</th>
                        <th class="p-2 text-left">Location</th>
                        <th class="p-2 text-right">Total Usage</th>
                        <th class="p-2 text-left">Last Used</th>
                        <th class="p-2 text-left">Health Status</th>
                        <th class="p-2 text-center">Parameters</th>
                        <th class="p-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instruments)): ?>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-muted-foreground">
                                <div class="flex flex-col items-center gap-2">
                                    <i class="fas fa-microscope text-2xl opacity-50"></i>
                                    <p>No instruments found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instruments as $instrument): ?>
                        <tr class="hover:bg-accent/50">
                            <td class="p-2">
                                <div class="font-medium"><?php echo esc_html($instrument['name']); ?></div>
                                <?php if ($instrument['description']): ?>
                                    <div class="text-[10px] text-muted-foreground truncate max-w-xs" 
                                         title="<?php echo esc_html($instrument['description']); ?>">
                                        <?php echo esc_html($instrument['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap font-mono text-blue-600">
                                <?php echo esc_html($instrument['code']); ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <span class="w-2 h-2 rounded-full inline-block mr-2 status-<?php echo $instrument['status']; ?>"></span>
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-medium <?php 
                                    echo $instrument['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                        ($instrument['status'] === 'maintenance' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                ?>">
                                    <?php echo ucfirst(esc_html($instrument['status'])); ?>
                                </span>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php echo $instrument['location'] ? esc_html($instrument['location']) : 
                                    '<span class="text-muted-foreground italic">Not specified</span>'; ?>
                            </td>
                            <td class="p-2 text-right">
                                <div class="font-medium"><?php echo number_format($instrument['total_usage']); ?></div>
                                <?php if ($instrument['usage_last_30_days'] > 0): ?>
                                    <div class="text-[10px] text-green-600">+<?php echo $instrument['usage_last_30_days']; ?> this month</div>
                                <?php else: ?>
                                    <div class="text-[10px] text-muted-foreground">No recent usage</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php if ($instrument['last_used']): ?>
                                    <div class="text-xs"><?php echo date('M j, Y', strtotime($instrument['last_used'])); ?></div>
                                    <div class="text-[10px] text-muted-foreground"><?php echo date('H:i', strtotime($instrument['last_used'])); ?></div>
                                <?php else: ?>
                                    <div class="text-xs text-muted-foreground italic">Never used</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 whitespace-nowrap">
                                <?php if ($instrument['broken_count'] > 0): ?>
                                    <span class="px-2 py-0.5 text-[10px] rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i><?php echo $instrument['broken_count']; ?> issues
                                    </span>
                                <?php elseif ($instrument['maintenance_needed_count'] > 0): ?>
                                    <span class="px-2 py-0.5 text-[10px] rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-wrench mr-1"></i>Needs maintenance
                                    </span>
                                <?php elseif ($instrument['good_condition_count'] > 0): ?>
                                    <span class="px-2 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Good
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-muted-foreground">No data</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 text-center">
                                <?php 
                                $activeParams = 0;
                                foreach ($parameter_columns as $param) {
                                    if ($instrument[$param]) $activeParams++;
                                }
                                ?>
                                <div class="text-sm font-medium"><?php echo $activeParams; ?></div>
                                <div class="text-[10px] text-muted-foreground">of <?php echo count($parameter_columns); ?></div>
                            </td>
                            <td class="p-2 text-center whitespace-nowrap">
                                <div class="flex justify-center gap-1">
                                    <button onclick="viewInstrument(<?php echo $instrument['id']; ?>)" 
                                            class="action-btn action-btn-view" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editInstrument(<?php echo $instrument['id']; ?>)" 
                                            class="action-btn action-btn-edit" 
                                            title="Edit Instrument">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteInstrument(<?php echo $instrument['id']; ?>, '<?php echo esc_html($instrument['name']); ?>')" 
                                            class="action-btn action-btn-delete" 
                                            title="Delete Instrument">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<!-- Add/Edit Instrument Modal -->
<div id="instrumentModal" class="modal-overlay hidden">
    <div class="modal-content max-w-4xl">
        <div class="modal-header">
            <h3 id="instrumentModalTitle" class="modal-title">Add New Instrument</h3>
            <button onclick="hideInstrumentModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="instrumentForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="instrument_id" id="instrument_id_field" value="">
                <input type="hidden" name="action" value="create_update">
                
                <!-- Basic Information -->
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold border-b pb-2">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="instrument_name" class="form-label text-xs">Instrument Name *</label>
                            <input type="text" id="instrument_name" name="name" required 
                                   class="form-input text-xs" placeholder="Enter instrument name">
                        </div>
                        <div>
                            <label for="instrument_code" class="form-label text-xs">Instrument Code *</label>
                            <input type="text" id="instrument_code" name="code" required 
                                   class="form-input text-xs font-mono" placeholder="e.g., HPLC-001">
                        </div>
                        <div class="md:col-span-2">
                            <label for="instrument_description" class="form-label text-xs">Description</label>
                            <textarea id="instrument_description" name="description" rows="3" 
                                      class="form-textarea text-xs" placeholder="Enter instrument description"></textarea>
                        </div>
                        <div>
                            <label for="instrument_location" class="form-label text-xs">Location</label>
                            <input type="text" id="instrument_location" name="location" 
                                   class="form-input text-xs" placeholder="e.g., Lab Room 101">
                        </div>
                        <div>
                            <label for="instrument_status" class="form-label text-xs">Status *</label>
                            <select id="instrument_status" name="status" required class="form-select text-xs">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Parameter Matrix -->
                <div class="space-y-4">
                    <h4 class="text-sm font-semibold border-b pb-2">Parameter Configuration</h4>
                    <p class="text-xs text-muted-foreground">Select which parameters are available for this instrument:</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <?php foreach ($parameter_columns as $param): ?>
                        <div>
                            <label class="flex items-center p-2 border border-border rounded-lg hover:bg-accent/50 cursor-pointer">
                                <input type="checkbox" name="parameters[]" value="<?php echo $param; ?>" 
                                       id="param_<?php echo $param; ?>" class="matrix-checkbox mr-2">
                                <span class="text-xs"><?php echo preg_replace('/(?<!^)([A-Z])/', ' $1', $param); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="hideInstrumentModal()" 
                            class="btn btn-secondary text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success text-xs">
                        <i class="fas fa-save mr-1"></i>Save Instrument
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Instrument Modal -->
<div id="viewInstrumentModal" class="modal-overlay hidden">
    <div class="modal-content max-w-3xl">
        <div class="modal-header">
            <h3 class="modal-title">Instrument Details</h3>
            <button onclick="hideViewInstrumentModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body" id="instrumentDetails">
            <!-- Content will be loaded dynamically -->
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
                    <h4 class="text-sm font-medium text-foreground">Delete Instrument</h4>
                    <p class="text-xs text-muted-foreground mt-1">This action cannot be undone.</p>
                </div>
            </div>
            <p id="confirmMessage" class="text-sm text-muted-foreground mb-6">
                Are you sure you want to delete this instrument? All associated logbook entries will be affected.
            </p>
            <div class="flex justify-end gap-3">
                <button onclick="hideConfirmModal()" class="btn btn-secondary text-xs">
                    Cancel
                </button>
                <button id="confirmButton" onclick="executeConfirmedAction()" 
                        class="btn btn-danger text-xs">
                    <i class="fas fa-trash mr-1"></i>Delete Instrument
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
    let table = $('#instrumentsTable').DataTable({ 
        "pageLength": 15, 
        "order": [[0, "asc"]], // Name column
        "columnDefs": [
            { "orderable": false, "targets": [7, 8] }, // Parameters and Actions columns
            { "searchable": false, "targets": [7, 8] }
        ],
        "language": {
            "info": "Showing _START_ to _END_ of _TOTAL_ instruments",
            "zeroRecords": "No matching instruments found",
            "paginate": {
                "next": '<i class="fas fa-chevron-right"></i>',
                "previous": '<i class="fas fa-chevron-left"></i>',
                "first": '<i class="fas fa-angle-double-left"></i>',
                "last": '<i class="fas fa-angle-double-right"></i>'
            }
        },
        "dom": 'rt<"mt-4"p>',
    });

    // Search functionality
    $('#instrumentSearch').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Status filter
    $('#statusFilter').on('change', function() {
        table.column(2).search(this.value).draw();
    });

    // Usage filter
    $('#usageFilter').on('change', function() {
        let searchTerm = '';
        if (this.value === 'recent') {
            searchTerm = 'this month';
        } else if (this.value === 'unused') {
            searchTerm = 'No recent';
        }
        table.column(4).search(searchTerm).draw();
    });
});

// Modal Functions
function showAddInstrumentModal() {
    $('#instrumentModalTitle').text('Add New Instrument');
    $('#instrumentForm')[0].reset();
    $('#instrument_id_field').val('');
    // Uncheck all parameters
    $('input[name="parameters[]"]').prop('checked', false);
    $('#instrumentModal').removeClass('hidden').addClass('flex');
}

function hideInstrumentModal() {
    $('#instrumentModal').addClass('hidden').removeClass('flex');
}

function editInstrument(instrumentId) {
    // Fetch instrument data and populate form
    fetch(`get_instrument_data.php?id=${instrumentId}`)
        .then(response => response.json())
        .then(instrument => {
            $('#instrumentModalTitle').text('Edit Instrument');
            $('#instrument_id_field').val(instrument.id);
            $('#instrument_name').val(instrument.name);
            $('#instrument_code').val(instrument.code);
            $('#instrument_description').val(instrument.description || '');
            $('#instrument_location').val(instrument.location || '');
            $('#instrument_status').val(instrument.status);
            
            // Set parameter checkboxes
            $('input[name="parameters[]"]').prop('checked', false);
            <?php foreach ($parameter_columns as $param): ?>
            if (instrument.<?php echo $param; ?> == 1) {
                $('#param_<?php echo $param; ?>').prop('checked', true);
            }
            <?php endforeach; ?>
            
            $('#instrumentModal').removeClass('hidden').addClass('flex');
        })
        .catch(error => {
            console.error('Error fetching instrument data:', error);
            showNotification('Error loading instrument data', 'error');
        });
}

function viewInstrument(instrumentId) {
    // Load instrument details in view modal
    $('#instrumentDetails').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    $('#viewInstrumentModal').removeClass('hidden').addClass('flex');
    
    fetch(`get_instrument_details.php?id=${instrumentId}`)
        .then(response => response.json())
        .then(data => {
            $('#instrumentDetails').html(data.html);
        })
        .catch(error => {
            $('#instrumentDetails').html('<div class="text-center py-4 text-red-500">Error loading instrument details</div>');
        });
}

function hideViewInstrumentModal() {
    $('#viewInstrumentModal').addClass('hidden').removeClass('flex');
}

// Form submission
$('#instrumentForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
    submitBtn.disabled = true;
    
    fetch('instrument_actions.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message, 'success');
                hideInstrumentModal();
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

function deleteInstrument(instrumentId, instrumentName) {
    const action = () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('instrument_id', instrumentId);
        formData.append('csrf_token', '<?php echo esc_html($_SESSION['csrf_token']); ?>');
        
        fetch('instrument_actions.php', { method: 'POST', body: formData })
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
        'Delete Instrument',
        `Are you sure you want to delete "${instrumentName}"? This may affect existing logbook entries.`,
        action
    );
}

// Notification function
function showNotification(message, type = 'success') {
    // Remove existing notification
    const existingNotification = document.getElementById('notification-toast');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.id = 'notification-toast';
    notification.className = `fixed top-20 right-6 px-4 py-3 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    notification.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} w-4 h-4"></i>
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

// Close modals on ESC key and outside click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideInstrumentModal();
        hideViewInstrumentModal();
        hideConfirmModal();
    }
});

document.addEventListener('click', function(e) {
    const instrumentModal = document.getElementById('instrumentModal');
    const viewModal = document.getElementById('viewInstrumentModal');
    const confirmModal = document.getElementById('confirmModal');
    
    if (e.target === instrumentModal && instrumentModal.classList.contains('flex')) {
        hideInstrumentModal();
    }
    if (e.target === viewModal && viewModal.classList.contains('flex')) {
        hideViewInstrumentModal();
    }
    if (e.target === confirmModal && confirmModal.classList.contains('flex')) {
        hideConfirmModal();
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>