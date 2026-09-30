<?php
/**
 * Admin authorisation.
 *
 * Admin is a flag on a normal user row, not a separate identity, so there is
 * one login form (login.php) and one session. Access control lives entirely in
 * require_admin(), applied by admin/_guard.php to every admin page.
 */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

/**
 * Is the current session an admin?
 *
 * This runs its own query instead of extending current_user()'s SELECT. That is
 * deliberate: current_user() has an explicit column list, no try/catch, and is
 * called from header.php on every single page. Adding is_admin there and
 * deploying before migrate.php runs would throw "unknown column" on every
 * request and take the entire storefront down. Isolating the check costs one
 * extra query on admin pages only, and it fails closed.
 */
function current_user_is_admin(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    if (empty($_SESSION['uid'])) {
        return $cache = false;
    }
    try {
        $pdo = db_try();
        if ($pdo === null) {
            return $cache = false;
        }
        $st = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        return $cache = ((int)$st->fetchColumn() === 1);
    } catch (Throwable $e) {
        // Column or table missing (pre-migration), or DB down. Fail closed.
        return $cache = false;
    }
}

/**
 * Gate an admin page.
 *
 * Non-admins get 404 rather than 403: a 403 confirms that an admin panel exists
 * at this path, which is free reconnaissance. Logged-out visitors are sent to
 * the login form with a ?next= so they land where they were heading.
 */
function require_admin(): void
{
    if (current_user_is_admin()) {
        return;
    }
    if (!current_user()) {
        $next = (string)($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . url('login.php') . '?next=' . rawurlencode($next));
        exit;
    }
    http_response_code(404);
    exit('<h1>404 Not Found</h1>');
}

/** Build a URL inside the admin panel. */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

/**
 * Validate a ?next= redirect target.
 *
 * Only same-app paths are accepted. Absolute URLs, protocol-relative "//evil",
 * backslashes (which some browsers normalise to "/") and traversal sequences
 * are all rejected in favour of the fallback — otherwise the login form becomes
 * an open redirect that lends our domain's credibility to a phishing page.
 */
function safe_next(string $raw, string $fallback): string
{
    $raw = trim($raw);
    if ($raw === '' || str_contains($raw, "\\") || str_contains($raw, '..')) {
        return $fallback;
    }
    if (str_starts_with($raw, '//') || preg_match('~^[a-z][a-z0-9+.\-]*:~i', $raw)) {
        return $fallback;
    }
    if (!str_starts_with($raw, BASE_URL)) {
        return $fallback;
    }
    return $raw;
}

/** Allowed order statuses. orders.status is free-form VARCHAR, so validate on write. */
const ORDER_STATUSES = [
    'pending'    => 'Pending',
    'paid'       => 'Paid',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
    'refunded'   => 'Refunded',
];

function order_status_label(string $status): string
{
    return ORDER_STATUSES[$status] ?? ucfirst($status);
}

/** Bootstrap badge class for an order status. */
function order_status_class(string $status): string
{
    return match ($status) {
        'delivered', 'paid'      => 'success',
        'shipped', 'processing'  => 'info',
        'cancelled', 'refunded'  => 'secondary',
        default                  => 'warning',
    };
}

/**
 * Settings still sitting at the value this codebase shipped with, flagged in
 * settings_defs.php as needing replacement. Drives the dashboard pre-launch
 * checklist — the mechanism that stops placeholder contact details from
 * quietly going live.
 */
function placeholder_settings(): array
{
    $stale = [];
    foreach (settings_defs() as $groupKey => $group) {
        foreach ($group['fields'] as $key => $def) {
            $stillDefault = !empty($def['placeholder_value']) && setting_is_default($key);
            // `required` fields fail when blank: an empty legal name renders the
            // Terms as "operated by ." and passed the default-only check unseen.
            $missing      = !empty($def['required']) && trim(setting($key)) === '';
            if ($stillDefault || $missing) {
                $stale[$key] = [
                    'label' => $def['label'],
                    'group' => $group['label'],
                    'anchor' => $groupKey,
                    'value' => setting($key),
                ];
            }
        }
    }
    return $stale;
}
