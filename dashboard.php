<?php

$title = 'Dashboard';

require 'includes/header.php';

$pdo = db();

$dashboardRole = strtolower($_SESSION['user']['role'] ?? '');

/*

|--------------------------------------------------------------------------

| AIA PHARMACY DASHBOARD

|--------------------------------------------------------------------------

| Role-based dashboard:

|

| ADMIN       = POS + Products + Purchases + Reports

| PHARMACIST  = POS + Products + Reports

| CASHIER     = POS

| STOREKEEPER = Products + Purchases + Reports

|--------------------------------------------------------------------------

*/

/* =========================================================

   SUMMARY CARDS

   ========================================================= */

$activeMedicines = (int)$pdo->query("

    SELECT COUNT(*)

    FROM products

    WHERE status = 'Active'

")->fetchColumn();

$totalStock = (int)$pdo->query("

    SELECT COALESCE(SUM(quantity), 0)

    FROM products

    WHERE status = 'Active'

")->fetchColumn();

$lowStock = (int)$pdo->query("

    SELECT COUNT(*)

    FROM products

    WHERE status = 'Active'

      AND quantity <= reorder_level

")->fetchColumn();

$expiredBatches = (int)$pdo->query("

    SELECT COUNT(*)

    FROM product_batches pb

    JOIN products p ON p.id = pb.product_id

    WHERE p.status = 'Active'

      AND pb.quantity > 0

      AND pb.expiry_date IS NOT NULL

      AND pb.expiry_date < CURDATE()

")->fetchColumn();

$expiringSoon = (int)$pdo->query("

    SELECT COUNT(*)

    FROM product_batches pb

    JOIN products p ON p.id = pb.product_id

    WHERE p.status = 'Active'

      AND pb.quantity > 0

      AND pb.expiry_date IS NOT NULL

      AND pb.expiry_date >= CURDATE()

      AND pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)

")->fetchColumn();

/* =========================================================

   TODAY'S SALES

   ========================================================= */

$todaySales = $pdo->query("

    SELECT

        COUNT(*) AS transactions,

        COALESCE(SUM(total), 0) AS revenue

    FROM sales

    WHERE DATE(created_at) = CURDATE()

")->fetch();

$todayTransactions = (int)($todaySales['transactions'] ?? 0);

$todayRevenue = (float)($todaySales['revenue'] ?? 0);

/* =========================================================

   TODAY'S EXPENSES

   ========================================================= */

$todayExpenses = (float)$pdo->query("

    SELECT COALESCE(SUM(amount), 0)

    FROM expenses

    WHERE DATE(created_at) = CURDATE()

")->fetchColumn();

/* =========================================================

   TODAY'S GROSS PROFIT

   ========================================================= */


$profitStmt = $pdo->query("
    SELECT COALESCE(
        SUM(
            si.total -
            (si.base_quantity * COALESCE(pb.purchase_price, p.purchase_price))
        ),
        0
    ) AS gross_profit
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    LEFT JOIN product_batches pb ON pb.id = si.batch_id
    WHERE DATE(s.created_at) = CURDATE()
");

$todayGrossProfit = (float)$profitStmt->fetchColumn();


$todayNetProfit = $todayGrossProfit - $todayExpenses;

/* =========================================================

   RECENT SALES

   ========================================================= */

$recentSales = $pdo->query("

    SELECT

        s.id,

        s.receipt_no,

        s.customer_name,

        s.total,

        s.amount_paid,

        s.change_amount,

        s.created_at,

        COALESCE(u.name, 'System') AS cashier

    FROM sales s

    LEFT JOIN users u

        ON u.id = s.created_by

    ORDER BY s.id DESC

    LIMIT 8

")->fetchAll();

/* LAST 7 DAYS SALES CHART DATA */
$salesChartRows = $pdo->query("
    SELECT DATE(created_at) AS sale_day,
           COALESCE(SUM(total), 0) AS revenue,
           COUNT(*) AS transactions
    FROM sales
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(created_at)
    ORDER BY sale_day ASC
")->fetchAll();

$salesByDay = [];
foreach ($salesChartRows as $chartRow) {
    $salesByDay[$chartRow['sale_day']] = [
        'revenue' => (float)$chartRow['revenue'],
        'transactions' => (int)$chartRow['transactions']
    ];
}
$salesChart = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $salesChart[] = [
        'label' => date('D', strtotime($day)),
        'date' => $day,
        'revenue' => $salesByDay[$day]['revenue'] ?? 0,
        'transactions' => $salesByDay[$day]['transactions'] ?? 0
    ];
}

/* =========================================================

   EXPIRY ALERTS

   ========================================================= */

$expiryAlerts = $pdo->query("

    SELECT

        pb.id,

        p.name,

        p.sku,

        pb.batch_no,

        pb.expiry_date,

        pb.quantity

    FROM product_batches pb

    JOIN products p

        ON p.id = pb.product_id

    WHERE p.status = 'Active'

      AND pb.quantity > 0

      AND pb.expiry_date IS NOT NULL

      AND pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)

    ORDER BY

        pb.expiry_date ASC,

        p.name ASC

    LIMIT 8

")->fetchAll();

/* =========================================================

   LOW STOCK MEDICINES

   ========================================================= */

$lowStockRows = $pdo->query("

    SELECT

        id,

        sku,

        name,

        quantity,

        reorder_level

    FROM products

    WHERE status = 'Active'

      AND quantity <= reorder_level

    ORDER BY quantity ASC, name ASC

    LIMIT 8

")->fetchAll();

/* =========================================================

   RECENT STOCK IN

   ========================================================= */

$recentPurchases = $pdo->query("

    SELECT

        pu.id,

        pu.invoice_no,

        pu.total,

        pu.created_at,

        COALESCE(s.name, 'Unknown Supplier') AS supplier

    FROM purchases pu

    LEFT JOIN suppliers s

        ON s.id = pu.supplier_id

    ORDER BY pu.id DESC

    LIMIT 5

")->fetchAll();

?>

<style>

.aia-dashboard {

    --green: #198754;

    --green-dark: #075b3d;

    --green-light: #eaf7ef;

    --red: #dc3545;

    --yellow: #d97706;

}

.dashboard-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 18px;

}

.dashboard-heading h1 {

    margin: 0;

}

.dashboard-heading .date {

    color: #666;

    font-size: 13px;

}

.dashboard-cards {

    display: grid;

    grid-template-columns: repeat(4, minmax(160px, 1fr));

    gap: 14px;

    margin-bottom: 18px;

}

.d-card {

    background: #fff;

    border: 1px solid #e5e5e5;

    border-radius: 12px;

    padding: 17px;

    position: relative;

    overflow: hidden;

}

.d-card::before {

    content: "";

    position: absolute;

    left: 0;

    top: 0;

    bottom: 0;

    width: 5px;

    background: var(--green);

}

.d-card.red::before {

    background: var(--red);

}

.d-card.yellow::before {

    background: #f0ad00;

}

.d-card .label {

    color: #666;

    font-size: 13px;

}

.d-card .value {

    font-size: 26px;

    font-weight: 800;

    margin-top: 5px;

    color: #153b2d;

}

.d-card .sub {

    color: #777;

    font-size: 12px;

    margin-top: 3px;

}

.d-card.red .value {

    color: var(--red);

}

.d-card.yellow .value {

    color: var(--yellow);

}

.dashboard-grid {

    display: grid;

    grid-template-columns: 2fr 1fr;

    gap: 16px;

    margin-bottom: 16px;

}

.dashboard-grid.equal {

    grid-template-columns: 1fr 1fr;

}

.d-panel {

    background: #fff;

    border: 1px solid #e5e5e5;

    border-radius: 12px;

    overflow: hidden;

}

.d-panel-header {

    padding: 14px 16px;

    border-bottom: 1px solid #e9e9e9;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

}

.d-panel-header h2 {

    font-size: 17px;

    margin: 0;

}

.d-panel-header a {

    color: var(--green);

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

}

.d-panel-body {

    padding: 12px 16px;

}

.d-table {

    width: 100%;

    border-collapse: collapse;

}

.d-table th,

.d-table td {

    padding: 9px 7px;

    border-bottom: 1px solid #eee;

    text-align: left;

    font-size: 13px;

}

.d-table th {

    color: #555;

    background: #f8faf9;

}

.d-table tr:last-child td {

    border-bottom: 0;

}

.alert-row {

    display: flex;

    justify-content: space-between;

    gap: 12px;

    align-items: center;

    padding: 10px 0;

    border-bottom: 1px solid #eee;

}

.alert-row:last-child {

    border-bottom: 0;

}

.alert-name {

    font-weight: 700;

}

.alert-meta {

    color: #777;

    font-size: 12px;

    margin-top: 2px;

}

.badge {

    display: inline-block;

    padding: 4px 7px;

    border-radius: 5px;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;

}

.badge-red {

    background: #f8d7da;

    color: #842029;

}

.badge-yellow {

    background: #fff3cd;

    color: #664d03;

}

.badge-green {

    background: #d1e7dd;

    color: #0f5132;

}

.empty-alert {

    color: #777;

    text-align: center;

    padding: 22px 10px;

}

.quick-actions {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 10px;

}

.quick-action {

    display: block;

    text-decoration: none;

    background: var(--green-light);

    border: 1px solid #cdebd8;

    color: var(--green-dark);

    padding: 13px 10px;

    border-radius: 9px;

    text-align: center;

    font-weight: 700;

    font-size: 13px;

}

.quick-action:hover {

    background: #dff3e6;

}

.profit-box {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 10px;

}

.profit-item {

    background: #f7faf8;

    border-radius: 8px;

    padding: 11px;

}

.profit-item .label {

    color: #777;

    font-size: 12px;

}

.profit-item .value {

    font-size: 18px;

    font-weight: 800;

    margin-top: 3px;

}

.amount {

    font-weight: 700;

    color: var(--green);

}

.d-card-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.d-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border-radius:10px;background:#eaf7ef;color:#087443;font-size:19px}
.d-card.red .d-icon{background:#fdebec;color:#c62828}
.d-card.yellow .d-icon{background:#fff4d9;color:#b77900}
.chart-panel{margin-bottom:16px}
.chart-area{display:flex;align-items:flex-end;gap:12px;min-height:205px;padding:18px 8px 4px;border-bottom:1px solid #e5eee8}
.chart-day{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:8px;height:185px}
.chart-bar-wrap{flex:1;width:100%;max-width:42px;display:flex;align-items:flex-end;justify-content:center;position:relative;min-height:12px}
.chart-bar{width:100%;min-height:3px;border-radius:6px 6px 2px 2px;background:linear-gradient(180deg,#20a464,#087443)}
.chart-day-label{font-size:11px;color:#68776e;font-weight:700}
.chart-day-value{font-size:10px;color:#52665a;text-align:center;white-space:nowrap}
.chart-legend{display:flex;gap:8px;align-items:center;color:#718078;font-size:12px}
.chart-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#087443}

@media (max-width: 1000px) {

    .dashboard-cards {

        grid-template-columns: repeat(2, 1fr);

    }

    .dashboard-grid,

    .dashboard-grid.equal {

        grid-template-columns: 1fr;

    }

    .quick-actions {

        grid-template-columns: repeat(2, 1fr);

    }

}

@media (max-width: 600px) {

    .dashboard-cards {

        grid-template-columns: 1fr;

    }

    .profit-box {

        grid-template-columns: 1fr;

    }

    .quick-actions {

        grid-template-columns: 1fr;

    }

}

</style>

<div class="aia-dashboard">

    <!-- =====================================================

         DASHBOARD HEADER

         ===================================================== -->

    <div class="dashboard-heading">

        <div>

            <h1>Dashboard</h1>

            <div class="date">

                AIA PHARMACY Management Overview

            </div>

        </div>

        <div class="date">

            <?= date('l, d F Y') ?>

        </div>

    </div>

    <!-- =====================================================

         SUMMARY CARDS - ROW 1

         ===================================================== -->

    <div class="dashboard-cards">

        <div class="d-card">

            <div class="d-card-top"><div class="label">Active Medicines</div><span class="d-icon">💊</span></div>

            <div class="value">

                <?= number_format($activeMedicines) ?>

            </div>

            <div class="sub">

                Products currently active

            </div>

        </div>

        <div class="d-card">

            <div class="d-card-top"><div class="label">Total Stock</div><span class="d-icon">📦</span></div>

            <div class="value">

                <?= number_format($totalStock) ?>

            </div>

            <div class="sub">

                Units in inventory

            </div>

        </div>

        <div class="d-card red">

            <div class="d-card-top"><div class="label">Low Stock</div><span class="d-icon">⚠️</span></div>

            <div class="value">

                <?= number_format($lowStock) ?>

            </div>

            <div class="sub">

                At or below reorder level

            </div>

        </div>

        <div class="d-card red">

            <div class="d-card-top"><div class="label">Expired Batches</div><span class="d-icon">⛔</span></div>

            <div class="value">

                <?= number_format($expiredBatches) ?>

            </div>

            <div class="sub">

                With remaining stock

            </div>

        </div>

    </div>

    <!-- =====================================================

         SUMMARY CARDS - ROW 2

         ===================================================== -->

    <div class="dashboard-cards">

        <div class="d-card yellow">

            <div class="d-card-top"><div class="label">Expiring Within 90 Days</div><span class="d-icon">🗓️</span></div>

            <div class="value">

                <?= number_format($expiringSoon) ?>

            </div>

            <div class="sub">

                Batch expiry alerts

            </div>

        </div>

        <div class="d-card">

            <div class="d-card-top"><div class="label">Today's Sales</div><span class="d-icon">💰</span></div>

            <div class="value">

                <?= money($todayRevenue) ?>

            </div>

            <div class="sub">

                <?= number_format($todayTransactions) ?> transaction(s)

            </div>

        </div>

        <div class="d-card">

            <div class="d-card-top"><div class="label">Today's Expenses</div><span class="d-icon">🧾</span></div>

            <div class="value">

                <?= money($todayExpenses) ?>

            </div>

            <div class="sub">

                Recorded expenses

            </div>

        </div>

        <div class="d-card">

            <div class="d-card-top"><div class="label">Today's Gross Profit</div><span class="d-icon">📈</span></div>

            <div class="value">

                <?= money($todayGrossProfit) ?>

            </div>

            <div class="sub">

                Before expenses

            </div>

        </div>

    </div>

    <!-- =====================================================

         QUICK ACTIONS

         ===================================================== -->

    <div class="d-panel" style="margin-bottom:16px">

        <div class="d-panel-header">

            <h2>Quick Actions</h2>

        </div>

        <div class="d-panel-body">

            <div class="quick-actions">

                <!-- POS:

                     ADMIN + CASHIER + PHARMACIST ONLY

                     STOREKEEPER MUST NOT SEE THIS

                -->

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'cashier', 'pharmacist'],

                        true

                    )

                ): ?>

                    <a

                        class="quick-action"

                        href="<?= BASE_URL ?>pos/index.php"

                    >

                        🛒 New Sale

                    </a>

                <?php endif; ?>

                <!-- ADD MEDICINE:

                     ADMIN + PHARMACIST + STOREKEEPER

                -->

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'pharmacist', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a

                        class="quick-action"

                        href="<?= BASE_URL ?>products/form.php"

                    >

                        💊 Add Medicine

                    </a>

                <?php endif; ?>

                <!-- STOCK IN:

                     ADMIN + STOREKEEPER

                -->

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a

                        class="quick-action"

                        href="<?= BASE_URL ?>purchases/index.php"

                    >

                        📦 Stock In

                    </a>

                <?php endif; ?>

                <!-- REPORTS:

                     ADMIN + PHARMACIST + STOREKEEPER

                -->

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'pharmacist', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a

                        class="quick-action"

                        href="<?= BASE_URL ?>reports/"

                    >

                        📊 View Reports

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- SALES CHART: LAST 7 DAYS -->
    <div class="d-panel chart-panel">
        <div class="d-panel-header">
            <h2>📊 Sales Trend (Last 7 Days)</h2>
            <span class="chart-legend"><span class="chart-dot"></span> Daily sales revenue</span>
        </div>
        <div class="d-panel-body">
            <?php
                $maxRevenue = 0;
                foreach ($salesChart as $chartDay) {
                    $maxRevenue = max($maxRevenue, (float)$chartDay['revenue']);
                }
            ?>
            <div class="chart-area" role="img" aria-label="Daily sales revenue chart for the last seven days">
                <?php foreach ($salesChart as $chartDay): ?>
                    <?php
                        $revenue = (float)$chartDay['revenue'];
                        $height = $maxRevenue > 0 ? max(3, ($revenue / $maxRevenue) * 145) : 3;
                    ?>
                    <div class="chart-day" title="<?= e(date('d M Y', strtotime($chartDay['date']))) ?> — <?= e(money($revenue)) ?>, <?= (int)$chartDay['transactions'] ?> transactions">
                        <div class="chart-day-value"><?= $revenue > 0 ? e(money($revenue)) : '₦0' ?></div>
                        <div class="chart-bar-wrap"><div class="chart-bar" style="height:<?= e((string)$height) ?>px"></div></div>
                        <div class="chart-day-label"><?= e($chartDay['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="stat-note" style="margin-top:10px">Recorded revenue for today and the previous six days.</div>
        </div>
    </div>

<!-- =====================================================

         RECENT SALES + EXPIRY ALERTS

         ===================================================== -->

    <div class="dashboard-grid">

        <!-- RECENT SALES -->

        <div class="d-panel">

            <div class="d-panel-header">

                <h2>Recent Sales</h2>

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'pharmacist'],

                        true

                    )

                ): ?>

                    <a href="<?= BASE_URL ?>reports/">

                        View Reports →

                    </a>

                <?php endif; ?>

            </div>

            <div class="d-panel-body">

                <?php if (!$recentSales): ?>

                    <div class="empty-alert">

                        No sales recorded yet.

                    </div>

                <?php else: ?>

                    <table class="d-table">

                        <tr>

                            <th>Receipt</th>

                            <th>Customer</th>

                            <th>Cashier</th>

                            <th>Total</th>

                            <th>Date</th>

                        </tr>

                        <?php foreach ($recentSales as $sale): ?>

                            <tr>

                                <td>

                                    <b>

                                        <?= e($sale['receipt_no']) ?>

                                    </b>

                                </td>

                                <td>

                                    <?= e(

                                        $sale['customer_name']

                                        ?: 'Walk-in Customer'

                                    ) ?>

                                </td>

                                <td>

                                    <?= e($sale['cashier']) ?>

                                </td>

                                <td class="amount">

                                    <?= money($sale['total']) ?>

                                </td>

                                <td>

                                    <?= e(

                                        date(

                                            'd M, H:i',

                                            strtotime($sale['created_at'])

                                        )

                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </table>

                <?php endif; ?>

            </div>

        </div>

        <!-- EXPIRY ALERTS -->

        <div class="d-panel">

            <div class="d-panel-header">

                <h2>Expiry Alerts</h2>

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'pharmacist', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a

                        href="<?= BASE_URL ?>products/?expiry_status=expiring"

                    >

                        View →

                    </a>

                <?php endif; ?>

            </div>

            <div class="d-panel-body">

                <?php if (!$expiryAlerts): ?>

                    <div class="empty-alert">

                        <div style="font-size:28px">

                            ✓

                        </div>

                        No expired or near-expiry batch found.

                    </div>

                <?php else: ?>

                    <?php foreach ($expiryAlerts as $alert): ?>

                        <?php

                        $expired =

                            $alert['expiry_date'] < date('Y-m-d');

                        ?>

                        <div class="alert-row">

                            <div>

                                <div class="alert-name">

                                    <?= e($alert['name']) ?>

                                </div>

                                <div class="alert-meta">

                                    Batch:

                                    <?= e($alert['batch_no']) ?>

                                    ·

                                    Stock:

                                    <?= number_format(

                                        (int)$alert['quantity']

                                    ) ?>

                                    ·

                                    Expiry:

                                    <?= e($alert['expiry_date']) ?>

                                </div>

                            </div>

                            <span

                                class="badge

                                <?= $expired

                                    ? 'badge-red'

                                    : 'badge-yellow' ?>"

                            >

                                <?= $expired

                                    ? 'EXPIRED'

                                    : 'EXPIRING' ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- =====================================================

         LOW STOCK + RECENT PURCHASES

         ===================================================== -->

    <div class="dashboard-grid equal">

        <!-- LOW STOCK -->

        <div class="d-panel">

            <div class="d-panel-header">

                <h2>Low Stock Medicines</h2>

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'pharmacist', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a

                        href="<?= BASE_URL ?>products/?expiry_status=low"

                    >

                        View →

                    </a>

                <?php endif; ?>

            </div>

            <div class="d-panel-body">

                <?php if (!$lowStockRows): ?>

                    <div class="empty-alert">

                        ✓ No low-stock medicine.

                    </div>

                <?php else: ?>

                    <?php foreach ($lowStockRows as $item): ?>

                        <div class="alert-row">

                            <div>

                                <div class="alert-name">

                                    <?= e($item['name']) ?>

                                </div>

                                <div class="alert-meta">

                                    SKU:

                                    <?= e($item['sku']) ?>

                                </div>

                            </div>

                            <span class="badge badge-red">

                                <?= number_format(

                                    (int)$item['quantity']

                                ) ?>

                                /

                                <?= number_format(

                                    (int)$item['reorder_level']

                                ) ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <!-- RECENT PURCHASES -->

        <div class="d-panel">

            <div class="d-panel-header">

                <h2>Recent Stock In</h2>

                <?php if (

                    in_array(

                        $dashboardRole,

                        ['admin', 'storekeeper'],

                        true

                    )

                ): ?>

                    <a href="<?= BASE_URL ?>purchases/">

                        View →

                    </a>

                <?php endif; ?>

            </div>

            <div class="d-panel-body">

                <?php if (!$recentPurchases): ?>

                    <div class="empty-alert">

                        No purchases recorded yet.

                    </div>

                <?php else: ?>

                    <?php foreach ($recentPurchases as $purchase): ?>

                        <div class="mini-stat">

                            <div>

                                <div class="title">

                                    <?= e(

                                        $purchase['invoice_no']

                                        ?: 'No Invoice'

                                    ) ?>

                                </div>

                                <div class="muted">

                                    <?= e(

                                        $purchase['supplier']

                                    ) ?>

                                    ·

                                    <?= e(

                                        date(

                                            'd M, H:i',

                                            strtotime(

                                                $purchase['created_at']

                                            )

                                        )

                                    ) ?>

                                </div>

                            </div>

                            <div class="amount">

                                <?= money(

                                    $purchase['total']

                                ) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- =====================================================

         FINANCIAL SUMMARY

         ===================================================== -->

    <div

        class="d-panel"

        style="margin-bottom:16px"

    >

        <div class="d-panel-header">

            <h2>

                Today's Financial Summary

            </h2>

        </div>

        <div class="d-panel-body">

            <div class="profit-box">

                <div class="profit-item">

                    <div class="label">

                        Sales Revenue

                    </div>

                    <div class="value">

                        <?= money($todayRevenue) ?>

                    </div>

                </div>

                <div class="profit-item">

                    <div class="label">

                        Gross Profit

                    </div>

                    <div class="value">

                        <?= money($todayGrossProfit) ?>

                    </div>

                </div>

                <div class="profit-item">

                    <div class="label">

                        Net After Expenses

                    </div>

                    <div class="value">

                        <?= money($todayNetProfit) ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>
