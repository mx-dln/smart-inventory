<?php
$page = 'kpi-dashboard';

$stmt = $pdo->query("SELECT COALESCE(SUM(total_cost), 0) FROM stock_out WHERE date_released >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
$cogs = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(AVG(current_stock * unit_cost), 0) FROM inventory_items WHERE status='Active'");
$avgInventory = (float)$stmt->fetchColumn();
$turnover = $avgInventory > 0 ? round($cogs / $avgInventory, 2) : 0;
$idealTurnover = $avgInventory > 0 ? round($cogs / ($avgInventory * 0.5), 2) : 0;

$stmt = $pdo->query("SELECT COALESCE(SUM(current_stock * unit_cost), 0) FROM inventory_items WHERE status='Active'");
$totalValue = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(i.current_stock * i.unit_cost), 0) FROM inventory_items i WHERE i.status='Active' AND i.current_stock > 0 AND (SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE item_id=i.id AND date_released > DATE_SUB(CURDATE(), INTERVAL 180 DAY)) = 0");
$deadStockValue = (float)$stmt->fetchColumn();
$deadStockPct = $totalValue > 0 ? round($deadStockValue / $totalValue * 100, 1) : 0;

$fastMoving = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE (SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE item_id=i.id AND date_released > DATE_SUB(CURDATE(), INTERVAL 90 DAY)) > 10")->fetchColumn();
$slowMoving = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.current_stock > 0 AND (SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE item_id=i.id AND date_released > DATE_SUB(CURDATE(), INTERVAL 90 DAY)) = 0 AND i.status='Active'")->fetchColumn();

$currentYear = date('Y');
$monthlyConsumption = [];
$monthlyConsumptionCost = [];
$monthlyIn = [];
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = date('M', mktime(0, 0, 0, $m, 1));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE YEAR(date_released) = ? AND MONTH(date_released) = ?");
    $stmt->execute([$currentYear, $m]);
    $monthlyConsumption[] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cost), 0) FROM stock_out WHERE YEAR(date_released) = ? AND MONTH(date_released) = ?");
    $stmt->execute([$currentYear, $m]);
    $monthlyConsumptionCost[] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_in WHERE YEAR(date_received) = ? AND MONTH(date_received) = ?");
    $stmt->execute([$currentYear, $m]);
    $monthlyIn[] = (float)$stmt->fetchColumn();
}

$catValues = $pdo->query("SELECT c.category_type, COALESCE(SUM(i.current_stock * i.unit_cost), 0) as val FROM categories c LEFT JOIN inventory_items i ON c.id=i.category_id AND i.status='Active' GROUP BY c.category_type ORDER BY val DESC")->fetchAll();

$topItems = $pdo->query("SELECT i.name, i.current_stock * i.unit_cost as val, i.current_stock, i.unit_cost, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.status='Active' ORDER BY val DESC LIMIT 10")->fetchAll();

$stmt = $pdo->query("SELECT COALESCE(SUM(current_stock * unit_cost), 0) FROM inventory_items WHERE status='Active' AND current_stock <= min_stock");
$lowStockValue = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='Active'");
$totalItems = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='Active' AND current_stock <= min_stock");
$lowStockCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='Active' AND current_stock > max_stock");
$overstockCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE() AND status='Active'");
$expiredCount = (int)$stmt->fetchColumn();

$avgLeadTime = 0;
$stmt = $pdo->query("SELECT COALESCE(AVG(DATEDIFF(date_received, created_at)), 0) FROM stock_in WHERE date_received IS NOT NULL AND created_at IS NOT NULL LIMIT 100");
$avgLeadTime = round((float)$stmt->fetchColumn(), 1);

$catColors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#6366f1', '#84cc16', '#06b6d4'];
$monthLabelColors = ['#ef4444','#f97316','#f59e0b','#84cc16','#10b981','#14b8a6','#06b6d4','#3b82f6','#6366f1','#8b5cf6','#d946ef','#ec4899'];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">KPI Dashboard</h2>
    <p class="text-gray-500 text-sm mt-1">Key performance indicators for inventory management.</p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Turnover Ratio</p>
            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-sync-alt text-indigo-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo formatNumber($turnover, 2); ?></p>
        <p class="text-xs text-gray-400 mt-1">Annual / Ideal: <?php echo formatNumber($idealTurnover, 2); ?></p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Inventory Value</p>
            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-dollar-sign text-emerald-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo formatCurrency($totalValue); ?></p>
        <p class="text-xs text-gray-400 mt-1"><?php echo $totalItems; ?> active items</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Dead Stock %</p>
            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-skull text-red-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $deadStockPct; ?>%</p>
        <p class="text-xs text-gray-400 mt-1"><?php echo formatCurrency($deadStockValue); ?> tied up</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Fast Moving</p>
            <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-rocket text-emerald-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $fastMoving; ?></p>
        <p class="text-xs text-gray-400 mt-1">Items with >10 used in 90d</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Slow Moving</p>
            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-turtle text-orange-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo $slowMoving; ?></p>
        <p class="text-xs text-gray-400 mt-1">No movement in 90 days</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-gray-500 font-medium">Monthly Consumption</p>
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-bar text-blue-600 text-sm"></i>
            </div>
        </div>
        <p class="text-xl font-bold text-gray-800"><?php echo formatNumber(array_sum($monthlyConsumption)); ?></p>
        <p class="text-xs text-gray-400 mt-1"><?php echo formatCurrency(array_sum($monthlyConsumptionCost)); ?> total</p>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center space-x-3">
        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-red-600"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Low Stock</p>
            <p class="text-lg font-bold text-gray-800"><?php echo $lowStockCount; ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center space-x-3">
        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-warehouse text-purple-600"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Overstock</p>
            <p class="text-lg font-bold text-gray-800"><?php echo $overstockCount; ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center space-x-3">
        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-clock text-orange-600"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Expired</p>
            <p class="text-lg font-bold text-gray-800"><?php echo $expiredCount; ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center space-x-3">
        <div class="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <i class="fas fa-truck text-teal-600"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Avg Lead Time</p>
            <p class="text-lg font-bold text-gray-800"><?php echo $avgLeadTime; ?>d</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Consumption (<?php echo $currentYear; ?>)</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="consumptionChart"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Stock In vs Out (<?php echo $currentYear; ?>)</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="stockFlowChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory Value by Category</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="categoryPieChart"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Items by Value</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="topItemsChart"></canvas>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory Health Summary</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 bg-emerald-50 rounded-lg border border-emerald-200">
            <div class="flex items-center space-x-2 mb-2">
                <i class="fas fa-check-circle text-emerald-600"></i>
                <h4 class="text-sm font-semibold text-emerald-800">Healthy Stock</h4>
            </div>
            <p class="text-2xl font-bold text-emerald-700"><?php echo max(0, $totalItems - $lowStockCount - $overstockCount - $expiredCount); ?></p>
            <p class="text-xs text-emerald-600 mt-1">Items within optimal range</p>
        </div>
        <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center space-x-2 mb-2">
                <i class="fas fa-chart-line text-blue-600"></i>
                <h4 class="text-sm font-semibold text-blue-800">Cost of Goods Sold</h4>
            </div>
            <p class="text-2xl font-bold text-blue-700"><?php echo formatCurrency($cogs); ?></p>
            <p class="text-xs text-blue-600 mt-1">Last 12 months</p>
        </div>
        <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
            <div class="flex items-center space-x-2 mb-2">
                <i class="fas fa-calculator text-purple-600"></i>
                <h4 class="text-sm font-semibold text-purple-800">Avg Inventory Value</h4>
            </div>
            <p class="text-2xl font-bold text-purple-700"><?php echo formatCurrency($avgInventory); ?></p>
            <p class="text-xs text-purple-600 mt-1">Average during the year</p>
        </div>
        <div class="p-4 bg-orange-50 rounded-lg border border-orange-200">
            <div class="flex items-center space-x-2 mb-2">
                <i class="fas fa-exclamation-triangle text-orange-600"></i>
                <h4 class="text-sm font-semibold text-orange-800">Low Stock Value</h4>
            </div>
            <p class="text-2xl font-bold text-orange-700"><?php echo formatCurrency($lowStockValue); ?></p>
            <p class="text-xs text-orange-600 mt-1">Value at risk of stockout</p>
        </div>
    </div>
</div>

<script>
const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
new Chart(consumptionCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Units Consumed',
            data: <?php echo json_encode($monthlyConsumption); ?>,
            backgroundColor: <?php echo json_encode($monthLabelColors); ?>,
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.raw.toFixed(1) + ' units (<?php echo '₱'; ?>' + <?php echo json_encode($monthlyConsumptionCost); ?>[ctx.dataIndex].toFixed(2) + ')';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

const flowCtx = document.getElementById('stockFlowChart').getContext('2d');
new Chart(flowCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Stock In',
            data: <?php echo json_encode($monthlyIn); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#10b981'
        }, {
            label: 'Stock Out (Consumption)',
            data: <?php echo json_encode($monthlyConsumption); ?>,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#ef4444'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

const catPieCtx = document.getElementById('categoryPieChart').getContext('2d');
const catLabels = <?php echo json_encode(array_column($catValues, 'category_type')); ?>;
const catData = <?php echo json_encode(array_column($catValues, 'val')); ?>;
new Chart(catPieCtx, {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catData,
            backgroundColor: <?php echo json_encode(array_slice($catColors, 0, count($catValues))); ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        let pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                        return ctx.label + ': <?php echo '₱'; ?>' + ctx.raw.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (' + pct + '%)';
                    }
                }
            }
        }
    }
});

const topCtx = document.getElementById('topItemsChart').getContext('2d');
const topLabels = <?php echo json_encode(array_column($topItems, 'name')); ?>;
const topValues = <?php echo json_encode(array_column($topItems, 'val')); ?>;
const topStocks = <?php echo json_encode(array_column($topItems, 'current_stock')); ?>;
new Chart(topCtx, {
    type: 'bar',
    data: {
        labels: topLabels,
        datasets: [{
            label: 'Inventory Value (<?php echo '₱'; ?>)',
            data: topValues,
            backgroundColor: 'rgba(139, 92, 246, 0.7)',
            borderColor: '#8b5cf6',
            borderWidth: 2,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return '<?php echo '₱'; ?>' + ctx.raw.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' (Stock: ' + topStocks[ctx.dataIndex] + ')';
                    }
                }
            }
        },
        scales: {
            y: { grid: { display: false } },
            x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: function(v) { return '<?php echo '₱'; ?>' + v.toLocaleString(); } } }
        }
    }
});
</script>
