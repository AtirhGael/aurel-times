<?php
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/pages.php';
require_once __DIR__ . '/app/_schema.php';

$pageTitle       = 'Luxury Timepieces';
$metaDescription = setting('meta_description');
$canonical       = abs_url('index.php');
$jsonLd          = [schema_organization(), schema_website()];

/** Fetch up to $n products whose name matches a term (for a featured row). */
function feat_row(string $term, int $n = 8): array {
    $st = db()->prepare(
        "SELECT p.handle, p.name, p.image, p.base_price AS min_price, b.name AS brand,
                AVG(r.rating) AS rating, COUNT(DISTINCT r.id) AS review_count
         FROM products p JOIN brands b ON b.id = p.brand_id
         LEFT JOIN reviews r ON r.product_id = p.id
         WHERE p.name LIKE ? AND p.base_price > 0
         GROUP BY p.id ORDER BY p.base_price DESC LIMIT ?"
    );
    $st->bindValue(1, '%' . $term . '%');
    $st->bindValue(2, $n, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

$brands = db()->query(
    'SELECT b.name, b.slug, COUNT(p.id) c
     FROM brands b JOIN products p ON p.brand_id = b.id
     GROUP BY b.id ORDER BY c DESC'
)->fetchAll();

// Featured collection rows. The third element is the term matched against
// product names, which now all carry their collection name.
$rows = [
    ['Tideline', 'Built to dive — rotating bezels, serious water resistance', 'Tideline'],
    ['Circuit',  'Chronographs with motorsport-inspired styling', 'Circuit'],
    ['Monolith', 'Integrated bracelets, hard-edged cases', 'Monolith'],
    ['Ascot',    'Slim dress watches that sit cleanly under a cuff', 'Ascot'],
];
$rows = array_values(array_filter(array_map(function ($r) {
    $items = feat_row($r[2]);
    return count($items) >= 4 ? [$r[0], $r[1], $items] : null;
}, $rows)));

$stats = db()->query('SELECT
    (SELECT COUNT(*) FROM products) p,
    (SELECT COUNT(*) FROM brands) b')->fetch();

require __DIR__ . '/app/header.php';
require __DIR__ . '/app/_hero.php';
?>
<!-- Collection List -->
<section class="container py-5">
  <h2 class="section-title">Collection List</h2>
  <p class="section-sub">Shop <?= (int)$stats['p'] ?> timepieces across <?= (int)$stats['b'] ?> collections</p>
  <div class="row g-3">
    <?php foreach ($brands as $b): ?>
      <div class="col-4 col-md-3 col-lg-2">
        <a href="<?= url('shop.php?brand=' . urlencode($b['slug'])) ?>" class="tile-brand d-block">
          <div><?= h($b['name']) ?></div>
          <div class="text-muted small mt-1"><?= (int)$b['c'] ?> models</div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-4">
    <a href="<?= url('shop.php') ?>" class="btn btn-outline-dark2 px-4 py-2">View More</a>
  </div>
</section>

<!-- Featured model rows -->
<?php foreach ($rows as $i => $row): [$title, $sub, $items] = $row; ?>
  <section class="py-5" style="<?= $i % 2 ? 'background:var(--bg2)' : '' ?>">
    <div class="container">
      <h2 class="section-title"><?= h($title) ?></h2>
      <p class="section-sub"><?= h($sub) ?></p>
      <div class="row g-4">
        <?php foreach ($items as $p) { require __DIR__ . '/app/_card.php'; } ?>
      </div>
      <div class="text-center mt-4">
        <a href="<?= url('shop.php?q=' . urlencode(explode(' ', $title)[count(explode(' ', $title)) - 1])) ?>"
           class="btn btn-dark2 px-4 py-2">View More</a>
      </div>
    </div>
  </section>

  <?php if ($i === 0): // collection promo tiles after the first row ?>
    <section class="container py-5">
      <h2 class="section-title mb-4">Explore the Collections</h2>
      <div class="row g-4">
        <div class="col-md-6">
          <div class="promo promo-brand">
            <h3 class="text-white">Find Your Transit</h3>
            <p class="mb-3" style="max-width:320px">For people who cross timezones often. Second-timezone tracking.</p>
            <a href="<?= url('shop.php?q=Transit') ?>" class="btn btn-red align-self-start px-4">Shop Now</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="promo promo-brand">
            <h3 class="text-white">Find Your Datum</h3>
            <p class="mb-3" style="max-width:320px">Plain-spoken everyday watches, made for daily wear.</p>
            <a href="<?= url('shop.php?q=Datum') ?>" class="btn btn-red align-self-start px-4">Shop Now</a>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
<?php endforeach; ?>

<!-- Why buy band -->
<section class="py-5" style="background:var(--bg2)">
  <div class="container">
    <h2 class="section-title mb-4">Why Buy From Us</h2>
    <?php
    // Every claim below comes from settings, so it stays consistent with the
    // announcement bar, the product page and the policy pages. The old copy
    // promised "24/7 Support" with no support channel of any kind and a
    // "30-day return policy" that the product page contradicted with 14.
    ?>
    <div class="row text-center g-4">
      <div class="col-md-4">
        <div class="fs-4 mb-1">🚚</div>
        <h6>
          <?php if (setting_bool('free_shipping_enabled') && setting_float('free_shipping_threshold') <= 0): ?>
            Free Insured Shipping
          <?php elseif (setting_bool('free_shipping_enabled')): ?>
            Free Over <?= h(money(setting_float('free_shipping_threshold'))) ?>
          <?php else: ?>
            Insured Worldwide Shipping
          <?php endif; ?>
        </h6>
        <p class="text-muted small mb-0">
          Tracked and insured on every order · <?= h(delivery_estimate()) ?>.
        </p>
      </div>
      <div class="col-md-4">
        <div class="fs-4 mb-1">💬</div>
        <h6>Talk To A Person</h6>
        <p class="text-muted small mb-0">
          <?php if (setting('support_hours') !== ''): ?><?= h(setting('support_hours')) ?> · <?php endif; ?>
          <a class="text-gold" href="<?= url('contact.php') ?>">contact us</a>
        </p>
      </div>
      <div class="col-md-4">
        <div class="fs-4 mb-1">↩</div>
        <h6><?= setting_int('return_window_days') ?>-Day Returns</h6>
        <p class="text-muted small mb-0">
          <?= setting_int('warranty_months') ?>-month warranty ·
          <a class="text-gold" href="<?= h(page_url('return-policy')) ?>">read the policy</a>
        </p>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/app/footer.php'; ?>
