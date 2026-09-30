<?php
/**
 * Outbound mail.
 *
 * Uses PHP's mail(), which on a stock XAMPP install has no configured MTA and
 * will simply return false. Every caller therefore treats a send failure as
 * non-fatal and persists the message to the database first — a customer's
 * order or enquiry must never be lost because SMTP is not wired up yet.
 *
 * To actually deliver mail, configure [mail function] in php.ini (SMTP host,
 * port, sendmail_from) or swap send_mail() for a real SMTP library.
 */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

/**
 * Send a plain-text email. Returns false on failure; never throws.
 */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = setting('orders_email') !== '' ? setting('orders_email') : setting('contact_email');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
    }

    // Header injection guard: a newline in any of these would let an attacker
    // append arbitrary headers (Bcc, Content-Type) to the message.
    $clean = static fn(string $s): string => trim(str_replace(["\r", "\n"], ' ', $s));
    $subject = $clean($subject);
    $from    = $clean($from);

    $headers = [
        'From: ' . sprintf('%s <%s>', $clean(setting('site_name')), $from),
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $clean($replyTo);
    }

    try {
        $ok = @mail($to, $subject, wordwrap($body, 78, "\r\n"), implode("\r\n", $headers));
    } catch (Throwable $e) {
        $ok = false;
    }
    if (!$ok) {
        error_log(sprintf('[mail] failed to send "%s" to %s', $subject, $to));
    }
    return (bool)$ok;
}

/** Plain-text order confirmation body. */
function order_confirmation_body(array $order, array $items): string
{
    $lines   = [];
    $lines[] = 'Thank you for your order.';
    $lines[] = '';
    $lines[] = 'Order #' . str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT);
    $lines[] = 'Placed: ' . date('j F Y', strtotime((string)($order['created_at'] ?? 'now')));
    $lines[] = '';
    $lines[] = str_repeat('-', 56);
    foreach ($items as $it) {
        $lines[] = sprintf(
            '%-38s %2d x %s',
            mb_strimwidth((string)$it['name'], 0, 38, '...'),
            (int)$it['qty'],
            money($it['price'])
        );
        if (!empty($it['grade']) && $it['grade'] !== 'Standard') {
            $lines[] = '  ' . $it['grade'];
        }
    }
    $lines[] = str_repeat('-', 56);
    $lines[] = sprintf('%-44s %s', 'Subtotal', money($order['subtotal'] ?? $order['total']));
    $lines[] = sprintf('%-44s %s', 'Shipping',
        (float)($order['shipping_cost'] ?? 0) > 0 ? money($order['shipping_cost']) : 'Free');
    $lines[] = sprintf('%-44s %s', 'Total', money($order['total']));
    $lines[] = '';
    $lines[] = 'Shipping to:';
    $lines[] = '  ' . $order['full_name'];
    $lines[] = '  ' . $order['address'];
    $addrTail = trim(implode(', ', array_filter([
        $order['city'] ?? '',
        trim(($order['region'] ?? '') . ' ' . ($order['postcode'] ?? '')),
        $order['country'] ?? '',
    ])));
    if ($addrTail !== '') {
        $lines[] = '  ' . $addrTail;
    }
    $lines[] = '';
    $lines[] = 'Payment: we will email you shortly to arrange payment. Nothing is charged '
             . 'until then, and nothing is dispatched until payment has been received.';
    $lines[] = '';
    $lines[] = 'What happens next: we prepare and dispatch your order within '
             . setting('ship_processing_days') . ' business day(s), then email you tracking. '
             . 'Delivery typically takes ' . delivery_estimate() . '.';
    $lines[] = '';
    $lines[] = 'Questions? Reply to this email or contact ' . setting('support_email')
             . ' / ' . setting('phone')
             . (setting('support_hours') !== '' ? ' (' . setting('support_hours') . ')' : '') . '.';
    $lines[] = '';
    $lines[] = setting('legal_entity_name') !== '' ? setting('legal_entity_name') : setting('site_name');
    $lines[] = setting_address_line(', ');
    $lines[] = site_url();
    return implode("\r\n", $lines);
}
