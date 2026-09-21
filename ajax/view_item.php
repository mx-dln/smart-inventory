<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, c.category_name, c.category_type, u.unit_name, u.abbreviation, s.company_name as supplier_name FROM inventory_items i LEFT JOIN categories c ON i.category_id = c.id LEFT JOIN units u ON i.unit_id = u.id LEFT JOIN suppliers s ON i.supplier_id = s.id WHERE i.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { echo '<div class="text-center py-8 text-gray-500">Item not found.</div>'; exit; }
$txnStmt = $pdo->prepare("SELECT it.*, u.full_name as user_name FROM inventory_transactions it LEFT JOIN users u ON it.created_by = u.id WHERE it.item_id = ? ORDER BY it.created_at DESC LIMIT 20");
$txnStmt->execute([$id]);
$transactions = $txnStmt->fetchAll();
$expStatus = getExpirationStatus($item['expiration_date']);
$lowStock = $item['current_stock'] <= $item['min_stock'];
$totalValue = $item['current_stock'] * $item['unit_cost'];
?>
<!-- Header -->
<div class="flex items-start justify-between mb-6">
    <div class="flex items-center space-x-4">
        <?php if ($item['image']): ?>
        <img src="uploads/<?php echo h($item['image']); ?>" class="w-14 h-14 rounded-xl object-cover border border-gray-200">
        <?php else: ?>
        <div class="w-14 h-14 bg-emerald-100 rounded-xl flex items-center justify-center">
            <i class="fas fa-box text-emerald-600 text-xl"></i>
        </div>
        <?php endif; ?>
        <div>
            <h4 class="text-lg font-bold text-gray-900"><?php echo h($item['name']); ?></h4>
            <div class="flex items-center space-x-2 mt-0.5">
                <span class="text-xs font-mono text-gray-500"><?php echo h($item['item_code']); ?></span>
                <span class="text-gray-300">|</span>
                <span class="px-2 py-0.5 text-xs rounded-full <?php echo $item['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo $item['status']; ?></span>
                <?php if ($lowStock && $item['current_stock'] > 0): ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-800">Low Stock</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="flex space-x-2">
        <button onclick="editItem(<?php echo $item['id']; ?>)" class="px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors"><i class="fas fa-edit mr-1"></i> Edit</button>
    </div>
</div>

<?php if ($item['description']): ?>
<p class="text-sm text-gray-600 mb-5 bg-gray-50 p-3 rounded-lg border border-gray-100"><?php echo h($item['description']); ?></p>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-4 gap-3 mb-6">
    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200">
        <p class="text-xs text-emerald-600 font-medium mb-1">Current Stock</p>
        <p class="text-2xl font-bold <?php echo $lowStock ? 'text-red-600' : 'text-emerald-700'; ?>"><?php echo formatNumber($item['current_stock']); ?> <span class="text-sm font-normal"><?php echo h($item['abbreviation'] ?? ''); ?></span></p>
        <div class="mt-2 w-full bg-emerald-200 rounded-full h-1.5">
            <?php $pct = $item['max_stock'] > 0 ? min(100, ($item['current_stock'] / $item['max_stock']) * 100) : 0; ?>
            <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?php echo $pct; ?>%"></div>
        </div>
        <p class="text-xs text-emerald-500 mt-1">Min: <?php echo formatNumber($item['min_stock']); ?> / Max: <?php echo formatNumber($item['max_stock']); ?></p>
    </div>
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
        <p class="text-xs text-blue-600 font-medium mb-1">Unit Cost</p>
        <p class="text-2xl font-bold text-blue-700"><?php echo formatCurrency($item['unit_cost']); ?></p>
        <p class="text-xs text-blue-500 mt-1">Per <?php echo h($item['abbreviation'] ?? 'unit'); ?></p>
    </div>
    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
        <p class="text-xs text-purple-600 font-medium mb-1">Total Value</p>
        <p class="text-2xl font-bold text-purple-700"><?php echo formatCurrency($totalValue); ?></p>
        <p class="text-xs text-purple-500 mt-1">Current inventory value</p>
    </div>
    <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-4 border border-amber-200">
        <p class="text-xs text-amber-600 font-medium mb-1">Expiration</p>
        <?php if ($item['expiration_date']): ?>
        <p class="text-lg font-bold <?php echo str_contains($expStatus['class'], 'red') ? 'text-red-700' : (str_contains($expStatus['class'], 'orange') ? 'text-orange-700' : 'text-amber-700'); ?>"><?php echo formatDate($item['expiration_date'], 'M d, Y'); ?></p>
        <span class="px-2 py-0.5 text-xs rounded-full inline-block mt-1 <?php echo $expStatus['class']; ?>"><?php echo $expStatus['label']; ?></span>
        <?php else: ?>
        <p class="text-lg font-bold text-gray-400">N/A</p>
        <p class="text-xs text-amber-500 mt-1">No expiration date</p>
        <?php endif; ?>
    </div>
</div>

<!-- Details Grid -->
<div class="grid grid-cols-2 gap-3 mb-6">
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-tag text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Category</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($item['category_name'] ?? 'N/A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-ruler text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Unit</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($item['unit_name'] ?? 'N/A'); ?> (<?php echo h($item['abbreviation'] ?? ''); ?>)</p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-truck text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Supplier</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($item['supplier_name'] ?? 'N/A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-map-marker-alt text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Storage Location</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($item['storage_location'] ?? 'N/A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-boxes text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Category Type</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($item['category_type'] ?? 'N/A'); ?></p>
        </div>
    </div>
</div>

<!-- Transaction History -->
<div class="grid grid-cols-1 gap-6">
    <div>
        <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
            <i class="fas fa-exchange-alt text-gray-400 mr-2"></i> Transaction History
        </h5>
        <?php if (count($transactions) > 0): ?>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-3 py-2.5">Date</th>
                        <th class="px-3 py-2.5">Type</th>
                        <th class="px-3 py-2.5">Qty</th>
                        <th class="px-3 py-2.5">Prev</th>
                        <th class="px-3 py-2.5">New</th>
                        <th class="px-3 py-2.5">User</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($transactions as $t): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-3 py-2 text-xs text-gray-500"><?php echo formatDateTime($t['created_at'], 'M d, Y h:i A'); ?></td>
                        <td class="px-3 py-2">
                            <span class="px-1.5 py-0.5 text-xs rounded-full font-medium <?php echo $t['transaction_type'] == 'Stock In' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $t['transaction_type']; ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 font-medium"><?php echo formatNumber($t['quantity']); ?></td>
                        <td class="px-3 py-2 text-gray-600"><?php echo formatNumber($t['previous_stock']); ?></td>
                        <td class="px-3 py-2 font-medium"><?php echo formatNumber($t['new_stock']); ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500"><?php echo h($t['user_name'] ?? 'System'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
            <i class="fas fa-exchange-alt text-gray-300 text-2xl mb-2"></i>
            <p class="text-gray-400 text-sm">No transactions recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
