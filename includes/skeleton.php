<?php
// Reusable skeleton loading components
// Call these in place of real content; JS can replace them when data loads.
?>
<!-- Stat card skeleton -->
<div class="skeleton-loading stat-card-skeleton" style="display: none;">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="skeletonStats">
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="skeleton-stat-card">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="skeleton skeleton-text-sm mb-2"></div>
                    <div class="skeleton skeleton-number"></div>
                </div>
                <div class="skeleton skeleton-icon"></div>
            </div>
            <div class="mt-3 skeleton skeleton-text-sm" style="width:45%"></div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-chart"></div></div>
        <div class="skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-chart"></div></div>
    </div>

    <div class="skeleton-card mb-6">
        <div class="skeleton skeleton-title mb-4"></div>
        <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="skeleton skeleton-table-row"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Single stat card skeleton -->
<div class="skeleton-loading single-stat-skeleton" style="display: none;">
    <div class="skeleton-stat-card">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="skeleton skeleton-text-sm mb-2"></div>
                <div class="skeleton skeleton-number"></div>
            </div>
            <div class="skeleton skeleton-icon"></div>
        </div>
        <div class="mt-3 skeleton skeleton-text-sm" style="width:45%"></div>
    </div>
</div>

<!-- Table skeleton -->
<div class="skeleton-loading table-skeleton" style="display: none;">
    <div class="skeleton-card">
        <div class="skeleton skeleton-title mb-4"></div>
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="skeleton skeleton-table-row"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Chart card skeleton -->
<div class="skeleton-loading chart-skeleton" style="display: none;">
    <div class="skeleton-card">
        <div class="skeleton skeleton-title mb-4"></div>
        <div class="skeleton skeleton-chart"></div>
    </div>
</div>

<script>
function showSkeleton(type) { $('.' + type + '-skeleton').show(); }
function hideSkeleton(type) { $('.' + type + '-skeleton').hide(); }

$(document).ready(function() {
    if ($('.skeleton-loading').length > 0) {
        $('.skeleton-loading').show();
        setTimeout(function() {
            $('.skeleton-loading').fadeOut(250, function() {
                $('.actual-content').fadeIn(250);
            });
        }, 400);
    }
});
</script>
