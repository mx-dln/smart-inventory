<?php
$page = 'suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'company_name' => $_POST['company_name'],
            'contact_person' => $_POST['contact_person'],
            'address' => $_POST['address'],
            'phone' => $_POST['phone'],
            'email' => $_POST['email'],
            'products' => $_POST['products'],
            'status' => $_POST['status'],
        ];
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO suppliers (company_name, contact_person, address, phone, email, products, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
            logAudit($pdo, 'Insert', 'Suppliers', 'Added supplier: ' . $data['company_name']);
        } else {
            $stmt = $pdo->prepare("UPDATE suppliers SET company_name=?, contact_person=?, address=?, phone=?, email=?, products=?, status=? WHERE id=?");
            $stmt->execute([...array_values($data), $id]);
            logAudit($pdo, 'Update', 'Suppliers', 'Updated supplier: ' . $data['company_name']);
        }
        echo '<script>window.location.href="index?page=suppliers&success=' . ($action === 'add' ? 'Supplier added' : 'Supplier updated') . '";</script>'; exit;
    }
    
    if ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE supplier_id=?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo '<script>window.location.href="index?page=suppliers&error=Cannot delete supplier with inventory items";</script>'; exit;
        }
        $stmt = $pdo->prepare("SELECT company_name FROM suppliers WHERE id=?");
        $stmt->execute([$id]); $s = $stmt->fetch();
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->execute([$id]);
        logAudit($pdo, 'Delete', 'Suppliers', 'Deleted supplier: ' . ($s['company_name'] ?? ''));
        echo '<script>window.location.href="index?page=suppliers&success=Supplier deleted";</script>'; exit;
    }
}

$suppliers = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM inventory_items WHERE supplier_id=s.id) as item_count FROM suppliers s ORDER BY s.company_name")->fetchAll();
$success = $_GET['success'] ?? ''; $error = $_GET['error'] ?? '';
?>
<?php if ($success): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4"><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><i class="fas fa-exclamation-circle mr-2"></i><?php echo h($error); ?></div><?php endif; ?>

<div class="flex justify-end mb-6">
    <button onclick="openSupplierModal('add')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-plus mr-1"></i> Add Supplier</button>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full">
            <thead><tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <th class="px-4 py-3">Company</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Products</th><th class="px-4 py-3">Items</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($suppliers as $s): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?php echo h($s['company_name']); ?></td>
                    <td class="px-4 py-3"><?php echo h($s['contact_person']); ?></td>
                    <td class="px-4 py-3"><?php echo h($s['phone']); ?></td>
                    <td class="px-4 py-3"><?php echo h($s['email']); ?></td>
                    <td class="px-4 py-3 max-w-xs truncate"><?php echo h($s['products']); ?></td>
                    <td class="px-4 py-3 text-center"><?php echo $s['item_count']; ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full <?php echo $s['status']=='Active'?'bg-emerald-100 text-emerald-800':'bg-gray-100 text-gray-800'; ?>"><?php echo $s['status']; ?></span></td>
                    <td class="px-4 py-3">
                        <button onclick="editSupplier(<?php echo $s['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteSupplier(<?php echo $s['id']; ?>,'<?php echo h(addslashes($s['company_name'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($suppliers)==0): ?><tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No suppliers.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Modal -->
<div id="supplierModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeSupplierModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900" id="supplierModalTitle">Add Supplier</h3>
                <button onclick="closeSupplierModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" id="supplierAction" value="add">
                <input type="hidden" name="id" id="supplierId" value="">
                <?php echo csrfField(); ?>
                <div class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label><input type="text" name="company_name" id="sCompany" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label><input type="text" name="contact_person" id="sContact" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Address</label><textarea name="address" id="sAddress" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Phone</label><input type="text" name="phone" id="sPhone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" id="sEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Products/Services</label><textarea name="products" id="sProducts" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="sStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="Active">Active</option><option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeSupplierModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSupplierModal(type) {
    document.getElementById('supplierModal').classList.remove('hidden');
    document.getElementById('supplierAction').value = type;
    document.getElementById('supplierModalTitle').textContent = type === 'add' ? 'Add Supplier' : 'Edit Supplier';
    if (type === 'add') { document.getElementById('supplierModal').querySelector('form').reset(); document.getElementById('supplierId').value = ''; }
}
function editSupplier(id) {
    openSupplierModal('edit');
    $.ajax({ url: 'ajax/get_supplier.php', data: { id: id }, dataType: 'json', success: function(s) {
        document.getElementById('supplierId').value = s.id;
        document.getElementById('sCompany').value = s.company_name;
        document.getElementById('sContact').value = s.contact_person || '';
        document.getElementById('sAddress').value = s.address || '';
        document.getElementById('sPhone').value = s.phone || '';
        document.getElementById('sEmail').value = s.email || '';
        document.getElementById('sProducts').value = s.products || '';
        document.getElementById('sStatus').value = s.status;
    }});
}
function deleteSupplier(id, name) {
    if (confirm('Delete supplier "' + name + '"?')) {
        var f = document.createElement('form'); f.method = 'POST';
        f.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'+id+'"><?php echo csrfField(); ?>';
        document.body.appendChild(f); f.submit();
    }
}
function closeSupplierModal() { document.getElementById('supplierModal').classList.add('hidden'); }
</script>
