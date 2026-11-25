<?php
// DigiHub - Public feedback and suggestions
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Feedback';
$flash = getFlash();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $name = trim($_POST['author_name'] ?? '');
        $email = trim($_POST['author_email'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$name) $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if ($rating < 1 || $rating > 5) $errors[] = 'Please choose a rating.';
        if (strlen($message) < 10) $errors[] = 'Please write at least 10 characters of feedback.';

        if (empty($errors)) {
            addFeedback($name, $email, $rating, $message);
            setFlash('success', 'Thank you! Your feedback was sent to the admin team.');
            redirect(SITE_URL . 'feedback.php');
        }
    }
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="form-card card wide">
    <h2><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Share your feedback</h2>
    <p class="subtitle">Visitors and creators can send suggestions, issues or ideas to the admin team.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" action="" data-loading>
        <?= csrfField() ?>
        <div class="form-group">
            <label for="author_name">Your name</label>
            <input id="author_name" type="text" name="author_name" required
                   value="<?= e($_POST['author_name'] ?? ($_SESSION['user_name'] ?? '')) ?>" placeholder="Your name">
        </div>
        <div class="form-group">
            <label for="author_email">Email address</label>
            <input id="author_email" type="email" name="author_email" required
                   value="<?= e($_POST['author_email'] ?? ($_SESSION['user_email'] ?? '')) ?>" placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="rating">Overall rating</label>
            <select id="rating" name="rating" required>
                <option value="">Choose a rating</option>
                <?php
                $selected = (int)($_POST['rating'] ?? 0);
                $labels = [5 => '5 - Excellent', 4 => '4 - Very good', 3 => '3 - Good', 2 => '2 - Fair', 1 => '1 - Needs work'];
                foreach ($labels as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $selected === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="message">Your feedback or suggestion</label>
            <textarea id="message" name="message" required minlength="10" placeholder="What did you like? What should we improve?"><?= e($_POST['message'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send feedback</button>
            <a href="<?= SITE_URL ?>" class="btn btn-outline">Back to home</a>
        </div>
    </form>
</div>

<section class="section" style="margin-top:1.5rem;">
    <div class="card card-pad">
        <div class="feature-icon"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i></div>
        <h3 style="font-size:1.05rem;">Your message goes straight to the admin dashboard.</h3>
        <p class="muted small">Admins can reply to each message. If you are logged in, their replies appear below.</p>
    </div>
</section>

<?php if (isLoggedIn() && !empty($_SESSION['user_email'])): ?>
    <?php $myFeedback = getFeedbackForEmail($_SESSION['user_email']); ?>
    <section class="section">
        <div class="section-head"><h3>Your feedback and admin replies</h3><p class="muted"><?= count($myFeedback) ?> total</p></div>
        <?php if (empty($myFeedback)): ?>
            <div class="card empty-state"><p class="muted">You have not sent any feedback yet.</p></div>
        <?php else: ?>
            <div class="thread-list">
                <?php foreach ($myFeedback as $f): ?>
                    <article class="card thread">
                        <div class="thread-head">
                            <span class="thread-title">Your feedback
                                <span class="badge badge-<?= e($f['status']) ?>"><?= e($f['status']) ?></span>
                            </span>
                            <span class="comment-date"><?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                        </div>
                        <div class="thread-body"><?= nl2br(e($f['message'])) ?></div>
                        <?php if (!empty($f['admin_notes'])): ?>
                            <div class="admin-reply"><strong>Admin conversation:</strong><br><?= nl2br(e($f['admin_notes'])) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
