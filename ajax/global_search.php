<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$q = trim($_GET['q'] ?? '');
$exact = (int)($_GET['exact'] ?? 0);
if (strlen($q) < 2 && !$exact) { echo json_encode([]); exit; }

$results = [];

$like = "%$q%";

// Search inventory items
$stmt = $pdo->prepare("SELECT id, name, item_code, current_stock, 'item' as type, NULL as extra FROM inventory_items WHERE (name LIKE ? OR item_code LIKE ?) AND status='Active' LIMIT 5");
$stmt->execute([$like, $like]);
$results = array_merge($results, $stmt->fetchAll());

// Search suppliers
$stmt = $pdo->prepare("SELECT id, company_name as name, '' as item_code, 0 as current_stock, 'supplier' as type, contact_person as extra FROM suppliers WHERE (company_name LIKE ? OR contact_person LIKE ?) AND status='Active' LIMIT 3");
$stmt->execute([$like, $like]);
$results = array_merge($results, $stmt->fetchAll());

// Search batches
$stmt = $pdo->prepare("SELECT b.id, CONCAT(b.batch_number, ' - ', i.name) as name, b.batch_number as item_code, b.remaining_quantity as current_stock, 'batch' as type, i.item_code as extra FROM batches b LEFT JOIN inventory_items i ON b.item_id=i.id WHERE (b.batch_number LIKE ? OR b.lot_number LIKE ?) AND b.status='Active' LIMIT 3");
$stmt->execute([$like, $like]);
$results = array_merge($results, $stmt->fetchAll());

// Search purchase requests
$stmt = $pdo->prepare("SELECT pr.id, CONCAT('PR: ', pr.pr_number) as name, pr.pr_number as item_code, 0 as current_stock, 'purchase' as type, pr.status as extra FROM purchase_requests pr WHERE (pr.pr_number LIKE ? OR pr.department LIKE ? OR pr.purpose LIKE ?) LIMIT 3");
$stmt->execute([$like, $like, $like]);
$results = array_merge($results, $stmt->fetchAll());

// Search borrow records
$stmt = $pdo->prepare("SELECT br.id, CONCAT('Borrow: ', br.borrower_name) as name, '' as item_code, br.quantity_borrowed as current_stock, 'borrow' as type, br.status as extra FROM borrow_records br WHERE br.borrower_name LIKE ? LIMIT 3");
$stmt->execute([$like]);
$results = array_merge($results, $stmt->fetchAll());

header('Content-Type: application/json');
echo json_encode($results);
