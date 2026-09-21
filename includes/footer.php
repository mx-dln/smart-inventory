
    <div id="loadingSpinner" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-[9999] flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 flex flex-col items-center space-y-3 min-w-[200px]">
            <div class="animate-spin rounded-full h-10 w-10 border-[3px] border-emerald-500 border-t-transparent"></div>
            <span class="text-gray-700 dark:text-gray-200 font-medium text-sm">Processing...</span>
            <span class="text-gray-400 dark:text-gray-500 text-xs">Please wait</span>
        </div>
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Custom Confirm Dialog -->
<div id="confirmDialog" class="hidden fixed inset-0 z-[9999] flex items-center justify-center">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeConfirm()"></div>
    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm mx-4 p-6 text-center">
        <div class="w-14 h-14 bg-red-100 dark:bg-red-900/40 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-red-500 dark:text-red-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2" id="confirmTitle">Are you sure?</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6" id="confirmMessage">This action cannot be undone.</p>
        <div class="flex space-x-3 justify-center">
            <button onclick="closeConfirm()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
            <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition-colors">
                <i class="fas fa-trash mr-1"></i> Delete
            </button>
        </div>
        <input type="hidden" id="confirmCallback" value="">
    </div>
</div>

<script>
var csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
var currentUserName = '<?php echo h($_SESSION['full_name'] ?? $_SESSION['username']); ?>';
var BASE_URL_JS = '<?php echo BASE_URL; ?>';
var confirmCallback = null;
function showConfirm(message, fn) {
    document.getElementById('confirmMessage').textContent = message || 'This action cannot be undone.';
    confirmCallback = fn;
    document.getElementById('confirmDeleteBtn').onclick = function() {
        var cb = confirmCallback;
        closeConfirm();
        if (typeof cb === 'function') setTimeout(cb, 100);
    };
    document.getElementById('confirmDialog').classList.remove('hidden');
}
function closeConfirm() { document.getElementById('confirmDialog').classList.add('hidden'); confirmCallback = null; }

</script>

<!-- Help / User Manual Modal -->
<div id="helpModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-60" onclick="closeHelp()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center z-10 rounded-t-2xl">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-question-circle text-emerald-600 text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">User Manual</h2>
                        <p class="text-xs text-gray-500">Smart Inventory System — ISU Cauayan Food Innovation Center</p>
                    </div>
                </div>
                <button onclick="closeHelp()" class="text-gray-400 hover:text-gray-600 p-1"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 space-y-6">
                <!-- Overview -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-info-circle text-emerald-500 mr-2"></i> System Overview</h3>
                    <p class="text-sm text-gray-600 mt-2 leading-relaxed">The Smart Inventory System is a comprehensive, enterprise-grade inventory management platform designed for the ISU Cauayan Food Innovation Center. It manages raw materials, production supplies, packaging, chemicals, equipment, and office supplies. Features include batch tracking, FIFO, forecasting, purchase requisitions, borrow/return tracking, role-based access, and advanced analytics — all without any PHP framework.</p>
                </div>

                <!-- Getting Started -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-rocket text-emerald-500 mr-2"></i> Getting Started</h3>
                    <div class="mt-2 space-y-2 text-sm text-gray-600">
                        <p><strong>Login:</strong> Use the demo accounts on the login page. Click a role card to auto-fill credentials or type manually.</p>
                        <p><strong>Dashboard:</strong> After login, the dashboard shows skeleton loading while data loads, then displays KPI cards, charts, low stock alerts, expiration warnings, recent transactions, and recommendation widgets.</p>
                        <p><strong>Navigation:</strong> Use the sidebar on the left. Modules are grouped into Inventory, Production, Suppliers, Analytics, Reports, and Administration. Your role determines which menus are visible.</p>
                    </div>
                </div>

                <!-- User Roles -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-users text-emerald-500 mr-2"></i> User Roles &amp; Permissions</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200 rounded-lg">
                            <thead><tr class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                                <th class="px-3 py-2 border-b">Module</th><th class="px-3 py-2 border-b">Admin</th><th class="px-3 py-2 border-b">Inventory Mgr</th><th class="px-3 py-2 border-b">Production</th><th class="px-3 py-2 border-b">Faculty</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                $permMatrix = [
                                    ['Dashboard', '✓', '✓', '✓', '✓'],
                                    ['Inventory (View)', '✓', '✓', '✓', '✓'],
                                    ['Inventory (Add/Edit)', '✓', '✓', '', ''],
                                    ['Stock In', '✓', '✓', '', ''],
                                    ['Stock Out', '✓', '✓', '✓', ''],
                                    ['Categories', '✓', '✓', '', ''],
                                    ['Units', '✓', '✓', '', ''],
                                    ['Batches / Lots', '✓', '✓', '✓', ''],
                                    ['Borrow &amp; Return', '✓', '✓', '✓', ''],
                                    ['Production Requests', '✓', '✓', '✓', ''],
                                    ['Purchase Requests', '✓', '✓', '✓', ''],
                                    ['Suppliers', '✓', '✓', '', ''],
                                    ['Supplier Analytics', '✓', '✓', '', ''],
                                    ['Forecasting', '✓', '✓', '', ''],
                                    ['KPI Dashboard', '✓', '✓', '', ''],
                                    ['Recommendations', '✓', '✓', '', ''],
                                    ['Activity Timeline', '✓', '✓', '✓', '✓'],
                                    ['Reports', '✓', '✓', '✓', '✓'],
                                    ['Users', '✓', '', '', ''],
                                    ['Roles &amp; Permissions', '✓', '', '', ''],
                                    ['Settings', '✓', '', '', ''],
                                    ['Email Settings', '✓', '', '', ''],
                                    ['Backup &amp; Restore', '✓', '', '', ''],
                                    ['Audit Logs', '✓', '', '', ''],
                                ];
                                foreach ($permMatrix as $row): ?>
                                <tr class="text-xs">
                                    <td class="px-3 py-1.5 font-medium text-gray-800"><?php echo $row[0]; ?></td>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <td class="px-3 py-1.5 text-center"><?php echo $row[$i] ? '<span class="text-emerald-600 font-bold">✓</span>' : '<span class="text-gray-300">—</span>'; ?></td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-xs text-gray-400 mt-2">Administrators can manage granular permissions per role via <strong>Roles &amp; Permissions</strong> page.</p>
                    </div>
                </div>

                <!-- Inventory Module -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-boxes text-emerald-500 mr-2"></i> Inventory Module</h3>
                    <div class="mt-2 space-y-3 text-sm text-gray-600">
                        <div><p class="font-medium text-gray-800">All Items</p><p>Searchable, filterable table of all inventory items with item code, name, category, stock levels, cost, expiration, and status. <strong>Add Item</strong> creates new items with auto-generated item codes. Click <strong>View</strong> for full details with transaction history. Use <strong>Edit</strong> to modify.</p></div>
                        <div><p class="font-medium text-gray-800">Stock In</p><p>Record incoming inventory (Purchase, Donation, Adjustment, Return). Automatically updates item stock and creates transaction records. Supports batch numbers, expiration dates, and reference numbers.</p></div>
                        <div><p class="font-medium text-gray-800">Stock Out</p><p>Record outgoing inventory (Production Request, Damaged, Expired, Transfer, Consumption). Validates available stock — cannot exceed current quantity. Stock is deducted automatically.</p></div>
                        <div><p class="font-medium text-gray-800">Batches / Lots</p><p>Manage item batches with lot numbers, suppliers, manufacturing/expiration dates, and quantities. FIFO (First In, First Out) is used for automatic inventory deductions. Expired batches are prevented from being used.</p></div>
                        <div><p class="font-medium text-gray-800">Borrow &amp; Return</p><p>Track equipment and tool borrowing. Record borrower, department, dates, and condition. Automatic overdue detection with penalty tracking. Items are deducted from inventory on borrow and restored on return.</p></div>
                        <div><p class="font-medium text-gray-800">Categories &amp; Units</p><p>Manage item categories (Raw Materials, Production Supplies, Packaging, etc.) and measurement units (kg, L, pcs, box, etc.).</p></div>
                    </div>
                </div>

                <!-- Label Designer -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-tag text-emerald-500 mr-2"></i> Label Designer</h3>
                    <div class="mt-2 space-y-2 text-sm text-gray-600">
                        <p><strong>Label Designer:</strong> Create custom label templates with selectable sizes (40×20mm to 100×50mm). Toggle fields: Logo, Item Name, Batch Number, Unit, Price, Supplier, Expiration, Location, Print Date. Preview and bulk print.</p>
                    </div>
                </div>

                <!-- Production & Purchasing -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-industry text-emerald-500 mr-2"></i> Production &amp; Purchasing</h3>
                    <div class="mt-2 space-y-3 text-sm text-gray-600">
                        <div><p class="font-medium text-gray-800">Production Requests</p><p>Create requests listing needed materials with quantities. Status workflow: <span class="px-1.5 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">Approved</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">Released</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Completed</span>. Release deducts inventory automatically.</p></div>
                        <div><p class="font-medium text-gray-800">Purchase Requests</p><p>Full procurement workflow: <span class="px-1.5 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">Draft</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800">Submitted</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">Department Approved</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">Inventory Mgr Approved</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">Admin Approved</span> → <span class="px-1.5 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-800">Purchased → Received → Completed</span>. Each approval level records comments and timestamps.</p></div>
                    </div>
                </div>

                <!-- Suppliers -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-truck text-emerald-500 mr-2"></i> Suppliers</h3>
                    <p class="text-sm text-gray-600 mt-2">Manage supplier records with company name, contact person, address, phone, email, and products. The <strong>Supplier Performance Analytics</strong> page rates suppliers on reliability, quality, delivery count, and overall performance with star ratings and comparison charts.</p>
                </div>

                <!-- Analytics -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-chart-line text-emerald-500 mr-2"></i> Analytics &amp; Intelligence</h3>
                    <div class="mt-2 space-y-3 text-sm text-gray-600">
                        <div><p class="font-medium text-gray-800">Inventory Forecasting</p><p>Select an item and forecasting method (Simple Moving Average or Weighted Moving Average). The system analyzes previous monthly usage and predicts future demand, reorder date, and suggested purchase quantity. Results shown with Chart.js line charts and comparison visuals.</p></div>
                        <div><p class="font-medium text-gray-800">KPI Dashboard</p><p>Enterprise-grade KPIs: Inventory Turnover Ratio, Total Inventory Value, Dead Stock Percentage, Fast Moving Items, Slow Moving Items, Monthly Consumption. Six Chart.js visualizations including bar, line, doughnut charts.</p></div>
                        <div><p class="font-medium text-gray-800">Recommendations Engine</p><p>Rule-based analysis that automatically identifies: items needing reorder, overstocked items, slow-moving or dead stock, expiring batches, and unusual consumption patterns. Color-coded by priority (Critical, Warning, Normal). Dismissable recommendations.</p></div>
                        <div><p class="font-medium text-gray-800">Activity Timeline</p><p>Chronological history of all item activities (created, updated, stock in/out, transferred, borrowed, printed, etc.). Filterable by item, action type, and date range. Exportable.</p></div>
                    </div>
                </div>

                <!-- Reports -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-file-alt text-emerald-500 mr-2"></i> Reports</h3>
                    <div class="mt-2 space-y-2 text-sm text-gray-600">
                        <p>Report types:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Inventory Report</strong> — All items with stock levels and values</li>
                            <li><strong>Stock Movement</strong> — All transactions within a date range</li>
                            <li><strong>Low Stock</strong> — Items below minimum stock level</li>
                            <li><strong>Expired Items</strong> — Expired and near-expiry items</li>
                            <li><strong>Transactions Summary</strong> — Activity grouped by date</li>
                        </ul>
                        <p class="mt-2">Export to CSV or Print directly.</p>
                    </div>
                </div>

                <!-- Administration -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-cog text-emerald-500 mr-2"></i> Administration</h3>
                    <div class="mt-2 space-y-3 text-sm text-gray-600">
                        <div><p class="font-medium text-gray-800">Roles &amp; Permissions</p><p>Granular permission system with 36+ permissions grouped by module. Assign permissions per role using the matrix editor. Permissions control every action: view, add, edit, delete, approve, export.</p></div>
                        <div><p class="font-medium text-gray-800">Email Settings</p><p>Configure SMTP server for automated email notifications. Toggle notifications for low stock, critical stock, near expiry, expired items, purchase request status changes, borrow overdue, and backups. Test email feature and email logs.</p></div>
                        <div><p class="font-medium text-gray-800">Backup &amp; Restore</p><p>One-click database backup with SQL export. Backup history with download, restore, and delete. Automatic file compression. Schedule backups via server cron if available.</p></div>
                        <div><p class="font-medium text-gray-800">Audit Logs</p><p>Every action (login, logout, insert, update, delete, approve, reject, print, export) is logged with user, IP, browser, and timestamp.</p></div>
                    </div>
                </div>

                <!-- Dashboard -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-chart-pie text-emerald-500 mr-2"></i> Dashboard</h3>
                    <div class="mt-2 space-y-2 text-sm text-gray-600">
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Statistics Cards</strong> — Raw Materials, Production Supplies, Low Stock, Expired, Today's Transactions, Inventory Value, Suppliers, Pending Requests</li>
                            <li><strong>Charts</strong> — Monthly Stock Movement, Category Distribution, Inventory Value by Category, Top Used Materials</li>
                            <li><strong>Alerts</strong> — Low stock items, expired and near-expiry items</li>
                            <li><strong>Recent Transactions</strong> — Latest 10 movements</li>
                        </ul>
                    </div>
                </div>

                <!-- Tips -->
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center"><i class="fas fa-lightbulb text-emerald-500 mr-2"></i> Tips &amp; Best Practices</h3>
                    <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600 mt-2">
                        <li>Use <strong>global search</strong> in the navbar to quickly find items, suppliers, batches, PRs, or borrow records</li>
                        <li>Enable <strong>dark mode</strong> using the moon icon in the navbar</li>
                        <li>Set <strong>minimum stock levels</strong> to receive low stock alerts automatically</li>
                        <li>Record <strong>expiration dates</strong> for perishable items to track freshness</li>
                        <li>Use <strong>Batches</strong> for items with different lots or expiration dates — FIFO is applied automatically</li>
                        <li>Check the <strong>Recommendations</strong> dashboard widget for actionable insights</li>
                        <li>Use <strong>Forecasting</strong> to plan purchases based on historical consumption</li>
                        <li>Run regular <strong>backups</strong> via the Administration panel</li>
                    </ul>
                </div>
            </div>
            <div class="sticky bottom-0 bg-gray-50 px-6 py-3 border-t border-gray-200 flex justify-between items-center rounded-b-2xl">
                <p class="text-xs text-gray-400">Smart Inventory System v1.0 — ISU Cauayan Food Innovation Center</p>
                <button onclick="closeHelp()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors">Got it</button>
            </div>
        </div>
    </div>
</div>

<script>
function closeHelp() { document.getElementById('helpModal').classList.add('hidden'); }
$(document).on('keydown', function(e) { if (e.key === 'Escape') { $('#helpModal').addClass('hidden'); } });
</script>

    <script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/inventory.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/reports.js"></script>

    <script>
    $(document).ready(function() {
        $('.datatable').each(function() {
            if (!$.fn.dataTable.isDataTable(this)) {
                var $table = $(this);
                var $container = $table.closest('.table-container');
                
                // Add loading overlay
                if ($container.length) {
                    $container.css('position', 'relative');
                    $container.append('<div class="dt-loading-overlay" style="position:absolute;inset:0;background:rgba(255,255,255,0.85);display:flex;align-items:center;justify-content:center;z-index:2;border-radius:0.75rem;transition:opacity 0.3s"><div style="text-align:center"><div class="animate-spin rounded-full h-8 w-8 border-[3px] border-emerald-500 border-t-transparent mx-auto"></div><p class="text-xs text-gray-400 mt-2">Loading data...</p></div></div>');
                    if (document.documentElement.classList.contains('dark')) $container.find('.dt-loading-overlay').css('background', 'rgba(15,23,42,0.85)');
                }
                
                var $firstRow = $table.find('tbody tr:first');
                if ($firstRow.length && $firstRow.find('td[colspan]').length > 0) {
                    if ($container.length) $container.find('.dt-loading-overlay').remove();
                    return;
                }
                
                $table.DataTable({
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    dom: '<"dt-toolbar"lf>t<"dt-footer"ip>',
                    language: {
                        search: 'Search:',
                        searchPlaceholder: 'Type to search...',
                        lengthMenu: 'Show _MENU_ entries',
                        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                        infoEmpty: 'No entries found',
                        infoFiltered: '(filtered from _MAX_ total entries)',
                        paginate: {
                            first: '«',
                            previous: '‹',
                            next: '›',
                            last: '»'
                        }
                    },
                    order: [],
                    initComplete: function() {
                        if ($container.length) {
                            $container.find('.dt-loading-overlay').fadeOut(200, function() { $(this).remove(); });
                        }
                    }
                });
            }
        });

        $('.datepicker').flatpickr({
            dateFormat: 'Y-m-d',
            allowInput: true
        });

        $('.datetimepicker').flatpickr({
            enableTime: true,
            dateFormat: 'Y-m-d H:i:S',
            allowInput: true
        });
    });

    function showToast(message, type = 'success') {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        const colors = {
            success: 'bg-emerald-500',
            error: 'bg-red-500',
            warning: 'bg-orange-500',
            info: 'bg-blue-500'
        };
        
        const toast = $('<div class="flex items-center p-4 rounded-lg shadow-lg text-white ' + (colors[type] || colors.success) + ' transition-all duration-300">' +
            '<i class="fas ' + (icons[type] || icons.success) + ' mr-3"></i>' +
            '<span class="text-sm font-medium">' + message + '</span>' +
            '<button onclick="$(this).parent().remove()" class="ml-4 hover:opacity-75"><i class="fas fa-times"></i></button>' +
            '</div>');
        
        $('#toastContainer').append(toast);
        setTimeout(() => {
            toast.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }

    function showLoading() { $('#loadingSpinner').removeClass('hidden'); }
    function hideLoading() { $('#loadingSpinner').addClass('hidden'); }

    function confirmAction(message, callback) {
        if (confirm(message)) {
            showLoading();
            callback();
        }
    }

    // Show loading on any form submission (including programmatic submit)
    var nativeFormSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        showLoading();
        nativeFormSubmit.call(this);
    };
    $(document).on('submit', 'form', function() { showLoading(); });

    $('#darkModeToggle').on('click', function() {
        $('html').toggleClass('dark');
        localStorage.setItem('darkMode', $('html').hasClass('dark'));
        $(this).find('i').toggleClass('fa-moon fa-sun');
    });

    if (localStorage.getItem('darkMode') === 'true') {
        $('html').addClass('dark');
        $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
    }

    $.ajaxSetup({
        data: {
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        }
    });
    </script>
</body>
</html>
