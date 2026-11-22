<?php
declare(strict_types=1);

define('APP_NAME', 'Krishi Vigyan Kendra Kokrajhar');
define('APP_SHORT_NAME', 'KVK Kokrajhar');
define('APP_URL', 'http://localhost/KVKKok');

// Google reCAPTCHA v3 is enabled only when both environment variables are set.
// Keep the secret outside the public website files (for Apache, set these in the
// virtual-host/server environment rather than committing production keys here).
define('RECAPTCHA_SITE_KEY', trim((string) (getenv('KVKKOK_RECAPTCHA_SITE_KEY') ?: '')));
define('RECAPTCHA_SECRET_KEY', trim((string) (getenv('KVKKOK_RECAPTCHA_SECRET_KEY') ?: '')));
define('RECAPTCHA_SCORE_THRESHOLD', 0.5);

// Login uses the visible reCAPTCHA v2 checkbox, which requires a separate v2
// key pair. Google-provided test keys are selected in login.php only on localhost.
define('RECAPTCHA_V2_SITE_KEY', trim((string) (getenv('KVKKOK_RECAPTCHA_V2_SITE_KEY') ?: '')));
define('RECAPTCHA_V2_SECRET_KEY', trim((string) (getenv('KVKKOK_RECAPTCHA_V2_SECRET_KEY') ?: '')));

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'kvk_kokrajhar');
define('DB_USER', 'kokrajhar');
define('DB_PASS', 'kjPjqNY98ChMU8jF');

define('ADMIN_EMAIL', 'adminkvkkokrajhar@gmail.com');
define('ADMIN_PASSWORD', 'admin123');

define('MAIL_FROM_EMAIL', 'adminkvkkokrajhar@gmail.com');
define('MAIL_FROM_NAME', 'KVK Kokrajhar');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'adminkvkkokrajhar@gmail.com');
define('SMTP_PASSWORD', 'loxxoygwsjpbghep');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads');

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    $configHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? '')) === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $configHttps ? '1' : '0');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $configHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
