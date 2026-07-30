<?php
/**
 * Idempotent schema migration. Safe to run repeatedly.
 *
 * Adds the settings, content_pages and contact_messages tables plus new columns
 * on users/orders/products, then seeds default settings and policy pages.
 *
 * THIS FILE NEVER ISSUES DROP, TRUNCATE, OR AN UNQUALIFIED DELETE/UPDATE.
 * Existing products, orders and users are left untouched. Running it twice
 * reports zero changes. (setup.php is the destructive one — it drops everything.)
 *
 * Usage:
 *   C:\xampp\php\php.exe migrate.php
 *   C:\xampp\php\php.exe migrate.php --admin=you@example.com
 *   C:\xampp\php\php.exe migrate.php --create-admin=you@example.com --password=secret
 *   C:\xampp\php\php.exe migrate.php --force-pages     # reset policy page bodies to defaults
 *
 * Over HTTP this runs only from loopback AND only when MIGRATE_TOKEN is defined
 * in app/config.php and matches ?token=. Admin promotion is CLI-only regardless:
 * this script grants privileges, so it is treated as a privilege-escalation
 * endpoint rather than a convenience.
 */
declare(strict_types=1);
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/seed_defaults.php';

$cli = (PHP_SAPI === 'cli');

if (!$cli) {
    $remote    = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $isLocal   = in_array($remote, ['127.0.0.1', '::1'], true);
    $token     = (string)($_GET['token'] ?? '');
    $hasSecret = defined('MIGRATE_TOKEN') && MIGRATE_TOKEN !== '';
    if (!$isLocal || !$hasSecret || !hash_equals((string)MIGRATE_TOKEN, $token)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font:14px/1.6 monospace;background:#111;color:#0f0;padding:20px">';
}

$nl = $cli ? "\n" : "<br>\n";
function say(string $m): void
{
    global $nl;
    echo $m . $nl;
    @flush();
}

$argvOpts = $cli ? ($argv ?? []) : [];
function opt(array $argvOpts, string $name): ?string
{
    foreach ($argvOpts as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return null;
}
$optAdmin       = opt($argvOpts, 'admin');
$optCreateAdmin = opt($argvOpts, 'create-admin');
$optPassword    = opt($argvOpts, 'password');
$forcePages     = in_array('--force-pages', $argvOpts, true);

$t0 = microtime(true);
say('Migrating `' . DB_NAME . '` on ' . DB_HOST . ':' . DB_PORT);
say(str_repeat('-', 60));

try {
    $pdo = db_connect();
} catch (PDOException $e) {
    say('CONNECT FAILED: ' . $e->getMessage());
    say('');
    say('If the database does not exist yet, create and seed it first:');
    say('    C:\\xampp\\php\\php.exe setup.php');
    exit(1);
}

/* ---- Introspection helpers -------------------------------------------------
 * information_schema, not "ADD COLUMN IF NOT EXISTS": MariaDB 10.4 supports
 * that syntax but MySQL 8 does not, and schema.sql targets both. There is a
 * standalone MySQL 8 on port 3306 on this machine, so portability is not
 * hypothetical here.
 * ------------------------------------------------------------------------- */
function has_table(PDO $p, string $t): bool
{
    $st = $p->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$t]);
    return (int)$st->fetchColumn() > 0;
}

function has_column(PDO $p, string $t, string $c): bool
{
    $st = $p->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$t, $c]);
    return (int)$st->fetchColumn() > 0;
}

$changes = 0;

function ensure_table(PDO $pdo, string $name, string $ddl): void
{
    global $changes;
    if (has_table($pdo, $name)) {
        say("  = table {$name} already present");
        return;
    }
    $pdo->exec($ddl);
    $changes++;
    say("  + table {$name} created");
}

function ensure_column(PDO $pdo, string $table, string $col, string $definition): void
{
    global $changes;
    if (!has_table($pdo, $table)) {
        say("  ! table {$table} missing — skipping column {$col} (run setup.php first)");
        return;
    }
    if (has_column($pdo, $table, $col)) {
        say("  = {$table}.{$col} already present");
        return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    $changes++;
    say("  + {$table}.{$col} added");
}

/* ---- 1. New tables ------------------------------------------------------ */
say('');
say('[1/4] Tables');

ensure_table($pdo, 'settings', <<<SQL
CREATE TABLE settings (
  skey       VARCHAR(80) NOT NULL,
  svalue     TEXT DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

ensure_table($pdo, 'content_pages', <<<SQL
CREATE TABLE content_pages (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug           VARCHAR(120) NOT NULL,
  title          VARCHAR(200) NOT NULL,
  body           MEDIUMTEXT DEFAULT NULL,
  meta_title     VARCHAR(200) NOT NULL DEFAULT '',
  meta_desc      VARCHAR(320) NOT NULL DEFAULT '',
  is_published   TINYINT(1) NOT NULL DEFAULT 1,
  show_in_footer TINYINT(1) NOT NULL DEFAULT 1,
  sort_order     SMALLINT NOT NULL DEFAULT 0,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_page_slug (slug),
  KEY idx_page_footer (is_published, show_in_footer, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

ensure_table($pdo, 'contact_messages', <<<SQL
CREATE TABLE contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(160) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  subject    VARCHAR(200) NOT NULL DEFAULT '',
  body       TEXT,
  order_ref  VARCHAR(60) DEFAULT NULL,
  ip         VARCHAR(45) DEFAULT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_unread (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

/* ---- 2. New columns ----------------------------------------------------- */
say('');
say('[2/4] Columns');

ensure_column($pdo, 'users', 'is_admin', 'is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER name');

ensure_column($pdo, 'orders', 'phone',           'phone VARCHAR(40) DEFAULT NULL');
ensure_column($pdo, 'orders', 'city',            'city VARCHAR(120) DEFAULT NULL');
ensure_column($pdo, 'orders', 'region',          'region VARCHAR(120) DEFAULT NULL');
ensure_column($pdo, 'orders', 'postcode',        'postcode VARCHAR(30) DEFAULT NULL');
ensure_column($pdo, 'orders', 'country',         'country VARCHAR(80) DEFAULT NULL');
ensure_column($pdo, 'orders', 'subtotal',        'subtotal DECIMAL(10,2) NOT NULL DEFAULT 0');
ensure_column($pdo, 'orders', 'shipping_cost',   'shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0');
ensure_column($pdo, 'orders', 'tracking_number', 'tracking_number VARCHAR(120) DEFAULT NULL');
ensure_column($pdo, 'orders', 'admin_note',      'admin_note TEXT DEFAULT NULL');
ensure_column($pdo, 'orders', 'updated_at',
    'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

ensure_column($pdo, 'products', 'compare_at_price', 'compare_at_price DECIMAL(10,2) DEFAULT NULL');
ensure_column($pdo, 'products', 'gtin',             'gtin VARCHAR(20) DEFAULT NULL');
ensure_column($pdo, 'products', 'item_condition',   "item_condition VARCHAR(20) NOT NULL DEFAULT 'new'");
ensure_column($pdo, 'products', 'google_category',  'google_category VARCHAR(120) DEFAULT NULL');

/* ---- 3. Seed defaults --------------------------------------------------- */
say('');
say('[3/4] Defaults');

[$sIns, $sSkip] = seed_settings($pdo);
$changes += $sIns;
say("  settings:      {$sIns} inserted, {$sSkip} already present");

[$pIns, $pSkip] = seed_pages($pdo, $forcePages);
$changes += $pIns;
$pageVerb = $forcePages ? 'written' : 'inserted';
say("  content pages: {$pIns} {$pageVerb}, {$pSkip} already present");

/* ---- 4. Admin promotion (CLI only) -------------------------------------- */
say('');
say('[4/4] Admin account');

if (!$cli && ($optAdmin || $optCreateAdmin)) {
    say('  ! admin promotion is CLI-only and was ignored');
} elseif ($optCreateAdmin !== null) {
    $email = trim($optCreateAdmin);
    $pass  = (string)$optPassword;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        say("  ! --create-admin: '{$email}' is not a valid email address");
    } elseif (strlen($pass) < 8) {
        say('  ! --create-admin requires --password= with at least 8 characters');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO users (email, password_hash, name, is_admin) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_admin = 1'
        )->execute([$email, $hash, 'Administrator']);
        $changes++;
        say("  + admin account ready: {$email}");
    }
} elseif ($optAdmin !== null) {
    $email = trim($optAdmin);
    $st = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE email = ?');
    $st->execute([$email]);
    if ($st->rowCount() > 0) {
        $changes++;
        say("  + promoted to admin: {$email}");
    } else {
        $exists = $pdo->prepare('SELECT is_admin FROM users WHERE email = ?');
        $exists->execute([$email]);
        $row = $exists->fetch();
        if ($row === false) {
            say("  ! NO USER FOUND with email '{$email}' — nobody was promoted.");
            say("    Register at " . rtrim(BASE_URL, '/') . "/register.php first, then re-run,");
            say("    or use: php migrate.php --create-admin={$email} --password=yourpassword");
        } else {
            say("  = {$email} is already an admin");
        }
    }
} else {
    $adminCount = has_column($pdo, 'users', 'is_admin')
        ? (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn()
        : 0;
    if ($adminCount === 0) {
        say('  ! NO ADMIN USERS EXIST. The admin panel is unreachable until you create one:');
        say('       php migrate.php --admin=you@example.com          (existing account)');
        say('       php migrate.php --create-admin=you@example.com --password=yourpassword');
    } else {
        say("  = {$adminCount} admin account(s) present");
    }
}

/* ---- Summary ------------------------------------------------------------ */
say('');
say(str_repeat('-', 60));
say($changes === 0
    ? sprintf('No changes needed — already up to date (%.2fs)', microtime(true) - $t0)
    : sprintf('%d change(s) applied in %.2fs', $changes, microtime(true) - $t0));
say('This script is safe to re-run.');

if (!$cli) {
    echo '</pre>';
}
