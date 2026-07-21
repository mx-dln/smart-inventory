<?php
// Dashboard page
require_once __DIR__ . '/../includes/skeleton.php';
$stats = getDashboardStats($pdo);

// Monthly data for charts
$currentYear = date('Y');
$monthlyIn = [];
$monthlyOut = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_in WHERE YEAR(date_received) = ? AND MONTH(date_received) = ?");
    $stmt->execute([$currentYear, $m]);
    $monthlyIn[] = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE YEAR(date_released) = ? AND MONTH(date_released) = ?");
    $stmt->execute([$currentYear, $m]);
    $monthlyOut[] = (float)$stmt->fetchColumn();
}

// Category distribution
$catStmt = $pdo->query("SELECT c.category_type, COUNT(i.id) as count FROM categories c LEFT JOIN inventory_items i ON c.id = i.category_id GROUP BY c.category_type");
$categories = $catStmt->fetchAll();

// Today incoming/outgoing totals
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_in WHERE DATE(date_received) = ?");
$stmt->execute([$today]);
$stats['today_incoming'] = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE DATE(date_released) = ?");
$stmt->execute([$today]);
$stats['today_outgoing'] = (float)$stmt->fetchColumn();

// Recent transactions
$recentStmt = $pdo->query("SELECT it.*, i.name as item_name, u.full_name as user_name FROM inventory_transactions it LEFT JOIN inventory_items i ON it.item_id = i.id LEFT JOIN users u ON it.created_by = u.id ORDER BY it.created_at DESC LIMIT 10");
$recentTransactions = $recentStmt->fetchAll();

// Low stock items
$lowStockItems = getLowStockItems($pdo);

// Near expiry items
$nearExpiryItems = getNearExpiryItems($pdo, 30);

// Expired items
$expiredItems = getExpiredItems($pdo);

// Pending production requests
$pendingStmt = $pdo->query("SELECT pr.*, u.full_name as requested_by_name FROM production_requests pr LEFT JOIN users u ON pr.requested_by = u.id WHERE pr.status = 'Pending' ORDER BY pr.created_at DESC LIMIT 5");
$pendingRequests = $pendingStmt->fetchAll();

// Inventory valuation by category
$valStmt = $pdo->query("SELECT c.category_type, COALESCE(SUM(i.current_stock * i.unit_cost), 0) as total_value FROM categories c LEFT JOIN inventory_items i ON c.id = i.category_id AND i.status = 'Active' GROUP BY c.category_type");
$valuationData = $valStmt->fetchAll();

// Top used materials
$topUsedStmt = $pdo->prepare("SELECT i.name, COALESCE(SUM(sout.quantity), 0) as total_used FROM inventory_items i LEFT JOIN stock_out sout ON i.id = sout.item_id AND YEAR(sout.date_released) = ? GROUP BY i.id ORDER BY total_used DESC LIMIT 10");
$topUsedStmt->execute([$currentYear]);
$topUsed = $topUsedStmt->fetchAll();
?>

<!-- Welcome Section -->
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?>!</h2>
    <p class="text-gray-500 text-sm mt-1">Here's what's happening at the Food Innovation Center today.</p>
</div>

<!-- Skeleton loading for stats -->
<div class="skeleton-loading stat-card-skeleton">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="skeleton-stat-card">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="skeleton skeleton-text-sm mb-2"></div>
                    <div class="skeleton skeleton-number"></div>
                </div>
                <div class="skeleton skeleton-icon"></div>
            </div>
            <div class="mt-3 skeleton skeleton-text-sm" style="width:45%"></div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-chart"></div></div>
        <div class="skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-chart"></div></div>
    </div>
    <div class="skeleton-card mb-6">
        <div class="skeleton skeleton-title mb-4"></div>
        <?php for ($i = 0; $i < 5; $i++): ?><div class="skeleton skeleton-table-row"></div><?php endfor; ?>
    </div>
</div>

<!-- Actual content (hidden until skeletons disappear) -->
<div class="actual-content" style="display:none">

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Raw Materials -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Raw Materials</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['raw_materials']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-seedling text-emerald-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Total quantity in stock</div>
    </div>

    <!-- Production Supplies -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Production Supplies</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['production_supplies']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-boxes text-blue-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Total quantity in stock</div>
    </div>

    <!-- Low Stock -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Low Stock Items</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['low_stock']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Items below minimum stock</div>
    </div>

    <!-- Expired -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Expired Items</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['expired']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-clock text-red-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Items past expiration date</div>
    </div>

    <!-- Today's Transactions -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Today's Transactions</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['today_transactions']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exchange-alt text-indigo-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">All inventory movements today</div>
    </div>

    <!-- Incoming Total -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Incoming Total</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['today_incoming'] ?? 0; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-arrow-down text-emerald-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Stock in today</div>
    </div>

    <!-- Outgoing Total -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Outgoing Total</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['today_outgoing'] ?? 0; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-arrow-up text-red-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Stock out today</div>
    </div>

    <!-- Active Suppliers -->
    <div class="stat-card bg-white rounded-xl shadow-sm p-5 border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 font-medium">Active Suppliers</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 stat-value" data-target="<?php echo $stats['suppliers']; ?>">0</p>
            </div>
            <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-truck text-teal-600 text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-400">Registered suppliers</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Monthly Stock Movement -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Stock Movement (<?php echo $currentYear; ?>)</h3>
        <div class="chart-container" style="position:relative; height:250px;">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory Category Distribution</h3>
        <div class="chart-container" style="position:relative; height:250px;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Second Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Inventory Value by Category -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Inventory Value by Category</h3>
        <div class="chart-container" style="position:relative; height:250px;">
            <canvas id="valueChart"></canvas>
        </div>
    </div>

    <!-- Top Used Materials -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Used Materials (<?php echo $currentYear; ?>)</h3>
        <div class="chart-container" style="position:relative; height:250px;">
            <canvas id="topUsedChart"></canvas>
        </div>
    </div>
</div>

<!-- Alerts & Requests Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Low Stock Alerts -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i> Low Stock Items
            </h3>
            <span class="bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($lowStockItems); ?></span>
        </div>
        <?php if (count($lowStockItems) > 0): ?>
        <div class="space-y-3 max-h-80 overflow-y-auto">
            <?php foreach ($lowStockItems as $item): ?>
            <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?php echo h($item['name']); ?></p>
                    <p class="text-xs text-gray-500">Stock: <?php echo formatNumber($item['current_stock']); ?> <?php echo h($item['unit_name'] ?? ''); ?> (Min: <?php echo formatNumber($item['min_stock']); ?>)</p>
                </div>
                <a href="index?page=inventory&view=<?php echo $item['id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">View</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-sm text-center py-4">All items are sufficiently stocked.</p>
        <?php endif; ?>
    </div>

    <!-- Expiration Alerts -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-calendar-times text-red-500 mr-2"></i> Expiration Alerts
            </h3>
            <div class="flex items-center space-x-2">
                <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($expiredItems) + count($nearExpiryItems); ?></span>
            </div>
        </div>

        <?php if (count($expiredItems) > 0): $firstExpired = reset($expiredItems); ?>
        <div class="p-3 bg-red-50 rounded-lg mb-2 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate"><?php echo h($firstExpired['name']); ?></p>
                <p class="text-xs text-red-600">Expired: <?php echo formatDate($firstExpired['expiration_date']); ?></p>
            </div>
            <a href="index?page=inventory&view=<?php echo $firstExpired['id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium shrink-0 ml-2">View</a>
        </div>
        <?php endif; ?>

        <?php if (count($nearExpiryItems) > 0): $firstNear = reset($nearExpiryItems); ?>
        <div class="p-3 bg-yellow-50 rounded-lg mb-2 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate"><?php echo h($firstNear['name']); ?></p>
                <p class="text-xs text-yellow-600"><?php echo $firstNear['days_remaining']; ?> days remaining</p>
            </div>
            <a href="index?page=inventory&view=<?php echo $firstNear['id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium shrink-0 ml-2">View</a>
        </div>
        <?php endif; ?>

        <?php if (count($expiredItems) == 0 && count($nearExpiryItems) == 0): ?>
        <p class="text-gray-500 text-sm text-center py-4">No expiration issues.</p>
        <?php else: ?>
        <button onclick="document.getElementById('alertsModal').classList.remove('hidden')" class="w-full mt-2 py-2 text-sm text-emerald-600 hover:text-emerald-700 font-medium border border-dashed border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            <i class="fas fa-eye mr-1"></i> View All Alerts (<?php echo count($expiredItems) + count($nearExpiryItems); ?>)
        </button>
        <?php endif; ?>
    </div>

    <!-- Alerts Modal -->
    <div id="alertsModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-60" onclick="document.getElementById('alertsModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto">
                <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center rounded-t-2xl">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center"><i class="fas fa-calendar-times text-red-500 mr-2"></i> All Expiration Alerts</h3>
                    <button onclick="document.getElementById('alertsModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-4 space-y-2">
                    <?php if (count($expiredItems) > 0): ?>
                    <p class="text-xs font-semibold text-red-600 uppercase px-1 mb-1">Expired (<?php echo count($expiredItems); ?>)</p>
                    <?php foreach ($expiredItems as $item): ?>
                    <a href="index?page=inventory&view=<?php echo $item['id']; ?>" class="flex items-center justify-between p-3 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?php echo h($item['name']); ?></p>
                            <p class="text-xs text-red-600">Expired: <?php echo formatDate($item['expiration_date']); ?></p>
                        </div>
                        <i class="fas fa-chevron-right text-red-400 text-xs"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (count($nearExpiryItems) > 0): ?>
                    <p class="text-xs font-semibold text-yellow-600 uppercase px-1 mb-1 mt-3">Near Expiry (<?php echo count($nearExpiryItems); ?>)</p>
                    <?php foreach ($nearExpiryItems as $item): ?>
                    <a href="index?page=inventory&view=<?php echo $item['id']; ?>" class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors">
                        <div>
                            <p class="text-sm font-medium text-gray-800"><?php echo h($item['name']); ?></p>
                            <p class="text-xs text-yellow-600"><?php echo $item['days_remaining']; ?> days remaining</p>
                        </div>
                        <i class="fas fa-chevron-right text-yellow-400 text-xs"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Production Requests -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <i class="fas fa-hourglass-half text-purple-500 mr-2"></i> Pending Production Requests
        </h3>
        <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($pendingRequests); ?></span>
    </div>
    <?php if (count($pendingRequests) > 0): ?>
    <div class="space-y-3">
        <?php foreach ($pendingRequests as $req): ?>
        <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
            <div>
                <p class="text-sm font-medium text-gray-800"><?php echo h($req['request_number'] ?? 'Request #' . $req['id']); ?></p>
                <p class="text-xs text-gray-500">Requested by: <?php echo h($req['requested_by_name'] ?? 'Unknown'); ?></p>
            </div>
            <a href="index?page=production&view=<?php echo $req['id']; ?>" class="text-xs text-purple-600 hover:text-purple-700 font-medium">View</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-gray-500 text-sm text-center py-4">No pending production requests.</p>
    <?php endif; ?>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
            <i class="fas fa-history text-gray-500 mr-2"></i> Recent Transactions
        </h3>
        <a href="index?page=reports&type=transactions" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">User</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($recentTransactions as $t): ?>
                <tr class="text-sm text-gray-700">
                    <td class="px-4 py-3"><?php echo formatDateTime($t['created_at'], 'M d, Y h:i A'); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($t['item_name'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full
                            <?php echo $t['transaction_type'] == 'Stock In' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $t['transaction_type']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3"><?php echo formatNumber($t['quantity']); ?></td>
                    <td class="px-4 py-3"><?php echo h($t['user_name'] ?? 'System'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($recentTransactions) == 0): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No recent transactions.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div> <!-- end actual-content -->

<script>
// Dark mode chart colors
var isDark = document.documentElement.classList.contains('dark');
var chartTextColor = isDark ? '#e2e8f0' : '#475569';
var chartGridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';

// Animated counter for stat cards
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-value');
    const speed = 50;

    counters.forEach(counter => {
        const updateCount = () => {
            const target = parseFloat(counter.getAttribute('data-target'));
            const count = parseFloat(counter.innerText.replace(/,/g, ''));
            const increment = Math.max(1, target / 50);

            if (count < target) {
                const newCount = Math.min(count + increment, target);
                counter.innerText = Number.isInteger(target) ? Math.floor(newCount) : newCount.toFixed(2);
                setTimeout(updateCount, speed);
            } else {
                counter.innerText = target % 1 === 0 ? target.toLocaleString() : target.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        };
        updateCount();
    });
});

// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{
            label: 'Stock In',
            data: <?php echo json_encode($monthlyIn); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10b981',
            borderWidth: 2,
            borderRadius: 4
        }, {
            label: 'Stock Out',
            data: <?php echo json_encode($monthlyOut); ?>,
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderColor: '#ef4444',
            borderWidth: 2,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        color: chartTextColor,
        plugins: {
            legend: { position: 'bottom', labels: { color: chartTextColor } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { color: chartTextColor } },
            x: { grid: { display: false }, ticks: { color: chartTextColor } }
        }
    }
});

// Category Chart
const catCtx = document.getElementById('categoryChart').getContext('2d');
const catLabels = <?php echo json_encode(array_column($categories, 'category_type')); ?>;
const catCounts = <?php echo json_encode(array_column($categories, 'count')); ?>;
const catColors = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6'];
new Chart(catCtx, {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catCounts,
            backgroundColor: catColors.slice(0, catLabels.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        color: chartTextColor,
        plugins: {
            legend: { position: 'bottom', labels: { color: chartTextColor, padding: 15 } }
        }
    }
});

// Value Chart
const valCtx = document.getElementById('valueChart').getContext('2d');
const valLabels = <?php echo json_encode(array_column($valuationData, 'category_type')); ?>;
const valValues = <?php echo json_encode(array_column($valuationData, 'total_value')); ?>;
new Chart(valCtx, {
    type: 'bar',
    data: {
        labels: valLabels,
        datasets: [{
            label: 'Total Value (<?php echo '₱'; ?>)',
            data: valValues,
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
        color: chartTextColor,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { grid: { display: false }, ticks: { color: chartTextColor } },
            x: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { color: chartTextColor } }
        }
    }
});

// Top Used Chart
const topCtx = document.getElementById('topUsedChart').getContext('2d');
const topLabels = <?php echo json_encode(array_column($topUsed, 'name')); ?>;
const topValues = <?php echo json_encode(array_column($topUsed, 'total_used')); ?>;
new Chart(topCtx, {
    type: 'bar',
    data: {
        labels: topLabels,
        datasets: [{
            label: 'Quantity Used',
            data: topValues,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10b981',
            borderWidth: 2,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        color: chartTextColor,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { grid: { display: false }, ticks: { color: chartTextColor } },
            x: { beginAtZero: true, grid: { color: chartGridColor }, ticks: { color: chartTextColor } }
        }
    }
});
</script>
