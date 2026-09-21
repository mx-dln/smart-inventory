<?php
$page = 'borrow';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'borrow') {
        $itemId = (int)$_POST['item_id'];
        $borrower = trim($_POST['borrower_name']);
        $department = trim($_POST['department'] ?? '');
        $qty = (float)$_POST['quantity_borrowed'];
        $expectedReturn = $_POST['expected_return_date'];
        $conditionBefore = trim($_POST['condition_before'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) throw new Exception('Item not found');
            if ((float)$item['current_stock'] < $qty) throw new Exception('Insufficient stock. Available: ' . $item['current_stock']);

            $stmt = $pdo->prepare("INSERT INTO borrow_records (item_id, borrower_name, department, borrow_date, expected_return_date, quantity_borrowed, condition_before, remarks, created_by) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([$itemId, $borrower, $department, $expectedReturn, $qty, $conditionBefore, $remarks, $userId]);

            $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$qty, $itemId]);

            logAudit($pdo, 'Insert', 'Borrow', 'Item borrowed: ' . $borrower . ', Qty: ' . $qty);
            $pdo->commit();
            echo '<script>window.location.href="index?page=borrow&success=' . urlencode('Borrow recorded successfully') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=borrow&error=' . urlencode($e->getMessage()) . '";</script>';
            exit;
        }
    }

    if ($action === 'return') {
        $id = (int)$_POST['id'];
        $conditionAfter = trim($_POST['condition_after'] ?? '');
        $returnedById = (int)($_POST['returned_by'] ?? $userId);
        $receivedById = (int)($_POST['received_by'] ?? $userId);
        $penalty = (float)($_POST['penalty'] ?? 0);
        $penaltyReason = trim($_POST['penalty_reason'] ?? '');
        $status = $_POST['status'] ?? 'Returned';
        $qtyReturned = (float)($_POST['quantity_returned'] ?? 0);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT item_id, quantity_borrowed FROM borrow_records WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) throw new Exception('Record not found');

            $returnQty = $qtyReturned > 0 ? $qtyReturned : $record['quantity_borrowed'];

            $stmt = $pdo->prepare("UPDATE borrow_records SET actual_return_date=NOW(), condition_after=?, returned_by=?, received_by=?, penalty=?, penalty_reason=?, status=?, quantity_returned=? WHERE id=?");
            $stmt->execute([$conditionAfter, $returnedById, $receivedById, $penalty, $penaltyReason, $status, $returnQty, $id]);

            if ($status === 'Returned' && $returnQty > 0) {
                $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$returnQty, $record['item_id']]);
            }

            logAudit($pdo, 'Update', 'Borrow', 'Item returned, record ID: ' . $id);
            $pdo->commit();
            echo '<script>window.location.href="index?page=borrow&success=' . urlencode('Return processed successfully') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=borrow&error=' . urlencode($e->getMessage()) . '";</script>';
            exit;
        }
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $itemId = (int)$_POST['item_id'];
        $borrower = trim($_POST['borrower_name']);
        $department = trim($_POST['department'] ?? '');
        $qty = (float)$_POST['quantity_borrowed'];
        $expectedReturn = $_POST['expected_return_date'];
        $conditionBefore = trim($_POST['condition_before'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT quantity_borrowed FROM borrow_records WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if (!$old) throw new Exception('Record not found');

            $diff = $qty - $old['quantity_borrowed'];

            if ($diff != 0) {
                $stmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ? FOR UPDATE");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();
                if ($diff > 0 && (float)$item['current_stock'] < $diff) {
                    throw new Exception('Insufficient stock. Available: ' . $item['current_stock']);
                }
                $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$diff, $itemId]);
            }

            $stmt = $pdo->prepare("UPDATE borrow_records SET item_id=?, borrower_name=?, department=?, expected_return_date=?, quantity_borrowed=?, condition_before=?, remarks=? WHERE id=?");
            $stmt->execute([$itemId, $borrower, $department, $expectedReturn, $qty, $conditionBefore, $remarks, $id]);

            logAudit($pdo, 'Update', 'Borrow', 'Updated borrow record ID: ' . $id);
            $pdo->commit();
            echo '<script>window.location.href="index?page=borrow&success=' . urlencode('Record updated successfully') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=borrow&error=' . urlencode($e->getMessage()) . '";</script>';
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT item_id, quantity_borrowed, status FROM borrow_records WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) throw new Exception('Record not found');

            if ($record['status'] === 'Borrowed' || $record['status'] === 'Overdue') {
                throw new Exception('Cannot delete active borrow records. Process a return first.');
            }

            $stmt = $pdo->prepare("DELETE FROM borrow_records WHERE id = ?");
            $stmt->execute([$id]);

            logAudit($pdo, 'Delete', 'Borrow', 'Deleted borrow record ID: ' . $id);
            $pdo->commit();
            echo '<script>window.location.href="index?page=borrow&success=' . urlencode('Record deleted') . '";</script>';
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<script>window.location.href="index?page=borrow&error=' . urlencode($e->getMessage()) . '";</script>';
            exit;
        }
    }
}

// AJAX handlers
if (isset($_GET['ajax']) && $_GET['ajax'] === 'active_borrows') {
    $stmt = $pdo->query("SELECT br.id, br.borrower_name, br.borrow_date, br.expected_return_date, i.name as item_name 
                         FROM borrow_records br 
                         LEFT JOIN inventory_items i ON br.item_id=i.id 
                         WHERE br.status IN ('Borrowed','Overdue') 
                         ORDER BY br.borrow_date DESC");
    $active = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($active);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'find_by_item') {
    $itemId = (int)$_GET['item_id'];
    $stmt = $pdo->prepare("SELECT br.*, i.name as item_name FROM borrow_records br LEFT JOIN inventory_items i ON br.item_id=i.id WHERE br.item_id=? AND br.status IN ('Borrowed','Overdue') ORDER BY br.borrow_date DESC");
    $stmt->execute([$itemId]);
    $records = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($records);
    exit;
}

// Dashboard counters
$borrowCount = $pdo->query("SELECT COUNT(*) FROM borrow_records")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status='Borrowed' AND expected_return_date < CURDATE()")->fetchColumn();
$borrowedCount = $pdo->query("SELECT COUNT(*) FROM borrow_records WHERE status='Borrowed'")->fetchColumn();
$returnedToday = $pdo->query("SELECT COUNT(*) FROM borrow_records WHERE DATE(actual_return_date) = CURDATE()")->fetchColumn();

$items = $pdo->query("SELECT id, name, item_code, current_stock, (SELECT abbreviation FROM units WHERE id=unit_id) as unit FROM inventory_items WHERE status='Active' ORDER BY name")->fetchAll();

$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$q = "SELECT br.*, i.name as item_name, i.item_code, 
      rb.full_name as returned_by_name, rc.full_name as received_by_name
      FROM borrow_records br 
      LEFT JOIN inventory_items i ON br.item_id=i.id 
      LEFT JOIN users rb ON br.returned_by=rb.id 
      LEFT JOIN users rc ON br.received_by=rc.id 
      WHERE 1=1";
$params = [];
if ($statusFilter) { $q .= " AND br.status=?"; $params[]=$statusFilter; }
if ($searchFilter) { 
    $q .= " AND (br.borrower_name LIKE ? OR i.name LIKE ?)"; 
    $params[]="%$searchFilter%"; 
    $params[]="%$searchFilter%"; 
}
$q .= " ORDER BY br.created_at DESC";
$records = $pdo->prepare($q);
$records->execute($params);
$records = $records->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$users = $pdo->query("SELECT id, full_name, username FROM users WHERE status='Active' ORDER BY full_name")->fetchAll();

$statusColors = [
    'Borrowed' => 'bg-blue-100 text-blue-800',
    'Returned' => 'bg-emerald-100 text-emerald-800',
    'Overdue' => 'bg-red-100 text-red-800',
    'Lost' => 'bg-gray-100 text-gray-800',
    'Damaged' => 'bg-orange-100 text-orange-800',
];
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

<!-- Dashboard Counters -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-blue-600 font-medium">Total Borrowed</p>
                <p class="text-2xl font-bold text-blue-700 mt-1"><?php echo $borrowCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center">
                <i class="fas fa-hand-holding text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-emerald-600 font-medium">Currently Borrowed</p>
                <p class="text-2xl font-bold text-emerald-700 mt-1"><?php echo $borrowedCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-emerald-200 rounded-lg flex items-center justify-center">
                <i class="fas fa-clipboard-list text-emerald-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4 border border-red-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-red-600 font-medium">Overdue</p>
                <p class="text-2xl font-bold text-red-700 mt-1"><?php echo $overdueCount; ?></p>
            </div>
            <div class="w-10 h-10 bg-red-200 rounded-lg flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border border-purple-200">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-purple-600 font-medium">Returned Today</p>
                <p class="text-2xl font-bold text-purple-700 mt-1"><?php echo $returnedToday; ?></p>
            </div>
            <div class="w-10 h-10 bg-purple-200 rounded-lg flex items-center justify-center">
                <i class="fas fa-undo-alt text-purple-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Actions -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div class="flex flex-wrap items-center gap-2">
        <select id="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="Borrowed" <?php echo $statusFilter=='Borrowed'?'selected':''; ?>>Borrowed</option>
            <option value="Returned" <?php echo $statusFilter=='Returned'?'selected':''; ?>>Returned</option>
            <option value="Overdue" <?php echo $statusFilter=='Overdue'?'selected':''; ?>>Overdue</option>
            <option value="Lost" <?php echo $statusFilter=='Lost'?'selected':''; ?>>Lost</option>
            <option value="Damaged" <?php echo $statusFilter=='Damaged'?'selected':''; ?>>Damaged</option>
        </select>
        <input type="text" id="filterSearch" placeholder="Search borrower or item..." value="<?php echo h($searchFilter); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onkeyup="applyFilters()">
    </div>
    <div class="flex gap-2">
        <button onclick="printPage('borrowTable', 'Borrow Records Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openReturnModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-undo-alt mr-1"></i> Return
        </button>
        <button onclick="openBorrowModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Borrow
        </button>
    </div>
</div>

<!-- Table -->
<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="borrowTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Borrower</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Borrow Date</th>
                    <th class="px-4 py-3">Expected Return</th>
                    <th class="px-4 py-3">Actual Return</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Condition</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($records as $r):
                    $isOverdue = ($r['status'] === 'Borrowed' && strtotime($r['expected_return_date']) < strtotime(date('Y-m-d')));
                    $isTodayReturn = ($r['actual_return_date'] && date('Y-m-d', strtotime($r['actual_return_date'])) === date('Y-m-d'));
                ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50 <?php echo $isOverdue ? 'bg-red-50' : ($isTodayReturn ? 'bg-emerald-50' : ''); ?>">
                    <td class="px-4 py-3">
                        <span class="font-medium"><?php echo h($r['item_name'] ?? 'Unknown'); ?></span>
                        <span class="text-xs text-gray-400 block font-mono"><?php echo h($r['item_code'] ?? ''); ?></span>
                    </td>
                    <td class="px-4 py-3 font-medium"><?php echo h($r['borrower_name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['department'] ?? 'N/A'); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo formatDateTime($r['borrow_date'], 'M d, Y'); ?></td>
                    <td class="px-4 py-3 whitespace-nowrap <?php echo $isOverdue ? 'font-semibold text-red-600' : ''; ?>">
                        <?php echo formatDate($r['expected_return_date']); ?>
                        <?php if ($isOverdue): ?>
                        <span class="block text-xs text-red-500 font-medium">OVERDUE</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap"><?php echo $r['actual_return_date'] ? formatDateTime($r['actual_return_date'], 'M d, Y') : '<span class="text-gray-400">—</span>'; ?></td>
                    <td class="px-4 py-3"><?php echo formatNumber($r['quantity_borrowed']); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $statusColors[$r['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $r['status']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <?php if ($r['condition_before'] || $r['condition_after']): ?>
                        <span title="Before: <?php echo h($r['condition_before'] ?? 'N/A'); ?> | After: <?php echo h($r['condition_after'] ?? 'N/A'); ?>">
                            <?php echo h($r['condition_after'] ?? $r['condition_before'] ?? 'N/A'); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center space-x-1">
                            <button onclick="viewRecord(<?php echo $r['id']; ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i class="fas fa-eye"></i></button>
                            <?php if ($r['status'] === 'Borrowed' || $r['status'] === 'Overdue'): ?>
                            <button onclick="openReturnModal(<?php echo $r['id']; ?>)" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Return"><i class="fas fa-undo-alt"></i></button>
                            <button onclick="editRecord(<?php echo $r['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></button>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'Borrowed' && $r['status'] !== 'Overdue'): ?>
                            <button onclick="deleteRecord(<?php echo $r['id']; ?>)" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($records) == 0): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No borrow records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Borrow Modal -->
<div id="borrowModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('borrowModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900" id="borrowModalTitle">New Borrow Record</h3>
                <button onclick="closeModal('borrowModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="borrowForm">
                <input type="hidden" name="action" id="borrowFormAction" value="borrow">
                <input type="hidden" name="id" id="borrowId" value="">
                <?php echo csrfField(); ?>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <select name="item_id" id="borrowItemId" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Select Item</option>
                                <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['current_stock']; ?>"><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p id="borrowStockInfo" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Borrower Name <span class="text-red-500">*</span></label>
                            <input type="text" name="borrower_name" id="borrowerName" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Full name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <input type="text" name="department" id="department" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., FIC Lab">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Borrow Date <span class="text-red-500">*</span></label>
                            <input type="datetime-local" name="borrow_date" id="borrowDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expected Return Date <span class="text-red-500">*</span></label>
                            <input type="date" name="expected_return_date" id="expectedReturn" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity <span class="text-red-500">*</span></label>
                            <input type="number" name="quantity_borrowed" id="borrowQty" step="0.01" min="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Condition Before</label>
                            <select name="condition_before" id="conditionBefore" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Select Condition</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="New">New</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <textarea name="remarks" id="borrowRemarks" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional notes"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('borrowModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> <span id="borrowSubmitText">Record Borrow</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div id="returnModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('returnModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">Process Return</h3>
                <button onclick="closeModal('returnModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="returnForm">
                <input type="hidden" name="action" value="return">
                <input type="hidden" name="id" id="returnId" value="">
                <?php echo csrfField(); ?>

                <div id="returnRecordInfo" class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="text-center py-4"><i class="fas fa-spinner fa-spin text-gray-400"></i> <span class="text-sm text-gray-500 ml-2">Loading record...</span></div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Condition After</label>
                            <select name="condition_after" id="conditionAfter" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Select Condition</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Lost">Lost</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Returned</label>
                            <input type="number" name="quantity_returned" id="returnQty" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Returned By <span class="text-red-500">*</span></label>
                            <select name="returned_by" id="returnedBy" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="<?php echo $userId; ?>"><?php echo h($currentUser['full_name'] ?? $currentUser['username']); ?> (Me)</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo h($u['full_name'] ?: $u['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Received By <span class="text-red-500">*</span></label>
                            <select name="received_by" id="receivedBy" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="<?php echo $userId; ?>"><?php echo h($currentUser['full_name'] ?? $currentUser['username']); ?> (Me)</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo h($u['full_name'] ?: $u['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" id="returnStatus" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="togglePenalty()">
                            <option value="Returned">Returned</option>
                            <option value="Lost">Lost</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                    <div id="penaltySection" class="hidden p-4 bg-orange-50 rounded-lg border border-orange-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Penalty Amount (₱)</label>
                                <input type="number" name="penalty" id="penalty" step="0.01" min="0" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Penalty Reason</label>
                                <input type="text" name="penalty_reason" id="penaltyReason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Reason for penalty">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('returnModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check mr-1"></i> Process Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('viewModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900">Borrow Record Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div id="viewContent" class="p-6">
                <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#borrowDate').val(new Date().toISOString().slice(0, 16));
    <?php if (isset($_GET['return'])): ?>
    openReturnModal(<?php echo (int)$_GET['return']; ?>);
    <?php endif; ?>
});

$('#borrowItemId').on('change', function() {
    var opt = $(this).find('option:selected');
    var stock = opt.data('stock');
    $('#borrowStockInfo').text(stock !== undefined ? 'Available stock: ' + stock : '');
});

function openBorrowModal() {
    document.getElementById('borrowFormAction').value = 'borrow';
    document.getElementById('borrowModalTitle').textContent = 'New Borrow Record';
    document.getElementById('borrowForm').reset();
    document.getElementById('borrowId').value = '';
    document.getElementById('borrowSubmitText').textContent = 'Record Borrow';
    document.getElementById('borrowDate').value = new Date().toISOString().slice(0, 16);
    document.getElementById('borrowStockInfo').textContent = '';
    document.getElementById('borrowModal').classList.remove('hidden');
}

function editRecord(id) {
    document.getElementById('borrowFormAction').value = 'update';
    document.getElementById('borrowModalTitle').textContent = 'Edit Borrow Record';
    document.getElementById('borrowSubmitText').textContent = 'Update Record';

    $.ajax({
        url: 'ajax/get_borrow.php?id=' + id,
        dataType: 'json',
        success: function(r) {
            if (!r || r.error) { showToast('Failed to load record', 'error'); return; }
            document.getElementById('borrowId').value = r.id;
            document.getElementById('borrowItemId').value = r.item_id;
            document.getElementById('borrowerName').value = r.borrower_name;
            document.getElementById('department').value = r.department || '';
            document.getElementById('borrowDate').value = r.borrow_date ? r.borrow_date.substring(0, 16) : '';
            document.getElementById('expectedReturn').value = r.expected_return_date || '';
            document.getElementById('borrowQty').value = r.quantity_borrowed;
            document.getElementById('conditionBefore').value = r.condition_before || '';
            document.getElementById('borrowRemarks').value = r.remarks || '';
            $('#borrowItemId').trigger('change');
            document.getElementById('borrowModal').classList.remove('hidden');
        },
        error: function() { showToast('Failed to load record', 'error'); }
    });
}

function openReturnModal(id) {
    var modal = document.getElementById('returnModal');
    modal.classList.remove('hidden');

    if (id) {
        document.getElementById('returnId').value = id;
        document.getElementById('returnRecordInfo').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-gray-400"></i> <span class="text-sm text-gray-500 ml-2">Loading record...</span></div>';

        $.ajax({
            url: 'ajax/get_borrow.php?id=' + id,
            dataType: 'json',
            success: function(r) {
                if (!r || r.error) { closeModal('returnModal'); showToast('Record not found', 'error'); return; }
                document.getElementById('returnId').value = r.id;
                document.getElementById('returnQty').value = r.quantity_borrowed;
                var overdue = r.status === 'Overdue' || (r.status === 'Borrowed' && r.expected_return_date < new Date().toISOString().slice(0,10));
                var daysOverdue = 0;
                if (overdue && r.expected_return_date) {
                    var exp = new Date(r.expected_return_date);
                    var now = new Date();
                    daysOverdue = Math.floor((now - exp) / (1000 * 60 * 60 * 24));
                }
                document.getElementById('returnRecordInfo').innerHTML =
                    '<div class="grid grid-cols-2 gap-3 text-sm">' +
                    '<div><span class="text-gray-500">Item:</span> <span class="font-medium">' + (r.item_name || 'Unknown') + '</span></div>' +
                    '<div><span class="text-gray-500">Borrower:</span> <span class="font-medium">' + r.borrower_name + '</span></div>' +
                    '<div><span class="text-gray-500">Borrow Date:</span> ' + (r.borrow_date || 'N/A') + '</div>' +
                    '<div><span class="text-gray-500">Expected Return:</span> ' + (r.expected_return_date || 'N/A') +
                    (daysOverdue > 0 ? ' <span class="text-red-600 font-semibold">(' + daysOverdue + ' days overdue)</span>' : '') + '</div>' +
                    '<div><span class="text-gray-500">Qty Borrowed:</span> ' + r.quantity_borrowed + '</div>' +
                    '<div><span class="text-gray-500">Condition Before:</span> ' + (r.condition_before || 'N/A') + '</div>' +
                    '</div>';
                document.getElementById('returnForm').reset();
                document.getElementById('returnId').value = r.id;
                document.getElementById('returnQty').value = r.quantity_borrowed;
                togglePenalty();
            },
            error: function() { document.getElementById('returnRecordInfo').innerHTML = '<div class="text-center py-4 text-red-500">Failed to load record.</div>'; }
        });
    } else {
        document.getElementById('returnRecordInfo').innerHTML =
            '<div class="mb-4">' +
            '<label class="block text-sm font-medium text-gray-700 mb-1">Select Item <span class="text-red-500">*</span></label>' +
            '<select id="returnBorrowSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="loadReturnByBorrow(this.value)">' +
            '<option value="">Select a borrowed item...</option>' +
            '</select>' +
            '</div>' +
            '<div id="returnSelectInfo" class="text-center text-sm text-gray-400 py-4">Select a record above to process return.</div>';
        loadActiveBorrows();
    }
}

function loadActiveBorrows() {
    $.ajax({
        url: 'index?page=borrow&ajax=active_borrows',
        dataType: 'json',
        success: function(data) {
            var sel = document.getElementById('returnBorrowSelect');
            if (sel) {
                sel.innerHTML = '<option value="">Select a borrowed item...</option>';
                data.forEach(function(r) {
                    sel.innerHTML += '<option value="' + r.id + '">' + r.item_name + ' - ' + r.borrower_name + ' (' + r.borrow_date + ')</option>';
                });
            }
        }
    });
}

function loadReturnByBorrow(id) {
    if (id) {
        openReturnModal(parseInt(id));
    } else {
        document.getElementById('returnSelectInfo').innerHTML = '<div class="text-center text-sm text-gray-400 py-4">Select a record above to process return.</div>';
    }
}

function togglePenalty() {
    var status = document.getElementById('returnStatus').value;
    var section = document.getElementById('penaltySection');
    if (status === 'Lost' || status === 'Damaged') {
        section.classList.remove('hidden');
    } else {
        section.classList.add('hidden');
        document.getElementById('penalty').value = 0;
        document.getElementById('penaltyReason').value = '';
    }
}

function viewRecord(id) {
    document.getElementById('viewModal').classList.remove('hidden');
    document.getElementById('viewContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
    $.ajax({
        url: 'ajax/view_borrow.php?id=' + id,
        method: 'GET',
        success: function(html) {
            document.getElementById('viewContent').innerHTML = html;
        },
        error: function() {
            document.getElementById('viewContent').innerHTML = '<div class="text-center py-8 text-red-500">Failed to load record details.</div>';
        }
    });
}

function deleteRecord(id) {
    if (confirm('Delete this borrow record? This action cannot be undone.')) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(f);
        f.submit();
    }
}

function applyFilters() {
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('filterSearch').value;
    var url = 'index?page=borrow';
    if (status) url += '&status=' + status;
    if (search) url += '&search=' + encodeURIComponent(search);
    window.location.href = url;
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        $('.fixed.inset-0.z-50').each(function() {
            if (!$(this).hasClass('hidden') && $(this).find('.bg-gray-900').length) {
                $(this).addClass('hidden');
            }
        });
    }
});
</script>
