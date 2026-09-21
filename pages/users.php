<?php
$page = 'users';

// Verify admin access
if ($_SESSION['role'] !== 'Administrator') {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'Active';

        if (empty($username) || empty($password)) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('Username and password are required') . '";</script>'; exit;
        }
        if (strlen($password) < 6) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('Password must be at least 6 characters') . '";</script>'; exit;
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('Username already exists') . '";</script>'; exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role_id, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $fullName, $roleId, $status]);
        $newId = $pdo->lastInsertId();

        logAudit($pdo, 'Insert', 'Users', 'Created user: ' . $username . ' (ID: ' . $newId . ')');
        echo '<script>window.location.href="index?page=users&success=' . urlencode('User created successfully') . '";</script>'; exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'Active';
        $password = $_POST['password'] ?? '';

        if (empty($username)) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('Username is required') . '";</script>'; exit;
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('Username already exists') . '";</script>'; exit;
        }

        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo '<script>window.location.href="index?page=users&error=' . urlencode('Password must be at least 6 characters') . '";</script>'; exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, email=?, full_name=?, role_id=?, status=? WHERE id=?");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $fullName, $roleId, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, full_name=?, role_id=?, status=? WHERE id=?");
            $stmt->execute([$username, $email, $fullName, $roleId, $status, $id]);
        }

        logAudit($pdo, 'Update', 'Users', 'Updated user: ' . $username . ' (ID: ' . $id . ')');
        echo '<script>window.location.href="index?page=users&success=' . urlencode('User updated successfully') . '";</script>'; exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$userId) {
            echo '<script>window.location.href="index?page=users&error=' . urlencode('You cannot delete your own account') . '";</script>'; exit;
        }

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            logAudit($pdo, 'Delete', 'Users', 'Deleted user: ' . $user['username'] . ' (ID: ' . $id . ')');
            echo '<script>window.location.href="index?page=users&success=' . urlencode('User deleted successfully') . '";</script>'; exit;
        }
        echo '<script>window.location.href="index?page=users&error=' . urlencode('User not found') . '";</script>'; exit;
    }
}

// AJAX get user
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_user') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role_id, status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($user);
    exit;
}

$stmt = $pdo->query("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

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
    <h3 class="text-lg font-semibold text-gray-900">User Management</h3>
    <div class="flex items-center gap-2">
        <button onclick="printPage('usersTable', 'Users Report')" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-print mr-1"></i> Print
        </button>
        <button onclick="openAddModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-plus mr-1"></i> Add User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="table-container">
    <div class="overflow-x-auto">
        <table class="datatable min-w-full" id="usersTable">
            <thead>
                <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Full Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last Login</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($users as $u): ?>
                <tr class="text-sm text-gray-700 hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?php echo h($u['username']); ?></td>
                    <td class="px-4 py-3"><?php echo h($u['full_name'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3"><?php echo h($u['email'] ?: 'N/A'); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-800"><?php echo h($u['role_name']); ?></span></td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $u['status'] == 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $u['status']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500"><?php echo $u['last_login'] ? formatDateTime($u['last_login']) : 'Never'; ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center space-x-1">
                            <button onclick="editUser(<?php echo $u['id']; ?>)" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Edit"><i class="fas fa-edit"></i></button>
                            <?php if ($u['id'] !== (int)$userId): ?>
                            <button onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo h(addslashes($u['username'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="userModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('userModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10">
                <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add User</h3>
                <button onclick="closeModal('userModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6" id="userForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="userId" value="">
                <?php echo csrfField(); ?>

                <div class="grid grid-cols-1 gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" id="userUsername" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Enter username">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500" id="pwdRequired">*</span></label>
                            <input type="password" name="password" id="userPassword" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="<?php echo isset($_GET['edit']) ? 'Leave blank to keep current' : 'Enter password'; ?>">
                            <p class="text-xs text-gray-400 mt-1" id="pwdHelp">Min 6 characters</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" id="userFullName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Enter full name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="userEmail" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="email@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                        <select name="role_id" id="userRole" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo h($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="userStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('userModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userPassword').required = true;
    document.getElementById('userPassword').placeholder = 'Enter password';
    document.getElementById('pwdRequired').style.display = 'inline';
    document.getElementById('pwdHelp').textContent = 'Min 6 characters';
    document.getElementById('userModal').classList.remove('hidden');
}

function editUser(id) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userPassword').required = false;
    document.getElementById('userPassword').placeholder = 'Leave blank to keep current';
    document.getElementById('pwdRequired').style.display = 'none';
    document.getElementById('pwdHelp').textContent = 'Fill only if changing password';

    $.ajax({
        url: 'ajax/get_user.php?id=' + id,
        method: 'GET',
        dataType: 'json',
        success: function(user) {
            if (!user || user.error) { showToast('Failed to load user data', 'error'); return; }
            document.getElementById('userId').value = user.id;
            document.getElementById('userUsername').value = user.username || '';
            document.getElementById('userFullName').value = user.full_name || '';
            document.getElementById('userEmail').value = user.email || '';
            document.getElementById('userRole').value = user.role_id || '';
            document.getElementById('userStatus').value = user.status || 'Active';
            document.getElementById('userPassword').value = '';
            document.getElementById('userModal').classList.remove('hidden');
        },
        error: function() { showToast('Failed to load user data', 'error'); }
    });
}

function deleteUser(id, username) {
    if (confirm('Are you sure you want to delete user "' + username + '"? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(form);
        form.submit();
    }
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
