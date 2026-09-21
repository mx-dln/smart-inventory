<?php
$page = 'periodic-reports';

// Determine period date range
$period = $_GET['period'] ?? 'monthly';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$customFrom = $fromDate;
$customTo = $toDate;

if ($period === 'weekly') {
    $fromDate = date('Y-m-d', strtotime('monday this week'));
    $toDate = date('Y-m-d');
} elseif ($period === 'monthly') {
    $fromDate = date('Y-m-01');
    $toDate = date('Y-m-d');
} elseif ($period === 'quarterly') {
    $fromDate = date('Y-m-d', strtotime('first day of this quarter'));
    $toDate = date('Y-m-d');
} elseif ($period === 'yearly') {
    $fromDate = date('Y-01-01');
    $toDate = date('Y-m-d');
} elseif ($period === 'custom') {
    $fromDate = $customFrom ?: date('Y-m-01');
    $toDate = $customTo ?: date('Y-m-d');
} else {
    $fromDate = date('Y-m-01');
    $toDate = date('Y-m-d');
}

// Get all active items with movement data in the period
$stmt = $pdo->prepare("
    SELECT i.id, i.item_code, i.name, i.unit_cost, i.current_stock, i.min_stock,
           c.category_name, u.abbreviation,
           (SELECT COALESCE(SUM(quantity),0) FROM stock_in si WHERE si.item_id=i.id AND DATE(si.date_received) BETWEEN ? AND ?) as total_in,
           (SELECT COALESCE(SUM(quantity),0) FROM stock_out so WHERE so.item_id=i.id AND DATE(so.date_released) BETWEEN ? AND ?) as total_out,
           (SELECT COALESCE(SUM(quantity),0) FROM inventory_transactions it WHERE it.item_id=i.id AND it.transaction_type='Stock In' AND DATE(it.created_at) < ?) as stock_before_period
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN units u ON i.unit_id = u.id
    WHERE i.status = 'Active'
    ORDER BY c.category_type, i.name
");
$stmt->execute([$fromDate, $toDate, $fromDate, $toDate, $fromDate]);
$items = $stmt->fetchAll();

// Summary totals
$totalBeginning = 0;
$totalIn = 0;
$totalOut = 0;
$totalValue = 0;
foreach ($items as $it) {
    $totalBeginning += (float)$it['stock_before_period'];
    $totalIn += (float)$it['total_in'];
    $totalOut += (float)$it['total_out'];
    $totalValue += (float)$it['current_stock'] * (float)$it['unit_cost'];
}

$periodLabel = ucfirst($period) . ' Inventory Report';
?>
<style>
html.dark .bg-white.rounded-xl { background-color: #1e293b !important; border-color: #334155 !important; }
</style>

<div class="mb-6 no-print">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h2 class="text-xl font-bold text-gray-800"><i class="fas fa-file-invoice text-emerald-500 mr-2"></i> Periodic Inventory Report</h2>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.location.href='index?page=periodic-reports&period=weekly'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $period==='weekly' ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">Weekly</button>
            <button onclick="window.location.href='index?page=periodic-reports&period=monthly'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $period==='monthly' ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">Monthly</button>
            <button onclick="window.location.href='index?page=periodic-reports&period=quarterly'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $period==='quarterly' ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">Quarterly</button>
            <button onclick="window.location.href='index?page=periodic-reports&period=yearly'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $period==='yearly' ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">Yearly</button>
            <button onclick="document.getElementById('customPeriod').classList.toggle('hidden')" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $period==='custom' ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">Custom</button>
            <button onclick="printPage('periodicReportTable', '<?php echo h($periodLabel); ?>')" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors"><i class="fas fa-print mr-1"></i> Print</button>
        </div>
    </div>

    <!-- Custom date range -->
    <div id="customPeriod" class="hidden mt-3 p-4 bg-white rounded-xl border border-gray-200">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="page" value="periodic-reports">
            <input type="hidden" name="period" value="custom">
            <div><label class="block text-xs font-medium text-gray-500 mb-1">From</label><input type="date" name="from" value="<?php echo h($customFrom); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-500 mb-1">To</label><input type="date" name="to" value="<?php echo h($customTo); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Generate</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Beginning Stock</p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatNumber($totalBeginning); ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Stock In</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1"><?php echo formatNumber($totalIn); ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Stock Out</p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?php echo formatNumber($totalOut); ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Inventory Value</p>
        <p class="text-2xl font-bold text-purple-600 mt-1"><?php echo formatCurrency($totalValue); ?></p>
    </div>
</div>

<!-- Report Period Info -->
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
    <p class="text-sm text-gray-600"><i class="fas fa-calendar-alt text-emerald-500 mr-2"></i><strong>Report Period:</strong> <?php echo formatDate($fromDate); ?> to <?php echo formatDate($toDate); ?> (<?php echo ucfirst($period); ?>)</p>
</div>

<!-- Periodic Inventory Table -->
<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="periodicReportTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item Code</th>
                    <th class="px-4 py-3">Item Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Beginning</th>
                    <th class="px-4 py-3">Stock In</th>
                    <th class="px-4 py-3">Stock Out</th>
                    <th class="px-4 py-3">Current</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($items as $it):
                    $begin = (float)$it['stock_before_period'];
                    $inQty = (float)$it['total_in'];
                    $outQty = (float)$it['total_out'];
                    $cur = (float)$it['current_stock'];
                ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($it['item_code']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($it['name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($it['category_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($begin); ?></td>
                    <td class="px-4 py-3 text-emerald-600 font-medium"><?php echo formatNumber($inQty); ?></td>
                    <td class="px-4 py-3 text-red-600 font-medium"><?php echo formatNumber($outQty); ?></td>
                    <td class="px-4 py-3 font-semibold"><?php echo formatNumber($cur); ?></td>
                    <td class="px-4 py-3"><?php echo h($it['abbreviation'] ?? ''); ?></td>
                    <td class="px-4 py-3"><?php echo formatCurrency($cur * (float)$it['unit_cost']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($items) === 0): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No inventory items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>