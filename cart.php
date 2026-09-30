<?php
require_once __DIR__ . '/app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { flash('Session expired, please try again.'); header('Location: ' . url('cart.php')); exit; }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $vid = (int)($_POST['variant_id'] ?? 0);
        $qty = max(1, min(20, (int)($_POST['qty'] ?? 1)));
        if ($vid > 0) { cart_add($vid, $qty); flash('Added to cart.'); }
    } elseif ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $vid => $qty) {
            cart_set((int)$vid, max(0, min(20, (int)$qty)));
        }
        flash('Cart updated.');
    } elseif ($action === 'remove') {
        cart_remove((int)($_POST['variant_id'] ?? 0));
        flash('Item removed.');
    } elseif ($action === 'clear') {
        cart_clear();
    }
    header('Location: ' . url('cart.php'));
    exit;
}

$cart  = cart();
$total = cart_total();
$pageTitle = 'Shopping Cart';
require __DIR__ . '/app/header.php';
?>
<div class="container py-4">
  <h1 class="section-title mb-4">Your Cart</h1>

  <?php if (!$cart): ?>
    <div class="text-center py-5">
      <p class="serif fs-3 text-muted">Your cart is empty</p>
      <a href="<?= url('shop.php') ?>" class="btn btn-gold mt-2">Discover Timepieces</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <div class="col-lg-8">
        <form method="post" action="<?= url('cart.php') ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <?php foreach ($cart as $line): ?>
            <div class="d-flex gap-3 align-items-center p-3 mb-3 rounded" style="background:var(--card);border:1px solid var(--line)">
              <img src="<?= h(img_or_placeholder($line['image'])) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:6px;background:#fff"
                   onerror="this.src='https://placehold.co/160x160/1a1a1a/c9a24b?text=Watch'">
              <div class="flex-grow-1">
                <a href="<?= url('product.php?handle=' . urlencode($line['handle'])) ?>" class="d-block"><?= h($line['name']) ?></a>
                <?php if ($line['grade'] !== '' && $line['grade'] !== 'Standard'): ?>
                  <div class="text-muted small"><?= h($line['grade']) ?></div>
                <?php endif; ?>
                <div class="price"><?= money($line['price']) ?></div>
              </div>
              <input type="number" name="qty[<?= (int)$line['variant_id'] ?>]" value="<?= (int)$line['qty'] ?>"
                     min="0" max="20" class="form-control" style="width:80px">
              <div class="text-end" style="width:100px">
                <div class="price"><?= money($line['price'] * $line['qty']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <button class="btn btn-outline-gold btn-sm">Update Cart</button>
        </form>
        <form method="post" action="<?= url('cart.php') ?>" class="mt-2 d-inline">
          <input type="hidden" name="action" value="clear">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <button class="btn btn-outline-secondary btn-sm">Clear Cart</button>
        </form>
      </div>

      <div class="col-lg-4">
        <div class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
          <h5 class="serif mb-3">Order Summary</h5>
          <?php
          // Shipping comes from the shipping settings via one shared helper, so
          // the cart, checkout and the shipping policy page can never disagree.
          $ship  = shipping_cost($total);
          $grand = $total + $ship;
          ?>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span><?= money($total) ?></span></div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Shipping</span>
            <span class="<?= $ship <= 0 ? 'text-gold' : '' ?>"><?= $ship > 0 ? money($ship) : 'Free' ?></span>
          </div>
          <?php if ($ship > 0 && setting_bool('free_shipping_enabled') && setting_float('free_shipping_threshold') > 0): ?>
            <p class="small text-muted mb-2">Add <?= money(setting_float('free_shipping_threshold') - $total) ?> more for free shipping.</p>
          <?php endif; ?>
          <hr style="border-color:var(--line)">
          <div class="d-flex justify-content-between mb-3 fs-5"><strong>Total</strong><strong class="price"><?= money($grand) ?></strong></div>
          <a href="<?= url('checkout.php') ?>" class="btn btn-gold w-100">Proceed to Checkout</a>
          <a href="<?= url('shop.php') ?>" class="btn btn-outline-gold w-100 mt-2">Continue Shopping</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
