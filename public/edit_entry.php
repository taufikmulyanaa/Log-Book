<?php
// public/edit_entry.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../templates/header.php';

// Validasi dan ambil ID entri
$entry_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$entry_id) {
    die('Invalid entry ID.');
}

// Ambil data entri dari database
$stmt = $pdo->prepare("SELECT le.*, u.name as user_name FROM logbook_entries le JOIN users u ON le.user_id = u.id WHERE le.id = :id");
$stmt->execute(['id' => $entry_id]);
$entry = $stmt->fetch();

if (!$entry) {
    die('Entry not found.');
}

// Cek hak akses
if ($entry['user_id'] != $_SESSION['user_id'] && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin')) {
    die('You do not have permission to edit this entry.');
}

// Ambil semua instrumen untuk dropdown
$instruments = $pdo->query("SELECT id, name, code FROM instruments ORDER BY name ASC")->fetchAll();

// Ambil matriks instrumen untuk logika JavaScript
$matrix_stmt = $pdo->query("SELECT * FROM instruments");
$instrument_matrix_data = [];
$parameter_columns = ['MobilePhase', 'Speed', 'ElectrodeType', 'Result', 'WavelengthScan', 'Diluent', 'Lamp', 'Column', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'];
foreach ($matrix_stmt->fetchAll() as $instrument) {
    $active_fields = [];
    foreach ($parameter_columns as $param) { if ($instrument[$param]) $active_fields[] = $param; }
    $instrument_matrix_data[$instrument['id']] = ['fields' => $active_fields];
}

// Pisahkan parameter untuk tata letak dua kolom
$params_col1 = ['Column', 'MobilePhase', 'Speed', 'Diluent', 'ElectrodeType', 'Result'];
$params_col2 = ['WavelengthScan', 'Lamp', 'Apparatus', 'Medium', 'TotalVolume', 'VesselQuantity'];

$is_completed = ($entry && $entry['status'] === 'Completed');
?>

<div class="card">
    <div class="card-header text-base flex justify-between items-center">
        <span>Edit Log Book Entry #<?php echo esc_html($entry['log_book_code']); ?></span>
        <a href="logbook_list.php" class="btn btn-secondary text-xs !py-1 !px-2">
            <i class="fas fa-arrow-left mr-1"></i>Back
        </a>
    </div>
    
    <form action="update_entry_action.php" method="POST" class="p-6">
        <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="entry_id" value="<?php echo $entry_id; ?>">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-6 mb-6">
            <div>
                <label for="instrument" class="form-label text-xs">Instrument *</label>
                <select id="instrument" name="instrument_id" class="form-select text-xs" required <?php echo $is_completed ? 'disabled' : ''; ?>>
                    <?php foreach ($instruments as $instrument): ?>
                        <option value="<?php echo (int)$instrument['id']; ?>" <?php echo ($entry['instrument_id'] == $instrument['id']) ? 'selected' : ''; ?>>
                            <?php echo esc_html($instrument['name'] . ' (' . $instrument['code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
             <div>
                <label for="start_date" class="form-label text-xs">Start Date *</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo esc_html($entry['start_date']); ?>" class="form-input text-xs" required <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
            <div>
                <label for="start_time" class="form-label text-xs">Start Time *</label>
                <input type="time" id="start_time" name="start_time" value="<?php echo esc_html($entry['start_time']); ?>" class="form-input text-xs" required <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
            <div>
                <label for="sample_name" class="form-label text-xs">Sample Name</label>
                <input type="text" id="sample_name" name="sample_name" value="<?php echo esc_html($entry['sample_name']); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
            <div class="md:col-span-2">
                <label for="trial_code" class="form-label text-xs">Trial Code</label>
                <input type="text" id="trial_code" name="trial_code" value="<?php echo esc_html($entry['trial_code']); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
        </div>

        <h3 class="text-sm font-semibold mb-4">Instrument Parameters</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 border-b pb-6 mb-6">
            <div class="space-y-4">
                <?php foreach ($params_col1 as $param): 
                    $db_col = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $param)) . '_val'; ?>
                    <div id="field-group-<?php echo esc_html($param); ?>" class="hidden">
                        <label class="form-label text-xs"><?php echo esc_html(preg_replace('/(?<!^)([A-Z])/', ' $1', $param)); ?></label>
                        <input type="text" name="params[<?php echo esc_html($param); ?>]" value="<?php echo esc_html($entry[$db_col] ?? ''); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="space-y-4">
                <?php foreach ($params_col2 as $param): 
                    $db_col = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $param)) . '_val'; ?>
                    <div id="field-group-<?php echo esc_html($param); ?>" class="hidden">
                        <label class="form-label text-xs"><?php echo esc_html(preg_replace('/(?<!^)([A-Z])/', ' $1', $param)); ?></label>
                        <input type="text" name="params[<?php echo esc_html($param); ?>]" value="<?php echo esc_html($entry[$db_col] ?? ''); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="finish_date" class="form-label text-xs">Finish Date</label>
                <input type="date" id="finish_date" name="finish_date" value="<?php echo esc_html($entry['finish_date']); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
            <div>
                <label for="finish_time" class="form-label text-xs">Finish Time</label>
                <input type="time" id="finish_time" name="finish_time" value="<?php echo esc_html($entry['finish_time']); ?>" class="form-input text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>>
            </div>
            <div>
                <label for="condition_after" class="form-label text-xs">Condition After Use</label>
                <select id="condition_after" name="condition_after" class="form-select text-xs" <?php echo $is_completed ? 'disabled' : ''; ?>>
                    <option value="" <?php echo ($entry['condition_after'] == '') ? 'selected' : ''; ?>>Choose</option>
                    <option value="Good" <?php echo ($entry['condition_after'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                    <option value="Need Maintenance" <?php echo ($entry['condition_after'] == 'Need Maintenance') ? 'selected' : ''; ?>>Need Maintenance</option>
                    <option value="Broken" <?php echo ($entry['condition_after'] == 'Broken') ? 'selected' : ''; ?>>Broken</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="remark" class="form-label text-xs">Remark</label>
                <textarea id="remark" name="remark" rows="3" class="form-textarea text-xs" <?php echo $is_completed ? 'readonly' : ''; ?>><?php echo esc_html($entry['remark']); ?></textarea>
            </div>
        </div>
        
        <div class="mt-6 pt-6 border-t flex items-center justify-between">
            <div>
                <label for="status" class="form-label text-xs">Entry Status</label>
                <select id="status" name="status" class="form-select text-xs" <?php echo $is_completed ? 'disabled' : ''; ?>>
                    <option value="Not Complete" <?php echo ($entry['status'] == 'Not Complete') ? 'selected' : ''; ?>>Not Complete</option>
                    <option value="Completed" <?php echo ($entry['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="flex gap-2">
                <?php if (!$is_completed): ?>
                <button type="submit" class="btn btn-success text-xs">
                    <i class="fas fa-save mr-1"></i>Update Entry
                </button>
                <button type="button" onclick="confirmDelete(<?php echo $entry_id; ?>)" class="btn btn-danger text-xs">
                    <i class="fas fa-trash mr-1"></i>Delete
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold mb-4">Confirm Delete</h3>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete this entry? This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
            <button onclick="hideDeleteModal()" class="btn btn-secondary text-xs">Cancel</button>
            <form id="deleteForm" method="POST" action="delete_entry_action.php" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="entry_id" value="">
                <button type="submit" class="btn btn-danger text-xs">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const instrumentSelect = document.getElementById('instrument');
    const INSTRUMENT_MATRIX = <?php echo json_encode($instrument_matrix_data); ?>;
    const ALL_FIELDS = <?php echo json_encode($parameter_columns); ?>;
    
    function updateFormFields(instrumentId) {
        let hasVisibleParams = false;
        ALL_FIELDS.forEach(field => {
            const group = document.getElementById(`field-group-${field}`);
            if (group) group.classList.add('hidden');
        });

        if (instrumentId && INSTRUMENT_MATRIX[instrumentId]) {
            const activeFields = INSTRUMENT_MATRIX[instrumentId].fields;
            if (activeFields.length > 0) hasVisibleParams = true;
            
            activeFields.forEach(field => {
                const group = document.getElementById(`field-group-${field}`);
                if (group) group.classList.remove('hidden');
            });
        }
        
        const paramsSection = document.querySelector('h3.font-semibold');
        if (paramsSection) {
            paramsSection.classList.toggle('hidden', !hasVisibleParams);
            paramsSection.nextElementSibling.classList.toggle('hidden', !hasVisibleParams);
        }
    }
    
    instrumentSelect.addEventListener('change', (e) => updateFormFields(e.target.value));
    
    // Panggil saat halaman dimuat untuk menampilkan parameter yang sudah ada
    updateFormFields(instrumentSelect.value);
});

function confirmDelete(entryId) {
    document.getElementById('deleteForm').querySelector('input[name="entry_id"]').value = entryId;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>