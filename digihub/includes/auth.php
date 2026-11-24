<?php
// DigiHub Authentication
// ======================

require_once __DIR__ . '/db.php';

session_start();

// CSRF token generation
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Register a new creator
function registerCreator($email, $password, $fullName, $phone = '') {
    global $pdo;

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email already registered.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, role) VALUES (?, ?, ?, ?, 'creator')");
    $stmt->execute([$email, $hash, $fullName, $phone]);

    return ['success' => true, 'user_id' => $pdo->lastInsertId()];
}

// Login
function loginUser($email, $password) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, email, password_hash, full_name, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    if ($user['status'] === 'suspended') {
        return ['success' => false, 'error' => 'Account suspended. Contact administrator.'];
    }

    // Regenerate session to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];

    return ['success' => true, 'role' => $user['role']];
}

// Logout
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Check if user is logged in
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . 'login.php');
        exit;
    }
}

// Require creator role
function requireCreator() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'creator') {
        header('Location: ' . SITE_URL . 'index.php');
        exit;
    }
}

// Require admin role
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . SITE_URL . 'index.php');
        exit;
    }
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}