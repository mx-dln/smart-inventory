<?php
$page = 'forecasting';

$items = $pdo->query("SELECT i.id, i.name, i.item_code, i.min_stock, i.max_stock, i.current_stock, i.unit_cost, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.status='Active' ORDER BY i.name")->fetchAll();

$selectedItemId = (int)($_GET['item_id'] ?? 0);
$method = $_GET['method'] ?? 'SMA';
$period = max(2, min(12, (int)($_GET['period'] ?? 3)));

$forecast = null;
$monthlyUsage = [];
$chartLabels = [];
$chartActual = [];
$chartForecast = [];
$chartForecastUpper = [];
$chartForecastLower = [];
$itemData = null;
$reorderDate = null;
$suggestedQty = 0;
$currentStock = 0;
$minStock = 0;
$maxStock = 0;
$avgMonthlyUsage = 0;
$errors = [];

if ($selectedItemId > 0 && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['generate'])) {
    $stmt = $pdo->prepare("SELECT current_stock, min_stock, max_stock, name, unit_cost FROM inventory_items WHERE id = ?");
    $stmt->execute([$selectedItemId]);
    $itemData = $stmt->fetch();

    if (!$itemData) {
        $errors[] = 'Item not found.';
    } else {
        $currentStock = (float)$itemData['current_stock'];
        $minStock = (float)$itemData['min_stock'];
        $maxStock = (float)$itemData['max_stock'];

        $stmt = $pdo->prepare("SELECT DATE_FORMAT(date_released, '%Y-%m') as month, COALESCE(SUM(quantity), 0) as total FROM stock_out WHERE item_id = ? AND date_released >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(date_released, '%Y-%m') ORDER BY month ASC");
        $stmt->execute([$selectedItemId]);
        $monthlyUsage = $stmt->fetchAll();

        $dbValues = [];
        foreach ($monthlyUsage as $m) {
            $dbValues[$m['month']] = (float)$m['total'];
        }

        $allMonths = [];
        for ($i = 11; $i >= 0; $i--) {
            $allMonths[] = date('Y-m', strtotime("-$i months"));
        }
        $chartLabels = $allMonths;
        $chartActual = array_map(function($m) use ($dbValues) { return $dbValues[$m] ?? 0; }, $allMonths);

        $recentValues = array_slice($chartActual, -$period);
        $nonZero = array_filter($recentValues, function($v) { return $v > 0; });

        if (count($nonZero) >= 1) {
            if ($method === 'SMA') {
                $forecast = array_sum($recentValues) / count($recentValues);
            } else {
                $count = count($recentValues);
                $weights = range(1, $count);
                $weightedSum = 0;
                $weightTotal = 0;
                for ($i = 0; $i < $count; $i++) {
                    $weightedSum += $recentValues[$i] * $weights[$i];
                    $weightTotal += $weights[$i];
                }
                $forecast = $weightTotal > 0 ? $weightedSum / $weightTotal : 0;
            }
        }

        $forecast = $forecast ?? 0;
        $avgMonthlyUsage = $forecast ?: 0;
        $monthsUntilReorder = $avgMonthlyUsage > 0 ? max(0, ($currentStock - $minStock) / $avgMonthlyUsage) : 0;
        $reorderDate = $monthsUntilReorder > 0 ? date('Y-m-d', strtotime("+$monthsUntilReorder months")) : ($currentStock <= $minStock ? date('Y-m-d') : null);
        $suggestedQty = max(0, ($avgMonthlyUsage * 2) - $currentStock + $minStock);

        $chartForecast = array_fill(0, count($chartActual) - 1, null);
        $chartForecast[] = round($forecast, 2);
        $chartForecastUpper = array_fill(0, count($chartActual) - 1, null);
        $chartForecastLower = array_fill(0, count($chartActual) - 1, null);
        $stdDev = 0;
        if (count(array_filter($chartActual)) > 1) {
            $nonZero = array_filter($chartActual, fn($v) => $v > 0);
            if (count($nonZero) > 1) {
                $mean = array_sum($nonZero) / count($nonZero);
                $variance = 0;
                foreach ($nonZero as $v) { $variance += pow($v - $mean, 2); }
                $variance /= count($nonZero);
                $stdDev = sqrt($variance);
            }
        }
        $chartForecastUpper[] = round($forecast + $stdDev, 2);
        $chartForecastLower[] = round(max(0, $forecast - $stdDev), 2);

        $stmt = $pdo->prepare("INSERT INTO forecast_data (item_id, forecast_date, forecast_type, predicted_demand, suggested_reorder_qty, predicted_reorder_date, period_months) VALUES (?, CURDATE(), ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE predicted_demand=VALUES(predicted_demand), suggested_reorder_qty=VALUES(suggested_reorder_qty), predicted_reorder_date=VALUES(predicted_reorder_date), forecast_type=VALUES(forecast_type), period_months=VALUES(period_months)");
        $stmt->execute([$selectedItemId, $method, round($forecast, 2), round($suggestedQty, 2), $reorderDate, $period]);

        logAudit($pdo, 'Generate Forecast', 'Forecasting', 'Generated ' . $method . ' forecast for item ID ' . $selectedItemId);
    }
}
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Inventory Forecasting</h2>
        <p class="text-gray-500 text-sm mt-1">Predict future demand using statistical methods.</p>
    </div>
    <?php if ($forecast !== null): ?>
    <button onclick="window.print()" class="no-print mt-3 sm:mt-0 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
        <i class="fas fa-print mr-2"></i> Print Report
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
<?php foreach ($errors as $err): ?>
<div class="mb-4 px-4 py-3 bg-red-100 border border-red-200 text-red-700 rounded-lg text-sm"><?php echo h($err); ?></div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
<div class="mb-4 px-4 py-3 bg-emerald-100 border border-emerald-200 text-emerald-700 rounded-lg text-sm">
    <i class="fas fa-check-circle mr-2"></i> Forecast generated and saved successfully.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 no-print">
    <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="page" value="forecasting">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
            <select name="item_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $item): ?>
                <option value="<?php echo $item['id']; ?>" <?php echo $selectedItemId === (int)$item['id'] ? 'selected' : ''; ?>>
                    <?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Method</label>
            <select name="method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="SMA" <?php echo $method === 'SMA' ? 'selected' : ''; ?>>Simple Moving Average (SMA)</option>
                <option value="WMA" <?php echo $method === 'WMA' ? 'selected' : ''; ?>>Weighted Moving Average (WMA)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period (months)</label>
            <select name="period" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <?php foreach ([3, 6, 12] as $p): ?>
                <option value="<?php echo $p; ?>" <?php echo $period === $p ? 'selected' : ''; ?>><?php echo $p; ?> months</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" name="generate" value="1" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                <i class="fas fa-calculator mr-2"></i> Generate Forecast
            </button>
        </div>
        <div>
            <a href="index?page=forecasting" class="w-full block text-center px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                <i class="fas fa-undo mr-2"></i> Reset
            </a>
        </div>
    </form>
</div>

<?php if ($forecast !== null && $itemData): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Predicted Monthly Demand</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatNumber($forecast, 1); ?> <span class="text-sm font-normal text-gray-400">units</span></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-chart-line text-blue-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400"><?php echo $method; ?> forecast for next month</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Predicted Reorder Date</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $reorderDate === 'Now' ? '<span class="text-red-600">Reorder Now</span>' : ($reorderDate && $reorderDate !== 'N/A' ? h($reorderDate) : 'N/A'); ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Based on current consumption rate</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Suggested Purchase Qty</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatNumber($suggestedQty, 1); ?> <span class="text-sm font-normal text-gray-400">units</span></p>
            </div>
            <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-shopping-cart text-emerald-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">To maintain 2-month buffer stock</div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Current Stock Level</p>
                <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatNumber($currentStock, 1); ?> <span class="text-sm font-normal text-gray-400">units</span></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-boxes text-purple-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Min: <?php echo formatNumber($minStock, 1); ?> | Max: <?php echo formatNumber($maxStock, 1); ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Demand Trend & Forecast</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="forecastChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Forecast vs Actual</h3>
        <div class="chart-container" style="position:relative; height:280px;">
            <canvas id="comparisonChart"></canvas>
        </div>
        <?php
        $actualSum = array_sum($chartActual);
        $mae = count($chartActual) > 0 && $actualSum > 0 ? (1 - abs(array_sum($chartActual) - ($forecast * count($chartActual))) / max($actualSum, 1)) * 100 : 0;
        $accuracy = round(max(0, min(100, $mae)), 1);
        ?>
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600 font-medium">Forecast Accuracy</span>
                <span class="text-lg font-bold <?php echo $accuracy >= 80 ? 'text-emerald-600' : ($accuracy >= 60 ? 'text-orange-600' : 'text-red-600'); ?>"><?php echo $accuracy; ?>%</span>
            </div>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                <div class="h-2 rounded-full <?php echo $accuracy >= 80 ? 'bg-emerald-500' : ($accuracy >= 60 ? 'bg-orange-500' : 'bg-red-500'); ?>" style="width: <?php echo $accuracy; ?>%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Based on historical variance between actual usage and predicted values</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800">Forecast Settings & Explanation</h3>
        <button onclick="toggleForecastInfo()" class="text-sm text-emerald-600 hover:text-emerald-700">
            <i class="fas fa-chevron-down" id="infoChevron"></i> Show Details
        </button>
    </div>
    <div id="forecastInfo" class="hidden space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-gray-800 text-sm mb-2">Simple Moving Average (SMA)</h4>
                <p class="text-sm text-gray-600">Averages the demand over the selected period. Best for stable demand patterns with no seasonality. Formula: (Sum of demand over n periods) / n</p>
                <?php if ($method === 'SMA' && $forecast !== null): ?>
                <div class="mt-2 text-xs text-gray-500">
                    Calculation: (<?php echo implode(' + ', array_map(function($v) { return formatNumber($v, 1); }, $recentValues ?? [])); ?>) / <?php echo count($recentValues ?? []); ?> = <?php echo formatNumber($forecast, 1); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-gray-800 text-sm mb-2">Weighted Moving Average (WMA)</h4>
                <p class="text-sm text-gray-600">Assigns more weight to recent data points. Better for trending data. Formula: (Sum of (demand × weight)) / (Sum of weights)</p>
                <?php if ($method === 'WMA' && $forecast !== null): ?>
                <div class="mt-2 text-xs text-gray-500">
                    Weights: 1 (oldest) to <?php echo count($recentValues ?? []); ?> (newest)
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-4 bg-blue-50 rounded-lg">
            <h4 class="font-medium text-gray-800 text-sm mb-1">Recommendations</h4>
            <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                <li>Use <strong>SMA</strong> for items with consistent, stable demand</li>
                <li>Use <strong>WMA</strong> for items with recent upward or downward trends</li>
                <li>Use a <strong>longer period</strong> (12 months) for items with annual seasonality</li>
                <li>Use a <strong>shorter period</strong> (3 months) for fast-moving items with frequent changes</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleForecastInfo() {
    const info = document.getElementById('forecastInfo');
    const chevron = document.getElementById('infoChevron');
    info.classList.toggle('hidden');
    chevron.classList.toggle('rotate-180');
}

const forecastCtx = document.getElementById('forecastChart').getContext('2d');
new Chart(forecastCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'Actual Usage',
            data: <?php echo json_encode($chartActual); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#10b981'
        }, {
            label: 'Forecast',
            data: <?php echo json_encode($chartForecast); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            borderDash: [6, 3],
            fill: false,
            tension: 0.3,
            pointRadius: 6,
            pointBackgroundColor: '#3b82f6',
            pointStyle: 'rectRot'
        }, {
            label: 'Upper Bound',
            data: <?php echo json_encode($chartForecastUpper); ?>,
            borderColor: 'rgba(59, 130, 246, 0.3)',
            backgroundColor: 'rgba(59, 130, 246, 0.05)',
            borderWidth: 1,
            borderDash: [3, 3],
            fill: '-2',
            tension: 0.3,
            pointRadius: 0
        }, {
            label: 'Lower Bound',
            data: <?php echo json_encode($chartForecastLower); ?>,
            borderColor: 'rgba(59, 130, 246, 0.3)',
            backgroundColor: 'rgba(59, 130, 246, 0.05)',
            borderWidth: 1,
            borderDash: [3, 3],
            fill: false,
            tension: 0.3,
            pointRadius: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        if (ctx.raw === null) return null;
                        return ctx.dataset.label + ': ' + ctx.raw.toFixed(1) + ' units';
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, title: { display: true, text: 'Units' } },
            x: { grid: { display: false } }
        }
    }
});

const compCtx = document.getElementById('comparisonChart').getContext('2d');
const compLabels = <?php echo json_encode($chartLabels); ?>;
const compActual = <?php echo json_encode($chartActual); ?>;
const compForecast = <?php echo json_encode($chartForecast); ?>;
const compBars = compLabels.map(function(l, i) {
    return compForecast[i] !== null ? compActual[i] : null;
});
new Chart(compCtx, {
    type: 'bar',
    data: {
        labels: compLabels,
        datasets: [{
            label: 'Actual',
            data: compActual,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10b981',
            borderWidth: 1,
            borderRadius: 3
        }, {
            label: 'Forecast (Projected)',
            data: compForecast,
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderColor: '#3b82f6',
            borderWidth: 1,
            borderRadius: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        if (ctx.raw === null) return null;
                        return ctx.dataset.label + ': ' + ctx.raw.toFixed(1) + ' units';
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
</script>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .bg-white { background: white !important; border: 1px solid #ddd !important; }
}
</style>
<?php elseif ($selectedItemId > 0 && !isset($_GET['generate'])): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <i class="fas fa-calculator text-5xl text-gray-300 mb-4"></i>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">Ready to Generate Forecast</h3>
    <p class="text-sm text-gray-500">Select a forecast method and period, then click <strong>Generate Forecast</strong>.</p>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <i class="fas fa-chart-line text-5xl text-gray-300 mb-4"></i>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">Select an Item to Begin</h3>
    <p class="text-sm text-gray-500">Choose an inventory item above and configure forecast settings to generate predictions.</p>
</div>
<?php endif; ?>
