<?php
$page = 'email-settings';

if ($_SESSION['role'] !== 'Administrator') {
    echo '<script>window.location.href="index?page=dashboard";</script>'; exit;
}

$settings = [];
$stmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'notify_%'");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_smtp') {
        $smtpSettings = [
            'email_smtp_host' => $_POST['smtp_host'],
            'email_smtp_port' => $_POST['smtp_port'],
            'email_smtp_encryption' => $_POST['smtp_encryption'],
            'email_smtp_username' => $_POST['smtp_username'],
            'email_smtp_password' => $_POST['smtp_password'],
            'email_from_address' => $_POST['from_address'],
            'email_from_name' => $_POST['from_name'],
        ];
        foreach ($smtpSettings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        logAudit($pdo, 'Update', 'Email Settings', 'SMTP configuration updated');
        echo '<script>window.location.href="index?page=email-settings&success=SMTP settings saved";</script>'; exit;
    }

    if ($action === 'save_notifications') {
        $types = ['low_stock', 'critical_stock', 'near_expiry', 'expired', 'pr_submitted', 'pr_approved', 'pr_rejected', 'borrow_overdue', 'backup_complete'];
        foreach ($types as $type) {
            $val = isset($_POST[$type]) ? '1' : '0';
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute(["notify_{$type}", $val, $val]);
        }
        echo '<script>window.location.href="index?page=email-settings&success=Notification preferences saved";</script>'; exit;
    }

    if ($action === 'test_email') {
        $to = trim($_POST['test_email_to']);
        $mailSent = false;
        $mailError = '';

        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $settings['email_smtp_host'] ?? '';
                $mail->Port = (int)($settings['email_smtp_port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $settings['email_smtp_username'] ?? '';
                $mail->Password = $settings['email_smtp_password'] ?? '';
                $mail->SMTPSecure = $settings['email_smtp_encryption'] ?? 'tls';
                $mail->setFrom($settings['email_from_address'] ?? '', $settings['email_from_name'] ?? '');
                $mail->addAddress($to);
                $mail->Subject = 'Test Email - Smart Inventory System';
                $mail->Body = 'This is a test email from Smart Inventory System. If you received this, your SMTP configuration is working correctly.';
                $mail->send();
                $mailSent = true;
            } catch (Exception $e) {
                $mailError = $e->getMessage();
            }
        } else {
            $mailError = 'PHPMailer not installed. Run: composer require phpmailer/phpmailer';
        }

        logAudit($pdo, 'Test', 'Email', 'Test email to ' . $to . ': ' . ($mailSent ? 'Success' : 'Failed'));

        if ($mailSent) {
            echo '<script>window.location.href="index?page=email-settings&success=Test email sent successfully to ' . urlencode($to) . '";</script>';
        } else {
            echo '<script>window.location.href="index?page=email-settings&error=Test email failed: ' . urlencode($mailError) . '";</script>';
        }
        exit;
    }
}

$emailLogs = $pdo->query("SELECT el.*, u.full_name as sent_by_name FROM email_logs el LEFT JOIN users u ON el.sent_by=u.id ORDER BY el.sent_at DESC LIMIT 50")->fetchAll();

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
    <h3 class="text-lg font-semibold text-gray-900">Email Configuration</h3>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-server text-gray-500 mr-2"></i> SMTP Settings</h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="save_smtp">
            <?php echo csrfField(); ?>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host <span class="text-red-500">*</span></label>
                        <input type="text" name="smtp_host" value="<?php echo h($settings['email_smtp_host'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port <span class="text-red-500">*</span></label>
                        <input type="number" name="smtp_port" value="<?php echo h($settings['email_smtp_port'] ?? '587'); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="587">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                    <select name="smtp_encryption" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="tls" <?php echo ($settings['email_smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?php echo ($settings['email_smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo ($settings['email_smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                    <input type="text" name="smtp_username" value="<?php echo h($settings['email_smtp_username'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="user@gmail.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                    <input type="password" name="smtp_password" value="<?php echo h($settings['email_smtp_password'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="App password or SMTP password">
                </div>
                <hr class="border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Address <span class="text-red-500">*</span></label>
                        <input type="email" name="from_address" value="<?php echo h($settings['email_from_address'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="noreply@isu.edu.ph">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input type="text" name="from_name" value="<?php echo h($settings['email_from_name'] ?? APP_NAME); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="Smart Inventory System">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-save mr-1"></i> Save SMTP Settings
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-bell text-gray-500 mr-2"></i> Notification Preferences</h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="save_notifications">
            <?php echo csrfField(); ?>

            <div class="space-y-3">
                <p class="text-sm text-gray-500 mb-2">Select which events should trigger email notifications:</p>

                <?php
                $notifTypes = [
                    'low_stock' => ['Low Stock Alert', 'When inventory items fall below minimum stock level', 'fa-exclamation-triangle'],
                    'critical_stock' => ['Critical Stock Alert', 'When inventory items reach critically low levels', 'fa-exclamation-circle'],
                    'near_expiry' => ['Near Expiry Alert', 'When items are nearing their expiration date', 'fa-clock'],
                    'expired' => ['Expired Item Alert', 'When items have expired', 'fa-calendar-times'],
                    'pr_submitted' => ['PR Submitted', 'When a new purchase request is submitted', 'fa-file-import'],
                    'pr_approved' => ['PR Approved', 'When a purchase request is approved', 'fa-check-circle'],
                    'pr_rejected' => ['PR Rejected', 'When a purchase request is rejected', 'fa-times-circle'],
                    'borrow_overdue' => ['Borrow Overdue', 'When borrowed items become overdue', 'fa-hand-holding'],
                    'backup_complete' => ['Backup Complete', 'When a database backup is completed', 'fa-database'],
                ];
                ?>
                <?php foreach ($notifTypes as $key => $info): ?>
                <label class="flex items-center p-3 rounded-lg border border-gray-200 hover:border-emerald-300 hover:bg-emerald-50/30 cursor-pointer transition-all">
                    <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php echo !empty($settings["notify_{$key}"]) && $settings["notify_{$key}"] === '1' ? 'checked' : ''; ?> class="h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                    <div class="ml-3 flex-1">
                        <div class="flex items-center">
                            <i class="fas <?php echo $info[2]; ?> text-gray-400 w-4 mr-1.5 text-xs"></i>
                            <span class="text-sm font-medium text-gray-800"><?php echo $info[0]; ?></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5"><?php echo $info[1]; ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-save mr-1"></i> Save Preferences
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-paper-plane text-gray-500 mr-2"></i> Test Email</h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="test_email">
            <?php echo csrfField(); ?>

            <div class="space-y-4">
                <p class="text-sm text-gray-500">Send a test email to verify your SMTP configuration is working correctly.</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Email <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <input type="email" name="test_email_to" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="test@example.com">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-paper-plane mr-1"></i> Send Test
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-history text-gray-500 mr-2"></i> Email Logs</h3>
            <span class="text-xs text-gray-400">Last 50 entries</span>
        </div>
        <div class="p-6">
            <div class="table-container">
                <div class="overflow-x-auto">
                    <table class="datatable min-w-full" id="emailLogsTable">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Recipient</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Module</th>
                                <th class="px-4 py-3">Sent By</th>
                                <th class="px-4 py-3">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($emailLogs as $log): ?>
                            <tr class="text-sm text-gray-700 hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500"><?php echo formatDateTime($log['sent_at']); ?></td>
                                <td class="px-4 py-3"><?php echo h($log['recipient']); ?></td>
                                <td class="px-4 py-3 max-w-xs truncate"><?php echo h($log['subject']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $log['status'] === 'Sent' ? 'bg-emerald-100 text-emerald-800' : ($log['status'] === 'Failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                        <?php echo $log['status']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3"><?php echo h($log['module'] ?: 'N/A'); ?></td>
                                <td class="px-4 py-3"><?php echo h($log['sent_by_name'] ?: 'System'); ?></td>
                                <td class="px-4 py-3 max-w-xs truncate text-red-600"><?php echo h($log['error_message'] ?: ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($emailLogs)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No email logs yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
