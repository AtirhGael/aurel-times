<?php
/**
 * One-shot setup: create database, load schema, seed data from data/seed_data.json.
 *
 * DESTRUCTIVE. schema.sql DROPs every table before recreating it. CLI only.
 *
 *   C:\xampp\php\php.exe setup.php                 # refuses if data exists
 *   C:\xampp\php\php.exe setup.php --force         # wipe and reseed anyway
 *   C:\xampp\php\php.exe setup.php --admin-email=you@example.com --admin-password=secret
 *
 * Without --admin-password a strong one is generated and printed once.
 *
 * To add the settings/admin/content tables to an EXISTING install without
 * losing data, run migrate.php instead — it never drops anything.
 */
declare(strict_types=1);
require_once __DIR__ . '/app/config.php';

// This endpoint drops the entire database. It must never be reachable over
// HTTP. Renaming or moving the file does not help: XAMPP serves everything
// under htdocs, including scripts/.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('setup.php is CLI-only. Run: C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\watches\\setup.php');
}

$argvOpts    = $argv ?? [];
$force       = in_array('--force', $argvOpts, true);

/** Read a --name=value argument. */
function opt_value(array $argvOpts, string $name): ?string
{
    foreach ($argvOpts as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return null;
}

$cli = true;
$nl  = "\n";
function out(string $m, string $nl) { echo $m . $nl; @flush(); }

$t0 = microtime(true);

// 1. Connect without database, create it.
try {
    $root = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    out('CONNECT FAILED: ' . $e->getMessage(), $nl);
    out('Check DB_HOST/DB_PORT/DB_USER/DB_PASS in app/config.php', $nl);
    exit(1);
}
$root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
out('✓ database `' . DB_NAME . '` ready', $nl);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1b. Refuse to wipe a store that already holds data.
$existing = ['products' => 0, 'orders' => 0, 'users' => 0];
foreach (array_keys($existing) as $tbl) {
    try {
        $existing[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
    } catch (Throwable $e) {
        $existing[$tbl] = 0; // table not created yet — fresh install
    }
}
if (!$force && array_sum($existing) > 0) {
    out('REFUSING TO RUN — this database already holds data:', $nl);
    foreach ($existing as $tbl => $n) { out(sprintf('    %-10s %d rows', $tbl, $n), $nl); }
    out('', $nl);
    out('setup.php DROPS every table. To add the new settings/admin/content', $nl);
    out('tables without losing this data, run:   php migrate.php', $nl);
    out('To wipe and reseed anyway:              php setup.php --force', $nl);
    exit(1);
}

// 2. Load schema.
$sql = file_get_contents(__DIR__ . '/schema.sql');
if ($sql === false) { out('schema.sql not found', $nl); exit(1); }
// strip line comments, split on semicolons at line ends.
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$stmts = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
foreach ($stmts as $s) {
    if ($s === '') continue;
    $pdo->exec($s);
}
out('✓ schema loaded (' . count($stmts) . ' statements)', $nl);

// 3. Read seed data.
$jsonPath = __DIR__ . '/data/seed_data.json';
if (!is_file($jsonPath)) { out('MISSING ' . $jsonPath . ' — run extract.py first', $nl); exit(1); }
$data = json_decode((string)file_get_contents($jsonPath), true);
if (!$data || empty($data['products'])) { out('seed_data.json empty/invalid', $nl); exit(1); }
$products = $data['products'];
out('✓ loaded ' . count($products) . ' products from JSON', $nl);

function slug(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'brand';
}

// No reviews are seeded. The old --demo-reviews generator wrote machine-made
// text presented as customer reviews, which UK law bans (DMCC Act 2024) and
// Google treats as structured-data spam. Reviews come only from real buyers.

$pdo->beginTransaction();

// Brands
$brandIds = [];
$insBrand = $pdo->prepare('INSERT INTO brands (name, slug) VALUES (?, ?)');
$brandNames = [];
foreach ($products as $p) { $brandNames[$p['brand']] = true; }
$usedSlugs = [];
foreach (array_keys($brandNames) as $bn) {
    $sl = slug($bn); $base = $sl; $i = 2;
    while (isset($usedSlugs[$sl])) { $sl = $base . '-' . $i++; }
    $usedSlugs[$sl] = true;
    $insBrand->execute([$bn, $sl]);
    $brandIds[$bn] = (int)$pdo->lastInsertId();
}
out('✓ ' . count($brandIds) . ' brands', $nl);

$insProd = $pdo->prepare(
    'INSERT INTO products (handle, name, brand_id, mpn, description, base_price, image)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insImg = $pdo->prepare('INSERT INTO product_images (product_id, url, position) VALUES (?, ?, ?)');
$insVar = $pdo->prepare('INSERT INTO variants (product_id, grade, price, sku, in_stock) VALUES (?, ?, ?, ?, ?)');

$nP = $nI = $nV = 0;
$seenHandles = [];
foreach ($products as $p) {
    $handle = $p['handle'];
    if (isset($seenHandles[$handle])) continue;
    $seenHandles[$handle] = true;
    $images = $p['images'] ?? [];
    $mainImg = $images[0] ?? null;
    // Drop junk $0 "Standard" placeholder variants; base_price = cheapest real grade.
    $allVars = $p['variants'] ?? [];
    $validVars = array_values(array_filter($allVars, fn($v) => (float)($v['price'] ?? 0) > 0));
    if (!$validVars) { $validVars = $allVars; }
    $realPrices = array_filter(array_map(fn($v) => (float)($v['price'] ?? 0), $validVars), fn($x) => $x > 0);
    $basePrice = $realPrices ? min($realPrices) : 0;
    $insProd->execute([
        $handle,
        mb_substr($p['name'], 0, 400),
        $brandIds[$p['brand']],
        ($p['mpn'] ?? '') !== '' ? mb_substr($p['mpn'], 0, 120) : null,
        $p['description'] ?? '',
        $basePrice,
        $mainImg ? mb_substr($mainImg, 0, 600) : null,
    ]);
    $pid = (int)$pdo->lastInsertId();
    $nP++;
    $pos = 0;
    foreach ($images as $u) {
        if (!$u) continue;
        $insImg->execute([$pid, mb_substr($u, 0, 600), $pos++]);
        $nI++;
        if ($pos >= 12) break;
    }
    foreach ($validVars as $v) {
        $insVar->execute([
            $pid,
            mb_substr($v['grade'], 0, 120),
            $v['price'] ?? 0,
            ($v['sku'] ?? '') !== '' ? mb_substr($v['sku'], 0, 120) : null,
            !empty($v['in_stock']) ? 1 : 0,
        ]);
        $nV++;
    }
}
$pdo->commit();
out("✓ seeded products=$nP images=$nI variants=$nV", $nl);

// 4. Settings + content pages. Shared with migrate.php so a fresh install and
//    a migrated install can never diverge.
require_once __DIR__ . '/app/seed_defaults.php';
[$sIns, $sSkip] = seed_settings($pdo);
out("✓ settings: $sIns inserted, $sSkip already present", $nl);
[$pIns, $pSkip] = seed_pages($pdo);
out("✓ content pages: $pIns inserted, $pSkip already present", $nl);

// 5. Admin account so a fresh install has a way into /admin.
//
// The password is generated per install and printed once, never hardcoded. A
// fixed password in a file that ships with the project means every install
// starts with an administrator whose credentials are public knowledge.
$adminEmail = opt_value($argvOpts, 'admin-email') ?? 'admin@' . strtolower(str_replace(' ', '', SITE_NAME)) . '.local';
$adminPass  = opt_value($argvOpts, 'admin-password');
$generated  = false;
if ($adminPass === null || strlen($adminPass) < 8) {
    $alphabet  = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $adminPass = '';
    for ($i = 0; $i < 16; $i++) {
        $adminPass .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $generated = true;
}
$pdo->prepare('INSERT INTO users (email, password_hash, name, is_admin) VALUES (?, ?, ?, 1)
               ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_admin = 1')
    ->execute([$adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'Administrator']);

out('', $nl);
out('  ADMIN ACCOUNT ------------------------------------------', $nl);
out('    email:    ' . $adminEmail, $nl);
out('    password: ' . $adminPass, $nl);
if ($generated) {
    out('    (generated for this install — it is not stored anywhere else,', $nl);
    out('     so copy it now. Change it once you are signed in.)', $nl);
}
out('  --------------------------------------------------------', $nl);

out(sprintf('DONE in %.1fs', microtime(true) - $t0), $nl);
out('', $nl);
out('Next: log in, open /watches/admin/ and work through the pre-launch', $nl);
out('checklist on the dashboard — it lists every business detail still set', $nl);
out('to a placeholder value.', $nl);
