<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT so.*, i.name as item_name, i.item_code, i.current_stock as item_current_stock, u.full_name as requested_by_name FROM stock_out so LEFT JOIN inventory_items i ON so.item_id = i.id LEFT JOIN users u ON so.requested_by = u.id WHERE so.id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($record ?: null);
