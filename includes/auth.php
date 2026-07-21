<?php
require_once __DIR__ . '/../config/config.php';

checkSessionTimeout();

requireLogin();

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Check notifications only once every 5 minutes
if (empty($_SESSION['last_notification_check']) || time() - $_SESSION['last_notification_check'] > 300) {
    checkLowStockNotifications($pdo);
    checkExpirationNotifications($pdo);
    $_SESSION['last_notification_check'] = time();
}
