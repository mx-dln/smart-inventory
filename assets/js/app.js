// Smart Inventory System - Main JavaScript

$(document).ready(function() {
    console.log('Smart Inventory System initialized');
    
    // Auto-hide alerts after 5 seconds
    $('.alert-auto-hide').delay(5000).fadeOut(500);
});

// Form validation helper
function validateForm(formId) {
    var valid = true;
    $('#' + formId + ' [required]').each(function() {
        if (!$(this).val()) {
            $(this).addClass('border-red-500');
            valid = false;
        } else {
            $(this).removeClass('border-red-500');
        }
    });
    return valid;
}

// Number formatting
function formatNumber(num, decimals) {
    decimals = decimals || 2;
    return parseFloat(num).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// Currency formatting
function formatCurrency(amount) {
    return '₱' + formatNumber(amount);
}

// Date formatting
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Get URL parameter
function getUrlParam(name) {
    var url = new URL(window.location.href);
    return url.searchParams.get(name) || '';
}

// Table search (simple client-side filter)
function filterTable(inputId, tableId) {
    var input = document.getElementById(inputId);
    var filter = input.value.toUpperCase();
    var table = document.getElementById(tableId);
    var tr = table.getElementsByTagName('tr');
    
    for (var i = 1; i < tr.length; i++) {
        var td = tr[i].getElementsByTagName('td');
        var found = false;
        for (var j = 0; j < td.length; j++) {
            if (td[j] && td[j].innerHTML.toUpperCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        tr[i].style.display = found ? '' : 'none';
    }
}

// Export table to CSV
function exportTableToCSV(tableId, filename) {
    var csv = [];
    var rows = document.getElementById(tableId).querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) {
        var row = [];
        var cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        csv.push(row.join(','));
    }
    
    var csvStr = csv.join('\n');
    var BOM = '\uFEFF';
    var blob = new Blob([BOM + csvStr], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename || 'export.csv';
    link.click();
}

// Print current page data table with proper layout
function printPage(tableId, title) {
    var $table = $('#' + tableId);
    if (!$table.length) $table = $('.datatable').first();
    if (!$table.length) { showToast('No table to print', 'warning'); return; }

    // Collect headers, skipping the Actions column
    var colIndexes = [];
    var cols = [];
    $table.find('thead th').each(function(i) {
        var h = $(this).text().trim();
        if (/^actions?$/i.test(h)) return; // skip Actions column
        colIndexes.push(i);
        cols.push(h);
    });

    // Clean a cell's text (strip badges, spans, buttons, etc.)
    function cleanCell($td) {
        var text = $td.text().replace(/\s+/g, ' ').trim();
        return $('<div>').text(text).html();
    }

    // Build rows from the visible tbody (source of truth)
    var rowsHtml = '';
    $table.find('tbody tr').each(function() {
        var $cells = $(this).find('td');
        if ($cells.length === 0) return;
        rowsHtml += '<tr>';
        for (var k = 0; k < colIndexes.length; k++) {
            var $td = $cells.eq(colIndexes[k]);
            rowsHtml += '<td>' + ($td.length ? cleanCell($td) : '') + '</td>';
        }
        rowsHtml += '</tr>';
    });

    var win = window.open('', '_blank');
    win.document.write('<html><head><title>' + title + '</title>');
    win.document.write('<style>');
    win.document.write('* { box-sizing: border-box; margin: 0; padding: 0; }');
    win.document.write('body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #333; padding: 20px; }');
    win.document.write('.report-header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #059669; padding-bottom: 12px; }');
    win.document.write('.report-header img { height: 50px; margin-bottom: 6px; }');
    win.document.write('.report-header h1 { font-size: 18px; color: #0f766e; }');
    win.document.write('.report-header p { font-size: 11px; color: #666; }');
    win.document.write('.report-meta { display: flex; justify-content: space-between; font-size: 10px; color: #666; margin-bottom: 10px; }');
    win.document.write('table { width: 100%; border-collapse: collapse; margin-top: 8px; }');
    win.document.write('th { background: #059669; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; }');
    win.document.write('td { border: 1px solid #ddd; padding: 5px 8px; white-space: nowrap; }');
    win.document.write('tr:nth-child(even) td { background: #f9fafb; }');
    win.document.write('.report-footer { margin-top: 16px; text-align: center; font-size: 10px; color: #999; }');
    win.document.write('@media print { body { padding: 0; } }');
    win.document.write('</style>');
    win.document.write('</head><body>');
    win.document.write('<div class="report-header">');
    win.document.write('<img src="' + (typeof BASE_URL_JS !== 'undefined' ? BASE_URL_JS : '') + 'assets/images/isu_logo.png">');
    win.document.write('<h1>' + title + '</h1>');
    win.document.write('<p>ISU Cauayan Food Innovation Center</p>');
    win.document.write('</div>');
    win.document.write('<div class="report-meta"><span>Generated: ' + new Date().toLocaleString() + '</span><span>Generated by: ' + (typeof currentUserName !== 'undefined' ? currentUserName : '') + '</span></div>');
    win.document.write('<table><thead><tr>');
    for (var i = 0; i < cols.length; i++) win.document.write('<th>' + $('<div>').text(cols[i]).html() + '</th>');
    win.document.write('</tr></thead><tbody>' + rowsHtml + '</tbody></table>');
    win.document.write('<div class="report-footer">Smart Inventory System - ISU Cauayan Food Innovation Center</div>');
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    setTimeout(function() { win.print(); }, 300);
}
