<?php
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id'])) {
    logAudit($pdo, 'Logout', 'Authentication', 'User ' . $_SESSION['username'] . ' logged out.');
}

session_unset();
session_destroy();
header('Location: login.php?logout=1');
exit;
?>
