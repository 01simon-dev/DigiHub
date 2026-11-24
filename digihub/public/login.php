<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect($_SESSION['user_role'] === 'admin' ? SITE_URL . 'admin/dashboard.php' : SITE_URL . 'creator/dashboard.php');
}

$pageTitle = 'Login';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            redirect($_SESSION['user_role'] === 'admin' ? SITE_URL . 'admin/dashboard.php' : SITE_URL . 'creator/dashboard.php');
        }
        $errors[] = $result['error'] ?? 'Unable to log in.';
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="form-card card">
    <h2>Welcome back</h2>
    <p class="subtitle">Log in to manage your products and uploads.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" action="" data-loading>
        <?= csrfField() ?>
        <div class="form-group">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon">
                <input id="password" type="password" name="password" required placeholder="Your password" autocomplete="current-password">
                <button type="button" data-password-toggle="password">Show</button>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Log in</button>
            <a href="<?= SITE_URL ?>index.php" class="btn btn-outline">Back to home</a>
        </div>
    </form>
</div>

<div class="form-card card" style="margin-top:1rem;">
    <h3 style="font-size:1rem;">Demo accounts</h3>
    <p class="small muted" style="margin-top:.4rem;">
        Admin: <strong>admin@digihub.cm</strong> / <strong>admin123</strong><br>
        Creator: <strong>creator@digihub.cm</strong> / <strong>creator123</strong>
    </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
