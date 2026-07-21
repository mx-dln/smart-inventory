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

// Print table
function printTable(tableId, title) {
    var table = document.getElementById(tableId).outerHTML;
    var win = window.open('', '_blank');
    win.document.write('<html><head><title>' + title + '</title>');
    win.document.write('<style>body { font-family: Arial, sans-serif; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>');
    win.document.write('</head><body><h2>' + title + '</h2>' + table + '</body></html>');
    win.document.close();
    win.print();
}
