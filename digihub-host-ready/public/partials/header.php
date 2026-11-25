<?php
// Shared document header / navigation
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6f4e37">
    <meta name="description" content="The co-op workspace and storefront for writers, comic artists and digital creators.">
    <title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('digihub-theme');
                if (saved === 'dark' || saved === 'light') document.documentElement.dataset.theme = saved;
                document.documentElement.classList.add('show-splash');
            } catch (e) {
                document.documentElement.classList.add('show-splash');
            }
        })();
    </script>
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>public/assets/logo.png">
    <link rel="stylesheet" href="<?= SITE_URL ?>public/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>public/css/style.css">
    <script src="<?= SITE_URL ?>public/js/app.js" defer></script>
    <noscript><style>.site-splash{display:none!important}html.show-splash body{overflow:auto!important}</style></noscript>
</head>
<body>
<div id="site-splash" class="site-splash" aria-label="Loading digi-hub" aria-hidden="true">
    <div class="site-splash-inner">
        <img src="<?= SITE_URL ?>public/assets/logo.png" alt="" class="site-splash-logo" aria-hidden="true">
        <p class="site-splash-name">digi-hub</p>
        <div class="site-splash-spinner" aria-hidden="true"><span></span></div>
        <p class="site-splash-tagline">Create together. Sell together. Get paid together.</p>
    </div>
</div>
<header class="site-header">
    <div class="container nav-inner">
        <a href="<?= SITE_URL ?>" class="brand">
            <img src="<?= SITE_URL ?>public/assets/logo.png" alt="" class="brand-logo">
            <span><?= e(SITE_NAME) ?></span>
        </a>

        <div class="nav-actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode">
                <i class="fa-solid fa-moon" data-theme-icon></i>
            </button>
            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Open menu" aria-expanded="false" aria-controls="main-nav">
                <span></span><span></span><span></span>
            </button>
        </div>

        <nav class="nav-links" id="main-nav" aria-label="Main navigation">
            <a href="<?= SITE_URL ?>">Home</a>
            <a href="<?= SITE_URL ?>index.php">Browse library</a>
            <?php if (($_SESSION['user_role'] ?? '') !== 'admin'): ?>
                <a href="<?= SITE_URL ?>feedback.php">Feedback</a>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>creator/projects.php">Co-op projects</a>
            <?php if (isLoggedIn()): ?>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <a href="<?= SITE_URL ?>admin/dashboard.php">Dashboard</a>
                    <a href="<?= SITE_URL ?>support.php">Support</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>support.php">Support</a>
                    <a href="<?= SITE_URL ?>creator/dashboard.php">Go to dashboard</a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>login.php">Login</a>
                <a href="<?= SITE_URL ?>register.php" class="nav-cta">Join as Creator</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div id="toast-stack" class="toast-stack" aria-live="polite"></div>

<main class="site-main">
    <div class="container">
