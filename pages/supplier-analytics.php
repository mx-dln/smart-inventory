<?php
$page = 'supplier-analytics';

$suppliers = $pdo->query("SELECT s.*, 
    (SELECT COUNT(*) FROM stock_in WHERE supplier_id=s.id) as total_orders,
    (SELECT COALESCE(SUM(quantity), 0) FROM stock_in WHERE supplier_id=s.id) as total_items,
    (SELECT COALESCE(AVG(unit_cost), 0) FROM stock_in WHERE supplier_id=s.id) as avg_cost,
    (SELECT COUNT(*) FROM inventory_items WHERE supplier_id=s.id) as supplied_items
    FROM suppliers s ORDER BY s.company_name")->fetchAll();

$stmt = $pdo->query("SELECT supplier_id, COUNT(*) as cnt, COALESCE(AVG(quantity), 0) as avg_qty FROM stock_in GROUP BY supplier_id");
$deliveryData = [];
while ($row = $stmt->fetch()) {
    $deliveryData[$row['supplier_id']] = $row;
}

foreach ($suppliers as &$s) {
    $s['delivery_count'] = $deliveryData[$s['id']]['cnt'] ?? 0;
    $s['avg_delivery_qty'] = $deliveryData[$s['id']]['avg_qty'] ?? 0;
    $s['reliability'] = min(5, max(1, round($s['delivery_count'] / 2 + 1, 1)));
    $s['quality'] = min(5, max(1, round(5 - ($s['total_items'] > 0 ? abs(50 - $s['avg_delivery_qty']) / 50 : 0), 1)));
    $s['overall'] = round(($s['reliability'] + $s['quality']) / 2, 1);
}
unset($s);

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

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Suppliers</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($suppliers); ?></p></div>
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-truck text-blue-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Orders</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo array_sum(array_column($suppliers, 'total_orders')); ?></p></div>
            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center"><i class="fas fa-shopping-cart text-emerald-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Items Supplied</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo array_sum(array_column($suppliers, 'total_items')); ?></p></div>
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center"><i class="fas fa-boxes text-purple-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Avg Overall Rating</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo count($suppliers) > 0 ? round(array_sum(array_column($suppliers, 'overall')) / count($suppliers), 1) : '0'; ?></p></div>
            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center"><i class="fas fa-star text-amber-600"></i></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <!-- Orders by Supplier Chart -->
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Orders by Supplier</h3>
        <canvas id="ordersChart" height="125"></canvas>
    </div>
    <!-- Average Cost Comparison -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Average Cost Comparison</h3>
        <canvas id="costChart" height="125"></canvas>
    </div>
</div>

<!-- Supplier Rankings -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800"><i class="fas fa-trophy text-amber-500 mr-2"></i> Supplier Performance Rankings</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-5 py-3">Rank</th>
                    <th class="px-5 py-3">Supplier</th>
                    <th class="px-5 py-3 text-center">Orders</th>
                    <th class="px-5 py-3 text-center">Items Supplied</th>
                    <th class="px-5 py-3 text-center">Inventory Items</th>
                    <th class="px-5 py-3 text-right">Avg Cost</th>
                    <th class="px-5 py-3 text-center">Reliability</th>
                    <th class="px-5 py-3 text-center">Quality</th>
                    <th class="px-5 py-3 text-center">Overall</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                usort($suppliers, function($a, $b) { return $b['overall'] <=> $a['overall']; });
                $rank = 1;
                foreach ($suppliers as $s):
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold <?php echo $rank <= 3 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'; ?>"><?php echo $rank; ?></span>
                    </td>
                    <td class="px-5 py-3 font-medium text-gray-800"><?php echo h($s['company_name']); ?></td>
                    <td class="px-5 py-3 text-center"><?php echo $s['total_orders']; ?></td>
                    <td class="px-5 py-3 text-center"><?php echo formatNumber($s['total_items']); ?></td>
                    <td class="px-5 py-3 text-center"><?php echo $s['supplied_items']; ?></td>
                    <td class="px-5 py-3 text-right"><?php echo formatCurrency($s['avg_cost']); ?></td>
                    <td class="px-5 py-3 text-center"><?php echo renderStars($s['reliability']); ?></td>
                    <td class="px-5 py-3 text-center"><?php echo renderStars($s['quality']); ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center space-x-1">
                            <span class="font-bold text-lg <?php echo $s['overall'] >= 4 ? 'text-emerald-600' : ($s['overall'] >= 3 ? 'text-amber-600' : 'text-red-600'); ?>"><?php echo $s['overall']; ?></span>
                            <span class="text-xs text-gray-400">/ 5</span>
                        </span>
                    </td>
                </tr>
                <?php $rank++; endforeach; ?>
                <?php if (count($suppliers) == 0): ?>
                <tr><td colspan="9" class="px-5 py-8 text-center text-gray-500">No supplier data available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php function renderStars($rating) {
    $full = floor($rating);
    $half = $rating - $full >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $html = '<span class="inline-flex space-x-0.5">';
    for ($i = 0; $i < $full; $i++) $html .= '<i class="fas fa-star text-amber-400 text-xs"></i>';
    if ($half) $html .= '<i class="fas fa-star-half-alt text-amber-400 text-xs"></i>';
    for ($i = 0; $i < $empty; $i++) $html .= '<i class="far fa-star text-amber-400 text-xs"></i>';
    $html .= '</span>';
    return $html;
} ?>

<style>
html.dark .bg-white.rounded-xl { background-color: #1e293b !important; border-color: #334155 !important; }
html.dark canvas { filter: brightness(0.9); }
</style>
<script>
var suppliersData = <?php echo json_encode($suppliers); ?>;

// Orders by Supplier Chart
new Chart(document.getElementById('ordersChart'), {
    type: 'bar',
    data: {
        labels: suppliersData.map(function(s) { return s.company_name; }),
        datasets: [{
            label: 'Orders',
            data: suppliersData.map(function(s) { return s.total_orders; }),
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { ticks: { maxRotation: 45, font: { size: 10 } } }
        }
    }
});

// Average Cost Chart
new Chart(document.getElementById('costChart'), {
    type: 'bar',
    data: {
        labels: suppliersData.map(function(s) { return s.company_name; }),
        datasets: [{
            label: 'Avg Cost',
            data: suppliersData.map(function(s) { return parseFloat(s.avg_cost) || 0; }),
            backgroundColor: 'rgba(99, 102, 241, 0.6)',
            borderColor: 'rgba(99, 102, 241, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { callback: function(v) { return '₱' + v.toFixed(2); } } },
            y: { ticks: { font: { size: 9 } } }
        }
    }
});
</script>
