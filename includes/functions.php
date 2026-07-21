<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($roleName) {
    if (!isset($_SESSION['role'])) return false;
    if (is_array($roleName)) {
        return in_array($_SESSION['role'], $roleName);
    }
    return $_SESSION['role'] === $roleName;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generateItemCode($categoryId, $pdo) {
    $prefix = 'ITM';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($categoryId, 2, '0', STR_PAD_LEFT) . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function generateRequestNumber($pdo) {
    $year = date('Y');
    $month = date('m');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM production_requests WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn() + 1;
    return 'PR-' . $year . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals);
}

function getStatusBadge($status) {
    $map = [
        'Active' => 'bg-emerald-100 text-emerald-800',
        'Inactive' => 'bg-gray-100 text-gray-800',
        'Pending' => 'bg-yellow-100 text-yellow-800',
        'Approved' => 'bg-blue-100 text-blue-800',
        'Released' => 'bg-indigo-100 text-indigo-800',
        'Completed' => 'bg-emerald-100 text-emerald-800',
        'Rejected' => 'bg-red-100 text-red-800',
        'Low Stock' => 'bg-orange-100 text-orange-800',
        'Expired' => 'bg-red-100 text-red-800',
        'Near Expiry' => 'bg-yellow-100 text-yellow-800',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-800';
}

function getExpirationStatus($expirationDate) {
    if (!$expirationDate) return ['label' => 'N/A', 'class' => 'bg-gray-100 text-gray-800'];
    $now = new DateTime();
    $exp = new DateTime($expirationDate);
    $diff = $now->diff($exp);
    $days = (int)$diff->format('%r%a');
    
    if ($days < 0) return ['label' => 'Expired', 'class' => 'bg-red-100 text-red-800'];
    if ($days <= 7) return ['label' => $days . ' days left', 'class' => 'bg-red-100 text-red-800'];
    if ($days <= 29) return ['label' => $days . ' days left', 'class' => 'bg-orange-100 text-orange-800'];
    if ($days <= 90) return ['label' => $days . ' days left', 'class' => 'bg-yellow-100 text-yellow-800'];
    return ['label' => $days . ' days left', 'class' => 'bg-emerald-100 text-emerald-800'];
}

function logAudit($pdo, $action, $module, $description) {
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'System';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, module, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $username, $action, $module, $description, $ip, $userAgent]);
}

function createNotification($pdo, $type, $title, $message, $relatedModule = null, $relatedId = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (type, title, message, related_module, related_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$type, $title, $message, $relatedModule, $relatedId]);
}

function getLowStockItems($pdo) {
    $stmt = $pdo->prepare("SELECT i.*, c.category_name, u.unit_name, s.company_name 
                           FROM inventory_items i 
                           LEFT JOIN categories c ON i.category_id = c.id 
                           LEFT JOIN units u ON i.unit_id = u.id 
                           LEFT JOIN suppliers s ON i.supplier_id = s.id 
                           WHERE i.current_stock <= i.min_stock AND i.status = 'Active'");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getExpiredItems($pdo) {
    $stmt = $pdo->prepare("SELECT i.*, c.category_name, u.unit_name 
                           FROM inventory_items i 
                           LEFT JOIN categories c ON i.category_id = c.id 
                           LEFT JOIN units u ON i.unit_id = u.id 
                           WHERE i.expiration_date IS NOT NULL 
                           AND i.expiration_date < CURDATE() 
                           AND i.status = 'Active'");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getNearExpiryItems($pdo, $days = 30) {
    $stmt = $pdo->prepare("SELECT i.*, c.category_name, u.unit_name, 
                           DATEDIFF(i.expiration_date, CURDATE()) as days_remaining
                           FROM inventory_items i 
                           LEFT JOIN categories c ON i.category_id = c.id 
                           LEFT JOIN units u ON i.unit_id = u.id 
                           WHERE i.expiration_date IS NOT NULL 
                           AND i.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                           AND i.status = 'Active'
                           ORDER BY i.expiration_date ASC");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

function getDashboardStats($pdo) {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM inventory_items i JOIN categories c ON i.category_id = c.id WHERE c.category_type = 'Raw Materials' AND i.status = 'Active'");
    $stats['raw_materials'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM inventory_items i JOIN categories c ON i.category_id = c.id WHERE c.category_type = 'Production Supplies' AND i.status = 'Active'");
    $stats['production_supplies'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE current_stock <= min_stock AND status = 'Active'");
    $stats['low_stock'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE() AND status = 'Active'");
    $stats['expired'] = $stmt->fetchColumn();
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $stats['today_transactions'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'Active'");
    $stats['suppliers'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(current_stock * unit_cost), 0) FROM inventory_items WHERE status = 'Active'");
    $stats['total_value'] = $stmt->fetchColumn();
    
    return $stats;
}

function getUnreadNotifications($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    return $stmt->fetchColumn();
}

function generateBarcode($pdo, $format = 'CODE128') {
    $prefix = 'SI';
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items");
    $count = $stmt->fetchColumn() + 1;
    $barcode = $prefix . date('Ymd') . str_pad($count, 6, '0', STR_PAD_LEFT);
    return $barcode;
}

function paginate($page, $totalRecords, $perPage = 20) {
    $totalPages = ceil($totalRecords / $perPage);
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'offset' => $offset,
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords,
    ];
}

function getPageTitle($page) {
    $titles = [
        'dashboard' => 'Dashboard',
        'inventory' => 'Inventory Management',
        'stock-in' => 'Stock In',
        'stock-out' => 'Stock Out',
        'categories' => 'Categories',
        'units' => 'Units',
        'borrow' => 'Borrow & Return',
        'production' => 'Production Requests',
        'batches' => 'Batch / Lot Management',
        'suppliers' => 'Suppliers',
        'reports' => 'Reports',
        'users' => 'Users',
        'settings' => 'Settings',
        'audit-logs' => 'Audit Logs',
        'forecasting' => 'Inventory Forecasting',
        'kpi-dashboard' => 'KPI Dashboard',
        'recommendations' => 'Recommendations',
        'label-designer' => 'Label Designer',
        'timeline' => 'Activity Timeline',
        'supplier-analytics' => 'Supplier Analytics',
    ];
    return $titles[$page] ?? 'Dashboard';
}

function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) return false;
    
    $filename = time() . '_' . basename($file['name']);
    $targetPath = $targetDir . $filename;
    $fileType = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes)) return false;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    return false;
}

function checkLowStockNotifications($pdo) {
    $items = getLowStockItems($pdo);
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'Low Stock' AND related_id = ?");
        $stmt->execute([$item['id']]);
        if (!$stmt->fetch()) {
            createNotification($pdo, 'Low Stock', 'Low Stock Alert: ' . $item['name'], 
                'Current stock (' . $item['current_stock'] . ') is below minimum level (' . $item['min_stock'] . ').', 
                'inventory', $item['id']);
        }
    }
}

function checkExpirationNotifications($pdo) {
    $items = getExpiredItems($pdo);
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'Expired' AND related_id = ?");
        $stmt->execute([$item['id']]);
        if (!$stmt->fetch()) {
            createNotification($pdo, 'Expired', 'Item Expired: ' . $item['name'], 
                'This item has expired on ' . formatDate($item['expiration_date']) . '.', 
                'inventory', $item['id']);
        }
    }
    
    $items = getNearExpiryItems($pdo, 30);
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT id FROM notifications WHERE type = 'Near Expiry' AND related_id = ?");
        $stmt->execute([$item['id']]);
        if (!$stmt->fetch()) {
            createNotification($pdo, 'Near Expiry', 'Near Expiry: ' . $item['name'], 
                $item['name'] . ' will expire in ' . $item['days_remaining'] . ' days.', 
                'inventory', $item['id']);
        }
    }
}

function exportToCSV($data, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
