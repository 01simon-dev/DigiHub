<?php
// DigiHub Configuration
// =====================

// Guard against duplicate includes (e.g. via symlinked document roots).
if (defined('DIGIHUB_CONFIG_LOADED')) {
    return;
}
define('DIGIHUB_CONFIG_LOADED', true);

// ---- Database settings ----
// Local development (localhost) uses the XAMPP defaults automatically.
// On a hosting account, values come from includes/hosting.php. Environment
// variables are still honoured if your host provides them another way, but
// InfinityFree free hosting does not support SetEnv.
$hostName = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$isLocal = in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)
    || strpos($hostName, 'localhost') !== false;

$hostingFile = __DIR__ . '/hosting.php';
$hosting = is_file($hostingFile) ? require $hostingFile : [];
if (!is_array($hosting)) {
    $hosting = [];
}

define('DB_HOST', getenv('DIGIHUB_DB_HOST') ?: ($isLocal ? 'localhost' : ($hosting['db_host'] ?? 'localhost')));
define('DB_NAME', getenv('DIGIHUB_DB_NAME') ?: ($isLocal ? 'digihub' : ($hosting['db_name'] ?? 'digihub')));
define('DB_USER', getenv('DIGIHUB_DB_USER') ?: ($isLocal ? 'root' : ($hosting['db_user'] ?? 'root')));
define('DB_PASS', getenv('DIGIHUB_DB_PASS') ?: ($isLocal ? '' : ($hosting['db_pass'] ?? '')));

// ---- Site URL ----
// Auto-detected from the current request. If links appear as http:// on an
// https site, set 'site_url' in includes/hosting.php. Environment variables
// are honoured if available, but InfinityFree free does not support SetEnv.
$envSiteUrl = getenv('DIGIHUB_SITE_URL') ?: ($hosting['site_url'] ?? '');
if ($envSiteUrl) {
    $siteUrl = rtrim($envSiteUrl, '/') . '/';
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    // Derive the site base from the script's location inside the app folder.
    // Works for root pages, /public/*.php rewrites and /public/{admin,creator}/*.php.
    $appRoot = realpath(dirname(__DIR__));
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '';
    if ($appRoot && $scriptFile && strpos($scriptFile, $appRoot . DIRECTORY_SEPARATOR) === 0) {
        $relativeScript = str_replace('\\', '/', substr($scriptFile, strlen($appRoot)));
        if ($relativeScript !== '' && substr($scriptName, -strlen($relativeScript)) === $relativeScript) {
            $basePath = substr($scriptName, 0, -strlen($relativeScript));
        }
    }
    if ($basePath === '') {
        // Fallback for hosts that do not expose SCRIPT_FILENAME.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = preg_replace('#/(?:public(?:/(?:admin|creator))?|admin|creator)$#', '', rtrim($scriptDir, '/'));
    }
    $basePath = rtrim($basePath, '/');
    $siteUrl = $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');
}

// Site settings
define('SITE_NAME', 'DigiHub');
define('SITE_URL', $siteUrl);
define('CURRENCY', 'XAF');
define('MONEY_SYMBOL', 'FCFA');

// Upload settings
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50 MB
define('MAX_BOOK_PAGES', 200);
define('FREE_PREVIEW_PAGES', 5);
define('FREE_IMAGE_SLOTS', 7);
define('IMAGE_SLOT_PACKAGES', [3 => 300, 7 => 900, 12 => 1000]);

// File paths
define('PATH_UPLOADS', __DIR__ . '/../uploads/');
define('PATH_BOOKS', PATH_UPLOADS . 'books/');
define('PATH_ARTWORK', PATH_UPLOADS . 'artwork/');
define('PATH_PREVIEWS', PATH_UPLOADS . 'previews/');
define('PATH_RECEIPTS', PATH_UPLOADS . 'receipts/');

// URL paths
define('URL_UPLOADS', SITE_URL . 'uploads/');
