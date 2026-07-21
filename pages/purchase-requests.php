<?php
$page = 'purchase-requests'; ?>
<style>
html.dark .bg-white.rounded-xl { background-color: #1e293b !important; border-color: #334155 !important; }
html.dark .text-gray-500 { color: #94a3b8 !important; }
html.dark .text-gray-800 { color: #e2e8f0 !important; }
</style>
<?php

function generatePRNumber($pdo) {
    $y = date('Y'); $m = date('m');
    $s = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests WHERE YEAR(created_at)=?");
    $s->execute([$y]);
    return 'PR-'.$y.$m.'-'.str_pad($s->fetchColumn()+1,4,'0',STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $prNo = generatePRNumber($pdo);
        $department = trim($_POST['department']);
        $purpose = trim($_POST['purpose']);
        $items = $_POST['items'] ?? [];
        $estTotal = 0;

        $stmt = $pdo->prepare("INSERT INTO purchase_requests (pr_number, requestor_id, department, purpose, status, estimated_total) VALUES (?, ?, ?, ?, 'Draft', ?)");
        $stmt->execute([$prNo, $userId, $department, $purpose, 0]);
        $prId = $pdo->lastInsertId();

        foreach ($items as $item) {
            if (!empty($item['name']) && (float)($item['qty'] ?? 0) > 0) {
                $total = (float)$item['qty'] * (float)($item['cost'] ?? 0);
                $estTotal += $total;
                $stmt = $pdo->prepare("INSERT INTO purchase_request_items (pr_id, item_name, item_code, category_id, unit_id, quantity_requested, estimated_cost, total_estimated) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$prId, $item['name'], $item['code'] ?? '', (int)($item['category_id'] ?? 0) ?: null, (int)($item['unit_id'] ?? 0) ?: null, (float)$item['qty'], (float)($item['cost'] ?? 0), $total]);
            }
        }

        $stmt = $pdo->prepare("UPDATE purchase_requests SET estimated_total = ? WHERE id = ?");
        $stmt->execute([$estTotal, $prId]);

        logAudit($pdo, 'Insert', 'Purchase Requests', 'Created PR: ' . $prNo);
        echo '<script>window.location.href="index?page=purchase-requests&success=' . urlencode('PR created successfully') . '";</script>'; exit;
    }

    if ($action === 'submit') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Submitted' WHERE id=? AND status='Draft'");
        $stmt->execute([$id]);
        logAudit($pdo, 'Submit', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' submitted');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR submitted for approval";</script>'; exit;
    }

    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        $level = $_POST['level'];
        $comments = trim($_POST['comments'] ?? '');
        $statusMap = ['dept' => 'Department Approved', 'inventory' => 'Inventory Approved', 'admin' => 'Administrator Approved'];
        $approvalLevelMap = ['dept' => 'Department', 'inventory' => 'Inventory Manager', 'admin' => 'Administrator'];
        $colMap = ['dept' => 'approved_by_dept', 'inventory' => 'approved_by_inventory', 'admin' => 'approved_by_admin'];
        $dateMap = ['dept' => 'approved_date_dept', 'inventory' => 'approved_date_inventory', 'admin' => 'approved_date_admin'];

        $newStatus = $statusMap[$level];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status=?, {$colMap[$level]}=?, {$dateMap[$level]}=NOW() WHERE id=?");
        $stmt->execute([$newStatus, $userId, $id]);

        $stmt = $pdo->prepare("INSERT INTO purchase_request_approvals (pr_id, approver_id, approval_level, action, comments) VALUES (?, ?, ?, 'Approved', ?)");
        $stmt->execute([$id, $userId, $approvalLevelMap[$level], $comments]);

        logAudit($pdo, 'Approve', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' approved at ' . $level . ' level');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR approved";</script>'; exit;
    }

    if ($action === 'reject') {
        $id = (int)$_POST['id'];
        $level = $_POST['level'] ?? 'admin';
        $comments = trim($_POST['comments'] ?? '');
        $approvalLevelMap = ['dept' => 'Department', 'inventory' => 'Inventory Manager', 'admin' => 'Administrator'];

        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Rejected', rejection_reason=? WHERE id=?");
        $stmt->execute([$comments, $id]);

        $stmt = $pdo->prepare("INSERT INTO purchase_request_approvals (pr_id, approver_id, approval_level, action, comments) VALUES (?, ?, ?, 'Rejected', ?)");
        $stmt->execute([$id, $userId, $approvalLevelMap[$level], $comments]);

        logAudit($pdo, 'Reject', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' rejected');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR rejected";</script>'; exit;
    }

    if ($action === 'cancel') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Cancelled' WHERE id=?");
        $stmt->execute([$id]);
        logAudit($pdo, 'Cancel', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' cancelled');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR cancelled";</script>'; exit;
    }

    if ($action === 'purchase') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Purchased' WHERE id=? AND status='Administrator Approved'");
        $stmt->execute([$id]);
        logAudit($pdo, 'Purchase', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' marked as purchased');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR marked as purchased";</script>'; exit;
    }

    if ($action === 'receive') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Received', received_date=NOW() WHERE id=? AND status='Purchased'");
        $stmt->execute([$id]);
        logAudit($pdo, 'Receive', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' received');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR marked as received";</script>'; exit;
    }

    if ($action === 'complete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status='Completed', completed_date=NOW() WHERE id=? AND status='Received'");
        $stmt->execute([$id]);
        logAudit($pdo, 'Complete', 'Purchase Requests', 'PR #' . ($_POST['pr_number'] ?? '') . ' completed');
        echo '<script>window.location.href="index?page=purchase-requests&success=PR completed";</script>'; exit;
    }
}

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

// AJAX handler for view modal
if (isset($_GET['ajax']) && $_GET['ajax'] === 'view') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT pr.*, u.full_name as requestor_name FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id=u.id WHERE pr.id=?");
    $stmt->execute([$id]);
    $pr = $stmt->fetch();
    if (!$pr) { echo '<div class="text-center py-8 text-gray-500">Purchase request not found.</div>'; exit; }

    $stmt = $pdo->prepare("SELECT pri.*, c.category_name, u.unit_name, u.abbreviation FROM purchase_request_items pri LEFT JOIN categories c ON pri.category_id=c.id LEFT JOIN units u ON pri.unit_id=u.id WHERE pri.pr_id=? ORDER BY pri.id");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT pra.*, us.full_name as approver_name FROM purchase_request_approvals pra LEFT JOIN users us ON pra.approver_id=us.id WHERE pra.pr_id=? ORDER BY pra.created_at");
    $stmt->execute([$id]);
    $approvals = $stmt->fetchAll();

    $role = $_SESSION['role'] ?? '';
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
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Department</p><p class="text-sm font-semibold text-gray-800"><?php echo h($pr['department'] ?: 'N/A'); ?></p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Estimated Total</p><p class="text-sm font-semibold text-gray-800"><?php echo formatCurrency($pr['estimated_total']); ?></p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Items</p><p class="text-sm font-semibold text-gray-800"><?php echo count($items); ?> item(s)</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Last Updated</p><p class="text-sm font-semibold text-gray-800"><?php echo formatDateTime($pr['updated_at'], 'M d, Y h:i A'); ?></p></div>
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

        <div>
            <h5 class="font-semibold text-gray-800 mb-2 flex items-center"><i class="fas fa-list text-gray-400 mr-2"></i> Items</h5>
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
            <h5 class="font-semibold text-gray-800 mb-2 flex items-center"><i class="fas fa-history text-gray-400 mr-2"></i> Approval Timeline</h5>
            <?php if (count($approvals) > 0): ?>
            <div class="space-y-2">
                <?php foreach ($approvals as $a): ?>
                <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center <?php echo $a['action'] == 'Approved' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas <?php echo $a['action'] == 'Approved' ? 'fa-check' : 'fa-times'; ?> text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-800"><?php echo h($a['approver_name']); ?> <span class="text-xs font-normal text-gray-500">- <?php echo h($a['approval_level']); ?></span></p>
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
    </div>
    <?php
    exit;
}

$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status='Active' ORDER BY category_name")->fetchAll();
$units = $pdo->query("SELECT id, unit_name, abbreviation FROM units ORDER BY unit_name")->fetchAll();

$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$q = "SELECT pr.*, u.full_name as requestor_name, (SELECT COUNT(*) FROM purchase_request_items WHERE pr_id = pr.id) as items_count FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id=u.id WHERE 1=1";
$params = [];
if ($statusFilter) { $q .= " AND pr.status=?"; $params[]=$statusFilter; }
if ($searchFilter) { $q .= " AND (pr.pr_number LIKE ? OR u.full_name LIKE ? OR pr.department LIKE ?)"; $params[]="%$searchFilter%"; $params[]="%$searchFilter%"; $params[]="%$searchFilter%"; }
$q .= " ORDER BY pr.created_at DESC";
$requests = $pdo->prepare($q); $requests->execute($params); $requests = $requests->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM purchase_requests GROUP BY status")->fetchAll();
$stats = ['Draft'=>0,'Submitted'=>0,'Department Approved'=>0,'Inventory Approved'=>0,'Administrator Approved'=>0,'Purchased'=>0,'Received'=>0,'Completed'=>0,'Rejected'=>0,'Cancelled'=>0];
foreach ($counts as $c) { $stats[$c['status']] = (int)$c['cnt']; }

$role = $_SESSION['role'] ?? '';

$success = $_GET['success'] ?? ''; $error = $_GET['error'] ?? '';
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

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer" onclick="window.location='index?page=purchase-requests&status=Draft'">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Draft</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $stats['Draft']; ?></p></div>
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center"><i class="fas fa-pen text-gray-500"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer" onclick="window.location='index?page=purchase-requests&status=Submitted'">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Submitted</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $stats['Submitted']; ?></p></div>
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-paper-plane text-blue-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer" onclick="window.location='index?page=purchase-requests&status=Department Approved'">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Approved</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $stats['Department Approved'] + $stats['Inventory Approved'] + $stats['Administrator Approved']; ?></p></div>
            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer" onclick="window.location='index?page=purchase-requests&status=Rejected'">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Rejected</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $stats['Rejected']; ?></p></div>
            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center"><i class="fas fa-times-circle text-red-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer" onclick="window.location='index?page=purchase-requests'">
        <div class="flex items-center justify-between">
            <div><p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total</p><p class="text-2xl font-bold text-gray-800 mt-1"><?php echo array_sum($stats); ?></p></div>
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center"><i class="fas fa-clipboard-list text-indigo-600"></i></div>
        </div>
    </div>
</div>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <select id="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Statuses</option>
            <option value="Draft" <?php echo $statusFilter=='Draft'?'selected':''; ?>>Draft</option>
            <option value="Submitted" <?php echo $statusFilter=='Submitted'?'selected':''; ?>>Submitted</option>
            <option value="Department Approved" <?php echo $statusFilter=='Department Approved'?'selected':''; ?>>Department Approved</option>
            <option value="Inventory Approved" <?php echo $statusFilter=='Inventory Approved'?'selected':''; ?>>Inventory Approved</option>
            <option value="Administrator Approved" <?php echo $statusFilter=='Administrator Approved'?'selected':''; ?>>Administrator Approved</option>
            <option value="Purchased" <?php echo $statusFilter=='Purchased'?'selected':''; ?>>Purchased</option>
            <option value="Received" <?php echo $statusFilter=='Received'?'selected':''; ?>>Received</option>
            <option value="Completed" <?php echo $statusFilter=='Completed'?'selected':''; ?>>Completed</option>
            <option value="Rejected" <?php echo $statusFilter=='Rejected'?'selected':''; ?>>Rejected</option>
            <option value="Cancelled" <?php echo $statusFilter=='Cancelled'?'selected':''; ?>>Cancelled</option>
        </select>
        <input type="text" id="filterSearch" placeholder="Search PR, requestor, dept..." value="<?php echo h($searchFilter); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onkeyup="applyFilters()">
    </div>
    <button onclick="openCreateModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-plus mr-1"></i> New Purchase Request
    </button>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">PR #</th>
                    <th class="px-4 py-3">Requestor</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Purpose</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-right">Est. Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($requests as $r): ?>
                <?php
                $canSubmit = $r['status'] === 'Draft' && ($r['requestor_id'] == $userId || $role === 'Administrator');
                $canApproveDept = $r['status'] === 'Submitted';
                $canApproveInv = $r['status'] === 'Department Approved' && in_array($role, ['Administrator', 'Inventory Manager']);
                $canApproveAdmin = $r['status'] === 'Inventory Approved' && $role === 'Administrator';
                $canPurchase = $r['status'] === 'Administrator Approved' && in_array($role, ['Administrator', 'Inventory Manager']);
                $canReceive = $r['status'] === 'Purchased' && in_array($role, ['Administrator', 'Inventory Manager']);
                $canComplete = $r['status'] === 'Received' && in_array($role, ['Administrator', 'Inventory Manager']);
                $canCancel = in_array($r['status'], ['Draft','Submitted','Department Approved','Inventory Approved','Administrator Approved','Purchased']) && ($r['requestor_id'] == $userId || $role === 'Administrator');
                $canReject = ($r['status'] === 'Submitted') || ($r['status'] === 'Department Approved' && in_array($role, ['Administrator', 'Inventory Manager'])) || ($r['status'] === 'Inventory Approved' && $role === 'Administrator');
                $showActions = $canSubmit || $canApproveDept || $canApproveInv || $canApproveAdmin || $canPurchase || $canReceive || $canComplete || $canCancel || $canReject;
                ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-900"><?php echo h($r['pr_number']); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo h($r['requestor_name'] ?? 'Unknown'); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['department'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3 max-w-[200px] truncate" title="<?php echo h($r['purpose']); ?>"><?php echo h($r['purpose'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3 text-center font-medium"><?php echo (int)$r['items_count']; ?></td>
                    <td class="px-4 py-3 text-right font-medium"><?php echo formatCurrency($r['estimated_total']); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $statusBadges[$r['status']] ?? 'bg-gray-100 text-gray-800'; ?>"><?php echo $r['status']; ?></span></td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs"><?php echo formatDateTime($r['created_at'], 'M d, Y'); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap text-center">
                        <button onclick="viewPR(<?php echo $r['id']; ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View"><i class="fas fa-eye"></i></button>
                        <?php if ($canSubmit): ?>
                        <button onclick="doAction('submit',<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>')" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Submit"><i class="fas fa-paper-plane"></i></button>
                        <?php endif; ?>
                        <?php if ($canApproveDept): ?>
                        <button onclick="openApproveModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','dept')" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Department Approve"><i class="fas fa-check"></i></button>
                        <button onclick="openRejectModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','dept')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                        <?php if ($canApproveInv): ?>
                        <button onclick="openApproveModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','inventory')" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" title="Inventory Approve"><i class="fas fa-check-double"></i></button>
                        <button onclick="openRejectModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','inventory')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                        <?php if ($canApproveAdmin): ?>
                        <button onclick="openApproveModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','admin')" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Admin Approve"><i class="fas fa-check-circle"></i></button>
                        <button onclick="openRejectModal(<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>','admin')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                        <?php if ($canPurchase): ?>
                        <button onclick="doAction('purchase',<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>')" class="p-1.5 text-cyan-600 hover:bg-cyan-50 rounded-lg transition-colors" title="Mark Purchased"><i class="fas fa-shopping-cart"></i></button>
                        <?php endif; ?>
                        <?php if ($canReceive): ?>
                        <button onclick="doAction('receive',<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>')" class="p-1.5 text-teal-600 hover:bg-teal-50 rounded-lg transition-colors" title="Mark Received"><i class="fas fa-truck-loading"></i></button>
                        <?php endif; ?>
                        <?php if ($canComplete): ?>
                        <button onclick="doAction('complete',<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>')" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Complete"><i class="fas fa-check-circle"></i></button>
                        <?php endif; ?>
                        <?php if ($canCancel): ?>
                        <button onclick="doAction('cancel',<?php echo $r['id']; ?>,'<?php echo h($r['pr_number']); ?>')" class="p-1.5 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors" title="Cancel"><i class="fas fa-ban"></i></button>
                        <?php endif; ?>
                        <?php if (!$showActions): ?>
                        <span class="text-xs text-gray-400">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($requests) == 0): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-500">No purchase requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="createModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeCreateModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">New Purchase Request</h3>
                <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="createForm">
                <input type="hidden" name="action" value="create">
                <?php echo csrfField(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                        <input type="text" name="department" id="prDepartment" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g. College of Engineering">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose / Justification <span class="text-red-500">*</span></label>
                        <input type="text" name="purpose" id="prPurpose" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Purpose of this request">
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">Items</label>
                        <button type="button" onclick="addItemRow()" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium"><i class="fas fa-plus mr-1"></i> Add Item</button>
                    </div>
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm" id="itemsTable">
                                <thead>
                                    <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="px-3 py-2.5 w-[200px]">Item Name</th>
                                        <th class="px-3 py-2.5 w-[100px]">Code</th>
                                        <th class="px-3 py-2.5 w-[140px]">Category</th>
                                        <th class="px-3 py-2.5 w-[100px]">Unit</th>
                                        <th class="px-3 py-2.5 w-[80px] text-center">Qty</th>
                                        <th class="px-3 py-2.5 w-[100px] text-right">Est. Cost</th>
                                        <th class="px-3 py-2.5 w-[100px] text-right">Total</th>
                                        <th class="px-3 py-2.5 w-[40px]"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100" id="itemsBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex justify-end items-center mt-3 px-1">
                        <span class="text-sm text-gray-500 mr-3">Estimated Total:</span>
                        <span class="text-lg font-bold text-gray-900" id="grandTotalDisplay">₱0.00</span>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-save mr-1"></i> Create PR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeViewModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">Purchase Request Details</h3>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6" id="viewContent">
                <div class="text-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading...</div>
            </div>
        </div>
    </div>
</div>

<div id="approveModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeApproveModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Approve Request</h3>
                <button onclick="closeApproveModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveId" value="">
                <input type="hidden" name="pr_number" id="approvePrNumber" value="">
                <input type="hidden" name="level" id="approveLevel" value="">
                <?php echo csrfField(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Approval Level</label>
                    <p id="approveLevelDisplay" class="text-sm font-semibold text-gray-900 bg-gray-50 px-3 py-2 rounded-lg border border-gray-200"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comments (optional)</label>
                    <textarea name="comments" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Add any comments..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeApproveModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-check mr-1"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="rejectModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeRejectModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Reject Request</h3>
                <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectId" value="">
                <input type="hidden" name="pr_number" id="rejectPrNumber" value="">
                <input type="hidden" name="level" id="rejectLevel" value="">
                <?php echo csrfField(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection <span class="text-red-500">*</span></label>
                    <textarea name="comments" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-red-500 focus:border-red-500" placeholder="Specify the reason..."></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors"><i class="fas fa-times mr-1"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var itemRowIndex = 0;
var categories = <?php echo json_encode($categories); ?>;
var units = <?php echo json_encode($units); ?>;

function addItemRow(data) {
    var idx = itemRowIndex++;
    var catOpts = '<option value="">Select</option>';
    categories.forEach(function(c) { catOpts += '<option value="' + c.id + '">' + c.category_name + '</option>'; });
    var unitOpts = '<option value="">Select</option>';
    units.forEach(function(u) { unitOpts += '<option value="' + u.id + '">' + u.unit_name + '</option>'; });

    var html = '<tr id="itemRow' + idx + '" class="hover:bg-gray-50">';
    html += '<td class="px-3 py-2"><input type="text" name="items[' + idx + '][name]" value="' + (data ? data.item_name||'' : '') + '" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Item name" required></td>';
    html += '<td class="px-3 py-2"><input type="text" name="items[' + idx + '][code]" value="' + (data ? data.item_code||'' : '') + '" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Code"></td>';
    html += '<td class="px-3 py-2"><select name="items[' + idx + '][category_id]" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500">' + catOpts + '</select></td>';
    html += '<td class="px-3 py-2"><select name="items[' + idx + '][unit_id]" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500">' + unitOpts + '</select></td>';
    html += '<td class="px-3 py-2"><input type="number" name="items[' + idx + '][qty]" step="0.01" min="0" value="' + (data ? data.quantity_requested||1 : 1) + '" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-center" onchange="calcRowTotal(' + idx + ')" onkeyup="calcRowTotal(' + idx + ')"></td>';
    html += '<td class="px-3 py-2"><input type="number" name="items[' + idx + '][cost]" step="0.01" min="0" value="' + (data ? data.estimated_cost||0 : 0) + '" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-emerald-500 focus:border-emerald-500 text-right" onchange="calcRowTotal(' + idx + ')" onkeyup="calcRowTotal(' + idx + ')"></td>';
    html += '<td class="px-3 py-2 text-right font-medium text-gray-800" id="rowTotal' + idx + '">₱0.00</td>';
    html += '<td class="px-3 py-2 text-center"><button type="button" onclick="removeItemRow(' + idx + ')" class="text-red-400 hover:text-red-600 transition-colors"><i class="fas fa-trash-alt"></i></button></td>';
    html += '</tr>';
    $('#itemsBody').append(html);
    if (data) calcRowTotal(idx);
    calcGrandTotal();
}

function removeItemRow(idx) {
    $('#itemRow' + idx).remove();
    calcGrandTotal();
}

function calcRowTotal(idx) {
    var qty = parseFloat($('input[name="items[' + idx + '][qty]"]').val()) || 0;
    var cost = parseFloat($('input[name="items[' + idx + '][cost]"]').val()) || 0;
    var total = qty * cost;
    $('#rowTotal' + idx).text('₱' + total.toFixed(2));
    calcGrandTotal();
}

function calcGrandTotal() {
    var grand = 0;
    $('#itemsBody tr').each(function() {
        var qty = parseFloat($(this).find('input[name$="[qty]"]').val()) || 0;
        var cost = parseFloat($(this).find('input[name$="[cost]"]').val()) || 0;
        grand += qty * cost;
    });
    $('#grandTotalDisplay').text('₱' + grand.toFixed(2));
}

function openCreateModal() {
    $('#createModal').removeClass('hidden');
    $('#itemsBody').empty();
    itemRowIndex = 0;
    addItemRow();
    calcGrandTotal();
}

function closeCreateModal() {
    $('#createModal').addClass('hidden');
}

function viewPR(id) {
    $('#viewModal').removeClass('hidden');
    $('#viewContent').html('<div class="text-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading...</div>');
    $.ajax({
        url: 'index?page=purchase-requests&ajax=view&id=' + id,
        success: function(html) { $('#viewContent').html(html); },
        error: function() { $('#viewContent').html('<div class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> Failed to load PR details.</div>'); }
    });
}

function closeViewModal() {
    $('#viewModal').addClass('hidden');
}

function openApproveModal(id, prNo, level) {
    $('#approveId').val(id);
    $('#approvePrNumber').val(prNo);
    $('#approveLevel').val(level);
    var labels = {dept: 'Department Approval', inventory: 'Inventory Manager Approval', admin: 'Administrator Approval'};
    $('#approveLevelDisplay').text(labels[level] || level);
    $('#approveModal').removeClass('hidden');
}

function closeApproveModal() {
    $('#approveModal').addClass('hidden');
}

function openRejectModal(id, prNo, level) {
    $('#rejectId').val(id);
    $('#rejectPrNumber').val(prNo);
    $('#rejectLevel').val(level);
    $('#rejectModal').removeClass('hidden');
}

function closeRejectModal() {
    $('#rejectModal').addClass('hidden');
}

function doAction(action, id, prNo) {
    var labels = {submit: 'submit', cancel: 'cancel', purchase: 'mark as purchased', receive: 'mark as received', complete: 'mark as complete'};
    var msg = 'Are you sure you want to ' + (labels[action] || action) + ' this PR?';
    if (confirm(msg)) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="action" value="' + action + '"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="pr_number" value="' + prNo.replace(/'/g, "\\'") + '"><?php echo csrfField(); ?>';
        document.body.appendChild(f);
        f.submit();
    }
}

function applyFilters() {
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('filterSearch').value;
    var url = 'index?page=purchase-requests';
    if (status) url += '&status=' + encodeURIComponent(status);
    if (search) url += '&search=' + encodeURIComponent(search);
    window.location.href = url;
}

function closeModal() {
    closeCreateModal();
    closeViewModal();
    closeApproveModal();
    closeRejectModal();
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>


