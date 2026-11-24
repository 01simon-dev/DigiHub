<?php
// DigiHub - Checkout (simulated payment with server-side cross-check)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/receipt.php';

$productId = (int)($_GET['id'] ?? 0);
if (!$productId) {
    redirect(SITE_URL . 'index.php');
}

$product = getProduct($productId);
if (!$product || $product['status'] !== 'active' || $product['is_free']) {
    redirect(SITE_URL . 'product.php?id=' . $productId);
}

$pageTitle = 'Checkout';
$flash = getFlash();
$purchase = null;
$receiptUrl = '';
$receiptViewUrl = '';
$buyerName = '';
$buyerEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyerEmail = trim($_POST['buyer_email'] ?? '');
    $buyerName = trim($_POST['buyer_name'] ?? '');
    $expectedAmount = (float)($_POST['expected_amount'] ?? 0);
    $providerRef = strtoupper(trim($_POST['payment_reference'] ?? ''));

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh and try again.');
    } elseif (!$buyerEmail || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) || !$buyerName) {
        setFlash('error', 'Please provide your name and a valid email address.');
    } elseif (abs($expectedAmount - (float)$product['price']) > 0.01) {
        setFlash('error', 'Payment amount did not match the product price. Please refresh the page and try again.');
    } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{5,39}$/', $providerRef)) {
        setFlash('error', 'Enter a valid MTN transaction ID (6-40 letters, numbers or dashes).');
    } else {
        $result = createPurchase($productId, $buyerEmail, $buyerName, $product['price'], $providerRef);
        if ($result['success'] && verifyPurchase($result['purchase_id'], $productId, $buyerEmail, $product['price'])) {
            $payment = [
                'transaction_ref' => $result['transaction_ref'],
                'provider_reference' => $result['provider_reference'],
                'verified_at' => date('M j, Y H:i')
            ];
            $receiptPath = generateReceipt($result['purchase_id'], $product, $buyerEmail, $buyerName, $product['price'], $payment);
            global $pdo;
            $stmt = $pdo->prepare('UPDATE purchases SET receipt_path = ? WHERE id = ?');
            $stmt->execute([$receiptPath, $result['purchase_id']]);

            $purchase = $result;
            $receiptUrl = SITE_URL . 'download.php?file=' . urlencode($receiptPath);
            $receiptViewUrl = $receiptUrl . '&inline=1';
        } else {
            setFlash('error', $result['error'] ?? 'The payment could not be verified. Please try again.');
        }
    }
    $flash = getFlash();
}

require __DIR__ . '/partials/header.php';
?>

<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="form-card card">
    <h2>Checkout</h2>
    <p class="subtitle">Complete your purchase of <?= e($product['title']) ?>.</p>

    <?php if ($purchase): ?>
        <div class="receipt">
            <div class="receipt-head">
                <div>
                    <h2 style="font-size:1.2rem;">Payment complete</h2>
                    <p class="small muted">Payment cross-checked and verified. Receipt generated.</p>
                </div>
                <span class="badge badge-completed"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified</span>
            </div>
            <div class="receipt-row"><span>Reference</span><strong><?= e($purchase['transaction_ref']) ?></strong></div>
            <div class="receipt-row"><span>MTN transaction ID</span><strong><?= e($purchase['provider_reference']) ?></strong></div>
            <div class="receipt-row"><span>Product</span><strong><?= e($product['title']) ?></strong></div>
            <div class="receipt-row"><span>Buyer</span><strong><?= e($buyerName) ?> &lt;<?= e($buyerEmail) ?>&gt;</strong></div>
            <div class="receipt-row"><span>Method</span><strong>MTN Mobile Money (simulated)</strong></div>
            <div class="receipt-total"><span>Total</span><span><?= formatMoney($product['price']) ?></span></div>
            <div class="form-actions">
                <a class="btn btn-primary" data-loading href="<?= e($receiptViewUrl) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> View receipt (PDF)
                </a>
                <a class="btn btn-outline" href="<?= e($receiptUrl) ?>">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Download receipt
                </a>
                <a class="btn btn-outline" href="<?= SITE_URL ?>download.php?file=<?= urlencode($product['file_path']) ?>&product_id=<?= $productId ?>&buyer_email=<?= urlencode($buyerEmail) ?>">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Download product
                </a>
            </div>
            <p style="margin-top:1rem;"><a href="<?= SITE_URL ?>index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to browse</a></p>
        </div>
    <?php else: ?>
        <div class="detail-facts" style="margin-bottom:1.25rem;">
            <div><span class="muted">Product</span><strong><?= e($product['title']) ?></strong></div>
            <div><span class="muted">Amount</span><strong><?= formatMoney($product['price']) ?></strong></div>
        </div>

        <form method="POST" action="" data-loading>
            <?= csrfField() ?>
            <input type="hidden" name="expected_amount" value="<?= e($product['price']) ?>">
            <div class="form-group">
                <label for="buyer_name">Your name</label>
                <input id="buyer_name" type="text" name="buyer_name" required value="<?= e($_POST['buyer_name'] ?? '') ?>" placeholder="Your name">
            </div>
            <div class="form-group">
                <label for="buyer_email">Your email</label>
                <input id="buyer_email" type="email" name="buyer_email" required value="<?= e($_POST['buyer_email'] ?? '') ?>" placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label for="payment_reference">MTN transaction ID</label>
                <input id="payment_reference" type="text" name="payment_reference" required
                       minlength="6" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9-]{5,39}"
                       title="Use the reference shown by MTN Mobile Money, e.g. MTN-1234-ABCD"
                       value="<?= e($_POST['payment_reference'] ?? '') ?>" placeholder="MTN-1234-ABCD">
                <small>Enter the reference shown on your phone. We cross-check it and the amount before granting access.</small>
            </div>
            <div class="form-group">
                <label>Payment method</label>
                <input type="text" value="MTN Mobile Money (Simulated)" disabled>
                <small>Test mode — no real money is charged.</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Pay now &middot; <?= formatMoney($product['price']) ?></button>
                <a class="btn btn-outline" href="<?= SITE_URL ?>product.php?id=<?= $productId ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
