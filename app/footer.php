</main>
<?php
require_once __DIR__ . '/pages.php';
$footerPages   = footer_pages();
$socialLinks   = setting_social_links();
$footerPhone   = setting('phone');
$footerEmail   = setting('support_email');
$footerAddress = setting_address_line(', ');
$socialLabels  = [
    'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X',
    'youtube'  => 'YouTube',  'tiktok'    => 'TikTok',    'pinterest' => 'Pinterest',
];
?>
<footer class="pt-5 mt-4">
  <div class="container">
    <!-- Feature strip. Every claim below is derived from settings, so it cannot
         contradict the policy pages. Nothing here advertises a capability the
         site does not actually have. -->
    <div class="row g-4 pb-4 text-center text-md-start" style="border-bottom:1px solid var(--line)">
      <div class="col-md-4 d-flex align-items-center justify-content-center justify-content-md-start gap-3">
        <span class="fs-3">🚚</span>
        <div>
          <div class="fw-600 text-dark">Insured Delivery</div>
          <div class="small">
            <?php if (setting_bool('free_shipping_enabled') && setting_float('free_shipping_threshold') <= 0): ?>
              Free worldwide · <?= h(delivery_estimate()) ?>
            <?php elseif (setting_bool('free_shipping_enabled')): ?>
              Free over <?= h(money(setting_float('free_shipping_threshold'))) ?> · <?= h(delivery_estimate()) ?>
            <?php else: ?>
              Tracked &amp; insured · <?= h(delivery_estimate()) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-4 d-flex align-items-center justify-content-center justify-content-md-start gap-3">
        <span class="fs-3">↩</span>
        <div>
          <div class="fw-600 text-dark"><?= setting_int('return_window_days') ?>-Day Returns</div>
          <div class="small"><a href="<?= h(page_url('return-policy')) ?>">Read our return policy</a></div>
        </div>
      </div>
      <div class="col-md-4 d-flex align-items-center justify-content-center justify-content-md-start gap-3">
        <span class="fs-3">💬</span>
        <div>
          <div class="fw-600 text-dark">Talk To Us</div>
          <div class="small">
            <?php if (setting('chatway_widget_id') !== ''): ?>
              <a class="js-chat" href="<?= url('contact.php') ?>">Start a live chat</a>
              <?= setting('support_hours') !== '' ? ' &middot; ' : '' ?>
            <?php endif; ?>
            <?php if (setting('support_hours') !== ''): ?>
              <?= h(setting('support_hours')) ?>
            <?php elseif (setting('chatway_widget_id') === ''): ?>
              <?php /* Neither hours nor live chat configured. Link the contact form rather
                       than leaving a heading sitting over empty space beside two filled
                       tiles — and rather than inventing opening hours we do not have. */ ?>
              <a href="<?= url('contact.php') ?>">Send us a message</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 py-5">
      <div class="col-md-3">
        <div class="brand-logo mb-2"><img class="lg-mark" src="<?= url('assets/brand/aurel-time-mark.png') ?>" alt="" width="54" height="46"><span class="lg-words"><?= logo_html() ?></span></div>
        <p class="small"><?= h(setting('tagline')) ?></p>
        <?php if ($socialLinks): ?>
          <div class="small">
            <?php foreach ($socialLinks as $net => $href): ?>
              <a class="me-2" href="<?= h($href) ?>" rel="noopener nofollow" target="_blank"><?= h($socialLabels[$net] ?? $net) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-6 col-md-3">
        <h6 class="text-uppercase small fw-600 text-dark">Company</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= h(page_url('about-us')) ?>">About Us</a></li>
          <li><a href="<?= url('contact.php') ?>">Contact Us</a></li>
          <li><a href="<?= url('shop.php') ?>">Shop All</a></li>
          <li><a href="<?= url('shop.php?sort=price_desc') ?>">Premium Pieces</a></li>
        </ul>

        <h6 class="text-uppercase small fw-600 text-dark mt-3">Your Account</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= url('account.php') ?>">My Account &amp; Orders</a></li>
          <li><a href="<?= url('cart.php') ?>">Cart</a></li>
        </ul>
      </div>

      <div class="col-6 col-md-3">
        <h6 class="text-uppercase small fw-600 text-dark">Help &amp; Policies</h6>
        <ul class="list-unstyled small">
          <?php
          // about-us is already linked under Company above; listing it again here
          // put the same link in two columns of the same footer.
          $policyPages = array_filter($footerPages, static fn($p) => $p['slug'] !== 'about-us');
          ?>
          <?php if ($policyPages): ?>
            <?php foreach ($policyPages as $fp): ?>
              <li><a href="<?= h(page_url($fp['slug'])) ?>"><?= h($fp['title']) ?></a></li>
            <?php endforeach; ?>
          <?php else: ?>
            <li><a href="<?= url('contact.php') ?>">Contact Us</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="col-md-3">
        <h6 class="text-uppercase small fw-600 text-dark">Get In Touch</h6>
        <address class="small mb-2" style="font-style:normal">
          <?php if (setting('legal_entity_name') !== ''): ?>
            <div class="text-dark fw-600"><?= h(setting('legal_entity_name')) ?></div>
          <?php endif; ?>
          <?php if ($footerAddress !== ''): ?>
            <div><?= h($footerAddress) ?></div>
          <?php endif; ?>
        </address>
        <ul class="list-unstyled small">
          <?php if ($footerPhone !== ''): ?>
            <li><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $footerPhone)) ?>"><?= h($footerPhone) ?></a></li>
          <?php endif; ?>
          <?php if ($footerEmail !== ''): ?>
            <li><a href="mailto:<?= h($footerEmail) ?>"><?= h($footerEmail) ?></a></li>
          <?php endif; ?>
          <?php if (setting('support_hours') !== ''): ?>
            <li><?= h(setting('support_hours')) ?></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="text-center small pb-4" style="color:var(--muted)">
      &copy; <?= date('Y') ?> <?= h(setting('legal_entity_name') !== '' ? setting('legal_entity_name') : setting('site_name')) ?>. All rights reserved.
      <?php if (setting('company_reg_no') !== ''): ?>
        · Reg. <?= h(setting('company_reg_no')) ?>
      <?php endif; ?>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function(){
  // Shrink nav on scroll
  var nav = document.getElementById('navMain');
  var onScroll = function(){ nav.classList.toggle('scrolled', window.scrollY > 24); };
  window.addEventListener('scroll', onScroll, {passive:true}); onScroll();

  // Rotating announcement
  var msgs = document.querySelectorAll('.announce .msg'), mi = 0;
  if (msgs.length > 1) setInterval(function(){
    msgs[mi].classList.remove('on'); mi = (mi + 1) % msgs.length; msgs[mi].classList.add('on');
  }, 3800);

  // Search overlay
  var st = document.getElementById('searchToggle'), sb = document.getElementById('searchBar');
  if (st && sb) st.addEventListener('click', function(){
    sb.classList.toggle('open');
    if (sb.classList.contains('open')) { var i = sb.querySelector('input'); if (i) setTimeout(function(){i.focus();}, 120); }
  });

  // Brands mega menu (hover on desktop)
  var mega = document.getElementById('megaBrands');
  var trigger = document.querySelector('.js-mega');
  if (mega && trigger){
    var open = function(){ mega.classList.add('open'); };
    var close = function(){ mega.classList.remove('open'); };
    trigger.addEventListener('mouseenter', open);
    trigger.addEventListener('click', function(e){ e.preventDefault(); mega.classList.toggle('open'); });
    nav.addEventListener('mouseleave', close);
    mega.addEventListener('mouseleave', close);
  }

  // Mobile menu
  var hb = document.getElementById('hamburger'), mm = document.getElementById('mobileMenu');
  if (hb && mm) hb.addEventListener('click', function(){ mm.classList.toggle('open'); });

  // Hero banner slider (Swiper) — autoplay + rewind + pagination + arrows
  if (window.Swiper && document.querySelector('.heroSwiper')){
    new Swiper('.heroSwiper', {
      rewind: true,
      speed: 800,
      autoplay: { delay: 5000, disableOnInteraction: false },
      pagination: { el: '.banner-hero .swiper-pagination', clickable: true },
      navigation: { nextEl: '.banner-hero .swiper-button-next', prevEl: '.banner-hero .swiper-button-prev' }
    });
  }

  // Lazy-load images: swap data-src -> src near viewport, fade in on load
  var lazyImgs = [].slice.call(document.querySelectorAll('img.lazy[data-src]'));
  var loadImg = function(img){
    if (img.dataset.loaded) return;
    img.dataset.loaded = '1';
    var done = function(){
      img.classList.add('loaded');
      var sh = img.closest('.shimmer'); if (sh) sh.classList.add('done');
    };
    img.addEventListener('load', done);
    img.addEventListener('error', done);
    img.src = img.dataset.src;
    if (img.complete && img.naturalWidth > 0) done();   // already cached -> reveal now
  };
  if ('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries, ob){
      entries.forEach(function(e){ if (e.isIntersecting){ loadImg(e.target); ob.unobserve(e.target); } });
    }, { rootMargin: '300px 0px' });
    lazyImgs.forEach(function(i){ io.observe(i); });
  } else { lazyImgs.forEach(loadImg); }

  // Scroll reveal: fade cards / tiles / section titles up on enter
  var reveals = [].slice.call(document.querySelectorAll('.card-watch, .tile-brand, .section-title, .promo'));
  reveals.forEach(function(el){ el.classList.add('reveal'); });
  if ('IntersectionObserver' in window){
    var ro = new IntersectionObserver(function(entries, ob){
      entries.forEach(function(e){ if (e.isIntersecting){ e.target.classList.add('in'); ob.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
    reveals.forEach(function(el){ ro.observe(el); });
  } else { reveals.forEach(function(el){ el.classList.add('in'); }); }

  // Live chat opener. Delegated, because the widget script loads async and $chatway
  // does not exist at parse time. preventDefault() fires only once the API is really
  // there — otherwise the link falls through to its href and the customer lands on the
  // contact form. A dead link because a third-party script was blocked is worse than
  // no link at all.
  document.addEventListener('click', function(e){
    var t = e.target && e.target.closest ? e.target.closest('.js-chat') : null;
    if (!t) return;
    if (window.$chatway && typeof window.$chatway.openChatwayWidget === 'function'){
      e.preventDefault();
      window.$chatway.openChatwayWidget();
    }
  });
})();
</script>
<?php
// Chatway live chat. Gated on the setting exactly like the Google Analytics block in
// header.php, so the storefront ships with no third-party chat JS until an id is set.
// Storefront only — the admin panel has its own shell and must not load a customer widget.
if (($chatwayId = setting('chatway_widget_id')) !== ''): ?>
<script id="chatway" async="true"
        src="https://cdn.chatway.app/widget.js?id=<?= rawurlencode($chatwayId) ?>"></script>
<?php endif; ?>
</body>
</html>
