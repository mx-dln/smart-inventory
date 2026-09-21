<?php
// Main router for Smart Inventory System
require_once __DIR__ . '/includes/auth.php';

// Get page parameter
$page = $_GET['page'] ?? 'dashboard';
$page = preg_replace('/[^a-zA-Z0-9_\-]/', '', $page);

// Define allowed pages and their access roles
$allowedPages = [
    'dashboard' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'inventory' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'stock-in' => ['Administrator', 'Inventory Manager'],
    'stock-out' => ['Administrator', 'Inventory Manager', 'Production Staff'],
    'borrow' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'categories' => ['Administrator', 'Inventory Manager'],
    'units' => ['Administrator', 'Inventory Manager'],
    'production' => ['Administrator', 'Inventory Manager', 'Production Staff'],
    'batches' => ['Administrator', 'Inventory Manager', 'Production Staff'],
    'suppliers' => ['Administrator', 'Inventory Manager'],
    'supplier-analytics' => ['Administrator', 'Inventory Manager'],
    'reports' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'periodic-reports' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'users' => ['Administrator'],
    'settings' => ['Administrator'],
    'audit-logs' => ['Administrator'],
    'notifications' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'profile' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'forecasting' => ['Administrator', 'Inventory Manager'],
    'kpi-dashboard' => ['Administrator', 'Inventory Manager'],
    'recommendations' => ['Administrator', 'Inventory Manager'],
    'label-designer' => ['Administrator', 'Inventory Manager'],
    'timeline' => ['Administrator', 'Inventory Manager', 'Production Staff', 'Faculty'],
    'purchase-requests' => ['Administrator', 'Inventory Manager', 'Production Staff'],
    'roles' => ['Administrator'],
    'email-settings' => ['Administrator'],
    'backups' => ['Administrator'],
];

// Check if page exists and user has access
$userRole = $_SESSION['role'] ?? '';
$pageFile = __DIR__ . '/pages/' . $page . '.php';

// Handle sub-pages with type parameter
$type = $_GET['type'] ?? '';

// Check if it's a sub-directory page (like reports/inventory)
if (!file_exists($pageFile) && !empty($type)) {
    $pageFile = __DIR__ . '/pages/' . $page . '/' . $type . '.php';
}

// Final fallback
if (!file_exists($pageFile)) {
    $pageFile = __DIR__ . '/pages/dashboard.php';
    $page = 'dashboard';
}

// Check role access
if (isset($allowedPages[$page])) {
    if (!in_array($userRole, $allowedPages[$page])) {
        $page = 'dashboard';
        $pageFile = __DIR__ . '/pages/dashboard.php';
    }
}

// Handle CSV export early - before any HTML output
if ($page === 'reports' && isset($_GET['export']) && file_exists($pageFile)) {
    require $pageFile;
    exit;
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col lg:ml-64">
        <!-- Navbar -->
        <?php require_once __DIR__ . '/includes/navbar.php'; ?>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-gray-50">
            <?php
            if (file_exists($pageFile)) {
                require $pageFile;
            } else {
                echo '<div class="text-center py-12"><i class="fas fa-exclamation-circle text-4xl text-gray-400 mb-4"></i><p class="text-gray-600">Page not found.</p></div>';
            }
            ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if (!empty($_SESSION['show_splash'])): ?>
<!-- Welcome Splash Screen (shown once after login) -->
<style>
@keyframes splashBounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
#splashScreen{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#059669 0%,#0f172a 100%);transition:opacity .6s ease}
#splashScreen .dot{width:10px;height:10px;background:#fff;border-radius:9999px;animation:splashBounce .8s ease infinite}
#splashScreen .logo{height:96px;width:auto;margin:0 auto 24px}
#splashScreen h1{font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:8px}
#splashScreen .sub{color:#a7f3d0;font-size:.875rem;margin-bottom:24px}
#splashScreen .welcome{color:#fff;font-size:1.125rem;font-weight:500;margin-top:24px}
#splashScreen .loading{color:#a7f3d0;font-size:.75rem;margin-top:4px}
</style>
<div id="splashScreen">
    <div style="text-align:center;padding:0 24px">
        <img src="<?php echo BASE_URL; ?>assets/images/isu_logo.png" alt="ISU Logo" class="logo">
        <h1><?php echo APP_NAME; ?></h1>
        <p class="sub"><?php echo SCHOOL_NAME; ?></p>
        <div style="display:flex;justify-content:center;gap:8px">
            <span class="dot" style="animation-delay:0ms"></span>
            <span class="dot" style="animation-delay:150ms"></span>
            <span class="dot" style="animation-delay:300ms"></span>
        </div>
        <p class="welcome">Welcome, <?php echo h($_SESSION['splash_user'] ?? $_SESSION['username']); ?>!</p>
        <p class="loading">Loading your dashboard...</p>
    </div>
</div>
<script>
$(document).ready(function() {
    setTimeout(function() {
        $('#splashScreen').fadeOut(600, function() { $(this).remove(); });
    }, 2500);
});
</script>
<?php
// Clear splash flag so it only shows once per login
unset($_SESSION['show_splash'], $_SESSION['splash_user']);
endif;
?>
