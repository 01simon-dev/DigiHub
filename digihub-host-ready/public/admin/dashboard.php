<?php
// DigiHub - Admin dashboard (single overview for moderation and replies)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';
$adminName = $_SESSION['user_name'] ?? 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $action = $_POST['action'] ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');

        if ($action === 'report' && in_array($_POST['decision'] ?? '', ['resolved', 'dismissed'], true)) {
            updateReportStatus((int)($_POST['report_id'] ?? 0), $_POST['decision'], $_SESSION['user_id'], trim($_POST['admin_notes'] ?? ''));
            setFlash('success', 'Report updated.');
        } elseif ($action === 'product_status') {
            $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'suspended';
            setProductStatus($targetId, $status);
            setFlash('success', $status === 'active' ? 'Product activated.' : 'Product suspended.');
        } elseif ($action === 'product_delete') {
            $deleted = deleteProduct($targetId);
            setFlash($deleted ? 'success' : 'error',
                $deleted ? 'Product and its files were deleted.' : 'Could not delete that product.');
        } elseif ($action === 'user_status') {
            $user = getUserById($targetId);
            if (!$user || $user['role'] === 'admin') {
                setFlash('error', 'Admin accounts cannot be suspended here.');
            } elseif ($targetId === (int)$_SESSION['user_id']) {
                setFlash('error', 'You cannot suspend your own account.');
            } else {
                $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'suspended';
                setUserStatus($targetId, $status);
                setFlash('success', $status === 'active' ? 'Account reactivated.' : 'Account suspended.');
            }
        } elseif ($action === 'user_delete') {
            $user = getUserById($targetId);
            if (!$user || $user['role'] === 'admin') {
                setFlash('error', 'Admin accounts cannot be deleted here.');
            } elseif ($targetId === (int)$_SESSION['user_id']) {
                setFlash('error', 'You cannot delete your own account.');
            } else {
                $deleted = deleteUser($targetId);
                setFlash($deleted ? 'success' : 'error',
                    $deleted ? 'Account and its products were deleted.' : 'Could not delete that account.');
            }
        } elseif ($action === 'support_status') {
            $status = in_array($_POST['status'] ?? '', ['open', 'replied', 'resolved'], true) ? $_POST['status'] : 'resolved';
            setSupportStatus($targetId, $status);
            setFlash('success', 'Support request updated.');
        } elseif ($action === 'support_reply') {
            if (strlen($reply) < 2) {
                setFlash('error', 'Write a reply before sending.');
            } else {
                $status = in_array($_POST['status'] ?? '', ['replied', 'resolved'], true) ? $_POST['status'] : 'replied';
                replyToSupportMessage($targetId, $reply, $status, $adminName);
                setFlash('success', $status === 'resolved' ? 'Reply sent and request resolved.' : 'Reply sent to the creator.');
            }
        } elseif ($action === 'feedback_status') {
            $status = in_array($_POST['status'] ?? '', ['new', 'reviewed', 'archived'], true) ? $_POST['status'] : 'reviewed';
            setFeedbackStatus($targetId, $status);
            setFlash('success', $status === 'archived' ? 'Feedback moved to the Archived tab.' : 'Feedback restored.');
        } elseif ($action === 'feedback_reply') {
            if (strlen($reply) < 2) {
                setFlash('error', 'Write a reply before sending.');
            } else {
                $status = in_array($_POST['status'] ?? '', ['reviewed', 'archived'], true) ? $_POST['status'] : 'reviewed';
                replyToFeedback($targetId, $reply, $status, $adminName);
                setFlash('success', $status === 'archived' ? 'Reply sent and feedback archived.' : 'Reply sent to the author.');
            }
        }
    }
    redirect(SITE_URL . 'admin/dashboard.php');
}

$flash = getFlash();
$stats = getAdminStats();
$reports = getReports();
$supportMessages = getSupportMessages();
$allFeedback = getFeedback();
$view = $_GET['view'] ?? 'all';
if (!in_array($view, ['all', 'new', 'reviewed', 'archived'], true)) $view = 'all';
$feedbackList = $view === 'all' ? $allFeedback : getFeedback($view);
$feedbackCounts = ['all' => count($allFeedback), 'new' => 0, 'reviewed' => 0, 'archived' => 0];
foreach ($allFeedback as $f) {
    if (isset($feedbackCounts[$f['status']])) $feedbackCounts[$f['status']]++;
}
$slotPurchases = getAllImageSlotPurchases();
$shelfPurchases = getAllShelfSpaces();
$products = getAllProducts();
$users = getAllUsers();

require __DIR__ . '/../partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>Admin dashboard</h2>
        <p class="muted">Everything in one place: reports, messages, listings and purchased add-ons.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat"><div class="stat-label">Products</div><div class="stat-value"><?= $stats['products'] ?></div></div>
    <div class="card stat"><div class="stat-label">Users</div><div class="stat-value"><?= $stats['users'] ?></div></div>
    <div class="card stat"><div class="stat-label">Purchases</div><div class="stat-value"><?= $stats['purchases'] ?></div></div>
    <div class="card stat"><div class="stat-label">Open reports</div><div class="stat-value"><?= $stats['pending_reports'] ?></div></div>
    <div class="card stat"><div class="stat-label">Support requests</div><div class="stat-value"><?= $stats['open_support'] ?></div></div>
    <div class="card stat"><div class="stat-label">New feedback</div><div class="stat-value"><?= $stats['new_feedback'] ?></div></div>
    <div class="card stat"><div class="stat-label">Image slots purchased</div><div class="stat-value"><?= $stats['image_slots_purchased'] ?></div><div class="small muted"><?= $stats['image_slot_orders'] ?> order<?= $stats['image_slot_orders'] === 1 ? '' : 's' ?></div></div>
    <div class="card stat"><div class="stat-label">Bookshelf spaces purchased</div><div class="stat-value"><?= $stats['shelf_spaces_purchased'] ?></div><div class="small muted"><?= $stats['shelf_space_orders'] ?> order<?= $stats['shelf_space_orders'] === 1 ? '' : 's' ?></div></div>
</div>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-flag" aria-hidden="true"></i> Reports</h3><p class="muted">Review and close community reports.</p></div>
    <?php if (empty($reports)): ?>
        <div class="card empty-state"><div class="empty-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div><h3>No reports</h3><p class="muted">Everything is clear right now.</p></div>
    <?php else: ?>
        <div class="card table-card">
            <div class="table-scroll admin-scroll">
                <table class="table admin-table with-actions table-reports">
                    <thead><tr><th>Product</th><th>Reporter</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><strong><?= e($r['product_title']) ?></strong><br><span class="small muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></span></td>
                            <td><?= e($r['reporter_email']) ?></td>
                            <td style="max-width:260px;"><?= nl2br(e($r['reason'])) ?></td>
                            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <form method="POST" style="display:flex;gap:.35rem;flex-wrap:wrap;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="report">
                                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                        <button class="btn btn-sm btn-primary" name="decision" value="resolved">Resolve</button>
                                        <button class="btn btn-sm btn-outline" name="decision" value="dismissed">Dismiss</button>
                                    </form>
                                <?php else: ?><span class="muted small">Closed</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-headset" aria-hidden="true"></i> Support conversations</h3><p class="muted">Reply to creators and users. Replies are visible on their Support page.</p></div>
    <?php if (empty($supportMessages)): ?>
        <div class="card empty-state"><p class="muted">No support requests yet.</p></div>
    <?php else: ?>
        <div class="thread-list">
            <?php foreach ($supportMessages as $s): ?>
                <article class="card thread">
                    <div class="thread-head">
                        <span class="thread-title"><?= e($s['subject']) ?>
                            <span class="badge badge-<?= e($s['status']) ?>"><?= e($s['status']) ?></span>
                        </span>
                        <span class="comment-date"><?= e($s['user_name']) ?> &lt;<?= e($s['user_email']) ?>&gt; · <?= date('M j, Y', strtotime($s['created_at'])) ?></span>
                    </div>
                    <p><span class="tag tag-primary"><?= e($s['category']) ?></span></p>
                    <div class="thread-body"><?= nl2br(e($s['message'])) ?></div>
                    <?php if (!empty($s['admin_notes'])): ?>
                        <div class="admin-reply"><strong>Conversation:</strong><br><?= nl2br(e($s['admin_notes'])) ?></div>
                    <?php endif; ?>
                    <form method="POST" class="reply-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="support_reply">
                        <input type="hidden" name="target_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="status" value="replied">
                        <div class="form-group">
                            <label class="sr-only" for="support-reply-<?= $s['id'] ?>">Reply to <?= e($s['user_name']) ?></label>
                            <textarea id="support-reply-<?= $s['id'] ?>" name="reply" required minlength="2" placeholder="Write a reply to <?= e($s['user_name']) ?>..."></textarea>
                        </div>
                        <div class="thread-actions">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-reply" aria-hidden="true"></i> Send reply</button>
                            <button type="submit" class="btn btn-outline btn-sm" formaction="<?= e(SITE_URL) ?>admin/dashboard.php"
                                    onclick="this.form.querySelector('input[name=status]').value='resolved'">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Reply &amp; resolve
                            </button>
                    </form>
                    <form method="POST" class="thread-actions">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="support_status">
                        <input type="hidden" name="target_id" value="<?= $s['id'] ?>">
                        <?php if ($s['status'] !== 'resolved'): ?>
                            <button class="btn btn-outline btn-sm" name="status" value="resolved">Mark resolved</button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" name="status" value="open">Reopen</button>
                        <?php endif; ?>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Feedback</h3><p class="muted">Reply to individual authors. Archived feedback stays in the Archived tab and can be restored.</p></div>
    <div class="status-tabs" aria-label="Filter feedback">
        <?php foreach (['all' => 'All', 'new' => 'New', 'reviewed' => 'Reviewed', 'archived' => 'Archived'] as $key => $label): ?>
            <a class="chip <?= $view === $key ? 'active' : '' ?>" href="<?= e(SITE_URL) ?>admin/dashboard.php?view=<?= $key ?>">
                <?= $label ?> (<?= $feedbackCounts[$key] ?>)
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($feedbackList)): ?>
        <div class="card empty-state"><p class="muted">No feedback in this view.</p></div>
    <?php else: ?>
        <div class="thread-list">
            <?php foreach ($feedbackList as $f): ?>
                <article class="card thread">
                    <div class="thread-head">
                        <span class="thread-title"><?= e($f['author_name'] ?: 'Anonymous') ?>
                            <span class="badge badge-<?= e($f['status']) ?>"><?= e($f['status']) ?></span>
                        </span>
                        <span class="comment-date"><?= e($f['author_email']) ?> · <?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                    </div>
                    <p>
                        <?php if ($f['rating']): ?>
                            <?php for ($i = 0; $i < (int)$f['rating']; $i++): ?><i class="fa-solid fa-star" style="color:var(--warning);" aria-hidden="true"></i><?php endfor; ?>
                        <?php else: ?><span class="muted small">No rating</span><?php endif; ?>
                    </p>
                    <div class="thread-body"><?= nl2br(e($f['message'])) ?></div>
                    <?php if (!empty($f['admin_notes'])): ?>
                        <div class="admin-reply"><strong>Conversation:</strong><br><?= nl2br(e($f['admin_notes'])) ?></div>
                    <?php endif; ?>
                    <form method="POST" class="reply-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="feedback_reply">
                        <input type="hidden" name="target_id" value="<?= $f['id'] ?>">
                        <input type="hidden" name="status" value="reviewed">
                        <div class="form-group">
                            <label class="sr-only" for="feedback-reply-<?= $f['id'] ?>">Reply to <?= e($f['author_name'] ?: 'this author') ?></label>
                            <textarea id="feedback-reply-<?= $f['id'] ?>" name="reply" required minlength="2" placeholder="Reply to this author..."></textarea>
                        </div>
                        <div class="thread-actions">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-reply" aria-hidden="true"></i> Send reply</button>
                            <button type="submit" class="btn btn-outline btn-sm"
                                    onclick="this.form.querySelector('input[name=status]').value='archived'">
                                <i class="fa-solid fa-box-archive" aria-hidden="true"></i> Reply &amp; archive
                            </button>
                        </div>
                    </form>
                    <form method="POST" class="thread-actions">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="feedback_status">
                        <input type="hidden" name="target_id" value="<?= $f['id'] ?>">
                        <?php if ($f['status'] === 'archived'): ?>
                            <button class="btn btn-outline btn-sm" name="status" value="new"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore from archive</button>
                        <?php else: ?>
                            <button class="btn btn-outline btn-sm" name="status" value="archived"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Archive</button>
                        <?php endif; ?>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Purchased image slots</h3><p class="muted">Extra image slots bought by creators.</p></div>
    <?php if (empty($slotPurchases)): ?>
        <div class="card empty-state"><p class="muted">No image slot purchases yet.</p></div>
    <?php else: ?>
        <div class="card table-card">
            <div class="table-scroll admin-scroll">
                <table class="table admin-table table-slots">
                    <thead><tr><th>Creator</th><th>Slots</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($slotPurchases as $sp): ?>
                        <tr>
                            <td><strong><?= e($sp['creator_name']) ?></strong><br><span class="small muted"><?= e($sp['creator_email']) ?></span></td>
                            <td><?= (int)$sp['slots'] ?></td>
                            <td><?= formatMoney($sp['price_paid']) ?></td>
                            <td><?= date('M j, Y', strtotime($sp['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-book" aria-hidden="true"></i> Bookshelf spaces</h3><p class="muted">Permanent shelf spaces purchased by creators.</p></div>
    <?php if (empty($shelfPurchases)): ?>
        <div class="card empty-state"><p class="muted">No bookshelf space purchases yet.</p></div>
    <?php else: ?>
        <div class="card table-card">
            <div class="table-scroll admin-scroll">
                <table class="table admin-table table-shelf">
                    <thead><tr><th>Creator</th><th>Book</th><th>Spaces</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($shelfPurchases as $sp): ?>
                        <tr>
                            <td><strong><?= e($sp['creator_name']) ?></strong><br><span class="small muted"><?= e($sp['creator_email']) ?></span></td>
                            <td><?= e($sp['product_title']) ?></td>
                            <td><?= (int)$sp['spaces_count'] ?></td>
                            <td><?= formatMoney($sp['price_paid']) ?></td>
                            <td><?= date('M j, Y', strtotime($sp['purchased_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Products</h3><p class="muted">Suspend, reactivate or permanently delete listings.</p></div>
    <div class="card table-card">
        <div class="table-scroll admin-scroll">
            <table class="table admin-table with-actions table-products">
                <thead><tr><th>Product</th><th>Creator</th><th>Price</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><strong><?= e($p['title']) ?></strong><br><span class="small muted"><?= e($p['category']) ?> · <?= e($p['product_type']) ?></span></td>
                        <td><?= e($p['creator_name']) ?></td>
                        <td><?= $p['is_free'] ? 'Free' : formatMoney($p['price']) ?></td>
                        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                        <td>
                            <div class="btn-group">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="product_status">
                                    <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm <?= $p['status'] === 'suspended' ? 'btn-primary' : 'btn-outline' ?>"
                                            name="status" value="<?= $p['status'] === 'suspended' ? 'active' : 'suspended' ?>">
                                        <?= $p['status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?>
                                    </button>
                                </form>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="product_delete">
                                    <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-danger" data-confirm="Permanently delete this product and all its files?" title="Delete product">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head"><h3><i class="fa-solid fa-users" aria-hidden="true"></i> Accounts</h3><p class="muted">Suspend or delete creator accounts. Admin accounts are protected.</p></div>
    <div class="card table-card">
        <div class="table-scroll admin-scroll">
            <table class="table admin-table with-actions table-users">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= e($u['full_name']) ?></strong><?= (int)$u['id'] === (int)$_SESSION['user_id'] ? ' <span class="tag tag-primary">You</span>' : '' ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e(formatCameroonPhone($u['phone'])) ?></td>
                        <td><span class="tag"><?= e($u['role']) ?></span></td>
                        <td><span class="badge badge-<?= e($u['status']) ?>"><?= e($u['status']) ?></span></td>
                        <td>
                            <?php if ($u['role'] === 'admin' || (int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                <span class="muted small">Protected</span>
                            <?php else: ?>
                                <div class="btn-group">
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="user_status">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm <?= $u['status'] === 'suspended' ? 'btn-primary' : 'btn-outline' ?>"
                                                name="status" value="<?= $u['status'] === 'suspended' ? 'active' : 'suspended' ?>">
                                            <?= $u['status'] === 'suspended' ? 'Reactivate' : 'Suspend' ?>
                                        </button>
                                    </form>
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="user_delete">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-danger" data-confirm="Permanently delete this account and all products uploaded by this creator?">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
