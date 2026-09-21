<?php
$page = 'label-designer';
$items = $pdo->query("SELECT id, name, item_code FROM inventory_items WHERE status='Active' ORDER BY name")->fetchAll();
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<?php if ($success): ?>
<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
    <span><i class="fas fa-check-circle mr-2"></i><?php echo h($success); ?></span>
    <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
    <span><i class="fas fa-exclamation-circle mr-2"></i><?php echo h($error); ?></span>
    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Controls Panel -->
    <div class="xl:col-span-1 space-y-6">
        <!-- Item Selector & Copies - moved to top -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-cube text-gray-400 mr-2"></i> Preview Item</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Item</label>
                <select id="previewItem" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-emerald-500 focus:border-emerald-500" onchange="updatePreview()">
                    <option value="">Select Item</option>
                    <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>"><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Copies per Label</label>
                    <input type="number" id="copiesPerLabel" value="1" min="1" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Columns (A4)</label>
                    <select id="columnsSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="updateColCount()">
                        <option value="2">2</option>
                        <option value="3" selected>3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="openPrintPreview()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors"><i class="fas fa-eye mr-1"></i> Print Preview</button>
                <button onclick="openBulkPrint()" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-print mr-1"></i> Bulk Print</button>
            </div>
        </div>

        <!-- Template Size -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-arrows-alt text-gray-400 mr-2"></i> Template Size</h3>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <button onclick="setSize('40x20mm', 40, 20)" class="size-opt px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors text-center" data-size="40x20mm">40 x 20 mm</button>
                <button onclick="setSize('50x25mm', 50, 25)" class="size-opt px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors text-center" data-size="50x25mm">50 x 25 mm</button>
                <button onclick="setSize('70x35mm', 70, 35)" class="size-opt px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors text-center" data-size="70x35mm">70 x 35 mm</button>
                <button onclick="setSize('100x50mm', 100, 50)" class="size-opt px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors text-center" data-size="100x50mm">100 x 50 mm</button>
            </div>
            <button onclick="setSize('custom', 80, 40)" class="size-opt px-3 py-2 text-xs border border-gray-300 rounded-lg hover:bg-emerald-50 hover:border-emerald-300 transition-colors text-center w-full" data-size="custom">Custom</button>
            <div id="customSizeFields" class="hidden mt-3 grid grid-cols-2 gap-2">
                <div><label class="block text-xs text-gray-500 mb-1">Width (mm)</label><input type="number" id="custW" value="80" min="20" max="200" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" onchange="applyCustomSize()"></div>
                <div><label class="block text-xs text-gray-500 mb-1">Height (mm)</label><input type="number" id="custH" value="40" min="10" max="150" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" onchange="applyCustomSize()"></div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                <span>Label size: <strong id="sizeLabel">70 x 35 mm</strong></span>
                <span>Columns: <strong id="colCount">3</strong></span>
            </div>
        </div>

    </div>

    <!-- Preview Panel + Field Toggles -->
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-eye text-gray-400 mr-2"></i> Live Preview</h3>
            <div class="bg-gray-50 rounded-xl p-6 flex items-center justify-center min-h-[300px] border-2 border-dashed border-gray-200">
                <div id="labelPreview" class="bg-white shadow-lg rounded-lg overflow-hidden" style="width:350px;">
                    <div class="p-3 text-center">
                        <p class="text-xs text-gray-400">Select an item and toggle fields above</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Field Toggles (below preview) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-layer-group text-gray-400 mr-2"></i> Label Fields</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                <?php
                $fields = [
                    'logo' => 'Logo',
                    'item_name' => 'Item Name',
                    'batch' => 'Batch Number',
                    'unit' => 'Unit',
                    'price' => 'Price',
                    'supplier' => 'Supplier',
                    'expiration' => 'Expiration',
                    'location' => 'Location',
                    'print_date' => 'Print Date',
                ];
                $defaults = ['item_name', 'unit', 'price', 'batch'];
                ?>
                <?php foreach ($fields as $key => $label): ?>
                <label class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors border border-gray-100">
                    <span class="text-sm text-gray-700"><?php echo h($label); ?></span>
                    <input type="checkbox" class="field-toggle rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 ml-2" data-field="<?php echo $key; ?>" <?php echo in_array($key, $defaults) ? 'checked' : ''; ?>>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Print Modal -->
<div id="bulkPrintModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" onclick="closeModal('bulkPrintModal')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Bulk Print Labels</h3>
                <button onclick="closeModal('bulkPrintModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Items</label>
                    <select id="bulkItemSelect" multiple class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm h-40 focus:ring-emerald-500 focus:border-emerald-500">
                        <?php foreach ($items as $item): ?>
                        <option value="<?php echo $item['id']; ?>"><?php echo h($item['name']); ?> (<?php echo h($item['item_code']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Copies per Item</label>
                        <input type="number" id="bulkCopies" value="1" min="1" max="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Columns</label>
                        <select id="bulkColumns" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="2">2</option>
                            <option value="3" selected>3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('bulkPrintModal')" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="doBulkPrint()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition-colors"><i class="fas fa-print mr-1"></i> Print</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var currentSize = '70x35mm';
var currentW = 70;
var currentH = 35;

function setSize(size, w, h) {
    currentSize = size;
    currentW = w;
    currentH = h;
    document.querySelectorAll('.size-opt').forEach(function(b) {
        b.classList.toggle('bg-emerald-50', b.dataset.size === size);
        b.classList.toggle('border-emerald-400', b.dataset.size === size);
        b.classList.toggle('text-emerald-700', b.dataset.size === size);
    });
    document.getElementById('customSizeFields').classList.toggle('hidden', size !== 'custom');
    if (size !== 'custom') {
        document.getElementById('sizeLabel').textContent = w + ' x ' + h + ' mm';
        updateColCount();
        updatePreview();
    }
}

function applyCustomSize() {
    var w = parseInt(document.getElementById('custW').value) || 80;
    var h = parseInt(document.getElementById('custH').value) || 40;
    currentW = w;
    currentH = h;
    document.getElementById('sizeLabel').textContent = w + ' x ' + h + ' mm';
    updateColCount();
    updatePreview();
}

function updateColCount() {
    var cols = parseInt(document.getElementById('columnsSelect').value);
    document.getElementById('colCount').textContent = cols;
}

function getSelectedFields() {
    var fields = [];
    document.querySelectorAll('.field-toggle:checked').forEach(function(cb) {
        fields.push(cb.dataset.field);
    });
    return fields;
}

function updatePreview() {
    var itemId = document.getElementById('previewItem').value;
    if (!itemId) {
        document.getElementById('labelPreview').innerHTML = '<div class="p-3 text-center"><p class="text-xs text-gray-400">Select an item and toggle fields above</p></div>';
        return;
    }
    document.getElementById('labelPreview').innerHTML = '<div class="p-6 text-center"><div class="inline-block w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div><p class="text-xs text-gray-400 mt-2">Loading preview...</p></div>';
    $.ajax({
        url: 'ajax/get_item.php?id=' + itemId,
        dataType: 'json',
        success: function(item) {
            if (!item) return;
            setTimeout(function() { renderPreview(item); }, 250);
        }
    });
}

function renderPreview(item) {
    var fields = getSelectedFields();
    var html = '<div class="p-3 text-center" style="font-family:Arial,sans-serif;">';
    if (fields.includes('logo')) {
        html += '<div class="text-xs font-bold text-gray-500 mb-1">LOGO</div>';
    }
    if (fields.includes('item_name')) {
        html += '<div class="text-sm font-bold text-gray-900 truncate">' + (item.name || '') + '</div>';
    }
    if (fields.includes('batch')) {
        html += '<div class="text-xs text-gray-500">Batch: ' + (item.item_code || '') + '</div>';
    }
    if (fields.includes('unit')) {
        html += '<div class="text-xs text-gray-600">Unit: ' + (item.unit_name || 'N/A') + '</div>';
    }
    if (fields.includes('price')) {
        html += '<div class="text-xs text-gray-600">Price: ₱' + (parseFloat(item.unit_cost) || 0).toFixed(2) + '</div>';
    }
    if (fields.includes('supplier')) {
        html += '<div class="text-xs text-gray-500 truncate">Supplier: ' + (item.supplier_name || 'N/A') + '</div>';
    }
    if (fields.includes('expiration')) {
        html += '<div class="text-xs text-gray-500">Exp: ' + (item.expiration_date || 'N/A') + '</div>';
    }
    if (fields.includes('location')) {
        html += '<div class="text-xs text-gray-500">Loc: ' + (item.storage_location || 'N/A') + '</div>';
    }
    if (fields.includes('print_date')) {
        html += '<div class="text-xs text-gray-400">Printed: ' + new Date().toLocaleDateString() + '</div>';
    }
    html += '</div>';

    var container = document.getElementById('labelPreview');
    container.innerHTML = html;
}

function openPrintPreview() {
    var itemId = document.getElementById('previewItem').value;
    if (!itemId) { showToast('Select an item first', 'warning'); return; }
    var copies = parseInt(document.getElementById('copiesPerLabel').value) || 1;
    var cols = document.getElementById('columnsSelect').value;
    var fields = getSelectedFields().join(',');

    $.ajax({
        url: 'ajax/get_item.php?id=' + itemId,
        dataType: 'json',
        success: function(item) {
            if (!item) { showToast('Item not found', 'error'); return; }
            var w = window.open('', '_blank');
            w.document.write(buildPrintHtml([item], copies, cols, fields));
            w.document.close();
        }
    });
}

function openBulkPrint() {
    document.getElementById('bulkPrintModal').classList.remove('hidden');
}

function doBulkPrint() {
    var selects = document.getElementById('bulkItemSelect');
    var ids = [];
    for (var i = 0; i < selects.options.length; i++) {
        if (selects.options[i].selected) ids.push(selects.options[i].value);
    }
    if (ids.length === 0) { showToast('Select at least one item', 'warning'); return; }
    var copies = parseInt(document.getElementById('bulkCopies').value) || 1;
    var cols = document.getElementById('bulkColumns').value;
    var fields = getSelectedFields().join(',');

    var items = [];
    var loaded = 0;
    ids.forEach(function(id) {
        $.ajax({
            url: 'ajax/get_item.php?id=' + id,
            dataType: 'json',
            success: function(item) {
                if (item) items.push(item);
                loaded++;
                if (loaded === ids.length) finishBulkPrint();
            },
            error: function() {
                loaded++;
                if (loaded === ids.length) finishBulkPrint();
            }
        });
    });

    function finishBulkPrint() {
        if (items.length === 0) { showToast('Failed to generate bulk print', 'error'); return; }
        var w = window.open('', '_blank');
        w.document.write(buildPrintHtml(items, copies, cols, fields));
        w.document.close();
        document.getElementById('bulkPrintModal').classList.add('hidden');
    }
}

function buildPrintHtml(items, copies, cols, fieldsStr) {
    var fields = fieldsStr ? fieldsStr.split(',') : ['item_name'];
    var w = currentW;
    var h = currentH;
    var scaleW = Math.min(100, Math.max(40, w));
    var scaleH = Math.min(60, Math.max(20, h));
    var fontSize = scaleW > 70 ? '8pt' : '7pt';
    var titleSize = scaleW > 70 ? '9pt' : '7pt';

    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Label Print</title>';
    html += '<style>';
    html += '*{margin:0;padding:0;box-sizing:border-box}';
    html += 'body{font-family:Arial,sans-serif;background:#fff}';
    html += '.page{width:210mm;min-height:297mm;padding:8mm 6mm;margin:0 auto}';
    html += '.grid{display:grid;grid-template-columns:repeat(' + cols + ',1fr);gap:3mm}';
    html += '.label{text-align:center;padding:2mm 1mm;border:1px dashed #ccc;border-radius:1mm;page-break-inside:avoid;overflow:hidden}';
    html += '.label .field-name{font-size:' + titleSize + ';font-weight:bold;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}';
    html += '.label .field-sm{font-size:' + fontSize + ';color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}';
    html += '.label .logo-placeholder{font-size:7pt;font-weight:bold;color:#999;margin-bottom:1mm}';
    html += '.no-print{display:block;text-align:center;margin:10px 0}';
    html += '.no-print button{padding:10px 30px;background:#059669;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin:0 4px}';
    html += '@media print{@page{size:A4;margin:6mm 6mm}body{margin:0;padding:0}.page{width:100%;min-height:auto;padding:0;margin:0}.no-print{display:none!important}.label{border:1px solid #ddd}}';
    html += '</style></head><body>';
    html += '<div class="no-print"><button onclick="window.print()">Print</button><button onclick="window.close()" style="background:#6b7280">Close</button></div>';
    html += '<div class="page"><div class="grid">';

    for (var si = 0; si < items.length; si++) {
        var item = items[si];
        for (var c = 0; c < copies; c++) {
            html += '<div class="label">';
            if (fields.indexOf('logo') !== -1) {
                html += '<div class="logo-placeholder">LOGO</div>';
            }
            if (fields.indexOf('item_name') !== -1) {
                html += '<div class="field-name">' + (item.name || '') + '</div>';
            }
            if (fields.indexOf('batch') !== -1) {
                html += '<div class="field-sm">Batch: ' + (item.item_code || '') + '</div>';
            }
            if (fields.indexOf('unit') !== -1) {
                html += '<div class="field-sm">' + (item.unit_name ? 'Unit: '+item.unit_name : '') + '</div>';
            }
            if (fields.indexOf('price') !== -1) {
                html += '<div class="field-sm">₱' + (parseFloat(item.unit_cost)||0).toFixed(2) + '</div>';
            }
            if (fields.indexOf('supplier') !== -1) {
                html += '<div class="field-sm">' + (item.supplier_name ? 'Sup: '+item.supplier_name : '') + '</div>';
            }
            if (fields.indexOf('expiration') !== -1) {
                html += '<div class="field-sm">' + (item.expiration_date ? 'Exp: '+item.expiration_date : '') + '</div>';
            }
            if (fields.indexOf('location') !== -1) {
                html += '<div class="field-sm">' + (item.storage_location ? 'Loc: '+item.storage_location : '') + '</div>';
            }
            if (fields.indexOf('print_date') !== -1) {
                html += '<div class="field-sm">' + new Date().toLocaleDateString() + '</div>';
            }
            html += '</div>';
        }
    }

    html += '</div></div>';
    html += '</body></html>';
    return html;
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

$('#previewItem').on('change', updatePreview);
document.querySelectorAll('.field-toggle').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var itemId = document.getElementById('previewItem').value;
        if (itemId) updatePreview();
    });
});

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        $('.fixed.inset-0.z-50').each(function() {
            if (!$(this).hasClass('hidden') && $(this).find('.bg-gray-900').length) {
                $(this).addClass('hidden');
            }
        });
    }
});
</script>
