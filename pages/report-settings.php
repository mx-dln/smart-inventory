<?php
$page = 'report-settings';
$reportPreparedBy = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System User';

$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'report_prepared_by' LIMIT 1");
$stmt->execute();
$savedValue = $stmt->fetchColumn();
if ($savedValue !== false && $savedValue !== '') $reportPreparedBy = $savedValue;

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $preparedBy = trim($_POST['prepared_by'] ?? '');
        if ($preparedBy === '') {
            $error = 'Please enter a prepared-by name.';
        } else {
            $save = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('report_prepared_by', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $save->execute([$preparedBy]);
            $reportPreparedBy = $preparedBy;
            $success = 'Report settings saved successfully.';
        }
    }
}
?>

<div class="max-w-3xl">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Report Settings</h2>
        <p class="text-gray-500 text-sm mt-1">Configure the name shown in the Prepared by section of printed reports.</p>
    </div>

    <?php if ($success): ?><div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700"><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700"><i class="fas fa-exclamation-circle mr-2"></i><?php echo h($error); ?></div><?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST">
            <?php echo csrfField(); ?>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="prepared_by">Prepared by name</label>
            <input id="prepared_by" name="prepared_by" type="text" required value="<?php echo h($reportPreparedBy); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" placeholder="e.g. Juan Dela Cruz">
            <p class="text-xs text-gray-500 mt-2">This value is used dynamically on printed reports.</p>
            <button type="submit" class="mt-5 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700"><i class="fas fa-save mr-1"></i> Save Report Settings</button>
        </form>
    </div>
</div>
