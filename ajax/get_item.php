<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT i.*, c.category_name, c.category_type, u.unit_name, u.abbreviation, s.company_name as supplier_name FROM inventory_items i LEFT JOIN categories c ON i.category_id = c.id LEFT JOIN units u ON i.unit_id = u.id LEFT JOIN suppliers s ON i.supplier_id = s.id WHERE i.id = ?");
    $stmt->execute([$id]);
} else {
    header('Content-Type: application/json');
    echo json_encode(null);
    exit;
}
$item = $stmt->fetch();
header('Content-Type: application/json');
echo json_encode($item ?: null);
