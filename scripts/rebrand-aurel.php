<?php
declare(strict_types=1);

/**
 * Rebrand migration: ALEX CLEAN -> AUREL TIME.
 *
 * Renames the house brand everywhere it lives in data:
 *   - settings       site_name, logo_text_a/b, meta_description
 *   - products.name  "ALEX CLEAN Tideline ..."  -> "AUREL TIME Tideline ..."
 *   - products.description  brand mentions and "Reference AC-XX-NNNN"
 *   - products.mpn   AC-XX-NNNN -> AT-XX-NNNN (house reference, initials follow the brand)
 *   - products.handle alex-clean-...-ac-xx-nnnn -> aurel-time-...-at-xx-nnnn
 *                    (site is noindex, so no SEO equity is lost; no redirects needed)
 *
 * Deliberately NOT touched:
 *   - contact/support/orders emails, address, phone, WhatsApp: the business
 *     location and mailboxes are unchanged, and the mailboxes live on the
 *     existing domain.
 *   - order_items    historical order records; rewriting them would falsify
 *                    what a customer actually bought.
 *
 * Idempotent: every replacement is a no-op on already-rebranded rows.
 *
 * Usage:
 *   php scripts/rebrand-aurel.php            # dry run, prints samples, writes nothing
 *   php scripts/rebrand-aurel.php --commit   # applies the migration
 */

require_once __DIR__ . '/../app/helpers.php';

const OLD_HOUSE = 'ALEX CLEAN';
const NEW_HOUSE = 'AUREL TIME';

/** Identity settings, set outright. */
const SETTINGS = [
    'site_name'   => 'Aurel Time',
    'logo_text_a' => 'AUREL TIME',
    'logo_text_b' => 'LUXURY WATCHES',
];

$COMMIT = in_array('--commit', $argv, true);

function rebrand_text(string $s): string {
    $s = str_replace([OLD_HOUSE . ' Timepieces', OLD_HOUSE], ['Aurel Time', NEW_HOUSE], $s);
    return preg_replace('/\bAC-([A-Z]{2}-\d{4})\b/', 'AT-$1', $s);
}

function rebrand_handle(string $h): string {
    $h = preg_replace('/^alex-clean-/', 'aurel-time-', $h);
    return preg_replace('/-ac-([a-z]{2}-\d{4})/', '-at-$1', $h);
}

$pdo = db();
printf("=== REBRAND MIGRATION (%s) ===\n\n", $COMMIT ? 'COMMIT' : 'DRY RUN');

// Settings: fixed identity values, plus a text rewrite of meta_description.
$setPlan = SETTINGS;
$meta = $pdo->query("SELECT svalue FROM settings WHERE skey = 'meta_description'")->fetchColumn();
if ($meta !== false) $setPlan['meta_description'] = rebrand_text((string)$meta);

echo "Settings:\n";
foreach ($setPlan as $k => $v) printf("  %-17s -> %s\n", $k, $v);

// Products.
$rows = $pdo->query('SELECT id, name, handle, mpn, description FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$plan = [];
foreach ($rows as $r) {
    $new = [
        'name'        => rebrand_text((string)$r['name']),
        'handle'      => rebrand_handle((string)$r['handle']),
        'mpn'         => $r['mpn'] === null ? null : rebrand_text((string)$r['mpn']),
        'description' => $r['description'] === null ? null : rebrand_text((string)$r['description']),
    ];
    if ($new['name'] !== $r['name'] || $new['handle'] !== $r['handle']
        || $new['mpn'] !== $r['mpn'] || $new['description'] !== $r['description']) {
        $plan[(int)$r['id']] = $new;
    }
}

$handles = array_column($rows, 'handle', 'id');
foreach ($plan as $id => $p) $handles[$id] = $p['handle'];
if (count($handles) !== count(array_unique($handles))) {
    echo "ABORT — rebranded handles collide.\n";
    exit(1);
}

printf("\nProducts to update: %d of %d\n", count($plan), count($rows));
foreach (array_slice($plan, 0, 3, true) as $id => $p) {
    printf("  #%d %s\n      %s\n      %s\n", $id, $p['name'], $p['handle'], substr((string)$p['description'], -190));
}

if (!$COMMIT) {
    echo "\nDry run complete. Nothing written. Re-run with --commit to apply.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $us = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    foreach ($setPlan as $k => $v) $us->execute([$v, $k]);

    $up = $pdo->prepare('UPDATE products SET name = ?, handle = ?, mpn = ?, description = ? WHERE id = ?');
    foreach ($plan as $id => $p) $up->execute([$p['name'], $p['handle'], $p['mpn'], $p['description'], $id]);

    $pdo->commit();
    printf("\nCommitted: %d settings, %d products.\n", count($setPlan), count($plan));
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nROLLED BACK: " . $e->getMessage() . "\n";
    exit(1);
}
