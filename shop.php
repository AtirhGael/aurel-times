<?php
require_once __DIR__ . '/app/helpers.php';

$q      = trim((string)($_GET['q'] ?? ''));
$brand  = trim((string)($_GET['brand'] ?? ''));
$sort   = (string)($_GET['sort'] ?? 'featured');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 24;
$offset = ($page - 1) * $per;

$where  = [];
$params = [];
$brandRow = null;
if ($brand !== '') {
    $st = db()->prepare('SELECT id, name FROM brands WHERE slug = ?');
    $st->execute([$brand]);
    $brandRow = $st->fetch();
    if ($brandRow) { $where[] = 'p.brand_id = ?'; $params[] = $brandRow['id']; }
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR b.name LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderSql = match ($sort) {
    'price_asc'  => 'p.base_price ASC',
    'price_desc' => 'p.base_price DESC',
    'name'       => 'p.name ASC',
    'newest'     => 'p.id DESC',
    default      => 'p.base_price DESC',
};

$countSt = db()->prepare("SELECT COUNT(*) FROM products p JOIN brands b ON b.id = p.brand_id $whereSql");
$countSt->execute($params);
$total = (int)$countSt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));

$sql = "SELECT p.handle, p.name, p.image, p.base_price AS min_price, b.name AS brand,
               AVG(r.rating) AS rating, COUNT(DISTINCT r.id) AS review_count
        FROM products p
        JOIN brands b ON b.id = p.brand_id
        LEFT JOIN reviews r ON r.product_id = p.id
        $whereSql
        GROUP BY p.id
        ORDER BY $orderSql
        LIMIT $per OFFSET $offset";
$st = db()->prepare($sql);
$st->execute($params);
$items = $st->fetchAll();

$allBrands = db()->query(
    'SELECT b.name, b.slug, COUNT(p.id) c FROM brands b JOIN products p ON p.brand_id=b.id
     GROUP BY b.id ORDER BY b.name'
)->fetchAll();

$pageTitle = $brandRow ? $brandRow['name'] . ' Collection' : ($q !== '' ? "Search: $q" : 'Shop All Watches');

// Canonical keeps the params that change which products are listed (collection,
// page) and drops sort order and search, which only reorder or filter ad hoc. The
// header default would point every collection page at plain shop.php, which
// contradicts the collection URLs submitted in the sitemap.
$canonParams = array_filter([
    'brand' => $brandRow ? $brand : '',
    'page'  => $page > 1 ? (string)$page : '',
], fn($v) => $v !== '');
$canonical = abs_url('shop.php' . ($canonParams ? '?' . http_build_query($canonParams) : ''));
if ($q !== '') {
    $robots = 'noindex, follow';
}
require __DIR__ . '/app/header.php';

/** Build a query string preserving current filters, overriding some keys. */
function qs(array $over = []): string {
    $base = ['q' => $_GET['q'] ?? '', 'brand' => $_GET['brand'] ?? '', 'sort' => $_GET['sort'] ?? '', 'page' => $_GET['page'] ?? ''];
    $m = array_filter(array_merge($base, $over), fn($v) => $v !== '' && $v !== null);
    return $m ? ('?' . http_build_query($m)) : '';
}
?>
<div class="container py-4">
  <h1 class="section-title mb-1"><?= h($pageTitle) ?></h1>
  <p class="text-muted"><?= number_format($total) ?> timepiece<?= $total === 1 ? '' : 's' ?></p>

  <div class="row">
    <!-- Sidebar -->
    <aside class="col-lg-3 mb-4">
      <div class="p-3 rounded" style="background:var(--card);border:1px solid var(--line)">
        <h6 class="text-gold text-uppercase small">Collections</h6>
        <div style="max-height:420px;overflow:auto">
          <a href="<?= url('shop.php' . qs(['brand' => null, 'page' => null])) ?>"
             class="d-flex justify-content-between py-1 <?= $brand === '' ? 'text-gold' : '' ?>">
             All Collections</a>
          <?php foreach ($allBrands as $b): ?>
            <a href="<?= url('shop.php' . qs(['brand' => $b['slug'], 'page' => null])) ?>"
               class="d-flex justify-content-between py-1 <?= $brand === $b['slug'] ? 'text-gold' : '' ?>">
               <span><?= h($b['name']) ?></span><span class="text-muted small"><?= (int)$b['c'] ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

    <!-- Grid -->
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="get" class="d-flex gap-2">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
          <?php if ($brand !== ''): ?><input type="hidden" name="brand" value="<?= h($brand) ?>"><?php endif; ?>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
            <option value="featured"   <?= $sort === 'featured'   ? 'selected' : '' ?>>Featured</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Name A–Z</option>
            <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
          </select>
        </form>
      </div>

      <?php if (!$items): ?>
        <div class="text-center py-5 text-muted">
          <p class="fs-4 serif">No timepieces found</p>
          <a href="<?= url('shop.php') ?>" class="btn btn-outline-gold">Browse all watches</a>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($items as $p) { require __DIR__ . '/app/_card.php'; } ?>
        </div>

        <?php if ($pages > 1): ?>
          <nav class="mt-5 d-flex justify-content-center">
            <ul class="pagination">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link bg-transparent text-gold border-0" href="<?= url('shop.php' . qs(['page' => $page - 1])) ?>">&laquo;</a>
              </li>
              <?php
              $start = max(1, $page - 3); $end = min($pages, $page + 3);
              for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item">
                  <a class="page-link bg-transparent border-0 <?= $i === $page ? 'text-gold fw-bold' : 'text-muted' ?>"
                     href="<?= url('shop.php' . qs(['page' => $i])) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link bg-transparent text-gold border-0" href="<?= url('shop.php' . qs(['page' => $page + 1])) ?>">&raquo;</a>
              </li>
            </ul>
          </nav>
          <p class="text-center text-muted small">Page <?= $page ?> of <?= $pages ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
