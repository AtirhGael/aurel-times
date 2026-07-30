<?php
/**
 * Content page editor.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';

$pdo    = db();
$id     = (int)($_GET['id'] ?? 0);
$isNew  = ($id === 0);
$errors = [];

if ($isNew) {
    $page = ['id' => 0, 'slug' => '', 'title' => '', 'body' => '', 'meta_title' => '',
             'meta_desc' => '', 'is_published' => 1, 'show_in_footer' => 1, 'sort_order' => 100];
} else {
    $st = $pdo->prepare('SELECT * FROM content_pages WHERE id = ?');
    $st->execute([$id]);
    $page = $st->fetch();
    if (!$page) {
        http_response_code(404);
        exit('<h1>404 Not Found</h1>');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $slug      = slugify((string)($_POST['slug'] ?? ''));
    $title     = trim((string)($_POST['title'] ?? ''));
    $body      = (string)($_POST['body'] ?? '');
    $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
    $metaDesc  = trim((string)($_POST['meta_desc'] ?? ''));
    $published = isset($_POST['is_published']) ? 1 : 0;
    $inFooter  = isset($_POST['show_in_footer']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if ($slug === '')  $errors[] = 'A URL slug is required.';
    if ($title === '') $errors[] = 'A title is required.';

    if ($slug !== '') {
        $dupe = $pdo->prepare('SELECT id FROM content_pages WHERE slug = ? AND id <> ?');
        $dupe->execute([$slug, $id]);
        if ($dupe->fetch()) {
            $errors[] = 'Another page already uses the slug "' . $slug . '".';
        }
    }

    // Keep the operator's edits on screen if validation fails.
    $page = array_merge($page, [
        'slug' => $slug, 'title' => $title, 'body' => $body, 'meta_title' => $metaTitle,
        'meta_desc' => $metaDesc, 'is_published' => $published,
        'show_in_footer' => $inFooter, 'sort_order' => $sortOrder,
    ]);

    if (!$errors) {
        try {
            if ($isNew) {
                $pdo->prepare(
                    'INSERT INTO content_pages (slug, title, body, meta_title, meta_desc,
                                                is_published, show_in_footer, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$slug, $title, $body, mb_substr($metaTitle, 0, 200),
                            mb_substr($metaDesc, 0, 320), $published, $inFooter, $sortOrder]);
                $id = (int)$pdo->lastInsertId();
            } else {
                $pdo->prepare(
                    'UPDATE content_pages SET slug = ?, title = ?, body = ?, meta_title = ?,
                            meta_desc = ?, is_published = ?, show_in_footer = ?, sort_order = ?
                     WHERE id = ?'
                )->execute([$slug, $title, $body, mb_substr($metaTitle, 0, 200),
                            mb_substr($metaDesc, 0, 320), $published, $inFooter, $sortOrder, $id]);
            }
            flash('Page saved.');
            header('Location: ' . admin_url('page_edit.php?id=' . $id));
            exit;
        } catch (Throwable $e) {
            error_log('[admin/page_edit] ' . $e->getMessage());
            $errors[] = 'Could not save the page. Please try again.';
        }
    }
}

$vars = page_vars();
ksort($vars);

$adminTitle = $isNew ? 'New page' : 'Edit: ' . $page['title'];
require __DIR__ . '/_layout.php';
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger py-2 small"><?= h($e) ?></div>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <div class="row g-4">

    <div class="col-lg-8">
      <section class="card-a">
        <div class="bd">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="<?= h((string)$page['title']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Body (HTML)</label>
            <?php
            /**
             * h() on the value is mandatory here: without it, a </textarea>
             * anywhere in the stored body would close the field early and
             * mangle the rest of the form.
             */
            ?>
            <textarea class="form-control" name="body" rows="26"
                      style="font:13px/1.5 ui-monospace,Consolas,monospace"><?= h((string)$page['body']) ?></textarea>
            <div class="form-text">
              HTML is allowed and rendered as-is. Only administrators can edit this field.
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-lg-4">
      <section class="card-a mb-3">
        <div class="hd">Publishing</div>
        <div class="bd">
          <div class="chk-row mb-2">
            <input class="form-check-input mt-1" type="checkbox" id="pub" name="is_published" value="1"
                   <?= $page['is_published'] ? 'checked' : '' ?>>
            <label class="form-label mb-0" for="pub">Published</label>
          </div>
          <div class="chk-row mb-3">
            <input class="form-check-input mt-1" type="checkbox" id="foot" name="show_in_footer" value="1"
                   <?= $page['show_in_footer'] ? 'checked' : '' ?>>
            <label class="form-label mb-0" for="foot">Show in footer</label>
          </div>
          <div class="mb-3">
            <label class="form-label">URL slug</label>
            <input class="form-control" name="slug" value="<?= h((string)$page['slug']) ?>" required>
            <div class="form-text">
              <?php if (!$isNew): ?>
                <a href="<?= h(page_url((string)$page['slug'])) ?>" target="_blank"><?= h(page_url((string)$page['slug'])) ?></a>
              <?php else: ?>
                Lowercase, hyphenated.
              <?php endif; ?>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Sort order</label>
            <input class="form-control" type="number" name="sort_order" value="<?= (int)$page['sort_order'] ?>">
            <div class="form-text">Lower numbers appear first in the footer.</div>
          </div>
        </div>
      </section>

      <section class="card-a mb-3">
        <div class="hd">Search listing</div>
        <div class="bd">
          <div class="mb-3">
            <label class="form-label">Meta title</label>
            <input class="form-control" name="meta_title" value="<?= h((string)$page['meta_title']) ?>">
            <div class="form-text">Leave empty to use the page title.</div>
          </div>
          <div class="mb-0">
            <label class="form-label">Meta description</label>
            <textarea class="form-control" name="meta_desc" rows="3" maxlength="320"><?= h((string)$page['meta_desc']) ?></textarea>
            <div class="form-text">Aim for 150-160 characters.</div>
          </div>
        </div>
      </section>

      <section class="card-a">
        <div class="hd">Placeholders</div>
        <div class="bd">
          <p class="small text-muted">
            Type any of these into the body and it is replaced with the live setting when the page renders.
            Change the setting once and every page follows.
          </p>
          <p class="small text-muted mb-3">
            <strong>Safe</strong> in normal text and in attribute values — values are HTML-escaped automatically.
            <strong>Not safe</strong> inside a <code class="ph">&lt;script&gt;</code> or
            <code class="ph">&lt;style&gt;</code> block, or as a <code class="ph">javascript:</code> URL.
            An unrecognised placeholder is left on the page verbatim so you can spot the typo.
          </p>
          <div style="max-height:320px;overflow:auto;border:1px solid var(--line);border-radius:6px;padding:10px">
            <?php foreach ($vars as $k => $v): ?>
              <div class="d-flex justify-content-between gap-2 small py-1" style="border-bottom:1px solid #f0f1f4">
                <code class="ph">{{<?= h((string)$k) ?>}}</code>
                <span class="text-muted text-end" style="font-size:.75rem;max-width:52%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?= h(mb_strimwidth((string)$v, 0, 40, '…')) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>
  </div>

  <div class="sticky-save">
    <button class="btn btn-primary px-4">Save page</button>
    <a class="btn btn-link" href="<?= admin_url('pages.php') ?>">Back to pages</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>
