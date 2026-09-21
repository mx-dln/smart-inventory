<?php
$page = 'reports';
$type = $_GET['type'] ?? 'inventory';
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');
$categoryFilter = $_GET['category'] ?? '';
$supplierFilter = $_GET['supplier'] ?? '';

// Handle CSV export - MUST be before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($type === 'inventory') {
        fputcsv($output, ['Item Code', 'Name', 'Category', 'Unit', 'Stock', 'Min Stock', 'Max Stock', 'Unit Cost', 'Total Value', 'Status'], ',', '"', '');
        $q = "SELECT i.*, c.category_name, u.unit_name FROM inventory_items i LEFT JOIN categories c ON i.category_id=c.id LEFT JOIN units u ON i.unit_id=u.id WHERE 1=1";
        $params = [];
        if ($categoryFilter) { $q .= " AND i.category_id=?"; $params[]=$categoryFilter; }
        if ($supplierFilter) { $q .= " AND i.supplier_id=?"; $params[]=$supplierFilter; }
        $q .= " ORDER BY i.name";
        $stmt = $pdo->prepare($q); $stmt->execute($params);
        foreach ($stmt as $r) {
            fputcsv($output, [$r['item_code'], $r['name'], $r['category_name'], $r['unit_name'], $r['current_stock'], $r['min_stock'], $r['max_stock'], $r['unit_cost'], $r['current_stock']*$r['unit_cost'], $r['status']], ',', '"', '');
        }
    } elseif ($type === 'movement') {
        fputcsv($output, ['Date', 'Item', 'Type', 'Reference', 'Qty', 'Previous', 'New', 'Unit Cost', 'Total', 'User'], ',', '"', '');
        $q = "SELECT it.*, i.name as item_name, u.full_name FROM inventory_transactions it LEFT JOIN inventory_items i ON it.item_id=i.id LEFT JOIN users u ON it.created_by=u.id WHERE DATE(it.created_at) BETWEEN ? AND ?";
        $stmt = $pdo->prepare($q); $stmt->execute([$fromDate, $toDate]);
        foreach ($stmt as $r) {
            fputcsv($output, [$r['created_at'], $r['item_name'], $r['transaction_type'], $r['reference_type'], $r['quantity'], $r['previous_stock'], $r['new_stock'], $r['unit_cost'], $r['total_value'], $r['full_name']], ',', '"', '');
        }
    } elseif ($type === 'low-stock') {
        fputcsv($output, ['Item Code', 'Name', 'Category', 'Current Stock', 'Min Stock', 'Unit'], ',', '"', '');
        $items = getLowStockItems($pdo);
        foreach ($items as $r) {
            fputcsv($output, [$r['item_code'], $r['name'], $r['category_name'], $r['current_stock'], $r['min_stock'], $r['unit_name']], ',', '"', '');
        }
    } elseif ($type === 'expired') {
        fputcsv($output, ['Item Code', 'Name', 'Category', 'Expiration Date', 'Days Status', 'Stock'], ',', '"', '');
        foreach (getExpiredItems($pdo) as $r) {
            fputcsv($output, [$r['item_code'], $r['name'], $r['category_name'], $r['expiration_date'], 'Expired', $r['current_stock']], ',', '"', '');
        }
        foreach (getNearExpiryItems($pdo, 30) as $r) {
            fputcsv($output, [$r['item_code'], $r['name'], $r['category_name'], $r['expiration_date'], $r['days_remaining'].' days left', $r['current_stock']], ',', '"', '');
        }
    } elseif ($type === 'transactions') {
        fputcsv($output, ['Date', 'Type', 'Reference', 'Item', 'Qty', 'Value'], ',', '"', '');
        $q = "SELECT DATE(created_at) as tdate, transaction_type, reference_type, COUNT(*) as tcount, SUM(quantity) as tqty, SUM(total_value) as tval FROM inventory_transactions WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at), transaction_type ORDER BY tdate DESC";
        $stmt = $pdo->prepare($q); $stmt->execute([$fromDate, $toDate]);
        foreach ($stmt as $r) {
            fputcsv($output, [$r['tdate'], $r['transaction_type'], $r['reference_type'], $r['tcount'].' items', $r['tqty'], $r['tval']], ',', '"', '');
        }
    }
    fclose($output);
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Get data for display
if ($type === 'inventory') {
    $q = "SELECT i.*, c.category_name, u.unit_name, s.company_name as supplier_name FROM inventory_items i LEFT JOIN categories c ON i.category_id=c.id LEFT JOIN units u ON i.unit_id=u.id LEFT JOIN suppliers s ON i.supplier_id=s.id WHERE 1=1";
    $params = [];
    if ($categoryFilter) { $q .= " AND i.category_id=?"; $params[]=$categoryFilter; }
    if ($supplierFilter) { $q .= " AND i.supplier_id=?"; $params[]=$supplierFilter; }
    $q .= " ORDER BY i.name";
    $stmt = $pdo->prepare($q); $stmt->execute($params); $data = $stmt->fetchAll();

    $totalItems = count($data);
    $totalQty = array_sum(array_column($data, 'current_stock'));
    $totalVal = array_sum(array_map(function($i) { return $i['current_stock'] * $i['unit_cost']; }, $data));
} elseif ($type === 'movement') {
    $q = "SELECT it.*, i.name as item_name, u.full_name FROM inventory_transactions it LEFT JOIN inventory_items i ON it.item_id=i.id LEFT JOIN users u ON it.created_by=u.id WHERE DATE(it.created_at) BETWEEN ? AND ? ORDER BY it.created_at DESC";
    $stmt = $pdo->prepare($q); $stmt->execute([$fromDate, $toDate]); $data = $stmt->fetchAll();

    $totalMvmts = count($data);
    $totalIn = array_sum(array_map(function($r) { return $r['transaction_type'] === 'Stock In' ? abs($r['quantity']) : 0; }, $data));
    $totalOut = array_sum(array_map(function($r) { return $r['transaction_type'] === 'Stock Out' ? abs($r['quantity']) : 0; }, $data));
} elseif ($type === 'low-stock') {
    $data = getLowStockItems($pdo);
    $totalLow = count($data);
    $totalShort = array_sum(array_map(function($r) { return max(0, $r['min_stock'] - $r['current_stock']); }, $data));
} elseif ($type === 'expired') {
    $data1 = getExpiredItems($pdo);
    $data2 = getNearExpiryItems($pdo, 30);
    $data = array_merge($data1, $data2);
    $totalExpired = count($data1);
    $totalNear = count($data2);
} elseif ($type === 'transactions') {
    $q = "SELECT DATE(created_at) as tdate, transaction_type, reference_type, COUNT(*) as tcount, SUM(quantity) as tqty, SUM(total_value) as tval FROM inventory_transactions WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at), transaction_type ORDER BY tdate DESC";
    $stmt = $pdo->prepare($q); $stmt->execute([$fromDate, $toDate]); $data = $stmt->fetchAll();

    $totalDays = count($data);
    $totalTransQty = array_sum(array_column($data, 'tqty'));
    $totalTransVal = array_sum(array_column($data, 'tval'));
}
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

<!-- Report Type Tabs -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="index?page=reports&type=inventory" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $type === 'inventory' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
        <i class="fas fa-boxes mr-1"></i> Inventory
    </a>
    <a href="index?page=reports&type=movement" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $type === 'movement' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
        <i class="fas fa-exchange-alt mr-1"></i> Movement
    </a>
    <a href="index?page=reports&type=low-stock" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $type === 'low-stock' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
        <i class="fas fa-exclamation-triangle mr-1"></i> Low Stock
    </a>
    <a href="index?page=reports&type=expired" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $type === 'expired' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
        <i class="fas fa-clock mr-1"></i> Expired
    </a>
    <a href="index?page=reports&type=transactions" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $type === 'transactions' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
        <i class="fas fa-chart-bar mr-1"></i> Transactions
    </a>
</div>

<!-- Filter Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" action="index.php" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="reports">
        <input type="hidden" name="type" value="<?php echo h($type); ?>">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="<?php echo $fromDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="<?php echo $toDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <?php if (in_array($type, ['inventory'])): ?>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
            <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?php echo h($cat['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
            <select name="supplier" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $sup): ?>
                <option value="<?php echo $sup['id']; ?>" <?php echo $supplierFilter == $sup['id'] ? 'selected' : ''; ?>><?php echo h($sup['company_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
            <a href="index?page=reports&type=<?php echo h($type); ?>" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors"><i class="fas fa-undo mr-1"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Export Buttons -->
<div class="flex flex-wrap gap-2 mb-6">
    <a href="index?page=reports&type=<?php echo h($type); ?>&from=<?php echo $fromDate; ?>&to=<?php echo $toDate; ?>&category=<?php echo $categoryFilter; ?>&supplier=<?php echo $supplierFilter; ?>&export=csv" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
        <i class="fas fa-file-csv mr-1"></i> Export CSV
    </a>
    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
        <i class="fas fa-print mr-1"></i> Print
    </button>
</div>

<!-- Summary Cards -->
<?php if ($type === 'inventory'): ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Items</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo $totalItems; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Stock Qty</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo formatNumber($totalQty); ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Inventory Value</p>
        <p class="text-2xl font-bold text-emerald-600"><?php echo formatCurrency($totalVal); ?></p>
    </div>
</div>
<?php elseif ($type === 'movement'): ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Movements</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo $totalMvmts; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Stock In</p>
        <p class="text-2xl font-bold text-emerald-600"><?php echo formatNumber($totalIn); ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Stock Out</p>
        <p class="text-2xl font-bold text-red-600"><?php echo formatNumber($totalOut); ?></p>
    </div>
</div>
<?php elseif ($type === 'low-stock'): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Low Stock Items</p>
        <p class="text-2xl font-bold text-orange-600"><?php echo $totalLow; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Reorder Qty Needed</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo formatNumber($totalShort); ?></p>
    </div>
</div>
<?php elseif ($type === 'expired'): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Expired Items</p>
        <p class="text-2xl font-bold text-red-600"><?php echo $totalExpired; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Near Expiry (30 days)</p>
        <p class="text-2xl font-bold text-yellow-600"><?php echo $totalNear; ?></p>
    </div>
</div>
<?php elseif ($type === 'transactions'): ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Transaction Days</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo $totalDays; ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Qty Moved</p>
        <p class="text-2xl font-bold text-gray-800"><?php echo formatNumber($totalTransQty); ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
        <p class="text-sm text-gray-500">Total Value</p>
        <p class="text-2xl font-bold text-emerald-600"><?php echo formatCurrency($totalTransVal); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Report Tables -->
<div class="table-container">
    <div class="overflow-x-auto">
        <?php if ($type === 'inventory'): ?>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Stock</th>
                    <th class="px-4 py-3">Min</th>
                    <th class="px-4 py-3">Max</th>
                    <th class="px-4 py-3">Unit Cost</th>
                    <th class="px-4 py-3">Total Value</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($r['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['unit_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3 font-semibold <?php echo $r['current_stock'] <= $r['min_stock'] ? 'text-red-600' : 'text-emerald-600'; ?>"><?php echo formatNumber($r['current_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['min_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['max_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($r['unit_cost']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo formatCurrency($r['current_stock'] * $r['unit_cost']); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full <?php echo $r['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo $r['status']; ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($data) == 0): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No inventory items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($type === 'movement'): ?>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Previous</th>
                    <th class="px-4 py-3">New</th>
                    <th class="px-4 py-3">Unit Cost</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">User</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo formatDateTime($r['created_at']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['item_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $r['transaction_type'] == 'Stock In' ? 'bg-emerald-100 text-emerald-800' : ($r['transaction_type'] == 'Stock Out' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo $r['transaction_type']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs"><?php echo h($r['reference_type'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3 font-semibold"><?php echo formatNumber($r['quantity']); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['previous_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['new_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($r['unit_cost']); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($r['total_value']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['full_name'] ?? 'System'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($data) == 0): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No movements found for the selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($type === 'low-stock'): ?>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Current Stock</th>
                    <th class="px-4 py-3">Min Stock</th>
                    <th class="px-4 py-3">Shortage</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $r): ?>
                <?php $shortage = max(0, $r['min_stock'] - $r['current_stock']); ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($r['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['company_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3 font-semibold text-red-600"><?php echo formatNumber($r['current_stock']); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['min_stock']); ?></td>
                    <td class="px-4 py-3 font-medium text-orange-600"><?php echo formatNumber($shortage); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['unit_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">Low Stock</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($data) == 0): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">All items are sufficiently stocked.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($type === 'expired'): ?>
        <?php
        $expiredItems = getExpiredItems($pdo);
        $nearExpiryItems = getNearExpiryItems($pdo, 30);
        ?>
        <?php if (count($expiredItems) > 0): ?>
        <div class="px-4 py-3 bg-red-50 border-b border-red-100">
            <h4 class="text-sm font-semibold text-red-700"><i class="fas fa-times-circle mr-1"></i> Expired Items (<?php echo count($expiredItems); ?>)</h4>
        </div>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Expiration Date</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($expiredItems as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($r['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo formatDate($r['expiration_date']); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Expired</span></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['current_stock']); ?> <?php echo h($r['unit_name'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (count($nearExpiryItems) > 0): ?>
        <div class="px-4 py-3 bg-yellow-50 border-b border-yellow-100 <?php if (count($expiredItems) > 0) echo 'mt-4'; ?>">
            <h4 class="text-sm font-semibold text-yellow-700"><i class="fas fa-clock mr-1"></i> Near Expiry (30 days) - <?php echo count($nearExpiryItems); ?> items</h4>
        </div>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Expiration Date</th>
                    <th class="px-4 py-3">Days Left</th>
                    <th class="px-4 py-3">Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($nearExpiryItems as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($r['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo formatDate($r['expiration_date']); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800"><?php echo $r['days_remaining']; ?> days</span></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['current_stock']); ?> <?php echo h($r['unit_name'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if (count($expiredItems) == 0 && count($nearExpiryItems) == 0): ?>
        <div class="px-4 py-8 text-center text-gray-500">
            <i class="fas fa-check-circle text-3xl text-emerald-400 mb-2"></i>
            <p>No expired or near-expiry items found.</p>
        </div>
        <?php endif; ?>

        <?php elseif ($type === 'transactions'): ?>
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Items</th>
                    <th class="px-4 py-3">Total Qty</th>
                    <th class="px-4 py-3">Total Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $r): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo formatDate($r['tdate']); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $r['transaction_type'] == 'Stock In' ? 'bg-emerald-100 text-emerald-800' : ($r['transaction_type'] == 'Stock Out' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo $r['transaction_type']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs"><?php echo h($r['reference_type'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo $r['tcount']; ?> items</td>
                    <td class="px-4 py-3 font-semibold"><?php echo formatNumber($r['tqty']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo formatCurrency($r['tval']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($data) == 0): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions found for the selected period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
