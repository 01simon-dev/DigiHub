<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect($_SESSION['user_role'] === 'admin' ? SITE_URL . 'admin/dashboard.php' : SITE_URL . 'creator/dashboard.php');
}

$pageTitle = 'Join as a Creator';
$coopIntent = coopIntent();
if ($coopIntent) {
    $pageTitle = 'Join the Co-op';
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = normalizeCameroonPhone(trim($_POST['phone'] ?? ''));

        if (!$email || !$password || !$fullName || !$phone) {
            $errors[] = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (!isValidCameroonPhone($phone)) {
            $errors[] = 'Phone must be exactly +237 6XX XXX XXX.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            $result = registerCreator($email, $password, $fullName, $phone);
            if ($result['success']) {
                setFlash('success', $coopIntent
                    ? 'Creator account created. Log in to open your co-op projects.'
                    : 'Account created. You can log in now.');
                redirect(SITE_URL . 'login.php' . ($coopIntent ? '?intent=coop' : ''));
            }
            $errors[] = $result['error'] ?? 'Could not create your account.';
        }
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="form-card card">
    <h2><?= $coopIntent ? 'Join as a creator to start a co-op' : 'Create your creator account' ?></h2>
    <p class="subtitle"><?= $coopIntent ? 'Create a free creator account to team up with other creators, agree your revenue split and track shared sales.' : 'Upload books, comics and artwork in a few minutes.' ?></p>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" action="" data-loading>
        <?= csrfField() ?>
        <?php if ($coopIntent): ?><input type="hidden" name="intent" value="coop"><?php endif; ?>
        <div class="form-group">
            <label for="full_name">Full name</label>
            <input id="full_name" type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Your name">
        </div>
        <div class="form-group">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="phone">Phone number</label>
            <input id="phone" class="phone-input" type="tel" name="phone" required
                   maxlength="16" pattern="\+237 6[0-9]{2} [0-9]{3} [0-9]{3}"
                   title="Use exactly +237 6XX XXX XXX"
                   value="<?= e(formatCameroonPhone($_POST['phone'] ?? '')) ?>" placeholder="+237 6XX XXX XXX" autocomplete="tel">
            <small>Cameroon format only: +237 6XX XXX XXX (16 characters).</small>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon">
                <input id="password" type="password" name="password" required minlength="6" placeholder="At least 6 characters">
                <button type="button" data-password-toggle="password">Show</button>
            </div>
            <small>Use at least 6 characters.</small>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create account</button>
            <a href="<?= SITE_URL ?>login.php" class="btn btn-outline">I already have an account</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
