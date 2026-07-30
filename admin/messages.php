<?php
/**
 * Contact form inbox.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $mid    = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($mid > 0) {
        if ($action === 'read') {
            $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$mid]);
        } elseif ($action === 'unread') {
            $pdo->prepare('UPDATE contact_messages SET is_read = 0 WHERE id = ?')->execute([$mid]);
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$mid]);
            flash('Message deleted.');
        }
    }
    header('Location: ' . admin_url('messages.php'));
    exit;
}

$rows = $pdo->query(
    'SELECT * FROM contact_messages ORDER BY is_read, created_at DESC LIMIT 200'
)->fetchAll();

$adminTitle = 'Messages';
require __DIR__ . '/_layout.php';
?>

<p class="text-muted small mb-3">
  Enquiries from <a href="<?= url('contact.php') ?>" target="_blank">contact.php</a>. Messages are stored here
  even when outbound mail fails, so nothing is lost while SMTP is unconfigured.
</p>

<?php if (!$rows): ?>
  <section class="card-a"><div class="bd"><p class="text-muted small mb-0">No messages yet.</p></div></section>
<?php else: ?>
  <?php foreach ($rows as $m): ?>
    <section class="card-a mb-3" style="<?= $m['is_read'] ? 'opacity:.72' : 'border-left:3px solid var(--red)' ?>">
      <div class="bd">
        <div class="d-flex flex-wrap gap-2 align-items-start mb-2">
          <div class="flex-grow-1">
            <div style="font-weight:600">
              <?= h((string)$m['subject']) ?: '(no subject)' ?>
              <?php if (!$m['is_read']): ?><span class="badge text-bg-danger ms-1" style="font-size:.62rem">new</span><?php endif; ?>
            </div>
            <div class="small text-muted">
              <?= h((string)$m['name']) ?> &lt;<a href="mailto:<?= h((string)$m['email']) ?>"><?= h((string)$m['email']) ?></a>&gt;
              · <?= h(date('M j, Y g:ia', strtotime((string)$m['created_at']))) ?>
              <?php if (!empty($m['order_ref'])): ?>
                · Order ref <strong><?= h((string)$m['order_ref']) ?></strong>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex gap-1">
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">
              <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem">
                Mark <?= $m['is_read'] ? 'unread' : 'read' ?>
              </button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this message permanently?')">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem">Delete</button>
            </form>
          </div>
        </div>
        <div class="small" style="white-space:pre-wrap;color:#3a3a3e"><?= h((string)$m['body']) ?></div>
        <div class="mt-2">
          <a class="small" href="mailto:<?= h((string)$m['email']) ?>?subject=<?= rawurlencode('Re: ' . (string)$m['subject']) ?>">Reply by email →</a>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
