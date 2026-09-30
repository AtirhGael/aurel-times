<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pages.php';

/**
 * Per-page overrides, all optional — set before requiring this file:
 *   $pageTitle       string  <title> and og:title
 *   $metaDescription string  meta description and og:description
 *   $canonical       string  absolute canonical URL
 *   $ogImage         string  absolute image URL for social cards
 *   $ogType          string  'website' (default) or 'product'
 *   $robots          string  overrides the site-wide indexing setting
 *   $jsonLd          array   one or more schema.org graphs to emit
 */
$siteName        = setting('site_name');
$pageTitle       = $pageTitle ?? $siteName;
$metaDescription = $metaDescription ?? setting('meta_description');
$ogImage         = $ogImage ?? setting('og_image');
if ($ogImage === '') {
    // Brand share card, so pages without a photo of their own still preview properly.
    $ogImage = abs_url('assets/brand/aurel-time-og.png');
}
$ogType          = $ogType ?? 'website';
$canonical       = $canonical ?? abs_url(ltrim((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/') === ''
    ? ''
    : basename((string)$_SERVER['SCRIPT_NAME']));
$robots          = $robots ?? (setting_bool('robots_index') ? 'index, follow' : 'noindex, nofollow');
$titleSuffix     = setting('meta_title_suffix');

// db_try(), not db(): a stopped database must degrade to an empty nav rather
// than replacing every page on the site with a connection-error screen.
try {
    $pdo = db_try();
    $navBrands = $pdo ? $pdo->query(
        'SELECT b.name, b.slug, COUNT(p.id) c
         FROM brands b JOIN products p ON p.brand_id = b.id
         GROUP BY b.id ORDER BY c DESC'
    )->fetchAll() : [];
    // one image for the mega-menu "editor's pick"
    $megaPick = $pdo ? $pdo->query(
        "SELECT handle, name, image, base_price FROM products
         WHERE image LIKE 'http%' AND base_price > 0 ORDER BY base_price DESC LIMIT 1"
    )->fetch() : null;
    $productCount = $pdo ? (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : 0;
} catch (Throwable $e) {
    $navBrands = [];
    $megaPick = null;
    $productCount = 0;
}
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · <?= h($siteName) ?><?= $titleSuffix !== '' ? ' · ' . h($titleSuffix) : '' ?></title>
<meta name="description" content="<?= h($metaDescription) ?>">
<meta name="robots" content="<?= h($robots) ?>">
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<?php endif; ?>
<?php if (($gsv = setting('google_site_verification')) !== ''): ?>
<meta name="google-site-verification" content="<?= h($gsv) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= h($siteName) ?>">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($metaDescription) ?>">
<meta property="og:type" content="<?= h($ogType) ?>">
<?php if ($canonical !== ''): ?>
<meta property="og:url" content="<?= h($canonical) ?>">
<?php endif; ?>
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?= h($ogImage) ?>">
<meta name="twitter:image" content="<?= h($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= h($pageTitle) ?>">
<meta name="twitter:description" content="<?= h($metaDescription) ?>">
<meta name="theme-color" content="#0c0c0e">
<link rel="icon" type="image/png" href="<?= url('assets/brand/favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= url('assets/brand/favicon.png') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#ffffff; --bg2:#f5f5f6; --card:#ffffff; --line:#e6e6e8;
    /* Brand palette, taken from the Aurel Time logo. --gold is the bright logo gold,
       for use on black. --red/--red2 keep their old names so existing markup and
       classes keep working, but now hold the darker gold that stays readable
       (AA contrast) as text on white. */
    --gold:#c9a15c; --gold2:#b08a45; --gold-ink:#8a6a2f;
    --red:#8a6a2f; --red2:#6f5424; --ink:#1c1c1e; --muted:#6b7280;
    --ink-hero:#0c0c0e; --black:#0c0c0e; --on-black:#f3efe6;
    --text:#1c1c1e; --tile:#ededee;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{background:var(--bg);color:var(--ink);font-family:'Poppins',system-ui,sans-serif;font-weight:300}
  h1,h2,h3,h4,h5{font-family:'Poppins',sans-serif;font-weight:600;letter-spacing:.2px;color:var(--ink)}
  .display{font-family:'Playfair Display',Georgia,serif}
  a{color:inherit;text-decoration:none}
  a:hover{color:var(--red)}
  .text-gold,.text-red{color:var(--red)!important}

  /* ===== Announcement bar ===== */
  .announce{background:#000;color:var(--on-black);border-bottom:1px solid rgba(201,161,92,.18);font-size:.78rem;letter-spacing:.4px;
    height:34px;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
  .announce .track{position:relative;height:100%;width:100%}
  .announce .msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:10px;
    opacity:0;transform:translateY(100%);transition:opacity .6s,transform .6s}
  .announce .msg.on{opacity:1;transform:translateY(0)}
  .announce .msg b{color:var(--gold);font-weight:600}
  .announce .msg .dot{color:var(--gold)}

  /* ===== Nav ===== */
  .nav-main{position:sticky;top:0;z-index:1030;background:var(--black);border-bottom:1px solid rgba(201,161,92,.25);
    transition:box-shadow .3s,padding .3s}
  .nav-main.scrolled{box-shadow:0 6px 24px rgba(0,0,0,.35)}
  .nav-inner{display:flex;align-items:center;gap:20px;padding:18px 0;transition:padding .3s}
  .nav-main.scrolled .nav-inner{padding:10px 0}
  /* Two-line wordmark: the store name is too long for a single nowrap line beside the
     nav links and icon cluster. .lg-a / .lg-b come from logo_html(). */
  .brand-logo{display:inline-flex;align-items:center;gap:10px;line-height:1;color:var(--on-black);white-space:nowrap}
  .brand-logo .lg-mark{height:46px;width:auto;transition:height .3s}
  .brand-logo .lg-words{display:inline-flex;flex-direction:column}
  .nav-main.scrolled .brand-logo .lg-mark{height:36px}
  /* Deck B is already red; without this the global a:hover would take deck A too and
     flip the whole mark to solid red on hover. */
  .brand-logo:hover{color:var(--on-black)}
  .brand-logo .lg-a{font-family:'Cinzel',Georgia,serif;font-size:1.3rem;font-weight:600;letter-spacing:2.6px;transition:font-size .3s}
  .brand-logo .lg-b{font-size:.62rem;font-weight:500;letter-spacing:3.6px;color:var(--gold);
    margin-top:4px;transition:font-size .3s,letter-spacing .3s}
  .nav-main.scrolled .brand-logo .lg-a{font-size:1.08rem}
  .nav-main.scrolled .brand-logo .lg-b{font-size:.6rem;letter-spacing:3px}
  @media(max-width:575px){
    .brand-logo .lg-mark{height:34px}
    .brand-logo .lg-a{font-size:1.05rem;letter-spacing:1.8px}
    .brand-logo .lg-b{font-size:.56rem;letter-spacing:2.4px}
  }
  .nav-links{display:flex;gap:30px;margin:0 auto;align-items:center}
  .nav-links a{position:relative;font-size:.82rem;text-transform:uppercase;letter-spacing:1.2px;font-weight:500;color:var(--on-black);padding:6px 0}
  .nav-links a::after{content:'';position:absolute;left:50%;bottom:0;height:1px;width:0;background:var(--gold);transition:width .28s ease,left .28s ease}
  .nav-links a:hover{color:var(--gold)}
  .nav-links a:hover::after{width:100%;left:0}
  .nav-links a.sale{color:var(--red)}
  .nav-links a.sale .pulse{display:inline-block;width:6px;height:6px;background:var(--red);border-radius:50%;margin-left:5px;animation:pulse 1.6s infinite}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(201,161,92,.5)}70%{box-shadow:0 0 0 7px rgba(201,161,92,0)}100%{box-shadow:0 0 0 0 rgba(201,161,92,0)}}
  .nav-icons{display:flex;align-items:center;gap:20px}
  .nav-icons a,.nav-icons button{color:var(--on-black);background:none;border:none;font-size:.92rem;position:relative;cursor:pointer;display:flex;align-items:center;gap:6px;letter-spacing:.5px}
  .nav-icons a:hover,.nav-icons button:hover{color:var(--gold)}
  .cart-count{position:absolute;top:-9px;right:-12px;background:var(--gold);color:#000;border-radius:50%;font-size:.6rem;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-weight:600}
  .hamburger{display:none}

  /* Mega menu */
  .has-mega{position:static}
  .mega{position:absolute;left:0;right:0;top:100%;background:#fff;border-top:1px solid var(--line);
    box-shadow:0 20px 40px rgba(0,0,0,.12);opacity:0;visibility:hidden;transform:translateY(8px);
    transition:.25s;z-index:1029}
  .mega.open{opacity:1;visibility:visible;transform:translateY(0)}
  .mega-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 26px}
  .mega a.b{display:flex;justify-content:space-between;padding:9px 12px;border-radius:4px;font-size:.9rem;color:var(--ink)}
  .mega a.b:hover{background:var(--bg2);color:var(--red)}
  .mega a.b span{color:var(--muted);font-size:.78rem}
  .mega-feat{border-left:1px solid var(--line);padding-left:24px}
  .mega-feat img{width:100%;aspect-ratio:1;object-fit:cover;background:#f2f2f3;border-radius:6px}

  /* Search overlay bar */
  .search-bar{max-height:0;overflow:hidden;background:var(--bg2);border-top:1px solid var(--line);transition:max-height .3s}
  .search-bar.open{max-height:90px}
  .search-bar .inner{padding:18px 0;display:flex;gap:10px}

  /* ===== Buttons ===== */
  .btn-red{background:var(--gold);border:none;color:#000;font-weight:500;border-radius:2px}
  .btn-red:hover{background:var(--gold2);color:#000}
  .btn-dark2,.btn-gold{background:var(--ink);border:none;color:#fff;border-radius:2px;font-size:.82rem;letter-spacing:.5px;text-transform:uppercase;font-weight:500}
  .btn-dark2:hover,.btn-gold:hover{background:#000;color:var(--gold)}
  .btn-outline-dark2,.btn-outline-gold{border:1px solid var(--ink);color:var(--ink);background:transparent;border-radius:2px;font-size:.82rem;letter-spacing:.5px;text-transform:uppercase}
  .btn-outline-dark2:hover,.btn-outline-gold:hover{background:var(--ink);color:#fff}
  .btn-cta{display:inline-flex;align-items:center;gap:10px;padding:13px 26px;font-size:.8rem;letter-spacing:1.5px;
    text-transform:uppercase;font-weight:600;border-radius:2px;transition:.25s;border:1px solid transparent}
  .btn-cta .arw{transition:transform .25s}
  .btn-cta:hover .arw{transform:translateX(5px)}
  .btn-cta-red{background:var(--gold);color:#000}
  .btn-cta-red:hover{background:var(--gold2);color:#000}
  .btn-cta-ghost{border-color:rgba(255,255,255,.55);color:#fff}
  .btn-cta-ghost:hover{background:#fff;color:var(--ink);border-color:#fff}

  /* ===== HERO (full-bleed banner slider — same images/style as original) ===== */
  .banner-hero{position:relative;width:100%;background:#eef0f2;overflow:hidden}
  .banner-hero .swiper{width:100%}
  .banner-hero .swiper-slide{aspect-ratio:2.4/1;overflow:hidden}
  @media(max-width:640px){.banner-hero .swiper-slide{aspect-ratio:16/11}}
  .banner-hero .banner-link{display:block;width:100%;height:100%}
  .banner-hero img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 7s ease}
  .banner-hero .swiper-slide-active img{transform:scale(1.06)}      /* slow ken-burns on active slide */
  .banner-hero .swiper-pagination{bottom:18px!important}
  .banner-hero .swiper-pagination-bullet{width:10px;height:10px;background:#fff;opacity:.7;box-shadow:0 1px 5px rgba(0,0,0,.35);transition:.3s}
  .banner-hero .swiper-pagination-bullet-active{background:var(--gold);opacity:1;width:28px;border-radius:6px}
  .banner-hero .swiper-button-prev,.banner-hero .swiper-button-next{color:#fff;width:46px;height:46px;
     background:rgba(0,0,0,.28);border-radius:50%;transition:.25s}
  .banner-hero .swiper-button-prev:hover,.banner-hero .swiper-button-next:hover{background:var(--gold);color:#000}
  .banner-hero .swiper-button-prev::after,.banner-hero .swiper-button-next::after{font-size:17px;font-weight:800}

  /* ===== Lazy image load: placeholder -> fade in (blur-up, like original) ===== */
  img.lazy{opacity:0;filter:blur(8px);transition:opacity .7s ease,filter .7s ease}
  img.lazy.loaded{opacity:1;filter:blur(0)}
  .shimmer{position:relative;background:#f0f0f1}
  .shimmer::before{content:'';position:absolute;inset:0;z-index:0;pointer-events:none;
    background:linear-gradient(100deg,#ececed 25%,#f7f7f8 50%,#ececed 75%);background-size:220% 100%;animation:shimmer 1.5s linear infinite}
  .shimmer.done::before{opacity:0;transition:opacity .4s}

  /* ===== Scroll reveal: cards & sections fade up on enter ===== */
  .reveal{opacity:0;transform:translateY(30px);transition:opacity .6s ease,transform .6s cubic-bezier(.2,.7,.3,1)}
  .reveal.in{opacity:1;transform:none}
  @keyframes shimmer{from{background-position:200% 0}to{background-position:-120% 0}}
  @media (prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none} img.lazy{transition:none;filter:none}}

  /* ===== cards / shared (light) ===== */
  .card-watch{background:#fff;border:1px solid var(--line);border-radius:4px;overflow:hidden;transition:.2s;height:100%;position:relative}
  .card-watch:hover{box-shadow:0 8px 24px rgba(0,0,0,.12);transform:translateY(-3px)}
  .card-watch .imgwrap{aspect-ratio:1;background:#f2f2f3;overflow:hidden;position:relative}
  .card-watch img{width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.2,.7,.3,1)}
  .card-watch:hover img{transform:scale(1.08)}
  .badge-off{position:absolute;top:8px;right:8px;background:var(--gold);color:#000;font-size:.72rem;font-weight:600;padding:3px 8px;border-radius:2px;z-index:2}
  .badge-save{display:inline-block;background:var(--ink);color:#fff;font-size:.68rem;padding:2px 7px;border-radius:2px;margin-top:4px}
  .price{color:var(--ink);font-weight:600}
  .price-compare{color:var(--muted);text-decoration:line-through;font-size:.85rem;font-weight:300}
  .form-control,.form-select{background:#fff;border:1px solid var(--line);color:var(--ink);border-radius:2px}
  .form-control:focus,.form-select:focus{border-color:var(--red);box-shadow:0 0 0 .2rem rgba(201,161,92,.12)}
  .rating{color:#f5a623;letter-spacing:1px;font-size:.8rem}
  .section-title{text-align:center;font-weight:600;font-size:1.7rem;margin-bottom:.3rem}
  .section-sub{text-align:center;color:var(--muted);font-size:.85rem;margin-bottom:1.6rem}
  .tile-brand{background:var(--bg2);border:1px solid var(--line);border-radius:4px;padding:26px 10px;text-align:center;transition:.2s;font-weight:500}
  .tile-brand:hover{background:var(--ink);color:#fff}
  footer{background:var(--black);border-top:1px solid rgba(201,161,92,.25);color:#a8a29a;--line:rgba(201,161,92,.18);--muted:#8f8a82}
  footer .text-dark{color:var(--on-black)!important}
  footer a:hover{color:var(--gold)}
  footer .brand-logo{color:var(--on-black)}
  .promo-brand{background:radial-gradient(circle at 80% 20%,rgba(201,161,92,.28),transparent 55%),linear-gradient(135deg,#16140f,#0c0c0e 60%);
    border:1px solid rgba(201,161,92,.3);color:var(--on-black)}
  .promo-brand h3{font-family:'Cinzel',Georgia,serif;color:var(--gold)!important;letter-spacing:1.5px}
  .promo{background:var(--bg2);border-radius:6px;overflow:hidden;min-height:190px;display:flex;flex-direction:column;justify-content:center;padding:30px;background-size:cover;background-position:center}
  .flash{border:1px solid var(--red);background:rgba(201,161,92,.06);color:var(--red2)}

  /* mobile */
  @media (max-width:991px){
    .nav-links,.nav-icons .lbl{display:none}
    .hamburger{display:flex;order:3;margin-left:auto;background:none;border:none;font-size:1.4rem;color:var(--on-black)}
    .mega{display:none}
    .hero .slide-grid{grid-template-columns:1fr;text-align:center}
    .hero .h-visual{display:none}
    .hero .eyebrow{justify-content:center}
    .hero .h-price,.hero .h-cta{justify-content:center}
    .mobile-menu.open{display:block}
  }
  .mobile-menu{display:none;border-top:1px solid rgba(201,161,92,.2);padding:10px 0}
  .mobile-menu a{display:block;padding:11px 4px;border-bottom:1px solid rgba(201,161,92,.15);color:var(--on-black);text-transform:uppercase;font-size:.85rem;letter-spacing:1px}
</style>
<?php
if (!empty($jsonLd)) {
    foreach ((array)$jsonLd as $graph) {
        echo '<script type="application/ld+json">'
           . json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
           . "</script>\n";
    }
}
if (($gaId = setting('google_analytics_id')) !== ''):
?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= rawurlencode($gaId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', <?= json_encode($gaId) ?>, {anonymize_ip: true});
</script>
<?php endif; ?>
</head>
<body>
<?php
/**
 * Announcement bar. Every claim here is generated from the shipping and returns
 * settings, so it cannot drift out of sync with the policy pages — which is
 * exactly what happened before: the bar promised 30-day returns while the
 * product page promised 14.
 */
$announcements = [];
$freeShip  = setting_bool('free_shipping_enabled');
$threshold = setting_float('free_shipping_threshold');
if ($freeShip && $threshold <= 0) {
    $announcements[] = 'Insured worldwide shipping <b>free</b> · Delivery in ' . h(delivery_estimate());
} elseif ($freeShip) {
    $announcements[] = 'Free shipping on orders over <b>' . h(money($threshold)) . '</b> · Delivery in ' . h(delivery_estimate());
} else {
    $announcements[] = 'Insured worldwide shipping · Delivery in ' . h(delivery_estimate());
}
if (($rw = setting_int('return_window_days')) > 0) {
    $announcements[] = 'Not satisfied? <b>' . $rw . '-day</b> hassle-free returns';
}
if (($wm = setting_int('warranty_months')) > 0) {
    $announcements[] = 'Every piece covered by a <b>' . $wm . '-month</b> warranty';
}
?>
<div class="announce">
  <div class="track container">
    <?php foreach ($announcements as $i => $msg): ?>
      <div class="msg<?= $i === 0 ? ' on' : '' ?>"><span class="dot">◆</span> <?= $msg ?></div>
    <?php endforeach; ?>
  </div>
</div>

<header class="nav-main" id="navMain">
  <div class="container nav-inner">
    <a class="brand-logo" href="<?= url('index.php') ?>"><img class="lg-mark" src="<?= url('assets/brand/aurel-time-mark.png') ?>" alt="" width="54" height="46"><span class="lg-words"><?= logo_html() ?></span></a>
    <nav class="nav-links">
      <a href="<?= url('index.php') ?>">Home</a>
      <a href="<?= url('shop.php') ?>">Shop All</a>
      <a href="#" class="js-mega" data-mega="megaBrands">Collections</a>
      <a href="<?= url('shop.php?sort=newest') ?>">New In</a>
    </nav>
    <div class="nav-icons">
      <button type="button" id="searchToggle" title="Search">⌕ <span class="lbl">Search</span></button>
      <?php if ($u): ?>
        <a href="<?= url('account.php') ?>" title="Account"><span class="lbl"><?= h(explode(' ', (string)$u['name'])[0] ?: 'Account') ?></span></a>
        <a href="<?= url('logout.php') ?>"><span class="lbl">Logout</span></a>
      <?php else: ?>
        <a href="<?= url('login.php') ?>"><span class="lbl">Login</span></a>
      <?php endif; ?>
      <a href="<?= url('cart.php') ?>" title="Cart">Cart<?php if (cart_count() > 0): ?><span class="cart-count"><?= cart_count() ?></span><?php endif; ?></a>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menu">☰</button>
  </div>

  <!-- Collections mega menu -->
  <div class="mega" id="megaBrands">
    <div class="container py-4">
      <div class="row">
        <div class="col-lg-8">
          <div class="text-uppercase small text-muted mb-3" style="letter-spacing:2px">Shop by Collection</div>
          <div class="mega-grid">
            <?php foreach ($navBrands as $b): ?>
              <a class="b" href="<?= url('shop.php?brand=' . urlencode($b['slug'])) ?>"><?= h($b['name']) ?> <span><?= (int)$b['c'] ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if ($megaPick): ?>
          <div class="col-lg-4 mega-feat">
            <div class="text-uppercase small text-red mb-2" style="letter-spacing:2px">Editor's Pick</div>
            <a href="<?= url('product.php?handle=' . urlencode($megaPick['handle'])) ?>">
              <img src="<?= h($megaPick['image']) ?>" alt="" onerror="this.style.opacity=.15">
              <div class="mt-2 small"><?= h(mb_strimwidth($megaPick['name'], 0, 44, '…')) ?></div>
              <div class="price"><?= money($megaPick['base_price']) ?></div>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Search bar -->
  <div class="search-bar" id="searchBar">
    <form class="container" action="<?= url('shop.php') ?>" method="get">
      <div class="inner">
        <input class="form-control form-control-lg" type="search" name="q" autocomplete="off"
               placeholder="<?= $productCount > 0 ? 'Search ' . number_format($productCount) . ' timepieces…' : 'Search timepieces…' ?>"
               value="<?= h($_GET['q'] ?? '') ?>">
        <button class="btn btn-red px-4">Search</button>
      </div>
    </form>
  </div>

  <!-- Mobile menu -->
  <div class="mobile-menu container" id="mobileMenu">
    <a href="<?= url('index.php') ?>">Home</a>
    <a href="<?= url('shop.php') ?>">Shop All</a>
    <?php foreach ($navBrands as $b): ?>
      <a href="<?= url('shop.php?brand=' . urlencode($b['slug'])) ?>"><?= h($b['name']) ?> (<?= (int)$b['c'] ?>)</a>
    <?php endforeach; ?>
    <a href="<?= $u ? url('account.php') : url('login.php') ?>"><?= $u ? 'Account' : 'Login' ?></a>
    <a href="<?= url('cart.php') ?>">Cart (<?= cart_count() ?>)</a>
  </div>
</header>

<?php if ($f = flash()): ?>
  <div class="container mt-3"><div class="flash rounded px-3 py-2"><?= h($f) ?></div></div>
<?php endif; ?>
<main>
