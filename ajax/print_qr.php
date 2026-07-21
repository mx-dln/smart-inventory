<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$copies = max(1, min(500, (int)($_GET['copies'] ?? 10)));
$cols = max(1, min(6, (int)($_GET['cols'] ?? 3)));
$stmt = $pdo->prepare("SELECT name, barcode, item_code FROM inventory_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { echo 'Item not found'; exit; }
$rows = ceil($copies / $cols);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print QR Code - <?php echo h($item['name']); ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #fff; }
.page { width: 210mm; min-height: 297mm; padding: 10mm 8mm; margin: 0 auto; }
.grid { display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, 1fr); gap: 4mm; }
.label { text-align: center; padding: 3mm 2mm; border: 1px dashed #ccc; border-radius: 2mm; page-break-inside: avoid; }
.label .item-name { font-size: 8pt; font-weight: bold; color: #333; margin-bottom: 1mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.label .item-code { font-size: 6pt; color: #999; margin-bottom: 1mm; }
.label .qr-container { display: inline-block; }
.no-print { display: block; text-align: center; margin: 10px 0; }
.no-print button { padding: 10px 30px; background: #7c3aed; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin: 0 4px; }
@media print {
    @page { size: A4; margin: 10mm 8mm; }
    body { margin: 0; padding: 0; }
    .page { width: 100%; min-height: auto; padding: 0; margin: 0; }
    .no-print { display: none !important; }
    .label { border: 1px solid #ddd; }
}
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()" style="background:#6b7280;">Close</button>
</div>
<div class="page">
    <div class="grid" id="qrGrid">
        <?php for ($i = 0; $i < $copies; $i++): ?>
        <div class="label">
            <div class="item-name"><?php echo h($item['name']); ?></div>
            <div class="item-code"><?php echo h($item['item_code']); ?></div>
            <div class="qr-container"><div class="qr-item" data-text="<?php echo h($item['barcode']); ?>"></div></div>
        </div>
        <?php endfor; ?>
    </div>
</div>
<script>
var qrs = document.querySelectorAll('.qr-item');
qrs.forEach(function(el) {
    new QRCode(el, { text: el.getAttribute('data-text'), width: 80, height: 80 });
});
</script>
</body>
</html>
