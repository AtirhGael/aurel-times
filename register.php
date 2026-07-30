<?php
require_once __DIR__ . '/app/helpers.php';
if (current_user()) { header('Location: ' . url('account.php')); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) $errors[] = 'Session expired. Please try again.';
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!$errors) {
        $chk = db()->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'That email is already registered.';
        } else {
            $ins = db()->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
            $ins->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name]);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            flash('Welcome, ' . $name . '!');
            header('Location: ' . url('account.php'));
            exit;
        }
    }
}
$pageTitle = 'Create Account';
require __DIR__ . '/app/header.php';
?>
<div class="container py-5" style="max-width:460px">
  <h1 class="section-title mb-4">Create Account</h1>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" class="p-4 rounded" style="background:var(--card);border:1px solid var(--line)">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <div class="mb-3"><label class="form-label small text-muted">Full Name</label>
      <input name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label small text-muted">Email</label>
      <input name="email" type="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required></div>
    <div class="mb-3"><label class="form-label small text-muted">Password</label>
      <input name="password" type="password" class="form-control" minlength="6" required></div>
    <button class="btn btn-gold w-100">Create Account</button>
  </form>
  <p class="text-center text-muted small mt-3">Already have an account? <a class="text-gold" href="<?= url('login.php') ?>">Sign in</a></p>
</div>
<?php require __DIR__ . '/app/footer.php'; ?>
