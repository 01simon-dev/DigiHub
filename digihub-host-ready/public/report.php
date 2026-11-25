<?php
// DigiHub - Submit a product report
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function reportResponse($data, $isAjax, $location) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    setFlash($data['success'] ? 'success' : 'error', $data['message'] ?? $data['error'] ?? 'Unable to submit report.');
    header('Location: ' . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$email = trim($_POST['reporter_email'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$location = SITE_URL . 'product.php?id=' . $productId;

if (!$productId || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($reason) < 5) {
    reportResponse(['success' => false, 'error' => 'Please provide a valid email and a short reason (at least 5 characters).'], $isAjax, $location);
}

$product = getProduct($productId);
if (!$product) {
    reportResponse(['success' => false, 'error' => 'Product not found.'], $isAjax, $location);
}

global $pdo;
$stmt = $pdo->prepare('INSERT INTO reports (product_id, reporter_email, reason) VALUES (?, ?, ?)');
$stmt->execute([$productId, $email, $reason]);

reportResponse(['success' => true, 'message' => 'Thank you. Your report has been sent to the admin team.'], $isAjax, $location);
