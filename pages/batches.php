<?php
// Batch/Lot Management Page

$items = $pdo->query("SELECT id, name, item_code FROM inventory_items WHERE status='Active' ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status='Active' ORDER BY company_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $itemId = (int)$_POST['item_id'];
        $batchNumber = trim($_POST['batch_number']);
        $lotNumber = trim($_POST['lot_number'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?: 0) ?: null;
        $manufDate = $_POST['manufacturing_date'] ?: null;
        $expDate = $_POST['expiration_date'] ?: null;
        $quantity = (float)$_POST['quantity'];
        $remaining = (float)($_POST['remaining_quantity'] ?? $quantity);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $purchaseRef = trim($_POST['purchase_reference'] ?? '');
        $location = trim($_POST['storage_location'] ?? '');
        $status = $_POST['status'] ?? 'Active';

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO batches (item_id, batch_number, lot_number, supplier_id, manufacturing_date, expiration_date, quantity, remaining_quantity, unit_cost, purchase_reference, storage_location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $batchNumber, $lotNumber, $supplierId, $manufDate, $expDate, $quantity, $remaining, $unitCost, $purchaseRef, $location, $status]);
            $batchId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO batch_movements (batch_id, movement_type, quantity, reference_type, previous_remaining, new_remaining, remarks, created_by) VALUES (?, 'In', ?, 'Initial', 0, ?, 'Batch created', ?)");
            $stmt->execute([$batchId, $quantity, $remaining, $userId]);

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
            $stmt->execute([$quantity, $itemId]);

            logAudit($pdo, 'Insert', 'Batches', 'Created batch: ' . $batchNumber);
            echo '<script>window.location.href="index?page=batches&success=' . urlencode('Batch created successfully') . '";</script>'; exit;
        } else {
            $stmt = $pdo->prepare("SELECT quantity, remaining_quantity, item_id FROM batches WHERE id = ?");
            $stmt->execute([$id]);
            $old = $stmt->fetch();

            $stmt = $pdo->prepare("UPDATE batches SET item_id=?, batch_number=?, lot_number=?, supplier_id=?, manufacturing_date=?, expiration_date=?, quantity=?, remaining_quantity=?, unit_cost=?, purchase_reference=?, storage_location=?, status=? WHERE id=?");
            $stmt->execute([$itemId, $batchNumber, $lotNumber, $supplierId, $manufDate, $expDate, $quantity, $remaining, $unitCost, $purchaseRef, $location, $status, $id]);

            if ($old && $old['item_id'] == $itemId) {
                $diff = $quantity - $old['quantity'];
                if ($diff != 0) {
                    $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
                    $stmt->execute([$diff, $itemId]);
                }
            }

            logAudit($pdo, 'Update', 'Batches', 'Updated batch: ' . $batchNumber);
            echo '<script>window.location.href="index?page=batches&success=' . urlencode('Batch updated successfully') . '";</script>'; exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT batch_number, quantity, item_id FROM batches WHERE id = ?");
        $stmt->execute([$id]);
        $batch = $stmt->fetch();
        if ($batch) {
            $stmt = $pdo->prepare("DELETE FROM batch_movements WHERE batch_id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM batches WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$batch['quantity'], $batch['item_id']]);
            logAudit($pdo, 'Delete', 'Batches', 'Deleted batch: ' . $batch['batch_number']);
        }
        echo '<script>window.location.href="index?page=batches&success=' . urlencode('Batch deleted successfully') . '";</script>'; exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_batch') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT b.*, i.name as item_name, i.item_code FROM batches b LEFT JOIN inventory_items i ON b.item_id = i.id WHERE b.id = ?");
    $stmt->execute([$id]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetch());
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'view_batch') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT b.*, i.name as item_name, i.item_code, i.unit_cost as item_unit_cost, s.company_name as supplier_name FROM batches b LEFT JOIN inventory_items i ON b.item_id = i.id LEFT JOIN suppliers s ON b.supplier_id = s.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $batch = $stmt->fetch();
    if (!$batch) { echo '<div class="text-center py-8 text-gray-500">Batch not found.</div>'; exit; }

    $movStmt = $pdo->prepare("SELECT bm.*, u.full_name as user_name FROM batch_movements bm LEFT JOIN users u ON bm.created_by = u.id WHERE bm.batch_id = ? ORDER BY bm.created_at DESC");
    $movStmt->execute([$id]);
    $movements = $movStmt->fetchAll();
    ?>
    <div class="mb-6">
        <h4 class="text-xl font-bold text-gray-900 mb-1"><?php echo h($batch['batch_number']); ?></h4>
        <div class="flex items-center space-x-2 text-sm text-gray-500">
            <span class="font-mono"><?php echo h($batch['item_name'] ?? 'Unknown Item'); ?></span>
            <?php if ($batch['lot_number']): ?>
            <span class="text-gray-300">|</span>
            <span>Lot: <?php echo h($batch['lot_number']); ?></span>
            <?php endif; ?>
            <span class="text-gray-300">|</span>
            <span class="px-2 py-0.5 text-xs rounded-full <?php echo getStatusBadge($batch['status']); ?>"><?php echo $batch['status']; ?></span>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200">
            <p class="text-xs text-emerald-600 font-medium mb-1">Quantity</p>
            <p class="text-xl font-bold text-emerald-700"><?php echo formatNumber($batch['quantity']); ?></p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
            <p class="text-xs text-blue-600 font-medium mb-1">Remaining</p>
            <p class="text-xl font-bold text-blue-700"><?php echo formatNumber($batch['remaining_quantity']); ?></p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
            <p class="text-xs text-purple-600 font-medium mb-1">Unit Cost</p>
            <p class="text-xl font-bold text-purple-700"><?php echo formatCurrency($batch['unit_cost']); ?></p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-4 border border-amber-200">
            <p class="text-xs text-amber-600 font-medium mb-1">Total Value</p>
            <p class="text-xl font-bold text-amber-700"><?php echo formatCurrency($batch['remaining_quantity'] * $batch['unit_cost']); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-box text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Item</p>
                <p class="text-sm font-medium text-gray-800"><?php echo h($batch['item_name'] ?? 'N/A'); ?> (<?php echo h($batch['item_code'] ?? ''); ?>)</p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-tag text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Lot Number</p>
                <p class="text-sm font-medium text-gray-800"><?php echo h($batch['lot_number'] ?: 'N/A'); ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-truck text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Supplier</p>
                <p class="text-sm font-medium text-gray-800"><?php echo h($batch['supplier_name'] ?? 'N/A'); ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-file-invoice text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Purchase Reference</p>
                <p class="text-sm font-medium text-gray-800"><?php echo h($batch['purchase_reference'] ?: 'N/A'); ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-calendar-plus text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Manufacturing Date</p>
                <p class="text-sm font-medium text-gray-800"><?php echo $batch['manufacturing_date'] ? formatDate($batch['manufacturing_date']) : 'N/A'; ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-calendar-times text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Expiration Date</p>
                <p class="text-sm font-medium text-gray-800">
                    <?php if ($batch['expiration_date']): ?>
                    <?php $exp = getExpirationStatus($batch['expiration_date']); ?>
                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo $exp['class']; ?>"><?php echo $exp['label']; ?> (<?php echo formatDate($batch['expiration_date']); ?>)</span>
                    <?php else: ?>
                    <span class="text-gray-400">N/A</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-map-marker-alt text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Storage Location</p>
                <p class="text-sm font-medium text-gray-800"><?php echo h($batch['storage_location'] ?: 'N/A'); ?></p>
            </div>
        </div>
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center"><i class="fas fa-clock text-gray-400 text-sm"></i></div>
            <div>
                <p class="text-xs text-gray-500">Created</p>
                <p class="text-sm font-medium text-gray-800"><?php echo formatDateTime($batch['created_at']); ?></p>
            </div>
        </div>
    </div>

    <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
        <i class="fas fa-exchange-alt text-gray-400 mr-2"></i> Movement History
    </h5>
    <?php if (count($movements) > 0): ?>
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-3 py-2.5">Date</th>
                    <th class="px-3 py-2.5">Type</th>
                    <th class="px-3 py-2.5">Qty</th>
                    <th class="px-3 py-2.5">Previous</th>
                    <th class="px-3 py-2.5">New</th>
                    <th class="px-3 py-2.5">Reference</th>
                    <th class="px-3 py-2.5">Remarks</th>
                    <th class="px-3 py-2.5">User</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($movements as $m): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-3 py-2 text-xs text-gray-500"><?php echo formatDateTime($m['created_at'], 'M d, Y h:i A'); ?></td>
                    <td class="px-3 py-2">
                        <span class="px-1.5 py-0.5 text-xs rounded-full font-medium <?php
                            echo $m['movement_type'] == 'In' ? 'bg-emerald-100 text-emerald-700' : ($m['movement_type'] == 'Out' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700');
                        ?>"><?php echo $m['movement_type']; ?></span>
                    </td>
                    <td class="px-3 py-2 font-medium"><?php echo formatNumber($m['quantity']); ?></td>
                    <td class="px-3 py-2 text-gray-600"><?php echo formatNumber($m['previous_remaining']); ?></td>
                    <td class="px-3 py-2 font-medium"><?php echo formatNumber($m['new_remaining']); ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?php echo h($m['reference_type'] ?? 'N/A'); ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?php echo h($m['remarks'] ?? ''); ?></td>
                    <td class="px-3 py-2 text-xs text-gray-500"><?php echo h($m['user_name'] ?? 'System'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
        <i class="fas fa-exchange-alt text-gray-300 text-2xl mb-2"></i>
        <p class="text-gray-400 text-sm">No movements recorded yet.</p>
    </div>
    <?php endif; ?>
    <?php
    exit;
}

$itemFilter = (int)($_GET['item_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$q = "SELECT b.*, i.name as item_name, i.item_code, s.company_name as supplier_name FROM batches b LEFT JOIN inventory_items i ON b.item_id = i.id LEFT JOIN suppliers s ON b.supplier_id = s.id WHERE 1=1";
$params = [];
if ($itemFilter) { $q .= " AND b.item_id = ?"; $params[] = $itemFilter; }
if ($statusFilter) { $q .= " AND b.status = ?"; $params[] = $statusFilter; }
$q .= " ORDER BY b.created_at DESC";
$batches = $pdo->prepare($q); $batches->execute($params); $batches = $batches->fetchAll();

$success = $_GET['success'] ?? ''; $error = $_GET['error'] ?? '';
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
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
        <select id="filterItem" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Items</option>
            <?php foreach ($items as $item): ?>
            <option value="<?php echo $item['id']; ?>" <?php echo $itemFilter == $item['id'] ? 'selected' : ''; ?>><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
            <?php endforeach; ?>
        </select>
        <select id="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Expired" <?php echo $statusFilter == 'Expired' ? 'selected' : ''; ?>>Expired</option>
            <option value="Depleted" <?php echo $statusFilter == 'Depleted' ? 'selected' : ''; ?>>Depleted</option>
            <option value="Disposed" <?php echo $statusFilter == 'Disposed' ? 'selected' : ''; ?>>Disposed</option>
        </select>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="printPage('batchesTable', 'Batches Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openAddModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add Batch
        </button>
    </div>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="batchesTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Batch #</th>
                    <th class="px-4 py-3">Lot #</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Remaining</th>
                    <th class="px-4 py-3">Manufactured</th>
                    <th class="px-4 py-3">Expiration</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($batches as $batch): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs font-medium"><?php echo h($batch['batch_number']); ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($batch['lot_number'] ?: '—'); ?></td>
                    <td class="px-4 py-3">
                        <span class="font-medium"><?php echo h($batch['item_name'] ?? 'N/A'); ?></span>
                        <span class="text-xs text-gray-400 block"><?php echo h($batch['item_code'] ?? ''); ?></span>
                    </td>
                    <td class="px-4 py-3"><?php echo h($batch['supplier_name'] ?? '—'); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo formatNumber($batch['quantity']); ?></td>
                    <td class="px-4 py-3">
                        <span class="font-semibold <?php echo $batch['remaining_quantity'] <= 0 ? 'text-red-600' : 'text-emerald-600'; ?>">
                            <?php echo formatNumber($batch['remaining_quantity']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs"><?php echo $batch['manufacturing_date'] ? formatDate($batch['manufacturing_date']) : '—'; ?></td>
                    <td class="px-4 py-3">
                        <?php if ($batch['expiration_date']): ?>
                        <?php $exp = getExpirationStatus($batch['expiration_date']); ?>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $exp['class']; ?>"><?php echo $exp['label']; ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusBadge($batch['status']); ?>"><?php echo $batch['status']; ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-1">
                            <button onclick="viewBatch(<?php echo $batch['id']; ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i class="fas fa-eye"></i></button>
                            <button onclick="editBatch(<?php echo $batch['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteBatch(<?php echo $batch['id']; ?>, '<?php echo h(addslashes($batch['batch_number'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($batches) == 0): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No batches found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="batchModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('batchModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900" id="batchModalTitle">Add New Batch</h3>
                <button onclick="closeModal('batchModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="batchForm">
                <input type="hidden" name="action" id="batchFormAction" value="add">
                <input type="hidden" name="id" id="batchId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <select name="item_id" id="batchItemId" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>"><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number <span class="text-red-500">*</span></label>
                        <input type="text" name="batch_number" id="batchNumber" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., BATCH-001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lot Number</label>
                        <input type="text" name="lot_number" id="batchLotNumber" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., LOT-A1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <select name="supplier_id" id="batchSupplierId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo $sup['id']; ?>"><?php echo h($sup['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Manufacturing Date</label>
                        <input type="date" name="manufacturing_date" id="batchManufDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date</label>
                        <input type="date" name="expiration_date" id="batchExpDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" id="batchQuantity" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="0.00" onchange="syncRemaining()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remaining Quantity</label>
                        <input type="number" name="remaining_quantity" id="batchRemaining" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="0.00">
                        <p class="text-xs text-gray-400 mt-1">Auto-set to quantity on add</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                        <input type="number" name="unit_cost" id="batchUnitCost" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Reference</label>
                        <input type="text" name="purchase_reference" id="batchPurchaseRef" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., PO-001">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Storage Location</label>
                        <input type="text" name="storage_location" id="batchLocation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., Shelf B-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="batchStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Active">Active</option>
                            <option value="Expired">Expired</option>
                            <option value="Depleted">Depleted</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('batchModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Save Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="viewBatchModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('viewBatchModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">Batch Details</h3>
                <button onclick="closeModal('viewBatchModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div id="viewBatchContent" class="p-6">
                <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>
            </div>
        </div>
    </div>
</div>

<?php if ($viewId > 0): ?>
<script>
$(document).ready(function() {
    viewBatch(<?php echo $viewId; ?>);
});
</script>
<?php endif; ?>

<script>
function openAddModal() {
    document.getElementById('batchFormAction').value = 'add';
    document.getElementById('batchModalTitle').textContent = 'Add New Batch';
    document.getElementById('batchForm').reset();
    document.getElementById('batchId').value = '';
    document.getElementById('batchRemaining').readOnly = false;
    document.getElementById('batchModal').classList.remove('hidden');
}

function editBatch(id) {
    document.getElementById('batchFormAction').value = 'edit';
    document.getElementById('batchModalTitle').textContent = 'Edit Batch';
    document.getElementById('batchRemaining').readOnly = false;

    $.ajax({
        url: 'ajax/get_batch.php?id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(batch) {
            if (!batch || batch.error) {
                showToast('Failed to load batch data', 'error');
                return;
            }
            document.getElementById('batchId').value = batch.id;
            document.getElementById('batchItemId').value = batch.item_id || '';
            document.getElementById('batchNumber').value = batch.batch_number || '';
            document.getElementById('batchLotNumber').value = batch.lot_number || '';
            document.getElementById('batchSupplierId').value = batch.supplier_id || '';
            document.getElementById('batchManufDate').value = batch.manufacturing_date || '';
            document.getElementById('batchExpDate').value = batch.expiration_date || '';
            document.getElementById('batchQuantity').value = batch.quantity || 0;
            document.getElementById('batchRemaining').value = batch.remaining_quantity || 0;
            document.getElementById('batchUnitCost').value = batch.unit_cost || 0;
            document.getElementById('batchPurchaseRef').value = batch.purchase_reference || '';
            document.getElementById('batchLocation').value = batch.storage_location || '';
            document.getElementById('batchStatus').value = batch.status || 'Active';
            document.getElementById('batchModal').classList.remove('hidden');
        },
        error: function() {
            showToast('Failed to load batch data', 'error');
        }
    });
}

function viewBatch(id) {
    document.getElementById('viewBatchModal').classList.remove('hidden');
    document.getElementById('viewBatchContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
    $.ajax({
        url: 'ajax/view_batch.php?id=' + id,
        method: 'GET',
        success: function(html) {
            document.getElementById('viewBatchContent').innerHTML = html;
        },
        error: function() {
            document.getElementById('viewBatchContent').innerHTML = '<div class="text-center py-8 text-red-500">Failed to load batch details.</div>';
        }
    });
}

function deleteBatch(id, batchNumber) {
    if (confirm('Are you sure you want to delete batch "' + batchNumber + '"? This will also reverse inventory quantities and cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(form);
        form.submit();
    }
}

function syncRemaining() {
    var action = document.getElementById('batchFormAction').value;
    if (action === 'add') {
        document.getElementById('batchRemaining').value = document.getElementById('batchQuantity').value;
    }
}

function applyFilters() {
    var item = document.getElementById('filterItem').value;
    var status = document.getElementById('filterStatus').value;
    var url = 'index?page=batches';
    if (item) url += '&item_id=' + item;
    if (status) url += '&status=' + status;
    window.location.href = url;
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        $('.fixed.inset-0.z-50').each(function() {
            if (!$(this).hasClass('hidden') && $(this).find('.bg-gray-900').length) {
                $(this).addClass('hidden');
            }
        });
    }
});
</script>
