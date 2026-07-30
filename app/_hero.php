<?php
/**
 * Full-bleed banner hero.
 *
 * The original site's three Rolex banners, served locally from
 * assets/banners/ (downloaded once, no third-party CDN at runtime).
 * The artwork is full-bleed with baked-in text, so slides carry no
 * overlay caption — brand/name left empty.
 */
$placeholder = url('assets/default/banner_loading43a0.png');

$slides = [
    [
        'img'   => url('assets/banners/hero-1.jpg'),
        'href'  => url('shop.php?q=Land-Dweller'),
        'alt'   => 'Rolex Land-Dweller collection',
        'brand' => '',
        'name'  => '',
    ],
    [
        'img'   => url('assets/banners/hero-2.jpg'),
        'href'  => url('shop.php?q=Daytona'),
        'alt'   => 'Rolex Daytona collection',
        'brand' => '',
        'name'  => '',
    ],
    [
        'img'   => url('assets/banners/hero-3.jpg'),
        'href'  => url('shop.php?q=GMT'),
        'alt'   => 'Rolex GMT-Master collection',
        'brand' => '',
        'name'  => '',
    ],
];

// Nothing to show (empty catalog or database down) — render a text hero rather
// than an empty band.
if (!$slides): ?>
<section class="banner-hero" style="background:var(--ink)">
  <div class="container py-5 text-center" style="color:#fff">
    <h1 class="display" style="font-size:2.4rem;color:#fff"><?= h(setting('site_name')) ?></h1>
    <p class="mb-4" style="color:rgba(255,255,255,.75)"><?= h(setting('tagline')) ?></p>
    <a class="btn-cta btn-cta-red" href="<?= url('shop.php') ?>">Shop the collection <span class="arw">→</span></a>
  </div>
</section>
<?php return; endif; ?>

<section class="banner-hero">
  <div class="swiper heroSwiper">
    <div class="swiper-wrapper">
      <?php foreach ($slides as $s): ?>
        <div class="swiper-slide">
          <a href="<?= h($s['href']) ?>" class="banner-link shimmer" style="position:relative;display:block">
            <img class="lazy" src="<?= h($placeholder) ?>" data-src="<?= h($s['img']) ?>" alt="<?= h($s['alt']) ?>"
                 onerror="this.style.opacity=.12">
            <?php if ($s['brand'] !== '' || $s['name'] !== ''): ?>
            <span style="position:absolute;left:0;right:0;bottom:0;padding:26px 30px;
                         background:linear-gradient(to top,rgba(0,0,0,.62),transparent);color:#fff">
              <span style="display:block;font-size:.72rem;letter-spacing:2px;text-transform:uppercase;opacity:.85">
                <?= h($s['brand']) ?>
              </span>
              <span style="display:block;font-size:1.15rem;font-weight:600;max-width:min(560px,80%)">
                <?= h(mb_strimwidth($s['name'], 0, 60, '…')) ?>
              </span>
            </span>
            <?php endif; ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="swiper-pagination"></div>
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
  </div>
</section>
