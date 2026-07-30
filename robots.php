<?php
/**
 * robots.txt, generated.
 *
 * The old static robots.txt hardcoded every path as /watches/… and pointed at a
 * relative "Sitemap: /watches/sitemap.xml". Both are wrong the moment the site
 * is served from a domain root instead of a subfolder — and a robots file full
 * of paths that do not exist protects nothing.
 *
 * Everything here is built from BASE_URL, so the same file is correct whether
 * the store lives at /watches/ or at https://example.site/.
 *
 * Reached as /robots.txt via the rewrite in .htaccess.
 */
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/db.php';
require __DIR__ . '/app/settings.php';
require __DIR__ . '/app/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

// Search indexing ships OFF and is flipped in Admin → Settings → SEO. While it
// is off, say so here as well as in the per-page robots meta tag: a crawler
// that never fetches a page never sees the meta tag.
if (!setting_bool('robots_index')) {
    echo "# Indexing is disabled for this site (Admin → Settings → SEO).\n";
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

$base = BASE_URL;   // '/' at a domain root, '/watches/' in a subfolder.

$disallow = [
    // Private / transactional areas — nothing here belongs in an index.
    'admin/',
    'cart.php',
    'checkout.php',
    'account.php',
    'login.php',
    'register.php',
    'logout.php',
    'setup.php',
    'migrate.php',
    'feed.php',
    'app/',
    'data/',
    'scripts/',
];

echo "User-agent: *\n";
foreach ($disallow as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}

// Faceted URLs — sorted and paged variants duplicate the canonical listing.
echo 'Disallow: ' . $base . "shop.php?*sort=\n";
echo 'Disallow: ' . $base . "shop.php?*page=\n";

// Absolute URL: the Sitemap directive is the one line in this file that a
// relative path is invalid for.
echo "\nSitemap: " . abs_url('sitemap.xml') . "\n";
