<?php
/**
 * XML sitemap. Reachable as /sitemap.php, or /sitemap.xml via the rewrite in
 * .htaccess.
 *
 * Emits nothing but public, indexable URLs — cart, checkout, account, login and
 * the admin panel are all excluded, matching robots.txt.
 */
declare(strict_types=1);
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/pages.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    ['loc' => abs_url('index.php'),   'changefreq' => 'daily',   'priority' => '1.0'],
    ['loc' => abs_url('shop.php'),    'changefreq' => 'daily',   'priority' => '0.9'],
    ['loc' => abs_url('contact.php'), 'changefreq' => 'yearly',  'priority' => '0.5'],
];

foreach (all_pages(true) as $pg) {
    $urls[] = [
        'loc'        => abs_url(ltrim(substr(page_url((string)$pg['slug']), strlen(BASE_URL)), '/')),
        'lastmod'    => date('Y-m-d', strtotime((string)$pg['updated_at'])),
        'changefreq' => 'yearly',
        'priority'   => '0.4',
    ];
}

try {
    $pdo = db_try();
    if ($pdo) {
        foreach ($pdo->query('SELECT slug FROM brands ORDER BY name') as $b) {
            $urls[] = [
                'loc'        => abs_url('shop.php?brand=' . rawurlencode((string)$b['slug'])),
                'changefreq' => 'weekly',
                'priority'   => '0.6',
            ];
        }
        $stmt = $pdo->query('SELECT handle, created_at FROM products ORDER BY id');
        foreach ($stmt as $p) {
            $urls[] = [
                'loc'        => abs_url('product.php?handle=' . rawurlencode((string)$p['handle'])),
                'lastmod'    => date('Y-m-d', strtotime((string)$p['created_at'])),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[sitemap] ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
