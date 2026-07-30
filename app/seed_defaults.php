<?php
/**
 * Idempotent seeding of settings and content pages.
 *
 * Shared by BOTH migrate.php and setup.php on purpose: if each seeded its own
 * copy, a fresh install and a migrated install would silently drift apart.
 */
declare(strict_types=1);
require_once __DIR__ . '/settings.php';

/**
 * Insert any settings key that does not exist yet.
 *
 * ON DUPLICATE KEY UPDATE skey = skey is a deliberate no-op: re-running must
 * never overwrite a value the operator has edited. INSERT IGNORE would do the
 * same job but would also swallow real errors like truncation or charset
 * failures, so it is avoided.
 *
 * @return array{0:int,1:int} [inserted, already present]
 */
function seed_settings(PDO $pdo, bool $force = false): array
{
    $defaults = setting_defaults();
    $sql = $force
        ? 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        : 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE skey = skey';
    $st = $pdo->prepare($sql);

    $inserted = 0;
    $skipped  = 0;
    foreach ($defaults as $key => $value) {
        $st->execute([$key, $value]);
        // rowCount(): 1 = inserted, 0 = untouched, 2 = updated (forced).
        if ($st->rowCount() > 0) {
            $inserted++;
        } else {
            $skipped++;
        }
    }
    SettingsCache::$all = null; // force a reload now that rows exist
    return [$inserted, $skipped];
}

/**
 * Insert any content page whose slug does not exist yet.
 *
 * @return array{0:int,1:int} [inserted, already present]
 */
function seed_pages(PDO $pdo, bool $force = false): array
{
    $pages = require __DIR__ . '/page_defaults.php';

    $ins = $pdo->prepare(
        'INSERT INTO content_pages (slug, title, body, meta_desc, sort_order)
         VALUES (:slug, :title, :body, :meta_desc, :sort_order)
         ON DUPLICATE KEY UPDATE slug = slug'
    );
    $upd = $pdo->prepare(
        'UPDATE content_pages SET title = :title, body = :body, meta_desc = :meta_desc,
                sort_order = :sort_order WHERE slug = :slug'
    );

    $inserted = 0;
    $skipped  = 0;
    foreach ($pages as $slug => $p) {
        $params = [
            'slug'       => $slug,
            'title'      => $p['title'],
            'body'       => $p['body'],
            'meta_desc'  => mb_substr((string)($p['meta_desc'] ?? ''), 0, 320),
            'sort_order' => (int)($p['sort_order'] ?? 0),
        ];
        $ins->execute($params);
        if ($ins->rowCount() > 0) {
            $inserted++;
        } elseif ($force) {
            $upd->execute($params);
            $inserted++;
        } else {
            $skipped++;
        }
    }
    return [$inserted, $skipped];
}
