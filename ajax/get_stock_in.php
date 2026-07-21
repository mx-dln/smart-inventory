<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT si.*, i.name as item_name, i.item_code, i.current_stock as item_current_stock, sup.company_name as supplier_name, u.full_name as received_by_name FROM stock_in si LEFT JOIN inventory_items i ON si.item_id = i.id LEFT JOIN suppliers sup ON si.supplier_id = sup.id LEFT JOIN users u ON si.received_by = u.id WHERE si.id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($record ?: null);
