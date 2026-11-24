<?php
// DigiHub - Product detail
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if (!$productId) {
    redirect(SITE_URL . 'index.php');
}

$product = getProduct($productId);
if (!$product || $product['status'] !== 'active') {
    redirect(SITE_URL . 'index.php');
}

$pageTitle = $product['title'];
$flash = getFlash();
$viewerEmail = $_SESSION['user_email'] ?? 'guest@email.com';
$liked = isLiked($productId, $viewerEmail);
$likeCount = getLikeCount($productId);
$comments = getComments($productId);

$previewPages = [];
if ($product['product_type'] === 'book') {
    $previewDir = __DIR__ . '/../uploads/previews/' . $productId . '/';
    if (is_dir($previewDir)) {
        for ($i = 1; $i <= FREE_PREVIEW_PAGES; $i++) {
            $previewPages[] = 'uploads/previews/' . $productId . '/page_' . $i . '.jpg';
        }
        $previewPages = array_values(array_filter($previewPages, function ($path) {
            return file_exists(__DIR__ . '/../' . $path);
        }));
    }
}

$coverPath = '';
if ($product['cover_path'] && file_exists(__DIR__ . '/../' . $product['cover_path'])) {
    $coverPath = $product['cover_path'];
} elseif ($product['product_type'] === 'image' && $product['file_path'] && file_exists(__DIR__ . '/../' . $product['file_path'])) {
    $coverPath = $product['file_path'];
}
$cover = $coverPath ? SITE_URL . 'download.php?file=' . urlencode($coverPath) : '';

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<?php if (($product['creator_id'] ?? null) === ($_SESSION['user_id'] ?? null)): ?>
    <div class="flash flash-info">This is your product. You can manage it from your dashboard.</div>
<?php endif; ?>

<nav class="small" style="margin-bottom:1rem;">
    <a href="<?= SITE_URL ?>index.php">Browse</a> <span class="muted">/ <?= e($product['title']) ?></span>
</nav>

<section class="detail-grid">
    <div class="card detail-image">
        <?php if ($cover): ?>
            <img src="<?= e($cover) ?>" alt="Cover of <?= e($product['title']) ?>">
        <?php else: ?>
            <div style="font-size:2.5rem;font-weight:800;color:var(--primary);"><?= strtoupper(substr(e($product['title']), 0, 2)) ?></div>
        <?php endif; ?>
    </div>

    <div class="card detail-info">
        <div class="detail-meta">
            <span class="tag tag-primary"><?= e($product['category']) ?></span>
            <span class="tag"><?= e($product['product_type']) ?></span>
        </div>
        <h1><?= e($product['title']) ?></h1>
        <div class="detail-price <?= $product['is_free'] ? 'free' : '' ?>">
            <?= $product['is_free'] ? 'Free download' : formatMoney($product['price']) ?>
        </div>
        <p class="detail-desc"><?= nl2br(e($product['description'] ?? '')) ?></p>

        <div class="detail-facts">
            <div><span class="muted">Creator</span><strong><?= e($product['creator_name']) ?></strong></div>
            <?php if ($product['product_type'] === 'book'): ?>
                <div><span class="muted">Pages</span><strong><?= e($product['page_count']) ?></strong></div>
                <div><span class="muted">Preview</span><strong>First <?= FREE_PREVIEW_PAGES ?> pages</strong></div>
            <?php else: ?>
                <div><span class="muted">Format</span><strong>Single image file</strong></div>
            <?php endif; ?>
        </div>

        <div class="detail-actions">
            <?php if ($product['is_free']): ?>
                <a class="btn btn-primary" data-loading href="<?= SITE_URL ?>download.php?file=<?= urlencode($product['file_path']) ?>&product_id=<?= $productId ?>">Download for free</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= SITE_URL ?>checkout.php?id=<?= $productId ?>">Buy now · <?= formatMoney($product['price']) ?></a>
            <?php endif; ?>
            <button type="button" id="like-btn"
                    class="btn btn-outline like-btn <?= $liked ? 'active' : '' ?>"
                    aria-pressed="<?= $liked ? 'true' : 'false' ?>"
                    data-product-id="<?= $productId ?>"
                    data-email="<?= e($viewerEmail) ?>"
                    data-endpoint="<?= SITE_URL ?>like.php">
                <i class="fa-solid fa-heart" data-like-icon aria-hidden="true"></i> <span id="like-label"><?= $liked ? 'Liked' : 'Like' ?></span>
                <span class="muted">(<span id="like-count"><?= $likeCount ?></span>)</span>
            </button>
        </div>
    </div>
</section>

<?php if ($product['product_type'] === 'book'): ?>
    <section class="section" style="margin-top:2rem;">
        <div class="section-head">
            <div><h3>Free preview</h3><p class="muted">Read the first <?= FREE_PREVIEW_PAGES ?> pages below. Scroll smoothly from page to page.</p></div>
        </div>
        <?php if (!empty($previewPages)): ?>
            <div class="card card-pad">
                <div class="preview-nav" aria-label="Preview pages">
                    <?php foreach ($previewPages as $index => $path): ?>
                        <button type="button" class="chip <?= $index === 0 ? 'active' : '' ?>" data-preview-page="preview-page-<?= $index + 1 ?>">
                            Page <?= $index + 1 ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="preview-reader" id="preview-reader" tabindex="0" aria-label="Book preview reader">
                    <?php foreach ($previewPages as $index => $path): ?>
                        <article class="preview-page" id="preview-page-<?= $index + 1 ?>">
                            <img src="<?= SITE_URL ?>download.php?file=<?= urlencode($path) ?>"
                                 alt="Preview page <?= $index + 1 ?> of <?= e($product['title']) ?>"
                                 loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$product['is_free']): ?>
                        <p class="preview-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> Pages 6 and above unlock after purchase.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card card-pad muted">No preview pages have been uploaded for this book yet.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="section" style="margin-top:2rem;">
    <div class="section-head"><h3>Comments</h3><p><?= count($comments) ?> <?= count($comments) === 1 ? 'comment' : 'comments' ?></p></div>

    <div class="comment-list" id="comment-list">
        <?php if (empty($comments)): ?>
            <div class="card card-pad muted empty-comment">No comments yet. Start the conversation below.</div>
        <?php else: ?>
            <?php foreach ($comments as $c): ?>
                <article class="comment card">
                    <div class="comment-head">
                        <span class="comment-author"><?= e($c['author_name'] ?: $c['author_email']) ?></span>
                        <span class="comment-date"><?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(e($c['comment_text'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form id="comment-form" class="card card-pad" method="POST" action="add_comment.php">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <h3 style="margin-bottom:1rem;">Leave a comment</h3>
        <div class="form-group">
            <label for="author_name">Your name</label>
            <input id="author_name" type="text" name="author_name" required placeholder="Your name">
        </div>
        <div class="form-group">
            <label for="author_email">Your email</label>
            <input id="author_email" type="email" name="author_email" required placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label for="comment_text">Message</label>
            <textarea id="comment_text" name="comment_text" rows="4" required placeholder="What did you think?"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Post comment</button>
    </form>
</section>

<section class="section" style="margin-top:1.5rem;">
    <details class="card card-pad">
        <summary style="cursor:pointer;font-weight:600;">Report this product</summary>
        <form id="report-form" method="POST" action="<?= SITE_URL ?>report.php" style="margin-top:1rem;">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <div class="form-group">
                <label for="reporter_email">Your email</label>
                <input id="reporter_email" type="email" name="reporter_email" required placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="reason">Reason</label>
                <textarea id="reason" name="reason" rows="3" required minlength="5" placeholder="Tell us what is wrong with this listing."></textarea>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Send report</button>
        </form>
    </details>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
