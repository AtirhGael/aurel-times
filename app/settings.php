<?php
/**
 * Key/value settings store.
 *
 * Requires db.php ONLY — never helpers.php. helpers.php requires this file, so
 * requiring it back would be a cycle. Include chain: config -> db -> settings -> helpers.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/**
 * Holder for the loaded settings map.
 *
 * A function-local `static` would be unreachable from setting_set(), which has
 * to write through after saving: admin/settings.php POSTs and re-renders inside
 * a single request, so a stale cache would show the operator their old values
 * immediately after saving them.
 */
final class SettingsCache
{
    public static ?array $all = null;
}

/** The full field definitions, grouped. */
function settings_defs(): array
{
    static $defs = null;
    if ($defs === null) {
        $defs = require __DIR__ . '/settings_defs.php';
    }
    return $defs;
}

/** Flat key => field-definition map across all groups. */
function settings_fields(): array
{
    static $flat = null;
    if ($flat === null) {
        $flat = [];
        foreach (settings_defs() as $group) {
            foreach ($group['fields'] as $key => $def) {
                $flat[$key] = $def;
            }
        }
    }
    return $flat;
}

/** Flat key => shipped default. */
function setting_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = [];
        foreach (settings_fields() as $key => $def) {
            $defaults[$key] = (string)($def['default'] ?? '');
        }
    }
    return $defaults;
}

/**
 * Shipped defaults overlaid with whatever the database holds.
 *
 * Handles both failure modes explicitly:
 *   - server unreachable  -> db_try() returns null
 *   - table not created   -> query throws
 * Either way the caller gets the shipped defaults and the page still renders.
 */
function settings_all(): array
{
    if (SettingsCache::$all !== null) {
        return SettingsCache::$all;
    }
    $out = setting_defaults();
    try {
        $pdo = db_try();
        if ($pdo !== null) {
            $rows = $pdo->query('SELECT skey, svalue FROM settings');
            foreach ($rows as $r) {
                // Ignore rows whose key is no longer defined, so removing a
                // field from settings_defs.php leaves no orphan behind.
                if (array_key_exists($r['skey'], $out)) {
                    $out[$r['skey']] = (string)$r['svalue'];
                }
            }
        }
    } catch (Throwable $e) {
        // Table missing (pre-migration). Defaults are a correct answer here.
    }
    SettingsCache::$all = $out;
    // The cache is assigned first: apply_store_timezone() calls setting(), which lands
    // back here and returns immediately instead of re-querying.
    apply_store_timezone();
    return SettingsCache::$all;
}

/** A single setting as a string. */
function setting(string $key, ?string $default = null): string
{
    $all = settings_all();
    if (array_key_exists($key, $all)) {
        return $all[$key];
    }
    return $default ?? '';
}

function setting_bool(string $key): bool
{
    return in_array(strtolower(trim(setting($key))), ['1', 'true', 'yes', 'on'], true);
}

function setting_int(string $key, int $default = 0): int
{
    $v = trim(setting($key));
    return $v === '' ? $default : (int)$v;
}

function setting_float(string $key, float $default = 0.0): float
{
    $v = trim(setting($key));
    return $v === '' ? $default : (float)$v;
}

/** True when a setting is still at the value this codebase shipped with. */
function setting_is_default(string $key): bool
{
    $defaults = setting_defaults();
    return array_key_exists($key, $defaults) && setting($key) === $defaults[$key];
}

/** Upsert one setting and write through to the in-request cache. */
function setting_set(string $key, string $value): void
{
    $pdo = db();
    $st = $pdo->prepare(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
    );
    $st->execute([$key, $value]);
    if (SettingsCache::$all !== null) {
        SettingsCache::$all[$key] = $value;
    }
}

/** Upsert many settings in one transaction. */
function setting_set_many(array $pairs): void
{
    if (!$pairs) {
        return;
    }
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        );
        foreach ($pairs as $k => $v) {
            $st->execute([(string)$k, (string)$v]);
            if (SettingsCache::$all !== null) {
                SettingsCache::$all[(string)$k] = (string)$v;
            }
        }
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Single-line postal address, e.g. for the footer. */
function setting_address_line(string $sep = ', '): string
{
    $parts = array_filter([
        setting('address_line1'),
        setting('address_line2'),
        setting('city'),
        trim(setting('region') . ' ' . setting('postcode')),
        setting('country'),
    ], static fn($p) => trim((string)$p) !== '');
    return implode($sep, $parts);
}

/** Non-empty social profile URLs, keyed by network. */
function setting_social_links(): array
{
    $out = [];
    foreach (['facebook', 'instagram', 'twitter', 'youtube', 'tiktok', 'pinterest'] as $net) {
        $u = trim(setting('social_' . $net));
        if ($u !== '') {
            $out[$net] = $u;
        }
    }
    return $out;
}

/**
 * Narrow the process timezone to the configured store timezone.
 *
 * config.php sets UTC before this file loads, because settings are not readable that
 * early and CLI scripts still need a valid default. Called from settings_all() the
 * moment settings first resolve, so it costs no extra query and covers both the web
 * and CLI paths. An unrecognised identifier is ignored rather than allowed to throw,
 * which matters because settings_all() falls back to shipped defaults when the
 * database is unreachable or the table does not exist yet.
 */
function apply_store_timezone(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $tz = trim(setting('timezone'));
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}
