<?php
require_once __DIR__ . '/coop.php';
// DigiHub General Functions
// =========================

require_once __DIR__ . '/db.php';

// Redirect helper
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Sanitize output
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Format currency
function formatMoney($amount) {
    return number_format($amount, 0, ',', ' ') . ' ' . MONEY_SYMBOL;
}

// Count creator's books
function countCreatorBooks($creatorId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE creator_id = ? AND product_type = 'book'");
    $stmt->execute([$creatorId]);
    return (int) $stmt->fetchColumn();
}

// Check if creator can buy shelf space (must have 5+ books)
function canBuyShelfSpace($creatorId) {
    return countCreatorBooks($creatorId) >= 5;
}

// Get product by ID
function getProduct($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, u.full_name AS creator_name, u.email AS creator_email 
        FROM products p 
        JOIN users u ON p.creator_id = u.id 
        WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get all active products
function getActiveProducts($category = null) {
    global $pdo;
    if ($category) {
        $stmt = $pdo->prepare("SELECT p.*, u.full_name AS creator_name 
            FROM products p 
            JOIN users u ON p.creator_id = u.id 
            WHERE p.status = 'active' AND p.category = ?
            ORDER BY p.created_at DESC");
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, u.full_name AS creator_name 
            FROM products p 
            JOIN users u ON p.creator_id = u.id 
            WHERE p.status = 'active' 
            ORDER BY p.created_at DESC");
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

// Get products by creator
function getCreatorProducts($creatorId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE creator_id = ? ORDER BY created_at DESC");
    $stmt->execute([$creatorId]);
    return $stmt->fetchAll();
}

// Check if user liked a product
function isLiked($productId, $email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE product_id = ? AND user_email = ?");
    $stmt->execute([$productId, $email]);
    return (bool) $stmt->fetch();
}

// Get like count
function getLikeCount($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE product_id = ?");
    $stmt->execute([$productId]);
    return (int) $stmt->fetchColumn();
}

// Get comments for product
function getComments($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE product_id = ? ORDER BY created_at DESC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

// Get reports (admin)
function getReports($status = null) {
    global $pdo;
    $sql = "SELECT r.*, p.title AS product_title
        FROM reports r
        JOIN products p ON r.product_id = p.id";
    if ($status) {
        $sql .= " WHERE r.status = ?";
    }
    $sql .= " ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($status ? [$status] : []);
    return $stmt->fetchAll();
}

// Alias kept for existing callers
function getPendingReports() {
    return getReports('pending');
}

// All products with creator info (admin)
function getAllProducts() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, u.full_name AS creator_name
        FROM products p JOIN users u ON p.creator_id = u.id
        ORDER BY p.created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Set product status (admin)
function setProductStatus($productId, $status) {
    global $pdo;
    if (!in_array($status, ['active', 'draft', 'reported', 'suspended'], true)) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $productId]);
}

// Resolve or dismiss a report (admin)
function updateReportStatus($reportId, $status, $adminId, $notes = '') {
    global $pdo;
    if (!in_array($status, ['reviewed', 'resolved', 'dismissed'], true)) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    return $stmt->execute([$status, $notes, $adminId, $reportId]);
}

// Platform summary for the admin dashboard
function getAdminStats() {
    global $pdo;
    $stats = [];
    foreach (['products' => 'products', 'users' => 'users', 'purchases' => 'purchases'] as $key => $table) {
        $stats[$key] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    $stats['pending_reports'] = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
    $stats['open_support'] = (int)$pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'open'")->fetchColumn();
    $stats['new_feedback'] = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE status = 'new'")->fetchColumn();

    // Admin reporting covers purchased add-ons, not creator sales revenue.
    $stats['image_slots_purchased'] = (int)$pdo->query("SELECT COALESCE(SUM(slots), 0) FROM image_slot_purchases")->fetchColumn();
    $stats['image_slot_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM image_slot_purchases")->fetchColumn();
    $stats['shelf_spaces_purchased'] = (int)$pdo->query("SELECT COALESCE(SUM(spaces_count), 0) FROM shelf_spaces")->fetchColumn();
    $stats['shelf_space_orders'] = (int)$pdo->query("SELECT COUNT(*) FROM shelf_spaces")->fetchColumn();
    return $stats;
}

// Generate transaction reference
function generateTxRef() {
    return 'DH' . date('YmdHis') . substr(bin2hex(random_bytes(4)), 0, 8);
}

// Create a purchase record after cross-checking the payment details.
function createPurchase($productId, $buyerEmail, $buyerName, $amount, $providerReference = '') {
    global $pdo;

    $productStmt = $pdo->prepare("SELECT price, is_free, status FROM products WHERE id = ?");
    $productStmt->execute([(int)$productId]);
    $product = $productStmt->fetch();
    if (!$product || $product['status'] !== 'active' || (float)$product['is_free'] === 1.0) {
        return ['success' => false, 'error' => 'This product is not available for purchase.'];
    }
    if (abs((float)$amount - (float)$product['price']) > 0.01) {
        return ['success' => false, 'error' => 'The amount does not match the product price. Please refresh and try again.'];
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{5,39}$/', $providerReference)) {
        return ['success' => false, 'error' => 'A valid payment reference is required.'];
    }
    $providerReference = strtoupper(trim($providerReference));
    $ref = generateTxRef();

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO purchases
            (product_id, buyer_email, buyer_name, amount, transaction_ref, payment_status, access_granted)
            VALUES (?, ?, ?, ?, ?, 'completed', 1)");
        $stmt->execute([(int)$productId, $buyerEmail, $buyerName, $amount, $ref]);
        $purchaseId = (int)$pdo->lastInsertId();

        $audit = $pdo->prepare("INSERT INTO payment_transactions
            (purchase_id, provider_reference, provider_status, raw_response, verified_at)
            VALUES (?, ?, 'success', ?, NOW())");
        $raw = json_encode([
            'provider' => 'MTN Mobile Money (simulated)',
            'provider_reference' => $providerReference,
            'amount' => (float)$amount,
            'currency' => CURRENCY,
            'product_id' => (int)$productId,
            'buyer_email' => $buyerEmail,
            'checked' => true
        ]);
        $audit->execute([$purchaseId, $providerReference, $raw]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'error' => 'The payment could not be recorded. Please try again.'];
    }

    if (!verifyPurchase($purchaseId, (int)$productId, $buyerEmail, $amount)) {
        return ['success' => false, 'error' => 'Payment verification failed. Please contact support.'];
    }

    // Co-op split payouts: record each collaborator's share of this sale.
    recordSaleSplits($purchaseId);

    return [
        'success' => true,
        'purchase_id' => $purchaseId,
        'transaction_ref' => $ref,
        'provider_reference' => $providerReference,
    ];
}

// Cross-check a purchase before granting access or issuing a receipt.
function verifyPurchase($purchaseId, $productId = null, $buyerEmail = null, $amount = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, pr.price AS current_price, pr.is_free, pt.provider_status, pt.verified_at
        FROM purchases p
        JOIN products pr ON pr.id = p.product_id
        LEFT JOIN payment_transactions pt ON pt.purchase_id = p.id
        WHERE p.id = ?");
    $stmt->execute([(int)$purchaseId]);
    $purchase = $stmt->fetch();
    if (!$purchase) return false;
    if ($purchase['payment_status'] !== 'completed' || !(int)$purchase['access_granted']) return false;
    if ($productId !== null && (int)$purchase['product_id'] !== (int)$productId) return false;
    if ($buyerEmail !== null && strcasecmp($purchase['buyer_email'], $buyerEmail) !== 0) return false;
    if ($amount !== null && abs((float)$purchase['amount'] - (float)$amount) > 0.01) return false;
    if (abs((float)$purchase['amount'] - (float)$purchase['current_price']) > 0.01) return false;
    if ($purchase['provider_status'] !== 'success' || empty($purchase['verified_at'])) return false;
    return true;
}

// Check purchase access (paid products require a verified, cross-checked payment)
function checkPurchaseAccess($productId, $buyerEmail) {
    global $pdo;
    // Free products are accessible to everyone
    $stmt = $pdo->prepare("SELECT is_free FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if ($product && $product['is_free']) {
        return true;
    }

    // Check for a verified purchase belonging to this buyer
    $stmt = $pdo->prepare("SELECT id FROM purchases
        WHERE product_id = ? AND buyer_email = ? AND payment_status = 'completed' AND access_granted = 1");
    $stmt->execute([$productId, $buyerEmail]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $purchaseId) {
        if (verifyPurchase((int)$purchaseId, (int)$productId, $buyerEmail)) {
            return true;
        }
    }
    return false;
}

// Get purchases by email
function getPurchasesByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.*, pr.title AS product_title, pr.product_type 
        FROM purchases p 
        JOIN products pr ON p.product_id = pr.id 
        WHERE p.buyer_email = ? 
        ORDER BY p.created_at DESC");
    $stmt->execute([$email]);
    return $stmt->fetchAll();
}

// Get shelf spaces for creator
function getCreatorShelfSpaces($creatorId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT ss.*, p.title AS product_title 
        FROM shelf_spaces ss 
        JOIN products p ON ss.product_id = p.id 
        WHERE ss.creator_id = ? AND ss.is_active = 1 
        ORDER BY ss.purchased_at DESC");
    $stmt->execute([$creatorId]);
    return $stmt->fetchAll();
}

// Purchased add-ons for the admin dashboard (creator revenue is not reported here).
function getAllImageSlotPurchases() {
    global $pdo;
    return $pdo->query("SELECT ip.*, u.full_name AS creator_name, u.email AS creator_email
        FROM image_slot_purchases ip JOIN users u ON ip.creator_id = u.id
        ORDER BY ip.created_at DESC")->fetchAll();
}

function getAllShelfSpaces() {
    global $pdo;
    return $pdo->query("SELECT ss.*, u.full_name AS creator_name, u.email AS creator_email, p.title AS product_title
        FROM shelf_spaces ss
        JOIN users u ON ss.creator_id = u.id
        JOIN products p ON ss.product_id = p.id
        ORDER BY ss.purchased_at DESC")->fetchAll();
}

/* ------------------------------------------------------------------
   Phone numbers (Cameroon: +237 6XX XXX XXX)
   ------------------------------------------------------------------ */
function isValidCameroonPhone($phone) {
    return (bool) preg_match('/^\+237 6[0-9]{2} [0-9]{3} [0-9]{3}$/', trim((string)$phone));
}

function formatCameroonPhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) === 9 && $digits[0] === '6') {
        $digits = '237' . $digits;
    }
    if (strlen($digits) === 12 && substr($digits, 0, 3) === '237') {
        return '+237 ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 3);
    }
    return trim((string)$phone);
}

function normalizeCameroonPhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) === 9 && $digits[0] === '6') {
        $digits = '237' . $digits;
    }
    if (strlen($digits) === 12 && substr($digits, 0, 3) === '237') {
        return '+237 ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 3);
    }
    return trim((string)$phone);
}

/* ------------------------------------------------------------------
   Image upload slots
   ------------------------------------------------------------------ */
function getImageSlotStats($creatorId) {
    global $pdo;
    $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE creator_id = ? AND product_type = 'image'");
    $usedStmt->execute([$creatorId]);
    $used = (int)$usedStmt->fetchColumn();

    $boughtStmt = $pdo->prepare("SELECT COALESCE(SUM(slots), 0) FROM image_slot_purchases WHERE creator_id = ?");
    $boughtStmt->execute([$creatorId]);
    $purchased = (int)$boughtStmt->fetchColumn();

    $total = FREE_IMAGE_SLOTS + $purchased;
    return [
        'free'      => FREE_IMAGE_SLOTS,
        'purchased' => $purchased,
        'used'      => $used,
        'total'     => $total,
        'available' => max(0, $total - $used),
    ];
}

function purchaseImageSlots($creatorId, $slots) {
    global $pdo;
    if (!isset(IMAGE_SLOT_PACKAGES[(int)$slots])) {
        return ['success' => false, 'error' => 'Invalid slot package.'];
    }
    // Extra slots unlock only after the creator has uploaded all 7 free images.
    $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE creator_id = ? AND product_type = 'image'");
    $usedStmt->execute([(int)$creatorId]);
    if ((int)$usedStmt->fetchColumn() < FREE_IMAGE_SLOTS) {
        return ['success' => false, 'error' => 'Upload ' . FREE_IMAGE_SLOTS . ' digital images before purchasing extra slots.'];
    }
    $slots = (int)$slots;
    $price = (float)IMAGE_SLOT_PACKAGES[$slots];
    $stmt = $pdo->prepare("INSERT INTO image_slot_purchases (creator_id, slots, price_paid) VALUES (?, ?, ?)");
    $stmt->execute([$creatorId, $slots, $price]);
    return ['success' => true, 'slots' => $slots, 'price' => $price];
}

/* ------------------------------------------------------------------
   Support, feedback and account administration
   ------------------------------------------------------------------ */
function getSupportMessages($status = null) {
    global $pdo;
    $sql = "SELECT s.*, u.full_name AS user_name, u.email AS user_email
        FROM support_messages s JOIN users u ON s.user_id = u.id";
    if ($status) $sql .= " WHERE s.status = ?";
    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($status ? [$status] : []);
    return $stmt->fetchAll();
}

function getFeedback($status = null) {
    global $pdo;
    $sql = "SELECT * FROM feedback";
    if ($status) $sql .= " WHERE status = ?";
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($status ? [$status] : []);
    return $stmt->fetchAll();
}

function getSupportMessagesForUser($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getAllUsers() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$userId]);
    return $stmt->fetch();
}

function addSupportMessage($userId, $category, $subject, $message) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO support_messages (user_id, category, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $category, $subject, $message]);
    return (int)$pdo->lastInsertId();
}

function addFeedback($name, $email, $rating, $message) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO feedback (author_name, author_email, rating, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $rating, $message]);
    return (int)$pdo->lastInsertId();
}

function setSupportStatus($id, $status, $notes = '') {
    global $pdo;
    if (!in_array($status, ['open', 'replied', 'resolved'], true)) return false;
    if ($notes !== '') {
        $stmt = $pdo->prepare("UPDATE support_messages SET status = ?, admin_notes = ? WHERE id = ?");
        return $stmt->execute([$status, $notes, $id]);
    }
    // Status-only changes must not erase an existing conversation.
    $stmt = $pdo->prepare("UPDATE support_messages SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function setFeedbackStatus($id, $status, $notes = '') {
    global $pdo;
    if (!in_array($status, ['new', 'reviewed', 'archived'], true)) return false;
    if ($notes !== '') {
        $stmt = $pdo->prepare("UPDATE feedback SET status = ?, admin_notes = ? WHERE id = ?");
        return $stmt->execute([$status, $notes, $id]);
    }
    $stmt = $pdo->prepare("UPDATE feedback SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

// Append a timestamped admin reply so a thread keeps its full history.
function appendAdminNote($existing, $note, $adminName) {
    $entry = '[' . date('M j, Y H:i') . ' - ' . $adminName . '] ' . trim($note);
    return trim((string)$existing) === '' ? $entry : trim($existing) . "\n\n" . $entry;
}

function replyToSupportMessage($id, $note, $status = 'replied', $adminName = 'Admin') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT admin_notes FROM support_messages WHERE id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    return setSupportStatus($id, $status, appendAdminNote($row['admin_notes'], $note, $adminName));
}

function replyToFeedback($id, $note, $status = 'reviewed', $adminName = 'Admin') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT admin_notes FROM feedback WHERE id = ?");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    return setFeedbackStatus($id, $status, appendAdminNote($row['admin_notes'], $note, $adminName));
}

function getFeedbackForEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE LOWER(author_email) = LOWER(?) ORDER BY created_at DESC");
    $stmt->execute([$email]);
    return $stmt->fetchAll();
}

function setUserStatus($userId, $status) {
    global $pdo;
    if (!in_array($status, ['active', 'suspended'], true)) return false;
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    return $stmt->execute([$status, (int)$userId]);
}

/* ------------------------------------------------------------------
   Safe deletion helpers (files + database records)
   ------------------------------------------------------------------ */
function uploadAbsolutePath($relativePath) {
    $base = realpath(__DIR__ . '/../uploads');
    if (!$base || !$relativePath) return null;
    $path = realpath(__DIR__ . '/../' . $relativePath);
    if (!$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0) return null;
    return $path;
}

function safeUnlinkUpload($relativePath) {
    $path = uploadAbsolutePath($relativePath);
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

function safeRemoveUploadDir($relativePath) {
    $path = uploadAbsolutePath($relativePath);
    if (!$path || !is_dir($path)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

/** Delete a product, its files, previews, purchases and linked records. */
function deleteProduct($productId) {
    global $pdo;
    $productId = (int)$productId;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) return false;

    $files = array_filter([$product['file_path'], $product['cover_path']]);

    $receiptStmt = $pdo->prepare("SELECT receipt_path FROM purchases WHERE product_id = ?");
    $receiptStmt->execute([$productId]);
    foreach ($receiptStmt->fetchAll(PDO::FETCH_COLUMN) as $receipt) {
        if ($receipt) $files[] = $receipt;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM payment_transactions WHERE purchase_id IN (SELECT id FROM purchases WHERE product_id = ?)");
        $stmt->execute([$productId]);
        $stmt = $pdo->prepare("DELETE FROM purchases WHERE product_id = ?");
        $stmt->execute([$productId]);
        // Shelf space is tied to the product, so remove it with the listing.
        $stmt = $pdo->prepare("DELETE FROM shelf_spaces WHERE product_id = ?");
        $stmt->execute([$productId]);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }

    foreach (array_unique($files) as $file) safeUnlinkUpload($file);
    safeRemoveUploadDir('uploads/previews/' . $productId);
    return true;
}

/** Delete a creator/admin account and everything they uploaded. */
function deleteUser($userId) {
    global $pdo;
    $userId = (int)$userId;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) return false;

    $productStmt = $pdo->prepare("SELECT id FROM products WHERE creator_id = ?");
    $productStmt->execute([$userId]);
    foreach ($productStmt->fetchAll(PDO::FETCH_COLUMN) as $productId) {
        deleteProduct((int)$productId);
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE reports SET reviewed_by = NULL WHERE reviewed_by = ?");
        $stmt->execute([$userId]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
    return true;
}

/** Write one clean, readable placeholder page for short PDFs. */
function writePreviewPlaceholder($path, $pageNumber) {
    if (!function_exists('imagecreatetruecolor')) return;
    $font = '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf';
    $bold = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf';
    $img = imagecreatetruecolor(900, 1200);
    $paper = imagecolorallocate($img, 255, 252, 248);
    $ink = imagecolorallocate($img, 74, 46, 27);
    $soft = imagecolorallocate($img, 111, 78, 55);
    imagefill($img, 0, 0, $paper);
    imagerectangle($img, 36, 36, 863, 1163, $soft);
    if (is_file($bold)) {
        imagettftext($img, 34, 0, 90, 150, $ink, $bold, 'DigiHub preview');
        imagettftext($img, 26, 0, 90, 230, $soft, $bold, 'Page ' . $pageNumber . ' of ' . FREE_PREVIEW_PAGES);
    }
    if (is_file($font)) {
        imagettftext($img, 22, 0, 90, 330, $ink, $font, 'This page was not in the uploaded PDF.');
        imagettftext($img, 22, 0, 90, 375, $ink, $font, 'The full document is still available to download.');
    }
    imagejpeg($img, $path, 85);
    imagedestroy($img);
}

/** Make sure the reader always has five scrollable preview pages. */
function padPreviewPages($previewDir) {
    for ($i = 1; $i <= FREE_PREVIEW_PAGES; $i++) {
        $path = $previewDir . 'page_' . $i . '.jpg';
        if (!is_file($path)) writePreviewPlaceholder($path, $i);
    }
}

/**
 * Render the first five pages of a PDF as real, readable JPG previews.
 * Uses pdftoppm (or Imagick when available) and pads short PDFs so the
 * reader always shows five scrollable pages.
 */
function generatePreviewPages($productId, $pdfPath) {
    $previewDir = __DIR__ . '/../uploads/previews/' . (int)$productId . '/';
    if (!is_dir($previewDir) && !mkdir($previewDir, 0775, true) && !is_dir($previewDir)) {
        return;
    }
    foreach (glob($previewDir . 'page_*.jpg') as $old) { @unlink($old); }
    foreach (glob($previewDir . 'page-*.jpg') as $old) { @unlink($old); }

    if (!is_file($pdfPath)) {
        padPreviewPages($previewDir);
        return;
    }

    // Imagick renders at high resolution when the extension is available.
    if (class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->setResolution(140, 140);
            $imagick->readImage($pdfPath . '[0-4]');
            foreach ($imagick as $i => $page) {
                if ($i >= FREE_PREVIEW_PAGES) break;
                $page->setImageFormat('jpg');
                $page->setImageCompressionQuality(86);
                $page->writeImage($previewDir . 'page_' . ($i + 1) . '.jpg');
            }
            padPreviewPages($previewDir);
            return;
        } catch (Throwable $e) {
            // Fall through to pdftoppm.
        }
    }

    // pdftoppm produces crisp page images on local XAMPP and some hosts.
    $pdftoppm = null;
    foreach (['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'] as $candidate) {
        if (is_executable($candidate)) { $pdftoppm = $candidate; break; }
    }
    if (!$pdftoppm && function_exists('shell_exec')) {
        $which = @shell_exec('command -v pdftoppm 2>/dev/null');
        if ($which) $pdftoppm = trim($which);
    }
    if ($pdftoppm && function_exists('shell_exec')) {
        $prefix = $previewDir . 'page';
        $isXampp = str_contains((string)(getenv('LD_LIBRARY_PATH') ?: ''), 'lampp');
        $cmdPrefix = $isXampp ? 'env -u LD_LIBRARY_PATH ' : '';
        $command = $cmdPrefix . escapeshellcmd($pdftoppm) . ' -f 1 -l ' . (int)FREE_PREVIEW_PAGES
            . ' -r 140 -jpeg -jpegopt quality=85 '
            . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix) . ' 2>/dev/null';
        @shell_exec($command);
        for ($i = 1; $i <= FREE_PREVIEW_PAGES; $i++) {
            $generated = $prefix . '-' . $i . '.jpg';
            if (is_file($generated)) {
                rename($generated, $previewDir . 'page_' . $i . '.jpg');
            }
        }
        if (glob($previewDir . 'page_*.jpg')) {
            foreach (glob($previewDir . 'page-*.jpg') as $leftover) { @unlink($leftover); }
            padPreviewPages($previewDir);
            return;
        }
    }

    // Last resort: a readable, well-sized placeholder (not a tiny icon).
    padPreviewPages($previewDir);
}