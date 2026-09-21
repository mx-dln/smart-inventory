<?php
$page = 'units';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $unitName = trim($_POST['unit_name'] ?? '');
        $abbreviation = trim($_POST['abbreviation'] ?? '');

        if (empty($unitName) || empty($abbreviation)) {
            echo '<script>window.location.href="index?page=units&error=' . urlencode('Unit name and abbreviation are required') . '";</script>';
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM units WHERE unit_name = ? OR abbreviation = ?");
            $stmt->execute([$unitName, $abbreviation]);
            if ($stmt->fetchColumn() > 0) {
                echo '<script>window.location.href="index?page=units&error=' . urlencode('Unit name or abbreviation already exists') . '";</script>';
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO units (unit_name, abbreviation) VALUES (?, ?)");
            $stmt->execute([$unitName, $abbreviation]);
            logAudit($pdo, 'Insert', 'Units', 'Added unit: ' . $unitName . ' (' . $abbreviation . ')');
            echo '<script>window.location.href="index?page=units&success=' . urlencode('Unit added successfully') . '";</script>';
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM units WHERE (unit_name = ? OR abbreviation = ?) AND id != ?");
            $stmt->execute([$unitName, $abbreviation, $id]);
            if ($stmt->fetchColumn() > 0) {
                echo '<script>window.location.href="index?page=units&error=' . urlencode('Unit name or abbreviation already exists') . '";</script>';
                exit;
            }

            $stmt = $pdo->prepare("UPDATE units SET unit_name=?, abbreviation=? WHERE id=?");
            $stmt->execute([$unitName, $abbreviation, $id]);
            logAudit($pdo, 'Update', 'Units', 'Updated unit ID: ' . $id . ', Name: ' . $unitName);
            echo '<script>window.location.href="index?page=units&success=' . urlencode('Unit updated successfully') . '";</script>';
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE unit_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo '<script>window.location.href="index?page=units&error=' . urlencode('Cannot delete unit assigned to existing items') . '";</script>';
            exit;
        }

        $stmt = $pdo->prepare("SELECT unit_name FROM units WHERE id = ?");
        $stmt->execute([$id]);
        $unit = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
        $stmt->execute([$id]);

        logAudit($pdo, 'Delete', 'Units', 'Deleted unit: ' . ($unit['unit_name'] ?? 'Unknown'));
        echo '<script>window.location.href="index?page=units&success=' . urlencode('Unit deleted successfully') . '";</script>';
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_unit') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmt->execute([$id]);
    $unit = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($unit);
    exit;
}

$stmt = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM inventory_items WHERE unit_id = u.id) as item_count FROM units u ORDER BY u.unit_name");
$units = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<?php if ($success): ?>
<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
    <span><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></span>
    <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
    <span><i class="fas fa-exclamation-circle mr-2"></i><?php echo h($error); ?></span>
    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <h3 class="text-lg font-semibold text-gray-800">Manage Units</h3>
    <div class="flex items-center gap-2">
        <button onclick="printPage('unitsTable', 'Units Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openModal('add')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add Unit
        </button>
    </div>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="unitsTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Unit Name</th>
                    <th class="px-4 py-3">Abbreviation</th>
                    <th class="px-4 py-3">Items Count</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($units as $u): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3"><?php echo $u['id']; ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($u['unit_name']); ?></td>
                    <td class="px-4 py-3 font-mono text-sm font-semibold text-gray-600"><?php echo h($u['abbreviation']); ?></td>
                    <td class="px-4 py-3"><?php echo $u['item_count']; ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button onclick="editUnit(<?php echo $u['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteUnit(<?php echo $u['id']; ?>, '<?php echo h(addslashes($u['unit_name'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($units) == 0): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No units found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="unitModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add Unit</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="unitForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="recordId" value="">
                <?php echo csrfField(); ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Name <span class="text-red-500">*</span></label>
                        <input type="text" name="unit_name" id="unitName" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., Kilogram, Liter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Abbreviation <span class="text-red-500">*</span></label>
                        <input type="text" name="abbreviation" id="unitAbbr" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., kg, L" maxlength="10">
                        <p class="text-xs text-gray-400 mt-1">Short code for the unit (e.g., kg, L, pcs)</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(type, id) {
    var modal = document.getElementById('unitModal');
    modal.classList.remove('hidden');
    if (type === 'add') {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').textContent = 'Add Unit';
        document.getElementById('unitForm').reset();
        document.getElementById('recordId').value = '';
    }
}

function editUnit(id) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Unit';
    $.ajax({
        url: 'ajax/get_unit.php?id=' + id,
        dataType: 'json',
        success: function(r) {
            if (!r || r.error) { showToast('Failed to load unit', 'error'); return; }
            document.getElementById('recordId').value = r.id;
            document.getElementById('unitName').value = r.unit_name || '';
            document.getElementById('unitAbbr').value = r.abbreviation || '';
            document.getElementById('unitModal').classList.remove('hidden');
        },
        error: function() { showToast('Failed to load unit', 'error'); }
    });
}

function deleteUnit(id, name) {
    if (confirm('Delete unit "' + name + '"? Items using this unit must be reassigned first.')) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(f);
        f.submit();
    }
}

function closeModal() {
    document.getElementById('unitModal').classList.add('hidden');
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
