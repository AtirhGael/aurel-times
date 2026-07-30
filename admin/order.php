<?php
/**
 * Single order: line items, shipping details, status / tracking / note update.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';
require_once __DIR__ . '/../app/mail.php';

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (csrf_check() && $id > 0) {
        $status   = (string)($_POST['status'] ?? '');
        $tracking = trim((string)($_POST['tracking_number'] ?? ''));
        $note     = trim((string)($_POST['admin_note'] ?? ''));

        // orders.status is a free-form VARCHAR and account.php renders it back
        // to the customer, so validate against the allowlist rather than
        // trusting the posted value.
        if (!array_key_exists($status, ORDER_STATUSES)) {
            flash('That is not a valid order status.');
        } else {
            $pdo->prepare(
                'UPDATE orders SET status = ?, tracking_number = ?, admin_note = ? WHERE id = ?'
            )->execute([$status, $tracking !== '' ? mb_substr($tracking, 0, 120) : null, $note, $id]);

            if (!empty($_POST['notify']) && $status === 'shipped' && $tracking !== '') {
                $o = $pdo->prepare('SELECT email, full_name FROM orders WHERE id = ?');
                $o->execute([$id]);
                if ($cust = $o->fetch()) {
                    send_mail(
                        (string)$cust['email'],
                        'Your ' . setting('site_name') . ' order #' . str_pad((string)$id, 6, '0', STR_PAD_LEFT) . ' has shipped',
                        "Hi " . $cust['full_name'] . ",\r\n\r\n"
                        . "Your order has been dispatched.\r\n\r\n"
                        . "Tracking number: {$tracking}\r\n"
                        . "Carrier(s): " . setting('ship_carriers') . "\r\n\r\n"
                        . "Delivery typically takes " . delivery_estimate() . ".\r\n\r\n"
                        . "Questions? " . setting('support_email') . " / " . setting('phone') . "\r\n",
                        setting('support_email')
                    );
                }
            }
            flash('Order #' . $id . ' updated.');
        }
    }
    header('Location: ' . admin_url('order.php?id=' . $id));
    exit;
}

$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    exit('<h1>404 Not Found</h1>');
}

$itemsSt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$itemsSt->execute([$id]);
$items = $itemsSt->fetchAll();

$subtotal = (float)($order['subtotal'] ?? 0);
if ($subtotal <= 0) {
    // Orders placed before shipping was itemised store only a total.
    foreach ($items as $it) {
        $subtotal += (float)$it['price'] * (int)$it['qty'];
    }
}

$adminTitle = 'Order #' . (int)$order['id'];
require __DIR__ . '/_layout.php';
?>

<div class="row g-4">
  <div class="col-lg-7">
    <section class="card-a mb-4">
      <div class="hd">Items</div>
      <div class="bd p-0">
        <table class="table">
          <thead><tr><th class="ps-3">Item</th><th>Grade</th><th class="text-center">Qty</th><th class="text-end pe-3">Line</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td class="ps-3">
                  <?php if (!empty($it['product_id'])): ?>
                    <a href="<?= url('product.php?handle=') ?>" onclick="return false" style="pointer-events:none;color:inherit">
                      <?= h((string)$it['name']) ?>
                    </a>
                  <?php else: ?>
                    <?= h((string)$it['name']) ?>
                  <?php endif; ?>
                  <div class="small text-muted"><?= h(money($it['price'])) ?> each</div>
                </td>
                <td class="small text-muted"><?= h((string)($it['grade'] ?? '')) ?: '—' ?></td>
                <td class="text-center"><?= (int)$it['qty'] ?></td>
                <td class="text-end pe-3"><?= h(money((float)$it['price'] * (int)$it['qty'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot style="border-top:2px solid var(--line)">
            <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end pe-3"><?= h(money($subtotal)) ?></td></tr>
            <tr><td colspan="3" class="text-end">Shipping</td>
                <td class="text-end pe-3"><?= (float)($order['shipping_cost'] ?? 0) > 0 ? h(money($order['shipping_cost'])) : 'Free' ?></td></tr>
            <tr><td colspan="3" class="text-end fw-bold">Total</td>
                <td class="text-end pe-3 fw-bold"><?= h(money($order['total'])) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section class="card-a">
      <div class="hd">Customer &amp; delivery</div>
      <div class="bd">
        <div class="row g-3 small">
          <div class="col-md-6">
            <div class="text-muted">Name</div><div class="mb-2"><?= h((string)$order['full_name']) ?></div>
            <div class="text-muted">Email</div>
            <div class="mb-2"><a href="mailto:<?= h((string)$order['email']) ?>"><?= h((string)$order['email']) ?></a></div>
            <div class="text-muted">Phone</div>
            <div><?= h((string)($order['phone'] ?? '')) ?: '—' ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-muted">Shipping address</div>
            <address style="font-style:normal;line-height:1.7">
              <?= nl2br(h((string)$order['address'])) ?><br>
              <?= h(trim(implode(', ', array_filter([
                  $order['city'] ?? '',
                  trim(((string)($order['region'] ?? '')) . ' ' . ((string)($order['postcode'] ?? ''))),
                  $order['country'] ?? '',
              ])))) ?>
            </address>
            <div class="text-muted">Placed</div>
            <div><?= h(date('F j, Y \a\t g:ia', strtotime((string)$order['created_at']))) ?></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <div class="col-lg-5">
    <section class="card-a">
      <div class="hd">Fulfilment</div>
      <div class="bd">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php foreach (ORDER_STATUSES as $k => $label): ?>
                <option value="<?= h($k) ?>" <?= (string)$order['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Tracking number</label>
            <input class="form-control" name="tracking_number" value="<?= h((string)($order['tracking_number'] ?? '')) ?>">
            <div class="form-text">Carriers: <?= h(setting('ship_carriers')) ?></div>
          </div>

          <div class="chk-row mb-3">
            <input class="form-check-input mt-1" type="checkbox" id="notify" name="notify" value="1">
            <label class="form-label mb-0" for="notify">
              Email the customer their tracking number
              <span class="d-block form-text text-muted fw-normal">Only sent when the status is "Shipped" and a tracking number is set.</span>
            </label>
          </div>

          <div class="mb-3">
            <label class="form-label">Internal note</label>
            <textarea class="form-control" name="admin_note" rows="4"><?= h((string)($order['admin_note'] ?? '')) ?></textarea>
            <div class="form-text">Never shown to the customer.</div>
          </div>

          <button class="btn btn-primary w-100">Update order</button>
        </form>
      </div>
    </section>

    <a class="btn btn-link mt-2" href="<?= admin_url('orders.php') ?>">← Back to orders</a>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
