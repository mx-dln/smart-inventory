<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT br.*, i.name as item_name, i.item_code, i.current_stock as item_current_stock,
                       rb.full_name as returned_by_name, rc.full_name as received_by_name
                       FROM borrow_records br
                       LEFT JOIN inventory_items i ON br.item_id = i.id
                       LEFT JOIN users rb ON br.returned_by = rb.id
                       LEFT JOIN users rc ON br.received_by = rc.id
                       WHERE br.id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
    echo '<div class="text-center py-8 text-gray-500">Record not found.</div>';
    exit;
}

$isOverdue = ($r['status'] === 'Borrowed' && strtotime($r['expected_return_date']) < strtotime(date('Y-m-d')));
$statusColors = [
    'Borrowed' => 'bg-blue-100 text-blue-800',
    'Returned' => 'bg-emerald-100 text-emerald-800',
    'Overdue' => 'bg-red-100 text-red-800',
    'Lost' => 'bg-gray-100 text-gray-800',
    'Damaged' => 'bg-orange-100 text-orange-800',
];
$penaltyTotal = (float)$r['penalty'];
$creatorStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
$creatorStmt->execute([$r['created_by']]);
$creator = $creatorStmt->fetch();
?>

<div class="flex items-start justify-between mb-6">
    <div>
        <h4 class="text-lg font-bold text-gray-900"><?php echo h($r['borrower_name']); ?></h4>
        <div class="flex items-center space-x-2 mt-0.5">
            <span class="text-xs text-gray-500"><?php echo h($r['department'] ?? 'No department'); ?></span>
            <span class="text-gray-300">|</span>
            <span class="px-2 py-0.5 text-xs rounded-full font-medium <?php echo $statusColors[$r['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo $r['status']; ?></span>
            <?php if ($isOverdue): ?>
            <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-800 font-medium">OVERDUE</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex space-x-2">
        <?php if ($r['status'] === 'Borrowed' || $r['status'] === 'Overdue'): ?>
        <button onclick="closeModal('viewModal'); openReturnModal(<?php echo $r['id']; ?>)" class="px-3 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-lg text-xs font-medium hover:bg-green-100 transition-colors">
            <i class="fas fa-undo-alt mr-1"></i> Process Return
        </button>
        <?php endif; ?>
        <button onclick="closeModal('viewModal')" class="px-3 py-1.5 bg-gray-50 text-gray-600 border border-gray-200 rounded-lg text-xs font-medium hover:bg-gray-100 transition-colors">
            <i class="fas fa-times mr-1"></i> Close
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-box text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Item</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($r['item_name'] ?? 'Unknown'); ?></p>
            <p class="text-xs font-mono text-gray-400"><?php echo h($r['item_code'] ?? ''); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-user text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Borrower / Department</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($r['borrower_name']); ?></p>
            <p class="text-xs text-gray-400"><?php echo h($r['department'] ?? 'N/A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-calendar-plus text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Borrow Date</p>
            <p class="text-sm font-medium text-gray-800"><?php echo formatDateTime($r['borrow_date'], 'M d, Y h:i A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-calendar-check text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Expected Return</p>
            <p class="text-sm font-medium <?php echo $isOverdue ? 'text-red-600' : 'text-gray-800'; ?>"><?php echo formatDate($r['expected_return_date']); ?></p>
            <?php if ($isOverdue): ?>
            <p class="text-xs text-red-500 font-medium">OVERDUE</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-undo text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Actual Return</p>
            <p class="text-sm font-medium text-gray-800"><?php echo $r['actual_return_date'] ? formatDateTime($r['actual_return_date'], 'M d, Y h:i A') : '<span class="text-gray-400">Not returned yet</span>'; ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-cubes text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Quantity</p>
            <p class="text-sm font-medium text-gray-800"><?php echo formatNumber($r['quantity_borrowed']); ?> borrowed / <?php echo formatNumber($r['quantity_returned'] ?: 0); ?> returned</p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-clipboard-check text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Condition</p>
            <p class="text-sm font-medium text-gray-800">Before: <?php echo h($r['condition_before'] ?? 'N/A'); ?></p>
            <p class="text-xs text-gray-400">After: <?php echo h($r['condition_after'] ?? 'N/A'); ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
            <i class="fas fa-exchange-alt text-gray-400 text-sm"></i>
        </div>
        <div>
            <p class="text-xs text-gray-500">Returned / Received By</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($r['returned_by_name'] ?? 'N/A'); ?></p>
            <p class="text-xs text-gray-400">Received by: <?php echo h($r['received_by_name'] ?? 'N/A'); ?></p>
        </div>
    </div>
</div>

<?php if ($penaltyTotal > 0 || $r['penalty_reason']): ?>
<div class="mb-6 p-4 bg-orange-50 rounded-lg border border-orange-200">
    <h5 class="font-semibold text-orange-800 mb-2 flex items-center">
        <i class="fas fa-exclamation-triangle mr-2"></i> Penalty Details
    </h5>
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="text-orange-600">Penalty Amount:</span>
            <span class="font-bold text-orange-800 ml-2"><?php echo formatCurrency($penaltyTotal); ?></span>
        </div>
        <div>
            <span class="text-orange-600">Reason:</span>
            <span class="text-orange-800 ml-2"><?php echo h($r['penalty_reason'] ?? 'N/A'); ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($r['remarks']): ?>
<div class="mb-6">
    <h5 class="font-semibold text-gray-800 mb-2 text-sm">Remarks</h5>
    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-100"><?php echo h($r['remarks']); ?></p>
</div>
<?php endif; ?>

<div class="text-xs text-gray-400 border-t border-gray-100 pt-4 mt-2">
    Created by: <?php echo h($creator['full_name'] ?? $creator['username'] ?? 'System'); ?> on <?php echo formatDateTime($r['created_at'], 'M d, Y h:i A'); ?>
    <?php if ($r['updated_at'] && $r['updated_at'] != $r['created_at']): ?>
    | Last updated: <?php echo formatDateTime($r['updated_at'], 'M d, Y h:i A'); ?>
    <?php endif; ?>
</div>
