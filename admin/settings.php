<?php
/**
 * Business settings editor.
 *
 * Every field is generated from app/settings_defs.php — adding a key there
 * makes it appear here with no changes to this file.
 */
declare(strict_types=1);
require __DIR__ . '/_guard.php';

$errors = [];
$defs   = settings_defs();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors['_'] = 'Your session expired. Please try again.';
    } else {
        $save = [];
        $current = settings_all();

        /**
         * Iterate the DEFINITIONS, never $_POST.
         *
         * An unchecked checkbox is not submitted at all. Walking $_POST would
         * therefore never see a toggle being switched off, and every "off"
         * would silently keep its old value — the single most common settings
         * form bug. Driving the loop from the defs makes absence meaningful.
         */
        foreach ($defs as $group) {
            foreach ($group['fields'] as $key => $def) {
                $type = $def['type'] ?? 'text';

                if ($type === 'bool') {
                    // Judge the value, not just the key's presence. A browser
                    // omits an unchecked box entirely, but any client that
                    // posts the field with an empty value means "off" too —
                    // isset() alone would read that as "on".
                    $v = $_POST[$key] ?? null;
                    $save[$key] = ($v !== null && $v !== '' && $v !== '0' && $v !== 'off') ? '1' : '0';
                    continue;
                }
                if (!array_key_exists($key, $_POST)) {
                    continue; // field not rendered in this submission
                }

                $raw = trim((string)$_POST[$key]);

                switch ($type) {
                    case 'email':
                        if ($raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                            $errors[$key] = 'Not a valid email address.';
                            continue 2;
                        }
                        break;

                    case 'url':
                        /**
                         * filter_var(..., FILTER_VALIDATE_URL) ACCEPTS
                         * "javascript:alert(1)" — it is a syntactically valid
                         * URL. These values render as footer links on every
                         * page, so accepting one would be stored XSS. Require
                         * an explicit http(s) scheme as well.
                         */
                        if ($raw !== '') {
                            if (!preg_match('~^https?://~i', $raw) || !filter_var($raw, FILTER_VALIDATE_URL)) {
                                $errors[$key] = 'Enter a full URL starting with http:// or https://';
                                continue 2;
                            }
                        }
                        break;

                    case 'number':
                        if ($raw === '') {
                            $raw = (string)($def['default'] ?? '0');
                        }
                        if (!is_numeric($raw)) {
                            $errors[$key] = 'Enter a number.';
                            continue 2;
                        }
                        $num = (float)$raw;
                        if (isset($def['min']) && $num < (float)$def['min']) {
                            $num = (float)$def['min'];
                        }
                        if (isset($def['max']) && $num > (float)$def['max']) {
                            $num = (float)$def['max'];
                        }
                        $raw = (string)(floor($num) == $num ? (int)$num : $num);
                        break;

                    case 'select':
                        if (!array_key_exists($raw, $def['options'] ?? [])) {
                            $errors[$key] = 'Choose one of the listed options.';
                            continue 2;
                        }
                        break;

                    default:
                        $max = (int)($def['max'] ?? 65535);
                        $raw = mb_substr($raw, 0, $max);
                }

                $save[$key] = $raw;
            }
        }

        // Only write what actually changed, so updated_at stays meaningful.
        $changed = [];
        foreach ($save as $k => $v) {
            if (!array_key_exists($k, $current) || $current[$k] !== $v) {
                $changed[$k] = $v;
            }
        }

        if (!$errors) {
            if ($changed) {
                try {
                    setting_set_many($changed);
                    // A timezone change should take effect immediately.
                    if (isset($changed['timezone']) && in_array($changed['timezone'], timezone_identifiers_list(), true)) {
                        date_default_timezone_set($changed['timezone']);
                    }
                    flash(count($changed) . ' setting(s) saved.');
                } catch (Throwable $e) {
                    error_log('[admin/settings] ' . $e->getMessage());
                    $errors['_'] = 'Could not save. Please try again.';
                }
            } else {
                flash('No changes to save.');
            }
            if (!$errors) {
                header('Location: ' . admin_url('settings.php'));
                exit;
            }
        }
    }
}

$stale = placeholder_settings();

$adminTitle = 'Settings';
require __DIR__ . '/_layout.php';
?>

<?php if (isset($errors['_'])): ?>
  <div class="alert alert-danger py-2 small"><?= h($errors['_']) ?></div>
<?php elseif ($errors): ?>
  <div class="alert alert-danger py-2 small">
    <?= count($errors) ?> field(s) could not be saved. Nothing was written — fix the highlighted fields and save again.
  </div>
<?php endif; ?>

<?php if ($stale): ?>
  <div class="alert alert-warning py-2 small">
    <strong><?= count($stale) ?> setting(s) still hold placeholder values.</strong>
    Replace them with your real business details before the site goes public —
    fake contact information is the fastest way for a store to be read as fraudulent.
  </div>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($defs as $gk => $g): ?>
      <a class="btn btn-sm btn-outline-secondary" href="#grp-<?= h($gk) ?>"><?= h($g['label']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php foreach ($defs as $gk => $g): ?>
    <section class="card-a grp" id="grp-<?= h($gk) ?>">
      <div class="hd"><?= h($g['label']) ?></div>
      <div class="bd">
        <?php if (!empty($g['hint'])): ?>
          <p class="text-muted small mb-4"><?= h($g['hint']) ?></p>
        <?php endif; ?>

        <div class="row g-3">
          <?php foreach ($g['fields'] as $key => $def):
              $type  = $def['type'] ?? 'text';
              $value = array_key_exists($key, $_POST) && $_SERVER['REQUEST_METHOD'] === 'POST'
                  ? (string)$_POST[$key]
                  : setting($key);
              $bad   = isset($errors[$key]);
              $isStale = isset($stale[$key]);
              $wide  = in_array($type, ['textarea'], true);
          ?>
            <div class="col-12 <?= $wide ? '' : 'col-lg-6' ?>">
              <?php if ($type === 'bool'): ?>
                <div class="chk-row">
                  <input class="form-check-input mt-1" type="checkbox" id="f-<?= h($key) ?>"
                         name="<?= h($key) ?>" value="1" <?= setting_bool($key) ? 'checked' : '' ?>>
                  <label class="form-label mb-0" for="f-<?= h($key) ?>">
                    <?= h($def['label']) ?>
                    <?php if (!empty($def['hint'])): ?>
                      <span class="d-block form-text text-muted fw-normal"><?= h($def['hint']) ?></span>
                    <?php endif; ?>
                  </label>
                </div>

              <?php elseif ($type === 'select'): ?>
                <label class="form-label" for="f-<?= h($key) ?>"><?= h($def['label']) ?></label>
                <select class="form-select<?= $bad ? ' is-invalid' : '' ?>" id="f-<?= h($key) ?>" name="<?= h($key) ?>">
                  <?php foreach ($def['options'] as $ov => $ol): ?>
                    <option value="<?= h((string)$ov) ?>" <?= $value === (string)$ov ? 'selected' : '' ?>><?= h((string)$ol) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (!empty($def['hint'])): ?><div class="form-text"><?= h($def['hint']) ?></div><?php endif; ?>

              <?php elseif ($type === 'textarea'): ?>
                <label class="form-label" for="f-<?= h($key) ?>"><?= h($def['label']) ?></label>
                <textarea class="form-control<?= $bad ? ' is-invalid' : '' ?>" id="f-<?= h($key) ?>"
                          name="<?= h($key) ?>" rows="3"><?= h($value) ?></textarea>
                <?php if (!empty($def['hint'])): ?><div class="form-text"><?= h($def['hint']) ?></div><?php endif; ?>

              <?php else: ?>
                <label class="form-label" for="f-<?= h($key) ?>">
                  <?= h($def['label']) ?>
                  <?php if ($isStale): ?>
                    <span class="badge text-bg-warning" style="font-size:.62rem;vertical-align:middle">placeholder</span>
                  <?php endif; ?>
                </label>
                <input class="form-control<?= $bad ? ' is-invalid' : '' ?>"
                       id="f-<?= h($key) ?>" name="<?= h($key) ?>"
                       type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>"
                       <?= isset($def['step']) ? 'step="' . h((string)$def['step']) . '"' : '' ?>
                       <?= isset($def['min']) ? 'min="' . h((string)$def['min']) . '"' : '' ?>
                       <?= isset($def['max']) && $type === 'number' ? 'max="' . h((string)$def['max']) . '"' : '' ?>
                       value="<?= h($value) ?>">
                <?php if (!empty($def['hint'])): ?><div class="form-text"><?= h($def['hint']) ?></div><?php endif; ?>
              <?php endif; ?>

              <?php if ($bad): ?>
                <div class="text-danger small mt-1"><?= h($errors[$key]) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="sticky-save">
    <button class="btn btn-primary px-4">Save settings</button>
    <a class="btn btn-link" href="<?= admin_url() ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>
