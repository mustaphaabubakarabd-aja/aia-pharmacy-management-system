<?php
$title = 'Medicine';
require '../includes/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$batchId = (int)($_GET['batch_id'] ?? 0);
$defaults = ['sku'=>'','barcode'=>'','name'=>'','generic_name'=>'','category_id'=>'','batch_no'=>'','expiry_date'=>'','purchase_price'=>'0.00','selling_price'=>'0.00','packs_per_carton'=>null,'pieces_per_pack'=>null,'wholesale_piece_price'=>'0.00','wholesale_pack_price'=>'0.00','wholesale_carton_price'=>'0.00','retail_pack_price'=>'0.00','retail_carton_price'=>'0.00','quantity'=>0,'reorder_level'=>10,'supplier_id'=>'','status'=>'Active'];
$r=$defaults; $hasBatches=false;
if ($id) {
 $q=$pdo->prepare('SELECT * FROM products WHERE id=? LIMIT 1'); $q->execute([$id]); $old=$q->fetch(PDO::FETCH_ASSOC);
 if (!$old) { flash('error','Medicine not found.'); redirect('products/index.php'); }
 $r=array_merge($defaults,$old);
 $q=$pdo->prepare('SELECT COUNT(*) FROM product_batches WHERE product_id=?'); $q->execute([$id]); $hasBatches=(int)$q->fetchColumn()>0;
 if ($hasBatches) { $q=$batchId>0 ? $pdo->prepare('SELECT * FROM product_batches WHERE id=? AND product_id=? LIMIT 1') : $pdo->prepare('SELECT * FROM product_batches WHERE product_id=? ORDER BY received_at DESC,id DESC LIMIT 1');
    $batchId>0 ? $q->execute([$batchId,$id]) : $q->execute([$id]);
    $b=$q->fetch(PDO::FETCH_ASSOC);
    if($b){
      $batchId=(int)$b['id'];
      foreach(['batch_no','expiry_date','purchase_price','selling_price','supplier_id','pieces_per_pack','packs_per_carton','retail_pack_price','retail_carton_price','wholesale_piece_price','wholesale_pack_price','wholesale_carton_price','quantity'] as $k) {
        if(array_key_exists($k,$b)) $r[$k]=$b[$k];
      }
      // A zero batch price means no batch-specific override; show the medicine default.
      foreach(['retail_pack_price','retail_carton_price','wholesale_piece_price','wholesale_pack_price','wholesale_carton_price'] as $k) {
        if((float)($r[$k] ?? 0) <= 0 && (float)($old[$k] ?? 0) > 0) $r[$k]=$old[$k];
      }
    }
  }
} else { $n=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(sku,5) AS UNSIGNED)),0)+1 FROM products WHERE sku REGEXP '^MED-[0-9]+$'")->fetchColumn(); $r['sku']='MED-'.str_pad((string)$n,6,'0',STR_PAD_LEFT); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf();
 $barcode=trim($_POST['barcode']??''); $barcode=$barcode===''?null:$barcode;
 $name=trim($_POST['name']??''); $generic=trim($_POST['generic_name']??''); $cat=($_POST['category_id']??'')!==''?(int)$_POST['category_id']:null;
 $batch=trim($_POST['batch_no']??''); $expiry=trim($_POST['expiry_date']??''); $expiry=$expiry===''?null:$expiry;
 $buy=(float)($_POST['purchase_price']??0); $retailPiece=(float)($_POST['selling_price']??0); $ppc=trim((string)($_POST['packs_per_carton']??''))!==''?(int)$_POST['packs_per_carton']:null; $pieces=trim((string)($_POST['pieces_per_pack']??''))!==''?(int)$_POST['pieces_per_pack']:null;
 $whPiece=(float)($_POST['wholesale_piece_price']??0); $whPack=(float)($_POST['wholesale_pack_price']??0); $whCarton=(float)($_POST['wholesale_carton_price']??0); $retPack=(float)($_POST['retail_pack_price']??0); $retCarton=(float)($_POST['retail_carton_price']??0);
 $qty=(int)($_POST['quantity']??0); $reorder=(int)($_POST['reorder_level']??10); $supplier=($_POST['supplier_id']??'')!==''?(int)$_POST['supplier_id']:null; $status=in_array($_POST['status']??'Active',['Active','Inactive'],true)?$_POST['status']:'Active';
 $back=$id?'products/form.php?id='.$id.($batchId?'&batch_id='.$batchId:''):'products/form.php';
 if($name===''){flash('error','Medicine name is required.');redirect($back);} if(($ppc!==null&&$ppc<1)||($pieces!==null&&$pieces<1)){flash('error','If entered, packaging counts must be at least 1. Leave them blank if unknown.');redirect($back);}
 if(min([$buy,$retailPiece,$whPiece,$whPack,$whCarton,$retPack,$retCarton])<0||$qty<0||$reorder<0){flash('error','Prices and quantities cannot be negative.');redirect($back);} if(!$id&&$qty>0&&$batch===''){flash('error','Enter a batch number when opening stock is greater than zero.');redirect($back);}
 if($barcode!==null){$sql='SELECT id FROM products WHERE barcode=?';$args=[$barcode];if($id){$sql.=' AND id<>?';$args[]=$id;}$sql.=' LIMIT 1';$q=$pdo->prepare($sql);$q->execute($args);if($q->fetch()){flash('error','This barcode is already assigned to another medicine.');redirect($back);}}
 try {
  $pdo->beginTransaction();
  if($id){
   if($batchId>0) {
    // Editing a specific batch must not overwrite medicine-wide defaults or other batches.
    $q=$pdo->prepare('UPDATE products SET barcode=?,name=?,generic_name=?,category_id=?,reorder_level=?,status=? WHERE id=?');
    $q->execute([$barcode,$name,$generic,$cat,$reorder,$status,$id]);
   } else {
    $q=$pdo->prepare('UPDATE products SET barcode=?,name=?,generic_name=?,category_id=?,batch_no=?,expiry_date=?,purchase_price=?,selling_price=?,packs_per_carton=?,pieces_per_pack=?,wholesale_piece_price=?,wholesale_pack_price=?,wholesale_carton_price=?,retail_pack_price=?,retail_carton_price=?,reorder_level=?,supplier_id=?,status=? WHERE id=?');
    $q->execute([$barcode,$name,$generic,$cat,$batch,$expiry,$buy,$retailPiece,$ppc,$pieces,$whPiece,$whPack,$whCarton,$retPack,$retCarton,$reorder,$supplier,$status,$id]);
   }
   $q=$pdo->prepare('SELECT COUNT(*) FROM product_batches WHERE product_id=?');$q->execute([$id]);
   if((int)$q->fetchColumn()===0&&$qty>0){if($batch==='')throw new RuntimeException('Batch number required for opening stock.');$by=$_SESSION['user']['id']??null;$q=$pdo->prepare('INSERT INTO product_batches (product_id,batch_no,expiry_date,purchase_price,selling_price,quantity,supplier_id,created_by,pieces_per_pack,packs_per_carton,retail_pack_price,retail_carton_price,wholesale_piece_price,wholesale_pack_price,wholesale_carton_price) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$id,$batch,$expiry,$buy,$retailPiece,$qty,$supplier,$by,$pieces,$ppc,$retPack,$retCarton,$whPiece,$whPack,$whCarton]);$bid=(int)$pdo->lastInsertId();$q=$pdo->prepare("INSERT INTO stock_movements (product_id,batch_id,type,quantity,reference,notes,created_by) VALUES (?,?,'IN',?,'Opening Stock / Batch Repair','Initial stock batch created from medicine form.',?)");$q->execute([$id,$bid,$qty,$by]);$pdo->prepare('UPDATE products SET quantity=? WHERE id=?')->execute([$qty,$id]);}
   if($batchId>0){
      $q=$pdo->prepare('UPDATE product_batches SET batch_no=?,expiry_date=?,purchase_price=?,selling_price=?,supplier_id=?,pieces_per_pack=?,packs_per_carton=?,retail_pack_price=?,retail_carton_price=?,wholesale_piece_price=?,wholesale_pack_price=?,wholesale_carton_price=? WHERE id=? AND product_id=?');
      $q->execute([$batch,$expiry,$buy,$retailPiece,$supplier,$pieces,$ppc,$retPack,$retCarton,$whPiece,$whPack,$whCarton,$batchId,$id]);
    }
    $message='Medicine and selected batch details updated. Stock quantity was not changed; use Purchases / Stock In to change stock.';
  } else {
   $q=$pdo->prepare('INSERT INTO products (sku,barcode,name,generic_name,category_id,batch_no,expiry_date,purchase_price,selling_price,packs_per_carton,pieces_per_pack,wholesale_piece_price,wholesale_pack_price,wholesale_carton_price,retail_pack_price,retail_carton_price,quantity,reorder_level,supplier_id,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $q->execute([$r['sku'],$barcode,$name,$generic,$cat,$batch,$expiry,$buy,$retailPiece,$ppc,$pieces,$whPiece,$whPack,$whCarton,$retPack,$retCarton,$qty,$reorder,$supplier,$status]);$pid=(int)$pdo->lastInsertId();$by=$_SESSION['user']['id']??null;
   if($qty>0){$q=$pdo->prepare('INSERT INTO product_batches (product_id,batch_no,expiry_date,purchase_price,selling_price,quantity,supplier_id,created_by,pieces_per_pack,packs_per_carton,retail_pack_price,retail_carton_price,wholesale_piece_price,wholesale_pack_price,wholesale_carton_price) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$pid,$batch,$expiry,$buy,$retailPiece,$qty,$supplier,$by,$pieces,$ppc,$retPack,$retCarton,$whPiece,$whPack,$whCarton]);$bid=(int)$pdo->lastInsertId();$q=$pdo->prepare("INSERT INTO stock_movements (product_id,batch_id,type,quantity,reference,notes,created_by) VALUES (?,?,'IN',?,'Opening Stock','Initial medicine batch added.',?)");$q->execute([$pid,$bid,$qty,$by]);}
   $message='Medicine added successfully. SKU '.$r['sku'].' was generated automatically.';
  }
  $pdo->commit();flash('success',$message);redirect('products/index.php');
 } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();error_log('AIA PHARMACY product save error: '.$e->getMessage());flash('error','Medicine could not be saved. Check database and batch details, then try again.');redirect($back);}
}
$cats=$pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);$sups=$pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
function money_value($v){return e(number_format((float)$v,2,'.',''));}
?>
<h1><?= $id?'Edit':'Add' ?> Medicine</h1>
<form class="form-card" method="post">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<h3>Medicine Information</h3><div class="grid">
<p><label>SKU</label><input value="<?= e($r['sku']) ?>" readonly><small>Automatically generated.</small></p>
<p><label>Barcode</label><input name="barcode" id="barcode" value="<?= e($r['barcode']??'') ?>" placeholder="Scan or enter barcode" autocomplete="off"></p>
<p><label>Medicine Name *</label><input name="name" required value="<?= e($r['name']) ?>" placeholder="e.g. Paracetamol 500mg"></p>
<p><label>Generic Name</label><input name="generic_name" value="<?= e($r['generic_name']??'') ?>"></p>
<p><label>Category</label><select name="category_id"><option value="">-- Select --</option><?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($r['category_id']??'')==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></p>
<p><label>Status</label><select name="status"><option value="Active" <?= ($r['status']??'Active')==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= ($r['status']??'')==='Inactive'?'selected':'' ?>>Inactive</option></select></p></div>
<h3>Packaging / Unit Conversion</h3><p><small>Stock is entered in pieces. These values belong to the selected batch. Leave either field blank if its conversion is unknown; Pack/Carton sales must not be allowed for that batch until confirmed.</small></p><div class="grid">
<p><label>Packs per Carton (this batch)</label><input type="number" min="1" step="1" name="packs_per_carton" value="<?= $r['packs_per_carton']!==null && $r['packs_per_carton']!=='' ? (int)$r['packs_per_carton'] : '' ?>" placeholder="Enter if known"></p>
<p><label>Pieces per Pack (this batch)</label><input type="number" min="1" step="1" name="pieces_per_pack" value="<?= $r['pieces_per_pack']!==null && $r['pieces_per_pack']!=='' ? (int)$r['pieces_per_pack'] : '' ?>" placeholder="Enter if known"></p></div>
<h3>Purchase and Retail Prices (₦)</h3><div class="grid">
<p><label>Purchase Price per Piece</label><input type="number" min="0" step="0.01" name="purchase_price" value="<?= money_value($r['purchase_price']) ?>"></p>
<p><label>Retail Price per Piece</label><input type="number" min="0" step="0.01" name="selling_price" value="<?= money_value($r['selling_price']) ?>"></p>
<p><label>Retail Price per Pack</label><input type="number" min="0" step="0.01" name="retail_pack_price" value="<?= money_value($r['retail_pack_price']) ?>"></p>
<p><label>Retail Price per Carton</label><input type="number" min="0" step="0.01" name="retail_carton_price" value="<?= money_value($r['retail_carton_price']) ?>"></p></div>
<h3>Wholesale Prices (₦)</h3><div class="grid">
<p><label>Wholesale Price per Piece</label><input type="number" min="0" step="0.01" name="wholesale_piece_price" value="<?= money_value($r['wholesale_piece_price']) ?>"></p>
<p><label>Wholesale Price per Pack</label><input type="number" min="0" step="0.01" name="wholesale_pack_price" value="<?= money_value($r['wholesale_pack_price']) ?>"></p>
<p><label>Wholesale Price per Carton</label><input type="number" min="0" step="0.01" name="wholesale_carton_price" value="<?= money_value($r['wholesale_carton_price']) ?>"></p></div>
<h3>Batch and Stock</h3><div class="grid">
<p><label>Batch No. <?= !$id?'*':'' ?></label><input name="batch_no" value="<?= e($r['batch_no']??'') ?>" placeholder="e.g. BTH-2026-001" <?= !$id?'required':'' ?>></p>
<p><label>Expiry Date</label><input type="date" name="expiry_date" value="<?= e($r['expiry_date']??'') ?>"></p>
<p><label>Opening Stock (Pieces)</label><input type="number" min="0" step="1" name="quantity" value="<?= (int)$r['quantity'] ?>" <?= $id&&$hasBatches?'readonly':'' ?>><small><?= $id&&$hasBatches?'Selected batch stock in pieces (read-only). Use Purchases / Stock In to change stock.':'Enter individual pieces, not packs or cartons.' ?></small></p>
<p><label>Reorder Level (Pieces)</label><input type="number" min="0" step="1" name="reorder_level" value="<?= (int)$r['reorder_level'] ?>"></p>
<p><label>Supplier</label><select name="supplier_id"><option value="">-- Select --</option><?php foreach($sups as $s): ?><option value="<?= (int)$s['id'] ?>" <?= ($r['supplier_id']??'')==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></p></div>
<button type="submit">💾 Save Medicine</button> <a class="btn red" href="index.php">Cancel</a>
</form>
<script>document.getElementById('barcode')?.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();document.querySelector('input[name="name"]')?.focus();}});</script>
<?php require '../includes/footer.php'; ?>
