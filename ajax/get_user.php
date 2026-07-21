<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, username, email, full_name, role_id, status FROM users WHERE id = ?");
$stmt->execute([$id]);
header('Content-Type: application/json');
echo json_encode($stmt->fetch());
