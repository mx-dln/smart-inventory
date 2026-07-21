<?php
$page = 'settings';

if ($_SESSION['role'] !== 'Administrator') {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $settings = [
            'system_name' => trim($_POST['system_name'] ?? ''),
            'school_name' => trim($_POST['school_name'] ?? ''),
        ];
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $logo = uploadFile($_FILES['logo'], UPLOAD_PATH);
                if ($logo) {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$logo, $logo]);
                }
            }
        }

        logAudit($pdo, 'Update', 'Settings', 'System settings updated');
        echo '<script>window.location.href="index?page=settings&success=Settings saved successfully";</script>'; exit;
    }

    if ($action === 'backup') {
        $backupDir = UPLOAD_PATH . 'backup/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

        $backupFile = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $backupPath = $backupDir . $backupFile;

        // PHP-based export (works without shell access)
        $sql = '-- Smart Inventory System Backup' . "\n";
        $sql .= '-- Database: ' . DB_NAME . "\n";
        $sql .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";
        $sql .= 'SET FOREIGN_KEY_CHECKS=0;' . "\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= 'DROP TABLE IF EXISTS `' . $table . '`;' . "\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
            if (count($rows) > 0) {
                $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                $colList = '`' . implode('`,`', $columns) . '`';
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, $row);
                    $sql .= "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $vals) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= 'SET FOREIGN_KEY_CHECKS=1;' . "\n";
        file_put_contents($backupPath, $sql);

        logAudit($pdo, 'Backup', 'Settings', 'Database backup created: ' . $backupFile);
        echo '<script>window.location.href="' . BASE_URL . 'uploads/backup/' . $backupFile . '";</script>';
        exit;
    }

    if ($action === 'restore') {
        if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
            $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
            if (!empty(trim($sqlContent))) {
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                    $pdo->exec($sqlContent);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    logAudit($pdo, 'Restore', 'Settings', 'Database restored from file');
                    echo '<script>window.location.href="index?page=settings&success=Database restored successfully";</script>';
                } catch (Exception $e) {
                    echo '<script>window.location.href="index?page=settings&error=Restore failed: ' . h($e->getMessage()) . '";</script>';
                }
                exit;
            }
            echo '<script>window.location.href="index?page=settings&error=Uploaded SQL file is empty";</script>'; exit;
        }
        echo '<script>window.location.href="index?page=settings&error=No file uploaded";</script>'; exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            echo '<script>window.location.href="index?page=settings&error=All password fields are required";</script>'; exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!password_verify($current, $user['password'])) {
            echo '<script>window.location.href="index?page=settings&error=Current password is incorrect";</script>'; exit;
        }
        if ($new !== $confirm) {
            echo '<script>window.location.href="index?page=settings&error=New passwords do not match";</script>'; exit;
        }
        if (strlen($new) < 6) {
            echo '<script>window.location.href="index?page=settings&error=Password must be at least 6 characters";</script>'; exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

        logAudit($pdo, 'Update', 'Settings', 'Password changed for user ID: ' . $userId);
        echo '<script>window.location.href="index?page=settings&success=Password changed successfully";</script>'; exit;
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- System Settings -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-cog text-gray-500 mr-2"></i> System Settings</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="action" value="save_settings">
            <?php echo csrfField(); ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">System Name</label>
                    <input type="text" name="system_name" value="<?php echo h($settings['system_name'] ?? APP_NAME); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">School/Organization Name</label>
                    <input type="text" name="school_name" value="<?php echo h($settings['school_name'] ?? SCHOOL_NAME); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                    <?php if (!empty($settings['logo'])): ?>
                    <div class="mb-2">
                        <img src="uploads/<?php echo h($settings['logo']); ?>" class="h-16 rounded border border-gray-200">
                    </div>
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/*" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                    <p class="text-xs text-gray-400 mt-1">Allowed: JPG, JPEG, PNG, GIF</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-save mr-1"></i> Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Backup & Restore -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-database text-gray-500 mr-2"></i> Database Backup & Restore</h3>
        </div>
        <div class="p-6 space-y-6">
            <!-- Backup -->
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Download Backup</h4>
                <p class="text-xs text-gray-500 mb-3">Generate a complete SQL backup of the entire database.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="backup">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-download mr-1"></i> Download SQL Backup
                    </button>
                </form>
            </div>

            <hr class="border-gray-200">

            <!-- Restore -->
            <div>
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Restore Database</h4>
                <p class="text-xs text-gray-500 mb-3 text-orange-600"><i class="fas fa-exclamation-triangle mr-1"></i> Warning: This will overwrite all existing data!</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="restore">
                    <?php echo csrfField(); ?>
                    <div class="flex items-center gap-3">
                        <input type="file" name="sql_file" accept=".sql" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm hover:bg-orange-700 transition-colors" onclick="return confirm('Are you sure you want to restore the database? All current data will be replaced!')">
                            <i class="fas fa-upload mr-1"></i> Restore
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-key text-gray-500 mr-2"></i> Change Password</h3>
        </div>
        <form method="POST" class="p-6 max-w-md">
            <input type="hidden" name="action" value="change_password">
            <?php echo csrfField(); ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password <span class="text-red-500">*</span></label>
                    <input type="password" name="current_password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Enter current password">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="new_password" required minlength="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Min 6 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Confirm new password">
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-key mr-1"></i> Change Password
                </button>
            </div>
        </form>
    </div>

</div>
