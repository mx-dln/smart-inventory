<?php
$page = 'audit-logs';

if ($_SESSION['role'] !== 'Administrator') {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');
$userFilter = $_GET['user'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$moduleFilter = $_GET['module'] ?? '';

$query = "SELECT al.* FROM audit_logs al WHERE DATE(al.created_at) BETWEEN ? AND ?";
$params = [$fromDate, $toDate];

if (!empty($userFilter)) {
    $query .= " AND al.username LIKE ?";
    $params[] = "%$userFilter%";
}
if (!empty($actionFilter)) {
    $query .= " AND al.action = ?";
    $params[] = $actionFilter;
}
if (!empty($moduleFilter)) {
    $query .= " AND al.module = ?";
    $params[] = $moduleFilter;
}

$query .= " ORDER BY al.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct actions and modules for filters
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

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

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <h3 class="text-lg font-semibold text-gray-900">Audit Logs</h3>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" action="index.php" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="audit-logs">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="<?php echo $fromDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="<?php echo $toDate; ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">User</label>
            <input type="text" name="user" value="<?php echo h($userFilter); ?>" placeholder="Search user..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Action</label>
            <select name="action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?php echo h($a); ?>" <?php echo $actionFilter === $a ? 'selected' : ''; ?>><?php echo h($a); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Module</label>
            <select name="module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?php echo h($m); ?>" <?php echo $moduleFilter === $m ? 'selected' : ''; ?>><?php echo h($m); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-filter mr-1"></i> Filter</button>
            <a href="index?page=audit-logs" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors"><i class="fas fa-undo mr-1"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="auditLogsTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Timestamp</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Module</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">IP Address</th>
                    <th class="px-4 py-3">Browser</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($logs as $log): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo formatDateTime($log['created_at']); ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($log['username'] ?: 'System'); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full
                            <?php echo match($log['action']) {
                                'Insert' => 'bg-emerald-100 text-emerald-800',
                                'Update' => 'bg-blue-100 text-blue-800',
                                'Delete' => 'bg-red-100 text-red-800',
                                'Backup' => 'bg-indigo-100 text-indigo-800',
                                'Restore' => 'bg-purple-100 text-purple-800',
                                default => 'bg-gray-100 text-gray-800',
                            }; ?>">
                            <?php echo h($log['action']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3"><?php echo h($log['module'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3 max-w-xs truncate" title="<?php echo h($log['description']); ?>"><?php echo h($log['description']); ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?php echo h($log['ip_address'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3 max-w-xs truncate text-xs text-gray-500" title="<?php echo h($log['user_agent']); ?>"><?php echo h(substr($log['user_agent'] ?: 'N/A', 0, 60)) . (strlen($log['user_agent'] ?? '') > 60 ? '...' : ''); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($logs) == 0): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No audit logs found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
