<?php
$page = 'roles';

if (!in_array($_SESSION['role'] ?? '', ['Administrator'])) {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $roleId = (int)$_POST['role_id'];
    $perms = $_POST['permissions'] ?? [];

    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);

    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($perms as $permId) {
        $stmt->execute([$roleId, (int)$permId]);
    }

    logAudit($pdo, 'Update', 'Roles', 'Updated permissions for role ID: ' . $roleId);
    echo '<script>window.location.href="index?page=roles&success=Permissions updated successfully";</script>'; exit;
}

$roles = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM users WHERE role_id=r.id) as user_count FROM roles r ORDER BY r.id")->fetchAll();
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY module, permission_name")->fetchAll();
$groupedPerms = [];
foreach ($permissions as $p) {
    $groupedPerms[$p['module']][] = $p;
}

$rolePerms = [];
$stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
while ($row = $stmt->fetch()) {
    $rolePerms[$row['role_id']][] = $row['permission_id'];
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$activeRoleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['id'] ?? 0);
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
    <h3 class="text-lg font-semibold text-gray-900">Role & Permission Management</h3>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-1 space-y-3">
        <?php foreach ($roles as $r): ?>
        <a href="index?page=roles&role_id=<?php echo $r['id']; ?>"
           class="block bg-white rounded-xl border <?php echo $activeRoleId == $r['id'] ? 'border-emerald-500 ring-2 ring-emerald-200' : 'border-gray-200 hover:border-emerald-300'; ?> shadow-sm transition-all p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold text-gray-900"><?php echo h($r['role_name']); ?></h4>
                    <p class="text-xs text-gray-500 mt-1"><?php echo h($r['description'] ?: 'No description'); ?></p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-bold text-emerald-600"><?php echo (int)$r['user_count']; ?></span>
                    <p class="text-xs text-gray-400">users</p>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="lg:col-span-3">
        <?php
        $selectedRole = null;
        foreach ($roles as $r) {
            if ($r['id'] == $activeRoleId) { $selectedRole = $r; break; }
        }
        if (!$selectedRole) $selectedRole = $roles[0] ?? null;
        ?>
        <?php if ($selectedRole): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-shield-alt text-gray-500 mr-2"></i>
                    Permissions: <span class="text-emerald-600"><?php echo h($selectedRole['role_name']); ?></span>
                </h3>
                <span class="text-xs text-gray-400"><?php echo count($permissions); ?> total permissions</span>
            </div>
            <form method="POST" id="permForm" class="p-6">
                <input type="hidden" name="role_id" value="<?php echo $selectedRole['id']; ?>">
                <input type="hidden" name="update_permissions" value="1">
                <?php echo csrfField(); ?>

                <div class="space-y-6">
                    <?php foreach ($groupedPerms as $module => $modPerms): ?>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-800 uppercase tracking-wider"><?php echo h($module); ?></h4>
                            <button type="button" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium toggle-module" data-module="<?php echo h($module); ?>">
                                <i class="fas fa-check-square mr-1"></i>Toggle All
                            </button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                            <?php foreach ($modPerms as $perm): ?>
                            <?php
                            $checked = in_array($perm['id'], $rolePerms[$selectedRole['id']] ?? []);
                            ?>
                            <label class="flex items-center p-2 rounded-lg border <?php echo $checked ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200 bg-white hover:bg-gray-50'; ?> cursor-pointer transition-all perm-label" data-perm-id="<?php echo $perm['id']; ?>">
                                <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" <?php echo $checked ? 'checked' : ''; ?> class="perm-checkbox h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                <span class="ml-2 text-xs font-medium text-gray-700"><?php echo h($perm['permission_name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-6 flex items-center justify-between pt-4 border-t border-gray-200">
                    <div>
                        <span id="checkedCount" class="text-sm text-gray-500"><?php echo count($rolePerms[$selectedRole['id']] ?? []); ?> / <?php echo count($permissions); ?> permissions selected</span>
                    </div>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-save mr-1"></i> Save Permissions
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    function updateCount() {
        var total = $('.perm-checkbox').length;
        var checked = $('.perm-checkbox:checked').length;
        $('#checkedCount').text(checked + ' / ' + total + ' permissions selected');
    }

    $('.perm-checkbox').on('change', function() {
        var label = $(this).closest('.perm-label');
        if ($(this).is(':checked')) {
            label.removeClass('border-gray-200 bg-white hover:bg-gray-50').addClass('border-emerald-300 bg-emerald-50');
        } else {
            label.removeClass('border-emerald-300 bg-emerald-50').addClass('border-gray-200 bg-white hover:bg-gray-50');
        }
        updateCount();
    });

    $('.toggle-module').on('click', function() {
        var moduleGroup = $(this).closest('div');
        var checkboxes = moduleGroup.find('.perm-checkbox');
        var allChecked = checkboxes.length === checkboxes.filter(':checked').length;
        checkboxes.prop('checked', !allChecked).trigger('change');
    });

    updateCount();
});
</script>
