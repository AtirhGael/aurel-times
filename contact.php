<?php
/**
 * Contact page: real business details plus a working enquiry form.
 *
 * Messages are written to contact_messages FIRST and mailed second. The
 * database row is the source of truth, so a mail() failure (XAMPP ships with
 * no MTA) never silently loses a customer's message — it still appears in
 * admin/messages.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/pages.php';
require_once __DIR__ . '/app/mail.php';
require_once __DIR__ . '/app/_schema.php';

$errors = [];
$sent   = false;
$u      = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim((string)($_POST['name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $subject  = trim((string)($_POST['subject'] ?? ''));
    $message  = trim((string)($_POST['message'] ?? ''));
    $orderRef = trim((string)($_POST['order_ref'] ?? ''));
    $trap     = trim((string)($_POST['website'] ?? '')); // honeypot

    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($name === '') {
        $errors[] = 'Please tell us your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address so we can reply.';
    }
    if (mb_strlen($message) < 10) {
        $errors[] = 'Please give us a little more detail (at least 10 characters).';
    }

    // Simple per-session throttle. Not a substitute for a real rate limiter,
    // but it stops a form-fill script from filling the inbox in one sitting.
    $last = (int)($_SESSION['contact_last'] ?? 0);
    if ($last > 0 && (time() - $last) < 30) {
        $errors[] = 'You just sent a message. Please wait a moment before sending another.';
    }

    if (!$errors) {
        // A bot filled the hidden field. Show success without storing anything —
        // a visible rejection just tells the bot to try a different shape.
        if ($trap !== '') {
            $sent = true;
        } else {
            try {
                db()->prepare(
                    'INSERT INTO contact_messages (name, email, subject, body, order_ref, ip)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    mb_substr($name, 0, 160),
                    mb_substr($email, 0, 190),
                    mb_substr($subject, 0, 200),
                    $message,
                    $orderRef !== '' ? mb_substr($orderRef, 0, 60) : null,
                    mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                ]);

                send_mail(
                    setting('contact_email'),
                    '[' . setting('site_name') . '] ' . ($subject !== '' ? $subject : 'New enquiry'),
                    "From: {$name} <{$email}>\r\n"
                    . ($orderRef !== '' ? "Order reference: {$orderRef}\r\n" : '')
                    . "\r\n{$message}\r\n",
                    $email
                );

                $_SESSION['contact_last'] = time();
                $sent = true;
            } catch (Throwable $e) {
                error_log('[contact] ' . $e->getMessage());
                $errors[] = 'We could not save your message just now. Please email us directly at '
                          . setting('support_email') . '.';
            }
        }
    }
}

$pageTitle       = 'Contact Us';
$metaDescription = 'Get in touch with ' . setting('site_name')
                 . ' — phone, email, postal address and support hours.';
$canonical       = abs_url('contact.php');
$jsonLd          = [
    schema_organization(),
    schema_breadcrumb([
        ['name' => setting('site_name'), 'url' => abs_url('index.php')],
        ['name' => 'Contact Us',         'url' => $canonical],
    ]),
];
require __DIR__ . '/app/header.php';
?>
<div class="container py-5">
  <div class="mx-auto" style="max-width:960px">

    <nav aria-label="breadcrumb" class="small mb-3" style="color:var(--muted)">
      <a href="<?= url('index.php') ?>">Home</a> <span class="mx-1">/</span> <span>Contact Us</span>
    </nav>

    <h1 class="mb-1" style="font-weight:600">Contact Us</h1>
    <p class="mb-4" style="color:var(--muted)">
      <?php /* support_hours is optional; without the guard an unset value rendered as
               "We answer , normally within one business day." */ ?>
      Every message reaches a person. We answer
      <?php if (setting('support_hours') !== ''): ?><?= h(setting('support_hours')) ?>, <?php endif ?>normally
      within one business day.
    </p>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="p-4 rounded h-100" style="background:var(--card);border:1px solid var(--line)">
          <h2 class="h6 text-uppercase" style="letter-spacing:1.5px">Reach us directly</h2>

          <?php if (setting('phone') !== ''): ?>
            <div class="mb-3">
              <div class="small" style="color:var(--muted)">Phone</div>
              <a class="text-gold" style="font-size:1.05rem"
                 href="tel:<?= h(preg_replace('/[^0-9+]/', '', setting('phone'))) ?>"><?= h(setting('phone')) ?></a>
              <?php if (setting('support_hours') !== ''): ?>
                <div class="small" style="color:var(--muted)"><?= h(setting('support_hours')) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (setting('support_email') !== ''): ?>
            <div class="mb-3">
              <div class="small" style="color:var(--muted)">Email</div>
              <a class="text-gold" href="mailto:<?= h(setting('support_email')) ?>"><?= h(setting('support_email')) ?></a>
            </div>
          <?php endif; ?>

          <?php if (($whatsapp = setting('whatsapp')) !== ''): ?>
            <div class="mb-3">
              <div class="small" style="color:var(--muted)">WhatsApp</div>
              <a class="text-gold" rel="noopener" target="_blank"
                 href="https://wa.me/<?= h(preg_replace('/[^0-9]/', '', $whatsapp)) ?>"><?= h($whatsapp) ?></a>
            </div>
          <?php endif; ?>

          <?php if (setting_address_line() !== ''): ?>
            <div class="mb-3">
              <div class="small" style="color:var(--muted)">Postal address</div>
              <address class="mb-0" style="font-style:normal;line-height:1.7">
                <?php if (setting('legal_entity_name') !== ''): ?>
                  <strong><?= h(setting('legal_entity_name')) ?></strong><br>
                <?php endif; ?>
                <?= h(setting('address_line1')) ?><br>
                <?php if (setting('address_line2') !== ''): ?><?= h(setting('address_line2')) ?><br><?php endif; ?>
                <?= h(setting('city')) ?>, <?= h(setting('region')) ?> <?= h(setting('postcode')) ?><br>
                <?= h(setting('country')) ?>
              </address>
            </div>
          <?php endif; ?>

          <hr>
          <div class="small" style="color:var(--muted)">
            Chasing an order? Include your order number and we can answer in one reply.
            Return and refund terms are on our
            <a class="text-gold" href="<?= h(page_url('return-policy')) ?>">Return Policy</a> and
            <a class="text-gold" href="<?= h(page_url('refund-policy')) ?>">Refund Policy</a> pages.
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
          <?php if ($sent): ?>
            <div class="text-center py-4">
              <div style="font-size:2.4rem">✓</div>
              <h2 class="h5 mt-2">Message received</h2>
              <p style="color:var(--muted)">
                Thank you — we have your message and will reply to your email address
                within one business day.
              </p>
              <a class="btn btn-outline-dark2 px-4 mt-2" href="<?= url('shop.php') ?>">Continue shopping</a>
            </div>
          <?php else: ?>
            <h2 class="h6 text-uppercase mb-3" style="letter-spacing:1.5px">Send us a message</h2>

            <?php foreach ($errors as $e): ?>
              <div class="alert alert-danger py-2 small"><?= h($e) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate id="contact-form">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

              <!-- Honeypot: hidden from people, irresistible to bots. -->
              <div style="position:absolute;left:-9999px" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-muted">Your name *</label>
                  <input name="name" class="form-control" required
                         value="<?= h($_POST['name'] ?? ($u['name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Email address *</label>
                  <input name="email" type="email" class="form-control" required
                         value="<?= h($_POST['email'] ?? ($u['email'] ?? '')) ?>">
                </div>
                <div class="col-md-7">
                  <label class="form-label small text-muted">Subject</label>
                  <input name="subject" class="form-control" value="<?= h($_POST['subject'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                  <label class="form-label small text-muted">Order number <span class="text-muted">(optional)</span></label>
                  <input name="order_ref" class="form-control" value="<?= h($_POST['order_ref'] ?? '') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label small text-muted">Message *</label>
                  <textarea name="message" rows="6" class="form-control" required><?= h($_POST['message'] ?? '') ?></textarea>
                </div>
              </div>

              <button class="btn btn-gold w-100 mt-3 py-2">Send Message</button>
              <p class="small mt-3 mb-0" style="color:var(--muted)">
                We use your details only to answer this enquiry — see our
                <a class="text-gold" href="<?= h(page_url('privacy-policy')) ?>">Privacy Policy</a>.
              </p>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
