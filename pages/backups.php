<?php
$page = 'backups';

if ($_SESSION['role'] !== 'Administrator') {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_backup') {
        $type = $_POST['backup_type'] ?? 'Database';
        $backupDir = UPLOAD_PATH . 'backup/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . $filename;

        try {
            $sql = '-- Smart Inventory System Backup' . "\n";
            $sql .= '-- Host: ' . DB_HOST . ' | Database: ' . DB_NAME . "\n";
            $sql .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n\n";
            $sql .= 'SET FOREIGN_KEY_CHECKS=0;' . "\n\n";

            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create['Create Table'] . ";\n\n";

                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
                if (count($rows) > 0) {
                    $cols = array_keys($rows[0]);
                    $colList = '`' . implode('`,`', $cols) . '`';
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
            file_put_contents($filepath, $sql);
            $fileSize = filesize($filepath);

            $stmt = $pdo->prepare("INSERT INTO backup_history (backup_type, filename, file_size, file_path, status, created_by) VALUES (?, ?, ?, ?, 'Success', ?)");
            $stmt->execute([$type, $filename, $fileSize, $filepath, $userId]);

            logAudit($pdo, 'Backup', 'System', 'Backup created: ' . $filename);
            echo '<script>window.location.href="index?page=backups&success=Backup created successfully (' . $filename . ')";</script>';
        } catch (Exception $e) {
            echo '<script>window.location.href="index?page=backups&error=Backup failed: ' . urlencode($e->getMessage()) . '";</script>';
        }
        exit;
    }

    if ($action === 'restore_backup') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();

        if ($backup && file_exists($backup['file_path'])) {
            try {
                $sql = file_get_contents($backup['file_path']);
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $pdo->exec($sql);
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                logAudit($pdo, 'Restore', 'System', 'Database restored from: ' . $backup['file_path']);
                echo '<script>window.location.href="index?page=backups&success=Database restored successfully";</script>';
            } catch (Exception $e) {
                echo '<script>window.location.href="index?page=backups&error=Restore failed: ' . urlencode($e->getMessage()) . '";</script>';
            }
        } else {
            echo '<script>window.location.href="index?page=backups&error=Backup file not found";</script>';
        }
        exit;
    }

    if ($action === 'delete_backup') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path, filename FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();

        if ($backup && file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }

        $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);

        logAudit($pdo, 'Delete', 'System', 'Deleted backup: ' . ($backup['filename'] ?? ''));
        echo '<script>window.location.href="index?page=backups&success=Backup deleted";</script>'; exit;
    }

    if ($action === 'download_backup') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path, filename FROM backup_history WHERE id = ?");
        $stmt->execute([$id]);
        $backup = $stmt->fetch();

        if ($backup && file_exists($backup['file_path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
            header('Content-Length: ' . filesize($backup['file_path']));
            readfile($backup['file_path']);
            exit;
        }
        echo '<script>window.location.href="index?page=backups&error=Backup file not found";</script>'; exit;
    }
}

$backups = $pdo->query("SELECT bh.*, u.full_name as created_by_name FROM backup_history bh LEFT JOIN users u ON bh.created_by=u.id ORDER BY bh.created_at DESC")->fetchAll();

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
    <h3 class="text-lg font-semibold text-gray-900">Database Backup Manager</h3>
    <button onclick="document.getElementById('createBackupModal').classList.remove('hidden')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        <i class="fas fa-plus-circle mr-1"></i> Create Backup
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Backups</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo count($backups); ?></p>
            </div>
            <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-database text-indigo-600"></i>
            </div>
        </div>
    </div>
    <?php
    $latestBackup = !empty($backups) ? $backups[0] : null;
    $totalSize = 0;
    foreach ($backups as $b) { $totalSize += (int)$b['file_size']; }
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Latest Backup</p>
                <p class="text-sm font-semibold text-gray-900 mt-1"><?php echo $latestBackup ? formatDateTime($latestBackup['created_at']) : 'N/A'; ?></p>
            </div>
            <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-clock text-emerald-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Size</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo $totalSize > 1048576 ? number_format($totalSize / 1048576, 2) . ' MB' : ($totalSize > 1024 ? number_format($totalSize / 1024, 2) . ' KB' : $totalSize . ' B'); ?></p>
            </div>
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-hard-drive text-amber-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">Last Status</p>
                <p class="text-sm font-semibold text-gray-900 mt-1">
                    <span class="px-2 py-0.5 text-xs rounded-full <?php echo $latestBackup && $latestBackup['status'] === 'Success' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo $latestBackup ? $latestBackup['status'] : 'No backups'; ?>
                    </span>
                </p>
            </div>
            <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-shield-alt text-emerald-600"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-history text-gray-500 mr-2"></i> Backup History</h3>
    </div>
    <div class="p-6">
        <div class="table-container">
            <div class="overflow-x-auto">
                <table class="datatable min-w-full" id="backupsTable">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Date Created</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Filename</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created By</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($backups as $b): ?>
                        <?php
                        $size = (int)$b['file_size'];
                        $sizeStr = $size > 1048576 ? number_format($size / 1048576, 2) . ' MB' : ($size > 1024 ? number_format($size / 1024, 2) . ' KB' : $size . ' B');
                        ?>
                        <tr class="text-sm text-gray-700 hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500"><?php echo formatDateTime($b['created_at']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800"><?php echo h($b['backup_type']); ?></span>
                            </td>
                            <td class="px-4 py-3 max-w-xs truncate font-mono text-xs"><?php echo h($b['filename']); ?></td>
                            <td class="px-4 py-3 text-xs text-gray-500"><?php echo $sizeStr; ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $b['status'] === 'Success' ? 'bg-emerald-100 text-emerald-800' : ($b['status'] === 'Failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                    <?php echo $b['status']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3"><?php echo h($b['created_by_name'] ?: 'System'); ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-1">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="download_backup">
                                        <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                        <?php echo csrfField(); ?>
                                        <button type="submit" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Download"><i class="fas fa-download"></i></button>
                                    </form>
                                    <button onclick="restoreBackup(<?php echo $b['id']; ?>, '<?php echo h(addslashes($b['filename'])); ?>')" class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg" title="Restore"><i class="fas fa-undo"></i></button>
                                    <button onclick="deleteBackup(<?php echo $b['id']; ?>, '<?php echo h(addslashes($b['filename'])); ?>')" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No backups have been created yet</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="createBackupModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="document.getElementById('createBackupModal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Create New Backup</h3>
                <button onclick="document.getElementById('createBackupModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="create_backup">
                <?php echo csrfField(); ?>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Select the backup type and click Create to generate a new backup.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Backup Type</label>
                        <div class="space-y-2">
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-emerald-300 transition-all <?php echo true ? 'border-emerald-300 bg-emerald-50' : ''; ?>">
                                <input type="radio" name="backup_type" value="Database" checked class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-800">Database Only</span>
                                    <p class="text-xs text-gray-500">SQL export of all database tables</p>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-emerald-300 transition-all">
                                <input type="radio" name="backup_type" value="Files" class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-800">Files Only</span>
                                    <p class="text-xs text-gray-500">Uploaded files and assets backup</p>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-emerald-300 transition-all">
                                <input type="radio" name="backup_type" value="Full" class="h-4 w-4 text-emerald-600 border-gray-300 focus:ring-emerald-500">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-800">Full Backup</span>
                                    <p class="text-xs text-gray-500">Complete system backup (DB + Files)</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('createBackupModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" onclick="showLoading()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                        <i class="fas fa-play mr-1"></i> Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function restoreBackup(id, filename) {
    if (confirm('Are you sure you want to restore "' + filename + '"?\n\nWARNING: This will replace ALL current data with the backup contents. This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="action" value="restore_backup"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(form);
        showLoading();
        form.submit();
    }
}

function deleteBackup(id, filename) {
    if (confirm('Delete backup "' + filename + '"? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input type="hidden" name="action" value="delete_backup"><input type="hidden" name="id" value="' + id + '"><?php echo csrfField(); ?>';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
