<?php
$title = 'Reports';
require '../includes/header.php';
$pdo = db();
$report = $_GET['report'] ?? 'sales';
$allowed = ['sales','purchases','expenses','stock','expiry'];
if (!in_array($report, $allowed, true)) $report = 'sales';
$today = date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !checkdate((int)substr($from, 5, 2), (int)substr($from, 8, 2), (int)substr($from, 0, 4))) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !checkdate((int)substr($to, 5, 2), (int)substr($to, 8, 2), (int)substr($to, 0, 4))) $to = $today;
if ($from > $to) [$from,$to] = [$to,$from];
$salesRows=[]; $purchaseRows=[]; $expenseRows=[]; $stockRows=[]; $expiryRows=[];
$salesRevenue=0; $salesCost=0; $salesProfit=0; $salesTransactions=0;
$purchaseTotal=0; $purchaseTransactions=0; $expenseTotal=0; $expenseTransactions=0;
$totalStock=0; $stockValue=0; $expiredValue=0; $expiringValue=0;
if ($report === 'sales') {
    $st=$pdo->prepare("
        SELECT s.id,s.receipt_no,COALESCE(s.customer_name,'Walk-in Customer') customer_name,
               s.discount,s.total,s.amount_paid,s.created_at,COALESCE(u.name,'System') cashier,
               COALESCE(SUM(si.quantity),0) item_qty,
               COALESCE(SUM(si.quantity*COALESCE(pb.purchase_price,p.purchase_price)),0) cost_total,
               (s.total - COALESCE(SUM(si.quantity * COALESCE(pb.purchase_price, p.purchase_price)), 0)) AS gross_profit
        FROM sales s
        LEFT JOIN users u ON u.id=s.created_by
        LEFT JOIN sale_items si ON si.sale_id=s.id
        LEFT JOIN products p ON p.id=si.product_id
        LEFT JOIN product_batches pb ON pb.id=si.batch_id
        WHERE s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY s.id,s.receipt_no,s.customer_name,s.discount,s.total,s.amount_paid,s.created_at,u.name
        ORDER BY s.id DESC
    ");
    $st->execute([$from,$to]); $salesRows=$st->fetchAll();
    foreach($salesRows as $r){$salesTransactions++;$salesRevenue+=(float)$r['total'];$salesCost+=(float)$r['cost_total'];$salesProfit+=(float)$r['gross_profit'];}
}
if ($report === 'purchases') {
    $st=$pdo->prepare("
        SELECT
            pu.id,
            pu.invoice_no,
            COALESCE(
                NULLIF(TRIM(s.name), ''),
                (
                    SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(bs.name), '') SEPARATOR ', ')
                    FROM purchase_items pi2
                    LEFT JOIN product_batches pb2 ON pb2.id = pi2.batch_id
                    LEFT JOIN suppliers bs ON bs.id = pb2.supplier_id
                    WHERE pi2.purchase_id = pu.id
                ),
                'Supplier Not Recorded'
            ) AS supplier,
            pu.total,
            pu.created_at,
            COALESCE(u.name, 'System') AS received_by,
            (
                SELECT COUNT(*)
                FROM purchase_items pi3
                WHERE pi3.purchase_id = pu.id
            ) AS item_lines,
            (
                SELECT COALESCE(SUM(COALESCE(pi4.base_quantity, pi4.quantity)), 0)
                FROM purchase_items pi4
                WHERE pi4.purchase_id = pu.id
            ) AS item_qty
        FROM purchases pu
        LEFT JOIN suppliers s ON s.id = pu.supplier_id
        LEFT JOIN users u ON u.id = pu.created_by
        WHERE pu.created_at >= ? AND pu.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY pu.id DESC
    ");
    $st->execute([$from,$to]); $purchaseRows=$st->fetchAll();
    foreach($purchaseRows as $r){$purchaseTransactions++;$purchaseTotal+=(float)$r['total'];}
}
if ($report === 'expenses') {
    $st=$pdo->prepare("
        SELECT e.id,e.title,e.amount,e.notes,e.created_at,COALESCE(u.name,'System') recorded_by
        FROM expenses e
        LEFT JOIN users u ON u.id=e.created_by
        WHERE e.created_at >= ? AND e.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY e.id DESC
    ");
    $st->execute([$from,$to]); $expenseRows=$st->fetchAll();
    foreach($expenseRows as $r){$expenseTransactions++;$expenseTotal+=(float)$r['amount'];}
}
if ($report === 'stock') {
    $stockRows = $pdo->query("
        SELECT
            p.id,
            p.barcode,
            p.sku,
            p.name,
            p.generic_name,
            p.quantity AS product_quantity,
            p.reorder_level,
            p.purchase_price,
            p.selling_price,
            c.name AS category,
            COALESCE((
                SELECT SUM(pb.quantity)
                FROM product_batches pb
                WHERE pb.product_id = p.id
            ), 0) AS batch_stock,
            COALESCE((
                SELECT COUNT(*)
                FROM product_batches pb
                WHERE pb.product_id = p.id
            ), 0) AS batch_count,
            COALESCE((
                SELECT SUM(pb.quantity * pb.purchase_price)
                FROM product_batches pb
                WHERE pb.product_id = p.id
            ), 0) AS batch_cost_value
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'Active'
        ORDER BY p.name
    ")->fetchAll();
    foreach ($stockRows as &$r) {
        // Batch quantities are the more detailed stock record when batches exist.
        // Fall back to products.quantity only for medicines that have no batch records.
        $hasBatches = (int)$r['batch_count'] > 0;
        $r['quantity'] = $hasBatches ? (int)$r['batch_stock'] : (int)$r['product_quantity'];
        $r['stock_value'] = $hasBatches
            ? (float)$r['batch_cost_value']
            : ((int)$r['product_quantity'] * (float)$r['purchase_price']);
        $totalStock += (int)$r['quantity'];
        $stockValue += (float)$r['stock_value'];
    }
    unset($r);
}
if ($report === 'expiry') {
    $expiryRows=$pdo->query("
        SELECT pb.id,p.name,p.sku,pb.batch_no,pb.expiry_date,pb.quantity,
               pb.purchase_price,COALESCE(NULLIF(TRIM(s.name),''),'Supplier Not Recorded') AS supplier
        FROM product_batches pb
        JOIN products p ON p.id=pb.product_id
        LEFT JOIN suppliers s ON s.id=pb.supplier_id
        WHERE p.status='Active' AND pb.quantity>0 AND pb.expiry_date IS NOT NULL
        ORDER BY pb.expiry_date,p.name
    ")->fetchAll();
    $todayObj=new DateTimeImmutable('today');
    foreach($expiryRows as $r){
        $days=(int)$todayObj->diff(new DateTimeImmutable($r['expiry_date']))->format('%r%a');
        $v=(int)$r['quantity']*(float)$r['purchase_price'];
        if($days<0)$expiredValue+=$v; elseif($days<=90)$expiringValue+=$v;
    }
}
$periodExpenses=0;
if($report==='sales'){
    $st=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)");
    $st->execute([$from,$to]); $periodExpenses=(float)$st->fetchColumn();
}
$netProfit = (float)$salesProfit - (float)$periodExpenses;
$titleMap=['sales'=>'Sales Report','purchases'=>'Purchase Report','expenses'=>'Expenses Report','stock'=>'Current Stock Report','expiry'=>'Expiry Report'];
$reportTitle=$titleMap[$report];
?>
<style>
.rpt{--g:#198754;--gl:#eaf7ef;--r:#dc3545;--y:#d97706}
.rpt-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px}
.rpt-head h1{margin:0}.rpt-sub{font-size:13px;color:#777}
.rpt-tabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:14px}
.rpt-tabs a{padding:8px 12px;border:1px solid #d9e3dd;border-radius:7px;background:#fff;color:#345;text-decoration:none;font-size:13px;font-weight:600}
.rpt-tabs a.active{background:var(--g);border-color:var(--g);color:#fff}
.rpt-filter{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px;margin-bottom:16px}
.rpt-filter form{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
.rpt-filter label{display:flex;flex-direction:column;gap:5px;font-size:12px;color:#666}
.rpt-filter input{min-width:165px}.presets{display:flex;gap:6px;flex-wrap:wrap}
.presets a{padding:8px 10px;border:1px solid #ddd;border-radius:6px;background:#f8f9fa;text-decoration:none;color:#444;font-size:12px}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.card{background:#fff;border:1px solid #e5e5e5;border-left:5px solid var(--g);border-radius:10px;padding:14px}
.card.red{border-left-color:var(--r)}.card.yellow{border-left-color:#f0ad00}
.card .lab{font-size:12px;color:#666}.card .val{font-size:21px;font-weight:800;margin-top:4px}
.panel{background:#fff;border:1px solid #e5e5e5;border-radius:10px;overflow:auto}
.panel-head{padding:13px 15px;border-bottom:1px solid #eee;display:flex;justify-content:space-between}
.panel-head h2{margin:0;font-size:17px}.table{width:100%;border-collapse:collapse;min-width:800px}
.table th,.table td{padding:9px 8px;border-bottom:1px solid #eee;font-size:12px;text-align:left}
.table th{background:#f5faf7;color:#40564b}.money,.profit{color:var(--g);font-weight:700}.danger{color:var(--r);font-weight:700}.warning{color:var(--y);font-weight:700}
.badge{padding:4px 7px;border-radius:5px;font-size:10px;font-weight:800}.good{background:#d1e7dd;color:#0f5132}.warn{background:#fff3cd;color:#664d03}.bad{background:#f8d7da;color:#842029}
.empty{text-align:center;padding:35px;color:#777}.print-only{display:none}
@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.cards{grid-template-columns:1fr}.rpt-head{align-items:flex-start;flex-direction:column}}
/* Print layout: isolate the report so the site header, sidebar and footer
   can never appear in the printed document, even if their class names change. */
@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    html, body {
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #111 !important;
        font-size: 10pt !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body * {
        visibility: hidden !important;
    }

    .rpt, .rpt * {
        visibility: visible !important;
    }

    .rpt {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .rpt-head,
    .rpt-tabs,
    .rpt-filter,
    .no-print,
    .sidebar,
    nav,
    footer,
    header {
        display: none !important;
    }

    .print-only {
        display: block !important;
        margin: 0 0 12px !important;
        padding: 0 0 8px !important;
        border-bottom: 2px solid #07583f !important;
        color: #111 !important;
    }

    .print-only h2 {
        margin: 0 0 3px !important;
        color: #07583f !important;
        font-size: 19pt !important;
        letter-spacing: .4px;
    }

    .print-only .print-subtitle {
        margin: 2px 0;
        font-size: 9pt !important;
    }

    .panel {
        width: 100% !important;
        overflow: visible !important;
        border: 1px solid #d7ded9 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        page-break-inside: auto;
    }

    .panel-head {
        padding: 8px 10px !important;
        background: #f2f7f4 !important;
        border-bottom: 1px solid #d7ded9 !important;
    }

    .panel-head h2 {
        font-size: 12pt !important;
    }

    .table {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
    }

    .table th,
    .table td {
        padding: 5px 6px !important;
        font-size: 8pt !important;
        line-height: 1.25 !important;
        overflow-wrap: anywhere;
        color: #111 !important;
        border: 1px solid #dfe5e1 !important;
    }

    .table th {
        background: #eaf3ee !important;
        font-weight: 700 !important;
    }

    .cards {
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 8px !important;
        margin: 0 0 12px !important;
    }

    .card {
        min-width: 0 !important;
        padding: 9px !important;
        border: 1px solid #cfdad3 !important;
        border-left: 3px solid #198754 !important;
        border-radius: 4px !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }

    .card .lab {
        font-size: 8pt !important;
        color: #444 !important;
    }

    .card .val {
        margin-top: 3px !important;
        font-size: 14pt !important;
        overflow-wrap: anywhere;
    }

    .money, .profit, .danger, .warning {
        color: #111 !important;
    }

    .badge {
        border: 1px solid #aaa !important;
        background: #fff !important;
        color: #111 !important;
    }

    .empty {
        padding: 18px !important;
    }

    a {
        color: #111 !important;
        text-decoration: none !important;
    }

    tr {
        page-break-inside: avoid;
    }
}
</style>
<div class="rpt">
<div class="rpt-head">
  <div><h1>Reports</h1><div class="rpt-sub">AIA PHARMACY — Sales, Purchases, Expenses, Stock & Expiry</div></div>
  <button class="no-print" onclick="window.print()">🖨️ Print Report</button>
</div>
<div class="rpt-tabs no-print">
<?php foreach(['sales'=>'💰 Sales','purchases'=>'📦 Purchases','expenses'=>'💸 Expenses','stock'=>'📋 Current Stock','expiry'=>'⏰ Expiry'] as $k=>$label): ?>
<a class="<?= $report===$k?'active':'' ?>" href="?report=<?=e($k)?>&from=<?=e($from)?>&to=<?=e($to)?>"><?=e($label)?></a>
<?php endforeach; ?>
</div>
<div class="rpt-filter no-print">
<form method="get">
<input type="hidden" name="report" value="<?=e($report)?>">
<label>From<input type="date" name="from" value="<?=e($from)?>"></label>
<label>To<input type="date" name="to" value="<?=e($to)?>"></label>
<button>Apply Filter</button>
<div class="presets">
<?php $week=date('Y-m-d',strtotime('-6 days')); $month=date('Y-m-01'); ?>
<a href="?report=<?=e($report)?>&from=<?=$today?>&to=<?=$today?>">Today</a>
<a href="?report=<?=e($report)?>&from=<?=$week?>&to=<?=$today?>">Last 7 Days</a>
<a href="?report=<?=e($report)?>&from=<?=$month?>&to=<?=$today?>">This Month</a>
</div>
</form>
</div>
<div class="print-only">
<h2>AIA PHARMACY</h2>
<div class="print-subtitle">Pharmacy Management System</div>
<div class="print-subtitle"><strong><?=e($reportTitle)?></strong></div>
<?php if(in_array($report,['sales','purchases','expenses'],true)): ?>
<div class="print-subtitle">Period: <?=e(date('d M Y', strtotime($from)))?> to <?=e(date('d M Y', strtotime($to)))?></div>
<?php else: ?>
<div class="print-subtitle">Generated: <?=e(date('d M Y, H:i'))?></div>
<?php endif; ?>
</div>
<?php if($report==='sales'): ?>
<div class="cards">
<div class="card"><div class="lab">Transactions</div><div class="val"><?=number_format($salesTransactions)?></div></div>
<div class="card"><div class="lab">Sales Revenue</div><div class="val"><?=money($salesRevenue)?></div></div>
<div class="card"><div class="lab">Gross Profit</div><div class="val"><?=money($salesProfit)?></div></div>
<div class="card <?= $netProfit < 0 ? 'red' : '' ?>">
<div class="lab">Net Profit</div><div class="val"><?= money($netProfit) ?></div></div>
</div>
<div class="panel"><div class="panel-head"><h2>Sales Transactions</h2><span><?=count($salesRows)?> records</span></div>
<?php if(!$salesRows): ?><div class="empty">No sales found for this period.</div><?php else: ?>
<table class="table"><tr><th>Receipt</th><th>Customer</th><th>Cashier</th><th>Items</th><th>Total</th><th>Cost</th><th>Profit</th><th>Paid</th><th>Date</th><th class="no-print">Action</th></tr>
<?php foreach($salesRows as $r): ?><tr>
<td><b><?=e($r['receipt_no'])?></b></td><td><?=e($r['customer_name'])?></td><td><?=e($r['cashier'])?></td><td><?=number_format($r['item_qty'])?></td>
<td class="money"><?=money($r['total'])?></td><td><?=money($r['cost_total'])?></td><td class="profit"><?=money($r['gross_profit'])?></td><td><?=money($r['amount_paid'])?></td><td><?=date('d M Y H:i',strtotime($r['created_at']))?></td>
<td class="no-print"><a class="btn" target="_blank" href="<?=BASE_URL?>receipt.php?id=<?=$r['id']?>">Receipt</a></td>
</tr><?php endforeach; ?>
<tr><th colspan="4">TOTAL</th><th><?=money($salesRevenue)?></th><th><?=money($salesCost)?></th><th><?=money($salesProfit)?></th><th colspan="3"></th></tr>
</table><?php endif; ?></div>
<?php elseif($report==='purchases'): ?>
<div class="cards"><div class="card"><div class="lab">Purchase Transactions</div><div class="val"><?=number_format($purchaseTransactions)?></div></div><div class="card"><div class="lab">Purchase Total</div><div class="val"><?=money($purchaseTotal)?></div></div></div>
<div class="panel"><div class="panel-head"><h2>Purchase / Stock-In Transactions</h2><span><?=count($purchaseRows)?> records</span></div>
<?php if(!$purchaseRows): ?><div class="empty">No purchases found for this period.</div><?php else: ?>
<table class="table">
<tr><th>Invoice</th><th>Supplier</th><th>Items</th><th>Base Units</th><th>Total</th><th>Received By</th><th>Date</th></tr>
<?php foreach($purchaseRows as $r): ?>
<tr>
<td><b><?=e($r['invoice_no']?:'No Invoice')?></b></td>
<td><?=e($r['supplier'])?></td>
<td><?=number_format((int)$r['item_lines'])?></td>
<td><?=number_format((int)$r['item_qty'])?></td>
<td class="money"><?=money($r['total'])?></td>
<td><?=e($r['received_by'])?></td>
<td><?=date('d M Y H:i',strtotime($r['created_at']))?></td>
</tr>
<?php endforeach; ?>
<tr><th colspan="4">TOTAL</th><th><?=money($purchaseTotal)?></th><th colspan="2"></th></tr>
</table>
<div class="rpt-sub" style="padding:10px 15px">
Supplier names are taken from the purchase record first, then from the linked stock batch. If both supplier fields are empty, update that purchase or batch in the system to record the correct supplier.
</div>
<?php endif; ?></div>
<?php elseif($report==='expenses'): ?>
<div class="cards"><div class="card red"><div class="lab">Expense Transactions</div><div class="val"><?=number_format($expenseTransactions)?></div></div><div class="card red"><div class="lab">Total Expenses</div><div class="val"><?=money($expenseTotal)?></div></div></div>
<div class="panel"><div class="panel-head"><h2>Recorded Expenses</h2><span><?=count($expenseRows)?> records</span></div>
<?php if(!$expenseRows): ?><div class="empty">No expenses found for this period.</div><?php else: ?>
<table class="table"><tr><th>Title</th><th>Amount</th><th>Notes</th><th>Recorded By</th><th>Date</th></tr>
<?php foreach($expenseRows as $r): ?><tr><td><b><?=e($r['title'])?></b></td><td class="danger"><?=money($r['amount'])?></td><td><?=e($r['notes']?:'-')?></td><td><?=e($r['recorded_by'])?></td><td><?=date('d M Y H:i',strtotime($r['created_at']))?></td></tr><?php endforeach; ?>
<tr><th>TOTAL</th><th><?=money($expenseTotal)?></th><th colspan="3"></th></tr></table><?php endif; ?></div>
<?php elseif($report==='stock'): ?>
<div class="cards"><div class="card"><div class="lab">Active Medicines</div><div class="val"><?=count($stockRows)?></div></div><div class="card"><div class="lab">Current Units</div><div class="val"><?=number_format($totalStock)?></div></div><div class="card"><div class="lab">Estimated Cost Value</div><div class="val"><?=money($stockValue)?></div></div></div>
<div class="panel"><div class="panel-head"><h2>Current Inventory</h2><span><?=count($stockRows)?> medicines</span></div>
<?php if(!$stockRows): ?><div class="empty">No active medicines found.</div><?php else: ?>
<table class="table"><tr><th>SKU</th><th>Medicine</th><th>Category</th><th>Available Stock</th><th>Reorder Level</th><th>Batch Stock</th><th>Purchase</th><th>Selling</th><th>Cost Value</th><th>Status</th></tr>
<?php foreach($stockRows as $r): $low=(int)$r['quantity']<=(int)$r['reorder_level']; $sv=(float)$r['stock_value']; ?><tr>
<td><b><?=e($r['sku'])?></b><?php if($r['barcode']): ?><br><small><?=e($r['barcode'])?></small><?php endif; ?></td><td><b><?=e($r['name'])?></b><?php if($r['generic_name']): ?><br><small><?=e($r['generic_name'])?></small><?php endif; ?></td><td><?=e($r['category']?:'-')?></td><td class="<?=$low?'danger':'profit'?>"><?=number_format($r['quantity'])?></td><td><?=number_format($r['reorder_level'])?></td><td><?=number_format($r['batch_stock'])?></td><td><?=money($r['purchase_price'])?></td><td><?=money($r['selling_price'])?></td><td><?=money($sv)?></td><td><span class="badge <?=$low?'bad':'good'?>"><?=$low?'LOW STOCK':'IN STOCK'?></span></td>
</tr><?php endforeach; ?></table><?php endif; ?></div>
<?php else: ?>
<div class="cards"><div class="card red"><div class="lab">Expired Stock Cost</div><div class="val"><?=money($expiredValue)?></div></div><div class="card yellow"><div class="lab">Expiring Soon Stock Cost</div><div class="val"><?=money($expiringValue)?></div></div></div>
<div class="panel"><div class="panel-head"><h2>Batch Expiry Report</h2><span><?=count($expiryRows)?> batches</span></div>
<?php if(!$expiryRows): ?><div class="empty">No batch with remaining stock has an expiry date.</div><?php else: ?>
<table class="table"><tr><th>Medicine</th><th>SKU</th><th>Batch</th><th>Expiry</th><th>Days</th><th>Stock</th><th>Supplier</th><th>Status</th></tr>
<?php $td=new DateTimeImmutable('today'); foreach($expiryRows as $r): $ed=new DateTimeImmutable($r['expiry_date']); $days=(int)$td->diff($ed)->format('%r%a'); if($days<0){$sl='EXPIRED';$bc='bad';}elseif($days<=90){$sl='EXPIRING SOON';$bc='warn';}else{$sl='GOOD';$bc='good';} ?>
<tr><td><b><?=e($r['name'])?></b></td><td><?=e($r['sku'])?></td><td><b><?=e($r['batch_no'])?></b></td><td><?=e($r['expiry_date'])?></td><td class="<?=$days<0?'danger':($days<=90?'warning':'profit')?>"><?=$days<0?$days.' days':$days.' days left'?></td><td><?=number_format($r['quantity'])?></td><td><?=e($r['supplier'])?></td><td><span class="badge <?=$bc?>"><?=$sl?></span></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>
<?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
