    </div>
</main>

<footer class="site-footer">
    <div class="container">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Made by Si-Vibe Tech.</span>
        <span>
            <?php if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <a href="<?= SITE_URL ?>support.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Support</a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>feedback.php"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Feedback</a>
                <?php if (isLoggedIn()): ?>
                    &nbsp;·&nbsp; <a href="<?= SITE_URL ?>support.php"><i class="fa-solid fa-headset" aria-hidden="true"></i> Support</a>
                <?php endif; ?>
            <?php endif; ?>
            &nbsp;·&nbsp; Digital books, comics &amp; art from Cameroon.
        </span>
    </div>
</footer>
</body>
</html>
