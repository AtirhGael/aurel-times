<?php
/**
 * Renderer for content pages. Included by the root stubs (shipping-policy.php,
 * return-policy.php, ...) and by page.php, each of which sets $slug first.
 */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/_schema.php';

/** @var string $slug */
$slug = $slug ?? '';
$page = $slug !== '' ? get_page($slug) : null;

if (!$page) {
    /**
     * Distinguish "this page does not exist" from "the database is down".
     *
     * get_page() returns null for both. Answering 404 during an outage would
     * tell search engines that the shipping and returns policies genuinely do
     * not exist, and repeated 404s get URLs dropped from the index. 503 with
     * Retry-After says "come back later" instead.
     */
    $dbDown = (db_try() === null);

    http_response_code($dbDown ? 503 : 404);
    if ($dbDown) {
        header('Retry-After: 300');
    }
    $pageTitle = $dbDown ? 'Temporarily unavailable' : 'Page not found';
    $robots    = 'noindex, nofollow';
    require __DIR__ . '/header.php';
    ?>
    <div class="container py-5 text-center" style="max-width:640px">
      <?php if ($dbDown): ?>
        <h1 class="section-title">We will be right back</h1>
        <p class="section-sub">
          This page is temporarily unavailable while we carry out maintenance.
          Please try again in a few minutes.
        </p>
        <p class="small text-muted">
          Need something urgently? Call <?= h(setting('phone')) ?> or email
          <a class="text-gold" href="mailto:<?= h(setting('support_email')) ?>"><?= h(setting('support_email')) ?></a>.
        </p>
      <?php else: ?>
        <h1 class="section-title">Page not found</h1>
        <p class="section-sub">We could not find that page. It may have been renamed or unpublished.</p>
        <a class="btn btn-gold px-4" href="<?= url('index.php') ?>">Back to the store</a>
        <a class="btn btn-outline-dark2 px-4 ms-2" href="<?= url('contact.php') ?>">Contact us</a>
      <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    return;
}

$pageTitle       = render_placeholders_text((string)($page['meta_title'] !== '' ? $page['meta_title'] : $page['title']));
$metaDescription = render_placeholders_text((string)$page['meta_desc']);
if (trim($metaDescription) === '') {
    $metaDescription = setting('meta_description');
}
$canonical = abs_url(ltrim(page_url($slug), '/') === '' ? '' : substr(page_url($slug), strlen(BASE_URL)));
$jsonLd    = [schema_breadcrumb([
    ['name' => setting('site_name'), 'url' => abs_url('index.php')],
    ['name' => (string)$page['title'], 'url' => $canonical],
])];

$heading = render_placeholders_text((string)$page['title']);
require __DIR__ . '/header.php';
?>
<div class="container py-5">
  <div class="mx-auto" style="max-width:820px">

    <nav aria-label="breadcrumb" class="small mb-3" style="color:var(--muted)">
      <a href="<?= url('index.php') ?>">Home</a> <span class="mx-1">/</span> <span><?= h($heading) ?></span>
    </nav>

    <h1 class="mb-1" style="font-weight:600"><?= h($heading) ?></h1>
    <?php if (!empty($page['updated_at'])): ?>
      <p class="small mb-4" style="color:var(--muted)">
        Last updated <?= h(date('j F Y', strtotime((string)$page['updated_at']))) ?>
      </p>
    <?php endif; ?>

    <div class="policy-body">
      <?php
      /**
       * Raw echo, deliberately.
       *
       * content_pages.body is admin-authored HTML — escaping it would render
       * the markup as visible text. It is stored and rendered WITHOUT a
       * sanitiser, and the only thing making that safe is that page editing is
       * gated behind require_admin(). If page editing is ever opened to a
       * lower-privileged role, an HTML sanitiser becomes mandatory here.
       *
       * The {{placeholder}} VALUES substituted in by render_placeholders() are
       * escaped individually inside that function, so a hostile settings value
       * cannot inject script through this path.
       */
      echo render_placeholders((string)$page['body']);
      ?>
    </div>

    <hr class="my-5">
    <div class="small" style="color:var(--muted)">
      Still have a question?
      <a class="text-gold" href="<?= url('contact.php') ?>">Contact our team</a><?php if (setting('support_hours') !== ''): ?> —
      we answer <?= h(setting('support_hours')) ?><?php endif; ?>.
    </div>
  </div>
</div>

<style>
  .policy-body{line-height:1.75}
  .policy-body h2{font-size:1.18rem;font-weight:600;margin-top:2.2rem;margin-bottom:.7rem}
  .policy-body h3{font-size:1rem;font-weight:600;margin-top:1.5rem;margin-bottom:.4rem}
  .policy-body p,.policy-body li{color:#3a3a3e}
  .policy-body ul,.policy-body ol{padding-left:1.2rem}
  .policy-body li{margin-bottom:.4rem}
  .policy-body .lead{font-size:1.05rem;color:var(--ink)}
  .policy-body a{color:var(--red);text-decoration:underline}
  .policy-body table{width:100%;margin:1rem 0}
  .policy-body th,.policy-body td{padding:.5rem .6rem;border-bottom:1px solid var(--line);font-size:.9rem;text-align:left}
</style>
<?php require __DIR__ . '/footer.php'; ?>
