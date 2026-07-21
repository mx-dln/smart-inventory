<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT pr.*, u.full_name as requestor_name FROM purchase_requests pr LEFT JOIN users u ON pr.requestor_id = u.id WHERE pr.id = ?");
$stmt->execute([$id]);
$pr = $stmt->fetch();

if (!$pr) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Purchase request not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT pri.*, c.category_name, u.unit_name, u.abbreviation FROM purchase_request_items pri LEFT JOIN categories c ON pri.category_id = c.id LEFT JOIN units u ON pri.unit_id = u.id WHERE pri.pr_id = ? ORDER BY pri.id");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT pra.*, us.full_name as approver_name FROM purchase_request_approvals pra LEFT JOIN users us ON pra.approver_id = us.id WHERE pra.pr_id = ? ORDER BY pra.created_at");
$stmt->execute([$id]);
$approvals = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode([
    'pr' => $pr,
    'items' => $items,
    'approvals' => $approvals,
]);
