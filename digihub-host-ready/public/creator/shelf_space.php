<?php
// DigiHub - Simulated shelf space purchase
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireCreator();

if (!canBuyShelfSpace($_SESSION['user_id'])) {
    setFlash('error', 'Upload at least 5 books before purchasing shelf space.');
    redirect(SITE_URL . 'creator/dashboard.php');
}

$pageTitle = 'Shelf Space';
$flash = getFlash();
$errors = [];
$books = array_values(array_filter(getCreatorProducts($_SESSION['user_id']), fn($p) => $p['product_type'] === 'book'));
$spaces = getCreatorShelfSpaces($_SESSION['user_id']);
$pricePerSpace = 1000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $productId = (int)($_POST['product_id'] ?? 0);
        $count = (int)($_POST['spaces_count'] ?? 0);
        $owned = array_column(array_filter($books, fn($b) => (int)$b['id'] === $productId), 'id');

        if (!$owned) $errors[] = 'Choose one of your books.';
        if ($count < 1 || $count > 10) $errors[] = 'Choose between 1 and 10 spaces.';

        if (empty($errors)) {
            global $pdo;
            $stmt = $pdo->prepare('INSERT INTO shelf_spaces (creator_id, product_id, spaces_count, price_paid) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $productId, $count, $count * $pricePerSpace]);
            setFlash('success', 'Shelf space purchased (simulated).');
            redirect(SITE_URL . 'creator/dashboard.php');
        }
    }
}

require __DIR__ . '/../partials/header.php';
?>

<div class="form-card card wide">
    <h2>Purchase shelf space</h2>
    <p class="subtitle">Permanent placement for your books. <strong><?= formatMoney($pricePerSpace) ?> per space</strong> (simulated).</p>

    <?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

    <form method="POST" action="" data-loading>
        <?= csrfField() ?>
        <div class="form-group">
            <label for="product_id">Book</label>
            <select id="product_id" name="product_id" required>
                <?php foreach ($books as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="spaces_count">Number of spaces (1–10)</label>
            <input id="spaces_count" type="number" name="spaces_count" min="1" max="10" value="1" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Purchase spaces</button>
            <a href="<?= SITE_URL ?>creator/dashboard.php" class="btn btn-outline">Back</a>
        </div>
    </form>
</div>

<?php if (!empty($spaces)): ?>
    <div class="card table-card" style="margin-top:1.5rem;">
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>Book</th><th>Spaces</th><th>Paid</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($spaces as $s): ?>
                    <tr><td><?= e($s['product_title']) ?></td><td><?= (int)$s['spaces_count'] ?></td><td><?= formatMoney($s['price_paid']) ?></td><td><?= date('M j, Y', strtotime($s['purchased_at'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
