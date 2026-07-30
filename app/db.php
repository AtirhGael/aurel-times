<?php
// PDO connection (shared, lazy singleton).
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Shared connection state for db() and db_try().
 *
 * $failed is memoized deliberately: on a firewalled or stopped server each
 * connect attempt blocks for the full TCP timeout, so a page with a dozen
 * money() calls would stall a dozen times over without it.
 */
final class DbConn
{
    public static ?PDO $pdo = null;
    public static bool $failed = false;
    public static string $error = '';
}

/** Open a connection. Lets PDOException propagate — callers decide the policy. */
function db_connect(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Connection for code that genuinely cannot continue without a database
 * (product pages, checkout). Renders the failure page and stops.
 */
function db(): PDO
{
    if (DbConn::$pdo instanceof PDO) {
        return DbConn::$pdo;
    }
    if (DbConn::$failed) {
        db_fail_page(DbConn::$error);
    }
    try {
        DbConn::$pdo = db_connect();
    } catch (PDOException $e) {
        DbConn::$failed = true;
        DbConn::$error  = $e->getMessage();
        db_fail_page(DbConn::$error);
    }
    return DbConn::$pdo;
}

/**
 * Connection for code that must degrade instead of dying — settings, footer
 * links, nav. Returns null when the server is unreachable.
 *
 * db() used to die() inside its catch, and die() is not throwable, so the
 * try/catch(Throwable) guards in header.php only ever covered the
 * missing-table case, never a stopped server. This is the accessor that
 * makes those guards mean something.
 */
function db_try(): ?PDO
{
    if (DbConn::$pdo instanceof PDO) {
        return DbConn::$pdo;
    }
    if (DbConn::$failed) {
        return null;
    }
    try {
        DbConn::$pdo = db_connect();
    } catch (PDOException $e) {
        DbConn::$failed = true;
        DbConn::$error  = $e->getMessage();
        return null;
    }
    return DbConn::$pdo;
}

/**
 * Terminal database-failure page for requests that cannot continue.
 *
 * Emits 503 with Retry-After, not 500: an outage is temporary, and a search
 * engine that sees 503 comes back later instead of dropping the URL from its
 * index the way a 500 or a 404 encourages.
 *
 * The connection details go to the error log, never to the visitor — the raw
 * PDO message names the host, port and database, which is free reconnaissance.
 * Operators get the detail on localhost, where it is genuinely useful.
 */
function db_fail_page(string $msg): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed: {$msg}\n");
        exit(1);
    }
    error_log('[db] connection failed: ' . $msg);

    $isLocal = in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);

    http_response_code(503);
    header('Retry-After: 300');
    header('Content-Type: text/html; charset=utf-8');

    $detail = $isLocal
        ? '<pre style="text-align:left;background:#f6f7f9;border:1px solid #e3e5e9;border-radius:6px;padding:12px;'
          . 'font-size:.8rem;overflow:auto">' . htmlspecialchars($msg) . '</pre>'
          . '<p style="font-size:.85rem;color:#6b7280">Shown because you are on localhost. Check '
          . '<code>app/config.php</code> (host ' . htmlspecialchars(DB_HOST . ':' . DB_PORT)
          . ', database <code>' . htmlspecialchars(DB_NAME) . '</code>), or run <code>setup.php</code> '
          . 'to create and seed the schema.</p>'
        : '';

    die(
        '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>Temporarily unavailable</title></head>'
        . '<body style="font:16px/1.6 system-ui,-apple-system,\'Segoe UI\',sans-serif;color:#1c1c1e;'
        . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px">'
        . '<main style="max-width:520px;text-align:center">'
        . '<div style="font-size:2.2rem">⏳</div>'
        . '<h1 style="font-size:1.4rem;font-weight:600;margin:.4rem 0">We will be right back</h1>'
        . '<p style="color:#6b7280">The store is temporarily unavailable while we carry out maintenance. '
        . 'Please try again in a few minutes — nothing in your cart has been lost.</p>'
        . $detail
        . '</main></body></html>'
    );
}
