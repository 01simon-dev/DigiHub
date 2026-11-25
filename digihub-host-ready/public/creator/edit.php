<?php
// DigiHub - Edit an own product
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCreator();

$productId = (int)($_GET['id'] ?? 0);
$product = $productId ? getProduct($productId) : null;
if (!$product || (int)$product['creator_id'] !== (int)$_SESSION['user_id']) {
    setFlash('error', 'Product not found.');
    redirect(SITE_URL . 'creator/dashboard.php');
}

$pageTitle = 'Edit Product';
$flash = getFlash();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $status = $_POST['status'] ?? 'active';
        $isFree = isset($_POST['is_free']) ? 1 : 0;
        $price = $isFree ? 0.00 : (float)($_POST['price'] ?? 0);

        if (!$title) $errors[] = 'Please enter a title.';
        if (!in_array($category, ['ebook', 'comic', 'artwork', 'other'], true)) $errors[] = 'Invalid category.';
        if (!in_array($status, ['active', 'draft'], true)) $errors[] = 'Invalid status.';
        if (!$isFree && $price <= 0) $errors[] = 'Paid products need a price greater than 0.';

        if (empty($errors)) {
            global $pdo;
            $stmt = $pdo->prepare('UPDATE products SET title = ?, description = ?, category = ?, price = ?, is_free = ?, status = ? WHERE id = ? AND creator_id = ?');
            $stmt->execute([$title, $description, $category, $price, $isFree, $status, $productId, $_SESSION['user_id']]);
            setFlash('success', 'Product updated.');
            redirect(SITE_URL . 'creator/dashboard.php');
        }
        $product = array_merge($product, $_POST);
    }
}

require __DIR__ . '/../partials/header.php';
?>

<div class="form-card card wide">
    <h2>Edit product</h2>
    <p class="subtitle"><?= e($product['title']) ?></p>

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

    <form method="POST" action="" data-loading>
        <?= csrfField() ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input id="title" type="text" name="title" required value="<?= e($product['title']) ?>">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4"><?= e($product['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <?php foreach (['ebook' => 'E-book', 'comic' => 'Comic', 'artwork' => 'Artwork', 'other' => 'Other'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $product['category'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="checkbox-row" for="is_free">
                <input id="is_free" type="checkbox" name="is_free" value="1" <?= $product['is_free'] ? 'checked' : '' ?>> Free product
            </label>
        </div>
        <div class="form-group" id="price_group">
            <label for="price">Price (<?= MONEY_SYMBOL ?>)</label>
            <input id="price" type="number" name="price" min="0" step="0.01" value="<?= e($product['price']) ?>">
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
