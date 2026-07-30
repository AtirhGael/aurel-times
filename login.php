<?php
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';

// ?next= lets admin/_guard.php send an unauthenticated visitor here and get
// them back to where they were going. safe_next() rejects anything that is not
// a path inside this app, so this cannot become an open redirect.
$next = safe_next((string)($_REQUEST['next'] ?? ''), url('account.php'));

if (current_user()) { header('Location: ' . $next); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) $errors[] = 'Session expired. Please try again.';
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    if (!$errors) {
        $st = db()->prepare('SELECT id, password_hash, name FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$user['id'];
            flash('Welcome back, ' . explode(' ', $user['name'])[0] . '!');
            header('Location: ' . $next);
            exit;
        }
        $errors[] = 'Invalid email or password.';
    }
}
$pageTitle = 'Sign In';
$robots    = 'noindex, nofollow';
require __DIR__ . '/app/header.php';
?>
<div class="container py-5" style="max-width:460px">
  <h1 class="section-title mb-4">Sign In</h1>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <div class="mb-3"><label class="form-label small text-muted">Email</label>
      <input name="email" type="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label small text-muted">Password</label>
      <input name="password" type="password" class="form-control" required></div>
    <button class="btn btn-gold w-100">Sign In</button>
  </form>
  <p class="text-center text-muted small mt-3">
    New here? <a class="text-gold" href="<?= url('register.php') ?>">Create an account</a>
  </p>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
