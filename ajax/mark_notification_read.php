<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) { http_response_code(403); exit; }
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
$stmt->execute([$id]);
header('Content-Type: application/json');
echo json_encode(['success' => true]);
