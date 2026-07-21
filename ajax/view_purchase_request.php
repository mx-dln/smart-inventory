<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="text-center py-8 text-gray-500">Invalid request.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT pr.*, u.full_name as requestor_name FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id = u.id WHERE pr.id = ?");
$stmt->execute([$id]);
$pr = $stmt->fetch();

if (!$pr) {
    echo '<div class="text-center py-8 text-gray-500">Purchase request not found.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT pri.*, c.category_name, u.unit_name, u.abbreviation FROM purchase_request_items pri LEFT JOIN categories c ON pri.category_id = c.id LEFT JOIN units u ON pri.unit_id = u.id WHERE pri.pr_id = ? ORDER BY pri.id");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT pra.*, us.full_name as approver_name FROM purchase_request_approvals pra LEFT JOIN users us ON pra.approver_id = us.id WHERE pra.pr_id = ? ORDER BY pra.created_at");
$stmt->execute([$id]);
$approvals = $stmt->fetchAll();

$statusBadges = [
    'Draft' => 'bg-gray-100 text-gray-800',
    'Submitted' => 'bg-blue-100 text-blue-800',
    'Department Approved' => 'bg-indigo-100 text-indigo-800',
    'Inventory Approved' => 'bg-purple-100 text-purple-800',
    'Administrator Approved' => 'bg-emerald-100 text-emerald-800',
    'Purchased' => 'bg-cyan-100 text-cyan-800',
    'Received' => 'bg-teal-100 text-teal-800',
    'Completed' => 'bg-emerald-100 text-emerald-800',
    'Rejected' => 'bg-red-100 text-red-800',
    'Cancelled' => 'bg-gray-100 text-gray-800',
];
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center space-x-3 mb-1">
                <h4 class="text-xl font-bold text-gray-900"><?php echo h($pr['pr_number']); ?></h4>
                <span class="px-2.5 py-1 text-xs rounded-full font-medium <?php echo $statusBadges[$pr['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo $pr['status']; ?></span>
            </div>
            <p class="text-sm text-gray-500">Requested by <span class="font-medium text-gray-700"><?php echo h($pr['requestor_name']); ?></span> on <?php echo formatDateTime($pr['created_at'], 'M d, Y h:i A'); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Department</p>
            <p class="text-sm font-semibold text-gray-800"><?php echo h($pr['department'] ?: 'N/A'); ?></p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Estimated Total</p>
            <p class="text-sm font-semibold text-gray-800"><?php echo formatCurrency($pr['estimated_total']); ?></p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Items</p>
            <p class="text-sm font-semibold text-gray-800"><?php echo count($items); ?> item(s)</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <p class="text-xs text-gray-500">Last Updated</p>
            <p class="text-sm font-semibold text-gray-800"><?php echo formatDateTime($pr['updated_at'], 'M d, Y h:i A'); ?></p>
        </div>
    </div>

    <?php if ($pr['purpose']): ?>
    <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">Purpose / Justification</p>
        <p class="text-sm text-gray-800"><?php echo h($pr['purpose']); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($pr['rejection_reason']): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
        <p class="text-xs text-red-600 font-medium mb-1">Rejection Reason</p>
        <p class="text-sm text-red-800"><?php echo h($pr['rejection_reason']); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($pr['remarks']): ?>
    <div class="bg-gray-50 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">Remarks</p>
        <p class="text-sm text-gray-800"><?php echo h($pr['remarks']); ?></p>
    </div>
    <?php endif; ?>

    <div>
        <h5 class="font-semibold text-gray-800 mb-2 flex items-center">
            <i class="fas fa-list text-gray-400 mr-2"></i> Items
        </h5>
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-3 py-2.5">Item Name</th>
                        <th class="px-3 py-2.5">Code</th>
                        <th class="px-3 py-2.5">Category</th>
                        <th class="px-3 py-2.5">Unit</th>
                        <th class="px-3 py-2.5 text-center">Qty</th>
                        <th class="px-3 py-2.5 text-right">Est. Cost</th>
                        <th class="px-3 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($items as $it): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-800"><?php echo h($it['item_name']); ?></td>
                        <td class="px-3 py-2 text-xs text-gray-500 font-mono"><?php echo h($it['item_code'] ?: 'N/A'); ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?php echo h($it['category_name'] ?: 'N/A'); ?></td>
                        <td class="px-3 py-2 text-xs text-gray-600"><?php echo h($it['abbreviation'] ?: $it['unit_name'] ?: 'N/A'); ?></td>
                        <td class="px-3 py-2 text-center font-medium"><?php echo formatNumber($it['quantity_requested']); ?></td>
                        <td class="px-3 py-2 text-right"><?php echo formatCurrency($it['estimated_cost']); ?></td>
                        <td class="px-3 py-2 text-right font-semibold"><?php echo formatCurrency($it['total_estimated']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-semibold">
                        <td colspan="6" class="px-3 py-2.5 text-right text-sm text-gray-700">Estimated Total:</td>
                        <td class="px-3 py-2.5 text-right text-sm text-gray-900"><?php echo formatCurrency($pr['estimated_total']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div>
        <h5 class="font-semibold text-gray-800 mb-2 flex items-center">
            <i class="fas fa-history text-gray-400 mr-2"></i> Approval Timeline
        </h5>
        <?php if (count($approvals) > 0): ?>
        <div class="space-y-2">
            <?php foreach ($approvals as $a): ?>
            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center <?php echo $a['action'] == 'Approved' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?>">
                    <i class="fas <?php echo $a['action'] == 'Approved' ? 'fa-check' : 'fa-times'; ?> text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-800">
                            <?php echo h($a['approver_name']); ?>
                            <span class="text-xs font-normal text-gray-500">- <?php echo h($a['approval_level']); ?></span>
                        </p>
                        <span class="text-xs text-gray-400"><?php echo formatDateTime($a['created_at'], 'M d, Y h:i A'); ?></span>
                    </div>
                    <p class="text-xs mt-0.5 <?php echo $a['action'] == 'Approved' ? 'text-emerald-600' : 'text-red-600'; ?> font-medium"><?php echo $a['action']; ?></p>
                    <?php if ($a['comments']): ?>
                    <p class="text-xs text-gray-500 mt-1 italic">"<?php echo h($a['comments']); ?>"</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-6 bg-gray-50 rounded-lg border border-gray-200">
            <i class="fas fa-clock text-gray-300 text-xl mb-1"></i>
            <p class="text-sm text-gray-400">No approvals recorded yet.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($pr['received_date']): ?>
    <div class="bg-teal-50 border border-teal-200 rounded-lg p-3 flex items-center space-x-3">
        <i class="fas fa-check-circle text-teal-600"></i>
        <div>
            <p class="text-sm font-medium text-teal-800">Items Received</p>
            <p class="text-xs text-teal-600"><?php echo formatDateTime($pr['received_date'], 'M d, Y h:i A'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($pr['completed_date']): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 flex items-center space-x-3">
        <i class="fas fa-check-double text-emerald-600"></i>
        <div>
            <p class="text-sm font-medium text-emerald-800">Completed</p>
            <p class="text-xs text-emerald-600"><?php echo formatDateTime($pr['completed_date'], 'M d, Y h:i A'); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
