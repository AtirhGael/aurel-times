<?php
/**
 * Generic content page renderer: /page.php?slug=some-page
 *
 * Pages created in admin work through this immediately. Slugs listed in
 * PAGE_STUBS (app/pages.php) additionally get a pretty /slug.php URL.
 */
declare(strict_types=1);
$slug = trim((string)($_GET['slug'] ?? ''));
require __DIR__ . '/app/page_render.php';
