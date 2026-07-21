// Reports module JavaScript

$(document).ready(function() {
    // Report type tabs
    $('.report-tab').on('click', function() {
        var type = $(this).data('type');
        window.location.href = 'index.php?page=reports&type=' + type;
    });
});

// Export table to PDF
function exportPDF(tableId, title) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var html = '<html><head><title>' + title + '</title>';
    html += '<style>';
    html += 'body { font-family: Arial, sans-serif; font-size: 12px; }';
    html += 'h2 { text-align: center; margin-bottom: 20px; }';
    html += 'table { border-collapse: collapse; width: 100%; margin-top: 10px; }';
    html += 'th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }';
    html += 'th { background-color: #059669; color: white; font-size: 11px; }';
    html += 'tr:nth-child(even) { background-color: #f9fafb; }';
    html += '.footer { text-align: center; margin-top: 20px; font-size: 10px; color: #666; }';
    html += '</style></head><body>';
    html += '<h2>' + title + '</h2>';
    html += '<p style="text-align:center;color:#666;">ISU Cauayan Food Innovation Center | Generated: ' + new Date().toLocaleDateString('en-PH') + '</p>';
    html += table.outerHTML;
    html += '<div class="footer">Smart Inventory System - ISU Cauayan Food Innovation Center</div>';
    html += '</body></html>';
    
    var win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.print();
}

// Export table to Excel
function exportExcel(tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    html += '<head><meta charset="UTF-8"><title>' + filename + '</title></head><body>';
    html += '<table>' + table.innerHTML + '</table>';
    html += '</body></html>';
    
    var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.xls';
    link.click();
}

// Apply report filters
function applyReportFilters() {
    var from = document.getElementById('filterFrom') ? document.getElementById('filterFrom').value : '';
    var to = document.getElementById('filterTo') ? document.getElementById('filterTo').value : '';
    var cat = document.getElementById('filterCategory') ? document.getElementById('filterCategory').value : '';
    var sup = document.getElementById('filterSupplier') ? document.getElementById('filterSupplier').value : '';
    var type = getUrlParam('type') || 'inventory';
    
    var url = 'index.php?page=reports&type=' + type;
    if (from) url += '&from=' + from;
    if (to) url += '&to=' + to;
    if (cat) url += '&category=' + cat;
    if (sup) url += '&supplier=' + sup;
    window.location.href = url;
}
