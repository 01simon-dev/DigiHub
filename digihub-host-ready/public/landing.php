<?php
// DigiHub - Public landing page (served at /digihub/)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Welcome';
$flash = getFlash();
$featured = array_slice(getActiveProducts(), 0, 3);
$stats = getAdminStats();

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="hero">
    <span class="tag tag-primary">Create together. Sell together. Get paid together.</span>
    <p>A co-op workspace and storefront for writers, comic artists and digital creators. Team up, agree your split, and every sale pays each collaborator automatically. Readers discover e-books, comics and artwork from Cameroon and beyond.</p>
    <div class="btn-group" style="justify-content:center;">
        <a href="<?= SITE_URL ?>index.php" class="btn btn-ghost"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Browse the library</a>
        <?php if (!isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>register.php" class="btn btn-ghost"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Join as a creator</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Go to dashboard</a>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="section-head"><h2>Why DigiHub</h2><p class="muted">A simple place to find and share local digital work.</p></div>
    <div class="stat-grid">
        <div class="card card-pad">
            <div class="feature-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></div>
            <h3 style="font-size:1.05rem;margin:.5rem 0;">Browse by category</h3>
            <p class="muted small">E-books, comics, artwork and more — filter or search to find something new.</p>
        </div>
        <div class="card card-pad">
            <div class="feature-icon"><i class="fa-solid fa-handshake" aria-hidden="true"></i></div>
            <h3 style="font-size:1.05rem;margin:.5rem 0;">Support creators</h3>
            <p class="muted small">Every download goes to an independent creator in your community.</p>
        </div>
        <div class="card card-pad">
            <div class="feature-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
            <h3 style="font-size:1.05rem;margin:.5rem 0;">Instant access</h3>
            <p class="muted small">Free downloads and a quick simulated checkout for paid work — no waiting.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div><h2>Fresh from the community</h2><p class="muted">A few things worth a look.</p></div>
        <a href="<?= SITE_URL ?>index.php" class="btn btn-outline btn-sm">See all products <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </div>

    <?php if (empty($featured)): ?>
        <div class="card empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
            <h3>Nothing published yet</h3>
            <p class="muted">Be the first creator to share something.</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($featured as $p): ?>
                <?php
                $cover = ($p['cover_path'] && file_exists(__DIR__ . '/../' . $p['cover_path']))
                    ? SITE_URL . 'download.php?file=' . urlencode($p['cover_path'])
                    : '';
                ?>
                <article class="card card-hover product-card">
                    <div class="product-thumb">
                        <?php if ($cover): ?>
                            <img src="<?= e($cover) ?>" alt="Cover of <?= e($p['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <?= strtoupper(substr(e($p['title']), 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <div class="tags">
                            <span class="tag tag-primary"><?= e($p['category']) ?></span>
                            <span class="tag"><?= e($p['product_type']) ?></span>
                        </div>
                        <h3 class="product-title"><?= e($p['title']) ?></h3>
                        <span class="product-creator">by <?= e($p['creator_name']) ?></span>
                        <div class="product-price <?= $p['is_free'] ? 'free' : '' ?>">
                            <?= $p['is_free'] ? 'Free' : formatMoney($p['price']) ?>
                        </div>
                        <a href="<?= SITE_URL ?>product.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">View details</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="card card-pad center" style="padding:2.5rem 1.5rem;">
        <h2 style="font-size:1.5rem;">Got something to share?</h2>
        <p class="muted" style="max-width:560px;margin:.6rem auto 1.2rem;">
            Join <?= (int)$stats['users'] ?> creator<?= $stats['users'] === 1 ? '' : 's' ?> already on DigiHub and publish your first digital product in minutes.
        </p>
        <div class="btn-group" style="justify-content:center;">
            <a href="<?= SITE_URL ?>register.php" class="btn btn-primary">Create a free account</a>
            <a href="<?= SITE_URL ?>login.php" class="btn btn-outline">I already have one</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
