<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
$userEmail = trim($_POST['user_email'] ?? '');

if (!$productId || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid data']);
    exit;
}

global $pdo;

// Check if already liked
$stmt = $pdo->prepare("SELECT id FROM likes WHERE product_id = ? AND user_email = ?");
$stmt->execute([$productId, $userEmail]);
$existing = $stmt->fetch();

if ($existing) {
    // Unlike
    $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
    $stmt->execute([$existing['id']]);
    $liked = false;
} else {
    // Like
    $stmt = $pdo->prepare("INSERT INTO likes (product_id, user_email) VALUES (?, ?)");
    $stmt->execute([$productId, $userEmail]);
    $liked = true;
}

// Get new count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE product_id = ?");
$stmt->execute([$productId]);
$count = (int) $stmt->fetchColumn();

echo json_encode(['success' => true, 'liked' => $liked, 'count' => $count]);