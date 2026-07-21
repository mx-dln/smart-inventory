<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT br.*, i.name as item_name, i.item_code, i.current_stock as item_current_stock,
                       rb.full_name as returned_by_name, rc.full_name as received_by_name
                       FROM borrow_records br
                       LEFT JOIN inventory_items i ON br.item_id = i.id
                       LEFT JOIN users rb ON br.returned_by = rb.id
                       LEFT JOIN users rc ON br.received_by = rc.id
                       WHERE br.id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($record ?: null);
