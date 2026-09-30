<?php
/**
 * Brand hero.
 *
 * Built from the logo and live HTML text rather than banner photography. The
 * previous slider used the original site's campaign artwork, which showed a
 * third party's watches and trademarked dials. Until there is original product
 * photography, the hero carries only the Aurel Time mark, so nothing on the
 * homepage can be read as another maker's product.
 */
$heroCollections = [];
try {
    $heroPdo = db_try();
    $heroCollections = $heroPdo ? $heroPdo->query(
        'SELECT b.name, b.slug FROM brands b JOIN products p ON p.brand_id = b.id
         GROUP BY b.id ORDER BY COUNT(p.id) DESC'
    )->fetchAll() : [];
} catch (Throwable $e) {
    $heroCollections = [];
}
?>
<style>
  .brand-hero{position:relative;overflow:hidden;background:
      radial-gradient(ellipse at 70% 40%,rgba(201,161,92,.20),transparent 60%),
      linear-gradient(180deg,#0c0c0e,#000);color:var(--on-black)}
  .brand-hero .inner{display:grid;grid-template-columns:1.1fr .9fr;align-items:center;gap:40px;padding:84px 0}
  .brand-hero .eyebrow{font-size:.74rem;letter-spacing:4px;text-transform:uppercase;color:var(--gold)}
  .brand-hero h1{font-family:'Cinzel',Georgia,serif;font-weight:600;font-size:clamp(2.2rem,5vw,3.8rem);
      letter-spacing:4px;color:#e8d3a2;margin:14px 0 16px;
      background:linear-gradient(180deg,#f1dcaa,#c9a15c 55%,#9c7738);-webkit-background-clip:text;background-clip:text;color:transparent}
  .brand-hero p.lead{color:#cfc8bb;font-weight:300;max-width:520px;font-size:1.02rem}
  .brand-hero .h-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}
  .brand-hero .h-colls{display:flex;gap:18px;flex-wrap:wrap;margin-top:34px;font-size:.76rem;letter-spacing:2px;text-transform:uppercase}
  .brand-hero .h-colls a{color:#a8a29a;border-bottom:1px solid transparent;padding-bottom:3px}
  .brand-hero .h-colls a:hover{color:var(--gold);border-color:var(--gold)}
  .brand-hero .h-mark{justify-self:center;width:min(360px,80%);filter:drop-shadow(0 10px 40px rgba(201,161,92,.25))}
  .brand-hero .ring{position:absolute;right:-160px;top:50%;width:640px;height:640px;margin-top:-320px;border-radius:50%;
      border:1px solid rgba(201,161,92,.14);pointer-events:none}
  .brand-hero .ring.r2{width:820px;height:820px;margin-top:-410px;right:-250px;border-color:rgba(201,161,92,.07)}
  @media(max-width:991px){
    .brand-hero .inner{grid-template-columns:1fr;text-align:center;padding:56px 0}
    .brand-hero p.lead{margin:0 auto}
    .brand-hero .h-cta,.brand-hero .h-colls{justify-content:center}
    .brand-hero .h-mark{order:-1;width:170px}
  }
</style>
<section class="brand-hero">
  <span class="ring"></span><span class="ring r2"></span>
  <div class="container inner">
    <div>
      <div class="eyebrow"><?= h(setting('city') !== '' ? 'Checked by hand in ' . setting('city') : 'Luxury watches') ?></div>
      <h1><?= h(strtoupper(setting('site_name'))) ?></h1>
      <p class="lead"><?= h(setting('tagline')) ?></p>
      <div class="h-cta">
        <a class="btn-cta btn-cta-red" href="<?= url('shop.php') ?>">Shop the collection <span class="arw">→</span></a>
        <a class="btn-cta btn-cta-ghost" href="<?= h(page_url('about-us')) ?>">Our story</a>
      </div>
      <?php if ($heroCollections): ?>
        <nav class="h-colls" aria-label="Collections">
          <?php foreach ($heroCollections as $c): ?>
            <a href="<?= url('shop.php?brand=' . urlencode($c['slug'])) ?>"><?= h($c['name']) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>
    <img class="h-mark" src="<?= url('assets/brand/aurel-time-mark.png') ?>" alt="<?= h(setting('site_name')) ?> monogram" width="360" height="310">
  </div>
</section>
