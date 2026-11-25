<?php
// DigiHub - Protected file delivery
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$file = $_GET['file'] ?? '';
$productId = $_GET['product_id'] ?? null;
$buyerEmail = trim($_GET['buyer_email'] ?? '');

if (!$file) {
    http_response_code(400);
    die('No file specified.');
}

$realUploads = realpath(__DIR__ . '/../uploads');
$realFile = realpath(__DIR__ . '/../' . $file);

if (!$realUploads || !$realFile || strpos($realFile, $realUploads . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    die('Access denied.');
}

if (!is_file($realFile)) {
    http_response_code(404);
    die('File not found.');
}

// Purchases are required for paid products; free products and assets are open.
if ($productId) {
    $product = getProduct((int)$productId);
    if (!$product) {
        http_response_code(404);
        die('Product not found.');
    }
    if (!$product['is_free'] && !checkPurchaseAccess((int)$productId, $buyerEmail)) {
        http_response_code(403);
        die('You must purchase this product to download it.');
    }
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $realFile) ?: $mime;
    finfo_close($finfo);
}

// Images always preview inline; PDF receipts can be opened inline with ?inline=1.
$inline = empty($_GET['download']) &&
    (strpos($mime, 'image/') === 0 || ($mime === 'application/pdf' && !empty($_GET['inline'])));
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($realFile) . '"');
header('Content-Length: ' . filesize($realFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($realFile);
exit;
