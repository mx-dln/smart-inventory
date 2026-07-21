<?php
$page = 'timeline';

$itemFilter = (int)($_GET['item_id'] ?? 0);
$actionFilter = $_GET['action'] ?? '';
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';

$items = $pdo->query("SELECT id, name, item_code FROM inventory_items WHERE status='Active' ORDER BY name")->fetchAll();

// Activity timeline (item-specific)
$q = "SELECT t.*, i.name as item_name, u.full_name as user_name FROM activity_timeline t LEFT JOIN inventory_items i ON t.item_id=i.id LEFT JOIN users u ON t.user_id=u.id WHERE 1=1";
$params = [];
if ($itemFilter) { $q .= " AND t.item_id=?"; $params[]=$itemFilter; }
if ($actionFilter) { $q .= " AND t.action_type=?"; $params[]=$actionFilter; }
if ($fromDate) { $q .= " AND DATE(t.created_at) >= ?"; $params[]=$fromDate; }
if ($toDate) { $q .= " AND DATE(t.created_at) <= ?"; $params[]=$toDate; }
$q .= " ORDER BY t.created_at DESC LIMIT 100";
$timeline = $pdo->prepare($q); $timeline->execute($params); $timeline = $timeline->fetchAll();

// Audit logs (non-item actions)
$auditQ = "SELECT a.*, u.full_name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id WHERE 1=1";
$auditParams = [];
if ($fromDate) { $auditQ .= " AND DATE(a.created_at) >= ?"; $auditParams[]=$fromDate; }
if ($toDate) { $auditQ .= " AND DATE(a.created_at) <= ?"; $auditParams[]=$toDate; }
$auditQ .= " ORDER BY a.created_at DESC LIMIT 100";
$auditLogs = $pdo->prepare($auditQ); $auditLogs->execute($auditParams); $auditLogs = $auditLogs->fetchAll();

// Get distinct action types for filter
$actionTypes = $pdo->query("SELECT DISTINCT action_type FROM activity_timeline ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);

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

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <h3 class="text-lg font-semibold text-gray-900 flex items-center"><i class="fas fa-history text-gray-400 mr-2"></i> Activity Timeline</h3>
    <button onclick="exportTimeline()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-download mr-1"></i> Export</button>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" action="index.php" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="timeline">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Item</label>
            <select name="item_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500 min-w-[180px]">
                <option value="">All Items</option>
                <?php foreach ($items as $it): ?>
                <option value="<?php echo $it['id']; ?>" <?php echo $itemFilter==$it['id']?'selected':''; ?>><?php echo h($it['name']); ?> (<?php echo h($it['item_code']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Action Type</label>
            <select name="action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">All Actions</option>
                <?php foreach ($actionTypes as $at): ?>
                <option value="<?php echo h($at); ?>" <?php echo $actionFilter===$at?'selected':''; ?>><?php echo h($at); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="<?php echo h($fromDate); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="<?php echo h($toDate); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
            <a href="index?page=timeline" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors"><i class="fas fa-undo mr-1"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Timeline -->
<?php
function getActionIcon($type) {
    $map = [
        'Created' => ['icon' => 'fa-plus-circle', 'color' => 'bg-emerald-100 text-emerald-600'],
        'Updated' => ['icon' => 'fa-pen', 'color' => 'bg-blue-100 text-blue-600'],
        'Deleted' => ['icon' => 'fa-trash', 'color' => 'bg-red-100 text-red-600'],
        'Stock In' => ['icon' => 'fa-arrow-down', 'color' => 'bg-emerald-100 text-emerald-600'],
        'Stock Out' => ['icon' => 'fa-arrow-up', 'color' => 'bg-orange-100 text-orange-600'],
        'Insert' => ['icon' => 'fa-plus-circle', 'color' => 'bg-emerald-100 text-emerald-600'],
        'Update' => ['icon' => 'fa-pen', 'color' => 'bg-blue-100 text-blue-600'],
        'Delete' => ['icon' => 'fa-trash', 'color' => 'bg-red-100 text-red-600'],
        'Approve' => ['icon' => 'fa-check-circle', 'color' => 'bg-emerald-100 text-emerald-600'],
        'Reject' => ['icon' => 'fa-times-circle', 'color' => 'bg-red-100 text-red-600'],
        'Submit' => ['icon' => 'fa-paper-plane', 'color' => 'bg-indigo-100 text-indigo-600'],
        'Cancel' => ['icon' => 'fa-ban', 'color' => 'bg-gray-100 text-gray-600'],
        'Receive' => ['icon' => 'fa-truck-loading', 'color' => 'bg-teal-100 text-teal-600'],
        'Complete' => ['icon' => 'fa-check-double', 'color' => 'bg-emerald-100 text-emerald-600'],
        'Purchase' => ['icon' => 'fa-shopping-cart', 'color' => 'bg-cyan-100 text-cyan-600'],
        'Backup' => ['icon' => 'fa-database', 'color' => 'bg-purple-100 text-purple-600'],
        'Restore' => ['icon' => 'fa-undo-alt', 'color' => 'bg-amber-100 text-amber-600'],
    ];
    return $map[$type] ?? ['icon' => 'fa-circle', 'color' => 'bg-gray-100 text-gray-600'];
}
?>
<div class="space-y-4">
    <?php
    $allEntries = [];

    foreach ($timeline as $t) {
        $allEntries[] = [
            'date' => $t['created_at'],
            'user' => $t['user_name'],
            'action' => $t['action_type'],
            'module' => 'Inventory',
            'description' => ($t['action_type'] == 'Stock In' ? 'Stocked in' : ($t['action_type'] == 'Stock Out' ? 'Stocked out' : $t['action_type'])) . ' ' . h($t['item_name'] ?? 'Unknown') . ($t['previous_value'] || $t['new_value'] ? ' (' . h($t['previous_value']) . ' → ' . h($t['new_value']) . ')' : ''),
            'type' => 'activity',
        ];
    }

    foreach ($auditLogs as $a) {
        $allEntries[] = [
            'date' => $a['created_at'],
            'user' => $a['user_name'] ?? $a['username'] ?? 'System',
            'action' => $a['action'],
            'module' => $a['module'] ?? 'N/A',
            'description' => h($a['description'] ?? ''),
            'type' => 'audit',
        ];
    }

    usort($allEntries, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $allEntries = array_slice($allEntries, 0, 200);

    if (count($allEntries) > 0): ?>
    <div class="relative">
        <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>
        <div class="space-y-3">
            <?php foreach ($allEntries as $entry):
                $meta = getActionIcon($entry['action']);
                $dateStr = date('M d, Y h:i A', strtotime($entry['date']));
                $dateGroup = date('Y-m-d', strtotime($entry['date']));
            ?>
            <div class="relative pl-14">
                <div class="absolute left-4 top-3 w-4 h-4 rounded-full border-2 border-white shadow-sm flex items-center justify-center <?php echo $meta['color']; ?>">
                    <i class="fas <?php echo $meta['icon']; ?> text-xs"></i>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-sm transition-shadow">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-1">
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium bg-opacity-20 <?php echo $meta['color']; ?>"><?php echo h($entry['action']); ?></span>
                                <span class="text-xs text-gray-400"><?php echo $dateStr; ?></span>
                            </div>
                            <p class="text-sm text-gray-800"><?php echo $entry['description']; ?></p>
                            <div class="flex items-center space-x-3 mt-1.5 text-xs text-gray-500">
                                <span><i class="fas fa-user mr-1"></i><?php echo h($entry['user'] ?: 'System'); ?></span>
                                <span><i class="fas fa-folder mr-1"></i><?php echo h($entry['module']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-12 bg-white rounded-xl border border-gray-100">
        <i class="fas fa-history text-4xl text-gray-300 mb-3"></i>
        <p class="text-gray-500 text-sm">No activity found for the selected filters.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function exportTimeline() {
    var params = '?page=timeline&export=1';
    var itemId = document.querySelector('select[name="item_id"]').value;
    var action = document.querySelector('select[name="action"]').value;
    var from = document.querySelector('input[name="from"]').value;
    var to = document.querySelector('input[name="to"]').value;
    if (itemId) params += '&item_id=' + itemId;
    if (action) params += '&action=' + encodeURIComponent(action);
    if (from) params += '&from=' + from;
    if (to) params += '&to=' + to;
    window.location.href = 'index' + params;
}
</script>
