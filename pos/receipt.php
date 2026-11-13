
<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$pdo = db();
$sale_id = (int)($_GET['id'] ?? 0);

if ($sale_id <= 0) {
    die('Invalid receipt ID.');
}

// Get saved sale details. This page does not modify stock.
$stmt = $pdo->prepare("
    SELECT s.*, u.name AS cashier_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('Receipt not found.');
}

// Get sale items, including the unit selected at POS.
$stmt = $pdo->prepare("
    SELECT si.*, p.name AS product_name, p.sku
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");
$stmt->execute([$sale_id]);
$rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groupedItems = [];

foreach ($rawItems as $item) {
    // Group FEFO split rows into one receipt line when product, selling unit,
    // and unit price match. Keep unit quantities separate from base pieces.
    $unit = receiptUnitLabel($item['unit_type'] ?? 'Piece');
    $unitPrice = (float)($item['unit_price'] ?? 0);
    $key = (int)$item['product_id'] . '|' . $unit . '|' . number_format($unitPrice, 4, '.', '');

    if (!isset($groupedItems[$key])) {
        $groupedItems[$key] = $item;
        $groupedItems[$key]['unit_type'] = $unit;
        $groupedItems[$key]['unit_price'] = $unitPrice;
        $groupedItems[$key]['unit_quantity'] = 0.0;
        $groupedItems[$key]['base_quantity'] = 0.0;
        $groupedItems[$key]['total'] = 0.0;
    }

    $unitQty = array_key_exists('unit_quantity', $item) && $item['unit_quantity'] !== null
        ? (float)$item['unit_quantity']
        : (float)($item['quantity'] ?? 0);
    $baseQty = array_key_exists('base_quantity', $item) && $item['base_quantity'] !== null
        ? (float)$item['base_quantity']
        : (float)($item['quantity'] ?? 0);

    $groupedItems[$key]['unit_quantity'] += $unitQty;
    $groupedItems[$key]['base_quantity'] += $baseQty;
    $groupedItems[$key]['total'] += (float)($item['total'] ?? 0);
}

$items = array_values($groupedItems);

// Load pharmacy settings.
$settings = [];
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
foreach ($stmt as $row) {
    $settings[$row['key']] = $row['value'];
}

$pharmacyName = $settings['pharmacy_name'] ?? 'AIA PHARMACY';
$address = $settings['address'] ?? 'Sumaila, Kano State, Nigeria';
$phone = $settings['phone'] ?? '';
$footer = $settings['receipt_footer']
    ?? 'Thank you for patronizing AIA PHARMACY.';

$customer = $sale['customer_name'] ?: 'Walk-in Customer';
$cashier = $sale['cashier_name'] ?? 'Cashier';

function receiptUnitLabel($unit) {
    $allowed = ['Piece', 'Pack', 'Carton'];
    return in_array($unit, $allowed, true) ? $unit : 'Piece';
}

function receiptQuantity($value) {
    $value = round((float)$value, 3);
    return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receipt - <?= e($sale['receipt_no']) ?></title>

<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    padding: 20px;
    background: #eee;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
}
.receipt {
    width: 80mm;
    max-width: 100%;
    margin: 0 auto;
    padding: 4mm;
    background: #fff;
    font-size: 10px;
}
.header { text-align: center; }
.pharmacy-name {
    font-size: 20px;
    font-weight: 900;
    overflow-wrap: anywhere;
}
.subtitle { font-size: 10px; font-weight: bold; }
.address { font-size: 9px; line-height: 1.5; margin-top: 4px; }
.line { border-top: 1px dashed #000; margin: 8px 0; }
.info { font-size: 9px; line-height: 1.7; }
.info-row, .summary-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
.info-row span:last-child {
    text-align: right;
    overflow-wrap: anywhere;
}
.items {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 9px;
}
.items th {
    border-bottom: 1px solid #000;
    padding: 5px 1px;
    text-align: right;
}
.items td {
    padding: 5px 1px;
    text-align: right;
    vertical-align: top;
    overflow-wrap: anywhere;
}
.items th:first-child, .items td:first-child {
    width: 39%;
    text-align: left;
}
.items th:nth-child(2), .items td:nth-child(2) {
    width: 16%;
    text-align: center;
}
.items th:nth-child(3), .items td:nth-child(3) { width: 22%; }
.items th:nth-child(4), .items td:nth-child(4) { width: 23%; }
.item-unit { display: block; font-size: 8px; }
.summary { font-size: 10px; }
.summary-row { padding: 4px 0; }
.total-row {
    font-size: 14px;
    font-weight: bold;
    border-top: 1px solid #000;
    border-bottom: 2px solid #000;
    padding: 7px 0;
}
.footer {
    text-align: center;
    font-size: 9px;
    line-height: 1.5;
}
.thank-you { font-size: 10px; font-weight: bold; }
.actions {
    width: 80mm;
    max-width: 100%;
    margin: 12px auto;
}
.btn {
    width: 100%;
    border: 0;
    padding: 12px;
    margin-bottom: 7px;
    color: white;
    font-weight: bold;
    border-radius: 5px;
    cursor: pointer;
}
.print-btn { background: #087443; }
.back-btn { background: #555; }
@media print {
    @page { size: 80mm auto; margin: 0; }
    body { padding: 0; margin: 0; background: #fff; }
    .receipt { width: 80mm; max-width: 80mm; margin: 0; }
    .actions { display: none !important; }
}
</style>
</head>

<body>
<div class="receipt">
    <div class="header">
        <div class="pharmacy-name"><?= e($pharmacyName) ?></div>
        <div class="subtitle">Pharmacy Management System</div>
        <div class="address">
            <?= nl2br(e($address)) ?>
            <?php if ($phone !== ''): ?>
                <br>Tel: <?= e($phone) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="line"></div>

    <div class="info">
        <div class="info-row">
            <strong>Receipt No:</strong>
            <span><?= e($sale['receipt_no']) ?></span>
        </div>
        <div class="info-row">
            <strong>Date:</strong>
            <span><?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <strong>Customer:</strong>
            <span><?= e($customer) ?></span>
        </div>
        <div class="info-row">
            <strong>Cashier:</strong>
            <span><?= e($cashier) ?></span>
        </div>
        <?php if (!empty($sale['sale_type'])): ?>
        <div class="info-row">
            <strong>Sale Type:</strong>
            <span><?= e($sale['sale_type']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="line"></div>

    <table class="items">
        <thead>
        <tr>
            <th>Medicine</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr><td colspan="4" style="text-align:center">No items found.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php
                $unit = receiptUnitLabel($item['unit_type'] ?? 'Piece');
                $qty = isset($item['unit_quantity'])
                    ? (float)$item['unit_quantity']
                    : (float)$item['quantity'];
                ?>
                <tr>
                    <td>
                        <?= e($item['product_name'] ?? 'Medicine') ?>
                        <span class="item-unit"><?= e($unit) ?></span>
                    </td>
                    <td>
    <?php
                $displayQty = receiptQuantity($qty);
                $baseQty = (float)($item['base_quantity'] ?? $item['quantity'] ?? 0);
                $pieces = receiptQuantity($baseQty);

                if ($unit === 'Carton' || $unit === 'Pack') {
                    echo e($displayQty . ' ' . $unit . ' (' . $pieces . ' pieces)');
                } else {
                    echo e($pieces . ' Piece');
                }
    ?>
</td>
                    <td><?= money($item['unit_price']) ?></td>
                    <td><?= money($item['total']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="line"></div>

    <div class="summary">
        <div class="summary-row">
            <span>Subtotal:</span>
            <strong><?= money($sale['subtotal']) ?></strong>
        </div>
        <div class="summary-row">
            <span>Discount:</span>
            <strong><?= money($sale['discount']) ?></strong>
        </div>
        <div class="summary-row total-row">
            <span>TOTAL:</span>
            <strong><?= money($sale['total']) ?></strong>
        </div>
        <div class="summary-row">
            <span>Amount Paid:</span>
            <strong><?= money($sale['amount_paid']) ?></strong>
        </div>
        <?php if (isset($sale['amount_due']) && (float)$sale['amount_due'] > 0): ?>
        <div class="summary-row">
            <span>Balance / Bashi:</span>
            <strong><?= money($sale['amount_due']) ?></strong>
        </div>
        <?php endif; ?>
        <div class="summary-row">
            <span>Change:</span>
            <strong><?= money($sale['change_amount']) ?></strong>
        </div>
        <?php if (!empty($sale['payment_status'])): ?>
        <div class="summary-row">
            <span>Payment Status:</span>
            <strong><?= e($sale['payment_status']) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <div class="line"></div>

    <div class="footer">
        <div class="thank-you">Thank You for Your Patronage!</div>
        <div><?= nl2br(e($footer)) ?></div>
        <div>Please keep this receipt.</div>
        <div><?= e($pharmacyName) ?></div>
    </div>
</div>

<div class="actions">
    <button class="btn print-btn" onclick="window.print()">PRINT RECEIPT</button>
    <button class="btn back-btn" onclick="window.location.href='index.php'">
        Back to POS
    </button>
</div>
</body>
</html>
