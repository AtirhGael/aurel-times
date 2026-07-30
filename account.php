<?php
require_once __DIR__ . '/app/helpers.php';
$u = current_user();
if (!$u) { header('Location: ' . url('login.php')); exit; }

$orders = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([$u['id']]);
$orders = $orders->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $st->execute($ids);
    foreach ($st->fetchAll() as $it) { $itemsByOrder[$it['order_id']][] = $it; }
}

$pageTitle = 'My Account';
require __DIR__ . '/app/header.php';
?>
<div class="container py-4">
  <h1 class="section-title mb-4">My Account</h1>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
        <div class="serif fs-4"><?= h($u['name']) ?></div>
        <div class="text-muted small mb-3"><?= h($u['email']) ?></div>
        <a href="<?= url('logout.php') ?>" class="btn btn-outline-gold btn-sm w-100">Sign Out</a>
      </div>
    </div>
    <div class="col-lg-8">
      <h4 class="serif mb-3">Order History</h4>
      <?php if (!$orders): ?>
        <p class="text-muted">No orders yet. <a class="text-gold" href="<?= url('shop.php') ?>">Start shopping →</a></p>
      <?php else: foreach ($orders as $o): ?>
        <div class="p-3 mb-3 rounded" style="background:var(--card);border:1px solid var(--line)">
          <div class="d-flex justify-content-between">
            <strong>Order #<?= (int)$o['id'] ?></strong>
            <span class="badge" style="background:var(--gold);color:#151515"><?= h(ucfirst($o['status'])) ?></span>
          </div>
          <div class="text-muted small mb-2"><?= h($o['created_at']) ?></div>
          <?php foreach (($itemsByOrder[$o['id']] ?? []) as $it): ?>
            <div class="d-flex justify-content-between small">
              <span><?= h(mb_strimwidth($it['name'], 0, 46, '…')) ?> <span class="text-muted">(<?= h($it['grade']) ?>) ×<?= (int)$it['qty'] ?></span></span>
              <span><?= money($it['price'] * $it['qty']) ?></span>
            </div>
          <?php endforeach; ?>
          <hr style="border-color:var(--line);margin:.5rem 0">
          <div class="d-flex justify-content-between"><span class="text-muted">Total</span><span class="price"><?= money($o['total']) ?></span></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
