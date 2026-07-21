<?php
$page = 'production';

// Auto-generate request number
function genReqNo($pdo) {
    $y = date('Y'); $m = date('m');
    $s = $pdo->prepare("SELECT COUNT(*) FROM production_requests WHERE YEAR(created_at)=?");
    $s->execute([$y]);
    return 'PR-'.$y.$m.'-'.str_pad($s->fetchColumn()+1,4,'0',STR_PAD_LEFT);
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            echo '<script>window.location.href="index?page=production&error=Invalid token";</script>'; exit;
        }
        $reqNo = $_POST['request_number'] ?: genReqNo($pdo);
        $department = $_POST['department'];
        $purpose = $_POST['purpose'];
        $reqDate = $_POST['request_date'] ?? date('Y-m-d');
        $items = $_POST['items'] ?? [];
        
        $stmt = $pdo->prepare("INSERT INTO production_requests (request_number, requested_by, department, request_date, purpose, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$reqNo, $userId, $department, $reqDate, $purpose]);
        $reqId = $pdo->lastInsertId();
        
        foreach ($items as $item) {
            if (!empty($item['item_id']) && $item['quantity'] > 0) {
                $stmt = $pdo->prepare("INSERT INTO production_request_items (request_id, item_id, quantity_requested) VALUES (?, ?, ?)");
                $stmt->execute([$reqId, $item['item_id'], $item['quantity']]);
            }
        }
        
        logAudit($pdo, 'Insert', 'Production', 'Created production request: ' . $reqNo);
        createNotification($pdo, 'Pending Request', 'New Production Request', 'Request ' . $reqNo . ' needs approval.', 'production', $reqId);
        echo '<script>window.location.href="index?page=production&success=Request created";</script>'; exit;
    }
    
    if (in_array($action, ['approve','reject','release','complete'])) {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            echo '<script>window.location.href="index?page=production&error=Invalid token";</script>'; exit;
        }
        $id = (int)$_POST['id'];
        $statusMap = ['approve'=>'Approved','reject'=>'Rejected','release'=>'Released','complete'=>'Completed'];
        $newStatus = $statusMap[$action];
        
        $stmt = $pdo->prepare("UPDATE production_requests SET status=?, approved_by=?, approved_date=NOW() WHERE id=?");
        $stmt->execute([$newStatus, $userId, $id]);
        
        if ($action === 'release') {
            $stmt = $pdo->prepare("SELECT pri.*, i.current_stock, i.unit_cost FROM production_request_items pri JOIN inventory_items i ON pri.item_id = i.id WHERE pri.request_id = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $qty = $item['quantity_requested'];
                if ($qty > $item['current_stock']) $qty = $item['current_stock'];
                
                $stmt = $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$qty, $item['item_id']]);
                
                $stmt = $pdo->prepare("UPDATE production_request_items SET quantity_released = ? WHERE id = ?");
                $stmt->execute([$qty, $item['id']]);
                
                $stmt = $pdo->prepare("INSERT INTO stock_out (item_id, transaction_type, quantity, unit_cost, total_cost, reference_number, purpose, requested_by, date_released) VALUES (?, 'Production Request', ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$item['item_id'], $qty, $item['unit_cost'], $qty * $item['unit_cost'], $_POST['request_number'], 'Production Request', $userId]);
                
                $prevStock = $item['current_stock'];
                $stmt = $pdo->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, reference_type, reference_id, quantity, previous_stock, new_stock, unit_cost, total_value, remarks, created_by) VALUES (?, 'Stock Out', 'Production', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item['item_id'], $id, $qty, $prevStock, $prevStock - $qty, $item['unit_cost'], $qty * $item['unit_cost'], 'Released for production', $userId]);
            }
            
            $stmt = $pdo->prepare("UPDATE production_requests SET completed_date=NOW() WHERE id=?");
            $stmt->execute([$id]);
        }
        
        logAudit($pdo, ucfirst($action), 'Production', 'Request #' . $_POST['request_number'] . ' ' . $newStatus);
        echo '<script>window.location.href="index?page=production&success=Request+' . urlencode(strtolower($newStatus)) . '";</script>'; exit;
    }
}

$items = $pdo->query("SELECT id, name, current_stock, unit_id, (SELECT abbreviation FROM units WHERE id=unit_id) as unit FROM inventory_items WHERE status='Active' ORDER BY name")->fetchAll();

$statusFilter = $_GET['status'] ?? '';
$q = "SELECT pr.*, u.full_name as requested_by_name, app.full_name as approved_by_name,
      (SELECT COUNT(*) FROM production_request_items WHERE request_id=pr.id) as item_count
      FROM production_requests pr 
      LEFT JOIN users u ON pr.requested_by=u.id 
      LEFT JOIN users app ON pr.approved_by=app.id WHERE 1=1";
$params = [];
if ($statusFilter) { $q .= " AND pr.status=?"; $params[]=$statusFilter; }
$q .= " ORDER BY pr.created_at DESC";
$requests = $pdo->prepare($q); $requests->execute($params); $requests = $requests->fetchAll();

$success = $_GET['success'] ?? '';
?>
<?php if ($success): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between"><span><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></span><button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button></div><?php endif; ?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <select id="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="applyFilter()">
        <option value="">All Status</option>
        <option value="Pending" <?php echo $statusFilter=='Pending'?'selected':''; ?>>Pending</option>
        <option value="Approved" <?php echo $statusFilter=='Approved'?'selected':''; ?>>Approved</option>
        <option value="Released" <?php echo $statusFilter=='Released'?'selected':''; ?>>Released</option>
        <option value="Completed" <?php echo $statusFilter=='Completed'?'selected':''; ?>>Completed</option>
        <option value="Rejected" <?php echo $statusFilter=='Rejected'?'selected':''; ?>>Rejected</option>
    </select>
    <button onclick="openCreateModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-plus mr-1"></i> Create Request</button>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Request #</th>
                    <th class="px-4 py-3">Requested By</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Purpose</th>
                    <th class="px-4 py-3">Items</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($requests as $r): 
                    $role = $_SESSION['role'] ?? '';
                    $canApprove = $role === 'Administrator' || $role === 'Inventory Manager';
                ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs font-semibold"><?php echo h($r['request_number']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['requested_by_name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($r['department']); ?></td>
                    <td class="px-4 py-3"><?php echo formatDate($r['request_date']); ?></td>
                    <td class="px-4 py-3 max-w-xs truncate"><?php echo h($r['purpose']); ?></td>
                    <td class="px-4 py-3 text-center"><?php echo $r['item_count']; ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo getStatusBadge($r['status']); ?>"><?php echo $r['status']; ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-1">
                            <button onclick="viewRequest(<?php echo $r['id']; ?>)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i class="fas fa-eye"></i></button>
                            <?php if ($r['status'] === 'Pending'): ?>
                                <button onclick="editRequest(<?php echo $r['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></button>
                                <?php if ($canApprove): ?>
                                <button onclick="changeStatus(<?php echo $r['id']; ?>,'approve','<?php echo h($r['request_number']); ?>')" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Approve"><i class="fas fa-check"></i></button>
                                <button onclick="changeStatus(<?php echo $r['id']; ?>,'reject','<?php echo h($r['request_number']); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Reject"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'Approved' && $canApprove): ?>
                                <button onclick="changeStatus(<?php echo $r['id']; ?>,'release','<?php echo h($r['request_number']); ?>')" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg" title="Release"><i class="fas fa-paper-plane"></i></button>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'Released'): ?>
                                <button onclick="changeStatus(<?php echo $r['id']; ?>,'complete','<?php echo h($r['request_number']); ?>')" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Complete"><i class="fas fa-flag"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($requests) == 0): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No production requests.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Request Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('createModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Create Production Request</h3>
                <button onclick="closeModal('createModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="create">
                <?php echo csrfField(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Request Number</label>
                        <input type="text" name="request_number" value="<?php echo genReqNo($pdo); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                        <input type="text" name="department" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g., Food Processing">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Request Date</label>
                        <input type="date" name="request_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose <span class="text-red-500">*</span></label>
                        <textarea name="purpose" required rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Describe the purpose of this request"></textarea>
                    </div>
                </div>
                
                <h4 class="font-medium text-gray-800 mb-2">Requested Materials</h4>
                <div id="itemsContainer">
                    <div class="item-row grid grid-cols-1 md:grid-cols-3 gap-3 mb-2 p-3 bg-gray-50 rounded-lg">
                        <select name="items[0][item_id]" class="item-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['current_stock']; ?>" data-unit="<?php echo h($item['unit']); ?>">
                                <?php echo h($item['name']); ?> (Stock: <?php echo formatNumber($item['current_stock']); ?> <?php echo h($item['unit']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div>
                            <input type="number" name="items[0][quantity]" class="item-qty w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Quantity" min="0.01" step="0.01" required>
                            <small class="text-gray-400 stock-info"></small>
                        </div>
                        <button type="button" onclick="this.closest('.item-row').remove()" class="text-red-500 hover:text-red-700 self-center"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <button type="button" onclick="addItemRow()" class="text-sm text-emerald-600 hover:text-emerald-700 font-medium"><i class="fas fa-plus mr-1"></i> Add Item</button>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700"><i class="fas fa-save mr-1"></i> Submit Request</button>
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
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div id="viewContent" class="p-6"><div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div></div>
        </div>
    </div>
</div>

<script>
var itemIndex = 1;
var itemsData = <?php echo json_encode($items); ?>;

function addItemRow() {
    var html = '<div class="item-row grid grid-cols-1 md:grid-cols-3 gap-3 mb-2 p-3 bg-gray-50 rounded-lg">';
    html += '<select name="items['+itemIndex+'][item_id]" class="item-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>';
    html += '<option value="">Select Item</option>';
    for (var i = 0; i < itemsData.length; i++) {
        var it = itemsData[i];
        html += '<option value="' + it.id + '" data-stock="' + it.current_stock + '" data-unit="' + (it.unit || '') + '">' + it.name + ' (Stock: ' + it.current_stock + ' ' + (it.unit || '') + ')</option>';
    }
    html += '</select>';
    html += '<div><input type="number" name="items['+itemIndex+'][quantity]" class="item-qty w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Quantity" min="0.01" step="0.01" required><small class="stock-info text-gray-400"></small></div>';
    html += '<button type="button" onclick="this.closest(\'.item-row\').remove()" class="text-red-500 hover:text-red-700 self-center"><i class="fas fa-trash"></i></button>';
    html += '</div>';
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    itemIndex++;
}

$(document).on('change', '.item-select', function() {
    var opt = $(this).find('option:selected');
    var stock = opt.data('stock');
    var unit = opt.data('unit');
    $(this).closest('.item-row').find('.stock-info').text('Available: ' + (stock || 0) + ' ' + (unit || ''));
});

function viewRequest(id) {
    document.getElementById('viewModal').classList.remove('hidden');
    $.ajax({ url: 'ajax/view_production.php', data: { id: id }, success: function(h) { document.getElementById('viewContent').innerHTML = h; } });
}

function changeStatus(id, action, reqNo) {
    var msgs = {approve:'Approve this request?',reject:'Reject this request?',release:'Release items for this request? This will deduct inventory.',complete:'Mark as completed?'};
    if (!confirm(msgs[action])) return;
    var f = document.createElement('form'); f.method = 'POST'; f.style.display = 'none';
    f.innerHTML = '<input type="hidden" name="action" value="'+action+'"><input type="hidden" name="id" value="'+id+'"><input type="hidden" name="request_number" value="'+reqNo+'"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">';
    document.body.appendChild(f); f.submit();
}

function applyFilter() {
    var s = document.getElementById('statusFilter').value;
    window.location.href = 'index?page=production' + (s ? '&status=' + s : '');
}

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
</script>
