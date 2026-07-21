<?php
$page = 'recommendations';

$filter = $_GET['priority'] ?? '';

// Dismiss recommendation via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_id'])) {
    $dismissId = (int)$_POST['dismiss_id'];
    $stmt = $pdo->prepare("UPDATE recommendations SET is_dismissed = 1 WHERE id = ?");
    $stmt->execute([$dismissId]);
    logAudit($pdo, 'Dismiss Recommendation', 'Recommendations', 'Dismissed recommendation ID ' . $dismissId);
    if (!isset($_POST['ajax'])) {
        header('Location: index?page=recommendations&priority=' . urlencode($filter));
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

// Generate recommendations from data analysis
$generatedRecs = [];

$stmt = $pdo->query("SELECT i.*, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.current_stock <= i.min_stock AND i.status='Active' LIMIT 20");
$lowItems = $stmt->fetchAll();
foreach ($lowItems as $item) {
    $reorderQty = max($item['max_stock'] - $item['current_stock'], $item['min_stock'] * 2);
    $generatedRecs[] = [
        'type' => 'Reorder',
        'title' => 'Reorder: ' . $item['name'],
        'description' => 'Current stock (' . formatNumber($item['current_stock']) . ' ' . $item['unit'] . ') is below minimum (' . formatNumber($item['min_stock']) . ' ' . $item['unit'] . '). Suggested reorder qty: ' . formatNumber($reorderQty) . ' ' . $item['unit'] . '.',
        'suggested_action' => 'Purchase ' . formatNumber($reorderQty) . ' ' . $item['unit'] . ' to replenish stock to maximum level.',
        'priority' => (float)$item['current_stock'] <= 0 ? 'Critical' : 'Warning',
        'item_id' => $item['id']
    ];
}

$stmt = $pdo->query("SELECT i.*, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.current_stock > i.max_stock * 1.5 AND i.status='Active' LIMIT 20");
$overstockItems = $stmt->fetchAll();
foreach ($overstockItems as $item) {
    $pctOver = round(($item['current_stock'] / $item['max_stock'] - 1) * 100);
    $generatedRecs[] = [
        'type' => 'Overstock',
        'title' => 'Overstock: ' . $item['name'],
        'description' => 'Current stock (' . formatNumber($item['current_stock']) . ' ' . $item['unit'] . ') exceeds maximum (' . formatNumber($item['max_stock']) . ' ' . $item['unit'] . ') by ' . $pctOver . '%.',
        'suggested_action' => 'Reduce orders and consider promoting this item to clear excess inventory.',
        'priority' => 'Normal',
        'item_id' => $item['id']
    ];
}

$stmt = $pdo->query("SELECT b.*, i.name as item_name, i.id as item_id, COALESCE(u.abbreviation, u.unit_name) as unit FROM batches b LEFT JOIN inventory_items i ON b.item_id=i.id LEFT JOIN units u ON i.unit_id=u.id WHERE b.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND b.status='Active' AND b.remaining_quantity > 0 LIMIT 20");
$expiringBatches = $stmt->fetchAll();
foreach ($expiringBatches as $batch) {
    $daysLeft = (new DateTime())->diff(new DateTime($batch['expiration_date']))->days;
    $generatedRecs[] = [
        'type' => 'Expiring',
        'title' => 'Expiring Batch: ' . $batch['batch_number'],
        'description' => 'Batch of ' . $batch['item_name'] . ' expires in ' . $daysLeft . ' days. Remaining: ' . formatNumber($batch['remaining_quantity']) . ' ' . ($batch['unit'] ?? 'units') . '.',
        'suggested_action' => $daysLeft <= 7 ? 'Use immediately or dispose before expiration.' : 'Plan to use within ' . $daysLeft . ' days.',
        'priority' => $daysLeft <= 7 ? 'Critical' : 'Warning',
        'item_id' => $batch['item_id']
    ];
}

$stmt = $pdo->query("SELECT i.id, i.name, i.current_stock, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.status='Active' AND i.current_stock > 0 AND (SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE item_id=i.id AND date_released > DATE_SUB(CURDATE(), INTERVAL 90 DAY)) = 0 LIMIT 20");
$slowItems = $stmt->fetchAll();
foreach ($slowItems as $item) {
    $generatedRecs[] = [
        'type' => 'SlowMoving',
        'title' => 'Slow Moving: ' . $item['name'],
        'description' => 'No movement in 90 days. Current stock: ' . formatNumber($item['current_stock']) . ' ' . $item['unit'] . '.',
        'suggested_action' => 'Promote in production, offer to other departments, or reduce future orders.',
        'priority' => 'Warning',
        'item_id' => $item['id']
    ];
}

$stmt = $pdo->query("SELECT i.id, i.name, i.current_stock, COALESCE(u.abbreviation, u.unit_name) as unit FROM inventory_items i LEFT JOIN units u ON i.unit_id=u.id WHERE i.status='Active' AND i.current_stock > 0 AND (SELECT COALESCE(SUM(quantity), 0) FROM stock_out WHERE item_id=i.id AND date_released > DATE_SUB(CURDATE(), INTERVAL 180 DAY)) = 0 LIMIT 20");
$deadItems = $stmt->fetchAll();
foreach ($deadItems as $item) {
    $generatedRecs[] = [
        'type' => 'DeadStock',
        'title' => 'Dead Stock: ' . $item['name'],
        'description' => 'No movement in 180+ days. Stock: ' . formatNumber($item['current_stock']) . ' ' . $item['unit'] . '.',
        'suggested_action' => 'Consider donation, disposal, or write-off. Remove from active inventory if no longer needed.',
        'priority' => 'Warning',
        'item_id' => $item['id']
    ];
}

foreach ($generatedRecs as $r) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO recommendations (item_id, recommendation_type, title, description, suggested_action, priority) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$r['item_id'], $r['type'], $r['title'], $r['description'], $r['suggested_action'], $r['priority']]);
}

$where = 'r.is_dismissed = 0';
$params = [];
if ($filter && in_array($filter, ['Critical', 'Warning', 'Normal'])) {
    $where .= ' AND r.priority = ?';
    $params[] = $filter;
}
$stmt = $pdo->prepare("SELECT r.*, i.name as item_name FROM recommendations r LEFT JOIN inventory_items i ON r.item_id=i.id WHERE $where ORDER BY FIELD(r.priority,'Critical','Warning','Normal'), r.created_at DESC");
$stmt->execute($params);
$dbRecs = $stmt->fetchAll();

$critCount = 0;
$warnCount = 0;
$normCount = 0;
foreach ($dbRecs as $r) {
    if ($r['priority'] === 'Critical') $critCount++;
    elseif ($r['priority'] === 'Warning') $warnCount++;
    else $normCount++;
}
$success = $_GET['success'] ?? '';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Inventory Recommendations</h2>
        <p class="text-gray-500 text-sm mt-1">AI-driven insights to optimize your inventory.</p>
    </div>
    <div class="flex items-center space-x-3 mt-3 sm:mt-0">
        <button onclick="refreshRecommendations()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
            <i class="fas fa-sync-alt mr-2"></i> Auto-Refresh
        </button>
        <a href="index?page=recommendations" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
            <i class="fas fa-undo mr-2"></i> Reset Filter
        </a>
    </div>
</div>

<?php if ($success === 'dismissed'): ?>
<div class="mb-4 px-4 py-3 bg-emerald-100 border border-emerald-200 text-emerald-700 rounded-lg text-sm">
    <i class="fas fa-check-circle mr-2"></i> Recommendation dismissed.
</div>
<?php endif; ?>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium">Total</p>
                <p class="text-xl font-bold text-gray-800 mt-1"><?php echo count($dbRecs); ?></p>
            </div>
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-list text-gray-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium">Critical</p>
                <p class="text-xl font-bold text-red-600 mt-1"><?php echo $critCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-circle text-red-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium">Warnings</p>
                <p class="text-xl font-bold text-orange-600 mt-1"><?php echo $warnCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-orange-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium">Normal</p>
                <p class="text-xl font-bold text-emerald-600 mt-1"><?php echo $normCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-check-circle text-emerald-600"></i>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-2 mb-6">
    <a href="index?page=recommendations" class="px-3 py-1.5 text-xs font-medium rounded-full border <?php echo $filter === '' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'; ?> transition-colors">All</a>
    <a href="index?page=recommendations&priority=Critical" class="px-3 py-1.5 text-xs font-medium rounded-full border <?php echo $filter === 'Critical' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'; ?> transition-colors">Critical</a>
    <a href="index?page=recommendations&priority=Warning" class="px-3 py-1.5 text-xs font-medium rounded-full border <?php echo $filter === 'Warning' ? 'bg-orange-600 text-white border-orange-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'; ?> transition-colors">Warning</a>
    <a href="index?page=recommendations&priority=Normal" class="px-3 py-1.5 text-xs font-medium rounded-full border <?php echo $filter === 'Normal' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'; ?> transition-colors">Normal</a>
</div>

<?php if (count($dbRecs) > 0): ?>
<div class="space-y-4">
    <?php foreach ($dbRecs as $rec):
        $priorityColors = [
            'Critical' => 'border-l-red-500 bg-red-50',
            'Warning' => 'border-l-orange-500 bg-orange-50',
            'Normal' => 'border-l-emerald-500 bg-emerald-50',
        ];
        $priorityIcons = [
            'Critical' => 'fa-exclamation-circle text-red-600',
            'Warning' => 'fa-exclamation-triangle text-orange-600',
            'Normal' => 'fa-check-circle text-emerald-600',
        ];
        $typeIcons = [
            'Reorder' => 'fa-shopping-cart',
            'Overstock' => 'fa-warehouse',
            'Expiring' => 'fa-clock',
            'SlowMoving' => 'fa-turtle',
            'DeadStock' => 'fa-skull',
        ];
        $borderColor = $priorityColors[$rec['priority']] ?? 'border-l-gray-500 bg-gray-50';
        $iconClass = $priorityIcons[$rec['priority']] ?? 'fa-info-circle text-gray-600';
        $typeIcon = $typeIcons[$rec['recommendation_type']] ?? 'fa-lightbulb';
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 <?php echo $borderColor; ?> p-5 transition-shadow hover:shadow-md animate-fade-in">
        <div class="flex items-start justify-between">
            <div class="flex items-start space-x-3 flex-1">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-sm flex-shrink-0">
                    <i class="fas <?php echo $typeIcon; ?> text-gray-600"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2 mb-1">
                        <span class="text-xs font-semibold uppercase tracking-wider <?php echo $rec['priority'] === 'Critical' ? 'text-red-600' : ($rec['priority'] === 'Warning' ? 'text-orange-600' : 'text-emerald-600'); ?>">
                            <i class="fas <?php echo $iconClass; ?> mr-1"></i> <?php echo $rec['priority']; ?>
                        </span>
                        <span class="text-xs text-gray-400">|</span>
                        <span class="text-xs text-gray-500"><?php echo $rec['recommendation_type']; ?></span>
                        <?php if ($rec['item_name']): ?>
                        <span class="text-xs text-gray-400">|</span>
                        <a href="index?page=inventory&view=<?php echo $rec['item_id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700 hover:underline">
                            <i class="fas fa-box mr-1"></i><?php echo h($rec['item_name']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-base font-semibold text-gray-900"><?php echo h($rec['title']); ?></h4>
                    <p class="text-sm text-gray-600 mt-1"><?php echo h($rec['description']); ?></p>
                    <?php if ($rec['suggested_action']): ?>
                    <div class="mt-2 flex items-start space-x-2">
                        <i class="fas fa-lightbulb text-yellow-500 mt-0.5 text-xs"></i>
                        <p class="text-xs text-gray-500 italic"><?php echo h($rec['suggested_action']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-2 ml-4 flex-shrink-0">
                <a href="index?page=inventory&view=<?php echo $rec['item_id']; ?>" class="no-print px-3 py-1.5 text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:underline" title="View Item">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <button onclick="dismissRecommendation(<?php echo $rec['id']; ?>)" class="no-print px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Dismiss">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="mt-2 text-xs text-gray-400">
            <i class="far fa-clock mr-1"></i> <?php echo formatDateTime($rec['created_at']); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
    <i class="fas fa-check-circle text-5xl text-emerald-300 mb-4"></i>
    <h3 class="text-lg font-semibold text-gray-700 mb-2">All Clear!</h3>
    <p class="text-sm text-gray-500">No recommendations at this time. Your inventory is well-managed.</p>
</div>
<?php endif; ?>

<script>
function dismissRecommendation(id) {
    if (!confirm('Dismiss this recommendation?')) return;
    $.ajax({
        url: 'index?page=recommendations',
        method: 'POST',
        data: {
            dismiss_id: id,
            ajax: 1,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        },
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                showToast('Recommendation dismissed', 'success');
                setTimeout(function() { location.reload(); }, 500);
            }
        },
        error: function() {
            showToast('Failed to dismiss recommendation', 'error');
        }
    });
}

function refreshRecommendations() {
    location.reload();
}
</script>
