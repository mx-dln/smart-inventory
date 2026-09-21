<?php
$page = 'stock-out';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $itemId = (int)$_POST['item_id'];
        $type = $_POST['transaction_type'];
        $quantity = (float)$_POST['quantity'];
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $totalCost = $quantity * $unitCost;
        $refNumber = trim($_POST['reference_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $dateReleased = $_POST['date_released'] ?? date('Y-m-d H:i:s');
        $recipient = trim($_POST['recipient'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT current_stock, unit_cost, name FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) throw new Exception('Item not found');
            if ((float)$item['current_stock'] < $quantity) throw new Exception('Insufficient stock. Available: ' . $item['current_stock']);

            $prevStock = (float)$item['current_stock'];
            $newStock = $prevStock - $quantity;
            $cost = $unitCost > 0 ? $unitCost : (float)$item['unit_cost'];
            $total = $quantity * $cost;

            $stmt = $pdo->prepare("INSERT INTO stock_out (item_id, transaction_type, quantity, unit_cost, total_cost, reference_number, purpose, requested_by, date_released) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $type, $quantity, $cost, $total, $refNumber, $remarks, $userId, $dateReleased]);
            $stockOutId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
            $stmt->execute([$newStock, $itemId]);

            $stmt = $pdo->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, reference_type, reference_id, quantity, previous_stock, new_stock, unit_cost, total_value, remarks, created_by) VALUES (?, 'Stock Out', 'Stock Out', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $stockOutId, $quantity, $prevStock, $newStock, $cost, $total, $remarks, $userId]);

            logAudit($pdo, 'Insert', 'Stock Out', 'Stock out recorded for item ID: ' . $itemId . ', Qty: ' . $quantity . ', Type: ' . $type);
            $pdo->commit();
            echo '<script>window.location.href="index?page=stock-out&success=' . urlencode('Stock out recorded successfully') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=stock-out&error=' . urlencode('Error: ' . $e->getMessage()) . '";</script>';
            exit;
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $itemId = (int)$_POST['item_id'];
        $type = $_POST['transaction_type'];
        $quantity = (float)$_POST['quantity'];
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $totalCost = $quantity * $unitCost;
        $refNumber = trim($_POST['reference_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        $recipient = trim($_POST['recipient'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT item_id, quantity, unit_cost FROM stock_out WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if (!$old) throw new Exception('Record not found');

            $stmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) throw new Exception('Item not found');

            $availableStock = (float)$item['current_stock'] + $old['quantity'];
            if ($availableStock < $quantity) throw new Exception('Insufficient stock. Available: ' . $availableStock);

            $diff = $quantity - $old['quantity'];

            $stmt = $pdo->prepare("UPDATE stock_out SET item_id=?, transaction_type=?, quantity=?, unit_cost=?, total_cost=?, reference_number=?, purpose=? WHERE id=?");
            $stmt->execute([$itemId, $type, $quantity, $unitCost, $totalCost, $refNumber, $remarks, $id]);

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$diff, $itemId]);

            logAudit($pdo, 'Update', 'Stock Out', 'Updated stock out record ID: ' . $id);
            $pdo->commit();
            echo '<script>window.location.href="index?page=stock-out&success=' . urlencode('Stock out updated') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=stock-out&error=' . urlencode('Error: ' . $e->getMessage()) . '";</script>';
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT so.*, i.name as item_name FROM stock_out so LEFT JOIN inventory_items i ON so.item_id = i.id WHERE so.id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) throw new Exception('Record not found');

            $dateReleased = strtotime($record['date_released']);
            $today = strtotime('today');
            if ($dateReleased < $today) {
                echo '<script>window.location.href="index?page=stock-out&error=' . urlencode('Cannot delete stock out records from previous days.') . '";</script>';
                exit;
            }

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
            $stmt->execute([$record['quantity'], $record['item_id']]);

            $stmt = $pdo->prepare("DELETE FROM stock_out WHERE id = ?");
            $stmt->execute([$id]);

            logAudit($pdo, 'Delete', 'Stock Out', 'Deleted stock out record ID: ' . $id . ', Item: ' . ($record['item_name'] ?? 'Unknown'));
            $pdo->commit();
            echo '<script>window.location.href="index?page=stock-out&success=' . urlencode('Record deleted') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=stock-out&error=' . urlencode('Error: ' . $e->getMessage()) . '";</script>';
            exit;
        }
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_record') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM stock_out WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($record);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_stock') {
    $itemId = (int)$_GET['item_id'];
    $stmt = $pdo->prepare("SELECT current_stock, unit_cost FROM inventory_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($item ?: ['current_stock' => 0, 'unit_cost' => 0]);
    exit;
}

$items = $pdo->query("SELECT id, name, item_code, current_stock FROM inventory_items WHERE status = 'Active' ORDER BY name")->fetchAll();

$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$query = "SELECT so.*, i.name as item_name, i.item_code, u.full_name as requested_by_name FROM stock_out so LEFT JOIN inventory_items i ON so.item_id = i.id LEFT JOIN users u ON so.requested_by = u.id WHERE 1=1";
$params = [];
if ($fromDate) { $query .= " AND DATE(so.date_released) >= ?"; $params[] = $fromDate; }
if ($toDate) { $query .= " AND DATE(so.date_released) <= ?"; $params[] = $toDate; }
if ($typeFilter) { $query .= " AND so.transaction_type = ?"; $params[] = $typeFilter; }
if ($searchFilter) { $query .= " AND (i.name LIKE ? OR so.reference_number LIKE ?)"; $params[] = "%$searchFilter%"; $params[] = "%$searchFilter%"; }
$query .= " ORDER BY so.date_released DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

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
    <div class="flex flex-wrap items-center gap-2">
        <input type="date" id="filterFrom" value="<?php echo $fromDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
        <span class="text-gray-400 text-sm">to</span>
        <input type="date" id="filterTo" value="<?php echo $toDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
        <select id="filterType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Types</option>
            <option value="Production Request" <?php echo $typeFilter=='Production Request'?'selected':''; ?>>Production Request</option>
            <option value="Damaged" <?php echo $typeFilter=='Damaged'?'selected':''; ?>>Damaged</option>
            <option value="Expired" <?php echo $typeFilter=='Expired'?'selected':''; ?>>Expired</option>
            <option value="Transfer" <?php echo $typeFilter=='Transfer'?'selected':''; ?>>Transfer</option>
            <option value="Consumption" <?php echo $typeFilter=='Consumption'?'selected':''; ?>>Consumption</option>
        </select>
        <input type="text" id="filterSearch" placeholder="Search item or reference..." value="<?php echo h($searchFilter); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onkeyup="applyFilters()">
    </div>
    <div class="flex items-center gap-2">
        <button onclick="printPage('stockOutTable', 'Stock Out Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openModal('add')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Stock Out
        </button>
    </div>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="stockOutTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Unit Cost</th>
                    <th class="px-4 py-3">Total Cost</th>
                    <th class="px-4 py-3">Requested By</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($records as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo formatDateTime($r['date_released'], 'M d, Y'); ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($r['reference_number'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <span class="font-medium"><?php echo h($r['item_name']); ?></span>
                        <span class="text-xs text-gray-400 block"><?php echo h($r['item_code']); ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php
                        $typeColors = [
                            'Production Request' => 'bg-blue-100 text-blue-800',
                            'Damaged' => 'bg-red-100 text-red-800',
                            'Expired' => 'bg-gray-100 text-gray-800',
                            'Transfer' => 'bg-indigo-100 text-indigo-800',
                            'Consumption' => 'bg-purple-100 text-purple-800',
                        ];
                        $badgeClass = $typeColors[$r['transaction_type']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $badgeClass; ?>"><?php echo h($r['transaction_type']); ?></span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-red-600"><?php echo formatNumber($r['quantity']); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($r['unit_cost']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo formatCurrency($r['total_cost']); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo h($r['requested_by_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button onclick="editRecord(<?php echo $r['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteRecord(<?php echo $r['id']; ?>)" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($records) == 0): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No stock out records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="stockModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">New Stock Out</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="stockForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="recordId" value="">
                <?php echo csrfField(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="itemId" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="checkStock()">
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>"><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p id="stockInfo" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type <span class="text-red-500">*</span></label>
                        <select name="transaction_type" id="transType" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Production Request">Production Request</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Expired">Expired</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Consumption">Consumption</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" oninput="calcTotal()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                        <input type="number" name="unit_cost" id="unitCost" step="0.01" min="0" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" oninput="calcTotal()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost</label>
                        <input type="text" id="totalCostDisplay" readonly class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm font-medium text-gray-700">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" name="reference_number" id="refNumber" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional reference number">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea name="remarks" id="remarks" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional notes"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Released</label>
                        <input type="datetime-local" name="date_released" id="dateReleased" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcTotal() {
    var qty = parseFloat(document.getElementById('quantity').value) || 0;
    var cost = parseFloat(document.getElementById('unitCost').value) || 0;
    document.getElementById('totalCostDisplay').value = '₱' + (qty * cost).toFixed(2);
}

function checkStock() {
    var itemId = document.getElementById('itemId').value;
    var info = document.getElementById('stockInfo');
    if (!itemId) { info.textContent = ''; return; }
    $.ajax({
        url: 'index?page=stock-out&ajax=check_stock&item_id=' + itemId,
        dataType: 'json',
        success: function(r) {
            if (r) {
                info.textContent = 'Available stock: ' + parseFloat(r.current_stock).toFixed(2) + (r.unit_cost > 0 ? ' | Unit cost: ₱' + parseFloat(r.unit_cost).toFixed(2) : '');
                if (parseFloat(document.getElementById('unitCost').value) === 0 && parseFloat(r.unit_cost) > 0) {
                    document.getElementById('unitCost').value = r.unit_cost;
                    calcTotal();
                }
            }
        }
    });
}

function openModal(type, id) {
    var modal = document.getElementById('stockModal');
    modal.classList.remove('hidden');
    if (type === 'add') {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').textContent = 'New Stock Out';
        document.getElementById('stockForm').reset();
        document.getElementById('recordId').value = '';
        document.getElementById('totalCostDisplay').value = '';
        document.getElementById('dateReleased').value = '<?php echo date('Y-m-d\TH:i'); ?>';
        document.getElementById('stockInfo').textContent = '';
    }
}

function editRecord(id) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Stock Out';
    $.ajax({
        url: 'ajax/get_stock_out.php?id=' + id,
        dataType: 'json',
        success: function(r) {
            if (!r || r.error) { showToast('Failed to load record', 'error'); return; }
            document.getElementById('recordId').value = r.id;
            document.getElementById('itemId').value = r.item_id;
            document.getElementById('transType').value = r.transaction_type;
            document.getElementById('quantity').value = r.quantity;
            document.getElementById('unitCost').value = r.unit_cost;

            document.getElementById('refNumber').value = r.reference_number || '';
            document.getElementById('remarks').value = r.remarks || '';
            document.getElementById('dateReleased').value = r.date_released ? r.date_released.substring(0, 16) : '';
            calcTotal();
            checkStock();
            document.getElementById('stockModal').classList.remove('hidden');
        },
        error: function() { showToast('Failed to load record', 'error'); }
    });
}

function deleteRecord(id) {
    if (confirm('Delete this stock out record? This will adjust inventory stock accordingly.')) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(f);
        f.submit();
    }
}

function applyFilters() {
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;
    var type = document.getElementById('filterType').value;
    var search = document.getElementById('filterSearch').value;
    var url = 'index?page=stock-out';
    if (from) url += '&from=' + from;
    if (to) url += '&to=' + to;
    if (type) url += '&type=' + type;
    if (search) url += '&search=' + encodeURIComponent(search);
    window.location.href = url;
}

function closeModal() {
    document.getElementById('stockModal').classList.add('hidden');
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
