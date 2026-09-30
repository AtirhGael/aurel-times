<?php
/**
 * Editable content pages (shipping policy, returns, privacy, terms, ...).
 */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

/**
 * Slugs that have a dedicated stub file at the project root, so they get a
 * clean /watches/shipping-policy.php URL instead of /watches/page.php?slug=...
 *
 * An in-memory allowlist rather than an is_file() check: the footer resolves
 * every one of these on every request, and a filesystem stat per link per page
 * buys nothing. Pages created in admin that are not listed here still work —
 * they fall back to page.php?slug=.
 */
const PAGE_STUBS = [
    'about-us',
    'shipping-policy',
    'return-policy',
    'refund-policy',
    'privacy-policy',
    'terms-of-service',
    'faq',
];

/** One published page by slug, or null. */
function get_page(string $slug): ?array
{
    try {
        $pdo = db_try();
        if ($pdo === null) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM content_pages WHERE slug = ? AND is_published = 1');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        return null; // table missing (pre-migration)
    }
}

/** All pages, ordered. */
function all_pages(bool $publishedOnly = true): array
{
    try {
        $pdo = db_try();
        if ($pdo === null) {
            return [];
        }
        $sql = 'SELECT id, slug, title, is_published, show_in_footer, sort_order, updated_at
                FROM content_pages';
        if ($publishedOnly) {
            $sql .= ' WHERE is_published = 1';
        }
        $sql .= ' ORDER BY sort_order, title';
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Pages linked from the footer.
 *
 * This runs on every request, so it degrades to [] rather than throwing: a
 * missing content_pages table must not be able to take the whole site down.
 */
function footer_pages(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo = db_try();
        if ($pdo === null) {
            return $cache = [];
        }
        $rows = $pdo->query(
            'SELECT slug, title FROM content_pages
             WHERE is_published = 1 AND show_in_footer = 1
             ORDER BY sort_order, title'
        )->fetchAll();
        return $cache = ($rows ?: []);
    } catch (Throwable $e) {
        return $cache = [];
    }
}

/** URL for a content page — pretty stub when one exists, generic otherwise. */
function page_url(string $slug): string
{
    return in_array($slug, PAGE_STUBS, true)
        ? url($slug . '.php')
        : url('page.php?slug=' . rawurlencode($slug));
}

/**
 * The substitution namespace for {{placeholders}}: every setting, plus a few
 * computed conveniences.
 */
function page_vars(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $vars = settings_all();

    $vars['year']       = date('Y');
    $vars['site_url']   = site_url();
    $vars['delivery_estimate'] = delivery_estimate();
    $vars['free_shipping_threshold_money'] = money(setting_float('free_shipping_threshold'));
    $vars['flat_shipping_rate_money']      = money(setting_float('flat_shipping_rate'));
    $vars['address_oneline'] = setting_address_line();
    // Always renders: the legal name once it is set, the store name until then.
    $vars['trader_name'] = setting('legal_entity_name') !== '' ? setting('legal_entity_name') : setting('site_name');

    // {{url_return_policy}} etc., so policy pages can cross-link without
    // hardcoding a path that a future rewrite rule would break.
    foreach (PAGE_STUBS as $slug) {
        $vars['url_' . str_replace('-', '_', $slug)] = page_url($slug);
    }
    $vars['url_contact'] = url('contact.php');
    $vars['url_shop']    = url('shop.php');
    $vars['url_home']    = url('index.php');

    return $vars;
}

/**
 * Substitute {{key}} placeholders in admin-authored HTML.
 *
 * Escaping direction matters. The body itself is HTML and is echoed raw —
 * escaping it would render <h2> as visible text. The substituted VALUES come
 * from the settings table, so each one is escaped here, inside the callback,
 * before it lands in the HTML string.
 *
 * preg_replace_callback does not re-scan its own replacements, so a setting
 * whose value happens to contain {{something}} is left alone. That closes both
 * placeholder-injection and expansion-bomb vectors for free. Do NOT wrap this
 * in a loop to "support nested placeholders" — that reopens both.
 *
 * Unknown keys are left verbatim so a typo is visible rather than silently
 * blanking a sentence.
 */
function render_placeholders(string $html): string
{
    $vars = page_vars();
    $html = drop_empty_blocks($html, $vars);
    return (string)preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_]{1,64})\s*\}\}/',
        static function (array $m) use ($vars): string {
            return array_key_exists($m[1], $vars) ? h((string)$vars[$m[1]]) : $m[0];
        },
        $html
    );
}

/**
 * Remove every <p>, <li> or <tr> that references a setting which is currently
 * empty. Without this an unfilled field prints as a broken sentence ("operated
 * by .", "We ship with ."), which reads as a placeholder site. Page authors keep
 * optional facts in their own element so only that element goes.
 *
 * Matching is non-greedy within one element, which is safe for these tags
 * because none of them nest inside themselves in page HTML.
 */
function drop_empty_blocks(string $html, array $vars): string
{
    return (string)preg_replace_callback(
        '#<(p|li|tr)\b[^>]*>.*?</\1>\s*#s',
        static function (array $m) use ($vars): string {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]{1,64})\s*\}\}/', $m[0], $keys);
            foreach ($keys[1] as $k) {
                if (array_key_exists($k, $vars) && trim((string)$vars[$k]) === '') {
                    return '';
                }
            }
            return $m[0];
        },
        $html
    );
}

/**
 * Same substitution for plain-text contexts — titles, meta descriptions.
 *
 * Values are inserted RAW here because the caller applies h() to the finished
 * string. Using render_placeholders() on a title and then escaping it again
 * would produce &amp;amp; — the classic double-escape.
 */
function render_placeholders_text(string $text): string
{
    $vars = page_vars();
    return (string)preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_]{1,64})\s*\}\}/',
        static function (array $m) use ($vars): string {
            return array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0];
        },
        $text
    );
}
