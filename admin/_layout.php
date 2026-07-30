<?php
/**
 * Admin shell — opening half. Pair with _layout_end.php.
 *
 * Intentionally NOT app/header.php. That file fires two storefront queries per
 * render, emits the announcement bar, mega menu, search overlay, mobile menu
 * and ~150 lines of storefront CSS, and calls cart_count(). None of that
 * belongs here, and sharing it would mean every nav tweak risks breaking admin.
 *
 * @var string $adminTitle
 */
declare(strict_types=1);
$adminTitle = $adminTitle ?? 'Dashboard';
$adminNav = [
    ''              => ['Dashboard',  '▤'],
    'settings.php'  => ['Settings',   '⚙'],
    'pages.php'     => ['Pages',      '▦'],
    'orders.php'    => ['Orders',     '▣'],
    'messages.php'  => ['Messages',   '✉'],
    'reviews.php'   => ['Reviews',    '★'],
];
$currentFile = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($currentFile === 'index.php') {
    $currentFile = '';
}

$unreadCount = 0;
try {
    $pdo = db_try();
    if ($pdo) {
        $unreadCount = (int)$pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    }
} catch (Throwable $e) {
    $unreadCount = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($adminTitle) ?> · Admin · <?= h(setting('site_name')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{--ink:#16181d;--line:#e3e5e9;--muted:#6b7280;--red:#c8102e;--bg:#f6f7f9}
  body{background:var(--bg);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
  a{color:var(--red);text-decoration:none} a:hover{text-decoration:underline}
  .adm-wrap{display:flex;min-height:100vh}
  .adm-side{width:230px;flex:0 0 230px;background:var(--ink);color:#cfd3da;padding:18px 0;position:sticky;top:0;height:100vh;overflow-y:auto}
  /* Same logo_html() as the storefront, but the 230px sidebar needs the second half
     inline so the "admin" tag sits beside it rather than on a third line. */
  .adm-brand{color:#fff;font-weight:700;font-size:1rem;padding:0 20px 16px;display:block;line-height:1.2}
  .adm-brand .lg-a{display:block;letter-spacing:1.4px}
  .adm-brand .lg-b{color:var(--red);font-size:.62rem;letter-spacing:2.4px}
  .adm-side a.nav-i{display:flex;align-items:center;gap:10px;padding:10px 20px;color:#cfd3da;font-size:.9rem}
  .adm-side a.nav-i:hover{background:rgba(255,255,255,.06);color:#fff;text-decoration:none}
  .adm-side a.nav-i.on{background:var(--red);color:#fff}
  .adm-side .ico{width:18px;text-align:center;opacity:.9}
  .adm-side .pill{margin-left:auto;background:var(--red);color:#fff;border-radius:10px;font-size:.68rem;padding:1px 7px}
  .adm-side a.nav-i.on .pill{background:#fff;color:var(--red)}
  .adm-side .sep{border-top:1px solid rgba(255,255,255,.1);margin:14px 20px}
  .adm-main{flex:1;min-width:0;padding:26px 30px 60px}
  .adm-head{display:flex;align-items:center;gap:14px;margin-bottom:22px}
  .adm-head h1{font-size:1.35rem;font-weight:600;margin:0}
  .card-a{background:#fff;border:1px solid var(--line);border-radius:8px}
  .card-a .hd{padding:14px 18px;border-bottom:1px solid var(--line);font-weight:600;font-size:.95rem;display:flex;align-items:center;gap:10px}
  .card-a .bd{padding:18px}
  .stat{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px 18px}
  .stat .k{font-size:.75rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted)}
  .stat .v{font-size:1.5rem;font-weight:600;margin-top:2px}
  .table{font-size:.9rem;margin:0}
  .table thead th{font-size:.72rem;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:600;border-bottom-width:1px}
  .table td,.table th{vertical-align:middle}
  .form-label{font-size:.8rem;font-weight:600;color:#3f434b;margin-bottom:.25rem}
  .form-text{font-size:.78rem}
  .form-control,.form-select{font-size:.9rem;border-color:var(--line)}
  .form-control:focus,.form-select:focus{border-color:var(--red);box-shadow:0 0 0 .2rem rgba(200,16,46,.1)}
  .btn-primary{background:var(--red);border-color:var(--red)}
  .btn-primary:hover{background:#a60d26;border-color:#a60d26}
  .grp{scroll-margin-top:20px}
  .grp+.grp{margin-top:20px}
  .chk-row{display:flex;align-items:flex-start;gap:9px}
  .sticky-save{position:sticky;bottom:0;background:rgba(246,247,249,.94);backdrop-filter:blur(6px);
    border-top:1px solid var(--line);padding:12px 0;margin-top:22px;z-index:5}
  code.ph{background:#f0f1f4;border:1px solid var(--line);border-radius:3px;padding:1px 5px;font-size:.82rem}
  @media(max-width:820px){
    .adm-wrap{flex-direction:column}
    .adm-side{width:100%;flex:none;height:auto;position:static;display:flex;flex-wrap:wrap;padding:10px}
    .adm-brand{width:100%;padding:6px 10px 10px}
    .adm-side a.nav-i{padding:8px 12px} .adm-side .sep{display:none}
    .adm-main{padding:18px 14px 50px}
  }
</style>
</head>
<body>
<div class="adm-wrap">
  <aside class="adm-side">
    <a class="adm-brand" href="<?= admin_url() ?>"><?= logo_html() ?> <small style="opacity:.6">admin</small></a>
    <?php foreach ($adminNav as $file => [$label, $icon]): ?>
      <a class="nav-i<?= $currentFile === $file ? ' on' : '' ?>" href="<?= admin_url($file) ?>">
        <span class="ico"><?= $icon ?></span><?= h($label) ?>
        <?php if ($file === 'messages.php' && $unreadCount > 0): ?>
          <span class="pill"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
    <div class="sep"></div>
    <a class="nav-i" href="<?= url('index.php') ?>" target="_blank"><span class="ico">↗</span>View store</a>
    <a class="nav-i" href="<?= url('logout.php') ?>"><span class="ico">⏻</span>Sign out</a>
    <div class="px-3 pt-3 small" style="color:#7b8290;font-size:.75rem">
      <?= h((string)($ADMIN_USER['email'] ?? '')) ?>
    </div>
  </aside>

  <main class="adm-main">
    <?php if ($f = flash()): ?>
      <div class="alert alert-success py-2 small"><?= h($f) ?></div>
    <?php endif; ?>
    <div class="adm-head">
      <h1><?= h($adminTitle) ?></h1>
      <?php if (!empty($adminHeadExtra)) { echo $adminHeadExtra; } ?>
    </div>
