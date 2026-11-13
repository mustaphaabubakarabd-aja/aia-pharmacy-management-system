<?php
$title = 'POS';
require '../includes/header.php';
$pdo = db();

/*
 * Batch-aware POS:
 * - Stock is stored in pieces.
 * - Each batch keeps its own pack/carton conversion.
 * - A pack/carton sale is fulfilled from one selected batch, so different
 *   carton sizes are never mixed in one sale line.
 * - Expired batches are excluded and stock is locked during checkout.
 */
$products = $pdo->query("
    SELECT p.id AS product_id, p.sku, p.barcode, p.name,
           p.selling_price AS selling_price, /* Piece retail price comes from the product's per-piece price; batch selling_price is legacy and may hold a pack/carton price. */
           p.wholesale_piece_price AS wholesale_piece_price,
           COALESCE(b.wholesale_pack_price,p.wholesale_pack_price) AS wholesale_pack_price,
           COALESCE(b.wholesale_carton_price,p.wholesale_carton_price) AS wholesale_carton_price,
           COALESCE(b.retail_pack_price,p.retail_pack_price) AS retail_pack_price,
           COALESCE(b.retail_carton_price,p.retail_carton_price) AS retail_carton_price,
           p.quantity AS product_quantity,
           b.id AS batch_id, b.batch_no, b.expiry_date, b.quantity AS batch_quantity,
           NULLIF(b.pieces_per_pack,0) AS pieces_per_pack,
           NULLIF(b.packs_per_carton,0) AS packs_per_carton
    FROM products p
    INNER JOIN product_batches b ON b.product_id=p.id
    WHERE p.status='Active' AND p.quantity>0 AND b.quantity>0
      AND (b.expiry_date IS NULL OR b.expiry_date>=CURDATE())
    ORDER BY p.name, CASE WHEN b.expiry_date IS NULL THEN 1 ELSE 0 END, b.expiry_date, b.id
")->fetchAll(PDO::FETCH_ASSOC);

$customers = $pdo->query("SELECT id,name,phone,customer_type,credit_allowed,credit_limit FROM customers WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $items = json_decode($_POST['items'] ?? '[]', true);
    $saleType = ($_POST['sale_type'] ?? 'Retail') === 'Wholesale' ? 'Wholesale' : 'Retail';
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    $discount = (float)($_POST['discount'] ?? 0);
    $paid = (float)($_POST['paid'] ?? 0);

    if (!is_finite($discount) || !is_finite($paid)) {
        flash('error','Please enter valid discount and payment amounts.');
        redirect('pos/index.php');
    }
    $discount = max(0, $discount);
    $paid = max(0, $paid);
    if (!is_array($items) || !$items) { flash('error','Cart is empty.'); redirect('pos/index.php'); }

    $clean = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $pid = (int)($item['product_id'] ?? 0);
        $bid = (int)($item['batch_id'] ?? 0);
        $unit = $item['unit_type'] ?? 'Piece';
        $rawQty = $item['quantity'] ?? 0;
        if (!is_numeric($rawQty)) continue;
        $qty = (int)$rawQty;
        if ($unit === 'Manual pieces') $unit = 'Piece'; // Manual quantity is entered directly as base pieces.
        if ($pid > 0 && $bid > 0 && $qty > 0 && in_array($unit,['Piece','Pack','Carton'],true)) {
            if (isset($clean[$bid])) {
                flash('error','A batch appears more than once in the cart. Please review the cart.');
                redirect('pos/index.php');
            }
            $clean[$bid] = ['product_id'=>$pid,'batch_id'=>$bid,'unit_type'=>$unit,'quantity'=>$qty];
        }
    }
    if (!$clean) { flash('error','Please add valid medicine quantities.'); redirect('pos/index.php'); }

    $pdo->beginTransaction();
    try {
        $alloc = [];
        $subtotal = 0.0;
        $productSold = [];

        foreach ($clean as $entry) {
            $ps = $pdo->prepare("SELECT * FROM products WHERE id=? AND status='Active' FOR UPDATE");
            $ps->execute([$entry['product_id']]);
            $p = $ps->fetch(PDO::FETCH_ASSOC);
            if (!$p) throw new RuntimeException('A selected medicine is no longer available.');

            $bs = $pdo->prepare("SELECT * FROM product_batches WHERE id=? AND product_id=? AND quantity>0 AND (expiry_date IS NULL OR expiry_date>=CURDATE()) FOR UPDATE");
            $bs->execute([$entry['batch_id'],$entry['product_id']]);
            $b = $bs->fetch(PDO::FETCH_ASSOC);
            if (!$b) throw new RuntimeException('The selected batch is unavailable or expired. Please refresh POS and choose another batch.');

            $pack = (int)($b['pieces_per_pack'] ?? 0);
            $packsPerCarton = (int)($b['packs_per_carton'] ?? 0);
            if ($entry['unit_type']==='Pack' && $pack < 1) throw new RuntimeException('Pieces per Pack is not confirmed for '.$p['name'].' batch '.$b['batch_no'].'. Update the batch conversion before selling a Pack.');
            if ($entry['unit_type']==='Carton' && ($pack < 1 || $packsPerCarton < 1)) throw new RuntimeException('Carton conversion is not confirmed for '.$p['name'].' batch '.$b['batch_no'].'. Update both packaging counts before selling a Carton.');
            if ($entry['unit_type']==='Carton' && $pack > intdiv(PHP_INT_MAX,$packsPerCarton)) throw new RuntimeException('Invalid carton conversion for '.$p['name'].'.');
            $carton = ($pack > 0 && $packsPerCarton > 0) ? $pack * $packsPerCarton : 0;
            $factor = $entry['unit_type']==='Carton' ? $carton : ($entry['unit_type']==='Pack' ? $pack : 1);
            if ($entry['quantity'] > intdiv(PHP_INT_MAX,$factor)) throw new RuntimeException('The quantity entered for '.$p['name'].' is too large.');
            $baseQty = $entry['quantity'] * $factor;

            // Match the displayed price: use a batch-specific override when present, otherwise the medicine default.
            if ($entry['unit_type']==='Carton') {
                $price = $saleType==='Wholesale'
                    ? (float)(($b['wholesale_carton_price'] ?? null) !== null ? $b['wholesale_carton_price'] : $p['wholesale_carton_price'])
                    : (float)(($b['retail_carton_price'] ?? null) !== null ? $b['retail_carton_price'] : $p['retail_carton_price']);
            } elseif ($entry['unit_type']==='Pack') {
                $price = $saleType==='Wholesale'
                    ? (float)(($b['wholesale_pack_price'] ?? null) !== null ? $b['wholesale_pack_price'] : $p['wholesale_pack_price'])
                    : (float)(($b['retail_pack_price'] ?? null) !== null ? $b['retail_pack_price'] : $p['retail_pack_price']);
            } else {
                $price = $saleType==='Wholesale'
                    ? (float)(($b['wholesale_piece_price'] ?? null) !== null ? $b['wholesale_piece_price'] : $p['wholesale_piece_price'])
                    : (float)$p['selling_price'];
            }
            if ($price <= 0) throw new RuntimeException('Please set a valid '.$saleType.' price for '.$p['name'].' in '.$entry['unit_type'].' units.');
            if ((int)$b['quantity'] < $baseQty) {
                throw new RuntimeException($p['name'].' batch '.$b['batch_no'].' has only '.(int)$b['quantity'].' pieces; '.$entry['quantity'].' '.$entry['unit_type'].' requires '.$baseQty.' pieces.');
            }
            $subtotal += $entry['quantity'] * $price;
            $productSold[(int)$p['id']] = ($productSold[(int)$p['id']] ?? 0) + $baseQty;
            $alloc[] = [
                'product_id'=>(int)$p['id'],'batch_id'=>(int)$b['id'],'batch_no'=>$b['batch_no'],
                'unit_type'=>$entry['unit_type'],'unit_quantity'=>$entry['quantity'],
                'base_quantity'=>$baseQty,'unit_price'=>$price,'total'=>round($entry['quantity']*$price,2),
                'product_name'=>$p['name']
            ];
        }

        foreach ($productSold as $pid=>$qty) {
            $st = $pdo->prepare('SELECT quantity FROM products WHERE id=? FOR UPDATE');
            $st->execute([$pid]);
            if ((int)$st->fetchColumn() < $qty) throw new RuntimeException('Product stock changed during checkout. Sale cancelled.');
        }

        $subtotal = round($subtotal,2);
        if ($discount > $subtotal) $discount = $subtotal;
        $discount = round($discount,2);
        $total = round(max(0,$subtotal-$discount),2);
        $due = round(max(0,$total-$paid),2);
        $change = round(max(0,$paid-$total),2);

        $customer = null;
        if ($customerId) {
            $cs=$pdo->prepare("SELECT * FROM customers WHERE id=? AND status='Active' FOR UPDATE");
            $cs->execute([$customerId]); $customer=$cs->fetch(PDO::FETCH_ASSOC);
            if (!$customer) throw new RuntimeException('Selected customer was not found or is inactive.');
            $customerName=$customer['name'];
        }
        if ($due > 0) {
            if (!$customer) throw new RuntimeException('Select a registered customer before allowing a balance/bashi.');
            if (!(int)$customer['credit_allowed']) throw new RuntimeException('Credit/bashi is not allowed for this customer.');
            $os=$pdo->prepare("SELECT COALESCE(SUM(amount_due),0) FROM sales WHERE customer_id=? AND amount_due>0");
            $os->execute([$customerId]); $outstanding=(float)$os->fetchColumn();
            if ($outstanding+$due>(float)$customer['credit_limit']) throw new RuntimeException('This sale exceeds the customer credit limit. Current balance: '.money($outstanding).'; new balance: '.money($outstanding+$due).'.');
        }

        $receipt='AIA-'.date('YmdHis').'-'.random_int(100,999);
        $status=$due<=0?'Paid':($paid>0?'Partial':'Unpaid');
        $ss=$pdo->prepare("INSERT INTO sales(receipt_no,customer_name,customer_id,sale_type,subtotal,discount,total,amount_paid,change_amount,amount_due,payment_status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
        $ss->execute([$receipt,$customerName?:'Walk-in Customer',$customerId?:null,$saleType,$subtotal,$discount,$total,$paid,$change,$due,$status,$_SESSION['user']['id']??null]);
        $saleId=(int)$pdo->lastInsertId();

        $si=$pdo->prepare("INSERT INTO sale_items(sale_id,product_id,batch_id,quantity,unit_type,unit_quantity,base_quantity,unit_price,total) VALUES(?,?,?,?,?,?,?,?,?)");
        $bu=$pdo->prepare("UPDATE product_batches SET quantity=quantity-? WHERE id=? AND quantity>=?");
        $pu=$pdo->prepare("UPDATE products SET quantity=quantity-? WHERE id=? AND quantity>=?");
        $mv=$pdo->prepare("INSERT INTO stock_movements(product_id,batch_id,type,quantity,reference,notes,created_by) VALUES(?,?,'OUT',?,?,?,?)");

        foreach ($productSold as $pid=>$qty) {
            $pu->execute([$qty,$pid,$qty]);
            if ($pu->rowCount()!==1) throw new RuntimeException('Stock changed for product ID '.$pid.'. Sale cancelled.');
        }
        foreach ($alloc as $a) {
            $si->execute([$saleId,$a['product_id'],$a['batch_id'],$a['base_quantity'],$a['unit_type'],$a['unit_quantity'],$a['base_quantity'],$a['unit_price'],$a['total']]);
            $bu->execute([$a['base_quantity'],$a['batch_id'],$a['base_quantity']]);
            if ($bu->rowCount()!==1) throw new RuntimeException('Batch stock changed. Sale cancelled.');
            $mv->execute([$a['product_id'],$a['batch_id'],$a['base_quantity'],$receipt,'Batch '.$a['batch_no'].' | '.$a['unit_type'].' x '.$a['unit_quantity'].' | base pieces '.$a['base_quantity'],$_SESSION['user']['id']??null]);
        }
        if ($paid>0 && $customerId) {
            $pm=$pdo->prepare("INSERT INTO customer_payments(customer_id,sale_id,amount,payment_method,reference,notes,received_by) VALUES(?,?,?,'Cash',?,?,?)");
            $pm->execute([$customerId,$saleId,min($paid,$total),$receipt,'Initial payment for sale '.$receipt,$_SESSION['user']['id']??null]);
        }
        $pdo->commit();
        redirect('receipt.php?id='.$saleId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('AIA PHARMACY POS checkout error: '.$e->getMessage());
        flash('error',$e->getMessage());
        redirect('pos/index.php');
    }
}
?>
<style>
.pos-wrap{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:16px}.pos-card{background:#fff;border:1px solid #dce8e0;border-radius:12px;padding:18px;box-shadow:0 2px 10px #1231}.pos-card h2{font-size:18px;margin:0 0 14px;color:#064b35}.pos-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pos-card label{display:block;font-weight:600;margin:10px 0 5px}.pos-card input,.pos-card select{width:100%;padding:10px;border:1px solid #cbdad1;border-radius:7px}.pos-table{width:100%;border-collapse:collapse;margin-top:12px}.pos-table th,.pos-table td{padding:8px;border-bottom:1px solid #e2e9e4;text-align:left;font-size:13px}.pos-table th{background:#edf6f0}.pos-total{background:#f5f7f5;padding:14px;border-radius:8px;margin-top:14px}.pos-total div{display:flex;justify-content:space-between;padding:4px 0}.pos-primary{background:#087443;color:white;border:0;border-radius:7px;padding:11px 14px;cursor:pointer;font-weight:700}.pos-muted{font-size:12px;color:#64746b}.pos-items select,.pos-items input{padding:6px}.pos-submit{margin-top:12px;width:100%}@media(max-width:900px){.pos-wrap{grid-template-columns:1fr}}
</style>
<h1>Point of Sale (POS)</h1>
<p class="pos-muted">Retail & Wholesale · Piece / Pack / Carton · Batch-specific conversion · Expiry-safe stock</p>
<div class="pos-wrap">
<section class="pos-card">
<h2>Find Medicine / Batch</h2>
<label for="barcode">Scan barcode or enter SKU</label><input id="barcode" autocomplete="off" placeholder="Scan barcode / enter SKU">
<label for="search">Search medicine or SKU</label><input id="search" placeholder="Type medicine name, SKU or barcode">
<label for="medicineChoice">Medicine</label>
<select id="medicineChoice" style="margin-top:6px"><option value="">-- Select medicine --</option></select>
<label for="batchChoice">Available Batch</label>
<select id="batchChoice" style="margin-top:6px"><option value="">-- Select batch --</option></select>
<select id="product" size="10" style="display:none">
<?php foreach($products as $p): ?>
<option value="<?= (int)$p['batch_id'] ?>"
 data-batch="<?= (int)$p['batch_id'] ?>" data-product="<?= (int)$p['product_id'] ?>"
 data-name="<?= e($p['name']) ?>" data-sku="<?= e($p['sku']) ?>" data-barcode="<?= e($p['barcode']??'') ?>"
 data-batchno="<?= e($p['batch_no']) ?>" data-expiry="<?= e($p['expiry_date']??'') ?>"
 data-stock="<?= (int)$p['batch_quantity'] ?>" data-productstock="<?= (int)$p['product_quantity'] ?>"
 data-pack="<?= (int)($p['pieces_per_pack'] ?? 0) ?>" data-carton="<?= ((int)($p['pieces_per_pack'] ?? 0)>0 && (int)($p['packs_per_carton'] ?? 0)>0) ? (int)$p['pieces_per_pack']*(int)$p['packs_per_carton'] : 0 ?>"
 data-rpiece="<?= (float)$p['selling_price'] ?>" data-rpack="<?= (float)$p['retail_pack_price'] ?>" data-rcarton="<?= (float)$p['retail_carton_price'] ?>"
 data-wpiece="<?= (float)$p['wholesale_piece_price'] ?>" data-wpack="<?= (float)$p['wholesale_pack_price'] ?>" data-wcarton="<?= (float)$p['wholesale_carton_price'] ?>">
<?= e($p['name']) ?> | <?= e($p['sku']) ?> | Batch: <?= e($p['batch_no']) ?> | Stock: <?= (int)$p['batch_quantity'] ?> pcs | <?= e($p['expiry_date']??'No expiry') ?> | Pack <?= (int)($p['pieces_per_pack'] ?? 0)>0 ? (int)$p['pieces_per_pack'].' pcs' : 'unknown' ?> / Carton <?= ((int)($p['pieces_per_pack'] ?? 0)>0 && (int)($p['packs_per_carton'] ?? 0)>0) ? (int)$p['pieces_per_pack']*(int)$p['packs_per_carton'].' pcs' : 'unknown' ?>
</option>
<?php endforeach; ?>
</select>
<button class="pos-primary" type="button" id="add">+ Add to Cart</button>
<p class="pos-muted">Choose the exact batch. Pack/Carton options are disabled until that batch's packaging counts are confirmed. Expired batches are excluded.</p><p class="pos-muted"><strong>Important:</strong> Changing the batch on the left only affects the next item you add. Items already in the cart keep their own batch; remove them with × if you selected the wrong batch.</p>
</section>
<section class="pos-card">
<h2>Shopping Cart & Payment</h2>
<form method="post" id="saleForm">
<input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="items" id="items">
<div class="pos-row">
<div><label>Sale Type</label><select name="sale_type" id="saleType"><option value="Retail">Retail</option><option value="Wholesale">Wholesale</option></select></div>
<div><label>Registered Customer</label><select name="customer_id" id="customerId"><option value="0">Walk-in / no account</option><?php foreach($customers as $c): ?><option value="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-type="<?= e($c['customer_type']) ?>" data-credit="<?= (int)$c['credit_allowed'] ?>"><?= e($c['name']) ?><?= $c['phone']?' · '.e($c['phone']):'' ?> (<?= e($c['customer_type']) ?>)</option><?php endforeach; ?></select></div>
</div>
<label>Customer Name (for walk-in or display)</label><input name="customer_name" id="customerName" value="Walk-in Customer">
<div class="pos-items"><table class="pos-table" id="cart"><thead><tr><th>Medicine / Batch</th><th>Unit</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead><tbody><tr><td colspan="6">Cart is empty.</td></tr></tbody></table></div>
<div class="pos-row"><div><label>Discount (₦)</label><input type="number" min="0" step="0.01" name="discount" id="discount" value="0"></div><div><label>Amount Paid (₦)</label><input type="number" min="0" step="0.01" name="paid" id="paid" value="0"></div></div>
<div class="pos-total"><div><span>Subtotal</span><strong id="subtotal">₦0.00</strong></div><div><span>Discount</span><strong id="discountText">₦0.00</strong></div><div><span>Total</span><strong id="total">₦0.00</strong></div><div><span>Amount due</span><strong id="due">₦0.00</strong></div><div><span>Change</span><strong id="change">₦0.00</strong></div></div>
<p class="pos-muted">Bashi is allowed only for a registered customer with credit enabled and within the customer's credit limit.</p>
<button class="pos-primary pos-submit" type="submit">Complete Sale & Print Receipt</button>
</form>
</section>
</div>
<script>
(()=>{'use strict';
const data={};const $=id=>document.getElementById(id);
const money=n=>'₦'+Number(n||0).toLocaleString('en-NG',{minimumFractionDigits:2,maximumFractionDigits:2});
const batchOptions=[...$('product').options].filter(o=>o.value);
const medicines=new Map();
batchOptions.forEach(o=>{const key=o.dataset.product;if(!medicines.has(key))medicines.set(key,{name:o.dataset.name,sku:o.dataset.sku,barcode:o.dataset.barcode||'',options:[]});medicines.get(key).options.push(o)});
medicines.forEach((m,id)=>{const op=document.createElement('option');op.value=id;op.textContent=m.name+' | '+m.sku;$('medicineChoice').appendChild(op)});
function fillBatches(productId,preferredBatch=''){
const sel=$('batchChoice');sel.innerHTML='<option value="">-- Select batch --</option>';
const m=medicines.get(String(productId));if(!m)return;
m.options.forEach(o=>{const op=document.createElement('option');op.value=o.value;op.textContent='Batch '+o.dataset.batchno+' | Stock '+o.dataset.stock+' pcs | Expiry '+(o.dataset.expiry||'N/A')+' | Pack '+(Number(o.dataset.pack)>0?o.dataset.pack+' pcs':'unknown')+' / Carton '+(Number(o.dataset.carton)>0?o.dataset.carton+' pcs':'unknown');sel.appendChild(op)});
if(preferredBatch)sel.value=String(preferredBatch);
}
function priceFor(o,unit){const prefix=$('saleType').value==='Wholesale'?'w':'r';const suffix=(unit==='Piece'||unit==='Manual pieces')?'piece':unit==='Pack'?'pack':'carton';return Number(o.dataset[prefix+suffix]||0)}
function add(){const batchId=$('batchChoice').value;const o=batchOptions.find(x=>x.value===batchId);if(!o||!o.value){alert('Select a medicine and its batch first.');return}const id=o.value;if(!data[id])data[id]={batchId:Number(o.dataset.batch),productId:Number(o.dataset.product),name:o.dataset.name,sku:o.dataset.sku,batchno:o.dataset.batchno,expiry:o.dataset.expiry,stock:Number(o.dataset.stock),pack:Number(o.dataset.pack),carton:Number(o.dataset.carton),unit:'Piece',qty:1};else data[id].qty+=1;render()}
function render(){const body=$('cart').querySelector('tbody');body.innerHTML='';let subtotal=0;
Object.values(data).forEach(it=>{const o=[...$('product').options].find(x=>x.value===String(it.batchId));if(!o)return;
/* Never leave a cart line on Pack/Carton after its batch conversion is unknown. */
if(it.unit==='Pack'&&it.pack<1)it.unit='Piece';
if(it.unit==='Carton'&&it.carton<1)it.unit='Piece';
const price=priceFor(o,it.unit);const factor=it.unit==='Carton'?it.carton:it.unit==='Pack'?it.pack:1;const conversionMissing=(it.unit==='Pack'&&it.pack<1)||(it.unit==='Carton'&&it.carton<1);const base=conversionMissing?0:it.qty*factor;const over=conversionMissing||base>it.stock;const line=conversionMissing?0:price*it.qty;subtotal+=line;
const tr=document.createElement('tr');const packDisabled=it.pack<1?'disabled title="Pieces per Pack is not confirmed for this batch"':'';const cartonDisabled=it.carton<1?'disabled title="Carton conversion is not confirmed for this batch"':'';const displayPrice=conversionMissing?'—':money(price);const displayLine=conversionMissing?'—':money(line);tr.innerHTML=`<td>${esc(it.name)}<small style="display:block;color:#667">${esc(it.batchno)} · Exp: ${esc(it.expiry||'N/A')}<br><span style="color:${over?'#c62828':'#667'}">${conversionMissing?'Packaging conversion unknown — choose Piece/Manual pieces or update this batch':over?'Not enough batch stock!':'Batch stock '+it.stock+' pieces'}</span></small></td><td><select data-id="${it.batchId}" class="unit"><option ${it.unit==='Piece'?'selected':''}>Piece</option><option ${it.unit==='Manual pieces'?'selected':''}>Manual pieces (enter pieces)</option><option ${it.unit==='Pack'?'selected':''} ${packDisabled}>Pack</option><option ${it.unit==='Carton'?'selected':''} ${cartonDisabled}>Carton</option></select></td><td><input class="qty" data-id="${it.batchId}" type="number" min="1" step="1" value="${it.qty}" style="width:58px"></td><td>${displayPrice}</td><td>${displayLine}<small style="display:block;color:#667">${conversionMissing?'Conversion needed':base+' pieces'}</small></td><td><button type="button" class="remove" data-id="${it.batchId}">×</button></td>`;body.appendChild(tr)});
if(!Object.keys(data).length)body.innerHTML='<tr><td colspan="6">Cart is empty.</td></tr>';
const disc=Math.min(Math.max(0,Number($('discount').value)||0),subtotal),total=Math.max(0,subtotal-disc),paid=Math.max(0,Number($('paid').value)||0);
$('subtotal').textContent=money(subtotal);$('discountText').textContent=money(disc);$('total').textContent=money(total);$('due').textContent=money(Math.max(0,total-paid));$('change').textContent=money(Math.max(0,paid-total));
$('items').value=JSON.stringify(Object.values(data).map(i=>({product_id:i.productId,batch_id:i.batchId,unit_type:i.unit,quantity:i.qty}))); 
}
function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
$('add').addEventListener('click',add);$('saleType').addEventListener('change',render);$('discount').addEventListener('input',render);$('paid').addEventListener('input',render);
$('cart').addEventListener('change',e=>{const id=e.target.dataset.id;if(!id||!data[id])return;if(e.target.classList.contains('unit'))data[id].unit=e.target.value;if(e.target.classList.contains('qty'))data[id].qty=Math.max(1,parseInt(e.target.value||'1',10));render()});
$('cart').addEventListener('click',e=>{if(e.target.classList.contains('remove')){delete data[e.target.dataset.id];render()}});
$('medicineChoice').addEventListener('change',()=>fillBatches($('medicineChoice').value));
$('search').addEventListener('input',()=>{const q=$('search').value.toLowerCase();[...$('medicineChoice').options].forEach((op,i)=>{if(!op.value)return;const m=medicines.get(op.value);op.hidden=!(m.name.toLowerCase().includes(q)||m.sku.toLowerCase().includes(q)||m.barcode.toLowerCase().includes(q)||m.options.some(o=>o.dataset.batchno.toLowerCase().includes(q)))})});
$('barcode').addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const q=$('barcode').value.trim().toLowerCase();const o=batchOptions.find(o=>o.dataset.sku.toLowerCase()===q||(o.dataset.barcode||'').toLowerCase()===q);if(!o){alert('Barcode/SKU not found.');return}$('medicineChoice').value=o.dataset.product;fillBatches(o.dataset.product,o.value);add();$('barcode').value=''});
$('customerId').addEventListener('change',()=>{const o=$('customerId').selectedOptions[0];if(o&&o.dataset.name)$('customerName').value=o.dataset.name;if(o&&o.dataset.type)$('saleType').value=o.dataset.type;render()});
$('saleForm').addEventListener('submit',e=>{if(!Object.keys(data).length){e.preventDefault();alert('Cart is empty.');return}for(const it of Object.values(data)){const factor=it.unit==='Carton'?it.carton:it.unit==='Pack'?it.pack:1;if((it.unit==='Pack'&&it.pack<1)||(it.unit==='Carton'&&it.carton<1)){e.preventDefault();alert('Packaging conversion is unknown for batch '+it.batchno+'. Enter/confirm the batch packaging counts before selling Pack or Carton.');return}if(it.qty*factor>it.stock){e.preventDefault();alert('Insufficient stock in batch '+it.batchno+'. Available: '+it.stock+' pieces.');return}}if(!confirm('Complete this sale and update stock?'))e.preventDefault()});
render();
})();
</script>
<?php require '../includes/footer.php'; ?>
