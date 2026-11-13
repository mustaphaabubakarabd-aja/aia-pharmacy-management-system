<?php
$title='Categories';
require '../includes/header.php';
$pdo=db();

// Seed standard pharmacy categories only when the table is empty.
if((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()===0){
    $defaults=['Tablets','Capsules','Syrups','Suspensions','Injections','Creams','Ointments','Drops','Suppositories','Vitamins & Supplements','Medical Devices','Other'];
    $st=$pdo->prepare('INSERT IGNORE INTO categories(name) VALUES(?)');
    foreach($defaults as $name){$st->execute([$name]);}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $action=$_POST['action']??'';
    try{
        if($action==='add'){
            $name=trim($_POST['name']??'');
            if($name==='') throw new Exception('Category name is required.');
            $pdo->prepare('INSERT INTO categories(name) VALUES(?)')->execute([$name]);
            flash('success','Category added successfully.');
        }elseif($action==='edit'){
            $id=(int)($_POST['id']??0);
            $name=trim($_POST['name']??'');
            if(!$id || $name==='') throw new Exception('Category details are required.');
            $pdo->prepare('UPDATE categories SET name=? WHERE id=?')->execute([$name,$id]);
            flash('success','Category updated successfully.');
        }elseif($action==='delete'){
            $id=(int)($_POST['id']??0);
            if(!$id) throw new Exception('Invalid category.');
            $check=$pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
            $check->execute([$id]);
            if((int)$check->fetchColumn()>0) throw new Exception('This category is assigned to one or more medicines and cannot be deleted.');
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            flash('success','Category deleted successfully.');
        }
    }catch(PDOException $e){
        flash('error',$e->getCode()==='23000'?'That category already exists.':'Unable to save the category.');
    }catch(Exception $e){
        flash('error',$e->getMessage());
    }
    redirect('categories/index.php');
}

$editId=(int)($_GET['edit']??0);
$edit=null;
if($editId){
    $st=$pdo->prepare('SELECT * FROM categories WHERE id=?');
    $st->execute([$editId]);
    $edit=$st->fetch();
}
$rows=$pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
?>
<h1>Medicine Categories</h1>
<div class="form-card">
    <h2><?=$edit?'Edit Category':'Add Category'?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="action" value="<?=$edit?'edit':'add'?>">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif; ?>
        <div class="grid">
            <p><label>Category Name *</label><input name="name" required value="<?=e($edit['name']??'')?>" placeholder="e.g. Tablets"></p>
        </div>
        <button><?=$edit?'Update Category':'Add Category'?></button>
        <?php if($edit): ?><a class="btn red" href="index.php">Cancel</a><?php endif; ?>
    </form>
</div>

<table>
<tr><th>#</th><th>Category</th><th>Medicines</th><th>Action</th></tr>
<?php foreach($rows as $i=>$r): ?>
<tr>
    <td><?=$i+1?></td>
    <td><?=e($r['name'])?></td>
    <td><?=$r['product_count']?></td>
    <td class="actions">
        <a class="btn orange" href="index.php?edit=<?=$r['id']?>">Edit</a>
        <form method="post" onsubmit="return confirm('Delete this category?');" style="display:inline">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$r['id']?>">
            <button class="btn red" type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
<?php require '../includes/footer.php';
