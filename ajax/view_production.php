<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pr.*, u.full_name as requested_by_name, u2.full_name as approved_by_name FROM production_requests pr LEFT JOIN users u ON pr.requested_by = u.id LEFT JOIN users u2 ON pr.approved_by = u2.id WHERE pr.id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { echo '<div class="text-center py-8 text-gray-500">Request not found.</div>'; exit; }
$itemsStmt = $pdo->prepare("SELECT pri.*, i.name as item_name, i.item_code, i.current_stock, u.abbreviation FROM production_request_items pri LEFT JOIN inventory_items i ON pri.item_id = i.id LEFT JOIN units u ON i.unit_id = u.id WHERE pri.request_id = ?");
$itemsStmt->execute([$id]);
$reqItems = $itemsStmt->fetchAll();
$canApprove = in_array($_SESSION['role'] ?? '', ['Administrator', 'Inventory Manager']);

$timeline = [
    ['status' => 'Pending', 'label' => 'Request Created', 'date' => $req['created_at'], 'icon' => 'fa-file', 'color' => 'text-yellow-500 bg-yellow-100'],
];
if ($req['status'] === 'Approved' || $req['status'] === 'Released' || $req['status'] === 'Completed') {
    $timeline[] = ['status' => 'Approved', 'label' => 'Approved', 'date' => $req['approved_date'], 'icon' => 'fa-check', 'color' => 'text-blue-500 bg-blue-100'];
}
if ($req['status'] === 'Released' || $req['status'] === 'Completed') {
    $timeline[] = ['status' => 'Released', 'label' => 'Released to Production', 'date' => $req['approved_date'], 'icon' => 'fa-paper-plane', 'color' => 'text-indigo-500 bg-indigo-100'];
}
if ($req['status'] === 'Completed') {
    $timeline[] = ['status' => 'Completed', 'label' => 'Completed', 'date' => $req['completed_date'], 'icon' => 'fa-flag', 'color' => 'text-emerald-500 bg-emerald-100'];
}
if ($req['status'] === 'Rejected') {
    $timeline[] = ['status' => 'Rejected', 'label' => 'Rejected', 'date' => $req['approved_date'], 'icon' => 'fa-times', 'color' => 'text-red-500 bg-red-100'];
}
?>
<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <p class="text-xs text-gray-500">Request Number</p>
            <p class="text-sm font-mono font-bold text-gray-900"><?php echo h($req['request_number']); ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Status</p>
            <p><span class="px-2 py-1 text-xs rounded-full <?php echo getStatusBadge($req['status']); ?>"><?php echo $req['status']; ?></span></p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Requested By</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($req['requested_by_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Department</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($req['department'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Request Date</p>
            <p class="text-sm font-medium text-gray-800"><?php echo formatDate($req['request_date']); ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-500">Approved By</p>
            <p class="text-sm font-medium text-gray-800"><?php echo h($req['approved_by_name'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <?php if ($req['purpose']): ?>
    <div>
        <p class="text-xs text-gray-500 mb-1">Purpose</p>
        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(h($req['purpose'])); ?></p>
    </div>
    <?php endif; ?>

    <div>
        <h5 class="font-semibold text-gray-800 mb-3">Requested Items</h5>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500 uppercase bg-gray-50">
                <th class="px-3 py-2">Item</th><th class="px-3 py-2">Code</th><th class="px-3 py-2">Qty Requested</th><th class="px-3 py-2">Qty Released</th><th class="px-3 py-2">Available</th><th class="px-3 py-2">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($reqItems as $ri): 
                    $released = (float)$ri['quantity_released'];
                    $requested = (float)$ri['quantity_requested'];
                ?>
                <tr class="text-sm text-gray-700">
                    <td class="px-3 py-2 font-medium"><?php echo h($ri['item_name'] ?? 'Deleted Item'); ?></td>
                    <td class="px-3 py-2 font-mono text-xs"><?php echo h($ri['item_code'] ?? 'N/A'); ?></td>
                    <td class="px-3 py-2"><?php echo formatNumber($ri['quantity_requested']); ?></td>
                    <td class="px-3 py-2"><?php echo formatNumber($ri['quantity_released']); ?></td>
                    <td class="px-3 py-2"><?php echo formatNumber($ri['current_stock'] ?? 0); ?> <?php echo h($ri['abbreviation'] ?? ''); ?></td>
                    <td class="px-3 py-2">
                        <?php if ($released >= $requested): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Released</span>
                        <?php elseif ($released > 0): ?>
                        <span class="px-1.5 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800">Partial</span>
                        <?php else: ?>
                        <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div>
        <h5 class="font-semibold text-gray-800 mb-3">Status Timeline</h5>
        <div class="space-y-3">
            <?php foreach ($timeline as $step): ?>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 rounded-full <?php echo $step['color']; ?> flex items-center justify-center">
                    <i class="fas <?php echo $step['icon']; ?> text-xs"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-800"><?php echo $step['label']; ?></p>
                    <p class="text-xs text-gray-500"><?php echo $step['date'] ? formatDateTime($step['date']) : 'N/A'; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($req['remarks']): ?>
    <div>
        <p class="text-xs text-gray-500 mb-1">Remarks</p>
        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(h($req['remarks'])); ?></p>
    </div>
    <?php endif; ?>
</div>
