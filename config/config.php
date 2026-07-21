<?php
// Application configuration

session_start();

// Base URL (auto-detect)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$scriptDir = dirname($scriptName);
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir === '.' || $scriptDir === '') $scriptDir = '';
define('BASE_URL', $protocol . '://' . $host . $scriptDir . '/');
define('APP_NAME', 'Smart Inventory System');
define('SCHOOL_NAME', 'ISU Cauayan Food Innovation Center');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('BARCODE_PATH', __DIR__ . '/../uploads/barcodes/');
define('QRCODE_PATH', __DIR__ . '/../uploads/qrcodes/');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
?>
