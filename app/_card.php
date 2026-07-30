<?php
/**
 * Product card. Expects $p: handle, name, brand, min_price, image, rating,
 * review_count, and optionally compare_at_price.
 *
 * The strikethrough "was" price and the discount badge appear ONLY when a real
 * compare_at_price is stored and is genuinely higher than the selling price.
 *
 * This previously synthesised both from crc32($p['handle']) % 16, so every
 * product displayed an invented 40-55% saving against a reference price that
 * had never existed. That is a deceptive-pricing violation under the FTC Act
 * and a Google Merchant Center violation, and it was the most obvious tell on
 * the site. Do not reintroduce a computed reference price — leave the column
 * NULL and the card simply shows one honest price.
 */
$price   = (float)($p['min_price'] ?? 0);
$compare = (float)($p['compare_at_price'] ?? 0);
$hasDeal = $compare > 0 && $price > 0 && $compare > $price;
$save    = $hasDeal ? $compare - $price : 0.0;
$disc    = $hasDeal ? (int)round(($save / $compare) * 100) : 0;
?>
<div class="col-6 col-md-4 col-lg-3">
  <a href="<?= url('product.php?handle=' . urlencode($p['handle'])) ?>" class="d-block">
    <div class="card-watch">
      <?php if ($hasDeal && $disc > 0): ?><span class="badge-off"><?= $disc ?>% OFF</span><?php endif; ?>
      <div class="imgwrap shimmer">
        <img class="lazy" alt="<?= h($p['name']) ?>"
             src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='1'%20height='1'%3E%3C/svg%3E"
             data-src="<?= h(img_or_placeholder($p['image'] ?? '')) ?>"
             onerror="this.onerror=null;this.src='https://placehold.co/600x600/f2f2f3/c8102e?text=<?= urlencode($p['brand'] ?? 'Watch') ?>';this.classList.add('loaded');this.closest('.shimmer')?.classList.add('done')">
      </div>
      <div class="p-3">
        <div class="small text-muted text-uppercase" style="letter-spacing:.5px;font-size:.68rem"><?= h($p['brand'] ?? '') ?></div>
        <div class="mb-1" style="min-height:2.6em;line-height:1.3;font-size:.86rem;color:var(--ink)"><?= h(mb_strimwidth($p['name'], 0, 52, '…')) ?></div>
        <?php if (!empty($p['review_count'])): ?>
          <div class="rating mb-1"><?= str_repeat('★', (int)round($p['rating'])) ?> <span class="text-muted">(<?= (int)$p['review_count'] ?>)</span></div>
        <?php endif; ?>
        <?php if ($price > 0): ?>
          <div class="d-flex align-items-center gap-2">
            <span class="price"><?= money($price) ?></span>
            <?php if ($hasDeal): ?>
              <span class="price-compare"><?= money($compare) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($hasDeal): ?>
            <span class="badge-save">Save <?= money($save) ?></span>
          <?php endif; ?>
        <?php else: ?>
          <div class="price">Enquire</div>
        <?php endif; ?>
      </div>
    </div>
  </a>
</div>
