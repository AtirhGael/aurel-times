<?php
declare(strict_types=1);
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/pages.php';
require_once __DIR__ . '/app/mail.php';

$cart = cart();
if (!$cart && !isset($_GET['done'])) {
    flash('Your cart is empty.');
    header('Location: ' . url('cart.php'));
    exit;
}

$errors = [];
$u = current_user();

$subtotal = cart_total();
$shipping = shipping_cost($subtotal);
$total    = $subtotal + $shipping;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired. Please try again.';
    }
    $name     = trim((string)($_POST['name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $phone    = trim((string)($_POST['phone'] ?? ''));
    $address  = trim((string)($_POST['address'] ?? ''));
    $city     = trim((string)($_POST['city'] ?? ''));
    $region   = trim((string)($_POST['region'] ?? ''));
    $postcode = trim((string)($_POST['postcode'] ?? ''));
    $country  = trim((string)($_POST['country'] ?? ''));

    if ($name === '')                                $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'A valid email is required.';
    if ($address === '')                             $errors[] = 'Street address is required.';
    if ($city === '')                                $errors[] = 'City is required.';
    if ($postcode === '')                            $errors[] = 'ZIP / postal code is required.';
    if ($country === '')                             $errors[] = 'Country is required.';
    if (!$cart)                                      $errors[] = 'Your cart is empty.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO orders (user_id, email, full_name, phone, address, city, region,
                                     postcode, country, subtotal, shipping_cost, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $u['id'] ?? null, $email, $name, $phone !== '' ? $phone : null,
                $address, $city, $region, $postcode, $country,
                $subtotal, $shipping, $total, 'pending',
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $it = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, variant_id, name, grade, price, qty)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($cart as $line) {
                $it->execute([
                    $orderId, $line['product_id'], $line['variant_id'],
                    $line['name'], $line['grade'], $line['price'], $line['qty'],
                ]);
            }
            $pdo->commit();

            // Proof of ownership for the confirmation screen. Without this,
            // checkout.php?done=<id> would hand any order's name, email and
            // full postal address to anyone who guessed the id.
            $_SESSION['own_orders'] = array_slice(
                array_merge((array)($_SESSION['own_orders'] ?? []), [$orderId]), -20
            );

            $items = $it = null;
            $itemsSt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $itemsSt->execute([$orderId]);
            $orderRow = ['id' => $orderId, 'full_name' => $name, 'address' => $address,
                         'city' => $city, 'region' => $region, 'postcode' => $postcode,
                         'country' => $country, 'subtotal' => $subtotal,
                         'shipping_cost' => $shipping, 'total' => $total,
                         'created_at' => date('c')];
            $body = order_confirmation_body($orderRow, $itemsSt->fetchAll());
            $subject = setting('site_name') . ' order #' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
            $mailed  = send_mail($email, $subject, $body, setting('support_email'));
            if (setting('orders_email') !== '') {
                send_mail(setting('orders_email'), '[new order] ' . $subject, $body, $email);
            }
            $_SESSION['order_mailed'] = $mailed;

            cart_clear();
            header('Location: ' . url('checkout.php?done=' . $orderId));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // The exception text can carry table names, column names and SQL
            // fragments. Log it; show the customer something actionable.
            error_log('[checkout] ' . $e->getMessage());
            $errors[] = 'We could not place your order just now. Nothing has been charged — '
                      . 'please try again, or contact ' . setting('support_email') . ' and we will place it for you.';
        }
    }
}

/* ---- Confirmation screen ------------------------------------------------ */
if (isset($_GET['done'])) {
    $oid   = (int)$_GET['done'];
    $owned = in_array($oid, (array)($_SESSION['own_orders'] ?? []), true);

    $o = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $o->execute([$oid]);
    $order = $o->fetch() ?: null;

    // Show the order only to the session that placed it or to the signed-in
    // account that owns it. Anyone else gets a 404.
    if ($order && !$owned && (int)($order['user_id'] ?? 0) !== (int)($u['id'] ?? -1)) {
        $order = null;
    }
    if (!$order) {
        http_response_code(404);
        $pageTitle = 'Order not found';
        $robots    = 'noindex, nofollow';
        require __DIR__ . '/app/header.php';
        ?>
        <div class="container py-5 text-center" style="max-width:600px">
          <h1 class="section-title">Order not found</h1>
          <p class="section-sub">
            We could not find that order for this session. If you have just placed an order,
            check your email for the confirmation, or sign in to view your order history.
          </p>
          <a class="btn btn-gold px-4" href="<?= url('account.php') ?>">My orders</a>
          <a class="btn btn-outline-dark2 px-4 ms-2" href="<?= url('contact.php') ?>">Contact us</a>
        </div>
        <?php
        require __DIR__ . '/app/footer.php';
        exit;
    }

    $mailed = (bool)($_SESSION['order_mailed'] ?? false);
    unset($_SESSION['order_mailed']);

    $pageTitle = 'Order confirmed';
    $robots    = 'noindex, nofollow';
    require __DIR__ . '/app/header.php';
    ?>
    <div class="container py-5" style="max-width:640px">
      <div class="text-center">
        <div class="text-gold" style="font-size:3rem">✓</div>
        <h1 style="font-weight:600">Thank you, <?= h(explode(' ', (string)$order['full_name'])[0]) ?>!</h1>
        <p class="lead text-muted">
          Order <strong class="text-gold">#<?= str_pad((string)$oid, 6, '0', STR_PAD_LEFT) ?></strong> has been received.
        </p>
      </div>

      <div class="p-4 rounded mt-4" style="background:var(--card);border:1px solid var(--line)">
        <h2 class="h6 text-uppercase mb-3" style="letter-spacing:1.5px">What happens next</h2>
        <ol class="small" style="line-height:1.9;color:#3a3a3e">
          <li>We confirm your order and prepare it for dispatch within
              <strong><?= setting_int('ship_processing_days') ?> business day(s)</strong>.</li>
          <li>You receive a tracking number by email as soon as the carrier collects it.</li>
          <li>Delivery typically takes <strong><?= h(delivery_estimate()) ?></strong>.</li>
        </ol>

        <hr>
        <div class="d-flex justify-content-between small"><span class="text-muted">Subtotal</span>
          <span><?= money($order['subtotal'] ?? $order['total']) ?></span></div>
        <div class="d-flex justify-content-between small"><span class="text-muted">Shipping</span>
          <span><?= (float)($order['shipping_cost'] ?? 0) > 0 ? money($order['shipping_cost']) : 'Free' ?></span></div>
        <div class="d-flex justify-content-between mt-2" style="font-size:1.1rem">
          <strong>Total</strong><strong class="price"><?= money($order['total']) ?></strong>
        </div>

        <p class="small text-muted mt-3 mb-0">
          <?php if ($mailed): ?>
            A confirmation has been emailed to <?= h((string)$order['email']) ?>.
          <?php else: ?>
            Keep this order number for your records — we were unable to send the confirmation email to
            <?= h((string)$order['email']) ?>. Our team has your order and will be in touch;
            you can also reach us at <a class="text-gold" href="mailto:<?= h(setting('support_email')) ?>"><?= h(setting('support_email')) ?></a>.
          <?php endif; ?>
        </p>
      </div>

      <div class="text-center mt-4">
        <a href="<?= url('shop.php') ?>" class="btn btn-gold px-4">Continue shopping</a>
        <?php if ($u): ?>
          <a href="<?= url('account.php') ?>" class="btn btn-outline-dark2 px-4 ms-2">View orders</a>
        <?php endif; ?>
      </div>
      <p class="text-center small text-muted mt-3">
        Changed your mind? See our <a class="text-gold" href="<?= h(page_url('return-policy')) ?>">Return Policy</a>
        — orders can be cancelled free of charge any time before dispatch.
      </p>
    </div>
    <?php
    require __DIR__ . '/app/footer.php';
    exit;
}

$pageTitle = 'Checkout';
$robots    = 'noindex, nofollow';
require __DIR__ . '/app/header.php';
?>
<div class="container py-4">
  <h1 class="section-title mb-4">Checkout</h1>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <form method="post" class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <h5 class="mb-3" style="font-weight:600">Shipping details</h5>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small text-muted">Full name *</label>
            <input name="name" class="form-control" required
                   value="<?= h($_POST['name'] ?? ($u['name'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Email *</label>
            <input name="email" type="email" class="form-control" required
                   value="<?= h($_POST['email'] ?? ($u['email'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Phone <span class="text-muted">(for delivery)</span></label>
            <input name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Country *</label>
            <input name="country" class="form-control" required
                   value="<?= h($_POST['country'] ?? setting('country')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted">Street address *</label>
            <textarea name="address" class="form-control" rows="2" required><?= h($_POST['address'] ?? '') ?></textarea>
          </div>
          <div class="col-md-5">
            <label class="form-label small text-muted">City *</label>
            <input name="city" class="form-control" required value="<?= h($_POST['city'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">State / region</label>
            <input name="region" class="form-control" value="<?= h($_POST['region'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted">ZIP / postal *</label>
            <input name="postcode" class="form-control" required value="<?= h($_POST['postcode'] ?? '') ?>">
          </div>
        </div>

        <p class="small text-muted mt-3">
          No card details are taken on this page. We confirm your order by email and arrange payment
          before anything ships — nothing is charged now. By ordering you agree to our
          <a class="text-gold" href="<?= h(page_url('terms-of-service')) ?>">Terms of Service</a>.
        </p>
        <button class="btn btn-gold btn-lg w-100">Place order · <?= money($total) ?></button>
      </form>
    </div>

    <div class="col-lg-5">
      <div class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
        <h5 class="mb-3" style="font-weight:600">Order summary</h5>
        <?php foreach ($cart as $line): ?>
          <div class="d-flex justify-content-between small mb-2">
            <span><?= h(mb_strimwidth($line['name'], 0, 34, '…')) ?> <span class="text-muted">×<?= (int)$line['qty'] ?></span></span>
            <span><?= money($line['price'] * $line['qty']) ?></span>
          </div>
        <?php endforeach; ?>
        <hr style="border-color:var(--line)">
        <div class="d-flex justify-content-between small mb-1">
          <span class="text-muted">Subtotal</span><span><?= money($subtotal) ?></span>
        </div>
        <div class="d-flex justify-content-between small mb-2">
          <span class="text-muted">Shipping</span>
          <span class="<?= $shipping <= 0 ? 'text-gold' : '' ?>"><?= $shipping > 0 ? money($shipping) : 'Free' ?></span>
        </div>
        <?php if ($shipping > 0 && setting_bool('free_shipping_enabled') && setting_float('free_shipping_threshold') > 0): ?>
          <p class="small text-muted">
            Add <?= money(setting_float('free_shipping_threshold') - $subtotal) ?> more for free shipping.
          </p>
        <?php endif; ?>
        <hr style="border-color:var(--line)">
        <div class="d-flex justify-content-between fs-5"><strong>Total</strong><strong class="price"><?= money($total) ?></strong></div>

        <ul class="list-unstyled small text-muted mt-3 mb-0" style="line-height:1.9">
          <li>Insured delivery in <?= h(delivery_estimate()) ?></li>
          <li><?= setting_int('return_window_days') ?>-day returns ·
              <a class="text-gold" href="<?= h(page_url('return-policy')) ?>">policy</a></li>
          <li><?= setting_int('warranty_months') ?>-month international warranty</li>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
