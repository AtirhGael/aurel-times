<?php
/**
 * Order list with status filter and pagination.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';

$pdo    = db();
$status = (string)($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 30;
$offset = ($page - 1) * $per;

$where  = '';
$args   = [];
if ($status !== '' && array_key_exists($status, ORDER_STATUSES)) {
    $where = ' WHERE status = ?';
    $args[] = $status;
}

$countSt = $pdo->prepare('SELECT COUNT(*) FROM orders' . $where);
$countSt->execute($args);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

// $per and $offset are cast ints, so interpolating them into LIMIT is safe —
// matching the existing idiom in shop.php.
$st = $pdo->prepare(
    'SELECT id, full_name, email, city, region, country, total, status, tracking_number, created_at
     FROM orders' . $where . " ORDER BY id DESC LIMIT {$per} OFFSET {$offset}"
);
$st->execute($args);
$rows = $st->fetchAll();

$adminTitle = 'Orders';
require __DIR__ . '/_layout.php';
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm btn-<?= $status === '' ? 'primary' : 'outline-secondary' ?>"
     href="<?= admin_url('orders.php') ?>">All (<?= $total ?>)</a>
  <?php foreach (ORDER_STATUSES as $k => $label): ?>
    <a class="btn btn-sm btn-<?= $status === $k ? 'primary' : 'outline-secondary' ?>"
       href="<?= admin_url('orders.php?status=' . urlencode($k)) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<section class="card-a">
  <div class="bd p-0">
    <?php if (!$rows): ?>
      <p class="text-muted small p-3 mb-0">No orders<?= $status !== '' ? ' with that status' : '' ?>.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th class="ps-3">#</th><th>Date</th><th>Customer</th><th>Destination</th>
              <th>Status</th><th>Tracking</th><th class="text-end pe-3">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $o): ?>
              <tr>
                <td class="ps-3"><a href="<?= admin_url('order.php?id=' . (int)$o['id']) ?>">#<?= (int)$o['id'] ?></a></td>
                <td class="small text-muted"><?= h(date('M j, Y', strtotime((string)$o['created_at']))) ?></td>
                <td>
                  <div><?= h((string)$o['full_name']) ?></div>
                  <div class="small text-muted"><?= h((string)$o['email']) ?></div>
                </td>
                <td class="small text-muted">
                  <?= h(trim(implode(', ', array_filter([$o['city'], $o['region'], $o['country']])))) ?: '—' ?>
                </td>
                <td><span class="badge text-bg-<?= h(order_status_class((string)$o['status'])) ?>"><?= h(order_status_label((string)$o['status'])) ?></span></td>
                <td class="small text-muted"><?= h((string)($o['tracking_number'] ?? '')) ?: '—' ?></td>
                <td class="text-end pe-3"><?= h(money($o['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($pages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
          <a class="page-link" href="<?= admin_url('orders.php?' . http_build_query(array_filter(['status' => $status, 'page' => $i]))) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
