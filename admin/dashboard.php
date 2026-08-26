<?php
require __DIR__.'/../includes/bootstrap.php'; require_login('admin');
$stats=[
 'products'=>(int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
 'stock'=>(int)db()->query('SELECT COALESCE(SUM(stock_quantity),0) FROM products')->fetchColumn(),
 'today'=>(float)db()->query('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE DATE(created_at)=CURDATE()')->fetchColumn(),
 'orders'=>(int)db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
];
$low=(int)setting('low_stock_threshold','5');
$stmt=db()->prepare('SELECT name,sku,stock_quantity FROM products WHERE stock_quantity<=? ORDER BY stock_quantity LIMIT 8');$stmt->execute([$low]);$lowProducts=$stmt->fetchAll();
$recent=db()->query('SELECT o.id,o.receipt_number,o.grand_total,o.created_at,u.name cashier FROM orders o JOIN users u ON u.id=o.cashier_id ORDER BY o.id DESC LIMIT 8')->fetchAll();
$pageTitle='Dashboard';require __DIR__.'/../includes/header.php';?>
<h1 class="h3 mb-4">Dashboard</h1><div class="row g-3 mb-4">
<?php foreach([['Products',$stats['products'],'primary'],['Total Stock',$stats['stock'],'success'],["Today's Sales",money($stats['today']),'warning'],['Total Orders',$stats['orders'],'info']] as $s):?><div class="col-6 col-lg-3"><div class="card stat p-3 border-<?=e($s[2])?>"><div class="text-muted small"><?=e($s[0])?></div><div class="h3 mb-0"><?=e($s[1])?></div></div></div><?php endforeach;?></div>
<div class="row g-4"><div class="col-lg-5"><div class="card p-3"><h2 class="h5">Low-stock Products</h2><div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Stock</th></tr></thead><tbody><?php foreach($lowProducts as $p):?><tr><td><?=e($p['name'])?></td><td><?=e($p['sku'])?></td><td><span class="badge bg-danger"><?=e($p['stock_quantity'])?></span></td></tr><?php endforeach;?><?php if(!$lowProducts):?><tr><td colspan="3" class="text-muted">No low-stock products.</td></tr><?php endif;?></tbody></table></div></div></div>
<div class="col-lg-7"><div class="card p-3"><h2 class="h5">Recent Orders</h2><div class="table-responsive"><table class="table"><thead><tr><th>Receipt</th><th>Cashier</th><th>Total</th><th>Date</th></tr></thead><tbody><?php foreach($recent as $o):?><tr><td><a href="<?=e(url('cashier/receipt.php?id='.$o['id']))?>"><?=e($o['receipt_number'])?></a></td><td><?=e($o['cashier'])?></td><td><?=money($o['grand_total'])?></td><td><?=e($o['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
<?php require __DIR__.'/../includes/footer.php';
