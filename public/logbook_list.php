<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Ambil semua data logbook dari database
$stmt = $pdo->query("
    SELECT 
        le.*, 
        u.name as user_name, 
        i.name as instrument_name,
        i.code as instrument_code
    FROM logbook_entries le
    JOIN users u ON le.user_id = u.id
    JOIN instruments i ON le.instrument_id = i.id
    ORDER BY le.start_date DESC, le.start_time DESC
");
$logs = $stmt->fetchAll();

// Ambil daftar instrumen unik untuk filter
$instruments_for_filter = $pdo->query("SELECT id, name FROM instruments ORDER BY name ASC")->fetchAll();

// Definisikan kolom parameter untuk kemudahan
$parameter_columns = [
    'MobilePhase' => 'mobile_phase_val', 'Speed' => 'speed_val', 'ElectrodeType' => 'electrode_type_val', 
    'Result' => 'result_val', 'WavelengthScan' => 'wavelength_scan_val', 'Diluent' => 'diluent_val', 
    'Lamp' => 'lamp_val', 'Column' => 'column_val', 'Apparatus' => 'apparatus_val', 
    'Medium' => 'medium_val', 'TotalVolume' => 'total_volume_val', 'VesselQuantity' => 'vessel_quantity_val'
];
?>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<style>
    /* Custom DataTables styling to match theme */
    .dataTables_wrapper { font-family: 'Inter', sans-serif; }
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_length { display: none !important; }
    
    /* Improved pagination styling and positioning */
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
        line-height: 1 !important; 
        min-width: 2rem !important;
        text-align: center !important;
        background: white !important;
        transition: all 0.2s ease !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover { 
        background: hsl(210, 40%, 96.1%) !important; 
        border-color: hsl(221.2, 83.2%, 53.3%) !important; 
        color: hsl(221.2, 83.2%, 53.3%) !important; 
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { 
        background: hsl(221.2, 83.2%, 53.3%) !important; 
        border-color: hsl(221.2, 83.2%, 53.3%) !important; 
        color: white !important; 
        font-weight: 600 !important; 
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { 
        background: hsl(221.2, 83.2%, 45%) !important; 
        border-color: hsl(221.2, 83.2%, 45%) !important; 
        color: white !important; 
        transform: translateY(-1px) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { 
        color: hsl(215.4, 16.3%, 46.9%) !important; 
        cursor: not-allowed !important; 
        opacity: 0.5 !important; 
        background: hsl(210, 40%, 96.1%) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover { 
        background: hsl(210, 40%, 96.1%) !important; 
        border-color: hsl(214.3, 31.8%, 91.4%) !important; 
        transform: none !important;
        box-shadow: none !important;
    }
    
    /* Table styling improvements */
    #logbookTable thead th { 
        background-color: hsl(210, 40%, 96.1%) !important; 
        color: hsl(215.4, 16.3%, 46.9%) !important; 
        font-weight: 600 !important; 
        font-size: 0.75rem !important; 
        white-space: nowrap; 
        border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important; 
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }
    #logbookTable tbody tr { 
        border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important; 
        font-size: 0.75rem !important; 
        transition: all 0.2s ease !important;
    }
    #logbookTable tbody tr:hover { 
        background-color: hsl(210, 40%, 96.1%) !important; 
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    }
    #logbookTable tbody td { 
        color: hsl(222.2, 84%, 4.9%) !important; 
        font-size: 0.75rem !important; 
    }
    
    /* Action buttons styling */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 0.375rem;
        border: 1px solid transparent;
        font-size: 0.75rem;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
    }
    .action-btn-view {
        background-color: #eff6ff;
        color: #2563eb;
        border-color: #dbeafe;
    }
    .action-btn-view:hover {
        background-color: #dbeafe;
        color: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
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
        border-color: #e5e7eb;
        cursor: not-allowed;
        opacity: 0.5;
    }
    .action-btn-disabled:hover {
        transform: none;
        box-shadow: none;
    }
    
    .dataTables_wrapper .dataTables_processing { 
        background: white !important; 
        color: hsl(222.2, 84%, 4.9%) !important; 
        border: 1px solid hsl(214.3, 31.8%, 91.4%) !important; 
        border-radius: 0.5rem !important; 
    }
    .dt-buttons, .hidden-export-btn { display: none !important; }
    #exportDropdown { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); animation: fadeIn 0.15s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    #exportDropdown button:hover i { transform: scale(1.1); transition: transform 0.15s ease; }
    #exportToast { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    
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
    .modal-overlay[style*="display: flex"] {
        display: flex !important;
    }
    .modal-overlay[style*="display: none"] {
        display: none !important;
    }
    .modal-content {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid hsl(214.3, 31.8%, 91.4%);
        width: 100%;
        max-width: 42rem;
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
        <span>Logbook List</span>
        <a href="entry.php" class="btn btn-primary text-xs !py-1 !px-2">
            <i class="fas fa-plus mr-1"></i>Add New Entry
        </a>
    </div>
    
    <div class="p-6 space-y-6">
        <!-- Filter Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 border-b pb-6">
            <div>
                <label for="generalSearch" class="form-label text-xs">Search</label>
                <input type="text" id="generalSearch" placeholder="Search anything..." class="form-input text-xs">
            </div>
            <div>
                <label for="dateFromFilter" class="form-label text-xs">Date From</label>
                <input type="date" id="dateFromFilter" class="form-input text-xs">
            </div>
            <div>
                <label for="dateToFilter" class="form-label text-xs">Date To</label>
                <input type="date" id="dateToFilter" class="form-input text-xs">
            </div>
            <div>
                <label for="instrumentFilter" class="form-label text-xs">Instrument</label>
                <select id="instrumentFilter" class="form-select text-xs">
                    <option value="">All Instruments</option>
                    <?php foreach ($instruments_for_filter as $instrument): ?>
                        <option value="<?php echo esc_html($instrument['name']); ?>"><?php echo esc_html($instrument['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Table Section -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <div class="text-xs text-muted-foreground"><span id="table-info">Loading entries...</span></div>
                <div class="relative">
                    <button id="exportDropdownBtn" class="btn btn-success text-xs">
                        <i class="fas fa-download mr-1"></i>Export<i class="fas fa-chevron-down ml-1"></i>
                    </button>
                    <div id="exportDropdown" class="absolute right-0 top-full mt-2 w-48 bg-card rounded-lg shadow-lg border border-border py-1.5 hidden z-50">
                        <button id="copyBtn" class="w-full text-left flex items-center px-3 py-1.5 text-xs text-foreground hover:bg-accent transition-colors"><i class="fas fa-copy w-3 h-3 mr-2 text-blue-500"></i>Copy to Clipboard</button>
                        <button id="csvBtn" class="w-full text-left flex items-center px-3 py-1.5 text-xs text-foreground hover:bg-accent transition-colors"><i class="fas fa-file-csv w-3 h-3 mr-2 text-green-500"></i>Export as CSV</button>
                        <button id="excelBtn" class="w-full text-left flex items-center px-3 py-1.5 text-xs text-foreground hover:bg-accent transition-colors"><i class="fas fa-file-excel w-3 h-3 mr-2 text-green-600"></i>Export as Excel</button>
                        <hr class="my-1 border-border">
                        <button id="printBtn" class="w-full text-left flex items-center px-3 py-1.5 text-xs text-foreground hover:bg-accent transition-colors"><i class="fas fa-print w-3 h-3 mr-2 text-gray-500"></i>Print Table</button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="logbookTable" class="display table-auto w-full text-xs" style="width:100%">
                    <thead>
                        <tr>
                            <th class="p-2 text-left">Log Code</th>
                            <th class="p-2 text-left">Instrument</th>
                            <th class="p-2 text-left">User</th>
                            <th class="p-2 text-left">Sample Name</th>
                            <th class="p-2 text-left">Trial Code</th>
                            <th class="p-2 text-left">Start Time</th>
                            <th class="p-2 text-left">Finish Time</th>
                            <?php foreach (array_keys($parameter_columns) as $param_name): ?>
                                <th class="p-2 text-left"><?php echo esc_html(preg_replace('/(?<!^)([A-Z])/', ' $1', $param_name)); ?></th>
                            <?php endforeach; ?>
                            <th class="p-2 text-left">Condition After</th>
                            <th class="p-2 text-left">Status</th>
                            <th class="p-2 text-left">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="<?php echo count($parameter_columns) + 10; ?>" class="p-8 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center gap-2"><i class="fas fa-inbox text-2xl opacity-50"></i><p>No logbook entries found.</p><a href="entry.php" class="text-primary hover:underline text-sm">Create your first entry</a></div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-accent/50">
                                <td class="p-2 whitespace-nowrap font-medium">
                                    <a href="edit_entry.php?id=<?php echo (int)$log['id']; ?>" class="text-blue-600 hover:underline hover:text-blue-800">
                                        <?php echo esc_html($log['log_book_code']); ?>
                                    </a>
                                </td>
                                <td class="p-2 whitespace-nowrap"><?php echo esc_html($log['instrument_name']); ?></td>
                                <td class="p-2 whitespace-nowrap"><?php echo esc_html($log['user_name']); ?></td>
                                <td class="p-2 whitespace-nowrap"><?php echo esc_html($log['sample_name']); ?></td>
                                <td class="p-2 whitespace-nowrap"><?php echo esc_html($log['trial_code']); ?></td>
                                <td class="p-2 whitespace-nowrap"><?php echo esc_html(date('Y-m-d H:i', strtotime($log['start_date'] . ' ' . $log['start_time']))); ?></td>
                                <td class="p-2 whitespace-nowrap"><?php if ($log['finish_date']): ?><?php echo esc_html(date('Y-m-d H:i', strtotime($log['finish_date'] . ' ' . $log['finish_time']))); ?><?php else: ?><span class="text-muted-foreground italic">In Progress</span><?php endif; ?></td>
                                
                                <?php foreach ($parameter_columns as $db_col): ?>
                                    <td class="p-2 whitespace-nowrap"><?php echo esc_html($log[$db_col]); ?></td>
                                <?php endforeach; ?>
                                
                                <td class="p-2 whitespace-nowrap">
                                    <?php if ($log['condition_after']): ?><span class="px-2 py-0.5 text-[10px] rounded-full <?php echo $log['condition_after'] === 'Good' ? 'bg-green-100 text-green-800' : ($log['condition_after'] === 'Need Maintenance' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>"><?php echo esc_html($log['condition_after']); ?></span><?php else: ?><span class="text-muted-foreground">-</span><?php endif; ?>
                                </td>
                                <td class="p-2 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-[10px] rounded-full <?php echo $log['status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo esc_html($log['status']); ?></span>
                                </td>
                                <td class="p-2 max-w-xs"><?php echo esc_html($log['remark']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Entry Modal -->
<div id="viewModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Entry Details</h3>
            <button onclick="hideViewModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Log Book Code</label>
                    <div id="view-code" class="text-sm font-medium mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Status</label>
                    <div id="view-status" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Instrument</label>
                    <div id="view-instrument" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">User</label>
                    <div id="view-user" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Sample Name</label>
                    <div id="view-sample" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Trial Code</label>
                    <div id="view-trial" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Start Time</label>
                    <div id="view-start" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Finish Time</label>
                    <div id="view-finish" class="text-sm mt-1"></div>
                </div>
                <div>
                    <label class="text-xs font-medium text-muted-foreground">Condition After Use</label>
                    <div id="view-condition" class="text-sm mt-1"></div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-medium text-muted-foreground">Remark</label>
                    <div id="view-remark" class="text-sm mt-1 p-3 bg-muted/50 rounded-lg"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal-content max-w-md">
        <div class="modal-header">
            <h3 class="modal-title text-red-600">Confirm Delete</h3>
            <button onclick="hideDeleteModal()" class="modal-close">
                <i class="fas fa-times w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-sm font-medium text-foreground">Delete Entry</h4>
                    <p class="text-xs text-muted-foreground mt-1">This action cannot be undone.</p>
                </div>
            </div>
            <p class="text-sm text-muted-foreground mb-6">
                Are you sure you want to delete entry <strong id="delete-entry-code"></strong>? 
                All data associated with this entry will be permanently removed.
            </p>
            <div class="flex justify-end gap-3">
                <button onclick="hideDeleteModal()" class="btn btn-secondary text-xs">Cancel</button>
                <form id="deleteForm" method="POST" action="delete_entry_action.php" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="entry_id" value="">
                    <button type="submit" class="btn btn-danger text-xs">
                        <i class="fas fa-trash mr-1"></i>Delete Entry
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    // Enhanced DataTable configuration with better pagination and column settings
    let table = $('#logbookTable').DataTable({
        pageLength: 25,
        lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
        dom: 'Blrtip',
        buttons: [ 
            { extend: 'copy', className: 'hidden-export-btn' }, 
            { extend: 'csv', className: 'hidden-export-btn' }, 
            { extend: 'excel', className: 'hidden-export-btn' }, 
            { extend: 'print', className: 'hidden-export-btn' } 
        ],
        order: [[ 6, "desc" ]], // Start Time column (adjusted for new Actions column)
        columnDefs: [
            { 
                orderable: false, 
                targets: 0, // Actions column
                searchable: false,
                className: 'text-center'
            }
        ],
        language: { 
            info: "Showing _START_ to _END_ of _TOTAL_ entries", 
            zeroRecords: "No matching records found", 
            paginate: { 
                next: '<i class="fas fa-chevron-right"></i>', 
                previous: '<i class="fas fa-chevron-left"></i>',
                first: '<i class="fas fa-angle-double-left"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            },
            search: "Search entries:",
            lengthMenu: "Show _MENU_ entries per page"
        },
        drawCallback: function() { 
            let info = this.api().page.info(); 
            $('#table-info').text(`Showing ${info.start + 1} to ${info.end} of ${info.recordsTotal} entries`); 
        },
        responsive: true,
        scrollX: true,
        fixedColumns: {
            leftColumns: 2 // Fix Actions and Log Code columns
        }
    });

    // Export dropdown functionality
    const exportDropdownBtn = $('#exportDropdownBtn');
    const exportDropdown = $('#exportDropdown');
    
    exportDropdownBtn.on('click', e => { 
        e.stopPropagation(); 
        exportDropdown.toggleClass('hidden'); 
    });
    
    $(document).on('click', e => { 
        if (!exportDropdown.is(e.target) && exportDropdownBtn.has(e.target).length === 0) { 
            exportDropdown.addClass('hidden'); 
        } 
    });
    
    // Export button actions
    $('#copyBtn').on('click', () => { 
        table.button(0).trigger(); 
        exportDropdown.addClass('hidden'); 
        showToast('Data copied to clipboard!', 'success'); 
    });
    
    $('#csvBtn').on('click', () => { 
        table.button(1).trigger(); 
        exportDropdown.addClass('hidden'); 
        showToast('CSV file downloaded!', 'success'); 
    });
    
    $('#excelBtn').on('click', () => { 
        table.button(2).trigger(); 
        exportDropdown.addClass('hidden'); 
        showToast('Excel file downloaded!', 'success'); 
    });
    
    $('#printBtn').on('click', () => { 
        table.button(3).trigger(); 
        exportDropdown.addClass('hidden'); 
    });
    
    // Enhanced toast notification
    function showToast(message, type = 'success') {
        $('#exportToast').remove();
        const iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
        const bgClass = type === 'success' ? 'bg-green-500' : 'bg-red-500';
        
        const toast = $(`
            <div id="exportToast" class="fixed top-20 right-6 px-4 py-3 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm ${bgClass} text-white">
                <div class="flex items-center gap-2">
                    <i class="fas fa-${iconClass} w-4 h-4"></i>
                    <span>${message}</span>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        setTimeout(() => toast.removeClass('translate-x-full').addClass('translate-x-0'), 100);
        setTimeout(() => { 
            toast.removeClass('translate-x-0').addClass('translate-x-full'); 
            setTimeout(() => toast.remove(), 300); 
        }, 3000);
    }

    // Enhanced search and filter functionality
    $('#generalSearch').on('keyup', function() { 
        table.search(this.value).draw(); 
    });
    
    $('#instrumentFilter').on('change', function() { 
        table.column(2).search(this.value).draw(); // Instrument column (adjusted for Actions)
    });
    
    // Date range filtering
    $('#dateFromFilter, #dateToFilter').on('change', function() {
        table.draw();
    });

    // Custom date range filter
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'logbookTable') {
                return true;
            }
            
            var dateFrom = $('#dateFromFilter').val();
            var dateTo = $('#dateToFilter').val();
            var startTime = data[6] || ''; // Start Time column (adjusted for Actions)
            
            if (!dateFrom && !dateTo) {
                return true;
            }
            
            var rowDate = startTime.split(' ')[0]; // Extract date part
            
            if (dateFrom && !dateTo) {
                return rowDate >= dateFrom;
            } else if (!dateFrom && dateTo) {
                return rowDate <= dateTo;
            } else if (dateFrom && dateTo) {
                return rowDate >= dateFrom && rowDate <= dateTo;
            }
            
            return true;
        }
    );
});

// Modal functionality for viewing entry details
function viewEntry(entryId) {
    const button = document.querySelector(`[data-id="${entryId}"]`);
    
    if (!button) {
        console.error('Button not found for entry:', entryId);
        return;
    }
    
    document.getElementById('view-code').textContent = button.dataset.code || '';
    document.getElementById('view-instrument').textContent = button.dataset.instrument || '';
    document.getElementById('view-user').textContent = button.dataset.user || '';
    document.getElementById('view-sample').textContent = button.dataset.sample || 'N/A';
    document.getElementById('view-trial').textContent = button.dataset.trial || 'N/A';
    document.getElementById('view-start').textContent = button.dataset.start || '';
    document.getElementById('view-finish').textContent = button.dataset.finish || 'In Progress';
    document.getElementById('view-condition').textContent = button.dataset.condition || 'N/A';
    document.getElementById('view-status').innerHTML = `<span class="px-2 py-1 text-xs rounded-full ${button.dataset.status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">${button.dataset.status || 'Unknown'}</span>`;
    document.getElementById('view-remark').textContent = button.dataset.remark || 'No remarks provided';
    
    const modal = document.getElementById('viewModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideViewModal() {
    const modal = document.getElementById('viewModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Delete confirmation functionality
function confirmDelete(entryId, entryCode) {
    if (!entryId || !entryCode) {
        console.error('Invalid entry data for deletion');
        return;
    }
    
    document.getElementById('delete-entry-code').textContent = entryCode;
    document.querySelector('#deleteForm input[name="entry_id"]').value = entryId;
    
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Enhanced keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideViewModal();
        hideDeleteModal();
    }
});

// Click outside modal to close
document.addEventListener('click', function(e) {
    const viewModal = document.getElementById('viewModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (e.target === viewModal && viewModal.style.display === 'flex') {
        hideViewModal();
    }
    if (e.target === deleteModal && deleteModal.style.display === 'flex') {
        hideDeleteModal();
    }
});

// Add loading states to action buttons
document.addEventListener('DOMContentLoaded', function() {
    // Ensure modals are hidden on page load
    const viewModal = document.getElementById('viewModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (viewModal) {
        viewModal.style.display = 'none';
    }
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    
    // Add loading state to delete form submission
    const deleteForm = document.getElementById('deleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Handle success/error messages from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const message = urlParams.get('message');
    const error = urlParams.get('error');
    
    if (success) {
        showToast('Entry created successfully!', 'success');
        // Clean URL without page reload
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    if (message) {
        showToast(decodeURIComponent(message), 'success');
        // Clean URL without page reload
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    if (error) {
        showToast(decodeURIComponent(error), 'error');
        // Clean URL without page reload
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    function showToast(message, type = 'success') {
        const iconClass = type === 'success' ? 'check-circle' : 'exclamation-circle';
        const bgClass = type === 'success' ? 'bg-green-500' : 'bg-red-500';
        
        const existingToast = document.getElementById('statusToast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.id = 'statusToast';
        toast.className = `fixed top-20 right-6 px-4 py-3 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm ${bgClass} text-white`;
        toast.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-${iconClass} w-4 h-4"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>