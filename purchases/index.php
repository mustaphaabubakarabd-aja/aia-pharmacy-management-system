<?php
$title = 'Purchases / Stock In';
require '../includes/header.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $items = json_decode($_POST['items'] ?? '[]', true);
    if ($supplierId < 1) {
        flash('error', 'Please select a registered supplier before recording a purchase.');
        redirect('purchases/index.php');
    }
    if (!is_array($items) || !$items) {
        flash('error', 'Please add at least one medicine to the purchase.');
        redirect('purchases/index.php');
    }
    try {
        $pdo->beginTransaction();

        // Prevent new purchases from being saved with a missing/invalid supplier.
        $supplierCheck = $pdo->prepare('SELECT id FROM suppliers WHERE id = ? LIMIT 1');
        $supplierCheck->execute([$supplierId]);
        if (!$supplierCheck->fetchColumn()) {
            throw new RuntimeException('The selected supplier was not found. Please select a valid supplier.');
        }

        if ($invoiceNo !== '') {
            $q = $pdo->prepare('SELECT id FROM purchases WHERE LOWER(TRIM(invoice_no)) = LOWER(TRIM(?)) LIMIT 1');
            $q->execute([$invoiceNo]);
            if ($q->fetch()) throw new RuntimeException('This invoice number already exists. Use a unique invoice number.');
        }
        $prepared = []; $grandTotal = 0.0;
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $unitType = strtoupper(trim((string)($item['unit_type'] ?? 'PIECE')));
            $unitQty = (int)($item['unit_quantity'] ?? 0);
            $unitBuyPrice = (float)($item['purchase_price'] ?? -1);
            $sellPrice = (float)($item['selling_price'] ?? -1);
            $retailPackPrice = isset($item['retail_pack_price']) && $item['retail_pack_price'] !== '' ? (float)$item['retail_pack_price'] : null;
            $retailCartonPrice = isset($item['retail_carton_price']) && $item['retail_carton_price'] !== '' ? (float)$item['retail_carton_price'] : null;
            $wholesalePiecePrice = isset($item['wholesale_piece_price']) && $item['wholesale_piece_price'] !== '' ? (float)$item['wholesale_piece_price'] : null;
            $wholesalePackPrice = isset($item['wholesale_pack_price']) && $item['wholesale_pack_price'] !== '' ? (float)$item['wholesale_pack_price'] : null;
            $wholesaleCartonPrice = isset($item['wholesale_carton_price']) && $item['wholesale_carton_price'] !== '' ? (float)$item['wholesale_carton_price'] : null;
            $batchNo = trim((string)($item['batch_no'] ?? ''));
            $expiry = !empty($item['expiry_date']) ? $item['expiry_date'] : null;
            $customPiecesPerPack = isset($item['pieces_per_pack']) && $item['pieces_per_pack'] !== '' ? (int)$item['pieces_per_pack'] : 0;
            $customPacksPerCarton = isset($item['packs_per_carton']) && $item['packs_per_carton'] !== '' ? (int)$item['packs_per_carton'] : 0;
            if ($productId < 1) throw new RuntimeException('Invalid medicine selected.');
            if (!in_array($unitType, ['PIECE','PACK','CARTON'], true)) throw new RuntimeException('Invalid purchase unit selected.');
            if ($unitQty < 1) throw new RuntimeException('Quantity must be at least 1.');
            if ($unitBuyPrice < 0 || $sellPrice < 0) throw new RuntimeException('Prices cannot be negative.');
            foreach ([$retailPackPrice,$retailCartonPrice,$wholesalePiecePrice,$wholesalePackPrice,$wholesaleCartonPrice] as $optionalPrice) if ($optionalPrice !== null && (!is_finite($optionalPrice) || $optionalPrice < 0)) throw new RuntimeException('Optional batch selling prices must be valid non-negative amounts.');
            if ($batchNo === '') throw new RuntimeException('Batch number is required.');
            if ($expiry !== null) {
                $ts = strtotime($expiry);
                if ($ts === false) throw new RuntimeException('Invalid expiry date.');
                if ($ts < strtotime(date('Y-m-d'))) throw new RuntimeException('Expired medicine cannot be added to stock.');
            }
            $q = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'Active' FOR UPDATE");
            $q->execute([$productId]); $product = $q->fetch(PDO::FETCH_ASSOC);
            if (!$product) throw new RuntimeException('Selected medicine was not found or is inactive.');
            $packs = $customPacksPerCarton > 0 ? $customPacksPerCarton : null;
            $pieces = $customPiecesPerPack > 0 ? $customPiecesPerPack : null;
            if ($unitType === 'PIECE') {
                $piecesPerUnit = 1;
            } elseif ($unitType === 'PACK') {
                if (!$pieces || $pieces < 1) throw new RuntimeException('Enter/confirm Pieces per Pack for '.$product['name'].' before purchasing a Pack.');
                $piecesPerUnit = $pieces;
            } else {
                if (!$packs || !$pieces || $packs < 1 || $pieces < 1 || $packs > intdiv(PHP_INT_MAX, $pieces)) {
                    throw new RuntimeException('Invalid or excessively large carton conversion for '.$product['name'].'.');
                }
                $piecesPerUnit = $packs * $pieces;
            }
            if ($unitQty > intdiv(PHP_INT_MAX, $piecesPerUnit)) {
                throw new RuntimeException('The purchase quantity is too large for '.$product['name'].'.');
            }
            $baseQty = $unitQty * $piecesPerUnit;
            $buyPerPiece = $unitBuyPrice / $piecesPerUnit;
            $lineTotal = $unitQty * $unitBuyPrice;
            $grandTotal += $lineTotal;
            $prepared[] = ['product_id'=>$productId,'batch_no'=>$batchNo,'expiry_date'=>$expiry,
                'unit_type'=>$unitType,'unit_quantity'=>$unitQty,'pieces_per_pack'=>$pieces,
                'packs_per_carton'=>$packs,'pieces_per_unit'=>$piecesPerUnit,'base_quantity'=>$baseQty,
                'quantity'=>$baseQty,'purchase_price'=>$buyPerPiece,'selling_price'=>$sellPrice,
                'retail_pack_price'=>$retailPackPrice,'retail_carton_price'=>$retailCartonPrice,
                'wholesale_piece_price'=>$wholesalePiecePrice,'wholesale_pack_price'=>$wholesalePackPrice,'wholesale_carton_price'=>$wholesaleCartonPrice,
                'line_total'=>$lineTotal];
        }
        $q = $pdo->prepare('INSERT INTO purchases (supplier_id, invoice_no, total, created_by) VALUES (?,?,?,?)');
        $q->execute([$supplierId, $invoiceNo !== '' ? $invoiceNo : null, $grandTotal, $_SESSION['user']['id'] ?? null]);
        $purchaseId = (int)$pdo->lastInsertId();
        // Store the conversion used for each batch. Requires product_batches.pieces_per_pack
        // and product_batches.packs_per_carton columns (added by the migration script).
        $batchStmt = $pdo->prepare('INSERT INTO product_batches (product_id,batch_no,expiry_date,purchase_price,selling_price,quantity,supplier_id,created_by,pieces_per_pack,packs_per_carton,retail_pack_price,retail_carton_price,wholesale_piece_price,wholesale_pack_price,wholesale_carton_price) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $itemStmt = $pdo->prepare('INSERT INTO purchase_items (purchase_id,product_id,batch_id,quantity,purchase_price,unit_type,unit_quantity,base_quantity) VALUES (?,?,?,?,?,?,?,?)');
        $productStmt = $pdo->prepare('UPDATE products SET quantity=quantity+?, purchase_price=?, selling_price=?, batch_no=?, expiry_date=?, supplier_id=? WHERE id=?');
        $moveStmt = $pdo->prepare("INSERT INTO stock_movements (product_id,batch_id,type,quantity,reference,notes,created_by) VALUES (?,?,'IN',?,?,?,?)");
        foreach ($prepared as $item) {
            $userId = $_SESSION['user']['id'] ?? null;
            $batchStmt->execute([$item['product_id'],$item['batch_no'],$item['expiry_date'],$item['purchase_price'],$item['selling_price'],$item['base_quantity'],$supplierId,$userId,$item['pieces_per_pack'],$item['packs_per_carton'],$item['retail_pack_price'],$item['retail_carton_price'],$item['wholesale_piece_price'],$item['wholesale_pack_price'],$item['wholesale_carton_price']]);
            $batchId = (int)$pdo->lastInsertId();
            $itemStmt->execute([$purchaseId,$item['product_id'],$batchId,$item['base_quantity'],$item['purchase_price'],$item['unit_type'],$item['unit_quantity'],$item['base_quantity']]);
            $productStmt->execute([$item['base_quantity'],$item['purchase_price'],$item['selling_price'],$item['batch_no'],$item['expiry_date'],$supplierId,$item['product_id']]);
            $moveStmt->execute([$item['product_id'],$batchId,$item['base_quantity'],'Purchase #'.$purchaseId,
                'Unit: '.$item['unit_type'].'; Purchased: '.$item['unit_quantity'].'; Base pieces: '.$item['base_quantity'].'; Batch: '.$item['batch_no'].'; Expiry: '.($item['expiry_date'] ?: 'N/A'),$userId]);
        }
        $pdo->commit();
        flash('success', 'Purchase #'.$purchaseId.' recorded successfully. Stock was converted to pieces and updated.');
        redirect('purchases/index.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('AIA PHARMACY purchase save error: '.$e->getMessage());
        flash('error', $e->getMessage());
        redirect('purchases/index.php');
    }
}

$products = $pdo->query("SELECT id,name,sku,barcode,purchase_price,selling_price,quantity,packs_per_carton,pieces_per_pack,retail_pack_price,retail_carton_price,wholesale_piece_price,wholesale_pack_price,wholesale_carton_price FROM products WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$rows = $pdo->query('SELECT pu.*,s.name AS supplier,u.name AS creator FROM purchases pu LEFT JOIN suppliers s ON s.id=pu.supplier_id LEFT JOIN users u ON u.id=pu.created_by ORDER BY pu.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
.purchase-header{background:#f8fff9;border:2px solid #198754;padding:18px;border-radius:10px;margin-bottom:20px}.purchase-header h2{margin-top:0;color:#075b39}.purchase-items{overflow-x:auto;margin-top:20px}.purchase-items table{min-width:1120px;width:100%}.purchase-items input,.purchase-items select{min-width:90px}.item-total{font-weight:bold;white-space:nowrap}.grand-total-box{margin-top:20px;padding:18px;background:#f5f5f5;border-radius:8px;text-align:right}.grand-total{font-size:28px;font-weight:bold;color:#198754}.remove-item{background:#dc3545;color:#fff;border:0;border-radius:5px;padding:7px 12px;cursor:pointer}.empty-items{text-align:center;padding:25px;color:#777}.unit-note{font-size:12px;color:#58656b}
</style>
<h1>Purchases / Stock In</h1>
<div class="purchase-header">
<h2>📦 New Purchase / Stock In</h2>
<div class="grid"><p><label>Supplier</label><select id="supplier_id" required><option value="">-- Select Supplier --</option><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></p>
<p><label>Invoice No.</label><input id="invoice_no" placeholder="e.g. INV-2026-001" autocomplete="off"></p></div>
<div class="grid" style="align-items:end">
<p><label>Medicine</label><select id="product_id"><option value="">-- Select Medicine --</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" data-sku="<?= e($p['sku']) ?>" data-buy="<?= e($p['purchase_price']) ?>" data-sell="<?= e($p['selling_price']) ?>" data-packs="0" data-pieces="0"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?></select></p>
<p><label>Batch No.</label><input id="batch_no" placeholder="e.g. BTH-2026-001"></p>
<p><label>Expiry Date</label><input type="date" id="expiry_date"></p>
<p><label>Purchase Unit</label><select id="unit_type"><option value="PIECE">Piece</option><option value="PACK">Pack</option><option value="CARTON">Carton</option></select></p>
<p><label>Pieces per Pack (required for Pack/Carton if unknown)</label><input type="number" id="pieces_per_pack" min="1" step="1" placeholder="Enter actual count"></p>
<p><label>Packs per Carton (required for Carton if unknown)</label><input type="number" id="packs_per_carton" min="1" step="1" placeholder="Enter actual count"></p>
<p><label>Quantity (selected unit)</label><input type="number" id="quantity" min="1" step="1" value="1"></p>
<p><label>Purchase Price per selected unit (₦)</label><input type="number" id="purchase_price" min="0" step="0.01" placeholder="0.00"></p>
<p><label>Retail Selling Price per Piece (₦)</label><input type="number" id="selling_price" min="0" step="0.01" placeholder="Use medicine default"></p>
<p><label>Retail Price per Pack (optional batch price)</label><input type="number" id="retail_pack_price" min="0" step="0.01" placeholder="Use medicine default"></p>
<p><label>Retail Price per Carton (optional batch price)</label><input type="number" id="retail_carton_price" min="0" step="0.01" placeholder="Use medicine default"></p>
<p><label>Wholesale Price per Piece (optional)</label><input type="number" id="wholesale_piece_price" min="0" step="0.01" placeholder="Use medicine default"></p>
<p><label>Wholesale Price per Pack (optional)</label><input type="number" id="wholesale_pack_price" min="0" step="0.01" placeholder="Use medicine default"></p>
<p><label>Wholesale Price per Carton (optional)</label><input type="number" id="wholesale_carton_price" min="0" step="0.01" placeholder="Use medicine default"></p>
</div><p class="unit-note" id="conversion_note">Select a medicine to see its unit conversion.</p><button type="button" onclick="addPurchaseItem()">➕ Add Medicine</button>
</div>
<div class="form-card purchase-items"><h2>Purchase Items</h2><table id="purchaseTable"><thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Unit</th><th>Purchased Qty</th><th>Base Pieces</th><th>Buy Price / Unit</th><th>Retail / Piece</th><th>Total</th><th>Action</th></tr></thead><tbody id="purchaseBody"><tr><td colspan="10" class="empty-items">No medicines added yet.</td></tr></tbody></table>
<div class="grand-total-box"><div>Grand Total</div><div class="grand-total" id="grandTotal">₦0.00</div></div>
<form method="post" id="purchaseForm" style="margin-top:20px"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="supplier_id" id="form_supplier_id"><input type="hidden" name="invoice_no" id="form_invoice_no"><input type="hidden" name="items" id="form_items"><button type="submit">💾 Save Purchase / Add Stock</button></form></div>
<h2 style="margin-top:30px">Recent Purchases</h2><table><tr><th>Invoice</th><th>Supplier</th><th>Total</th><th>Created By</th><th>Date</th></tr>
<?php if(!$rows): ?><tr><td colspan="5" style="text-align:center;padding:25px">No purchases recorded yet.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><?= e($r['invoice_no'] ?: '-') ?></td><td><?= e($r['supplier'] ?: 'Supplier not linked (legacy record)') ?></td><td><?= money($r['total']) ?></td><td><?= e($r['creator'] ?? '-') ?></td><td><?= e($r['created_at']) ?></td></tr><?php endforeach; ?></table>
<script>
let purchaseItems=[];const byId=id=>document.getElementById(id);const moneyText=n=>'₦'+Number(n||0).toLocaleString('en-NG',{minimumFractionDigits:2,maximumFractionDigits:2});
function selectedOption(){const s=byId('product_id');return s.options[s.selectedIndex]||null;}
function conversionValues(o){const p=byId('pieces_per_pack')?.value.trim();const c=byId('packs_per_carton')?.value.trim();return {pieces:p?parseInt(p,10):null,packs:c?parseInt(c,10):null};}
function piecesPerUnit(o,u){const cv=conversionValues(o);if(u==='PACK'&&!cv.pieces)return null;if(u==='CARTON'&&(!cv.pieces||!cv.packs))return null;return u==='CARTON'?cv.packs*cv.pieces:(u==='PACK'?cv.pieces:1);}
function updateNote(){const o=selectedOption();if(!o||!o.value){byId('conversion_note').textContent='Select a medicine, then enter the actual packaging counts for this purchase if buying Packs or Cartons.';return;}const u=byId('unit_type').value;const cv=conversionValues(o);const factor=piecesPerUnit(o,u);byId('conversion_note').textContent=factor===null?'Conversion is not entered for this purchase. Enter Pieces per Pack for Pack/Carton, and Packs per Carton for Carton.':'1 '+u.toLowerCase()+' = '+factor+' piece(s), using the counts entered for this purchase.';}
function suggestedBuy(o,u){const p=parseFloat(o?.dataset.buy||'0');const f=piecesPerUnit(o,u);return f===null?0:p*f;}
byId('product_id').addEventListener('change',()=>{const o=selectedOption();if(o&&o.value){byId('purchase_price').value=suggestedBuy(o,byId('unit_type').value).toFixed(2);byId('selling_price').value=parseFloat(o.dataset.sell||'0').toFixed(2);}updateNote();});
byId('unit_type').addEventListener('change',()=>{const o=selectedOption();if(o&&o.value)byId('purchase_price').value=suggestedBuy(o,byId('unit_type').value).toFixed(2);updateNote();});
byId('pieces_per_pack').addEventListener('input',()=>{const o=selectedOption();if(o&&o.value)byId('purchase_price').value=suggestedBuy(o,byId('unit_type').value).toFixed(2);updateNote();});
byId('packs_per_carton').addEventListener('input',()=>{const o=selectedOption();if(o&&o.value)byId('purchase_price').value=suggestedBuy(o,byId('unit_type').value).toFixed(2);updateNote();});
function addPurchaseItem(){const o=selectedOption(),pid=byId('product_id').value,u=byId('unit_type').value,q=parseInt(byId('quantity').value||'0',10),buy=parseFloat(byId('purchase_price').value),sell=parseFloat(byId('selling_price').value||'0'),batch=byId('batch_no').value.trim(),expiry=byId('expiry_date').value;
if(!pid||!o)return alert('Please select a medicine.');if(!batch)return alert('Please enter the batch number.');if(!expiry)return alert('Please enter the expiry date.');const ex=new Date(expiry+'T00:00:00'),today=new Date();today.setHours(0,0,0,0);if(Number.isNaN(ex.getTime())||ex<today)return alert('Enter a valid expiry date that is not in the past.');if(!Number.isInteger(q)||q<1)return alert('Quantity must be a whole number of at least 1.');if(!Number.isFinite(buy)||buy<0)return alert('Enter a valid purchase price.');if(!Number.isFinite(sell)||sell<0)return alert('Enter a valid retail selling price per piece.');
const cv=conversionValues(o);const factor=piecesPerUnit(o,u);if(factor===null)return alert('Packaging conversion is unknown. Enter the actual Pieces per Pack and, for Carton, Packs per Carton first.');const base=q*factor;if(!Number.isSafeInteger(base)||base<1)return alert('Invalid conversion or quantity.');purchaseItems.push({product_id:parseInt(pid,10),name:o.dataset.name||o.textContent.trim(),sku:o.dataset.sku||'',batch_no:batch,expiry_date:expiry,unit_type:u,unit_quantity:q,pieces_per_pack:cv.pieces,packs_per_carton:cv.packs,base_quantity:base,purchase_price:buy,selling_price:sell,retail_pack_price:optionalPrice('retail_pack_price'),retail_carton_price:optionalPrice('retail_carton_price'),wholesale_piece_price:optionalPrice('wholesale_piece_price'),wholesale_pack_price:optionalPrice('wholesale_pack_price'),wholesale_carton_price:optionalPrice('wholesale_carton_price')});renderItems();byId('product_id').value='';byId('batch_no').value='';byId('expiry_date').value='';byId('quantity').value='1';byId('purchase_price').value='';byId('selling_price').value='';['retail_pack_price','retail_carton_price','wholesale_piece_price','wholesale_pack_price','wholesale_carton_price'].forEach(id=>byId(id).value='');byId('pieces_per_pack').value='';byId('packs_per_carton').value='';updateNote();}
function optionalPrice(id){const v=byId(id).value.trim();if(v==='')return null;const n=Number(v);return Number.isFinite(n)&&n>=0?n:null;}
function removeItem(i){purchaseItems.splice(i,1);renderItems();}
function updateItem(i,field,value){if(field==='unit_quantity'){const q=parseInt(value||'1',10);purchaseItems[i].unit_quantity=Number.isInteger(q)&&q>0?q:1;const factor=purchaseItems[i].unit_type==='CARTON'?(purchaseItems[i].pieces_per_pack||0)*(purchaseItems[i].packs_per_carton||0):(purchaseItems[i].unit_type==='PACK'?(purchaseItems[i].pieces_per_pack||0):1);purchaseItems[i].base_quantity=purchaseItems[i].unit_quantity*factor;}else{const p=parseFloat(value);purchaseItems[i][field]=Number.isFinite(p)&&p>=0?p:0;}renderItems();}
function esc(v){return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function serialize(){return JSON.stringify(purchaseItems.map(i=>({product_id:i.product_id,batch_no:i.batch_no,expiry_date:i.expiry_date,unit_type:i.unit_type,unit_quantity:i.unit_quantity,pieces_per_pack:i.pieces_per_pack,packs_per_carton:i.packs_per_carton,base_quantity:i.base_quantity,quantity:i.base_quantity,purchase_price:i.purchase_price,selling_price:i.selling_price,retail_pack_price:i.retail_pack_price,retail_carton_price:i.retail_carton_price,wholesale_piece_price:i.wholesale_piece_price,wholesale_pack_price:i.wholesale_pack_price,wholesale_carton_price:i.wholesale_carton_price})));}
function renderItems(){let total=0;const body=byId('purchaseBody');if(!purchaseItems.length){body.innerHTML='<tr><td colspan="10" class="empty-items">No medicines added yet.</td></tr>';}else{body.innerHTML=purchaseItems.map((i,n)=>{const line=i.unit_quantity*i.purchase_price;total+=line;return `<tr><td><strong>${esc(i.name)}</strong><br><small>SKU: ${esc(i.sku)}</small></td><td>${esc(i.batch_no)}</td><td>${esc(i.expiry_date)}</td><td>${esc(i.unit_type)}</td><td><input type="number" min="1" step="1" value="${i.unit_quantity}" onchange="updateItem(${n},'unit_quantity',this.value)" style="width:80px"></td><td>${i.base_quantity}</td><td><input type="number" min="0" step="0.01" value="${Number(i.purchase_price).toFixed(2)}" onchange="updateItem(${n},'purchase_price',this.value)" style="width:110px"></td><td><input type="number" min="0" step="0.01" value="${Number(i.selling_price).toFixed(2)}" onchange="updateItem(${n},'selling_price',this.value)" style="width:110px"></td><td class="item-total">${moneyText(line)}</td><td><button type="button" class="remove-item" onclick="removeItem(${n})">×</button></td></tr>`;}).join('');}byId('grandTotal').textContent=moneyText(total);byId('form_items').value=serialize();}
byId('purchaseForm').addEventListener('submit',e=>{if(!byId('supplier_id').value){e.preventDefault();alert('Please select a registered supplier.');return;}if(!purchaseItems.length){e.preventDefault();alert('Please add at least one medicine.');return;}byId('form_supplier_id').value=byId('supplier_id').value;byId('form_invoice_no').value=byId('invoice_no').value.trim();byId('form_items').value=serialize();if(!confirm('Save this purchase and update pharmacy stock?'))e.preventDefault();});
renderItems();
</script>
<?php require '../includes/footer.php'; ?>
