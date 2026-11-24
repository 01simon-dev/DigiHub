<?php
// DigiHub - Creator product upload
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCreator();

$pageTitle = 'Upload Product';
$flash = getFlash();
$errors = [];
$slotStats = getImageSlotStats($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'other';
        $productType = in_array($_POST['product_type'] ?? '', ['book', 'image'], true) ? $_POST['product_type'] : 'book';
        $isFree = isset($_POST['is_free']) ? 1 : 0;
        $price = $isFree ? 0.00 : (float)($_POST['price'] ?? 0);
        $pageCount = (int)($_POST['page_count'] ?? 0);
        $allowedCategories = ['ebook', 'comic', 'artwork', 'other'];

        if (!$title) $errors[] = 'Please enter a title.';
        if (!in_array($category, $allowedCategories, true)) $errors[] = 'Please choose a valid category.';
        if (!$isFree && $price <= 0) $errors[] = 'Price must be greater than 0 for paid products.';
        if ($productType === 'book' && ($pageCount < 1 || $pageCount > MAX_BOOK_PAGES)) {
            $errors[] = 'Page count must be between 1 and ' . MAX_BOOK_PAGES . ' for books.';
        }

        if ($productType === 'image') {
            $slotStats = getImageSlotStats($_SESSION['user_id']);
            if ($slotStats['available'] < 1) {
                $errors[] = 'You have no image slots left. Purchase more before uploading artwork.';
            }
        }

        $fileInfo = $_FILES['product_file'] ?? null;
        if (!$fileInfo || empty($fileInfo['tmp_name']) || $fileInfo['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please choose a product file.';
        } elseif ($fileInfo['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'The product file must be 50 MB or smaller.';
        } else {
            $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            $mime = function_exists('finfo_open')
                ? (new finfo(FILEINFO_MIME_TYPE))->file($fileInfo['tmp_name'])
                : ($fileInfo['type'] ?? '');

            if ($productType === 'book') {
                // Books and comics are strictly PDF - check extension, MIME and magic bytes.
                $header = file_get_contents($fileInfo['tmp_name'], false, null, 0, 5);
                if ($extension !== 'pdf' || $mime !== 'application/pdf' || strpos((string)$header, '%PDF-') !== 0) {
                    $errors[] = 'Books and comics must be real PDF files (.pdf).';
                }
            } elseif (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
                || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || !getimagesize($fileInfo['tmp_name'])) {
                $errors[] = 'Artwork must be a real JPG, PNG or WebP image.';
            }
        }

        $coverInfo = $_FILES['cover_file'] ?? null;
        if ($coverInfo && !empty($coverInfo['tmp_name']) && $coverInfo['error'] === UPLOAD_ERR_OK) {
            $coverExt = strtolower(pathinfo($coverInfo['name'], PATHINFO_EXTENSION));
            $coverMime = function_exists('finfo_open')
                ? (new finfo(FILEINFO_MIME_TYPE))->file($coverInfo['tmp_name'])
                : ($coverInfo['type'] ?? '');
            if (!in_array($coverExt, ['jpg', 'jpeg', 'png', 'webp'], true)
                || !in_array($coverMime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || !getimagesize($coverInfo['tmp_name'])) {
                $errors[] = 'Cover image must be a real JPG, PNG or WebP image.';
            } elseif ($coverInfo['size'] > UPLOAD_MAX_SIZE) {
                $errors[] = 'The cover image must be 50 MB or smaller.';
            }
        }

        if (empty($errors)) {
            $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            $fileName = uniqid('prod_', true) . '.' . $extension;
            $relativePath = ($productType === 'book' ? 'uploads/books/' : 'uploads/artwork/') . $fileName;
            $uploadDir = __DIR__ . '/../../' . $relativePath;
            if (!is_dir(dirname($uploadDir))) {
                mkdir(dirname($uploadDir), 0775, true);
            }

            if (!move_uploaded_file($fileInfo['tmp_name'], $uploadDir)) {
                $errors[] = 'Failed to save the product file. Please try again.';
            } else {
                $coverPath = '';
                if ($coverInfo && !empty($coverInfo['tmp_name']) && $coverInfo['error'] === UPLOAD_ERR_OK) {
                    $coverExt = strtolower(pathinfo($coverInfo['name'], PATHINFO_EXTENSION));
                    $coverName = uniqid('cover_', true) . '.' . $coverExt;
                    $coverPath = 'uploads/artwork/' . $coverName;
                    move_uploaded_file($coverInfo['tmp_name'], __DIR__ . '/../../' . $coverPath);
                }

                global $pdo;
                $stmt = $pdo->prepare('INSERT INTO products
                    (creator_id, title, description, category, product_type, price, is_free, file_path, cover_path, page_count, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")');
                $stmt->execute([
                    $_SESSION['user_id'], $title, $description, $category, $productType,
                    $price, $isFree, $relativePath, $coverPath, $pageCount,
                ]);

                $productId = (int)$pdo->lastInsertId();
                if ($productType === 'book') {
                    generatePreviewPages($productId, $uploadDir);
                }

                setFlash('success', 'Product uploaded successfully!');
                redirect(SITE_URL . 'creator/dashboard.php');
            }
        }
    }
}

require __DIR__ . '/../partials/header.php';
?>

<div class="form-card card wide">
    <h2>Upload a new product</h2>
    <p class="subtitle">Share a book, comic or piece of digital art with the community. Books must be PDF files.</p>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" data-loading>
        <?= csrfField() ?>

        <div class="form-group">
            <label for="product_type">Product type</label>
            <select id="product_type" name="product_type" required>
                <option value="book" <?= (($_POST['product_type'] ?? 'book') === 'book') ? 'selected' : '' ?>>Book / comic (PDF)</option>
                <option value="image" <?= (($_POST['product_type'] ?? '') === 'image') ? 'selected' : '' ?>>Digital artwork (image)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="title">Title</label>
            <input id="title" type="text" name="title" required value="<?= e($_POST['title'] ?? '') ?>" placeholder="Give your work a clear title">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Tell buyers what they will get."><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category" required>
                <option value="ebook" <?= (($_POST['category'] ?? '') === 'ebook') ? 'selected' : '' ?>>E-book</option>
                <option value="comic" <?= (($_POST['category'] ?? '') === 'comic') ? 'selected' : '' ?>>Comic</option>
                <option value="artwork" <?= (($_POST['category'] ?? '') === 'artwork') ? 'selected' : '' ?>>Artwork</option>
                <option value="other" <?= (($_POST['category'] ?? 'other') === 'other') ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <div class="form-group">
            <label class="checkbox-row" for="is_free">
                <input id="is_free" type="checkbox" name="is_free" value="1" <?= isset($_POST['is_free']) ? 'checked' : '' ?>>
                This product is free
            </label>
        </div>

        <div class="form-group" id="price_group">
            <label for="price">Price (<?= MONEY_SYMBOL ?>)</label>
            <input id="price" type="number" name="price" step="0.01" min="0" value="<?= e($_POST['price'] ?? '') ?>" placeholder="e.g. 1500">
        </div>

        <div class="form-group" id="page_group">
            <label for="page_count">Number of pages (max <?= MAX_BOOK_PAGES ?>)</label>
            <input id="page_count" type="number" name="page_count" min="1" max="<?= MAX_BOOK_PAGES ?>" value="<?= e($_POST['page_count'] ?? '') ?>" placeholder="e.g. 24">
        </div>

        <div class="form-group">
            <label for="product_file">Product file</label>
            <input id="product_file" type="file" name="product_file" required accept=".pdf">
            <small id="file-hint">PDF only for books and comics. JPG, PNG or WebP for artwork. Max 50 MB.</small>
            <small id="slot-hint" class="slot-hint">Image slots: <?= $slotStats['used'] ?> used of <?= $slotStats['total'] ?> · <?= $slotStats['available'] ?> available. <a href="<?= SITE_URL ?>creator/dashboard.php#image-slots">Manage slots</a></small>
        </div>

        <div class="form-group" id="cover_group">
            <label for="cover_file">Cover image <span class="muted">· optional</span></label>
            <input id="cover_file" type="file" name="cover_file" accept=".jpg,.jpeg,.png,.webp">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload product</button>
            <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
