<?php
$page = 'profile';
// Get current user details
$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        logAudit($pdo, 'Update', 'Profile', 'Password changed');
        $success = 'Password changed successfully';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
    $stmt->execute([$fullName, $email, $userId]);
    $_SESSION['full_name'] = $fullName;
    logAudit($pdo, 'Update', 'Profile', 'Profile updated');
    $success = 'Profile updated successfully';
    // Refresh user data
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}
?>
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex items-center space-x-4 mb-6">
            <div class="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900"><?php echo h($user['full_name'] ?? $user['username']); ?></h2>
                <p class="text-gray-500"><?php echo h($user['role_name']); ?></p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-500">Username:</span> <span class="font-medium"><?php echo h($user['username']); ?></span></div>
            <div><span class="text-gray-500">Email:</span> <span class="font-medium"><?php echo h($user['email'] ?: 'N/A'); ?></span></div>
            <div><span class="text-gray-500">Last Login:</span> <span class="font-medium"><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></span></div>
            <div><span class="text-gray-500">Member Since:</span> <span class="font-medium"><?php echo formatDate($user['created_at']); ?></span></div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Profile</h3>
        <?php if (isset($success)): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4"><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4"><i class="fas fa-exclamation-circle mr-2"></i><?php echo h($error); ?></div><?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">
            <?php echo csrfField(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label><input type="text" name="full_name" value="<?php echo h($user['full_name'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" value="<?php echo h($user['email'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-save mr-1"></i> Save Changes</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Change Password</h3>
        <form method="POST" class="space-y-4 max-w-md">
            <input type="hidden" name="action" value="change_password">
            <?php echo csrfField(); ?>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label><input type="password" name="current_password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">New Password</label><input type="password" name="new_password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label><input type="password" name="confirm_password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-key mr-1"></i> Change Password</button>
        </form>
    </div>
</div>
