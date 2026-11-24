<?php
// DigiHub - Creator dashboard
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCreator();

$pageTitle = 'Creator Dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'product_delete') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $product = getProduct($productId);
            if (!$product || (int)$product['creator_id'] !== (int)$_SESSION['user_id']) {
                setFlash('error', 'You can only delete your own products.');
            } else {
                $deleted = deleteProduct($productId);
                setFlash($deleted ? 'success' : 'error',
                    $deleted ? 'Product, files and any linked shelf space were deleted.' : 'Could not delete that product.');
            }
        } elseif ($action === 'buy_image_slots') {
            $slots = (int)($_POST['slots'] ?? 0);
            $result = purchaseImageSlots($_SESSION['user_id'], $slots);
            if ($result['success']) {
                setFlash('success', 'Purchased ' . $slots . ' image slots for ' . formatMoney($result['price']) . ' (simulated).');
            } else {
                setFlash('error', $result['error'] ?? 'Could not purchase image slots.');
            }
        }
    }
    redirect(SITE_URL . 'creator/dashboard.php');
}

$flash = getFlash();
$products = getCreatorProducts($_SESSION['user_id']);
$bookCount = countCreatorBooks($_SESSION['user_id']);
$imageCount = count(array_filter($products, fn($p) => $p['product_type'] === 'image'));
$slotStats = getImageSlotStats($_SESSION['user_id']);
$canPurchaseSlots = $slotStats['used'] >= FREE_IMAGE_SLOTS;
$canShelf = canBuyShelfSpace($_SESSION['user_id']);
$shelfSpaces = getCreatorShelfSpaces($_SESSION['user_id']);
$shelfSpaceCount = array_sum(array_column($shelfSpaces, 'spaces_count'));
$activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));

require __DIR__ . '/../partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="section-head">
    <div>
        <h2>Creator dashboard</h2>
        <p class="muted">Welcome back, <?= e($_SESSION['user_name']) ?>. Manage your books, artwork and slots here.</p>
    </div>
    <a href="<?= SITE_URL ?>creator/upload.php" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Upload product</a>
</div>

<div class="stat-grid">
    <div class="card stat"><div class="stat-label">Products</div><div class="stat-value"><?= count($products) ?></div></div>
    <div class="card stat"><div class="stat-label">Live listings</div><div class="stat-value"><?= $activeCount ?></div></div>
    <div class="card stat"><div class="stat-label">Books</div><div class="stat-value"><?= $bookCount ?></div></div>
    <div class="card stat"><div class="stat-label">Images</div><div class="stat-value"><?= $imageCount ?></div></div>
    <div class="card stat"><div class="stat-label">Shelf eligible</div><div class="stat-value"><?= $canShelf ? 'Yes' : 'No' ?></div></div>
</div>

<section class="section">
    <div class="section-head">
        <h3><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Your products</h3>
        <p class="muted">Books, comics and artwork you have uploaded.</p>
    </div>
    <?php if (empty($products)): ?>
        <div class="card empty-state">
            <div class="empty-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></div>
            <h3>Nothing uploaded yet</h3>
            <p class="muted">Your products will appear here once you publish them.</p>
            <p style="margin-top:1rem;"><a href="<?= SITE_URL ?>creator/upload.php" class="btn btn-primary">Upload your first product</a></p>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $p): ?>
                <?php
                $thumbPath = '';
                if (!empty($p['cover_path']) && file_exists(__DIR__ . '/../../' . $p['cover_path'])) {
                    $thumbPath = $p['cover_path'];
                } elseif ($p['product_type'] === 'image' && !empty($p['file_path']) && file_exists(__DIR__ . '/../../' . $p['file_path'])) {
                    $thumbPath = $p['file_path'];
                }
                ?>
                <article class="card card-hover product-card">
                    <div class="product-thumb">
                        <?php if ($thumbPath): ?>
                            <img src="<?= SITE_URL ?>download.php?file=<?= urlencode($thumbPath) ?>" alt="Cover of <?= e($p['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <?= strtoupper(substr(e($p['title']), 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <div class="tags">
                            <span class="tag tag-primary"><?= e($p['category']) ?></span>
                            <span class="tag"><?= e($p['product_type']) ?></span>
                            <span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                        </div>
                        <h3 class="product-title"><?= e($p['title']) ?></h3>
                        <div class="product-price <?= $p['is_free'] ? 'free' : '' ?>"><?= $p['is_free'] ? 'Free' : formatMoney($p['price']) ?></div>
                        <div class="btn-group">
                            <a href="<?= SITE_URL ?>product.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            <a href="<?= SITE_URL ?>creator/edit.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="product_delete">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Delete this product and all of its files? This cannot be undone.">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section" id="slots-and-shelf">
    <div class="card capacity-card">
        <div class="capacity-grid">
            <div class="capacity-col">
                <div class="capacity-head"><i class="fa-regular fa-image" aria-hidden="true"></i><span>Image slots left</span></div>
                <div class="capacity-value"><?= $slotStats['available'] ?></div>
                <div class="capacity-meta">
                    Used <?= $slotStats['used'] ?> of <?= $slotStats['total'] ?> · <?= FREE_IMAGE_SLOTS ?> free
                    <?= $slotStats['purchased'] ? ' + ' . $slotStats['purchased'] . ' purchased' : '' ?>
                </div>
            </div>
            <div class="capacity-col">
                <div class="capacity-head"><i class="fa-solid fa-book" aria-hidden="true"></i><span>Shelf space left</span></div>
                <div class="capacity-value"><?= $shelfSpaceCount ?></div>
                <div class="capacity-meta">
                    Purchased spaces on your bookshelf
                    <?= $canShelf ? '' : ' · unlocks after 5 books' ?>
                </div>
            </div>
        </div>

        <div class="capacity-actions">
            <div class="capacity-actions-grid">
                <div class="capacity-action">
                    <h4 class="capacity-title"><i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Extra image slots</h4>
                    <?php if ($canPurchaseSlots): ?>
                        <p class="small muted">You have uploaded <?= FREE_IMAGE_SLOTS ?> digital images. Choose a pack below.</p>
                        <div class="slot-grid">
                            <?php foreach (IMAGE_SLOT_PACKAGES as $slots => $price): ?>
                                <div class="slot-package">
                                    <div class="slot-count"><?= (int)$slots ?></div>
                                    <div class="slot-price">slots for <?= formatMoney($price) ?></div>
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="buy_image_slots">
                                        <input type="hidden" name="slots" value="<?= (int)$slots ?>">
                                        <button type="submit" class="btn btn-primary btn-sm btn-block" data-confirm="Purchase <?= (int)$slots ?> image slots for <?= formatMoney($price) ?> (simulated)?">
                                            <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Purchase
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="small muted" style="margin-top:.7rem;">Simulated payments. Deleting an image frees one slot.</p>
                    <?php else: ?>
                        <p class="small muted">
                            Unlocks after you upload <?= FREE_IMAGE_SLOTS ?> digital images.
                            Upload <?= FREE_IMAGE_SLOTS - $slotStats['used'] ?> more
                            <?= (FREE_IMAGE_SLOTS - $slotStats['used']) === 1 ? 'image' : 'images' ?>.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="capacity-action">
                    <h4 class="capacity-title"><i class="fa-solid fa-book" aria-hidden="true"></i> Bookshelf space</h4>
                    <?php if ($canShelf): ?>
                        <p class="small muted">You have uploaded <?= $bookCount ?> books and can buy permanent shelf space.</p>
                        <a href="<?= SITE_URL ?>creator/shelf_space.php" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> Purchase shelf space
                        </a>
                    <?php else: ?>
                        <p class="small muted">
                            Unlocks after 5 books. Upload <?= max(0, 5 - $bookCount) ?> more
                            <?= (5 - $bookCount) === 1 ? 'book' : 'books' ?>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($shelfSpaces)): ?>
                <div class="table-card capacity-table">
                    <div class="table-scroll">
                        <table class="table">
                            <thead><tr><th>Book</th><th>Spaces</th><th>Paid</th><th>Purchased</th></tr></thead>
                            <tbody>
                            <?php foreach ($shelfSpaces as $s): ?>
                                <tr>
                                    <td><?= e($s['product_title']) ?></td>
                                    <td><?= (int)$s['spaces_count'] ?></td>
                                    <td><?= formatMoney($s['price_paid']) ?></td>
                                    <td><?= date('M j, Y', strtotime($s['purchased_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
