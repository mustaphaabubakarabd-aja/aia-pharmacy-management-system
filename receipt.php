<?php

require_once __DIR__ . '/config/config.php';

require_login();

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    die('Invalid receipt ID.');
}

$pdo = db();

/*
|--------------------------------------------------------------------------
| SALE INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.receipt_no,
        s.customer_name,
        s.subtotal,
        s.discount,
        s.total,
        s.amount_paid,
        s.change_amount,
        s.created_at,
        u.name AS cashier_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
    LIMIT 1
");

$stmt->execute([$sale_id]);

$sale = $stmt->fetch();

if (!$sale) {
    die('Receipt not found.');
}

/*
|--------------------------------------------------------------------------
| SALE ITEMS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        si.*,
        p.name AS product_name,
        p.sku
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");
$stmt->execute([$sale_id]);
$rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groupedItems = [];

foreach ($rawItems as $item) {
    $key = (int)$item['product_id'] . '|' .
           ($item['unit_type'] ?? 'Piece') . '|' .
           (float)$item['unit_price'];

    if (!isset($groupedItems[$key])) {
        $groupedItems[$key] = $item;
        $groupedItems[$key]['unit_quantity'] = 0;
        $groupedItems[$key]['base_quantity'] = 0;
        $groupedItems[$key]['total'] = 0;
    }

    $groupedItems[$key]['unit_quantity'] +=
        (float)($item['unit_quantity'] ?? $item['quantity']);

    $groupedItems[$key]['base_quantity'] +=
        (int)round((float)($item['base_quantity'] ?? $item['quantity']));

    $groupedItems[$key]['total'] += (float)$item['total'];
}

$items = array_values($groupedItems);

/*
|--------------------------------------------------------------------------
| PHARMACY SETTINGS
|--------------------------------------------------------------------------
|
| These values are now loaded from the Settings page.
|
*/

$set = [];

$stmt = $pdo->query("
    SELECT `key`, `value`
    FROM settings
");

foreach ($stmt as $row) {
    $set[$row['key']] = $row['value'];
}

/*
|--------------------------------------------------------------------------
| Pharmacy Information
|--------------------------------------------------------------------------
*/

$pharmacy_name = !empty($set['pharmacy_name'])
    ? $set['pharmacy_name']
    : 'AIA PHARMACY';

$pharmacy_subtitle = 'Pharmacy Management System';

$pharmacy_address = !empty($set['address'])
    ? $set['address']
    : 'Sumaila, Kano State, Nigeria';

$pharmacy_phone = $set['phone'] ?? '';

$receipt_footer = !empty($set['receipt_footer'])
    ? $set['receipt_footer']
    : 'Thank you for patronizing AIA PHARMACY.';

/*
|--------------------------------------------------------------------------
| Customer & Cashier
|--------------------------------------------------------------------------
*/

$customer = !empty($sale['customer_name'])
    ? $sale['customer_name']
    : 'Walk-in Customer';

$cashier = !empty($sale['cashier_name'])
    ? $sale['cashier_name']
    : 'Cashier';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Receipt - <?= e($sale['receipt_no']) ?>
</title>

<style>

/* =========================================
   SCREEN
========================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {
    background: #eeeeee;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
}

/* =========================================
   RECEIPT
========================================= */

.receipt {
    width: 80mm;
    max-width: 80mm;
    margin: 20px auto;
    background: #ffffff;
    padding: 5mm;
}

/* =========================================
   HEADER
========================================= */

.header {
    text-align: center;
}

.pharmacy-name {
    font-size: 21px;
    font-weight: 900;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.subtitle {
    font-size: 10px;
    font-weight: bold;
}

.address {
    font-size: 9px;
    margin-top: 3px;
    line-height: 1.4;
}

/* =========================================
   DIVIDERS
========================================= */

.line {
    border-top: 1px dashed #000;
    margin: 7px 0;
}

/* =========================================
   RECEIPT INFO
========================================= */

.receipt-info {
    font-size: 9px;
    line-height: 1.65;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 5px;
}

.info-label {
    font-weight: bold;
}

/* =========================================
   ITEMS TABLE
========================================= */

.items {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 9px;
}

.items th {
    border-bottom: 1px solid #000;
    padding: 4px 1px;
    font-weight: bold;
}

.items td {
    padding: 4px 1px;
    vertical-align: top;
}

.items th:nth-child(1),
.items td:nth-child(1) {
    width: 43%;
    text-align: left;
}

.items th:nth-child(2),
.items td:nth-child(2) {
    width: 12%;
    text-align: center;
}

.items th:nth-child(3),
.items td:nth-child(3) {
    width: 22%;
    text-align: right;
}

.items th:nth-child(4),
.items td:nth-child(4) {
    width: 23%;
    text-align: right;
}

.item-name {
    word-break: break-word;
}

/* =========================================
   SUMMARY
========================================= */

.summary {
    font-size: 9px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
}

.total-row {
    border-top: 1px solid #000;
    border-bottom: 2px solid #000;
    margin: 4px 0;
    padding: 6px 0;
    font-size: 14px;
    font-weight: 900;
}

.payment-row {
    padding-top: 3px;
}

/* =========================================
   FOOTER
========================================= */

.footer {
    text-align: center;
    font-size: 9px;
    line-height: 1.5;
    margin-top: 8px;
}

.thank-you {
    font-size: 10px;
    font-weight: bold;
}

.footer-message {
    margin-top: 3px;
    white-space: pre-line;
}

/* =========================================
   BUTTONS
========================================= */

.actions {
    width: 80mm;
    max-width: 80mm;
    margin: 15px auto;
}

.action-btn {
    display: block;
    width: 100%;
    border: none;
    padding: 11px;
    margin-bottom: 7px;
    border-radius: 5px;
    color: white;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.print-btn {
    background: #087f4e;
}

.back-btn {
    background: #555555;
}

/* =========================================
   PRINT
========================================= */

@media print {

    @page {
        size: 80mm auto;
        margin: 0;
    }

    html,
    body {
        width: 80mm;
        margin: 0;
        padding: 0;
        background: #ffffff;
    }

    .receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 0;
        padding: 4mm;
    }

    .actions {
        display: none !important;
    }

    .pharmacy-name {
        font-size: 20px;
    }

    .items {
        font-size: 9px;
    }

    .summary {
        font-size: 9px;
    }
}

/* =========================================
   SMALL SCREEN
========================================= */

@media screen and (max-width: 500px) {

    body {
        background: #ffffff;
    }

    .receipt {
        margin: 0 auto;
    }

    .actions {
        margin-top: 10px;
    }
}

</style>

</head>

<body>

<!-- ======================================
     RECEIPT
====================================== -->

<div class="receipt">

    <!-- HEADER -->

    <div class="header">

        <div class="pharmacy-name">
            <?= e($pharmacy_name) ?>
        </div>

        <div class="subtitle">
            <?= e($pharmacy_subtitle) ?>
        </div>

        <div class="address">

            <?= nl2br(e($pharmacy_address)) ?>

            <?php if (!empty($pharmacy_phone)): ?>

                <br>

                Tel:
                <?= e($pharmacy_phone) ?>

            <?php endif; ?>

        </div>

    </div>

    <div class="line"></div>


    <!-- RECEIPT INFORMATION -->

    <div class="receipt-info">

        <div class="info-row">

            <span class="info-label">
                Receipt No:
            </span>

            <span>
                <?= e($sale['receipt_no']) ?>
            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Date:
            </span>

            <span>
                <?= date(
                    'd/m/Y h:i A',
                    strtotime($sale['created_at'])
                ) ?>
            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Customer:
            </span>

            <span>
                <?= e($customer) ?>
            </span>

        </div>


        <div class="info-row">

            <span class="info-label">
                Cashier:
            </span>

            <span>
                <?= e($cashier) ?>
            </span>

        </div>

    </div>


    <div class="line"></div>


    <!-- ITEMS -->

    <table class="items">

        <thead>

            <tr>

                <th>Item</th>

                <th>Qty</th>

                <th>Price</th>

                <th>Total</th>

            </tr>

        </thead>

        <tbody>

        <?php if (empty($items)): ?>

            <tr>

                <td colspan="4"
                    style="text-align:center;">

                    No items found.

                </td>

            </tr>

        <?php else: ?>

            <?php foreach ($items as $item): ?>

                <tr>

                    <td class="item-name">

                        <?= e(
                            $item['product_name']
                            ?: 'Medicine'
                        ) ?>

                    </td>

                    <td>

                        <?php
$unit = $item['unit_type'] ?? 'Piece';
$qty = (float)($item['unit_quantity'] ?? $item['quantity']);
$pieces = (int)($item['base_quantity'] ?? $item['quantity']);

$displayQty = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');

if ($unit === 'Carton' || $unit === 'Pack') {
    echo e($displayQty . ' ' . $unit . ' (' . $pieces . ' pieces)');
} else {
    echo e($displayQty . ' Piece');
}
?>
                    </td>

                    <td>

                        <?= money(
                            $item['unit_price']
                        ) ?>

                    </td>

                    <td>

                        <?= money(
                            $item['total']
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>

    </table>


    <div class="line"></div>


    <!-- SUMMARY -->

    <div class="summary">

        <div class="summary-row">

            <span>
                Subtotal:
            </span>

            <strong>
                <?= money($sale['subtotal']) ?>
            </strong>

        </div>


        <div class="summary-row">

            <span>
                Discount:
            </span>

            <strong>
                <?= money($sale['discount']) ?>
            </strong>

        </div>


        <div class="summary-row total-row">

            <span>
                TOTAL:
            </span>

            <strong>
                <?= money($sale['total']) ?>
            </strong>

        </div>


        <div class="summary-row payment-row">

            <span>
                Amount Paid:
            </span>

            <strong>
                <?= money($sale['amount_paid']) ?>
            </strong>

        </div>


        <div class="summary-row">

            <span>
                Change:
            </span>

            <strong>
                <?= money($sale['change_amount']) ?>
            </strong>

        </div>

    </div>


    <div class="line"></div>


    <!-- FOOTER -->

    <div class="footer">

        <div class="thank-you">
            Thank You for Your Patronage!
        </div>

        <div class="footer-message">
            <?= nl2br(e($receipt_footer)) ?>
        </div>

        <div>
            Please keep this receipt.
        </div>

        <div>
            <?= e($pharmacy_name) ?>
        </div>

    </div>

</div>


<!-- ======================================
     ACTION BUTTONS
====================================== -->

<div class="actions">

    <button
        class="action-btn print-btn"
        onclick="window.print();">

        🖨 PRINT RECEIPT

    </button>


    <button
        class="action-btn back-btn"
        onclick="window.location.href='pos/index.php';">

        ← Back to POS

    </button>

</div>

</body>

</html>