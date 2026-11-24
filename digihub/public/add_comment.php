<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function commentResponse($data, $isAjax, $location) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    setFlash($data['success'] ? 'success' : 'error', $data['message'] ?? $data['error'] ?? 'Unable to post comment.');
    header('Location: ' . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$authorName = trim($_POST['author_name'] ?? '');
$authorEmail = trim($_POST['author_email'] ?? '');
$commentText = trim($_POST['comment_text'] ?? '');
$location = SITE_URL . 'product.php?id=' . $productId;

if (!$productId || !$authorName || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL) || !$commentText) {
    commentResponse(['success' => false, 'error' => 'Please fill in all fields with valid details.'], $isAjax, $location);
}

global $pdo;
$stmt = $pdo->prepare('INSERT INTO comments (product_id, author_email, author_name, comment_text) VALUES (?, ?, ?, ?)');
$stmt->execute([$productId, $authorEmail, $authorName, $commentText]);

commentResponse([
    'success' => true,
    'message' => 'Comment posted successfully.',
    'author_name' => $authorName,
    'created_at' => date('M j, Y'),
], $isAjax, $location);
