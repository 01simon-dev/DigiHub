<?php
// DigiHub - Homepage
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Discover Digital Products';
$flash = getFlash();
$category = $_GET['category'] ?? null;
$categories = ['ebook' => 'E-books', 'comic' => 'Comics', 'artwork' => 'Artwork'];
$category = isset($categories[$category]) ? $category : null;
$products = getActiveProducts($category);

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="hero">
    <h1>Discover &amp; Support Local Creators</h1>
    <p>E-books, comics and digital artwork made by creators in Cameroon — simple to browse, easy to own.</p>
    <div class="btn-group" style="justify-content:center;">
        <?php if (!isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>register.php" class="btn btn-ghost"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Start selling as a creator</a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-ghost"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Go to your dashboard</a>
        <?php endif; ?>
        <a href="#products" class="btn btn-ghost"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Browse products</a>
    </div>
</section>

<section id="products" class="section">
    <div class="card filter-bar">
        <div class="filter-chips">
            <a href="<?= SITE_URL ?>index.php" class="chip <?= !$category ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $key => $label): ?>
                <a href="<?= SITE_URL ?>index.php?category=<?= $key ?>" class="chip <?= $category === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="search-box">
            <label class="sr-only" for="product-search">Search products</label>
            <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
            <input id="product-search" type="search" placeholder="Search products or creators..." autocomplete="off">
        </div>
    </div>

    <div class="section-head">
        <div>
            <h2><?= $category ? e($categories[$category]) : 'Featured products' ?></h2>
            <p><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?> available</p>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="card empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
            <h3>No products here yet</h3>
            <p class="muted">Try another category, or be the first to upload something.</p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $p): ?>
                <?php
                $cover = ($p['cover_path'] && file_exists(__DIR__ . '/../' . $p['cover_path']))
                    ? SITE_URL . 'download.php?file=' . urlencode($p['cover_path'])
                    : '';
                $searchText = $p['title'] . ' ' . $p['creator_name'] . ' ' . $p['category'] . ' ' . ($p['description'] ?? '');
                ?>
                <article class="card card-hover product-card" data-search="<?= e($searchText) ?>">
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
        <div class="card empty-state" id="no-results" hidden>
            <div class="empty-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></div>
            <h3>No matches found</h3>
            <p class="muted">Try a different search term.</p>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
