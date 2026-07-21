<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.*, i.name as item_name FROM batches b LEFT JOIN inventory_items i ON b.item_id = i.id WHERE b.id = ?");
$stmt->execute([$id]);
header('Content-Type: application/json');
echo json_encode($stmt->fetch());
