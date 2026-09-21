<?php
// Inventory Management Page

// Get categories, units, suppliers for dropdowns
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'Active' ORDER BY category_name")->fetchAll();
$units = $pdo->query("SELECT * FROM units ORDER BY unit_name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status = 'Active' ORDER BY company_name")->fetchAll();

// Handle form submissions
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

function respond($success, $message) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    $param = $success ? 'success' : 'error';
    echo '<script>window.location.href="index?page=inventory&' . $param . '=' . urlencode($message) . '";</script>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        respond(false, 'Invalid security token');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $itemCode = trim($_POST['item_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $minStock = (float)($_POST['min_stock'] ?? 0);
        $maxStock = (float)($_POST['max_stock'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $expirationDate = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        $storageLocation = trim($_POST['storage_location'] ?? '');
        $status = $_POST['status'] ?? 'Active';

        if ($action === 'add') {
            $currentStock = (float)($_POST['current_stock'] ?? 0);
            if (empty($itemCode)) {
                $itemCode = generateItemCode($categoryId, $pdo);
            }

            $image = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = uploadFile($_FILES['image'], UPLOAD_PATH);
            }

            $stmt = $pdo->prepare("INSERT INTO inventory_items (item_code, name, description, category_id, unit_id, current_stock, min_stock, max_stock, unit_cost, supplier_id, expiration_date, storage_location, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemCode, $name, $description, $categoryId, $unitId, $currentStock, $minStock, $maxStock, $unitCost, $supplierId, $expirationDate, $storageLocation, $image, $status]);

            $itemId = $pdo->lastInsertId();

            if ($currentStock > 0) {
                $stmt = $pdo->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, reference_type, quantity, previous_stock, new_stock, unit_cost, total_value, remarks, created_by) VALUES (?, 'Stock In', 'Initial', ?, 0, ?, ?, ?, 'Initial stock entry', ?)");
                $stmt->execute([$itemId, $currentStock, $currentStock, $unitCost, $currentStock * $unitCost, $userId]);
            }

            logAudit($pdo, 'Insert', 'Inventory', 'Added new item: ' . $name);
            respond(true, 'Item added successfully');
        } else {
            $stmt = $pdo->prepare("UPDATE inventory_items SET name=?, description=?, category_id=?, unit_id=?, min_stock=?, max_stock=?, unit_cost=?, supplier_id=?, expiration_date=?, storage_location=?, status=? WHERE id=?");
            $stmt->execute([$name, $description, $categoryId, $unitId, $minStock, $maxStock, $unitCost, $supplierId, $expirationDate, $storageLocation, $status, $id]);

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = uploadFile($_FILES['image'], UPLOAD_PATH);
                if ($image) {
                    $stmt = $pdo->prepare("UPDATE inventory_items SET image=? WHERE id=?");
                    $stmt->execute([$image, $id]);
                }
            }

            logAudit($pdo, 'Update', 'Inventory', 'Updated item: ' . $name);
            respond(true, 'Item updated successfully');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT name FROM inventory_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) respond(false, 'Item not found');

        // Delete related records first
        $pdo->prepare("DELETE FROM inventory_transactions WHERE item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stock_in WHERE item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stock_out WHERE item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM batches WHERE item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM production_request_items WHERE item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM borrow_records WHERE item_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->execute([$id]);

        logAudit($pdo, 'Delete', 'Inventory', 'Deleted item: ' . ($item['name'] ?? 'Unknown'));
        respond(true, 'Item deleted successfully');
    }

    if ($action === 'update_stock') {
        $id = (int)($_POST['id'] ?? 0);
        $newStock = (float)($_POST['current_stock'] ?? 0);

        $stmt = $pdo->prepare("SELECT current_stock, unit_cost, name FROM inventory_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item) {
            $diff = $newStock - $item['current_stock'];
            $type = $diff >= 0 ? 'Stock In' : 'Stock Out';
            $qty = abs($diff);

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = ? WHERE id = ?");
            $stmt->execute([$newStock, $id]);

            if ($qty > 0) {
                $stmt = $pdo->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, reference_type, quantity, previous_stock, new_stock, unit_cost, total_value, remarks, created_by) VALUES (?, ?, 'Adjustment', ?, ?, ?, ?, ?, 'Stock adjustment', ?)");
                $stmt->execute([$id, $type, $qty, $item['current_stock'], $newStock, $item['unit_cost'], $qty * $item['unit_cost'], $userId]);
            }

            logAudit($pdo, 'Update', 'Inventory', 'Adjusted stock for: ' . $item['name'] . ' (' . $item['current_stock'] . ' -> ' . $newStock . ')');
            respond(true, 'Stock updated successfully');
        }
    }
}

// AJAX handler for get_item
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_item') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($item);
    exit;
}

// AJAX handler for view_item
if (isset($_GET['ajax']) && $_GET['ajax'] === 'view_item') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT i.*, c.category_name, c.category_type, u.unit_name, u.abbreviation, s.company_name as supplier_name
                           FROM inventory_items i
                           LEFT JOIN categories c ON i.category_id = c.id
                           LEFT JOIN units u ON i.unit_id = u.id
                           LEFT JOIN suppliers s ON i.supplier_id = s.id
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo '<div class="text-center py-8 text-gray-500">Item not found.</div>';
        exit;
    }

    $txnStmt = $pdo->prepare("SELECT it.*, u.full_name as user_name FROM inventory_transactions it LEFT JOIN users u ON it.created_by = u.id WHERE it.item_id = ? ORDER BY it.created_at DESC LIMIT 20");
    $txnStmt->execute([$id]);
    $transactions = $txnStmt->fetchAll();

    $expStatus = getExpirationStatus($item['expiration_date']);
    $lowStock = $item['current_stock'] <= $item['min_stock'];
    $totalValue = $item['current_stock'] * $item['unit_cost'];
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2">
            <h4 class="text-xl font-bold text-gray-900 mb-2"><?php echo h($item['name']); ?></h4>
            <?php if ($item['description']): ?>
            <p class="text-sm text-gray-600 mb-4"><?php echo h($item['description']); ?></p>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Item Code</p>
                    <p class="text-sm font-mono font-medium text-gray-800"><?php echo h($item['item_code']); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Category</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo h($item['category_name'] ?? 'N/A'); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Unit</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo h($item['unit_name'] ?? 'N/A'); ?> (<?php echo h($item['abbreviation'] ?? ''); ?>)</p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Current Stock</p>
                    <p class="text-lg font-bold <?php echo $lowStock ? 'text-red-600' : 'text-emerald-600'; ?>"><?php echo formatNumber($item['current_stock']); ?> <?php echo h($item['abbreviation'] ?? ''); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Min / Max Stock</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo formatNumber($item['min_stock']); ?> / <?php echo formatNumber($item['max_stock']); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Unit Cost</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo formatCurrency($item['unit_cost']); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Total Value</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo formatCurrency($totalValue); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Supplier</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo h($item['supplier_name'] ?? 'N/A'); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Storage Location</p>
                    <p class="text-sm font-medium text-gray-800"><?php echo h($item['storage_location'] ?? 'N/A'); ?></p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Expiration</p>
                    <p class="text-sm font-medium">
                        <?php if ($item['expiration_date']): ?>
                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo $expStatus['class']; ?>"><?php echo $expStatus['label']; ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">N/A</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg">
                    <p class="text-xs text-gray-500">Status</p>
                    <p class="text-sm font-medium">
                        <span class="px-2 py-0.5 text-xs rounded-full <?php echo $item['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo $item['status']; ?></span>
                    </p>
                </div>
            </div>

            <h5 class="font-semibold text-gray-800 mb-3">Transaction History</h5>
            <?php if (count($transactions) > 0): ?>
            <div class="overflow-x-auto max-h-64 overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Qty</th>
                            <th class="px-3 py-2">Prev</th>
                            <th class="px-3 py-2">New</th>
                            <th class="px-3 py-2">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td class="px-3 py-2 text-xs"><?php echo formatDateTime($t['created_at'], 'M d, Y h:i A'); ?></td>
                            <td class="px-3 py-2">
                                <span class="px-1.5 py-0.5 text-xs rounded-full <?php echo $t['transaction_type'] == 'Stock In' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>"><?php echo $t['transaction_type']; ?></span>
                            </td>
                            <td class="px-3 py-2"><?php echo formatNumber($t['quantity']); ?></td>
                            <td class="px-3 py-2"><?php echo formatNumber($t['previous_stock']); ?></td>
                            <td class="px-3 py-2"><?php echo formatNumber($t['new_stock']); ?></td>
                            <td class="px-3 py-2 text-xs"><?php echo h($t['user_name'] ?? 'System'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-400 text-sm">No transactions yet.</p>
            <?php endif; ?>
        </div>

        <div>
            <?php if ($item['image']): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 mb-1">Item Image</p>
                <img src="uploads/<?php echo h($item['image']); ?>" class="w-full rounded-lg border border-gray-200">
            </div>
            <?php endif; ?>

            <div class="flex space-x-2">
                <button onclick="editItem(<?php echo $item['id']; ?>)" class="flex-1 px-3 py-2 bg-amber-500 text-white rounded-lg text-sm hover:bg-amber-600 transition-colors"><i class="fas fa-edit mr-1"></i> Edit</button>
            </div>
        </div>
    </div>
    <?php
    exit;
}

// Get filter parameters
$catFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$query = "SELECT i.*, c.category_name, u.unit_name, u.abbreviation, s.company_name as supplier_name FROM inventory_items i LEFT JOIN categories c ON i.category_id = c.id LEFT JOIN units u ON i.unit_id = u.id LEFT JOIN suppliers s ON i.supplier_id = s.id WHERE 1=1";
$params = [];

if (!empty($catFilter)) {
    $query .= " AND i.category_id = ?";
    $params[] = $catFilter;
}
if (!empty($statusFilter)) {
    $query .= " AND i.status = ?";
    $params[] = $statusFilter;
}
if (!empty($searchFilter)) {
    $query .= " AND (i.name LIKE ? OR i.item_code LIKE ?)";
    $params[] = "%$searchFilter%";
    $params[] = "%$searchFilter%";
}

$query .= " ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
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

<!-- Page Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <select id="filterCategory" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter == $cat['id'] ? 'selected' : ''; ?>><?php echo h($cat['category_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="Active" <?php echo $statusFilter == 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Inactive" <?php echo $statusFilter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <input type="text" id="filterSearch" placeholder="Search items..." value="<?php echo h($searchFilter); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onkeyup="applyFilters()">
    </div>
    <div class="flex items-center gap-2">
        <button onclick="printPage('inventoryTable', 'Inventory Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openAddModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add Item
        </button>
    </div>
</div>

<!-- Items Table -->
<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="inventoryTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Min/Max</th>
                    <th class="px-4 py-3">Unit Cost</th>
                    <th class="px-4 py-3">Value</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Expiration</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $item):
                    $expStatus = getExpirationStatus($item['expiration_date']);
                    $lowStock = $item['current_stock'] <= $item['min_stock'] && $item['current_stock'] > 0;
                ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($item['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($item['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($item['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <span class="font-semibold <?php echo $lowStock ? 'text-red-600' : 'text-emerald-600'; ?>">
                            <?php echo formatNumber($item['current_stock']); ?>
                        </span>
                        <span class="text-gray-400 text-xs"><?php echo h($item['abbreviation'] ?? ''); ?></span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?php echo formatNumber($item['min_stock']); ?> / <?php echo formatNumber($item['max_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($item['unit_cost']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo formatCurrency($item['current_stock'] * $item['unit_cost']); ?></td>
                    <td class="px-4 py-3"><?php echo h($item['supplier_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <?php if ($item['expiration_date']): ?>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $expStatus['class']; ?>">
                            <?php echo $expStatus['label']; ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $item['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $item['status']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-1">
                            <button onclick="viewItem(<?php echo $item['id']; ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i class="fas fa-eye"></i></button>
                            <button onclick="editItem(<?php echo $item['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></button>
                            <button onclick="quickStock(<?php echo $item['id']; ?>, '<?php echo h(addslashes($item['name'])); ?>', <?php echo $item['current_stock']; ?>)" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg" title="Quick Stock Update"><i class="fas fa-cubes"></i></button>
                            <button onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo h(addslashes($item['name'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($items) == 0): ?>
                <tr><td colspan="11" class="px-4 py-8 text-center text-gray-500">No inventory items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="itemModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('itemModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add New Item</h3>
                <button onclick="closeModal('itemModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6" id="itemForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="itemId" value="">
                <?php echo csrfField(); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Code</label>
                        <input type="text" name="item_code" id="itemCode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" readonly>
                        <p class="text-xs text-gray-400 mt-1">Auto-generated</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="itemName" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Enter item name">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="itemDescription" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional description"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category_id" id="itemCategory" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo h($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                        <select name="unit_id" id="itemUnit" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                            <option value="<?php echo $unit['id']; ?>"><?php echo h($unit['unit_name']); ?> (<?php echo h($unit['abbreviation']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Stock</label>
                        <input type="number" name="current_stock" id="itemStock" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Stock</label>
                        <input type="number" name="min_stock" id="itemMinStock" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Maximum Stock</label>
                        <input type="number" name="max_stock" id="itemMaxStock" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                        <input type="number" name="unit_cost" id="itemCost" step="0.01" value="0.00" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <select name="supplier_id" id="itemSupplier" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo $sup['id']; ?>"><?php echo h($sup['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date</label>
                        <input type="date" name="expiration_date" id="itemExpiration" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Storage Location</label>
                        <input type="text" name="storage_location" id="itemLocation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., Shelf A-1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="itemStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                        <input type="file" name="image" accept="image/*" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <div id="imagePreview" class="mt-2 hidden">
                            <img src="" class="w-16 h-16 rounded object-cover border">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('itemModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('viewModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">Item Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div id="viewContent" class="p-6">
                <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stock Update Modal -->
<div id="stockModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('stockModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Quick Stock Update</h3>
                <button onclick="closeModal('stockModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="id" id="stockItemId" value="">
                <?php echo csrfField(); ?>
                <p class="text-sm text-gray-600 mb-4" id="stockItemName"></p>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Stock Quantity <span class="text-red-500">*</span></label>
                <input type="number" name="current_stock" id="stockNewValue" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('stockModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($viewId > 0): ?>
<script>
$(document).ready(function() {
    viewItem(<?php echo $viewId; ?>);
});
</script>
<?php endif; ?>

<script>
function openAddModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add New Item';
    document.getElementById('itemForm').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('itemCode').readOnly = false;
    document.getElementById('itemCode').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    document.getElementById('itemModal').classList.remove('hidden');
}

function editItem(id) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Item';
    document.getElementById('itemCode').readOnly = true;
    document.getElementById('imagePreview').classList.add('hidden');

    $.ajax({
        url: 'ajax/get_item.php?id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(item) {
            if (!item || item.error) {
                showToast('Failed to load item data', 'error');
                return;
            }
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemCode').value = item.item_code || '';
            document.getElementById('itemName').value = item.name || '';
            document.getElementById('itemDescription').value = item.description || '';
            document.getElementById('itemCategory').value = item.category_id || '';
            document.getElementById('itemUnit').value = item.unit_id || '';
            document.getElementById('itemStock').value = item.current_stock || 0;
            document.getElementById('itemMinStock').value = item.min_stock || 0;
            document.getElementById('itemMaxStock').value = item.max_stock || 0;
            document.getElementById('itemCost').value = item.unit_cost || 0;
            document.getElementById('itemSupplier').value = item.supplier_id || '';
            document.getElementById('itemExpiration').value = item.expiration_date || '';
            document.getElementById('itemLocation').value = item.storage_location || '';
            document.getElementById('itemStatus').value = item.status || 'Active';
            document.getElementById('itemModal').classList.remove('hidden');
        },
        error: function() {
            showToast('Failed to load item data', 'error');
        }
    });
}

function viewItem(id) {
    document.getElementById('viewModal').classList.remove('hidden');
    document.getElementById('viewContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
    $.ajax({
        url: 'ajax/view_item.php?id=' + id,
        method: 'GET',
        success: function(html) {
            document.getElementById('viewContent').innerHTML = html;
        },
        error: function() {
            document.getElementById('viewContent').innerHTML = '<div class="text-center py-8 text-red-500">Failed to load item details.</div>';
        }
    });
}

function quickStock(id, name, currentStock) {
    document.getElementById('stockItemId').value = id;
    document.getElementById('stockItemName').textContent = 'Item: ' + name + ' (Current: ' + currentStock + ')';
    document.getElementById('stockNewValue').value = currentStock;
    document.getElementById('stockModal').classList.remove('hidden');
}

function deleteItem(id, name) {
    showConfirm('Delete "' + name + '"?', function() {
        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { action: 'delete', id: id, csrf_token: csrfToken },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    hideLoading();
                    showToast(resp.message || 'Deleted successfully', 'success');
                    location.reload();
                } else {
                    hideLoading();
                    showToast(resp && resp.message || 'Delete failed', 'error');
                }
            },
            error: function(xhr) {
                hideLoading();
                try {
                    var r = JSON.parse(xhr.responseText);
                    showToast(r.message || 'Delete failed', 'error');
                } catch(e) {
                    showToast('Delete failed', 'error');
                }
            }
        });
    });
}

function reinitDataTables() {
    $('.table-container .datatable').each(function() {
        if ($.fn.dataTable.isDataTable(this)) $(this).DataTable().destroy();
    });
    $('.datatable').each(function() {
        if (!$.fn.dataTable.isDataTable(this)) {
            var $t = $(this);
            if ($t.find('tbody tr:first td[colspan]').length > 0) return;
            $t.DataTable({
                responsive: true, pageLength: 25,
                lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
                dom: '<"dt-toolbar"lf>t<"dt-footer"ip>',
                language: { search: 'Search:', searchPlaceholder: 'Type to search...', lengthMenu: 'Show _MENU_ entries', info: 'Showing _START_ to _END_ of _TOTAL_ entries', infoEmpty: 'No entries found', infoFiltered: '(filtered from _MAX_ total entries)', paginate: { first: '«', previous: '‹', next: '›', last: '»' } },
                order: []
            });
        }
    });
}

function applyFilters() {
    var cat = document.getElementById('filterCategory').value;
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('filterSearch').value;
    var url = 'index?page=inventory';
    if (cat) url += '&category=' + cat;
    if (status) url += '&status=' + status;
    if (search) url += '&search=' + encodeURIComponent(search);
    window.location.href = url;
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// AJAX form submission — no page reload
$(document).on('submit', '#itemForm', function(e) {
    e.preventDefault();
    showLoading();
    var formData = new FormData(this);
    var isEdit = formData.get('action') === 'edit';
    var modal = document.getElementById('itemModal');
    if (modal) modal.classList.add('hidden');

    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function() {
            // Reload only the table section via AJAX
            $.get(window.location.href, function(html) {
                var newTable = $(html).find('.table-container').html();
                if (newTable) {
                    $('.table-container').html(newTable);
                    // Re-init DataTables
                    $('.table-container .datatable').each(function() {
                        if ($.fn.dataTable.isDataTable(this)) $(this).DataTable().destroy();
                    });
                    $('.datatable').each(function() {
                        if (!$.fn.dataTable.isDataTable(this)) {
                            var $t = $(this);
                            if ($t.find('tbody tr:first td[colspan]').length > 0) return;
                            $t.DataTable({
                                pageLength: 25,
                                lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'All']],
                                dom: '<"dt-toolbar"lf>t<"dt-footer"ip>',
                                language: { search: 'Search:', searchPlaceholder: 'Type to search...', lengthMenu: 'Show _MENU_ entries', info: 'Showing _START_ to _END_ of _TOTAL_ entries', infoEmpty: 'No entries found', infoFiltered: '(filtered from _MAX_ total entries)', paginate: { first: '«', previous: '‹', next: '›', last: '»' } },
                                order: []
                            });
                        }
                    });
                }
                hideLoading();
                showToast(isEdit ? 'Item updated successfully' : 'Item added successfully', 'success');
            }).fail(function() {
                hideLoading();
                location.reload();
            });
        },
        error: function() {
            hideLoading();
            showToast('Operation failed', 'error');
        }
    });
});

// Close modals on escape
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
