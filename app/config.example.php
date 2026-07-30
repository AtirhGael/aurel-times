<?php
/**
 * Template for app/config.php, which is gitignored because it holds the live
 * database password.
 *
 * Copy this file to app/config.php and fill in the credentials for the
 * environment you are on. On the production host those are the cPanel database
 * values; on a dev box they are whatever your local MySQL/MariaDB uses.
 *
 *     cp app/config.example.php app/config.php
 *
 * A dev machine can instead leave app/config.php holding the production values
 * and create app/config.local.php (also gitignored) with its local overrides —
 * config.php loads that first and PHP constants are immutable, so whatever
 * config.local.php defines wins. That is the arrangement used here, and it is
 * why production must NOT have a config.local.php.
 */
declare(strict_types=1);

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'your_database');
defined('DB_USER')    || define('DB_USER', 'your_db_user');
defined('DB_PASS')    || define('DB_PASS', 'your_db_password');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// Fallback defaults only. Live values come from the `settings` table via
// setting('site_name') / setting('currency_symbol') and are editable in
// admin/settings.php. app/settings_defs.php seeds from these constants, and
// the app falls back to them when the database is unreachable.
define('SITE_NAME', 'Alex Clean Factory Watches');
define('CURRENCY', '£');

// Optional shared secret allowing migrate.php to run over HTTP from loopback.
// Leave undefined to keep migrate.php CLI-only (recommended).
// define('MIGRATE_TOKEN', 'change-me');

// Base URL path (folder under the document root). Derived from __DIR__, NOT
// from SCRIPT_NAME — SCRIPT_NAME would resolve to /admin/ for any script under
// admin/, so every url('shop.php') there would emit /admin/shop.php and 404.
$__appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$__docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
if ($__docRoot !== '' && stripos($__appRoot, $__docRoot) === 0) {
    $__base = rtrim(substr($__appRoot, strlen($__docRoot)), '/');
} else {
    $__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}
define('BASE_URL', $__base === '' ? '/' : $__base . '/');

/**
 * Scheme + host for absolute URLs (canonical, Open Graph, sitemap, feed, mail).
 * Returns '' under CLI, where there is no request to derive a host from.
 */
function site_origin(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    }
    return $proto . '://' . $host;
}

date_default_timezone_set('UTC');
