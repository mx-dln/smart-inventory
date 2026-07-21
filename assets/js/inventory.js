// Inventory module JavaScript

$(document).ready(function() {
    // Barcode scanner support
    initBarcodeScanner();
});

// Initialize barcode scanner (listens for keyboard input like a USB scanner)
function initBarcodeScanner() {
    var barcode = '';
    var lastKeyTime = Date.now();
    
    $(document).on('keydown', function(e) {
        // Only scan if not focused on an input
        if ($(e.target).is('input, textarea, select')) return;
        
        var currentTime = Date.now();
        if (currentTime - lastKeyTime > 100) {
            barcode = '';
        }
        lastKeyTime = currentTime;
        
        if (e.key === 'Enter') {
            if (barcode.length > 3) {
                e.preventDefault();
                searchByBarcode(barcode);
                barcode = '';
            }
        } else if (e.key.length === 1) {
            barcode += e.key;
        }
    });
}

// Search item by barcode
function searchByBarcode(barcode) {
    $.ajax({
        url: 'ajax/global_search.php',
        data: { q: barcode, exact: 1 },
        dataType: 'json',
        success: function(data) {
            if (data.length === 1) {
                window.location.href = 'index.php?page=inventory&view=' + data[0].id;
            } else if (data.length > 1) {
                showToast('Multiple items found for barcode: ' + barcode, 'info');
            } else {
                showToast('No item found with barcode: ' + barcode, 'error');
            }
        }
    });
}

// Camera barcode scanner using html5-qrcode
function startCameraScanner() {
    var scannerContainer = document.getElementById('scannerContainer');
    if (!scannerContainer) {
        showToast('Scanner container not found', 'error');
        return;
    }
    
    scannerContainer.classList.remove('hidden');
    scannerContainer.innerHTML = '<div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>';
    
    var html5QrcodeScanner = new Html5QrcodeScanner('reader', {
        fps: 10,
        qrbox: { width: 250, height: 150 },
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.QR_CODE
        ]
    });
    
    html5QrcodeScanner.render(function(decodedText) {
        html5QrcodeScanner.clear();
        scannerContainer.classList.add('hidden');
        searchByBarcode(decodedText);
    }, function(error) {
        // scan in progress
    });
}

// Generate barcode image URL
function getBarcodeUrl(code) {
    return 'https://barcode.tec-it.com/barcode.ashx?data=' + encodeURIComponent(code) + '&code=Code128&dpi=96&imagetype=png';
}

// Generate QR code image URL
function getQRUrl(data) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(data);
}
