<?php
// View + app helpers.
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** HTML-escape. */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Format money.
 *
 * $n stays untyped on purpose: strict_types is on and callers pass PDO DECIMAL
 * values, which arrive as strings. A `float` type declaration would fatal on
 * money($order['total']).
 */
function money($n): string
{
    $amount = number_format((float)$n, 2);
    $symbol = setting('currency_symbol');
    return setting('currency_position') === 'after'
        ? $amount . $symbol
        : $symbol . $amount;
}

/** Build a URL relative to the app base. */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Absolute URL — for canonical tags, Open Graph, sitemap, feed and email. */
function abs_url(string $path = ''): string
{
    return site_origin() . url($path);
}

/** The site root as an absolute URL. */
function site_url(): string
{
    return rtrim(abs_url(), '/') . '/';
}

/**
 * Two-tone wordmark. Both halves escaped; .lg-b carries the accent colour.
 *
 * Each half gets its own class rather than relying on a bare `span` selector, so the
 * storefront can stack them on two lines (app/header.php) while the admin sidebar keeps
 * them inline (admin/_layout.php) from the same markup.
 */
function logo_html(): string
{
    $a = trim(setting('logo_text_a'));
    $b = trim(setting('logo_text_b'));
    if ($a === '' && $b === '') {
        // Never render an empty link: the nav mark is the only "home" affordance.
        $a = trim(setting('site_name'));
    }
    $out = '';
    if ($a !== '') {
        $out .= '<span class="lg-a">' . h($a) . '</span>';
    }
    if ($b !== '') {
        $out .= '<span class="lg-b">' . h($b) . '</span>';
    }
    return $out;
}

/**
 * Shipping charge for a given order subtotal, from the shipping settings.
 * Single source of truth for cart.php, checkout.php and the policy pages.
 */
function shipping_cost(float $subtotal): float
{
    if (setting_bool('free_shipping_enabled')) {
        $threshold = setting_float('free_shipping_threshold');
        if ($threshold <= 0 || $subtotal >= $threshold) {
            return 0.0;
        }
    }
    return round(setting_float('flat_shipping_rate'), 2);
}

/** Human-readable delivery estimate, e.g. "5-20 business days". */
function delivery_estimate(): string
{
    $min = setting_int('ship_delivery_min_days', 5);
    $max = setting_int('ship_delivery_max_days', 20);
    return $min === $max
        ? $min . ' business days'
        : $min . '-' . $max . ' business days';
}

/** Slugify text for URLs. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-');
}

/** Placeholder image when a product has no image. */
function img_or_placeholder(?string $u): string
{
    $u = trim((string)$u);
    if ($u === '') {
        return 'https://placehold.co/600x600/1a1a1a/c9a24b?text=No+Image';
    }
    return $u;
}

/** Current logged-in user row or null. */
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $st = db()->prepare('SELECT id, email, name FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $cache = $st->fetch() ?: null;
    return $cache;
}

/** ---- Cart (session based) ---- */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart() as $line) {
        $n += (int)$line['qty'];
    }
    return $n;
}

function cart_total(): float
{
    $t = 0.0;
    foreach (cart() as $line) {
        $t += (float)$line['price'] * (int)$line['qty'];
    }
    return $t;
}

function cart_add(int $variantId, int $qty = 1): void
{
    $st = db()->prepare(
        'SELECT v.id AS variant_id, v.grade, v.price, p.id AS product_id, p.name, p.handle, p.image
         FROM variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?'
    );
    $st->execute([$variantId]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }
    $key = (string)$variantId;
    $cart = cart();
    if (isset($cart[$key])) {
        $cart[$key]['qty'] += $qty;
    } else {
        $cart[$key] = [
            'variant_id' => (int)$row['variant_id'],
            'product_id' => (int)$row['product_id'],
            'name'       => $row['name'],
            'handle'     => $row['handle'],
            'grade'      => $row['grade'],
            'price'      => (float)$row['price'],
            'image'      => $row['image'],
            'qty'        => $qty,
        ];
    }
    if ($cart[$key]['qty'] < 1) {
        unset($cart[$key]);
    }
    $_SESSION['cart'] = $cart;
}

function cart_set(int $variantId, int $qty): void
{
    $cart = cart();
    $key = (string)$variantId;
    if (!isset($cart[$key])) {
        return;
    }
    if ($qty < 1) {
        unset($cart[$key]);
    } else {
        $cart[$key]['qty'] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove(int $variantId): void
{
    $cart = cart();
    unset($cart[(string)$variantId]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/** CSRF token helpers. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/** Flash messages. */
function flash(string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
