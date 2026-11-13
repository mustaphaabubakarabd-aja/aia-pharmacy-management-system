<?php
$title = 'Products / Medicines';
require '../includes/header.php';

$pdo = db();

$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['expiry_status'] ?? 'all';

$allowedFilters = ['all', 'good', 'expiring', 'expired', 'low'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/
$totalMedicines = (int)$pdo->query("
    SELECT COUNT(*)
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
    INNER JOIN products p ON p.id = pb.product_id
    WHERE p.status = 'Active'
      AND pb.quantity > 0
      AND pb.expiry_date IS NOT NULL
      AND pb.expiry_date < CURDATE()
")->fetchColumn();

$expiringSoon = (int)$pdo->query("
    SELECT COUNT(*)
    FROM product_batches pb
    INNER JOIN products p ON p.id = pb.product_id
    WHERE p.status = 'Active'
      AND pb.quantity > 0
      AND pb.expiry_date IS NOT NULL
      AND pb.expiry_date >= CURDATE()
      AND pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| PRODUCT AND BATCH LIST
|--------------------------------------------------------------------------
| Each row represents one batch. Total stock is the product-level quantity.
*/
$sql = "
    SELECT
        p.id AS product_id,
        p.sku,
        p.barcode,
        p.name,
        p.generic_name,
        p.quantity AS product_quantity,
        p.reorder_level,
        p.selling_price AS product_selling_price,
        p.purchase_price AS product_purchase_price,
        c.name AS category,
        pb.id AS batch_id,
        pb.batch_no,
        pb.expiry_date,
        pb.quantity AS batch_quantity,
        pb.purchase_price AS batch_purchase_price,
        pb.selling_price AS batch_selling_price,
        s.name AS supplier
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_batches pb ON pb.product_id = p.id
    LEFT JOIN suppliers s ON s.id = pb.supplier_id
    WHERE p.status = 'Active'
";

$args = [];

if ($q !== '') {
    $sql .= "
        AND (
            p.name LIKE ?
            OR p.generic_name LIKE ?
            OR p.sku LIKE ?
            OR p.barcode LIKE ?
            OR pb.batch_no LIKE ?
        )
    ";
    $like = '%' . $q . '%';
    $args = [$like, $like, $like, $like, $like];
}

if ($statusFilter === 'expired') {
    $sql .= "
        AND pb.quantity > 0
        AND pb.expiry_date IS NOT NULL
        AND pb.expiry_date < CURDATE()
    ";
} elseif ($statusFilter === 'expiring') {
    $sql .= "
        AND pb.quantity > 0
        AND pb.expiry_date IS NOT NULL
        AND pb.expiry_date >= CURDATE()
        AND pb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ";
} elseif ($statusFilter === 'good') {
    $sql .= "
        AND pb.quantity > 0
        AND (
            pb.expiry_date IS NULL
            OR pb.expiry_date > DATE_ADD(CURDATE(), INTERVAL 90 DAY)
        )
    ";
} elseif ($statusFilter === 'low') {
    $sql .= " AND p.quantity <= p.reorder_level ";
}

$sql .= "
    ORDER BY
        p.name ASC,
        CASE WHEN pb.expiry_date IS NULL THEN 1 ELSE 0 END ASC,
        pb.expiry_date ASC,
        pb.id ASC
";

$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

/*
|--------------------------------------------------------------------------
| EXPIRY STATUS
|--------------------------------------------------------------------------
*/
function batchExpiryStatus($expiry, $quantity): array
{
    if ((int)$quantity <= 0) {
        return ['class' => 'neutral', 'label' => 'Depleted'];
    }

    if ($expiry === null || $expiry === '') {
        return ['class' => 'neutral', 'label' => 'Expiry Unknown'];
    }

    try {
        $today = new DateTimeImmutable('today');
        $date = new DateTimeImmutable($expiry);
    } catch (Exception $e) {
        return ['class' => 'neutral', 'label' => 'Check Expiry'];
    }

    if ($date < $today) {
        return ['class' => 'danger', 'label' => 'EXPIRED'];
    }

    $days = (int)$today->diff($date)->format('%r%a');

    if ($days <= 90) {
        return ['class' => 'warning', 'label' => 'EXPIRING SOON'];
    }

    return ['class' => 'good', 'label' => 'GOOD'];
}
?>

<style>
.products-page { color: var(--text, #24332c); }
.products-heading {
    display:flex; align-items:center; gap:14px; flex-wrap:wrap;
    margin-bottom:18px;
}
.products-heading h1 { margin:0 auto 0 0; font-size:28px; }
.products-summary {
    display:grid; grid-template-columns:repeat(4,minmax(150px,1fr));
    gap:12px; margin-bottom:18px;
}
.summary-card {
    background:#fff; border:1px solid #e5e9e6; border-radius:12px;
    padding:15px 17px; box-shadow:0 2px 8px #00000008;
}
.summary-card .label { color:#68756e; font-size:13px; }
.summary-card .number { font-size:26px; font-weight:700; margin-top:5px; }
.summary-card.low .number, .summary-card.expired .number { color:#c62828; }
.summary-card.expiring .number { color:#d97706; }
.products-toolbar { display:flex; gap:9px; align-items:center; flex-wrap:wrap; margin-bottom:14px; }
.products-toolbar input { flex:1 1 300px; min-width:220px; }
.filter-links { display:flex; gap:6px; flex-wrap:wrap; }
.filter-links a {
    text-decoration:none; border:1px solid #d8e0db; padding:8px 10px;
    border-radius:7px; color:#34443b; background:#fff; font-size:13px;
}
.filter-links a:hover { border-color:#087443; color:#087443; }
.filter-links a.active { background:#087443; border-color:#087443; color:#fff; }
.table-wrap { overflow-x:auto; background:#fff; border:1px solid #e4e9e6; border-radius:10px; }
.products-table { min-width:1050px; margin:0; }
.products-table th { background:#f0f7f3; color:#263d31; white-space:nowrap; }
.products-table td { vertical-align:middle; }
.products-table tr:last-child td { border-bottom:0; }
.badge-status {
    display:inline-block; padding:5px 8px; border-radius:6px;
    font-size:10px; font-weight:700; white-space:nowrap;
}
.badge-status.good { background:#d1e7dd; color:#0f5132; }
.badge-status.warning { background:#fff3cd; color:#664d03; }
.badge-status.danger { background:#f8d7da; color:#842029; }
.badge-status.neutral { background:#e9ecef; color:#495057; }
.stock-good { color:#087443; font-weight:700; }
.stock-low { color:#c62828; font-weight:700; }
.expired-row { background:#fff5f5; }
.expiring-row { background:#fffdf2; }
.batch-cell small, .medicine-cell small, .sku-cell small { color:#718078; }
.empty-state { text-align:center; padding:32px !important; color:#718078; }
@media(max-width:900px) {
    .products-summary { grid-template-columns:repeat(2,minmax(0,1fr)); }
    main { padding:16px; }
}
@media(max-width:520px) {
    .products-summary { grid-template-columns:1fr; }
    .products-heading h1 { font-size:23px; }
    .products-toolbar input { min-width:100%; }
}
</style>

<div class="products-page">
    <div class="products-heading">
        <h1>Products / Medicines</h1>
        <a class="btn" href="form.php">+ Add Medicine</a>
    </div>

    <div class="products-summary">
        <div class="summary-card">
            <div class="label">Active Medicines</div>
            <div class="number"><?= number_format($totalMedicines) ?></div>
        </div>
        <div class="summary-card low">
            <div class="label">Low Stock Medicines</div>
            <div class="number"><?= number_format($lowStock) ?></div>
        </div>
        <div class="summary-card expired">
            <div class="label">Expired Batches With Stock</div>
            <div class="number"><?= number_format($expiredBatches) ?></div>
        </div>
        <div class="summary-card expiring">
            <div class="label">Expiring Within 90 Days</div>
            <div class="number"><?= number_format($expiringSoon) ?></div>
        </div>
    </div>

    <form class="products-toolbar" method="get">
        <input
            type="search"
            name="q"
            placeholder="Search medicine, SKU, barcode or batch..."
            value="<?= e($q) ?>"
            aria-label="Search medicines"
        >
        <button type="submit">Search</button>
        <div class="filter-links">
            <a href="?q=<?= urlencode($q) ?>&expiry_status=all"
               class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?q=<?= urlencode($q) ?>&expiry_status=good"
               class="<?= $statusFilter === 'good' ? 'active' : '' ?>">🟢 Good</a>
            <a href="?q=<?= urlencode($q) ?>&expiry_status=expiring"
               class="<?= $statusFilter === 'expiring' ? 'active' : '' ?>">🟡 Expiring Soon</a>
            <a href="?q=<?= urlencode($q) ?>&expiry_status=expired"
               class="<?= $statusFilter === 'expired' ? 'active' : '' ?>">🔴 Expired</a>
            <a href="?q=<?= urlencode($q) ?>&expiry_status=low"
               class="<?= $statusFilter === 'low' ? 'active' : '' ?>">⚠️ Low Stock</a>
        </div>
    </form>

    <p class="muted" style="font-size:12px;margin:0 0 8px">Each row represents a batch. Total stock is the medicine-wide quantity and is shown once per medicine.</p>
    <div class="table-wrap">
        <table class="products-table">
            <thead>
                <tr>
                    <th>SKU / Barcode</th>
                    <th>Medicine</th>
                    <th>Batch</th>
                    <th>Expiry Date</th>
                    <th>Batch Stock</th>
                    <th>Total Stock (Medicine)</th>
                    <th>Buy Price</th>
                    <th>Sell Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="10" class="empty-state">No medicine or batch matches this search.</td>
                </tr>
            <?php endif; ?>

            <?php $shownProductStock = []; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                    $batchQty = (int)($r['batch_quantity'] ?? 0);
                    $expiryStatus = $r['batch_id']
                        ? batchExpiryStatus($r['expiry_date'], $batchQty)
                        : ['class' => 'neutral', 'label' => 'No Batch Record'];

                    $rowClass = '';
                    if ($expiryStatus['class'] === 'danger') {
                        $rowClass = 'expired-row';
                    } elseif ($expiryStatus['class'] === 'warning') {
                        $rowClass = 'expiring-row';
                    }

                    $productQty = (int)$r['product_quantity'];
                    $showProductStock = !isset($shownProductStock[$r['product_id']]);
                    $shownProductStock[$r['product_id']] = true;
                    $totalStockClass = ($productQty <= (int)$r['reorder_level'])
                        ? 'stock-low'
                        : 'stock-good';

                    $buyPrice = ($r['batch_id'] !== null && $r['batch_purchase_price'] !== null)
                        ? $r['batch_purchase_price']
                        : $r['product_purchase_price'];
                    $sellPrice = ($r['batch_id'] !== null && $r['batch_selling_price'] !== null)
                        ? $r['batch_selling_price']
                        : $r['product_selling_price'];
                ?>
                <tr class="<?= e($rowClass) ?>">
                    <td class="sku-cell">
                        <strong><?= e($r['sku']) ?></strong><br>
                        <small>
                            Barcode: <?= !empty($r['barcode']) ? e($r['barcode']) : 'Not Set' ?>
                        </small>
                    </td>
                    <td class="medicine-cell">
                        <strong><?= e($r['name']) ?></strong>
                        <?php if (!empty($r['generic_name'])): ?>
                            <br><small><?= e($r['generic_name']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($r['category'])): ?>
                            <br><small>Category: <?= e($r['category']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="batch-cell">
                        <?php if ($r['batch_id'] !== null): ?>
                            <strong><?= e($r['batch_no']) ?></strong>
                            <?php if (!empty($r['supplier'])): ?>
                                <br><small>Supplier: <?= e($r['supplier']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-status neutral">No Batch Record</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= !empty($r['expiry_date']) ? e($r['expiry_date']) : '<span class="muted">Unknown</span>' ?>
                    </td>
                    <td><strong><?= number_format($batchQty) ?></strong></td>
                    <td class="<?= e($totalStockClass) ?>">
                        <?php if ($showProductStock): ?>
                            <?= number_format($productQty) ?>
                            <?php if ($productQty <= (int)$r['reorder_level']): ?>
                                <br><small>Reorder level: <?= number_format((int)$r['reorder_level']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted" title="Total stock is shown on the first row for this medicine">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= money($buyPrice) ?></td>
                    <td><?= money($sellPrice) ?></td>
                    <td>
                        <span class="badge-status <?= e($expiryStatus['class']) ?>">
                            <?= e($expiryStatus['label']) ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn" href="form.php?id=<?= (int)$r['product_id'] ?>&batch_id=<?= (int)$r['batch_id'] ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
