<?php
$page = 'notifications';

// Mark single notification as read
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    echo '<script>window.location.href="index?page=notifications";</script>'; exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    logAudit($pdo, 'Update', 'Notifications', 'Marked all notifications as read');
    echo '<script>window.location.href="index?page=notifications&success=All notifications marked as read";</script>'; exit;
}

$typeFilter = $_GET['type'] ?? '';

$query = "SELECT n.* FROM notifications n WHERE 1=1";
$params = [];

if (!empty($typeFilter)) {
    $query .= " AND n.type = ?";
    $params[] = $typeFilter;
}

$query .= " ORDER BY n.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Count by type
$typeCounts = [];
$countStmt = $pdo->query("SELECT type, COUNT(*) as cnt, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread FROM notifications GROUP BY type");
while ($row = $countStmt->fetch()) {
    $typeCounts[$row['type']] = $row;
}

$totalUnread = array_sum(array_column($typeCounts, 'unread'));
$totalAll = array_sum(array_column($typeCounts, 'cnt'));

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
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
        <i class="fas fa-bell text-gray-500 mr-2"></i> Notifications
        <?php if ($totalUnread > 0): ?>
        <span class="ml-2 bg-red-500 text-white text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo $totalUnread; ?> unread</span>
        <?php endif; ?>
    </h3>
    <?php if ($totalUnread > 0): ?>
    <a href="index?page=notifications&mark_all_read=1" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors" onclick="return confirm('Mark all notifications as read?')">
        <i class="fas fa-check-double mr-1"></i> Mark All Read
    </a>
    <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <a href="index?page=notifications" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition-shadow <?php echo empty($typeFilter) ? 'ring-2 ring-emerald-500' : ''; ?>">
        <p class="text-xs text-gray-500">All</p>
        <p class="text-xl font-bold text-gray-800"><?php echo $totalAll; ?></p>
        <?php if ($totalUnread > 0): ?>
        <p class="text-xs text-red-500"><?php echo $totalUnread; ?> unread</p>
        <?php endif; ?>
    </a>
    <?php foreach (['Low Stock' => 'text-orange-500 bg-orange-50', 'Near Expiry' => 'text-yellow-500 bg-yellow-50', 'Expired' => 'text-red-500 bg-red-50', 'Pending Request' => 'text-purple-500 bg-purple-50', 'System' => 'text-blue-500 bg-blue-50'] as $t => $colors): ?>
    <?php $tc = $typeCounts[$t] ?? ['cnt' => 0, 'unread' => 0]; ?>
    <a href="index?page=notifications&type=<?php echo urlencode($t); ?>" class="bg-white rounded-xl shadow-sm p-4 border border-gray-100 hover:shadow-md transition-shadow <?php echo $typeFilter === $t ? 'ring-2 ring-emerald-500' : ''; ?>">
        <p class="text-xs text-gray-500"><?php echo $t; ?></p>
        <p class="text-xl font-bold text-gray-800"><?php echo $tc['cnt']; ?></p>
        <?php if ($tc['unread'] > 0): ?>
        <p class="text-xs text-red-500"><?php echo $tc['unread']; ?> new</p>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Notifications List -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (count($notifications) > 0): ?>
    <div class="divide-y divide-gray-100">
        <?php foreach ($notifications as $n): ?>
        <?php
        $iconMap = [
            'Low Stock' => ['icon' => 'fa-exclamation-triangle', 'color' => 'text-orange-500', 'bg' => 'bg-orange-50'],
            'Near Expiry' => ['icon' => 'fa-clock', 'color' => 'text-yellow-500', 'bg' => 'bg-yellow-50'],
            'Expired' => ['icon' => 'fa-times-circle', 'color' => 'text-red-500', 'bg' => 'bg-red-50'],
            'Pending Request' => ['icon' => 'fa-hourglass-half', 'color' => 'text-purple-500', 'bg' => 'bg-purple-50'],
            'System' => ['icon' => 'fa-info-circle', 'color' => 'text-blue-500', 'bg' => 'bg-blue-50'],
        ];
        $ni = $iconMap[$n['type']] ?? ['icon' => 'fa-bell', 'color' => 'text-gray-500', 'bg' => 'bg-gray-50'];
        ?>
        <div class="flex items-start p-4 hover:bg-gray-50 transition-colors <?php echo !$n['is_read'] ? 'bg-blue-50/50' : ''; ?>">
            <div class="flex-shrink-0 w-10 h-10 <?php echo $ni['bg']; ?> rounded-lg flex items-center justify-center">
                <i class="fas <?php echo $ni['icon']; ?> <?php echo $ni['color']; ?>"></i>
            </div>
            <div class="ml-4 flex-1 min-w-0">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900"><?php echo h($n['title']); ?></p>
                        <p class="text-sm text-gray-600 mt-1"><?php echo h($n['message']); ?></p>
                    </div>
                    <?php if (!$n['is_read']): ?>
                    <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p class="text-xs text-gray-400"><?php echo formatDateTime($n['created_at']); ?></p>
                    <div class="flex items-center space-x-2">
                        <?php if (empty($n['is_read'])): ?>
                        <a href="index?page=notifications&mark_read=<?php echo $n['id']; ?>" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium"><i class="fas fa-check mr-1"></i> Mark Read</a>
                        <?php endif; ?>
                        <?php if ($n['related_module'] && $n['related_id']): ?>
                        <a href="index?page=<?php echo h($n['related_module']); ?>&view=<?php echo $n['related_id']; ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium"><i class="fas fa-external-link-alt mr-1"></i> View</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="px-4 py-12 text-center text-gray-500">
        <i class="fas fa-bell-slash text-4xl text-gray-300 mb-3"></i>
        <p class="text-lg font-medium text-gray-400">No notifications</p>
        <p class="text-sm"><?php echo $typeFilter ? 'No ' . h($typeFilter) . ' notifications found.' : 'You are all caught up!'; ?></p>
    </div>
    <?php endif; ?>
</div>
