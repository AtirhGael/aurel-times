<?php
declare(strict_types=1);

/**
 * De-branding migration.
 *
 * Strips every third-party trademark from the catalogue and re-issues the whole
 * range under the house brand. The old catalogue carried real maison names
 * (Rolex, Patek Philippe, Audemars Piguet, ...), real model marks (Submariner,
 * Daytona, Nautilus, ...), the maisons' own reference numbers, and grade labels
 * that said "Replica" / "Clone" outright. All of it goes.
 *
 * What replaces it:
 *   - brands table  -> six house COLLECTIONS (the schema keeps the table name)
 *   - product name  -> "<Collection> <size> <dial> Dial <material>"
 *   - product mpn   -> house reference, e.g. AT-TD-0142
 *   - handle        -> slug of the new name (site is noindex, so no SEO equity
 *                      is lost by changing URLs; no redirects needed)
 *   - description   -> rewritten from parsed attributes only. Nothing is
 *                      invented: if an attribute was not present in the source
 *                      name, it is omitted rather than guessed.
 *   - variants.grade-> neutral product tiers, no quality claims
 *
 * Deliberately NOT touched:
 *   - order_items    historical order records; rewriting them would falsify
 *                    what a customer actually bought.
 *   - products.image supplier CDN photos still show trademarked dials. No
 *                    script can fix that; it needs real photography.
 *
 * Usage:
 *   php scripts/debrand.php            # dry run, prints samples, writes nothing
 *   php scripts/debrand.php --commit   # applies the migration
 */

require_once __DIR__ . '/../app/helpers.php';

const HOUSE = 'AUREL TIME';

$COMMIT = in_array('--commit', $argv, true);

/**
 * Collections. Keyed by internal code; `ref` is the house reference infix.
 *
 * These names are unregistered. Run a trademark clearance search before putting
 * any of them on a dial or filing for a mark — that is a legal check, not one a
 * migration script can make.
 */
const COLLECTIONS = [
    // `blurb` is a predicate phrase: the opener supplies the noun, the blurb
    // supplies the verb, so the two always compose into a real sentence.
    'diver'  => ['name' => 'Tideline', 'ref' => 'TD', 'blurb' => 'is built to dive, with a rotating bezel and serious water resistance'],
    'chrono' => ['name' => 'Circuit',  'ref' => 'CR', 'blurb' => 'carries a stopwatch complication and motorsport-inspired styling'],
    'gmt'    => ['name' => 'Transit',  'ref' => 'TR', 'blurb' => 'tracks a second timezone, for people who cross them often'],
    'dress'  => ['name' => 'Ascot',    'ref' => 'AS', 'blurb' => 'keeps a slim profile that sits cleanly under a cuff'],
    'sport'  => ['name' => 'Monolith', 'ref' => 'MN', 'blurb' => 'pairs an integrated bracelet with a hard-edged case'],
    'field'  => ['name' => 'Datum',    'ref' => 'DT', 'blurb' => 'is a plain-spoken everyday watch made for daily wear'],
];

/** Source model mark -> collection. Longest match wins, so order matters. */
const ARCHETYPES = [
    'Sea-Dweller' => 'diver',  'Submariner' => 'diver',  'Deepsea' => 'diver',
    'Yacht-Master' => 'diver', 'Seamaster' => 'diver',   'Superocean' => 'diver',
    'Luminor' => 'diver',      'Radiomir' => 'diver',    'Diver' => 'diver',

    'Daytona' => 'chrono',     'Speedmaster' => 'chrono', 'Navitimer' => 'chrono',
    'Chronomat' => 'chrono',   'Chronograph' => 'chrono', 'Big Bang' => 'chrono',

    'GMT-Master' => 'gmt',     'GMT' => 'gmt',            'Explorer II' => 'gmt',

    'Datejust' => 'dress',     'Day-Date' => 'dress',     'Cellini' => 'dress',
    'Calatrava' => 'dress',    'Tank' => 'dress',         'Santos' => 'dress',
    'De Ville' => 'dress',     'Constellation' => 'dress', 'Ballon' => 'dress',
    'Prince' => 'dress',

    'Royal Oak' => 'sport',    'Nautilus' => 'sport',     'Aquanaut' => 'sport',
    'Classic Fusion' => 'sport', 'Spirit' => 'sport',     'RM0' => 'sport',
    'RM1' => 'sport',          'RM2' => 'sport',

    'Air-King' => 'field',     'Oyster Perpetual' => 'field', 'Milgauss' => 'field',
    'Explorer' => 'field',     'Perpetual' => 'field',
];

/**
 * Grade relabelling. The source values were build-quality claims for counterfeit
 * goods. The replacements are line tiers only — they assert nothing about
 * movement origin or materials, because those specs are not known here. Fill in
 * real specs from your supplier before publishing spec tables.
 */
const GRADE_MAP = [
    'A Replica'              => 'Core',
    'A Replica Movement'     => 'Core',
    'AAA'                    => 'Signature',
    'AAA Replica'            => 'Signature',
    'AAA Replica Movement'   => 'Signature',
    'AAAAA Replica'          => 'Elite',
    'AAAAA Replica Movement' => 'Elite',
    'Superclone'             => 'Reserve',
    'Japanse'                => 'Standard',
];

/** Every mark that must not survive anywhere in generated output. */
const TM_TERMS = [
    'Rolex', 'Omega', 'Cartier', 'Breitling', 'Hublot', 'Audemars', 'Piguet',
    'Patek', 'Philippe', 'Panerai', 'Richard Mille', 'Tudor', 'Submariner',
    'Sea-Dweller', 'Deepsea', 'Yacht-Master', 'Daytona', 'Speedmaster',
    'Navitimer', 'Chronomat', 'GMT-Master', 'Datejust', 'Day-Date', 'Cellini',
    'Calatrava', 'Santos', 'De Ville', 'Royal Oak', 'Nautilus', 'Aquanaut',
    'Big Bang', 'Classic Fusion', 'Luminor', 'Radiomir', 'Milgauss', 'Air-King',
    'Oyster', 'Perpetual', 'Constellation', 'Ballon Bleu', 'Superocean',
    'Explorer', 'Replica', 'Clone', 'Superclone', 'Swiss Made',
];

/** Dial colours, longest-first so "Rose Gold" beats "Gold". */
const DIALS = [
    'Rose Gold', 'Two Tone', 'Champagne', 'Chocolate', 'Turquoise', 'Burgundy',
    'Anthracite', 'Salmon', 'Silver', 'Bronze', 'Purple', 'Yellow', 'Orange',
    'Ivory', 'Slate', 'Green', 'Brown', 'Black', 'White', 'Blue', 'Gold',
    'Grey', 'Gray', 'Pink', 'Red',
];

const MATERIALS = [
    'Stainless Steel' => 'Stainless Steel', 'Rose Gold' => 'Rose Gold Tone',
    'Yellow Gold' => 'Gold Tone', 'Two Tone' => 'Two-Tone', 'Alligator' => 'Leather Strap',
    'Leather' => 'Leather Strap', 'Rubber' => 'Rubber Strap', 'Ceramic' => 'Ceramic',
    'Titanium' => 'Titanium', 'Carbon' => 'Carbon Composite', 'Platinum' => 'Platinum Tone',
    'Steel' => 'Stainless Steel',
];

// ---------------------------------------------------------------- helpers

function tm_hits(string $s): array {
    $hits = [];
    foreach (TM_TERMS as $t) {
        if (stripos($s, $t) !== false) $hits[] = $t;
    }
    return $hits;
}

// slugify() comes from app/helpers.php — same behaviour, kept single-sourced so
// generated handles match the format the rest of the site already produces.

function classify(string $name): string {
    foreach (ARCHETYPES as $needle => $coll) {
        if (stripos($name, $needle) !== false) return $coll;
    }
    return 'field'; // safe default: plain everyday watch
}

/** Pull only genuinely descriptive attributes out of the old name. */
function parse_attrs(string $name): array {
    $a = ['size' => null, 'dial' => null, 'material' => null, 'movement' => null];

    if (preg_match('/\b(\d{2})\s?mm\b/i', $name, $m)) {
        $sz = (int)$m[1];
        if ($sz >= 22 && $sz <= 50) $a['size'] = $sz . 'mm';
    }
    foreach (DIALS as $d) {
        if (preg_match('/\b' . preg_quote($d, '/') . '\b/i', $name)) { $a['dial'] = $d; break; }
    }
    foreach (MATERIALS as $needle => $label) {
        if (stripos($name, $needle) !== false) { $a['material'] = $label; break; }
    }
    if (stripos($name, 'Automatic') !== false)   $a['movement'] = 'automatic';
    elseif (stripos($name, 'Quartz') !== false)  $a['movement'] = 'quartz';

    if ($a['dial'] === 'Gray') $a['dial'] = 'Grey';
    return $a;
}

function build_name(array $coll, array $a): string {
    $parts = [HOUSE, $coll['name']];
    if ($a['size'])     $parts[] = $a['size'];
    if ($a['dial'])     $parts[] = $a['dial'] . ' Dial';
    if ($a['material']) $parts[] = $a['material'];
    return implode(' ', $parts);
}

function build_description(array $coll, array $a, string $ref, int $seed): string {
    // Every opener is a bare noun phrase; COLLECTIONS['blurb'] supplies the verb.
    $openers = [
        'A {noun} from the {coll} line',
        'The {coll} {noun}',
        'This {coll} {noun}',
        'Part of the {coll} collection, this {noun}',
        'This {noun}, from our {coll} range,',
        'The {noun} in our {coll} line',
    ];
    $noun = $a['size'] ? $a['size'] . ' watch' : 'watch';
    $open = strtr($openers[$seed % count($openers)], [
        '{noun}' => $noun,
        '{coll}' => $coll['name'],
    ]);

    $spec = [];
    if ($a['dial'])     $spec[] = 'a ' . strtolower($a['dial']) . ' dial';
    if ($a['material']) $spec[] = strtolower($a['material']);
    if ($a['movement']) $spec[] = ($a['movement'] === 'automatic' ? 'an ' : 'a ') . $a['movement'] . ' movement';

    $specSentence = '';
    if ($spec) {
        $last = array_pop($spec);
        $specSentence = ' It has ' . ($spec ? implode(', ', $spec) . ' and ' : '') . $last . '.';
    }

    $ret = setting_int('return_window_days') ?: 30;
    $war = setting_int('warranty_months') ?: 24;

    $text = $open . ' ' . $coll['blurb'] . '.' . $specSentence
          . ' Reference ' . $ref . '.'
          . ' Every ' . HOUSE . ' timepiece is checked by hand in Manchester before it ships,'
          . ' travels tracked and insured worldwide, and carries a ' . $war . '-month warranty'
          . ' with a ' . $ret . '-day return window.';

    if (strlen($text) < 200) {
        $text .= ' Questions about sizing, strap options or delivery? Message the'
               . ' workshop directly and a person will answer.';
    }
    return $text;
}

// ---------------------------------------------------------------- migration

$pdo = db();
printf("=== DE-BRANDING MIGRATION (%s) ===\n\n", $COMMIT ? 'COMMIT' : 'DRY RUN');

$products = $pdo->query(
    'SELECT p.id, p.name, p.handle, p.mpn, p.base_price, b.name AS brand
     FROM products p LEFT JOIN brands b ON b.id = p.brand_id
     ORDER BY p.id'
)->fetchAll(PDO::FETCH_ASSOC);

// Pass 1 — plan every row.
$plan = [];
$counters = [];
$dist = [];
$usedHandles = [];
$usedNames   = [];

foreach ($products as $p) {
    $key  = classify($p['name']);
    $coll = COLLECTIONS[$key];
    $counters[$key] = ($counters[$key] ?? 0) + 1;
    $ref  = sprintf('AT-%s-%04d', $coll['ref'], $counters[$key]);
    $a    = parse_attrs($p['name']);

    // Parsed attributes are coarse, so many products reduce to the same label.
    // Duplicate display names would be useless to a shopper and would read as
    // thin content to a crawler, so a collided name earns its reference.
    $newName = build_name($coll, $a);
    if (isset($usedNames[$newName])) $newName .= ' ' . $ref;
    $usedNames[$newName] = true;

    // A collided name already carries the ref; don't stutter it into the URL.
    $handle  = slugify(str_contains($newName, $ref) ? $newName : $newName . ' ' . $ref);
    if (isset($usedHandles[$handle])) $handle .= '-' . $p['id'];
    $usedHandles[$handle] = true;

    $plan[] = [
        'id'    => (int)$p['id'],
        'coll'  => $key,
        'old'   => $p['name'],
        'name'  => $newName,
        'handle'=> $handle,
        'mpn'   => $ref,
        'desc'  => build_description($coll, $a, $ref, (int)$p['id']),
    ];
    $dist[$key] = ($dist[$key] ?? 0) + 1;
}

// Pass 2 — assert the plan is clean before a single write happens.
$dirty = [];
foreach ($plan as $row) {
    foreach (['name', 'handle', 'desc', 'mpn'] as $f) {
        $hits = tm_hits((string)$row[$f]);
        if ($hits) $dirty[] = "#{$row['id']} $f: " . implode(',', $hits) . " -> {$row[$f]}";
    }
}
if ($dirty) {
    echo "ABORT — generated output still contains trademarks:\n";
    foreach (array_slice($dirty, 0, 20) as $d) echo "  $d\n";
    printf("  (%d total)\n", count($dirty));
    exit(1);
}
echo "Trademark assertion: PASS — 0 marks in " . count($plan) . " generated rows.\n\n";

echo "Collection distribution:\n";
foreach ($dist as $k => $n) printf("  %-10s %-10s %5d products\n", $k, COLLECTIONS[$k]['name'], $n);

echo "\nSample renames:\n";
foreach ([0, 200, 500, 800, 1100] as $i) {
    if (!isset($plan[$i])) continue;
    printf("  OLD  %s\n  NEW  %s\n  URL  %s\n  DESC %s\n\n",
        $plan[$i]['old'], $plan[$i]['name'], $plan[$i]['handle'],
        substr($plan[$i]['desc'], 0, 150) . '...');
}

$nameCounts = array_count_values(array_column($plan, 'name'));
$dupNames   = array_filter($nameCounts, fn($c) => $c > 1);
printf("Name uniqueness: %d distinct names across %d products (%d collisions)\n\n",
    count($nameCounts), count($plan), array_sum($dupNames) - count($dupNames));

$lens = array_map(fn($r) => strlen($r['desc']), $plan);
printf("Description length: min %d, avg %d, max %d (target >=200)\n\n", min($lens), (int)array_sum($lens) / count($lens), max($lens));

// Grade remap preview
$grades = $pdo->query('SELECT grade, COUNT(*) c FROM variants GROUP BY grade')->fetchAll(PDO::FETCH_ASSOC);
echo "Variant grade remap:\n";
foreach ($grades as $g) {
    $new = GRADE_MAP[$g['grade']] ?? $g['grade'];
    printf("  %-24s -> %-12s (%d rows)%s\n", $g['grade'], $new, $g['c'],
        $new === $g['grade'] ? '  [unchanged]' : '');
}

// Data-quality flags that marketing will trip over later.
$gtin = $pdo->query("SELECT COUNT(*) FROM products WHERE gtin IS NOT NULL AND gtin <> ''")->fetchColumn();
$revs = $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
echo "\nFlags:\n";
printf("  products carrying a gtin: %d  (a GTIN belongs to the original maison — must be cleared)\n", $gtin);
printf("  reviews in table:         %d  (seeded, not from real customers)\n", $revs);

if (!$COMMIT) {
    echo "\nDry run complete. Nothing written. Re-run with --commit to apply.\n";
    exit(0);
}

// ---------------------------------------------------------------- write

$pdo->beginTransaction();
try {
    // Collections replace brands. Insert new, remap, drop old.
    $oldBrandIds = $pdo->query('SELECT id FROM brands')->fetchAll(PDO::FETCH_COLUMN);

    $collId = [];
    $ins = $pdo->prepare('INSERT INTO brands (name, slug) VALUES (?, ?)');
    foreach (COLLECTIONS as $k => $c) {
        $ins->execute([$c['name'], slugify($c['name'])]);
        $collId[$k] = (int)$pdo->lastInsertId();
    }

    $up = $pdo->prepare(
        'UPDATE products SET name = ?, handle = ?, mpn = ?, description = ?, brand_id = ?, gtin = NULL WHERE id = ?'
    );
    foreach ($plan as $row) {
        $up->execute([$row['name'], $row['handle'], $row['mpn'], $row['desc'], $collId[$row['coll']], $row['id']]);
    }

    if ($oldBrandIds) {
        $in = implode(',', array_map('intval', $oldBrandIds));
        $pdo->exec("DELETE FROM brands WHERE id IN ($in)");
    }

    $ug = $pdo->prepare('UPDATE variants SET grade = ? WHERE grade = ?');
    foreach (GRADE_MAP as $from => $to) $ug->execute([$to, $from]);

    $pdo->commit();
    echo "\nCommitted: " . count($plan) . " products, " . count(COLLECTIONS) . " collections.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nROLLED BACK: " . $e->getMessage() . "\n";
    exit(1);
}
