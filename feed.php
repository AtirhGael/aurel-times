<?php
/**
 * Google Shopping product feed (RSS 2.0 + the g: namespace).
 *
 * ---------------------------------------------------------------------------
 * Every item is sold under the house brand (setting site_name, "Aurel Time").
 * The brands table holds the six collections, which go into g:product_type.
 *
 * One gate remains before submitting. Merchant Center also reads the product
 * IMAGE, and products.image still points at the supplier CDN, showing dials
 * with third-party trademarks on them. Submitting on those images invites the
 * same suspension the titles used to. Replace the imagery with your own
 * photography first, then set feed_token to enable this endpoint.
 * ---------------------------------------------------------------------------
 *
 * Access: /feed.php?token=<feed_token setting>   (or /feed.xml?token=...)
 */
declare(strict_types=1);
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/_schema.php';

$token = setting('feed_token');
if ($token === '' || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/xml; charset=utf-8');

$currency = setting('currency_code') !== '' ? setting('currency_code') : 'GBP';
$country  = store_country_code();
$house    = setting('site_name');
$shipRate = shipping_cost(0.0); // worst case: an order below any free threshold

$pdo = db();
$rows = $pdo->query(
    "SELECT p.id, p.handle, p.name, p.description, p.base_price, p.compare_at_price,
            p.image, p.mpn, p.gtin, p.item_condition, p.google_category,
            b.name AS brand,
            (SELECT MIN(v.price) FROM variants v WHERE v.product_id = p.id AND v.price > 0) AS min_price,
            (SELECT MAX(v.in_stock) FROM variants v WHERE v.product_id = p.id) AS any_stock
     FROM products p JOIN brands b ON b.id = p.brand_id
     WHERE p.base_price > 0
     ORDER BY p.id"
);

$x = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
echo "<channel>\n";
echo '  <title>' . $x(setting('site_name')) . "</title>\n";
echo '  <link>' . $x(site_url()) . "</link>\n";
echo '  <description>' . $x(setting('meta_description')) . "</description>\n";

$imgSt  = $pdo->prepare('SELECT url FROM product_images WHERE product_id = ? ORDER BY position LIMIT 11');

foreach ($rows as $p) {
    $price = (float)($p['min_price'] ?? 0) > 0 ? (float)$p['min_price'] : (float)$p['base_price'];
    if ($price <= 0 || empty($p['image'])) {
        continue; // Merchant Center rejects items with no price or no image.
    }
    $desc = trim(strip_tags((string)$p['description']));
    if ($desc === '') {
        $desc = $p['name'] . ', from the ' . $p['brand'] . ' collection.';
    }
    $link = abs_url('product.php?handle=' . rawurlencode((string)$p['handle']));

    echo "  <item>\n";
    echo '    <g:id>' . $x((string)$p['id']) . "</g:id>\n";
    echo '    <g:title>' . $x(mb_substr((string)$p['name'], 0, 150)) . "</g:title>\n";
    echo '    <g:description>' . $x(mb_substr($desc, 0, 5000)) . "</g:description>\n";
    echo '    <g:link>' . $x($link) . "</g:link>\n";
    echo '    <g:image_link>' . $x((string)$p['image']) . "</g:image_link>\n";

    $imgSt->execute([(int)$p['id']]);
    $extra = 0;
    foreach ($imgSt->fetchAll(PDO::FETCH_COLUMN) as $u) {
        if ((string)$u === (string)$p['image'] || $extra >= 10) {
            continue;
        }
        echo '    <g:additional_image_link>' . $x((string)$u) . "</g:additional_image_link>\n";
        $extra++;
    }

    echo '    <g:availability>' . ((int)$p['any_stock'] === 1 ? 'in_stock' : 'out_of_stock') . "</g:availability>\n";
    echo '    <g:condition>' . $x((string)($p['item_condition'] ?: 'new')) . "</g:condition>\n";
    // Exactly one g:price per item. With a genuine compare_at_price, g:price is the
    // reference and g:sale_price the selling price; otherwise g:price is the price.
    $compare = (float)($p['compare_at_price'] ?? 0);
    if ($compare > $price) {
        echo '    <g:price>' . number_format($compare, 2, '.', '') . ' ' . $x($currency) . "</g:price>\n";
        echo '    <g:sale_price>' . number_format($price, 2, '.', '') . ' ' . $x($currency) . "</g:sale_price>\n";
    } else {
        echo '    <g:price>' . number_format($price, 2, '.', '') . ' ' . $x($currency) . "</g:price>\n";
    }

    echo '    <g:brand>' . $x($house) . "</g:brand>\n";
    echo '    <g:product_type>' . $x('Watches > ' . $p['brand'] . ' collection') . "</g:product_type>\n";
    $hasId = false;
    if (!empty($p['gtin'])) { echo '    <g:gtin>' . $x((string)$p['gtin']) . "</g:gtin>\n"; $hasId = true; }
    if (!empty($p['mpn']))  { echo '    <g:mpn>' . $x((string)$p['mpn']) . "</g:mpn>\n";  $hasId = true; }
    echo '    <g:identifier_exists>' . ($hasId ? 'yes' : 'no') . "</g:identifier_exists>\n";

    // 201 = Apparel & Accessories > Jewelry > Watches
    echo '    <g:google_product_category>' . $x((string)($p['google_category'] ?: '201')) . "</g:google_product_category>\n";

    echo "    <g:shipping>\n";
    echo '      <g:country>' . $x($country) . "</g:country>\n";
    echo '      <g:price>' . number_format($shipRate, 2, '.', '') . ' ' . $x($currency) . "</g:price>\n";
    echo "    </g:shipping>\n";
    echo '    <g:max_handling_time>' . setting_int('ship_processing_days', 1) . "</g:max_handling_time>\n";
    echo '    <g:min_transit_time>' . setting_int('ship_delivery_min_days', 5) . "</g:min_transit_time>\n";
    echo '    <g:max_transit_time>' . setting_int('ship_delivery_max_days', 20) . "</g:max_transit_time>\n";
    echo "  </item>\n";
}

echo "</channel>\n</rss>\n";
