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
    .dataTables_wrapper .dataTables_paginate { margin-top: 1.5rem !important; float: none !important; text-align: center !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 0.375rem !important; padding: 0.25rem 0.5rem !important; margin: 0 0.0625rem !important; border: 1px solid hsl(214.3, 31.8%, 91.4%) !important; color: hsl(222.2, 84%, 4.9%) !important; font-size: 0.75rem !important; line-height: 1 !important; min-width: auto !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: hsl(210, 40%, 96.1%) !important; border-color: hsl(214.3, 31.8%, 91.4%) !important; color: hsl(222.2, 84%, 4.9%) !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: hsl(221.2, 83.2%, 53.3%) !important; border-color: hsl(221.2, 83.2%, 53.3%) !important; color: white !important; font-weight: 500 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover { background: hsl(221.2, 83.2%, 53.3%) !important; border-color: hsl(221.2, 83.2%, 53.3%) !important; color: white !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { color: hsl(215.4, 16.3%, 46.9%) !important; cursor: not-allowed !important; opacity: 0.5 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover { background: transparent !important; border-color: hsl(214.3, 31.8%, 91.4%) !important; }
    #logbookTable thead th { background-color: hsl(210, 40%, 96.1%) !important; color: hsl(215.4, 16.3%, 46.9%) !important; font-weight: 500 !important; font-size: 0.75rem !important; white-space: nowrap; border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important; }
    #logbookTable tbody tr { border-bottom: 1px solid hsl(214.3, 31.8%, 91.4%) !important; font-size: 0.75rem !important; }
    #logbookTable tbody tr:hover { background-color: hsl(210, 40%, 96.1%) !important; }
    #logbookTable tbody td { color: hsl(222.2, 84%, 4.9%) !important; font-size: 0.75rem !important; }
    .dataTables_wrapper .dataTables_processing { background: white !important; color: hsl(222.2, 84%, 4.9%) !important; border: 1px solid hsl(214.3, 31.8%, 91.4%) !important; border-radius: 0.5rem !important; }
    .dt-buttons, .hidden-export-btn { display: none !important; }
    #exportDropdown { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); animation: fadeIn 0.15s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    #exportDropdown button:hover i { transform: scale(1.1); transition: transform 0.15s ease; }
    #exportToast { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
</style>

<div class="space-y-4">
    <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
        <div class="flex justify-between items-center border-b border-border pb-3">
            <div>
                <h2 class="text-lg font-bold text-foreground">Logbook List</h2>
                <p class="text-xs text-muted-foreground mt-1">Manage and view all logbook entries</p>
            </div>
            <a href="entry.php" class="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-md flex items-center gap-1.5 hover:bg-blue-600/90 transition-colors">
                <i class="fas fa-plus w-3 h-3"></i>
                <span>Add New Entry</span>
            </a>
        </div>
    </div>
    
    <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="generalSearch" class="block text-xs font-medium text-muted-foreground">Search</label>
                <input type="text" id="generalSearch" placeholder="Search anything..." class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring">
            </div>
            <div>
                <label for="dateFromFilter" class="block text-xs font-medium text-muted-foreground">Date From</label>
                <input type="date" id="dateFromFilter" class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring">
            </div>
            <div>
                <label for="dateToFilter" class="block text-xs font-medium text-muted-foreground">Date To</label>
                <input type="date" id="dateToFilter" class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring">
            </div>
            <div>
                <label for="instrumentFilter" class="block text-xs font-medium text-muted-foreground">Instrument</label>
                <select id="instrumentFilter" class="mt-1 w-full bg-muted border border-border rounded-md px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">All Instruments</option>
                    <?php foreach ($instruments_for_filter as $instrument): ?>
                        <option value="<?php echo esc_html($instrument['name']); ?>"><?php echo esc_html($instrument['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-card p-4 rounded-xl border border-border shadow-lg">
        <div class="flex justify-between items-center mb-4">
            <div class="text-xs text-muted-foreground"><span id="table-info">Loading entries...</span></div>
            <div class="relative">
                <button id="exportDropdownBtn" class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md flex items-center gap-1.5 hover:bg-green-600/90 transition-colors">
                    <i class="fas fa-download w-3 h-3"></i><span>Export</span><i class="fas fa-chevron-down w-2.5 h-2.5 ml-1"></i>
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

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    // PERBAIKAN DI SINI: Hapus 'columnDefs' agar DataTable tidak mengubah HTML
    let table = $('#logbookTable').DataTable({
        pageLength: 25,
        lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
        dom: 'Blrtip',
        buttons: [ { extend: 'copy', className: 'hidden-export-btn' }, { extend: 'csv', className: 'hidden-export-btn' }, { extend: 'excel', className: 'hidden-export-btn' }, { extend: 'print', className: 'hidden-export-btn' } ],
        order: [[ 5, "desc" ]],
        language: { info: "Showing _START_ to _END_ of _TOTAL_ entries", zeroRecords: "No matching records found", paginate: { next: "Next", previous: "Previous" } },
        drawCallback: function() { let info = this.api().page.info(); $('#table-info').text(`Showing ${info.start + 1} to ${info.end} of ${info.recordsTotal} entries`); }
    });

    // Fungsi filter dan export (tidak berubah)
    const exportDropdownBtn = $('#exportDropdownBtn');
    const exportDropdown = $('#exportDropdown');
    exportDropdownBtn.on('click', e => { e.stopPropagation(); exportDropdown.toggleClass('hidden'); });
    $(document).on('click', e => { if (!exportDropdown.is(e.target) && exportDropdownBtn.has(e.target).length === 0) { exportDropdown.addClass('hidden'); } });
    $('#copyBtn').on('click', () => { table.button(0).trigger(); exportDropdown.addClass('hidden'); showToast('Data copied!', 'success'); });
    $('#csvBtn').on('click', () => { table.button(1).trigger(); exportDropdown.addClass('hidden'); showToast('CSV downloaded!', 'success'); });
    $('#excelBtn').on('click', () => { table.button(2).trigger(); exportDropdown.addClass('hidden'); showToast('Excel downloaded!', 'success'); });
    $('#printBtn').on('click', () => { table.button(3).trigger(); exportDropdown.addClass('hidden'); });
    
    function showToast(message, type = 'success') {
        $('#exportToast').remove();
        const toast = $(`<div id="exportToast" class="fixed top-20 right-6 px-4 py-2 rounded-lg shadow-lg transition-transform transform translate-x-full z-50 text-sm ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}"><div class="flex items-center gap-2"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} w-4 h-4"></i><span>${message}</span></div></div>`);
        $('body').append(toast);
        setTimeout(() => toast.removeClass('translate-x-full').addClass('translate-x-0'), 100);
        setTimeout(() => { toast.removeClass('translate-x-0').addClass('translate-x-full'); setTimeout(() => toast.remove(), 300); }, 3000);
    }

    $('#generalSearch').on('keyup', function() { table.search(this.value).draw(); });
    $('#instrumentFilter').on('change', function() { table.column(1).search(this.value).draw(); });
    $('#dateFromFilter, #dateToFilter').on('change', () => table.draw());
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>