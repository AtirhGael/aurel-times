<?php
/**
 * Content page list.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($id > 0 && in_array($action, ['publish', 'unpublish', 'footer_on', 'footer_off'], true)) {
        $map = [
            'publish'    => ['is_published', 1],
            'unpublish'  => ['is_published', 0],
            'footer_on'  => ['show_in_footer', 1],
            'footer_off' => ['show_in_footer', 0],
        ];
        [$col, $val] = $map[$action];
        $pdo->prepare("UPDATE content_pages SET `{$col}` = ? WHERE id = ?")->execute([$val, $id]);
        flash('Page updated.');
    }
    header('Location: ' . admin_url('pages.php'));
    exit;
}

$rows = $pdo->query(
    'SELECT id, slug, title, is_published, show_in_footer, sort_order, updated_at
     FROM content_pages ORDER BY sort_order, title'
)->fetchAll();

$adminTitle = 'Content Pages';
require __DIR__ . '/_layout.php';
?>

<p class="text-muted small mb-3">
  Policy text supports <code class="ph">{{placeholders}}</code> that pull live values from
  <a href="<?= admin_url('settings.php') ?>">Settings</a> — change the return window once and every page
  that mentions it updates.
</p>

<section class="card-a">
  <div class="bd p-0">
    <?php if (!$rows): ?>
      <p class="text-muted small p-3 mb-0">
        No content pages yet. Run <code class="ph">php migrate.php</code> to seed the default policy pages.
      </p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th class="ps-3">Title</th><th>URL</th><th>Published</th><th>In footer</th>
            <th>Updated</th><th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="ps-3">
                <a href="<?= admin_url('page_edit.php?id=' . (int)$r['id']) ?>"><?= h($r['title']) ?></a>
              </td>
              <td class="small text-muted"><?= h(page_url((string)$r['slug'])) ?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="<?= $r['is_published'] ? 'unpublish' : 'publish' ?>">
                  <button class="btn btn-sm btn-<?= $r['is_published'] ? 'success' : 'outline-secondary' ?>" style="font-size:.72rem">
                    <?= $r['is_published'] ? 'Live' : 'Draft' ?>
                  </button>
                </form>
              </td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="<?= $r['show_in_footer'] ? 'footer_off' : 'footer_on' ?>">
                  <button class="btn btn-sm btn-<?= $r['show_in_footer'] ? 'secondary' : 'outline-secondary' ?>" style="font-size:.72rem">
                    <?= $r['show_in_footer'] ? 'Shown' : 'Hidden' ?>
                  </button>
                </form>
              </td>
              <td class="small text-muted"><?= h(date('M j, Y', strtotime((string)$r['updated_at']))) ?></td>
              <td class="text-end pe-3">
                <a class="btn btn-sm btn-outline-secondary" style="font-size:.72rem"
                   href="<?= admin_url('page_edit.php?id=' . (int)$r['id']) ?>">Edit</a>
                <a class="btn btn-sm btn-outline-secondary" style="font-size:.72rem" target="_blank"
                   href="<?= h(page_url((string)$r['slug'])) ?>">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/_layout_end.php'; ?>
