<?php
// DigiHub - Support inbox for admins / enquiry form for creators
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (!$isAdmin) requireCreator();

$pageTitle = $isAdmin ? 'Support Inbox' : 'Support';
$flash = getFlash();
$errors = [];
$adminName = $_SESSION['user_name'] ?? 'Admin';
$categories = [
    'account' => 'Account or login',
    'product' => 'A product or upload',
    'payment' => 'Payment or slots',
    'platform' => 'Platform issue',
    'other' => 'Something else'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif ($isAdmin) {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if (($_POST['action'] ?? '') === 'support_reply') {
            if (strlen($reply) < 2) {
                setFlash('error', 'Write a reply before sending.');
            } else {
                $status = in_array($_POST['status'] ?? '', ['replied', 'resolved'], true) ? $_POST['status'] : 'replied';
                replyToSupportMessage($targetId, $reply, $status, $adminName);
                setFlash('success', $status === 'resolved' ? 'Reply sent and request resolved.' : 'Reply sent.');
            }
        } elseif (($_POST['action'] ?? '') === 'support_status') {
            $status = in_array($_POST['status'] ?? '', ['open', 'replied', 'resolved'], true) ? $_POST['status'] : 'resolved';
            setSupportStatus($targetId, $status);
            setFlash('success', 'Support request updated.');
        }
        redirect(SITE_URL . 'support.php');
    } else {
        $category = $_POST['category'] ?? 'other';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!isset($categories[$category])) $errors[] = 'Please choose a category.';
        if (strlen($subject) < 5) $errors[] = 'Please write a short subject (at least 5 characters).';
        if (strlen($message) < 10) $errors[] = 'Please describe your question (at least 10 characters).';

        if (empty($errors)) {
            addSupportMessage($_SESSION['user_id'], $category, $subject, $message);
            setFlash('success', 'Your message was sent to the admin team.');
            redirect(SITE_URL . 'support.php');
        }
    }
}

$messages = $isAdmin ? getSupportMessages() : getSupportMessagesForUser($_SESSION['user_id']);

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2><i class="fa-solid fa-headset" aria-hidden="true"></i> <?= $isAdmin ? 'Support inbox' : 'Support' ?></h2>
        <p class="muted">
            <?= $isAdmin
                ? 'Reply to creators and users here. Everything is also visible on your dashboard.'
                : 'Need help with your account, a product or the platform? Send a message directly to the admin team.' ?>
        </p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="<?= SITE_URL ?>admin/dashboard.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Back to dashboard</a>
    <?php endif; ?>
</div>

<?php if (!$isAdmin): ?>
    <div class="form-card card wide">
        <h3>New enquiry</h3>
        <p class="subtitle">Admin replies appear below with your messages.</p>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form method="POST" action="" data-loading>
            <?= csrfField() ?>
            <div class="form-group">
                <label for="category">What is it about?</label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (($_POST['category'] ?? '') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject">Subject</label>
                <input id="subject" type="text" name="subject" required minlength="5" maxlength="200"
                       value="<?= e($_POST['subject'] ?? '') ?>" placeholder="Brief summary of your issue">
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" required minlength="10" placeholder="Tell us what happened and how we can help."><?= e($_POST['message'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send message</button>
                <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-outline">Back to dashboard</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<section class="section" style="margin-top:2rem;">
    <div class="section-head">
        <h3><?= $isAdmin ? 'All conversations' : 'Your messages' ?></h3>
        <p class="muted"><?= count($messages) ?> total</p>
    </div>
    <?php if (empty($messages)): ?>
        <div class="card empty-state"><div class="empty-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></div><p class="muted">No support messages yet.</p></div>
    <?php else: ?>
        <div class="thread-list">
            <?php foreach ($messages as $m): ?>
                <article class="card thread">
                    <div class="thread-head">
                        <span class="thread-title"><?= e($m['subject']) ?>
                            <span class="badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span>
                        </span>
                        <span class="comment-date">
                            <?= $isAdmin ? e($m['user_name']) . ' &lt;' . e($m['user_email']) . '&gt;' : 'You' ?>
                            · <?= date('M j, Y', strtotime($m['created_at'])) ?>
                        </span>
                    </div>
                    <p><span class="tag tag-primary"><?= e($m['category']) ?></span></p>
                    <div class="thread-body"><?= nl2br(e($m['message'])) ?></div>
                    <?php if (!empty($m['admin_notes'])): ?>
                        <div class="admin-reply"><strong>Admin conversation:</strong><br><?= nl2br(e($m['admin_notes'])) ?></div>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <form method="POST" class="reply-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="support_reply">
                            <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="status" value="replied">
                            <div class="form-group">
                                <label class="sr-only" for="support-reply-<?= $m['id'] ?>">Reply to <?= e($m['user_name']) ?></label>
                                <textarea id="support-reply-<?= $m['id'] ?>" name="reply" required minlength="2" placeholder="Write a reply..."></textarea>
                            </div>
                            <div class="thread-actions">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-reply" aria-hidden="true"></i> Send reply</button>
                                <button type="submit" class="btn btn-outline btn-sm"
                                        onclick="this.form.querySelector('input[name=status]').value='resolved'">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> Reply &amp; resolve
                                </button>
                            </div>
                        </form>
                        <form method="POST" class="thread-actions">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="support_status">
                            <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                            <?php if ($m['status'] !== 'resolved'): ?>
                                <button class="btn btn-outline btn-sm" name="status" value="resolved">Mark resolved</button>
                            <?php else: ?>
                                <button class="btn btn-outline btn-sm" name="status" value="open">Reopen</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
