<?php
require_once __DIR__ . '/app/helpers.php';

$handle = trim((string)($_GET['handle'] ?? ''));
if ($handle === '') { header('Location: ' . url('shop.php')); exit; }

$st = db()->prepare(
    'SELECT p.*, b.name AS brand, b.slug AS brand_slug
     FROM products p JOIN brands b ON b.id = p.brand_id
     WHERE p.handle = ?'
);
$st->execute([$handle]);
$product = $st->fetch();
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/app/header.php';
    echo '<div class="container py-5 text-center"><h1 class="serif">404 — Timepiece not found</h1>
          <a class="btn btn-outline-gold mt-3" href="' . url('shop.php') . '">Back to shop</a></div>';
    require __DIR__ . '/app/footer.php';
    exit;
}
$pid = (int)$product['id'];

$images = db()->prepare('SELECT url FROM product_images WHERE product_id = ? ORDER BY position');
$images->execute([$pid]);
$images = $images->fetchAll(PDO::FETCH_COLUMN);
if (!$images && $product['image']) { $images = [$product['image']]; }

$variants = db()->prepare('SELECT id, grade, price, sku, in_stock FROM variants WHERE product_id = ? ORDER BY price');
$variants->execute([$pid]);
$variants = $variants->fetchAll();

$reviews = db()->prepare('SELECT author, rating, body, created_at FROM reviews WHERE product_id = ? ORDER BY id DESC LIMIT 20');
$reviews->execute([$pid]);
$reviews = $reviews->fetchAll();

$ratingRow = db()->prepare('SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE product_id = ?');
$ratingRow->execute([$pid]);
$ratingRow = $ratingRow->fetch();
$avg = $ratingRow['a'] ? round((float)$ratingRow['a'], 1) : 0;

$related = db()->prepare(
    "SELECT p.handle, p.name, p.image, p.base_price AS min_price, b.name AS brand
     FROM products p JOIN brands b ON b.id = p.brand_id
     WHERE p.brand_id = ? AND p.id <> ? ORDER BY RAND() LIMIT 4"
);
$related->execute([$product['brand_id'], $pid]);
$related = $related->fetchAll();

$pageTitle       = $product['name'];
$metaDescription = trim(mb_substr(strip_tags((string)$product['description']), 0, 155));
if ($metaDescription === '') {
    $metaDescription = $product['brand'] . ' ' . $product['name'] . ' — insured worldwide delivery in '
                     . delivery_estimate() . ', ' . setting_int('return_window_days') . '-day returns.';
}
$canonical = abs_url('product.php?handle=' . rawurlencode((string)$product['handle']));
$ogImage   = (string)($images[0] ?? '');
$ogType    = 'product';

require_once __DIR__ . '/app/_schema.php';
require_once __DIR__ . '/app/pages.php';

// aggregateRating is emitted only when genuine reviews exist. Marking up
// generated review text as a rating misrepresents the product to every search
// engine that reads it — see admin/reviews.php.
$reviewStats = ((int)$ratingRow['c'] > 0)
    ? ['avg' => (float)$avg, 'count' => (int)$ratingRow['c']]
    : null;

$jsonLd = [
    schema_product(
        $product + ['brand_name' => $product['brand']],
        $variants,
        $reviewStats
    ),
    schema_breadcrumb([
        ['name' => 'Home',              'url' => abs_url('index.php')],
        ['name' => 'Shop',              'url' => abs_url('shop.php')],
        ['name' => (string)$product['brand'], 'url' => abs_url('shop.php?brand=' . rawurlencode((string)$product['brand_slug']))],
        ['name' => (string)$product['name'],  'url' => $canonical],
    ]),
];

require __DIR__ . '/app/header.php';
?>
<div class="container py-4">
  <nav class="small text-muted mb-3">
    <a href="<?= url('index.php') ?>">Home</a> /
    <a href="<?= url('shop.php') ?>">Shop</a> /
    <a href="<?= url('shop.php?brand=' . urlencode($product['brand_slug'])) ?>"><?= h($product['brand']) ?></a>
  </nav>

  <div class="row g-4">
    <!-- Gallery -->
    <div class="col-lg-6">
      <div class="rounded overflow-hidden mb-3" style="background:#fff">
        <img id="mainImg" src="<?= h(img_or_placeholder($images[0] ?? '')) ?>" class="w-100"
             style="aspect-ratio:1;object-fit:cover"
             onerror="this.src='https://placehold.co/700x700/1a1a1a/c9a24b?text=<?= urlencode($product['brand']) ?>'"
             alt="<?= h($product['name']) ?>">
      </div>
      <?php if (count($images) > 1): ?>
      <div class="d-flex gap-2 flex-wrap">
        <?php foreach (array_slice($images, 0, 8) as $im): ?>
          <img src="<?= h($im) ?>" onclick="document.getElementById('mainImg').src=this.src"
               style="width:70px;height:70px;object-fit:cover;cursor:pointer;border:1px solid var(--line);border-radius:6px;background:#fff"
               onerror="this.style.display='none'">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="col-lg-6">
      <div class="text-uppercase text-muted small" style="letter-spacing:2px"><?= h($product['brand']) ?></div>
      <h1 class="serif mb-2" style="font-size:2rem"><?= h($product['name']) ?></h1>
      <?php if ($ratingRow['c'] > 0): ?>
        <div class="mb-2"><span class="rating"><?= str_repeat('★', (int)round($avg)) . str_repeat('☆', 5 - (int)round($avg)) ?></span>
          <span class="text-muted small"><?= $avg ?> · <?= (int)$ratingRow['c'] ?> reviews</span></div>
      <?php endif; ?>
      <?php if ($product['mpn']): ?><div class="text-muted small mb-3">Ref. <?= h($product['mpn']) ?></div><?php endif; ?>

      <form method="post" action="<?= url('cart.php') ?>" class="my-4">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label class="text-gold text-uppercase small d-block mb-2">Select Grade</label>
        <div class="mb-3">
          <?php foreach ($variants as $i => $v): ?>
            <label class="d-flex justify-content-between align-items-center p-3 mb-2 rounded"
                   style="background:var(--card);border:1px solid var(--line);cursor:pointer">
              <span>
                <input type="radio" name="variant_id" value="<?= (int)$v['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> class="me-2">
                <span class="fw-500"><?= h($v['grade']) ?></span>
                <?php if (!$v['in_stock']): ?><span class="badge bg-secondary ms-2">Out of stock</span><?php endif; ?>
              </span>
              <span class="price fs-5"><?= money($v['price']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <input type="number" name="qty" value="1" min="1" max="20" class="form-control" style="width:90px">
          <button class="btn btn-gold btn-lg flex-grow-1">Add to Cart</button>
        </div>
      </form>

      <?php if ($product['description']): ?>
        <div class="mt-4">
          <h5 class="serif text-gold">Details</h5>
          <p class="text-muted small" style="line-height:1.7"><?= h($product['description']) ?></p>
        </div>
      <?php endif; ?>
      <?php
      // Driven by settings. These previously read "14-day return policy" while
      // the header and homepage both promised 30 days — a contradiction a
      // customer notices immediately.
      ?>
      <ul class="list-unstyled small text-muted mt-3" style="line-height:1.9">
        <li>✓ Fully insured shipping · <?= h(delivery_estimate()) ?></li>
        <?php if (setting_int('warranty_months') > 0): ?>
          <li>✓ <?= setting_int('warranty_months') ?>-month international warranty</li>
        <?php endif; ?>
        <?php if (setting_int('return_window_days') > 0): ?>
          <li>✓ <?= setting_int('return_window_days') ?>-day returns ·
              <a class="text-gold" href="<?= h(page_url('return-policy')) ?>">see policy</a></li>
        <?php endif; ?>
        <li>✓ Questions? <a class="text-gold" href="<?= url('contact.php') ?>">Talk to a person</a></li>
      </ul>
    </div>
  </div>

  <!-- Reviews -->
  <div class="row mt-5">
    <div class="col-lg-8">
      <h3 class="section-title mb-4">Customer Reviews</h3>
      <?php if (!$reviews): ?>
        <p class="text-muted">No reviews yet. Be the first to share your impression.</p>
      <?php else: foreach ($reviews as $r): ?>
        <div class="p-3 mb-3 rounded" style="background:var(--card);border:1px solid var(--line)">
          <div class="d-flex justify-content-between">
            <strong><?= h($r['author']) ?></strong>
            <span class="rating"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
          </div>
          <?php if (trim((string)$r['body']) !== ''): ?>
            <p class="text-muted small mb-0 mt-2"><?= h($r['body']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Related -->
  <?php if ($related): ?>
    <h3 class="section-title mb-4 mt-5">You May Also Like</h3>
    <div class="row g-4">
      <?php foreach ($related as $p) { require __DIR__ . '/app/_card.php'; } ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
