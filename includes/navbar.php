<?php
$unreadNotif = getUnreadNotifications($pdo);
?>
<header class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <button id="sidebarToggle" class="text-gray-500 hover:text-gray-700 focus:outline-none lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="ml-4">
                    <h1 class="text-lg font-semibold text-gray-900"><?php echo h(getPageTitle($page ?? 'dashboard')); ?></h1>
                    <nav class="flex text-sm text-gray-500 mt-0.5">
                        <a href="index?page=dashboard" class="hover:text-emerald-600">Home</a>
                        <span class="mx-2">/</span>
                        <span class="text-gray-700 font-medium"><?php echo h(getPageTitle($page ?? 'dashboard')); ?></span>
                    </nav>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <div class="hidden md:block relative">
                    <input type="text" id="globalSearch" placeholder="Search inventory..." 
                           class="w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    <div id="searchResults" class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50 max-h-96 overflow-y-auto"></div>
                </div>

                <div class="relative">
                    <button id="notifDropdown" class="relative text-gray-500 hover:text-gray-700 focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($unreadNotif > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $unreadNotif > 99 ? '99+' : $unreadNotif; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-3 border-b border-gray-100 flex justify-between items-center">
                            <h3 class="font-semibold text-gray-900">Notifications</h3>
                            <button onclick="markAllRead()" class="text-xs text-emerald-600 hover:text-emerald-700">Mark all read</button>
                        </div>
                        <div class="max-h-64 overflow-y-auto" id="notifList"></div>
                        <a href="index?page=notifications" class="block p-3 text-center text-sm text-emerald-600 hover:bg-gray-50">View all notifications</a>
                    </div>
                </div>

                <button id="darkModeToggle" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i class="fas fa-moon text-xl"></i>
                </button>

                <button onclick="document.getElementById('helpModal').classList.remove('hidden')" class="text-gray-500 hover:text-emerald-600 focus:outline-none" title="Help & User Manual">
                    <i class="fas fa-question-circle text-xl"></i>
                </button>

                <div class="relative">
                    <button id="userDropdown" class="flex items-center space-x-2 focus:outline-none">
                        <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
                            <?php echo strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)); ?>
                        </div>
                        <span class="hidden md:block text-sm font-medium text-gray-700"><?php echo h($currentUser['full_name'] ?? $currentUser['username']); ?></span>
                        <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-medium text-gray-900"><?php echo h($currentUser['full_name'] ?? $currentUser['username']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo h($currentUser['role_name'] ?? ''); ?></p>
                        </div>
                        <a href="index?page=profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-user mr-2"></i> Profile</a>
                        <a href="index?page=settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-cog mr-2"></i> Settings</a>
                        <hr class="border-gray-100">
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
$('#notifDropdown').on('click', function(e) {
    e.stopPropagation();
    $('#notifMenu').toggleClass('hidden');
    if (!$('#notifMenu').hasClass('hidden')) {
        loadNotifications();
    }
});

$('#userDropdown').on('click', function(e) {
    e.stopPropagation();
    $('#userMenu').toggleClass('hidden');
});

$(document).on('click', function() {
    $('#notifMenu').addClass('hidden');
    $('#userMenu').addClass('hidden');
    $('#searchResults').addClass('hidden');
});

function loadNotifications() {
    $('#notifList').html('<div class="p-4 text-center"><div class="inline-block w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div><p class="text-xs text-gray-400 mt-1">Loading...</p></div>');
    $.ajax({
        url: 'ajax/get_notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            setTimeout(function() {
                let html = '';
                if (data.length === 0) {
                    html = '<div class="p-4 text-center text-gray-500 text-sm">No notifications</div>';
                } else {
                    data.forEach(function(notif) {
                        let icon = 'fa-bell';
                        let color = 'text-blue-500';
                        if (notif.type === 'Low Stock') { icon = 'fa-exclamation-triangle'; color = 'text-orange-500'; }
                        else if (notif.type === 'Expired') { icon = 'fa-times-circle'; color = 'text-red-500'; }
                        else if (notif.type === 'Near Expiry') { icon = 'fa-clock'; color = 'text-yellow-500'; }
                        else if (notif.type === 'Pending Request') { icon = 'fa-hourglass-half'; color = 'text-purple-500'; }
                        
                        html += '<a href="javascript:void(0)" onclick="viewNotification(' + notif.id + ',\'' + notif.related_module + '\',' + (notif.related_id || 0) + ')" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 ' + (notif.is_read == 0 ? 'bg-blue-50' : '') + '">';
                        html += '<div class="flex items-start">';
                        html += '<div class="flex-shrink-0"><i class="fas ' + icon + ' ' + color + ' mt-1"></i></div>';
                        html += '<div class="ml-3 flex-1">';
                        html += '<p class="text-sm font-medium text-gray-900">' + notif.title + '</p>';
                        html += '<p class="text-xs text-gray-500 mt-1">' + notif.message + '</p>';
                        html += '<p class="text-xs text-gray-400 mt-1">' + notif.created_at + '</p>';
                        html += '</div></div></a>';
                    });
                }
                $('#notifList').html(html);
            }, 300);
        }
    });
}

function markNotificationRead(notifId) {
    $.ajax({
        url: 'ajax/mark_notification_read.php',
        method: 'POST',
        data: { id: notifId, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' },
        success: function() {
            loadNotifications();
        }
    });
}

function markAllRead() {
    $.ajax({
        url: 'ajax/mark_all_read.php',
        method: 'POST',
        data: { csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' },
        success: function() {
            loadNotifications();
            location.reload();
        }
    });
}

function viewNotification(id, module, relatedId) {
    markNotificationRead(id);
    if (module === 'inventory' && relatedId) {
        window.location.href = 'index?page=inventory&view=' + relatedId;
    } else if (module === 'production' && relatedId) {
        window.location.href = 'index?page=production&view=' + relatedId;
    } else {
        window.location.href = 'index?page=' + (module || 'dashboard');
    }
}

var searchTimeout;
$('#globalSearch').on('keyup', function() {
    clearTimeout(searchTimeout);
    let query = $(this).val();
    if (query.length < 2) {
        $('#searchResults').addClass('hidden');
        return;
    }
    $('#searchResults').html('<div class="p-3 text-center"><i class="fas fa-spinner fa-spin text-emerald-500"></i><span class="ml-2 text-sm text-gray-500">Searching...</span></div>').removeClass('hidden');
    searchTimeout = setTimeout(function() {
        $.ajax({
            url: 'ajax/global_search.php',
            method: 'GET',
            data: { q: query },
            dataType: 'json',
            beforeSend: function() {
                $('#searchResults').html('<div class="p-3 text-center"><i class="fas fa-spinner fa-spin text-emerald-500"></i><span class="ml-2 text-sm text-gray-500">Searching...</span></div>').removeClass('hidden');
            },
            success: function(data) {
                if (data.length === 0) {
                    $('#searchResults').html('<div class="p-3 text-sm text-gray-500 text-center">No results found</div>').removeClass('hidden');
                    return;
                }
                let html = '';
                data.forEach(function(item) {
                    html += '<a href="index?page=inventory&view=' + item.id + '" class="flex items-center px-4 py-2 hover:bg-gray-50 border-b border-gray-100">';
                    html += '<div class="w-8 h-8 bg-emerald-100 rounded flex items-center justify-center mr-3">';
                    html += '<i class="fas fa-box text-emerald-600 text-sm"></i></div>';
                    html += '<div><p class="text-sm font-medium text-gray-900">' + item.name + '</p>';
                    html += '<p class="text-xs text-gray-500">' + (item.category_name || '') + ' | Stock: ' + item.current_stock + ' ' + (item.unit_name || '') + '</p></div>';
                    html += '</a>';
                });
                $('#searchResults').html(html).removeClass('hidden');
            }
        });
    }, 300);
});

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        $('#searchResults').addClass('hidden');
    }
});
</script>
