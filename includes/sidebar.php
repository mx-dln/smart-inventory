<?php
$role = $_SESSION['role'] ?? '';
?>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out">
    <div class="flex flex-col h-full">
        <div class="flex h-16 border-b border-gray-200 px-4">
            <div class="flex items-center space-x-3">
                <img src="<?php echo BASE_URL; ?>assets/images/isu_logo.png" alt="ISU" class="h-9 w-auto">
                <div>
                    <h1 class="text-sm font-bold text-gray-900">Smart Inventory</h1>
                    <p class="text-xs text-gray-500">ISU Cauayan FIC</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <a href="index?page=dashboard" class="sidebar-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>" data-page="dashboard">
                <i class="fas fa-chart-pie w-5"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo in_array($page, ['inventory','stock-in','stock-out','categories','units','batches','borrow']) ? 'active' : ''; ?>">
                    <i class="fas fa-boxes w-5"></i>
                    <span>Inventory</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo in_array($page, ['inventory','stock-in','stock-out','categories','units','batches','borrow']) ? 'open' : ''; ?>">
                    <a href="index?page=inventory" class="sidebar-sub-link <?php echo $page == 'inventory' ? 'active' : ''; ?>">
                        <i class="fas fa-box w-4"></i>
                        <span>All Items</span>
                    </a>
                    <a href="index?page=borrow" class="sidebar-sub-link <?php echo $page == 'borrow' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-holding w-4"></i>
                        <span>Borrow & Return</span>
                    </a>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager'])): ?>
                    <a href="index?page=stock-in" class="sidebar-sub-link <?php echo $page == 'stock-in' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-down w-4"></i>
                        <span>Stock In</span>
                    </a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager', 'Production Staff'])): ?>
                    <a href="index?page=stock-out" class="sidebar-sub-link <?php echo $page == 'stock-out' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up w-4"></i>
                        <span>Stock Out</span>
                    </a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager'])): ?>
                    <a href="index?page=categories" class="sidebar-sub-link <?php echo $page == 'categories' ? 'active' : ''; ?>">
                        <i class="fas fa-tags w-4"></i>
                        <span>Categories</span>
                    </a>
                    <a href="index?page=units" class="sidebar-sub-link <?php echo $page == 'units' ? 'active' : ''; ?>">
                        <i class="fas fa-ruler w-4"></i>
                        <span>Units</span>
                    </a>
                    <?php endif; ?>
                    <a href="index?page=batches" class="sidebar-sub-link <?php echo $page == 'batches' ? 'active' : ''; ?>">
                        <i class="fas fa-layer-group w-4"></i>
                        <span>Batch / Lot</span>
                    </a>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager'])): ?>
                    <a href="index?page=label-designer" class="sidebar-sub-link <?php echo $page == 'label-designer' ? 'active' : ''; ?>">
                        <i class="fas fa-tag w-4"></i>
                        <span>Label Designer</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo in_array($page, ['production', 'purchase-requests']) ? 'active' : ''; ?>">
                    <i class="fas fa-industry w-5"></i>
                    <span>Production</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo in_array($page, ['production', 'purchase-requests']) ? 'open' : ''; ?>">
                    <a href="index?page=production" class="sidebar-sub-link <?php echo $page == 'production' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list w-4"></i>
                        <span>Production Requests</span>
                    </a>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager', 'Production Staff'])): ?>
                    <a href="index?page=purchase-requests" class="sidebar-sub-link <?php echo $page == 'purchase-requests' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart w-4"></i>
                        <span>Purchase Requests</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo in_array($page, ['suppliers', 'supplier-analytics']) ? 'active' : ''; ?>">
                    <i class="fas fa-truck w-5"></i>
                    <span>Suppliers</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo in_array($page, ['suppliers', 'supplier-analytics']) ? 'open' : ''; ?>">
                    <a href="index?page=suppliers" class="sidebar-sub-link <?php echo $page == 'suppliers' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book w-4"></i>
                        <span>Manage Suppliers</span>
                    </a>
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager'])): ?>
                    <a href="index?page=supplier-analytics" class="sidebar-sub-link <?php echo $page == 'supplier-analytics' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar w-4"></i>
                        <span>Performance Analytics</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo in_array($page, ['forecasting','kpi-dashboard','recommendations','timeline']) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Analytics</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo in_array($page, ['forecasting','kpi-dashboard','recommendations','timeline']) ? 'open' : ''; ?>">
                    <?php if (in_array($role, ['Administrator', 'Inventory Manager'])): ?>
                    <a href="index?page=forecasting" class="sidebar-sub-link <?php echo $page == 'forecasting' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line w-4"></i>
                        <span>Forecasting</span>
                    </a>
                    <a href="index?page=kpi-dashboard" class="sidebar-sub-link <?php echo $page == 'kpi-dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt w-4"></i>
                        <span>KPI Dashboard</span>
                    </a>
                    <a href="index?page=recommendations" class="sidebar-sub-link <?php echo $page == 'recommendations' ? 'active' : ''; ?>">
                        <i class="fas fa-lightbulb w-4"></i>
                        <span>Recommendations</span>
                    </a>
                    <?php endif; ?>
                    <a href="index?page=timeline" class="sidebar-sub-link <?php echo $page == 'timeline' ? 'active' : ''; ?>">
                        <i class="fas fa-history w-4"></i>
                        <span>Activity Timeline</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo $page == 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Reports</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo $page == 'reports' ? 'open' : ''; ?>">
                    <a href="index?page=reports&type=inventory" class="sidebar-sub-link">
                        <i class="fas fa-boxes w-4"></i>
                        <span>Inventory Report</span>
                    </a>
                    <a href="index?page=reports&type=movement" class="sidebar-sub-link">
                        <i class="fas fa-exchange-alt w-4"></i>
                        <span>Stock Movement</span>
                    </a>
                    <a href="index?page=reports&type=low-stock" class="sidebar-sub-link">
                        <i class="fas fa-exclamation-triangle w-4"></i>
                        <span>Low Stock</span>
                    </a>
                    <a href="index?page=reports&type=expired" class="sidebar-sub-link">
                        <i class="fas fa-clock w-4"></i>
                        <span>Expired Items</span>
                    </a>
                    <a href="index?page=reports&type=transactions" class="sidebar-sub-link">
                        <i class="fas fa-chart-bar w-4"></i>
                        <span>Transactions</span>
                    </a>
                </div>
            </div>

            <?php if ($role === 'Administrator'): ?>
            <div class="sidebar-group">
                <div class="sidebar-group-title <?php echo in_array($page, ['users','roles','audit-logs','backups','email-settings','settings']) ? 'active' : ''; ?>">
                    <i class="fas fa-cog w-5"></i>
                    <span>Administration</span>
                    <i class="fas fa-chevron-down ml-auto text-xs transition-transform"></i>
                </div>
                <div class="sidebar-submenu <?php echo in_array($page, ['users','roles','audit-logs','backups','email-settings','settings']) ? 'open' : ''; ?>">
                    <a href="index?page=users" class="sidebar-sub-link <?php echo $page == 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users w-4"></i><span>Users</span>
                    </a>
                    <a href="index?page=roles" class="sidebar-sub-link <?php echo $page == 'roles' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt w-4"></i><span>Roles & Permissions</span>
                    </a>
                    <a href="index?page=audit-logs" class="sidebar-sub-link <?php echo $page == 'audit-logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history w-4"></i><span>Audit Logs</span>
                    </a>
                    <a href="index?page=backups" class="sidebar-sub-link <?php echo $page == 'backups' ? 'active' : ''; ?>">
                        <i class="fas fa-database w-4"></i><span>Backup & Restore</span>
                    </a>
                    <a href="index?page=email-settings" class="sidebar-sub-link <?php echo $page == 'email-settings' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope w-4"></i><span>Email Settings</span>
                    </a>
                    <a href="index?page=settings" class="sidebar-sub-link <?php echo $page == 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-sliders-h w-4"></i><span>System Settings</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <div class="p-4 border-t border-gray-200">
            <p class="text-xs text-gray-400 text-center">&copy; <?php echo date('Y'); ?> ISU Cauayan FIC</p>
        </div>
    </div>
</aside>

<div id="sidebarOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-30 hidden lg:hidden"></div>

<script>
$('#sidebarToggle').on('click', function() {
    $('#sidebar').toggleClass('-translate-x-full');
    $('#sidebarOverlay').toggleClass('hidden');
});

$('#sidebarOverlay').on('click', function() {
    $('#sidebar').addClass('-translate-x-full');
    $('#sidebarOverlay').addClass('hidden');
});

$('.sidebar-group-title').on('click', function() {
    $(this).find('.fa-chevron-down').toggleClass('rotate-180');
    $(this).next('.sidebar-submenu').slideToggle(200);
});

$('.sidebar-link, .sidebar-sub-link').each(function() {
    if ($(this).hasClass('active')) {
        $(this).closest('.sidebar-submenu').addClass('open').show();
        $(this).closest('.sidebar-submenu').prev('.sidebar-group-title').find('.fa-chevron-down').addClass('rotate-180');
    }
});
</script>
