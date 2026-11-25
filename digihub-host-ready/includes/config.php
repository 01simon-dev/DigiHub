<?php
// DigiHub Configuration
// =====================

// Guard against duplicate includes (e.g. via symlinked document roots).
if (defined('DIGIHUB_CONFIG_LOADED')) {
    return;
}
define('DIGIHUB_CONFIG_LOADED', true);

// ---- Deployment settings ----
// On InfinityFree, edit includes/hosting.php before uploading. Environment
// variables still take precedence on hosts that support them. On localhost the
// XAMPP defaults below are used automatically, even with placeholder values.
$hostingFile = __DIR__ . '/hosting.php';
$hosting = is_file($hostingFile) ? (require $hostingFile) : [];
if (!is_array($hosting)) {
    $hosting = [];
}

function digihub_is_placeholder($value, bool $allowEmpty = false) {
    if (!is_string($value)) {
        return true;
    }
    if (trim($value) === '') {
        return !$allowEmpty;
    }
    return preg_match('/XXX|YOUR_|CHANGE_|example\.com/i', $value) === 1;
}

function digihub_setting(array $hosting, string $envName, string $key, $default = null, bool $allowEmpty = false) {
    $env = getenv($envName);
    if ($env !== false && trim((string)$env) !== '') {
        return trim((string)$env);
    }
    $value = $hosting[$key] ?? null;
    if (!digihub_is_placeholder($value, $allowEmpty)) {
        return trim((string)$value);
    }
    return $default;
}

$rawHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$bareHost = trim(preg_replace('/:\d+$/', '', (string)$rawHost) ?: '', '[]');
$isLocal = in_array($bareHost, ['localhost', '127.0.0.1', '::1'], true)
    || str_contains((string)$rawHost, 'localhost')
    || str_ends_with($bareHost, '.local')
    || str_ends_with($bareHost, '.test');

// Local development always uses the bundled XAMPP database; hosting.php applies
// on the live host. Environment variables still take precedence on both.
if ($isLocal) {
    $hosting['db_host'] = 'localhost';
    $hosting['db_name'] = 'digihub';
    $hosting['db_user'] = 'root';
    $hosting['db_pass'] = '';
}

$dbHost = digihub_setting($hosting, 'DIGIHUB_DB_HOST', 'db_host', $isLocal ? 'localhost' : null);
$dbName = digihub_setting($hosting, 'DIGIHUB_DB_NAME', 'db_name', $isLocal ? 'digihub' : null);
$dbUser = digihub_setting($hosting, 'DIGIHUB_DB_USER', 'db_user', $isLocal ? 'root' : null);
$dbPass = digihub_setting($hosting, 'DIGIHUB_DB_PASS', 'db_pass', $isLocal ? '' : null, true);

if ($dbHost === null || $dbName === null || $dbUser === null || $dbPass === null) {
    http_response_code(500);
    die('DigiHub is not configured. Open includes/hosting.php, enter your InfinityFree MySQL details, then upload the updated file.');
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// ---- Site URL ----
// Auto-detected from the current request. Set site_url in includes/hosting.php
// if your host sits behind a proxy and links get the wrong scheme or path.
$envSiteUrl = getenv('DIGIHUB_SITE_URL');
$configuredSiteUrl = $hosting['site_url'] ?? null;
if ($envSiteUrl && trim((string)$envSiteUrl) !== '') {
    $siteUrl = rtrim(trim((string)$envSiteUrl), '/') . '/';
} elseif (!digihub_is_placeholder($configuredSiteUrl)) {
    $siteUrl = rtrim(trim((string)$configuredSiteUrl), '/') . '/';
} else {
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    // Derive the site base from the script's location inside the app folder.
    // Works for root pages, /public/*.php rewrites and /public/{admin,creator}/*.php.
    $appRoot = realpath(dirname(__DIR__));
    $scriptFile = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '';
    if ($appRoot && $scriptFile && str_starts_with($scriptFile, $appRoot . DIRECTORY_SEPARATOR)) {
        $relativeScript = str_replace('\\', '/', substr($scriptFile, strlen($appRoot)));
        if ($relativeScript !== '' && str_ends_with($scriptName, $relativeScript)) {
            $basePath = substr($scriptName, 0, -strlen($relativeScript));
        }
    }
    if ($basePath === '') {
        // Fallback for hosts that do not expose SCRIPT_FILENAME.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = preg_replace('#/(?:public(?:/(?:admin|creator))?|admin|creator)$#', '', rtrim($scriptDir, '/'));
    }
    $basePath = rtrim((string)$basePath, '/');
    if ($basePath === '.') {
        $basePath = '';
    }
    $siteUrl = $scheme . '://' . $host . ($basePath === '' ? '/' : $basePath . '/');
}

// Site settings
define('SITE_NAME', 'digi-hub');
define('PLATFORM_FEE_PERCENT', 5); // digi-hub commission on each sale
define('SITE_URL', $siteUrl);
define('CURRENCY', 'XAF');
define('MONEY_SYMBOL', 'FCFA');

// Upload settings. InfinityFree allows 20 MB per PHP upload; keep a 2 MB buffer.
define('UPLOAD_MAX_SIZE', 18 * 1024 * 1024);
define('UPLOAD_MAX_LABEL', '18 MB');
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
