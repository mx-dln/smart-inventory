<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$stmt = $pdo->prepare("SELECT id, type, title, message, related_module, related_id, is_read, created_at FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$notifications = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($notifications);
