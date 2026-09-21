<?php
$page = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');
        $categoryType = $_POST['category_type'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';

        if (empty($categoryName) || empty($categoryType)) {
            echo '<script>window.location.href="index?page=categories&error=' . urlencode('Category name and type are required') . '";</script>';
            exit;
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ?");
            $stmt->execute([$categoryName]);
            if ($stmt->fetchColumn() > 0) {
                echo '<script>window.location.href="index?page=categories&error=' . urlencode('Category name already exists') . '";</script>';
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO categories (category_name, category_type, description, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$categoryName, $categoryType, $description, $status]);
            $newId = $pdo->lastInsertId();
            logAudit($pdo, 'Insert', 'Categories', 'Added category: ' . $categoryName);
            echo '<script>window.location.href="index?page=categories&success=' . urlencode('Category added successfully') . '";</script>';
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ? AND id != ?");
            $stmt->execute([$categoryName, $id]);
            if ($stmt->fetchColumn() > 0) {
                echo '<script>window.location.href="index?page=categories&error=' . urlencode('Category name already exists') . '";</script>';
                exit;
            }

            $stmt = $pdo->prepare("UPDATE categories SET category_name=?, category_type=?, description=?, status=? WHERE id=?");
            $stmt->execute([$categoryName, $categoryType, $description, $status, $id]);
            logAudit($pdo, 'Update', 'Categories', 'Updated category ID: ' . $id . ', Name: ' . $categoryName);
            echo '<script>window.location.href="index?page=categories&success=' . urlencode('Category updated successfully') . '";</script>';
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo '<script>window.location.href="index?page=categories&error=' . urlencode('Cannot delete category with existing items') . '";</script>';
            exit;
        }

        $stmt = $pdo->prepare("SELECT category_name FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        logAudit($pdo, 'Delete', 'Categories', 'Deleted category: ' . ($cat['category_name'] ?? 'Unknown'));
        echo '<script>window.location.href="index?page=categories&success=' . urlencode('Category deleted successfully') . '";</script>';
        exit;
    }
}

$query = "SELECT c.*, (SELECT COUNT(*) FROM inventory_items WHERE category_id = c.id) as item_count FROM categories c ORDER BY c.category_name";
$stmt = $pdo->query($query);
$categories = $stmt->fetchAll();

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
    <h3 class="text-lg font-semibold text-gray-800">Manage Categories</h3>
    <div class="flex items-center gap-2">
        <button onclick="printPage('categoriesTable', 'Categories Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openModal('add')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add Category
        </button>
    </div>
</div>

<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="categoriesTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Category Name</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Items</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($categories as $c): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3"><?php echo $c['id']; ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo h($c['category_name']); ?></td>
                    <td class="px-4 py-3">
                        <?php
                        $typeColors = [
                            'Raw Materials' => 'bg-emerald-100 text-emerald-800',
                            'Production Supplies' => 'bg-blue-100 text-blue-800',
                            'Packaging' => 'bg-purple-100 text-purple-800',
                            'Chemicals' => 'bg-yellow-100 text-yellow-800',
                            'Cleaning Materials' => 'bg-cyan-100 text-cyan-800',
                            'Office Supplies' => 'bg-indigo-100 text-indigo-800',
                            'Equipment' => 'bg-gray-100 text-gray-800',
                        ];
                        $color = $typeColors[$c['category_type']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $color; ?>"><?php echo h($c['category_type']); ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 max-w-xs truncate"><?php echo h($c['description'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $c['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>"><?php echo h($c['status']); ?></span>
                    </td>
                    <td class="px-4 py-3"><?php echo $c['item_count']; ?></td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <button onclick="editCategory(<?php echo $c['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteCategory(<?php echo $c['id']; ?>, '<?php echo h(addslashes($c['category_name'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($categories) == 0): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No categories found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="categoryModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add Category</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="categoryForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="recordId" value="">
                <?php echo csrfField(); ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" name="category_name" id="catName" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g., Flour, Sugar">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Type <span class="text-red-500">*</span></label>
                        <select name="category_type" id="catType" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Type</option>
                            <option value="Raw Materials">Raw Materials</option>
                            <option value="Production Supplies">Production Supplies</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Chemicals">Chemicals</option>
                            <option value="Cleaning Materials">Cleaning Materials</option>
                            <option value="Office Supplies">Office Supplies</option>
                            <option value="Equipment">Equipment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="catDesc" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Optional description"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="catStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(type, id) {
    var modal = document.getElementById('categoryModal');
    modal.classList.remove('hidden');
    if (type === 'add') {
        document.getElementById('formAction').value = 'add';
        document.getElementById('modalTitle').textContent = 'Add Category';
        document.getElementById('categoryForm').reset();
        document.getElementById('recordId').value = '';
    }
}

function editCategory(id) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Category';
    $.ajax({
        url: 'ajax/get_category.php?id=' + id,
        dataType: 'json',
        success: function(r) {
            if (!r || r.error) { showToast('Failed to load category', 'error'); return; }
            document.getElementById('recordId').value = r.id;
            document.getElementById('catName').value = r.category_name || '';
            document.getElementById('catType').value = r.category_type || '';
            document.getElementById('catDesc').value = r.description || '';
            document.getElementById('catStatus').value = r.status || 'Active';
            document.getElementById('categoryModal').classList.remove('hidden');
        },
        error: function() { showToast('Failed to load category', 'error'); }
    });
}

function deleteCategory(id, name) {
    if (confirm('Delete category "' + name + '"? Items assigned to this category must be reassigned first.')) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(f);
        f.submit();
    }
}

function closeModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
