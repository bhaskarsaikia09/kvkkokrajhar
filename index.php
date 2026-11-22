<?php

declare(strict_types=1);

/**
 * Security bootstrap for the public homepage.
 * Keep this block before application includes so session cookie flags and
 * response headers are applied before any output is sent.
 */
function home_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));

    return ($https !== '' && $https !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || $forwardedProto === 'https';
}

function home_request_url_parts(): array
{
    $scheme = home_is_https() ? 'https' : 'http';
    $hostHeader = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $parts = $hostHeader !== '' ? parse_url($scheme . '://' . $hostHeader) : false;

    return is_array($parts) ? $parts : [];
}

function home_public_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptName = preg_replace('#[^A-Za-z0-9/_\-.%]#', '', $scriptName) ?: '/index.php';
    $directory = trim(str_replace('\\', '/', dirname($scriptName)));

    if ($directory === '' || $directory === '.' || $directory === '/' || $directory === '\\') {
        return '/';
    }

    return '/' . trim($directory, '/') . '/';
}

function home_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    header("Content-Security-Policy: base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'");

    // This page contains a session-bound CSRF token and must never be stored by shared caches.
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (home_is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// Never expose filesystem paths, SQL details or stack traces from this public page.
// Keep diagnostics in the PHP error log instead.
ini_set('log_errors', '1');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Harden PHP session behavior before starting a session. Secure is enabled only on HTTPS,
// which preserves local WAMP/http development while protecting production HTTPS cookies.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', home_is_https() ? '1' : '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => home_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

home_send_security_headers();

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    if (!headers_sent()) {
        header('Allow: GET, HEAD, POST');
    }
    http_response_code(405);
    exit('Method Not Allowed');
}

// Keep the public homepage URL clean even when mod_rewrite is unavailable.
if (in_array($requestMethod, ['GET', 'HEAD'], true)) {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($requestPath) && strtolower(basename($requestPath)) === 'index.php') {
        header('Location: ' . home_public_base_path(), true, 301);
        exit;
    }
}

require_once __DIR__ . '/app/content.php';
$homepageConfigFile = __DIR__ . '/app/homepage.php';
if (is_file($homepageConfigFile)) {
    require_once $homepageConfigFile;
}

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

// session_start() may apply PHP's own cache limiter; restore the stricter homepage headers.
home_send_security_headers();

// Lightweight same-origin endpoint used by the advisory form's refresh button.
if ($requestMethod === 'GET' && isset($_GET['refresh_advisory_captcha'])) {
    if (!home_request_is_same_origin()) {
        http_response_code(403);
        exit;
    }

    home_create_captcha();
    $_SESSION['home_contact_recaptcha_fallback'] = true;
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    echo json_encode([
        'question' => (string) $_SESSION['home_contact_captcha_question'],
        'challenge' => (string) $_SESSION['home_contact_captcha_challenge'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$page_title = 'Krishi Vigyan Kendra Kokrajhar';
$notices = [];
$activities = [];
$viewerImages = [];
$contactStatus = null;
$homeProducts = [];
$farmersTrainedCount = 10468;
$trainingsOrganizedCount = 758;

function home_ensure_product_profile_columns(PDO $pdo): void
{
    $columns = [
        'tagline' => 'VARCHAR(255) NULL AFTER unit',
        'native_identity' => 'TEXT NULL AFTER tagline',
        'gi_status' => 'VARCHAR(120) NULL AFTER native_identity',
        'farmer_linkage' => 'TEXT NULL AFTER gi_status',
        'brand' => 'VARCHAR(220) NULL AFTER farmer_linkage',
        'catchment_reach' => 'TEXT NULL AFTER brand',
        'product_highlights' => 'TEXT NULL AFTER catchment_reach',
        'show_qr' => 'TINYINT(1) DEFAULT 0 AFTER product_note',
    ];

    $columnStatement = $pdo->query('SHOW COLUMNS FROM planting_material_commodities');
    $existingColumns = $columnStatement ? array_flip($columnStatement->fetchAll(PDO::FETCH_COLUMN)) : [];

    foreach ($columns as $column => $definition) {
        if (isset($existingColumns[$column])) {
            continue;
        }

        try {
            $pdo->exec("ALTER TABLE planting_material_commodities ADD COLUMN {$column} {$definition}");
        } catch (Throwable $exception) {
            // Existing installations may already have the product-profile column.
        }
    }
}

try {
    $notices = latest_notices(6) ?: [];
} catch (Throwable $exception) {
    error_log('Home page notices could not be loaded: ' . $exception->getMessage());
}

try {
    $activities = records('activities', 'activities', 'is_active = 1', 'title ASC', 3) ?: [];
} catch (Throwable $exception) {
    error_log('Home page activities could not be loaded: ' . $exception->getMessage());
}

try {
    $productDb = db();
    if ($productDb instanceof PDO) {
        home_ensure_product_profile_columns($productDb);
        $productSql = "SELECT c.id, c.commodity_name, c.rate, c.unit,
                c.tagline, c.native_identity, c.gi_status, c.farmer_linkage,
                c.brand, c.catchment_reach, c.product_highlights, c.show_qr,
                c.product_photo, c.product_photos, c.product_note,
                GREATEST(0,
                    COALESCE(SUM(r.quantity_produced), 0)
                    - COALESCE(SUM(r.quantity_sold), 0)
                    - COALESCE(SUM(r.quantity_distributed), 0)
                ) AS available_qty
            FROM planting_material_commodities c
            LEFT JOIN planting_material_records r
                ON r.commodity_id = c.id AND r.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.commodity_name, c.rate, c.unit,
                c.tagline, c.native_identity, c.gi_status, c.farmer_linkage,
                c.brand, c.catchment_reach, c.product_highlights, c.show_qr,
                c.product_photo, c.product_photos, c.product_note
            ORDER BY c.updated_at DESC, c.commodity_name ASC";
        $productStatement = $productDb->query($productSql);
        $homeProducts = $productStatement ? ($productStatement->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }
} catch (Throwable $exception) {
    error_log('Homepage products could not be loaded: ' . $exception->getMessage());
}

// Keep the homepage achievement current with active Training Desk records.
try {
    $trainingDb = db();
    if ($trainingDb instanceof PDO) {
        $trainingTotalStatement = $trainingDb->query(
            'SELECT COUNT(*) AS training_count,
                    COALESCE(SUM(total_participants), 0) AS participant_count
             FROM training_records
             WHERE is_active = 1'
        );
        $trainingTotals = $trainingTotalStatement ? $trainingTotalStatement->fetch(PDO::FETCH_ASSOC) : [];
        $farmersTrainedCount += max(0, (int) ($trainingTotals['participant_count'] ?? 0));
        $trainingsOrganizedCount += max(0, (int) ($trainingTotals['training_count'] ?? 0));
    }
} catch (Throwable $exception) {
    error_log('Homepage training total could not be loaded: ' . $exception->getMessage());
}

function home_plain_text(string $value, int $maxLength = 2000): string
{
    // Keep contact/config text as plain text. This also reduces stored-XSS risk if another
    // administrative page later displays these values incorrectly.
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim($value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function home_safe_image_url(string $url, string $fallback = ''): string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 1500) {
        return $fallback;
    }

    // Reject control characters, HTML-breaking quotes, backslashes and protocol-relative URLs.
    if (preg_match('/[\x00-\x20<>"\'`]/', $url) || str_contains($url, '\\') || str_starts_with($url, '//')) {
        return $fallback;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return $fallback;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
        return $fallback;
    }

    // User-info in media URLs is unnecessary and can obscure the real destination.
    if (isset($parts['user']) || isset($parts['pass'])) {
        return $fallback;
    }

    // Keep database/config-controlled images same-origin. This prevents silent third-party
    // tracking pixels and avoids mixed-content media on HTTPS pages.
    if ($scheme !== '') {
        $requestParts = home_request_url_parts();
        $urlHost = strtolower((string) ($parts['host'] ?? ''));
        $requestHost = strtolower((string) ($requestParts['host'] ?? ''));
        $urlPort = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $requestScheme = home_is_https() ? 'https' : 'http';
        $requestPort = (int) ($requestParts['port'] ?? ($requestScheme === 'https' ? 443 : 80));

        if ($urlHost === ''
            || $requestHost === ''
            || !hash_equals($requestHost, $urlHost)
            || $urlPort !== $requestPort
        ) {
            return $fallback;
        }
        if (home_is_https() && $scheme !== 'https') {
            return $fallback;
        }
    }

    return $url;
}

function home_safe_css_image_url(string $url, string $fallback): string
{
    $url = home_safe_image_url($url, '');
    if ($url === '') {
        return $fallback;
    }

    // CSS url(...) contexts need stronger restrictions than normal <img src> values.
    if (preg_match('/[(){};]/', $url) || str_contains($url, '\\')) {
        return $fallback;
    }

    return $url;
}

function home_safe_background_position(string $position): string
{
    $position = strtolower(trim($position));
    if ($position === '') {
        return 'center center';
    }

    // Allow standard keywords and percentages only; block arbitrary CSS tokens/functions.
    if (!preg_match('/^(?:left|center|right|(?:100|[0-9]{1,2})(?:\.\d+)?%)(?:\s+(?:top|center|bottom|(?:100|[0-9]{1,2})(?:\.\d+)?%))?$/', $position)) {
        return 'center center';
    }

    return $position;
}

function home_product_image(array $product, int $index): string
{
    $fallbacks = ['assets/img/campus-5.jpeg', 'assets/img/campus-7.jpg', 'assets/img/campus-10-web.jpg'];
    $fallback = $fallbacks[$index % count($fallbacks)];

    $primary = home_safe_image_url((string) ($product['product_photo'] ?? ''), '');
    if ($primary !== '') {
        return $primary;
    }

    $stored = json_decode((string) ($product['product_photos'] ?? ''), true);
    if (is_array($stored)) {
        foreach ($stored as $photo) {
            if (!is_scalar($photo)) {
                continue;
            }

            $safePhoto = home_safe_image_url((string) $photo, '');
            if ($safePhoto !== '') {
                return $safePhoto;
            }
        }
    }

    return $fallback;
}

function home_product_number(float $number): string
{
    return rtrim(rtrim(number_format(max(0, $number), 3, '.', ','), '0'), '.') ?: '0';
}

function home_product_detail_url(int $productId): string
{
    $parts = home_request_url_parts();
    $scheme = (string) ($parts['scheme'] ?? (home_is_https() ? 'https' : 'http'));
    $host = (string) ($parts['host'] ?? 'localhost');
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

    return $scheme . '://' . $host . $port . home_public_base_path()
        . 'planting-materials.php?product=' . max(0, $productId)
        . '#material-' . max(0, $productId);
}

function home_activity_is_training_record(string $details): bool
{
    $decoded = json_decode($details, true);

    return is_array($decoded) && ($decoded['type'] ?? '') === 'training_record';
}

function home_activity_is_training_slug(string $slug): bool
{
    return strtolower(trim($slug)) === 'training';
}

function home_image_title(string $filename): string
{
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $name)) ?? '';
    $name = trim($name);

    return $name === '' ? 'Gallery Image' : ucwords($name);
}

function home_update_label(int $index): string
{
    $labels = ['Latest', 'Training', 'Advisory', 'Notice', 'Event', 'Farmer Desk'];

    return $labels[$index % count($labels)];
}

function home_update_date(array $notice): string
{
    $date = trim((string) ($notice['date'] ?? $notice['created_at'] ?? ''));
    $timestamp = $date !== '' ? strtotime($date) : false;

    return $timestamp ? date('d M Y', $timestamp) : 'New update';
}

function home_post_value(string $key, int $maxLength = 2000): string
{
    $rawValue = $_POST[$key] ?? '';
    if (!is_scalar($rawValue)) {
        return '';
    }

    return home_plain_text((string) $rawValue, $maxLength);
}

function home_string_length(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function home_create_captcha(): void
{
    $challengeType = random_int(0, 2);

    if ($challengeType === 0) {
        $firstNumber = random_int(2, 9);
        $secondNumber = random_int(1, 9);
        $question = 'What is ' . $firstNumber . ' + ' . $secondNumber . '?';
        $answer = $firstNumber + $secondNumber;
    } elseif ($challengeType === 1) {
        $firstNumber = random_int(7, 15);
        $secondNumber = random_int(1, min(9, $firstNumber - 1));
        $question = 'What is ' . $firstNumber . ' - ' . $secondNumber . '?';
        $answer = $firstNumber - $secondNumber;
    } else {
        $start = random_int(1, 5);
        $step = random_int(2, 4);
        $secondNumber = $start + $step;
        $thirdNumber = $secondNumber + $step;
        $question = 'What comes next: ' . $start . ', ' . $secondNumber . ', ' . $thirdNumber . ', ?';
        $answer = $thirdNumber + $step;
    }

    $_SESSION['home_contact_captcha_question'] = $question;
    $_SESSION['home_contact_captcha_answer'] = (string) $answer;
    $_SESSION['home_contact_captcha_challenge'] = bin2hex(random_bytes(12));
    $_SESSION['home_contact_captcha_issued_at'] = time();
}

/**
 * Verify a short-lived reCAPTCHA v3 token on Google's server.
 *
 * @return array{status: 'passed'|'rejected'|'unavailable', score: float|null}
 */
function home_verify_recaptcha_v3(
    string $token,
    string $secretKey,
    string $expectedAction,
    float $minimumScore
): array {
    if ($token === ''
        || strlen($token) > 4096
        || !preg_match('/^[A-Za-z0-9._-]+$/D', $token)
    ) {
        return ['status' => 'rejected', 'score' => null];
    }

    $requestBody = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
    ], '', '&', PHP_QUERY_RFC3986);
    $responseBody = false;

    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $responseBody = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($httpStatus !== 200) {
                $responseBody = false;
            }
        }
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . 'Content-Length: ' . strlen($requestBody) . "\r\n",
                'content' => $requestBody,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $responseBody = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $context
        );
    }

    if (!is_string($responseBody) || $responseBody === '' || strlen($responseBody) > 16384) {
        return ['status' => 'unavailable', 'score' => null];
    }

    $result = json_decode($responseBody, true);
    if (!is_array($result)) {
        return ['status' => 'unavailable', 'score' => null];
    }

    $score = is_numeric($result['score'] ?? null) ? (float) $result['score'] : null;
    $responseAction = (string) ($result['action'] ?? '');
    $responseHost = strtolower(rtrim((string) ($result['hostname'] ?? ''), '.'));
    $requestHost = strtolower(rtrim((string) (home_request_url_parts()['host'] ?? ''), '.'));
    $timestamp = strtotime((string) ($result['challenge_ts'] ?? ''));
    $isFresh = $timestamp !== false && $timestamp >= (time() - 180) && $timestamp <= (time() + 30);
    $hostMatches = $requestHost !== ''
        && $responseHost !== ''
        && hash_equals($requestHost, $responseHost);

    if (($result['success'] ?? false) !== true
        || $score === null
        || $score < $minimumScore
        || !hash_equals($expectedAction, $responseAction)
        || !$hostMatches
        || !$isFresh
    ) {
        return ['status' => 'rejected', 'score' => $score];
    }

    return ['status' => 'passed', 'score' => $score];
}

function home_request_is_same_origin(): bool
{
    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite === 'cross-site') {
        return false;
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return true;
    }

    $originParts = parse_url($origin);
    $requestParts = home_request_url_parts();
    if (!is_array($originParts) || !$requestParts) {
        return false;
    }

    $originScheme = strtolower((string) ($originParts['scheme'] ?? ''));
    $requestScheme = home_is_https() ? 'https' : 'http';
    $originHost = strtolower((string) ($originParts['host'] ?? ''));
    $requestHost = strtolower((string) ($requestParts['host'] ?? ''));
    $originPort = (int) ($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));
    $requestPort = (int) ($requestParts['port'] ?? ($requestScheme === 'https' ? 443 : 80));

    return $originScheme === $requestScheme
        && $originHost !== ''
        && $requestHost !== ''
        && hash_equals($requestHost, $originHost)
        && $originPort === $requestPort;
}

function home_session_rate_limit_exceeded(int $limit = 6, int $windowSeconds = 600): bool
{
    $now = time();
    $attempts = $_SESSION['home_contact_attempts'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }

    $attempts = array_values(array_filter(
        $attempts,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $windowSeconds)
    ));

    if (count($attempts) >= $limit) {
        $_SESSION['home_contact_attempts'] = $attempts;
        return true;
    }

    $attempts[] = $now;
    $_SESSION['home_contact_attempts'] = $attempts;
    return false;
}

function home_ip_rate_limit_exceeded(int $limit = 60, int $windowSeconds = 3600): bool
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kvk_home_contact_rate';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        // Fail open if the temporary directory is unavailable; the session limiter still applies.
        return false;
    }

    $file = $directory . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $timestamps = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $now = time();
        $timestamps = array_values(array_filter(
            $timestamps,
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $windowSeconds)
        ));

        $exceeded = count($timestamps) >= $limit;
        if (!$exceeded) {
            $timestamps[] = $now;
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps, JSON_UNESCAPED_SLASHES) ?: '[]');
        fflush($handle);
        flock($handle, LOCK_UN);

        return $exceeded;
    } finally {
        fclose($handle);
    }
}

$viewerSources = [];
$viewerRoot = __DIR__ . '/assets/img';
$viewerRootReal = realpath($viewerRoot);

// Preserve the directory's real spelling and capitalization in generated URLs.
// This supports both "scroller" and the legacy "scoller" on Linux hosting.
if (is_dir($viewerRoot)) {
    foreach (new DirectoryIterator($viewerRoot) as $directory) {
        if ($directory->isDot() || !$directory->isDir()) {
            continue;
        }

        $directoryName = $directory->getFilename();
        if (!in_array(strtolower($directoryName), ['scroller', 'scoller'], true)) {
            continue;
        }

        $directoryReal = realpath($directory->getPathname());
        if ($viewerRootReal === false
            || $directoryReal === false
            || !str_starts_with($directoryReal . DIRECTORY_SEPARATOR, rtrim($viewerRootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            continue;
        }

        $viewerSources[] = [
            'dir' => $directoryReal,
            'url' => 'assets/img/' . rawurlencode($directoryName) . '/',
        ];
    }
}
$seenViewerFiles = [];

foreach ($viewerSources as $source) {
    if (!is_dir($source['dir'])) {
        continue;
    }

    $viewerFiles = glob(
        $source['dir'] . '/*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}',
        GLOB_BRACE
    ) ?: [];

    natsort($viewerFiles);

    foreach ($viewerFiles as $file) {
        if (!is_file($file)) {
            continue;
        }

        $realPath = realpath($file);
        $sourceReal = realpath($source['dir']);
        if ($realPath === false
            || $sourceReal === false
            || !str_starts_with($realPath, rtrim($sourceReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            continue;
        }

        if (isset($seenViewerFiles[$realPath])) {
            continue;
        }
        $seenViewerFiles[$realPath] = true;

        // The homepage feature gallery uses only originals that meet or exceed
        // Full HD, so enlarging a photograph never relies on a low-resolution file.
        $imageDimensions = @getimagesize($realPath);
        $imageWidth = is_array($imageDimensions) ? (int) ($imageDimensions[0] ?? 0) : 0;
        $imageHeight = is_array($imageDimensions) ? (int) ($imageDimensions[1] ?? 0) : 0;
        if ($imageWidth < 1920 || $imageHeight < 1080) {
            continue;
        }

        $filename = basename($file);
        $title = home_image_title($filename);
        if (preg_match('/^\d+(?:\s*\(\d+\))?$/', $title)) {
            $title = 'KVK Activity Photo ' . (count($viewerImages) + 1);
        }
        $viewerImages[] = [
            'img' => $source['url'] . rawurlencode($filename),
            'title' => $title,
            'desc' => $title . ' — KVK Kokrajhar gallery.',
            'alt' => $title . ' at KVK Kokrajhar',
            'width' => $imageWidth,
            'height' => $imageHeight,
        ];

        // Prevent an unexpectedly large gallery directory from exhausting memory or bloating the page.
        if (count($viewerImages) >= 80) {
            break 2;
        }
    }
}

if (!$viewerImages) {
    $viewerImages[] = [
        'img' => 'assets/img/slider-1.jpg',
        'title' => 'KVK Kokrajhar Campus',
        'desc' => 'A closer look at the KVK Kokrajhar campus and field facilities.',
        'alt' => 'KVK Kokrajhar campus view',
    ];
}

$expertiseOptions = [
    'Agronomy', 'Crop Management',
    'Horticulture', 'Vegetables',
    'Plant Protection', 'Pest Management',
    'Fishery Development',
    'Livestock & Animal Husbandry','Community Science',
];

$formData = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'subject' => '',
    'message' => '',
];

$recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? trim((string) RECAPTCHA_SITE_KEY) : '';
$recaptchaSecretKey = defined('RECAPTCHA_SECRET_KEY') ? trim((string) RECAPTCHA_SECRET_KEY) : '';
$recaptchaMinimumScore = defined('RECAPTCHA_SCORE_THRESHOLD')
    ? max(0.0, min(1.0, (float) RECAPTCHA_SCORE_THRESHOLD))
    : 0.5;
$recaptchaEnabled = preg_match('/^[A-Za-z0-9_-]{20,256}$/D', $recaptchaSiteKey) === 1
    && preg_match('/^[A-Za-z0-9_-]{20,256}$/D', $recaptchaSecretKey) === 1;
$captchaFallbackRequired = !$recaptchaEnabled
    || ($_SESSION['home_contact_recaptcha_fallback'] ?? false) === true;

// Create a strong, session-bound CSRF token with a finite lifetime.
$storedToken = $_SESSION['home_contact_token'] ?? '';
$tokenIssuedAt = (int) ($_SESSION['home_contact_token_issued_at'] ?? 0);
if (!is_string($storedToken)
    || !preg_match('/^[a-f0-9]{64}$/D', $storedToken)
    || $tokenIssuedAt <= 0
    || $tokenIssuedAt < (time() - 7200)
) {
    $_SESSION['home_contact_token'] = bin2hex(random_bytes(32));
    $_SESSION['home_contact_token_issued_at'] = time();
}

// Keep the human-verification challenge server-side and replace stale challenges.
$captchaQuestion = $_SESSION['home_contact_captcha_question'] ?? '';
$captchaAnswer = $_SESSION['home_contact_captcha_answer'] ?? '';
$captchaChallenge = $_SESSION['home_contact_captcha_challenge'] ?? '';
$captchaIssuedAt = (int) ($_SESSION['home_contact_captcha_issued_at'] ?? 0);
if (!is_string($captchaQuestion)
    || !preg_match('/^[A-Za-z0-9 ?,+:\-]{5,80}$/D', $captchaQuestion)
    || !is_string($captchaAnswer)
    || !preg_match('/^[0-9]{1,2}$/D', $captchaAnswer)
    || !is_string($captchaChallenge)
    || !preg_match('/^[a-f0-9]{24}$/D', $captchaChallenge)
    || $captchaIssuedAt <= 0
    || $captchaIssuedAt < (time() - 600)
) {
    home_create_captcha();
    $captchaQuestion = (string) $_SESSION['home_contact_captcha_question'];
    $captchaChallenge = (string) $_SESSION['home_contact_captcha_challenge'];
}

if (isset($_SESSION['home_contact_flash']) && is_array($_SESSION['home_contact_flash'])) {
    $contactStatus = $_SESSION['home_contact_flash'];
    unset($_SESSION['home_contact_flash']);
}

if ($requestMethod === 'POST') {
    $formData = [
        'name' => home_post_value('name', 100),
        'phone' => home_post_value('phone', 20),
        'email' => home_post_value('email', 150),
        'subject' => home_post_value('subject', 100),
        'message' => home_post_value('message', 2000),
    ];

    $errors = [];
    $contentLength = max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    $postedToken = home_post_value('_token', 128);
    $sessionToken = (string) ($_SESSION['home_contact_token'] ?? '');
    $honeypot = home_post_value('website', 200);
    $postedRecaptchaToken = home_post_value('g-recaptcha-response', 4096);
    $postedCaptcha = home_post_value('captcha_answer', 2);
    $postedCaptchaChallenge = home_post_value('captcha_challenge', 64);
    $sessionCaptcha = (string) ($_SESSION['home_contact_captcha_answer'] ?? '');
    $sessionCaptchaChallenge = (string) ($_SESSION['home_contact_captcha_challenge'] ?? '');
    $captchaIssuedAt = (int) ($_SESSION['home_contact_captcha_issued_at'] ?? 0);
    $captchaFallbackAuthorized = ($_SESSION['home_contact_recaptcha_fallback'] ?? false) === true;
    unset($_SESSION['home_contact_recaptcha_fallback']);

    // A challenge can be attempted only once, preventing replay of a known answer.
    unset(
        $_SESSION['home_contact_captcha_question'],
        $_SESSION['home_contact_captcha_answer'],
        $_SESSION['home_contact_captcha_challenge'],
        $_SESSION['home_contact_captcha_issued_at']
    );

    if ($contentLength > 16384) {
        $errors[] = 'The submitted request is too large.';
    }

    if ($contentType !== ''
        && !str_starts_with($contentType, 'application/x-www-form-urlencoded')
        && !str_starts_with($contentType, 'multipart/form-data')
    ) {
        $errors[] = 'The submission format is not supported.';
    }

    if (!home_request_is_same_origin()) {
        $errors[] = 'The submission could not be verified.';
    }

    if (home_session_rate_limit_exceeded() || home_ip_rate_limit_exceeded()) {
        $errors[] = 'Too many advisory requests have been submitted recently. Please try again later.';
    }

    if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $errors[] = 'Your form session has expired. Please refresh the page and try again.';
    }

    if ($honeypot !== '') {
        $errors[] = 'The submission could not be verified.';
    }

    $localCaptchaValid = $captchaIssuedAt > 0
        && $captchaIssuedAt >= (time() - 600)
        && $sessionCaptcha !== ''
        && $sessionCaptchaChallenge !== ''
        && preg_match('/^[0-9]{1,2}$/D', $postedCaptcha) === 1
        && preg_match('/^[a-f0-9]{24}$/D', $postedCaptchaChallenge) === 1
        && hash_equals($sessionCaptchaChallenge, $postedCaptchaChallenge)
        && hash_equals($sessionCaptcha, $postedCaptcha);

    $humanVerificationPassed = false;
    if (!$recaptchaEnabled) {
        $humanVerificationPassed = $localCaptchaValid;
    } elseif ($captchaFallbackAuthorized && $localCaptchaValid) {
        $humanVerificationPassed = true;
    } else {
        $recaptchaResult = home_verify_recaptcha_v3(
            $postedRecaptchaToken,
            $recaptchaSecretKey,
            'farmer_advisory',
            $recaptchaMinimumScore
        );
        $humanVerificationPassed = $recaptchaResult['status'] === 'passed';

        if (!$humanVerificationPassed) {
            error_log('Farmer advisory reCAPTCHA result: ' . $recaptchaResult['status']);
        }
    }

    if (!$humanVerificationPassed) {
        $_SESSION['home_contact_recaptcha_fallback'] = true;
        $captchaFallbackRequired = true;
        $errors[] = $recaptchaEnabled
            ? 'We could not confirm the automatic security check. Please complete the quick human-verification question and submit again.'
            : 'Please complete the human verification correctly.';
    }

    $nameLength = home_string_length($formData['name']);
    if ($nameLength < 2
        || $nameLength > 100
        || !preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’\-]{1,99}$/u', $formData['name'])
    ) {
        $errors[] = 'Please enter a valid full name.';
    }

    $phoneDigits = preg_replace('/\D+/', '', $formData['phone']) ?? '';
    if (!preg_match('/^\+?[0-9() .\-]{10,20}$/', $formData['phone'])
        || strlen($phoneDigits) < 10
        || strlen($phoneDigits) > 15
    ) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if ($formData['email'] !== '' && filter_var($formData['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!in_array($formData['subject'], $expertiseOptions, true)) {
        $errors[] = 'Please select the required area of expertise.';
    }

    $messageLength = home_string_length($formData['message']);
    if ($messageLength < 10 || $messageLength > 2000) {
        $errors[] = 'Please describe your farm concern in a little more detail.';
    }

    $submissionFingerprint = hash('sha256', implode("\n", [
        $formData['name'],
        $formData['phone'],
        strtolower($formData['email']),
        $formData['subject'],
        $formData['message'],
    ]));
    $lastFingerprint = (string) ($_SESSION['home_contact_last_fingerprint'] ?? '');
    $lastSubmittedAt = (int) ($_SESSION['home_contact_last_submitted_at'] ?? 0);
    if ($lastFingerprint !== ''
        && hash_equals($lastFingerprint, $submissionFingerprint)
        && $lastSubmittedAt > (time() - 300)
    ) {
        $errors[] = 'This advisory request was already submitted recently.';
    }

    if (!$errors) {
        try {
            $pdo = db();

            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Database connection is unavailable.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages (name, email, phone, subject, message)
                 VALUES (:name, :email, :phone, :subject, :message)'
            );
            $stmt->execute([
                ':name' => $formData['name'],
                ':email' => $formData['email'],
                ':phone' => $formData['phone'],
                ':subject' => $formData['subject'],
                ':message' => $formData['message'],
            ]);

            $_SESSION['home_contact_last_fingerprint'] = $submissionFingerprint;
            $_SESSION['home_contact_last_submitted_at'] = time();
            $_SESSION['home_contact_flash'] = [
                'type' => 'success',
                'message' => 'Thank you. Your advisory request has been submitted successfully.',
            ];

            // One-time token behavior after a successful state-changing request.
            $_SESSION['home_contact_token'] = bin2hex(random_bytes(32));
            $_SESSION['home_contact_token_issued_at'] = time();
            home_create_captcha();

            header('Location: ' . home_public_base_path() . '#appointment', true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Home page contact form failed: ' . $exception->getMessage());
            $errors[] = 'We could not save your request at this time. Please try again shortly.';
        }
    }

    if ($errors) {
        $contactStatus = [
            'type' => 'error',
            'message' => implode(' ', array_values(array_unique($errors))),
        ];

        if ($captchaFallbackAuthorized) {
            $_SESSION['home_contact_recaptcha_fallback'] = true;
            $captchaFallbackRequired = true;
        }
    }

    home_create_captcha();
    $captchaQuestion = (string) $_SESSION['home_contact_captcha_question'];
    $captchaChallenge = (string) $_SESSION['home_contact_captcha_challenge'];
}

$csrfToken = (string) $_SESSION['home_contact_token'];
$viewerImagesJson = json_encode(
    $viewerImages,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
) ?: '[]';

$defaultHeroSlides = [
    [
        'image' => 'assets/img/campus-1-web.jpg',
        'title' => 'Practical science for stronger fields and sustainable farming.',
        'description' => "Connecting Kokrajhar's farming community with hands-on technical training, field advisories, demonstrations, and enterprise support.",
        'position' => 'center center',
    ],
    [
        'image' => 'assets/img/campus-8-web.jpg',
        'title' => 'Locally relevant technologies for resilient farm livelihoods.',
        'description' => 'Evaluating promising crops, livestock options, and integrated farming practices under real field conditions.',
        'position' => 'center center',
    ],
    [
        'image' => 'assets/img/campus-10-web.jpg',
        'title' => 'Building skills among farmers, farm women, and rural youth.',
        'description' => 'Practical demonstrations and learning programmes that support confident adoption of improved agricultural practices.',
        'position' => 'center center',
    ],
];

$rawHeroSlides = $defaultHeroSlides;
if (function_exists('homepage_slides')) {
    try {
        $configuredSlides = homepage_slides();
        if (is_array($configuredSlides) && $configuredSlides) {
            $rawHeroSlides = $configuredSlides;
        }
    } catch (Throwable $exception) {
        error_log('Homepage slides could not be loaded: ' . $exception->getMessage());
    }
}

$heroSlides = [];
foreach (array_slice(array_values($rawHeroSlides), 0, 10) as $index => $slide) {
    if (!is_array($slide)) {
        continue;
    }

    $fallback = $defaultHeroSlides[$index % count($defaultHeroSlides)];
    $rawImage = is_scalar($slide['image'] ?? null) ? (string) $slide['image'] : '';
    $rawTitle = is_scalar($slide['title'] ?? null) ? (string) $slide['title'] : '';
    $rawDescription = is_scalar($slide['description'] ?? null) ? (string) $slide['description'] : '';
    $rawPosition = is_scalar($slide['position'] ?? null) ? (string) $slide['position'] : '';

    $title = home_plain_text($rawTitle, 180);
    $description = home_plain_text($rawDescription, 500);

    $heroSlides[] = [
        'image' => home_safe_css_image_url($rawImage, $fallback['image']),
        'title' => $title !== '' ? $title : $fallback['title'],
        'description' => $description !== '' ? $description : $fallback['description'],
        'position' => home_safe_background_position($rawPosition),
    ];
}

if (!$heroSlides) {
    $heroSlides = $defaultHeroSlides;
}

$heroSlidesJson = json_encode(
    $heroSlides,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
) ?: '[]';

// A single update should appear once; multiple updates use two groups for a smooth loop.
$noticeCount = count($notices);
$tickerCopyCount = $noticeCount > 1 ? 2 : $noticeCount;

require_once __DIR__ . '/includes/header.php';
?>

<style>

	.cdms-topbar {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
         
       

            color: #fff;
            background:
                linear-gradient(135deg, rgba(12, 63, 95, .98), rgba(8, 119, 79, .94) 55%, rgba(145, 42, 44, .86)),
                linear-gradient(90deg, rgba(245, 200, 75, .18), transparent 62%);
            box-shadow: 0 22px 54px rgba(20, 72, 96, .24);
        }










    .kvk-home {
        --home-primary: #176b3a;
        --home-primary-dark: #0d4a28;
        --home-secondary: #2f8a50;
        --home-accent: #f2b441;
        --home-accent-soft: #fff3d3;
        --home-ink: #14271d;
        --home-heading: #102a1c;
        --home-muted: #66756d;
        --home-surface: #ffffff;
        --home-soft: #f3f8f1;
        --home-border: #dce8da;
        --home-danger: #a5231d;
        --home-radius-sm: 12px;
        --home-radius: 20px;
        --home-radius-lg: 30px;
        --home-shadow-sm: 0 12px 30px rgba(20, 65, 40, 0.08);
        --home-shadow: 0 24px 60px rgba(20, 65, 40, 0.14);
        color: var(--home-ink);
        background: #fff;
        overflow: hidden;
    }

    .kvk-home *,
    .kvk-home *::before,
    .kvk-home *::after {
        box-sizing: border-box;
    }

    .kvk-home a {
        color: inherit;
    }

    .home-section {
        position: relative;
        padding: 88px 0;
    }

    #infrastructure,
    #services,
    #kvk-produce,
    #image-viewer,
    #appointment {
        content-visibility: auto;
        contain-intrinsic-size: auto 760px;
    }

    .home-section-soft {
        background:
            radial-gradient(circle at 12% 12%, rgba(47, 138, 80, 0.08), transparent 28%),
            linear-gradient(180deg, #f8fbf6 0%, #ffffff 100%);
    }

    .home-section-heading {
        max-width: 720px;
        margin: 0 auto 42px;
        text-align: center;
    }

    .home-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 12px;
        color: var(--home-primary);
        font-size: 0.78rem;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .home-eyebrow::before,
    .home-eyebrow::after {
        content: '';
        width: 22px;
        height: 2px;
        background: var(--home-accent);
        border-radius: 999px;
    }

    .home-section-heading h2 {
        margin: 0 0 14px;
        color: var(--home-heading);
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2rem, 4vw, 3.15rem);
        line-height: 1.12;
        text-wrap: balance;
    }

    .home-section-heading p {
        max-width: 650px;
        margin: 0 auto;
        color: var(--home-muted);
        font-size: 1.02rem;
        line-height: 1.75;
    }

    .home-ticker {
        position: relative;
        z-index: 5;
        border-bottom: 1px solid var(--home-border);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 8px 25px rgba(12, 50, 29, 0.06);
    }

    .home-ticker-inner {
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 52px;
    }

    .home-ticker-label {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 13px;
        border-radius: 999px;
        background: var(--home-danger);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 900;
        letter-spacing: 0.08em;
    }

    .home-ticker-window {
        min-width: 0;
        flex: 1;
        overflow: hidden;
        white-space: nowrap;
		
		
		
		
		
    }

    .home-ticker-track {
        display: flex;
        width: max-content;
        transform: translate3d(0, 0, 0);
        will-change: transform;
        animation: homeTicker 48s linear infinite;
    }

    .home-ticker-track--single {
        width: 100%;
        animation: none !important;
        will-change: auto;
    }

    .home-ticker-track--single .home-ticker-group {
        width: 100%;
        padding-right: 0;
    }

    .home-ticker-window:hover .home-ticker-track,
    .home-ticker-window:focus-within .home-ticker-track {
        animation-play-state: paused;
    }

    .home-ticker-group {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 36px;
        padding-right: 36px;
    }

    .home-ticker-item {
        display: inline-flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 9px;
        color: var(--home-ink);
        font-size: 0.92rem;
        font-weight: 750;
        text-decoration: none;
    }

    .home-ticker-item:hover {
        color: var(--home-primary);
    }

    .home-ticker-type {
        color: var(--home-primary);
        font-size: 0.7rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .home-ticker-date {
        color: var(--home-muted);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .home-ticker-empty {
        color: var(--home-muted);
        font-size: 0.9rem;
        font-weight: 700;
    }

    @keyframes homeTicker {
        from { transform: translate3d(0, 0, 0); }
        to { transform: translate3d(-50%, 0, 0); }
    }

    .home-hero {
        position: relative;
        min-height: 940px;
        display: flex;
        align-items: center;
        isolation: isolate;
        overflow: hidden;
        background: #0e3b24;
        color: #fff;
    }

    .home-hero-backdrop {
        position: absolute;
        inset: 0;
        z-index: -3;
        background-image: url('<?= e($heroSlides[0]['image']) ?>');
        background-position: <?= e($heroSlides[0]['position']) ?>;
        background-size: cover;
        background-repeat: no-repeat;
        transform: scale(1.02);
        transition: opacity 0.42s ease, transform 6s ease;
    }

    .home-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -2;
        background:
            radial-gradient(circle at 82% 22%, rgba(242, 180, 65, 0.32), transparent 28%),
            linear-gradient(100deg, rgba(5, 30, 17, 0.95) 0%, rgba(13, 74, 40, 0.84) 50%, rgba(14, 39, 31, 0.53) 100%);
			
    }

    .home-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        opacity: 0.32;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
        background-size: 78px 78px;
        mask-image: linear-gradient(90deg, #000 0%, rgba(0, 0, 0, 0.78) 55%, transparent 95%);
        pointer-events: none;
    }


    
    .home-hero-inner {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(290px, 0.75fr);
        align-items: center;
        gap: clamp(38px, 7vw, 90px);
        padding: 82px 0 150px;
    }

    .home-hero-copy {
        max-width: 840px;
    }

    .home-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 22px;
        padding: 9px 15px;
        border: 1px solid rgba(255, 255, 255, 0.34);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #ffe5aa;
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.15);
        font-size: 0.77rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        backdrop-filter: blur(12px);
    }

    .home-hero-badge::before {
        content: '';
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--home-accent);
        box-shadow: 0 0 0 6px rgba(242, 180, 65, 0.2);
    }

    .home-hero-title {
        max-width: 1000px;
        margin: 0 0 24px;
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2.0rem, 5.0vw, 5.0rem);
        line-height: 1.02;
        letter-spacing: -0.025em;
        text-wrap: balance;
        text-shadow: 0 18px 42px rgba(0, 0, 0, 0.34);
        transition: opacity 0.22s ease, transform 0.22s ease;
    }

    .home-hero-description {
        max-width: 1000px;
        margin: 0 0 34px;
        color: rgba(241, 250, 244, 0.93);
        font-size: 1.12rem;
        line-height: 1.75;
        text-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);
        transition: opacity 0.22s ease, transform 0.22s ease;
    }

    .home-hero-copy.is-changing .home-hero-title,
    .home-hero-copy.is-changing .home-hero-description {
        opacity: 0;
        transform: translateY(8px);
    }

    .home-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    

    .home-hero-panel {
        position: relative;
        padding: 28px;
        border: 1px solid rgba(255, 255, 255, 0.26);
        border-radius: var(--home-radius-lg);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.08));
        box-shadow: 0 28px 65px rgba(0, 0, 0, 0.24);
        backdrop-filter: blur(16px);
    }

    .home-hero-panel::before {
        content: '';
        position: absolute;
        width: 110px;
        height: 110px;
        right: -25px;
        top: -30px;
        border: 20px solid rgba(242, 180, 65, 0.2);
        border-radius: 50%;
    }

    .home-hero-panel-label {
        display: block;
        margin-bottom: 9px;
        color: #ffe0a0;
        font-size: 0.75rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .home-hero-panel h2 {
        position: relative;
        margin: 0 0 22px;
        font-size: 1.55rem;
        line-height: 1.25;
    }

    .home-hero-list {
        display: grid;
        gap: 15px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .home-hero-list li {
        display: grid;
        grid-template-columns: 38px 1fr;
        align-items: center;
        gap: 12px;
        color: rgba(246, 252, 248, 0.92);
        font-size: 0.92rem;
        font-weight: 700;
    }

    .home-hero-list i {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: rgba(255, 255, 255, 0.14);
        color: #ffdc8e;
    }

    .home-slide-controls {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-top: 30px;
    }

    .home-slide-dot {
        width: 10px;
        height: 10px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.42);
        cursor: pointer;
        transition: width 0.22s ease, background 0.22s ease;
    }

    .home-slide-dot.is-active {
        width: 34px;
        background: var(--home-accent);
    }

    .home-hero-edge {
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        z-index: 0;
        height: 190px;
        pointer-events: none;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(250, 255, 247, 0.82) 48%, #fff 88%);
    }

    

    .home-stats {
        position: relative;
        z-index: 4;
        margin-top: -108px;
        padding-bottom: 44px;
    }

    .home-stats::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 42px;
        width: min(980px, calc(100% - 72px));
        height: 56px;
        border-radius: 999px;
        background: radial-gradient(ellipse at center, rgba(29, 104, 58, 0.16), rgba(29, 104, 58, 0));
        filter: blur(18px);
        transform: translateX(-50%);
        pointer-events: none;
    }

    .home-stats-grid {
        position: relative;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .home-stat-card {
        position: relative;
        overflow: hidden;
        padding: 24px;
        border: 2px solid rgba(219, 232, 216, 0.94);
        border-radius: var(--home-radius);
        background:
            linear-gradient(180deg, #fff, #fbfef9),
            radial-gradient(circle at 100% 0%, rgba(242, 180, 65, 0.12), transparent 30%);
        box-shadow: 0 20px 46px rgba(15, 54, 31, 0.13);
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .home-stat-card::before {
        content: '';
        position: absolute;
        left: 24px;
        right: 24px;
        bottom: 0;
        height: 3px;
        border-radius: 999px 999px 0 0;
        
    }

    .home-stat-card::after {
        content: '';
        position: absolute;
        right: -28px;
        bottom: -34px;
        width: 95px;
        height: 95px;
        border-radius: 50%;
        background: rgba(470, 138, 80, 0.07);
    }

    .home-stat-card:hover {
        transform: translateY(-6px);
        border-color: rgba(154, 193, 124, 0.9);
        box-shadow: 0 30px 64px rgba(15, 54, 31, 0.18);
    }

    .home-stat-icon {
        width: 60px;
        height: 60px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        border-radius: 14px;
        background: linear-gradient(135deg, rgba(236, 249, 226, 1), rgba(255, 241, 197, 0.82));
        color: #236d3f;
        font-size: 1.75rem;
        box-shadow: inset 0 0 0 1px rgba(49, 126, 72, 0.08), 0 12px 24px rgba(28, 92, 52, 0.1);
    }

    .home-stat-value {
        margin: 0 0 5px;
        color: var(--home-heading);
        font-size: 2rem;
        font-weight: 900;
        line-height: 1;
    }

    .home-stat-label {
        margin: 0;
        color: var(--home-muted);
        font-size: 0.9rem;
        font-weight: 750;
    }

    .home-about-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.12fr) minmax(290px, 0.88fr);
        align-items: center;
        gap: clamp(38px, 7vw, 84px);
    }

    .home-about-copy h2 {
        margin: 0 0 20px;
        color: var(--home-heading);
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2rem, 4vw, 3.2rem);
        line-height: 1.15;
        text-wrap: balance;
    }

    .home-about-copy p {
        margin: 0 0 16px;
        color: var(--home-muted);
        font-size: 1rem;
        line-height: 1.82;
    }

    .home-text-link {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin-top: 8px;
        color: var(--home-primary);
        font-weight: 850;
        text-decoration: none;
    }

    .home-text-link:hover {
        color: var(--home-primary-dark);
        gap: 13px;
        text-decoration: none;
    }

    .home-about-photo {
        position: relative;
        isolation: isolate;
        min-height: clamp(420px, 46vw, 560px);
        margin: 0;
        overflow: hidden;
        border: 8px solid #ffffff;
        border-radius: var(--home-radius-lg);
        background: #dfeadf;
        box-shadow: var(--home-shadow);
    }

    .home-about-photo::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 1;
        pointer-events: none;
        background:
            linear-gradient(180deg, transparent 52%, rgba(7, 32, 18, 0.74) 100%),
            linear-gradient(135deg, rgba(242, 180, 65, 0.12), transparent 38%);
    }

    .home-about-photo::after {
        content: '';
        position: absolute;
        inset: 12px;
        z-index: 2;
        border: 1px solid rgba(255, 255, 255, 0.48);
        border-radius: calc(var(--home-radius-lg) - 10px);
        pointer-events: none;
    }

    .home-about-photo img {
        width: 100%;
        height: 100%;
        min-height: inherit;
        display: block;
        object-fit: cover;
        object-position: center center;
        transition: transform 0.7s ease;
    }

    .home-about-photo:hover img {
        transform: scale(1.035);
    }

    .home-about-photo figcaption {
        position: absolute;
        left: 28px;
        right: 28px;
        bottom: 24px;
        z-index: 3;
        color: #ffffff;
        font-weight: 850;
        letter-spacing: 0.01em;
        text-shadow: 0 3px 14px rgba(0, 0, 0, 0.55);
    }

    .home-about-photo figcaption span {
        display: block;
        margin-top: 5px;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.84rem;
        font-weight: 650;
    }

    .home-feature-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 20px;
    }

    .home-feature-card {
        position: relative;
        overflow: hidden;
        min-height: 220px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 27px;
        border: 1px solid var(--home-border);
        border-radius: var(--home-radius);
        background: #fff;
        box-shadow: var(--home-shadow-sm);
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .home-feature-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(145deg, transparent 45%, rgba(47, 138, 80, 0.07));
        pointer-events: none;
    }

    .home-feature-card:hover {
        transform: translateY(-7px);
        border-color: rgba(47, 138, 80, 0.35);
        box-shadow: var(--home-shadow);
    }

    .home-feature-icon {
        width: 58px;
        height: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 28px;
        border-radius: 17px;
        background: var(--home-soft);
        color: var(--home-primary);
        font-size: 1.55rem;
    }

    .home-feature-card h3 {
        position: relative;
        margin: 0 0 8px;
        color: var(--home-heading);
        font-size: 1.08rem;
        line-height: 1.35;
    }

    .home-feature-card h3 a {
        color: inherit;
        text-decoration: none;
    }

    .home-feature-card h3 a::after {
        content: '';
        position: absolute;
        inset: -120px -27px -90px;
    }

    .home-feature-card:has(h3 a):focus-within {
        outline: 3px solid var(--home-accent);
        outline-offset: 4px;
    }

    .home-feature-card p {
        position: relative;
        margin: 0;
        color: var(--home-muted);
        font-size: 0.88rem;
        line-height: 1.6;
    }

    .home-activity-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 24px;
    }

    .home-activity-card {
        position: relative;
        min-height: 270px;
        display: flex;
        flex-direction: column;
        padding: 30px;
        border: 1px solid var(--home-border);
        border-radius: var(--home-radius);
        background: #fff;
        box-shadow: var(--home-shadow-sm);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    

    .home-activity-card:hover {
        transform: translateY(-7px);
        box-shadow: var(--home-shadow);
    }

    .home-card-tag {
        display: inline-flex;
        align-self: flex-start;
        margin-bottom: 18px;
        padding: 7px 11px;
        border-radius: 999px;
        background: var(--home-soft);
        color: var(--home-primary);
        font-size: 0.7rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .home-activity-card h3 {
        margin: 0 0 13px;
        color: var(--home-heading);
        font-size: 1.25rem;
        line-height: 1.4;
    }

    .home-activity-card p {
        margin: 0 0 22px;
        color: var(--home-muted);
        font-size: 0.92rem;
        line-height: 1.72;
    }

    .home-card-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: auto;
        color: var(--home-primary);
        font-size: 0.9rem;
        font-weight: 900;
        text-decoration: none;
    }

    .home-card-link:hover {
        color: var(--home-primary-dark);
        gap: 12px;
        text-decoration: none;
    }

    .home-empty-card {
        grid-column: 1 / -1;
        padding: 32px;
        border: 1px dashed #b9ccb7;
        border-radius: var(--home-radius);
        background: #fff;
        color: var(--home-muted);
        text-align: center;
    }

    .home-produce-grid {
        display: flex;
        gap: 15px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
    }

    .home-produce-grid::-webkit-scrollbar { display: none; }

    .home-produce-grid > .home-produce-card {
        flex: 0 0 calc((100% - 30px) / 3);
        min-width: 0;
        scroll-snap-align: start;
    }

    .home-produce-section {
        padding-block: 58px;
    }

    .home-produce-section .home-section-heading {
        margin-bottom: 0;
    }

    .home-produce-heading-row {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 28px;
    }

    .home-produce-scroller-controls {
        display: flex;
        align-items: center;
        gap: 9px;
        flex: 0 0 auto;
    }

    .home-produce-scroller-controls span {
        margin-right: 3px;
        color: var(--home-muted);
        font-size: .74rem;
        font-weight: 800;
    }

    .home-produce-scroll-button {
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        padding: 0;
        border: 1px solid var(--home-border);
        border-radius: 50%;
        color: #fff;
        background: var(--home-primary);
        box-shadow: var(--home-shadow-sm);
        cursor: pointer;
        transition: transform .18s ease, background .18s ease;
    }

    .home-produce-scroll-button:hover,
    .home-produce-scroll-button:focus-visible {
        transform: translateY(-2px);
        background: var(--home-primary-dark);
    }

    @media (max-width: 680px) {
        .home-produce-heading-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 15px;
        }

        .home-produce-scroller-controls {
            width: 100%;
            justify-content: flex-end;
        }
    }

    .home-produce-card {
        overflow: hidden;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--home-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--home-shadow-sm);
        transition: transform .22s ease, box-shadow .22s ease;
    }

    .home-produce-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--home-shadow);
    }

    .home-produce-image {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        background: #e8f0e9;
    }

    .home-produce-image img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        transition: transform .35s ease;
    }

    .home-produce-card:hover .home-produce-image img { transform: scale(1.045); }

    .home-produce-stock {
        position: absolute;
        top: 9px;
        left: 9px;
        padding: 5px 8px;
        border-radius: 999px;
        background: rgba(12, 91, 50, .92);
        color: #fff;
        font-size: .64rem;
        font-weight: 900;
    }

    .home-produce-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 14px;
    }

    .home-produce-body h3 {
        margin: 0 0 8px;
        color: var(--home-heading);
        font-size: .98rem;
    }

    .home-produce-price {
        margin-bottom: 10px;
        color: var(--home-primary);
        font-size: 1.05rem;
        font-weight: 900;
    }

    .home-produce-price small { color: var(--home-muted); font-size: .75rem; }

    .home-produce-note {
        margin: 0 0 12px;
        color: var(--home-muted);
        font-size: .78rem;
        line-height: 1.48;
    }

    .home-produce-tagline {
        margin: -3px 0 9px;
        color: var(--home-primary-dark);
        font-size: .78rem;
        font-weight: 900;
        line-height: 1.4;
    }

    .home-produce-profile {
        display: grid;
        gap: 7px;
        margin: 0 0 14px;
        padding: 11px;
        border: 1px solid #dfeadf;
        border-radius: 11px;
        background: #f8fbf7;
    }

    .home-produce-profile div {
        display: grid;
        grid-template-columns: minmax(90px, .8fr) minmax(0, 1.2fr);
        gap: 8px;
        font-size: .72rem;
        line-height: 1.42;
    }

    .home-produce-profile dt {
        color: var(--home-primary-dark);
        font-weight: 900;
    }

    .home-produce-profile dd {
        margin: 0;
        color: var(--home-muted);
        overflow-wrap: anywhere;
    }

    .home-produce-qr {
        display: grid;
        grid-template-columns: 150px 1fr;
        align-items: center;
        gap: 12px;
        margin: 0 0 14px;
        padding: 10px;
        border: 1px dashed #b9d0bd;
        border-radius: 11px;
        background: #fff;
    }

    .home-produce-qr__code {
        width: 150px;
        height: 150px;
        padding: 7px;
        border-radius: 10px;
        background: #fff;
    }

    .home-produce-qr__code img,
    .home-produce-qr__code canvas,
    .home-produce-qr__code svg {
        display: block;
        width: 100% !important;
        height: 100% !important;
    }

    .home-produce-qr p {
        margin: 0;
        color: var(--home-muted);
        font-size: .7rem;
        line-height: 1.45;
    }

    .home-produce-qr strong {
        display: block;
        margin-bottom: 3px;
        color: var(--home-primary-dark);
        font-size: .76rem;
    }

    @media (max-width: 480px) {
        .home-produce-qr {
            grid-template-columns: 1fr;
            justify-items: center;
            text-align: center;
        }
    }

    .home-produce-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: auto;
        padding-top: 4px;
    }

    /* Keep View Details and Purchase as one consistent, equal-size action set. */
    .home-produce-actions .home-produce-action-button {
        width: 100%;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid var(--home-primary);
        border-radius: 12px;
        background: linear-gradient(135deg, var(--home-primary), var(--home-primary-dark));
        color: #fff;
        font-size: .78rem;
        font-weight: 850;
        line-height: 1.15;
        text-align: center;
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(13, 74, 40, .16);
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }

    .home-produce-actions .home-produce-action-button i {
        flex: 0 0 auto;
        font-size: .82rem;
    }

    .home-produce-actions .home-produce-action-button:hover,
    .home-produce-actions .home-produce-action-button:focus-visible {
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(13, 74, 40, .22);
        filter: brightness(1.05);
    }

    .home-produce-actions .home-produce-action-button:focus-visible {
        outline: 3px solid rgba(242, 180, 65, .42);
        outline-offset: 2px;
    }

    .home-button-outline {
        border-color: var(--home-border);
        background: #fff;
        color: var(--home-primary-dark);
    }

    @media (max-width: 420px) {
        .home-produce-actions {
            grid-template-columns: 1fr;
        }
    }

    .home-produce-footer {
        margin-top: 20px;
        text-align: center;
    }

    .home-viewer-grid {
        position: relative;
        max-width: 1120px;
        margin: 0 auto;
        overflow: hidden;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        isolation: isolate;
    }

    .home-visually-hidden {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }

    .home-viewer-grid::before,
    .home-viewer-grid::after {
        content: none;
    }

    .home-viewer-stage {
        position: relative;
        min-height: clamp(590px, 61vw, 650px);
        overflow: hidden;
        outline: none;
        perspective: 1500px;
        touch-action: pan-y;
        cursor: grab;
        user-select: none;
    }

    .home-viewer-stage:active { cursor: grabbing; }

    .home-viewer-stage:focus-visible {
        box-shadow: inset 0 0 0 4px rgba(23, 107, 58, .26);
    }

    .home-viewer-deck {
        position: absolute;
        inset: 18px 0 92px;
        transform-style: preserve-3d;
        transform: translate3d(var(--viewer-drag, 0px), 0, 0);
        transition: transform .48s cubic-bezier(.22, 1, .36, 1);
        will-change: transform;
    }

    .home-viewer-stage.is-dragging .home-viewer-deck { transition: none; }

    .home-viewer-card {
        position: absolute;
        top: 50%;
        left: 50%;
        z-index: 1;
        width: clamp(340px, 40vw, 460px);
        height: auto;
        aspect-ratio: 19 / 22;
        overflow: hidden;
        padding: 0;
        border: 0;
        border-radius: 38px;
        background: #173625;
        color: #fff;
        box-shadow: 0 24px 58px rgba(8, 45, 25, .22);
        opacity: 0;
        visibility: hidden;
        transform: translate(-50%, -50%) scale(.68);
        transform-style: preserve-3d;
        cursor: pointer;
        transition:
            transform .62s cubic-bezier(.22, 1, .36, 1),
            opacity .42s ease,
            filter .42s ease,
            visibility .42s ease,
            box-shadow .42s ease;
        will-change: transform, opacity;
    }

    .home-viewer-card[data-slot="0"] {
        z-index: 8;
        opacity: 1;
        visibility: visible;
        transform: translate(-50%, -50%) translateZ(0) scale(1);
        filter: saturate(1.04);
        box-shadow: 0 28px 58px rgba(8, 45, 25, .24), 0 0 0 1px rgba(255, 255, 255, .5);
    }

    .home-viewer-card[data-slot="-1"],
    .home-viewer-card[data-slot="1"] {
        z-index: 6;
        opacity: 1;
        visibility: visible;
        filter: saturate(.92);
    }

    .home-viewer-card[data-slot="-1"] {
        transform: translate(-50%, -50%) translateX(-64%) translateZ(-230px) rotateY(14deg) rotateZ(-6deg) scale(.85);
    }

    .home-viewer-card[data-slot="1"] {
        transform: translate(-50%, -50%) translateX(64%) translateZ(-230px) rotateY(-14deg) rotateZ(6deg) scale(.85);
    }

    .home-viewer-card[data-slot="-2"],
    .home-viewer-card[data-slot="2"] {
        z-index: 4;
        opacity: .76;
        visibility: visible;
        filter: saturate(.78);
    }

    .home-viewer-card[data-slot="-2"] {
        transform: translate(-50%, -50%) translateX(-112%) translateZ(-460px) rotateY(14deg) rotateZ(-6deg) scale(.7);
    }

    .home-viewer-card[data-slot="2"] {
        transform: translate(-50%, -50%) translateX(112%) translateZ(-460px) rotateY(-14deg) rotateZ(6deg) scale(.7);
    }

    .home-viewer-card img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        image-rendering: auto;
        backface-visibility: hidden;
        transition: transform .9s cubic-bezier(.22, 1, .36, 1);
        pointer-events: none;
    }

    .home-viewer-zoom-cue {
        position: absolute;
        z-index: 4;
        top: 18px;
        right: 18px;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 0 12px;
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 999px;
        background: rgba(4, 25, 14, .7);
        color: #fff;
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .02em;
        opacity: 0;
        transform: translateY(-7px);
        backdrop-filter: blur(10px);
        transition: opacity .3s ease, transform .4s cubic-bezier(.22, 1, .36, 1);
        pointer-events: none;
    }

    .home-viewer-card[data-slot="0"] .home-viewer-zoom-cue {
        opacity: 1;
        transform: translateY(0);
    }

    .home-viewer-card[data-slot="0"]:hover img { transform: scale(1.035); }

    .home-viewer-card::before,
    .home-viewer-card::after {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
    }

    .home-viewer-card::before {
        z-index: 1;
        background: #020a05;
        opacity: .55;
        transition: opacity .42s ease;
    }

    .home-viewer-card[data-slot="0"]::before { opacity: 0; }

    .home-viewer-card[data-slot="-2"]::before,
    .home-viewer-card[data-slot="2"]::before { opacity: .66; }

    .home-viewer-card::after {
        z-index: 2;
        background: linear-gradient(180deg, transparent 42%, rgba(4, 25, 14, .9) 100%);
    }

    .home-viewer-card-caption {
        position: absolute;
        z-index: 3;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 76px 28px 25px;
        text-align: left;
        opacity: 0;
        transform: translateY(12px);
        transition: opacity .48s ease .12s, transform .62s cubic-bezier(.22, 1, .36, 1) .08s;
        pointer-events: none;
    }

    .home-viewer-card[data-slot="0"] .home-viewer-card-caption {
        opacity: 1;
        transform: translateY(0);
    }

    .home-viewer-card[data-slot="-1"] .home-viewer-card-caption,
    .home-viewer-card[data-slot="1"] .home-viewer-card-caption {
        opacity: .62;
        transform: translateY(0);
    }

    .home-viewer-card-caption span {
        display: inline-flex;
        margin-bottom: 7px;
        padding: 5px 9px;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 999px;
        background: rgba(7, 49, 27, .66);
        color: #f5d894;
        font-size: .72rem;
        font-weight: 850;
        letter-spacing: .08em;
        text-transform: uppercase;
        backdrop-filter: blur(8px);
    }

    .home-viewer-card-caption strong {
        display: block;
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(1.05rem, 2vw, 1.38rem);
        line-height: 1.25;
        text-shadow: 0 3px 14px rgba(0, 0, 0, .38);
    }

    .home-viewer-card-caption small {
        display: block;
        margin-top: 5px;
        color: rgba(255, 255, 255, .76);
        font-size: .78rem;
        font-weight: 650;
        letter-spacing: .02em;
    }

    .home-viewer-card:focus-visible {
        outline: 4px solid var(--home-accent);
        outline-offset: 5px;
    }

    .home-viewer-toolbar {
        position: absolute;
        z-index: 12;
        top: 18px;
        left: 20px;
        right: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 9px;
        pointer-events: none;
    }

    .home-viewer-actions {
        display: flex;
        gap: 9px;
        pointer-events: auto;
    }

    .home-viewer-actions .home-viewer-zoom-button {
        width: auto;
        padding: 0 14px;
        gap: 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .home-viewer-zoom-button span {
        font-size: .78rem;
        font-weight: 800;
    }

    .home-viewer-count {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        padding: 0 13px;
        border: 1px solid rgba(23, 107, 58, .15);
        border-radius: 999px;
        background: rgba(255, 255, 255, .86);
        color: var(--home-primary-dark);
        font-size: 0.74rem;
        font-weight: 850;
        letter-spacing: 0.06em;
        backdrop-filter: blur(12px);
        box-shadow: 0 8px 24px rgba(13, 58, 34, .1);
        pointer-events: auto;
    }

    .home-viewer-controls {
        position: absolute;
        z-index: 12;
        left: 50%;
        bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: calc(100% - 30px);
        padding: 10px 13px;
        border: 1px solid rgba(13, 58, 34, .12);
        border-radius: 999px;
        background: rgba(245, 247, 245, .92);
        box-shadow: 0 13px 32px rgba(13, 58, 34, .13);
        backdrop-filter: blur(16px);
        transform: translateX(-50%);
    }

    .home-viewer-controls .home-icon-button {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border-color: rgba(23, 107, 58, .16);
        background: #edf5ef;
        color: var(--home-primary-dark);
        box-shadow: none;
    }

    .home-viewer-controls .home-icon-button:hover {
        color: #fff;
        background: var(--home-primary);
    }

    .home-viewer-dots {
        width: auto;
        min-width: 76px;
        display: flex;
        align-items: center;
        gap: 7px;
        overflow-x: auto;
        padding: 7px 2px;
        scrollbar-width: none;
        scroll-behavior: smooth;
    }

    .home-viewer-dots::-webkit-scrollbar { display: none; }

    .home-viewer-dot {
        width: 6px;
        height: 6px;
        flex: 0 0 6px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: #b7c8bb;
        cursor: pointer;
        transition: width .28s ease, background .28s ease, transform .28s ease;
    }

    .home-viewer-dot:hover { transform: scale(1.35); }

    .home-viewer-dot.is-active {
        width: 24px;
        flex-basis: 24px;
        background: #4f5551;
    }

    .home-viewer-progress {
        position: absolute;
        z-index: 13;
        left: 0;
        right: 0;
        bottom: 0;
        height: 3px;
        overflow: hidden;
        background: rgba(23, 107, 58, .08);
    }

    .home-viewer-progress::after {
        content: '';
        display: block;
        width: 100%;
        height: 100%;
        background: var(--home-accent);
        transform: scaleX(0);
        transform-origin: left;
    }

    .home-viewer-progress.is-running::after {
        animation: homeGalleryProgress 4.6s linear forwards;
    }

    @keyframes homeGalleryProgress {
        to { transform: scaleX(1); }
    }

    .home-icon-button {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid rgba(255, 255, 255, 0.38);
        border-radius: 50%;
        background: rgba(4, 25, 14, .62);
        color: #fff;
        cursor: pointer;
        backdrop-filter: blur(10px);
        box-shadow: 0 7px 20px rgba(0, 0, 0, .16);
        transition: transform 0.2s ease, background 0.2s ease, border-color .2s ease;
    }

    .home-icon-button:hover {
        transform: translateY(-2px);
        background: var(--home-primary);
        border-color: rgba(255, 255, 255, .72);
    }

    .home-lightbox {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        overflow: auto;
        padding: 74px 28px 28px;
        background: rgba(3, 18, 10, 0.92);
    }

    .home-lightbox.is-open {
        display: flex;
    }

    .home-lightbox-image {
        max-width: min(96vw, 1920px);
        max-height: min(88vh, 1080px);
        display: block;
        object-fit: contain;
        image-rendering: auto;
        border-radius: 14px;
        box-shadow: 0 30px 85px rgba(0, 0, 0, 0.48);
        transform-origin: center;
        transition: transform 0.25s ease;
        cursor: zoom-in;
    }

    .home-lightbox-image.is-zoomed {
        cursor: zoom-out;
    }

    .home-lightbox-close {
        position: fixed;
        top: 18px;
        right: 18px;
    }

    .home-lightbox-tools {
        position: fixed;
        z-index: 2;
        top: 18px;
        left: 18px;
        display: flex;
        gap: 9px;
    }

    .home-lightbox-zoom-level {
        min-width: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 10px;
        border: 1px solid rgba(255, 255, 255, 0.38);
        border-radius: 999px;
        background: rgba(4, 25, 14, .62);
        color: #fff;
        font-size: .78rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        backdrop-filter: blur(10px);
    }

    .home-lightbox-tools .home-icon-button:disabled {
        opacity: .42;
        cursor: not-allowed;
        transform: none;
    }

    .home-lightbox-nav {
        position: fixed;
        z-index: 3;
        top: 50%;
        transform: translateY(-50%);
    }

    .home-lightbox-prev { left: 18px; }
    .home-lightbox-next { right: 18px; }

    .home-contact-wrap {
        display: grid;
        grid-template-columns: minmax(300px, 0.76fr) minmax(0, 1.24fr);
        overflow: hidden;
        border: 1px solid #cfe0d1;
        border-radius: var(--home-radius-lg);
        background: #ffffff;
        box-shadow: 0 26px 70px rgba(12, 58, 33, 0.14);
    }

    .home-contact-aside {
        position: relative;
        overflow: hidden;
        padding: 42px;
        background:
            radial-gradient(circle at 90% 12%, rgba(242, 180, 65, 0.32), transparent 25%),
            linear-gradient(145deg, #0d4a28, #176b3a 60%, #2f8a50);
        color: #fff;
    }

    .home-contact-aside::after {
        content: '';
        position: absolute;
        right: -75px;
        bottom: -80px;
        width: 220px;
        height: 220px;
        border: 42px solid rgba(255, 255, 255, 0.08);
        border-radius: 50%;
    }

    .home-contact-aside h3 {
        position: relative;
        margin: 0 0 15px;
        font-size: 1.75rem;
        line-height: 1.25;
    }

    .home-contact-aside > p {
        position: relative;
        margin: 0 0 28px;
        color: rgba(240, 250, 243, 0.82);
        line-height: 1.75;
    }

    .home-contact-points {
        position: relative;
        display: grid;
        gap: 18px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .home-contact-points li {
        display: grid;
        grid-template-columns: 42px 1fr;
        align-items: center;
        gap: 12px;
        font-size: 0.92rem;
        font-weight: 750;
    }

    .home-contact-points i {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: rgba(255, 255, 255, 0.13);
        color: #ffe2a1;
    }

    .home-contact-form {
        position: relative;
        padding: 38px 42px 42px;
        background:
            radial-gradient(circle at 100% 0%, rgba(242, 180, 65, 0.09), transparent 28%),
            #ffffff;
    }

    .home-form-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 22px;
        margin-bottom: 28px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--home-border);
    }

    .home-form-intro-copy {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .home-form-intro-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: linear-gradient(145deg, #e9f6ec, #fff5d8);
        color: var(--home-primary);
        font-size: 1.15rem;
        box-shadow: inset 0 0 0 1px rgba(23, 107, 58, 0.09);
    }

    .home-form-intro h3 {
        margin: 0 0 3px;
        color: var(--home-heading);
        font-family: Merriweather, Georgia, serif;
        font-size: 1.28rem;
        line-height: 1.3;
    }

    .home-form-intro p {
        margin: 0;
        color: var(--home-muted);
        font-size: 0.84rem;
        line-height: 1.5;
    }

    .home-required-note {
        flex: 0 0 auto;
        margin: 0;
        padding: 6px 10px;
        border: 1px solid #d9e6d9;
        border-radius: 999px;
        background: #f6faf5;
        color: #607066;
        font-size: 0.74rem;
        font-weight: 750;
        white-space: nowrap;
    }

    .home-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 21px 18px;
    }

    .home-form-group {
        min-width: 0;
    }

    .home-form-full {
        grid-column: 1 / -1;
    }

    .home-form-group label {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 8px;
        color: var(--home-heading);
        font-size: 0.86rem;
        font-weight: 800;
    }

    .home-field-required {
        color: #b13c2f;
        font-size: 1rem;
        line-height: 1;
    }

    .home-field-optional {
        margin-left: auto;
        color: #7c8a81;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .home-form-control {
        width: 100%;
        min-height: 54px;
        padding: 14px 15px;
        border: 1px solid #cfded0;
        border-radius: 13px;
        background: #f9fcf8;
        color: var(--home-ink);
        font: inherit;
        font-size: 0.95rem;
        outline: none;
        box-shadow: inset 0 1px 2px rgba(18, 63, 37, 0.025);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }

    .home-form-control::placeholder {
        color: #87968d;
        opacity: 1;
    }

    select.home-form-control {
        cursor: pointer;
    }

    textarea.home-form-control {
        min-height: 142px;
        line-height: 1.6;
        resize: vertical;
    }

    .home-form-control:focus {
        border-color: var(--home-primary);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(47, 138, 80, 0.12), 0 8px 22px rgba(19, 83, 47, 0.07);
    }

    .home-form-control:hover:not(:focus) {
        border-color: #adc9b2;
    }

    .home-captcha {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 16px;
        border: 1px solid #cfe2d2;
        border-radius: 14px;
        background: #f4faf5;
    }

    .home-captcha[hidden] {
        display: none;
    }

    .home-captcha-copy {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .home-captcha-copy i {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #dcefe0;
        color: var(--home-primary);
    }

    .home-captcha-copy strong,
    .home-captcha-copy span {
        display: block;
    }

    .home-captcha-copy strong {
        color: var(--home-heading);
        font-size: 0.88rem;
    }

    .home-captcha-copy span {
        margin-top: 2px;
        color: var(--home-muted);
        font-size: 0.8rem;
    }

    .home-captcha-answer {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }

    .home-captcha-action {
        display: grid;
        justify-items: end;
        gap: 5px;
    }

    .home-captcha-question {
        color: var(--home-heading);
        font-weight: 850;
        white-space: nowrap;
    }

    .home-captcha-input {
        width: 82px;
        min-height: 46px;
        text-align: center;
        font-weight: 800;
    }

    .home-captcha-refresh {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 46px;
        border: 1px solid #bfd8c5;
        border-radius: 12px;
        color: var(--home-primary);
        background: #fff;
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }

    .home-captcha-refresh:hover {
        border-color: var(--home-primary);
        background: #eaf6ed;
        transform: rotate(20deg);
    }

    .home-captcha-refresh:focus-visible {
        outline: 3px solid rgba(47, 138, 80, 0.2);
        outline-offset: 2px;
    }

    .home-captcha-refresh[disabled] {
        opacity: 0.6;
        cursor: wait;
        transform: none;
    }

    .home-captcha-status {
        min-height: 1.2em;
        color: var(--home-muted);
        font-size: 0.74rem;
    }

    .home-security-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 10px 2px 0;
        color: #66766b;
        font-size: 0.74rem;
        line-height: 1.55;
    }

    .home-security-note i {
        margin-top: 3px;
        color: var(--home-primary);
    }

    .home-security-note a {
        color: #426d4f;
        font-weight: 700;
        text-underline-offset: 2px;
    }

    .home-security-note a:hover {
        color: var(--home-primary);
    }

    .home-security-state {
        display: block;
        min-height: 1.2em;
        margin-top: 3px;
    }

    .home-form-status {
        margin-bottom: 20px;
        padding: 13px 15px;
        border-radius: 12px;
        font-size: 0.9rem;
        font-weight: 750;
        line-height: 1.55;
    }

    .home-form-status-success {
        border: 1px solid #b8e0c3;
        background: #eaf8ee;
        color: #126235;
    }

    .home-form-status-error {
        border: 1px solid #edc1bd;
        background: #fff0ef;
        color: #91241f;
    }

    .home-honeypot {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        clip: rect(1px, 1px, 1px, 1px) !important;
        white-space: nowrap !important;
    }

    .home-submit {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        width: 100%;
        min-height: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 11px;
        margin-top: 24px;
        padding: 15px 24px;
        border: 1px solid #0e5730;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d4a28 0%, #176b3a 58%, #21804a 100%);
        color: #ffffff;
        box-shadow: 0 15px 30px rgba(17, 94, 51, 0.23);
        font: inherit;
        font-size: 0.98rem;
        font-weight: 850;
        letter-spacing: 0.01em;
        cursor: pointer;
        transition: transform 0.22s ease, box-shadow 0.22s ease, filter 0.22s ease;
    }

    .home-submit::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(110deg, transparent 22%, rgba(255, 255, 255, 0.2) 46%, transparent 68%);
        transform: translateX(-115%);
        transition: transform 0.55s ease;
    }

    .home-submit:hover {
        color: #ffffff;
        filter: saturate(1.08);
        transform: translateY(-2px);
        box-shadow: 0 19px 38px rgba(17, 94, 51, 0.29);
    }

    .home-submit:hover::before {
        transform: translateX(115%);
    }

    .home-submit:focus-visible {
        outline: 3px solid rgba(242, 180, 65, 0.62);
        outline-offset: 4px;
    }

    .home-submit i {
        font-size: 1rem;
        transition: transform 0.22s ease;
    }

    .home-submit:hover i {
        transform: translate(2px, -2px);
    }

    .home-submit-help {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        gap: 7px;
        margin: 12px auto 0;
        color: #738179;
        font-size: 0.76rem;
        line-height: 1.5;
        text-align: center;
    }

    .home-submit-help i {
        margin-top: 3px;
        color: var(--home-primary);
    }

    .home-submit[disabled] {
        opacity: 0.68;
        cursor: wait;
        transform: none;
        box-shadow: none;
    }

    @media (max-width: 1100px) {
        .home-stats-grid,
        .home-feature-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .home-activity-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .home-produce-grid > .home-produce-card {
            flex-basis: calc((100% - 15px) / 2);
        }
    }

    @media (max-width: 900px) {
        .home-hero {
            min-height: auto;
        }

        .home-hero-inner,
        .home-about-grid,
        .home-contact-wrap {
            grid-template-columns: 1fr;
        }

        .home-hero-inner {
            padding-top: 88px;
        }

        .home-hero-panel {
            max-width: 610px;
        }

        .home-viewer-grid { border-radius: 22px; }
    }

    @media (max-width: 680px) {
        .home-section {
            padding: 68px 0;
        }

        .home-ticker-inner {
            gap: 9px;
        }

        .home-ticker-label {
            padding-inline: 10px;
        }

        .home-ticker-label span {
            display: none;
        }

        .home-ticker-date {
            display: none;
        }

        .home-hero-inner {
            padding: 74px 0 142px;
        }

        .home-hero-edge {
            height: 132px;
        }

        .home-hero-edge::after {
            bottom: 112px;
            width: calc(100% - 42px);
        }

        .home-hero-title {
            font-size: clamp(2.35rem, 11vw, 3.5rem);
        }

        .home-hero-description {
            font-size: 1rem;
        }

        .home-actions {
            display: grid;
        }

        .home-button {
            width: 100%;
            min-height: 52px;
        }

        .home-button-ghost { backdrop-filter: none; }
        .home-hero-waves { height: 52px; }

        .home-stats {
            margin-top: -58px;
        }

        .home-stats-grid,
        .home-feature-grid,
        .home-activity-grid,
        .home-form-grid {
            grid-template-columns: 1fr;
        }

        .home-produce-grid > .home-produce-card {
            flex-basis: 82%;
        }

        .home-form-full {
            grid-column: auto;
        }

        .home-captcha {
            align-items: stretch;
            flex-direction: column;
        }

        .home-captcha-answer {
            justify-content: flex-start;
            flex-wrap: wrap;
            padding-left: 0;
        }

        .home-captcha-action {
            justify-items: start;
        }

        .home-captcha-question {
            width: 100%;
            white-space: normal;
        }

        .home-viewer-stage {
            min-height: 550px;
        }

        .home-viewer-deck {
            inset: 18px 0 90px;
        }

        .home-viewer-card {
            width: min(80vw, 380px);
            border-radius: 30px;
        }

        .home-viewer-card[data-slot="-1"] {
            transform: translate(-50%, -50%) translateX(-58%) translateZ(-200px) rotateY(14deg) rotateZ(-6deg) scale(.85);
        }

        .home-viewer-card[data-slot="1"] {
            transform: translate(-50%, -50%) translateX(58%) translateZ(-200px) rotateY(-14deg) rotateZ(6deg) scale(.85);
        }

        .home-viewer-card[data-slot="-2"],
        .home-viewer-card[data-slot="2"] {
            opacity: 0;
            visibility: hidden;
        }

        .home-contact-aside,
        .home-contact-form {
            padding: 28px 22px;
        }

        .home-form-intro {
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
        }

        .home-about-photo {
            min-height: 500px;
        }
    }

    @media (max-width: 430px) {
        .home-about-photo {
            min-height: 430px;
            border-width: 5px;
        }

        .home-about-photo figcaption {
            left: 20px;
            right: 20px;
            bottom: 18px;
        }

        .home-viewer-stage {
            min-height: 520px;
        }

        .home-viewer-deck {
            inset: 18px 0 88px;
        }

        .home-viewer-card {
            width: min(84vw, 360px);
            border-radius: 25px;
        }

        .home-viewer-card-caption {
            padding: 64px 20px 20px;
        }

        .home-viewer-card-caption strong {
            font-size: 1rem;
        }

        .home-viewer-zoom-cue {
            width: 36px;
            padding: 0;
            justify-content: center;
        }

        .home-viewer-zoom-cue span { display: none; }

        .home-viewer-controls {
            bottom: 12px;
            gap: 9px;
            padding: 8px 10px;
        }

        .home-viewer-dots {
            width: auto;
            min-width: 76px;
        }

        .home-icon-button {
            width: 42px;
            height: 42px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .kvk-home *,
        .kvk-home *::before,
        .kvk-home *::after {
            scroll-behavior: auto !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }

        .home-viewer-progress.is-running::after {
            animation-duration: 4.6s !important;
        }

        .home-ticker-track {
            animation-name: homeTicker !important;
            animation-duration: 80s !important;
            animation-timing-function: linear !important;
            animation-iteration-count: infinite !important;
        }
    }
</style>

<div class="kvk-home" id="main-content">
    <section class="home-ticker" aria-label="Latest updates">
        <div class="container home-ticker-inner">
            <span class="home-ticker-label"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i><span>UPDATES</span></span>

            <div class="home-ticker-window">
                <?php if ($notices): ?>
                    <div class="home-ticker-track<?= $noticeCount === 1 ? ' home-ticker-track--single' : '' ?>">
                        <?php for ($tickerCopy = 0; $tickerCopy < $tickerCopyCount; $tickerCopy++): ?>
                            <div class="home-ticker-group"<?= $tickerCopy > 0 ? ' aria-hidden="true"' : '' ?>>
                                <?php foreach ($notices as $index => $notice): ?>
                                    <a class="home-ticker-item" href="notice.php?id=<?= max(0, (int) ($notice['id'] ?? 0)) ?>"<?= $tickerCopy > 0 ? ' tabindex="-1"' : '' ?>>
                                        <span class="home-ticker-type"><?= e(home_update_label((int) $index)) ?></span>
                                        <span class="home-ticker-date"><?= e(home_update_date($notice)) ?></span>
                                        <span><?= e((string) ($notice['title'] ?? 'Notice')) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <span class="home-ticker-empty">New notices and farmer advisories will appear here.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
	
    <section class="home-hero" id="home" aria-labelledby="heroTitle">
        <div class="home-hero-backdrop" id="heroBackdrop" aria-hidden="true"></div>
        <div class="container home-hero-inner">
            <div class="home-hero-copy" id="heroCopy">
                
                <h2 class="home-hero-title" id="heroTitle"><?= e($heroSlides[0]['title']) ?></h2>
                <p class="home-hero-description" id="heroDescription"><?= e($heroSlides[0]['description']) ?></p>

              

                <div class="home-slide-controls" id="heroDots" aria-label="Hero slides"></div>
            </div>

           
        </div>

        
        <div class="home-hero-edge" aria-hidden="true"></div>
    </section>

    <section class="home-stats" aria-label="KVK achievements">
        <div class="container">
            <div class="home-stats-grid">
                <article class="home-stat-card">
                    <span class="home-stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <p class="home-stat-value" data-count="<?= e((string) $farmersTrainedCount) ?>"><?= e(number_format($farmersTrainedCount)) ?></p>
                    <p class="home-stat-label">Farmers Trained</p>
                </article>
                <article class="home-stat-card">
                    <span class="home-stat-icon"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i></span>
                    <p class="home-stat-value" data-count="<?= e((string) $trainingsOrganizedCount) ?>"><?= e(number_format($trainingsOrganizedCount)) ?></p>
                    <p class="home-stat-label">Trainings Organized</p>
                </article>
                <article class="home-stat-card">
                    <span class="home-stat-icon"><i class="fa-solid fa-vials" aria-hidden="true"></i></span>
                    <p class="home-stat-value" data-count="257">257</p>
                    <p class="home-stat-label">On-Farm Trials</p>
                </article>
                <article class="home-stat-card">
                    <span class="home-stat-icon"><i class="fa-solid fa-tractor" aria-hidden="true"></i></span>
                    <p class="home-stat-value" data-count="320">320</p>
                    <p class="home-stat-label">Frontline Demonstrations</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section" id="about">
        <div class="container home-about-grid">
            <div class="home-about-copy">
                <span class="home-eyebrow">Welcome to Krishi Vigyan Kendra Kokrajhar</span>
                <h2>Establishment of KVK Kokrajhar</h2>
                <p>Krishi Vigyan Kendra (KVK), farm science centre-Kokrajhar was established in 1985 with financial support from ICAR, New Delhi, and is hosted by Assam Agricultural University (AAU), Jorhat, Assam. The KVK is situated at Gossaigaon (Telipara) in the Kokrajhar district of Assam. The mandated activities of the KVK are operated across the farming communities of Kokrajhar district. The KVK works with an action plan guided and approved by ICAR-Agricultural Technology Application Research Institute (ATARI), Zone-VI, Guwahati, Assam.</p>
                <a href="establishment.php">Learn about our establishment <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>

            <figure class="home-about-photo">
                <img
                    src="assets/img/kvk-kokrajhar-team.png"
                    
                    width="450"
                    height="600"
					loading="lazy"
                    decoding="async"
                    alt="KVK Kokrajhar team"
                >
                <figcaption>
                    KVK Kokrajhar Team
                    <span>Committed to farmers, field innovation, and rural development</span>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="home-section home-section-soft" id="infrastructure">
        <div class="container">
            <div class="home-section-heading">
                <span class="home-eyebrow">Facilities</span>
                <h2>Infrastructure supporting learning and demonstration</h2>
                <p>Purpose-built facilities help deliver practical training, farm demonstrations, technology testing, and farmer-oriented programmes.</p>
            </div>

            <div class="home-feature-grid">
                <article class="home-feature-card">
                    <span class="home-feature-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                    <h3><a href="admin_building.php">Administrative Building</a></h3>
                    <p>Office and coordination space for extension, programme management, and farmer services.</p>
                </article>
                <article class="home-feature-card">
                    <span class="home-feature-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                    <h3><a href="farmers-hostel.php">Farmers' Hostel</a></h3>
                    <p>Residential support for participants attending training and capacity-building programmes.</p>
                </article>
                <article class="home-feature-card">
                    <span class="home-feature-icon"><i class="fa-solid fa-compass-drafting" aria-hidden="true"></i></span>
                    <h3><a href="demonstration-units.php">Demonstration Units</a></h3>
                    <p>Practical learning spaces for crop, livestock, fishery, and allied agricultural technologies.</p>
                </article>
                <article class="home-feature-card">
                    <span class="home-feature-icon"><i class="fa-solid fa-tractor" aria-hidden="true"></i></span>
                    <h3><a href="farm-machinery.php">Farm Machinery</a></h3>
                    <p>Equipment used for field operations, demonstrations, and skill-oriented technical training.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section" id="services">
        <div class="container">
            <div class="home-section-heading">
                <span class="home-eyebrow">Extension Services</span>
                <h2>Programmes designed around field needs</h2>
                <p>Explore active programmes, trainings, demonstrations, and technical services delivered by KVK Kokrajhar.</p>
            </div>

            <div class="home-activity-grid">
                <?php if ($activities): ?>
                    <?php foreach ($activities as $activity): ?>
                        <?php
                        $activitySlug = (string) ($activity['slug'] ?? 'KVK');
                        $activityDetails = (string) ($activity['details'] ?? '');
                        $isTrainingRecord = home_activity_is_training_slug($activitySlug)
                            || home_activity_is_training_record($activityDetails);
                        $activityUrl = ($isTrainingRecord ? 'training-record.php' : 'activity.php')
                            . '?id=' . max(0, (int) ($activity['id'] ?? 0));
                        ?>
                        <article class="home-activity-card">
                            <span class="home-card-tag"><?= e($activitySlug !== '' ? $activitySlug : 'KVK') ?></span>
                            <h3><?= e((string) ($activity['title'] ?? 'Extension activity')) ?></h3>
                            <p><?= e((string) ($activity['summary'] ?? 'View details of this KVK programme and its field-level activities.')) ?></p>
                            <a class="home-card-link" href="<?= e($activityUrl) ?>">
                                <?= $isTrainingRecord ? 'View training record' : 'View details' ?>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="home-empty-card">Active extension-service records will appear here after they are added to the activities section.</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-produce-section" id="kvk-produce">
        <div class="container">
            <div class="home-produce-heading-row">
                <div class="home-section-heading">
                    <span class="home-eyebrow">KVK Farm Produce</span>
                    <h2>Quality produce and planting materials</h2>
                    <p>Check current availability, view product details, and submit a purchase request directly to KVK Kokrajhar.</p>
                </div>
                <?php if (count($homeProducts) > 1): ?>
                    <div class="home-produce-scroller-controls" aria-label="Product scroller controls">
                        <span>Scroll all products</span>
                        <button class="home-produce-scroll-button" id="homeProducePrevious" type="button" aria-label="Show previous product">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <button class="home-produce-scroll-button" id="homeProduceNext" type="button" aria-label="Show next product">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($homeProducts): ?>
                <div class="home-produce-grid" id="homeProduceCarousel" aria-label="Available KVK produce">
                    <?php foreach ($homeProducts as $productIndex => $product): ?>
                        <?php
                        $productId = (int) ($product['id'] ?? 0);
                        $productName = trim((string) ($product['commodity_name'] ?? 'KVK Produce'));
                        $productUnit = trim((string) ($product['unit'] ?? '')) ?: 'unit';
                        $productRate = max(0, (float) ($product['rate'] ?? 0));
                        $productAvailable = max(0, (float) ($product['available_qty'] ?? 0));
                        $productNote = trim((string) ($product['product_note'] ?? ''));
                        $productUrl = 'planting-materials.php?buy=' . $productId . '#available-materials';
                        $productDetailsUrl = 'planting-materials.php?product=' . $productId . '#material-' . $productId;
                        $productQrUrl = home_product_detail_url($productId);
                        
                        ?>
                        <article class="home-produce-card">
                            <div class="home-produce-image">
                                <img src="<?= e(home_product_image($product, (int) $productIndex)) ?>" alt="<?= e($productName) ?>" loading="lazy" decoding="async">
                                <span class="home-produce-stock"><?= e(home_product_number($productAvailable)) ?> <?= e($productUnit) ?> available</span>
                            </div>
                            <div class="home-produce-body">
                                <h3><?= e($productName) ?></h3>
                                <?php if (trim((string) ($product['tagline'] ?? '')) !== ''): ?>
                                    <p class="home-produce-tagline"><?= e((string) $product['tagline']) ?></p>
                                <?php endif; ?>
                                <div class="home-produce-price">Rs. <?= e(number_format($productRate, 2)) ?> <small>/ <?= e($productUnit) ?></small></div>
                                <p class="home-produce-note"><?= e($productNote !== '' ? $productNote : 'KVK-produced agricultural material supplied subject to current stock availability.') ?></p>
                                <?php if ($productProfileFields): ?>
                                    <dl class="home-produce-profile">
                                        <?php foreach ($productProfileFields as $profileLabel => $profileValue): ?>
                                            <div><dt><?= e($profileLabel) ?></dt><dd><?= e($profileValue) ?></dd></div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                                <?php if (!empty($product['show_qr'])): ?>
                                    <div class="home-produce-qr">
                                        <div class="home-produce-qr__code" data-product-qr data-qr-url="<?= e($productQrUrl) ?>" aria-label="QR code for <?= e($productName) ?>"></div>
                                        <p><strong>Scan for full details</strong>Open this product profile, photos and current availability.</p>
                                    </div>
                                <?php endif; ?>
                                <div class="home-produce-actions">
                                    <a class="home-button home-produce-action-button" href="<?= e($productDetailsUrl) ?>" aria-label="View details for <?= e($productName) ?>">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <span>View Details</span>
                                    </a>
                                    <a class="home-button home-produce-action-button" href="<?= e($productUrl) ?>" aria-label="Purchase <?= e($productName) ?>">
                                        <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                                        <span>Purchase</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="home-empty-card">KVK produce will appear here automatically when items are available in the Planting Materials stock register.</div>
            <?php endif; ?>

            <div class="home-produce-footer">
                <a class="home-button home-button-primary" href="planting-materials.php#available-materials">View all KVK produce <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>

    <section class="home-section" id="image-viewer">
        <div class="container">
            <div class="home-section-heading">
                <span class="home-eyebrow">Gallery</span>
                <h2>KVK Kokrajhar in action</h2>
                <p>Browse photographs from campus activities, farmer training, field demonstrations, advisories, and outreach programmes.</p>
            </div>

            <div class="home-viewer-grid" aria-label="Photo gallery">
                <div
                    class="home-viewer-stage"
                    id="viewerStage"
                    role="region"
                    aria-roledescription="carousel"
                    aria-label="KVK Kokrajhar photo gallery. Use the arrow keys or swipe to browse."
                    tabindex="0"
                >
                    <div class="home-viewer-toolbar">
                        
                        <div class="home-viewer-actions" aria-label="Gallery playback and view options">
                            <button class="home-icon-button" type="button" id="viewerPlayPause" aria-label="Pause slideshow" aria-pressed="true" title="Pause slideshow"><i class="fa-solid fa-pause" aria-hidden="true"></i></button>
                            <button class="home-icon-button home-viewer-zoom-button" type="button" id="viewerExpand" aria-label="Zoom image" title="Zoom image"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i><span>Zoom</span></button>
                        </div>
                    </div>

                    <div class="home-viewer-deck" id="viewerDeck">
                        <?php foreach ($viewerImages as $index => $viewerImageItem): ?>
                            <button
                                class="home-viewer-card"
                                type="button"
                                data-index="<?= e((string) $index) ?>"
                                data-slot="<?= $index === 0 ? '0' : ($index === 1 ? '1' : ($index === count($viewerImages) - 1 ? '-1' : 'hidden')) ?>"
                                aria-label="<?= $index === 0 ? 'Open ' : 'Show ' ?><?= e($viewerImageItem['title']) ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                tabindex="<?= $index === 0 ? '0' : '-1' ?>"
                            >
                                <img
                                    src="<?= e($viewerImageItem['img']) ?>"
                                    alt="<?= e($viewerImageItem['alt']) ?>"
                                    width="<?= e((string) ($viewerImageItem['width'] ?? 1920)) ?>"
                                    height="<?= e((string) ($viewerImageItem['height'] ?? 1080)) ?>"
                                    loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                    fetchpriority="<?= $index === 0 ? 'high' : 'auto' ?>"
                                    sizes="(max-width: 430px) 84vw, (max-width: 680px) 80vw, min(40vw, 460px)"
                                    decoding="async"
                                >
                                
                               
                                    
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="home-viewer-controls" aria-label="Gallery controls">
                        <button class="home-icon-button" type="button" id="viewerPrevious" aria-label="Previous image"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                        <div class="home-viewer-dots" id="viewerDots" aria-label="Choose a gallery image">
                            <?php foreach ($viewerImages as $index => $viewerImageItem): ?>
                                <button
                                    class="home-viewer-dot<?= $index === 0 ? ' is-active' : '' ?>"
                                    type="button"
                                    data-index="<?= e((string) $index) ?>"
                                    aria-label="Show image <?= e((string) ($index + 1)) ?>: <?= e($viewerImageItem['title']) ?>"
                                    aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                ></button>
                            <?php endforeach; ?>
                        </div>
                        <button class="home-icon-button" type="button" id="viewerNext" aria-label="Next image"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    </div>

                    
                </div>
            </div>
        </div>
    </section>

    <div class="home-lightbox" id="imageLightbox" role="dialog" aria-modal="true" aria-label="Expanded gallery image" aria-hidden="true">
        <div class="home-lightbox-tools">
            <button class="home-icon-button" type="button" id="lightboxZoomOut" aria-label="Zoom out"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></button>
            <button class="home-icon-button" type="button" id="lightboxZoomReset" aria-label="Reset zoom"><i class="fa-solid fa-compress" aria-hidden="true"></i></button>
            <output class="home-lightbox-zoom-level" id="lightboxZoomLevel" aria-live="polite">100%</output>
            <button class="home-icon-button" type="button" id="lightboxZoomIn" aria-label="Zoom in"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
        </div>
        <button class="home-icon-button home-lightbox-close" type="button" id="lightboxClose" aria-label="Close image viewer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <button class="home-icon-button home-lightbox-nav home-lightbox-prev" type="button" id="lightboxPrevious" aria-label="Previous image"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
        <img class="home-lightbox-image" id="lightboxImage" src="<?= e($viewerImages[0]['img']) ?>" alt="<?= e($viewerImages[0]['alt']) ?>" decoding="async" role="button" tabindex="0" aria-label="Toggle image zoom">
        <button class="home-icon-button home-lightbox-nav home-lightbox-next" type="button" id="lightboxNext" aria-label="Next image"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
    </div>

    <section class="home-section" id="appointment">
        <div class="container">
            <div class="home-section-heading">
                <span class="home-eyebrow">Farmer Advisory</span>
                <h2>Request technical guidance</h2>
                <p>Share your concern with KVK Kokrajhar so the appropriate technical team can review your advisory request.</p>
            </div>

            <div class="home-contact-wrap">
                <aside class="home-contact-aside">
                    <h3>Let us understand your field-level concern.</h3>
                    <p>Provide clear details about the crop, livestock, fishery, pest, disease, or management issue for a more useful response.</p>
                    <ul class="home-contact-points">
                        <li><i class="fa-solid fa-user-doctor" aria-hidden="true"></i><span>Guidance from relevant technical personnel</span></li>
                        <li><i class="fa-solid fa-leaf" aria-hidden="true"></i><span>Need-based and practical recommendations</span></li>
                        <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Support focused on local farming conditions</span></li>
                    </ul>
                </aside>

                <form class="home-contact-form" method="post" id="appointmentForm" action="<?= e(home_public_base_path()) ?>#appointment" accept-charset="UTF-8" novalidate>
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>" autocomplete="off">
                    <input type="hidden" id="captchaChallenge" name="captcha_challenge" value="<?= e($captchaChallenge) ?>" autocomplete="off">
                    <input type="hidden" id="recaptchaToken" name="g-recaptcha-response" value="" autocomplete="off">
                    <div class="home-honeypot" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="home-form-intro">
                        <div class="home-form-intro-copy">
                            <span class="home-form-intro-icon"><i class="fa-solid fa-seedling" aria-hidden="true"></i></span>
                            <div>
                                <h3>Tell us about your farm issue</h3>
                                <p>Include the important details so our team can provide useful guidance.</p>
                            </div>
                        </div>
                        <p class="home-required-note"><span class="home-field-required" aria-hidden="true">*</span> Required fields</p>
                    </div>

                    <?php if (is_array($contactStatus)): ?>
                        <div
                            class="home-form-status <?= ($contactStatus['type'] ?? '') === 'success' ? 'home-form-status-success' : 'home-form-status-error' ?>"
                            role="<?= ($contactStatus['type'] ?? '') === 'success' ? 'status' : 'alert' ?>"
                        >
                            <?= e((string) ($contactStatus['message'] ?? '')) ?>
                        </div>
                    <?php endif; ?>

                    <div class="home-form-grid">
                        <div class="home-form-group">
                            <label for="name">Full Name <span class="home-field-required" aria-hidden="true">*</span></label>
                            <input class="home-form-control" type="text" id="name" name="name" value="<?= e($formData['name']) ?>" placeholder="Enter your full name" maxlength="100" autocomplete="name" required>
                        </div>

                        <div class="home-form-group">
                            <label for="phone">Phone Number <span class="home-field-required" aria-hidden="true">*</span></label>
                            <input class="home-form-control" type="tel" id="phone" name="phone" value="<?= e($formData['phone']) ?>" placeholder="Enter your mobile number" maxlength="20" inputmode="tel" autocomplete="tel" pattern="[0-9+() -]{10,20}" required>
                        </div>

                        <div class="home-form-group">
                            <label for="email">Email Address <span class="home-field-optional">Optional</span></label>
                            <input class="home-form-control" type="email" id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="Enter your email address" maxlength="150" autocomplete="email">
                        </div>

                        <div class="home-form-group">
                            <label for="subject">Area of Expertise Needed <span class="home-field-required" aria-hidden="true">*</span></label>
                            <select class="home-form-control" id="subject" name="subject" required>
                                <option value="">Select a domain</option>
                                <?php foreach ($expertiseOptions as $option): ?>
                                    <option value="<?= e($option) ?>"<?= $formData['subject'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="home-form-group home-form-full">
                            <label for="message">Describe Your Problem or Query <span class="home-field-required" aria-hidden="true">*</span></label>
                            <textarea class="home-form-control" id="message" name="message" rows="5" maxlength="2000" placeholder="Briefly describe the crop, animal, pest, disease, or farm-management concern..." required><?= e($formData['message']) ?></textarea>
                        </div>

                        <div class="home-form-group home-form-full">
                            <div class="home-captcha" id="captchaFallback"<?= $captchaFallbackRequired ? '' : ' hidden' ?>>
                                <div class="home-captcha-copy">
                                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                    <div>
                                        <strong>Quick human check</strong>
                                        <span><?= $recaptchaEnabled ? 'Complete this only when the automatic check needs a little help.' : 'Complete this changing question to protect your request from automated spam.' ?></span>
                                    </div>
                                </div>
                                <div class="home-captcha-action">
                                    <div class="home-captcha-answer">
                                        <label class="home-captcha-question" for="captcha_answer" id="captchaQuestion"><?= e($captchaQuestion) ?></label>
                                        <input class="home-form-control home-captcha-input" type="text" id="captcha_answer" name="captcha_answer" maxlength="2" inputmode="numeric" pattern="[0-9]{1,2}" autocomplete="off" aria-describedby="captchaHelp captchaRefreshStatus"<?= $captchaFallbackRequired ? ' required' : '' ?>>
                                        <button class="home-captcha-refresh" type="button" id="captchaRefresh" aria-label="Get a new human-verification question" title="New question">
                                            <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <span class="home-captcha-status" id="captchaRefreshStatus" role="status" aria-live="polite"></span>
                                </div>
                            </div>
                            <span class="home-honeypot" id="captchaHelp">Required human-verification answer</span>
                            <?php if ($recaptchaEnabled): ?>
                                <p class="home-security-note">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    <span>
                                        This site is protected by reCAPTCHA and the Google
                                        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and
                                        <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Terms of Service</a> apply.
                                        <span class="home-security-state" id="recaptchaStatus" role="status" aria-live="polite">Automatic protection is ready.</span>
                                    </span>
                                </p>
                            <?php else: ?>
                                <p class="home-security-note">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    <span>Secure local human verification is active.</span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button class="home-button home-button-primary home-submit" type="submit" id="appointmentSubmit">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        <span>Submit Advisory Request</span>
                    </button>
                    <p class="home-submit-help"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Your contact details are used only to respond to this advisory request.</span></p>
                </form>
            </div>
        </div>
    </section>
</div>

<?php if ($recaptchaEnabled): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?= rawurlencode($recaptchaSiteKey) ?>" async defer></script>
<?php endif; ?>
<script src="assets/vendor/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        document.querySelectorAll('[data-product-qr]').forEach((container) => {
            const url = container.dataset.qrUrl || '';
            if (!url || typeof window.QRCode !== 'function') return;
            container.replaceChildren();
            new window.QRCode(container, {
                text: url,
                width: 240,
                height: 240,
                colorDark: '#102f1f',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.M
            });
        });

        const produceCarousel = document.getElementById('homeProduceCarousel');
        const producePrevious = document.getElementById('homeProducePrevious');
        const produceNext = document.getElementById('homeProduceNext');
        const produceCards = Array.from(produceCarousel?.querySelectorAll('.home-produce-card') || []);
        let produceTimer = null;

        const visibleProduceCards = () => {
            if (window.innerWidth <= 680) return 1;
            if (window.innerWidth <= 1100) return 2;
            return 3;
        };

        const scrollProduce = (direction) => {
            const firstCard = produceCards[0];
            if (!produceCarousel || !firstCard) return;

            const step = firstCard.getBoundingClientRect().width + 15;
            const maximumScroll = Math.max(0, produceCarousel.scrollWidth - produceCarousel.clientWidth);
            let nextPosition;
            if (direction > 0) {
                nextPosition = produceCarousel.scrollLeft >= maximumScroll - 2
                    ? 0
                    : Math.min(maximumScroll, produceCarousel.scrollLeft + step);
            } else {
                nextPosition = produceCarousel.scrollLeft <= 2
                    ? maximumScroll
                    : Math.max(0, produceCarousel.scrollLeft - step);
            }

            produceCarousel.scrollTo({
                left: Math.max(0, nextPosition),
                behavior: prefersReducedMotion ? 'auto' : 'smooth'
            });
        };

        const startProduceCarousel = () => {
            window.clearInterval(produceTimer);
            if (!produceCarousel || prefersReducedMotion || produceCards.length <= visibleProduceCards() || document.hidden) return;

            produceTimer = window.setInterval(() => {
                scrollProduce(1);
            }, 4000);
        };

        if (produceCarousel) {
            if ('IntersectionObserver' in window) {
                const produceObserver = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        startProduceCarousel();
                    } else {
                        window.clearInterval(produceTimer);
                    }
                }, { threshold: 0.15 });
                produceObserver.observe(produceCarousel);
            } else {
                startProduceCarousel();
            }
            window.addEventListener('resize', startProduceCarousel);
            produceCarousel.addEventListener('mouseenter', () => window.clearInterval(produceTimer));
            produceCarousel.addEventListener('mouseleave', startProduceCarousel);
            produceCarousel.addEventListener('touchstart', () => window.clearInterval(produceTimer), { passive: true });
            produceCarousel.addEventListener('touchend', startProduceCarousel, { passive: true });
        }

        producePrevious?.addEventListener('click', () => {
            scrollProduce(-1);
            startProduceCarousel();
        });
        produceNext?.addEventListener('click', () => {
            scrollProduce(1);
            startProduceCarousel();
        });

        const heroSlides = <?= $heroSlidesJson ?>;

        const hero = document.querySelector('.home-hero');
        const heroBackdrop = document.getElementById('heroBackdrop');
        const heroCopy = document.getElementById('heroCopy');
        const heroTitle = document.getElementById('heroTitle');
        const heroDescription = document.getElementById('heroDescription');
        const heroDots = document.getElementById('heroDots');
        let heroIndex = 0;
        let heroTimer = null;

        heroSlides.forEach((slide, index) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = `home-slide-dot${index === 0 ? ' is-active' : ''}`;
            dot.setAttribute('aria-label', `Show slide ${index + 1}`);
            dot.setAttribute('aria-current', index === 0 ? 'true' : 'false');
            dot.addEventListener('click', () => {
                showHeroSlide(index);
                startHeroSlider();
            });
            heroDots?.appendChild(dot);
        });

        const updateHeroDots = () => {
            heroDots?.querySelectorAll('.home-slide-dot').forEach((dot, index) => {
                const isActive = index === heroIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
        };

        const showHeroSlide = (index, animate = true) => {
            if (!heroBackdrop || !heroTitle || !heroDescription || !heroSlides.length) return;

            heroIndex = (index + heroSlides.length) % heroSlides.length;
            const slide = heroSlides[heroIndex];
            const shouldAnimate = animate && !prefersReducedMotion;

            if (!shouldAnimate) {
                heroBackdrop.style.backgroundImage = `url('${slide.image}')`;
                heroBackdrop.style.backgroundPosition = slide.position || 'center center';
                heroBackdrop.style.opacity = '1';
                heroTitle.textContent = slide.title;
                heroDescription.textContent = slide.description;
                heroCopy?.classList.remove('is-changing');
                updateHeroDots();
                return;
            }

            heroCopy?.classList.add('is-changing');
            heroBackdrop.style.opacity = '0.25';

            window.setTimeout(() => {
                heroBackdrop.style.backgroundImage = `url('${slide.image}')`;
                heroBackdrop.style.backgroundPosition = slide.position || 'center center';
                heroTitle.textContent = slide.title;
                heroDescription.textContent = slide.description;
                heroBackdrop.style.opacity = '1';
                heroCopy?.classList.remove('is-changing');
                updateHeroDots();
            }, 230);
        };

        const startHeroSlider = () => {
            window.clearInterval(heroTimer);
            if (!prefersReducedMotion && heroSlides.length > 1 && !document.hidden) {
                heroTimer = window.setInterval(() => showHeroSlide(heroIndex + 1), 6500);
            }
        };

        hero?.addEventListener('mouseenter', () => window.clearInterval(heroTimer));
        hero?.addEventListener('mouseleave', startHeroSlider);
        showHeroSlide(0, false);
        startHeroSlider();

        const counters = document.querySelectorAll('.home-stat-value[data-count]');
        const animateCounter = (element) => {
            const target = Number(element.dataset.count || 0);
            if (!Number.isFinite(target)) return;

            if (prefersReducedMotion) {
                element.textContent = target.toLocaleString('en-IN');
                return;
            }

            const duration = 1100;
            const startTime = performance.now();
            const tick = (time) => {
                const progress = Math.min((time - startTime) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                element.textContent = Math.round(target * eased).toLocaleString('en-IN');
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };

        if ('IntersectionObserver' in window) {
            const counterObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.45 });
            counters.forEach((counter) => counterObserver.observe(counter));
        } else {
            counters.forEach(animateCounter);
        }

        const viewerImages = <?= $viewerImagesJson ?>;
        const viewerStage = document.getElementById('viewerStage');
        const viewerDeck = document.getElementById('viewerDeck');
        const viewerCards = Array.from(document.querySelectorAll('.home-viewer-card'));
        const viewerDotContainer = document.getElementById('viewerDots');
        const viewerDots = Array.from(document.querySelectorAll('.home-viewer-dot'));
        const viewerCount = document.getElementById('viewerCount');
        const viewerStatus = document.getElementById('viewerStatus');
        const viewerPlayPause = document.getElementById('viewerPlayPause');
        const viewerProgress = document.getElementById('viewerProgress');
        const lightbox = document.getElementById('imageLightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxClose = document.getElementById('lightboxClose');
        const lightboxZoomOut = document.getElementById('lightboxZoomOut');
        const lightboxZoomReset = document.getElementById('lightboxZoomReset');
        const lightboxZoomIn = document.getElementById('lightboxZoomIn');
        const lightboxZoomLevel = document.getElementById('lightboxZoomLevel');
        let viewerIndex = 0;
        let lightboxZoom = 1;
        let viewerTimer = null;
        let viewerPaused = false;
        let viewerHasStarted = false;
        let previouslyFocusedElement = null;
        let viewerPointerStart = null;
        let viewerSuppressClick = false;

        const setLightboxZoom = (zoom) => {
            if (!lightboxImage) return;
            lightboxZoom = Math.round(Math.min(Math.max(zoom, 1), 4) * 4) / 4;
            lightboxImage.style.transform = `scale(${lightboxZoom})`;
            lightboxImage.classList.toggle('is-zoomed', lightboxZoom > 1);
            lightboxImage.setAttribute('aria-label', lightboxZoom > 1 ? 'Reset image zoom' : 'Zoom image to 2 times');
            if (lightboxZoomLevel) lightboxZoomLevel.value = `${Math.round(lightboxZoom * 100)}%`;
            if (lightboxZoomOut) lightboxZoomOut.disabled = lightboxZoom <= 1;
            if (lightboxZoomReset) lightboxZoomReset.disabled = lightboxZoom <= 1;
            if (lightboxZoomIn) lightboxZoomIn.disabled = lightboxZoom >= 4;
        };

        const keepActiveDotVisible = (dot) => {
            if (!viewerDotContainer || !dot) return;
            const dotLeft = dot.offsetLeft;
            const dotRight = dotLeft + dot.offsetWidth;
            const visibleLeft = viewerDotContainer.scrollLeft;
            const visibleRight = visibleLeft + viewerDotContainer.clientWidth;

            if (dotLeft < visibleLeft) {
                viewerDotContainer.scrollTo({
                    left: Math.max(0, dotLeft - 16),
                    behavior: prefersReducedMotion ? 'auto' : 'smooth'
                });
            } else if (dotRight > visibleRight) {
                viewerDotContainer.scrollTo({
                    left: dotRight - viewerDotContainer.clientWidth + 16,
                    behavior: prefersReducedMotion ? 'auto' : 'smooth'
                });
            }
        };

        const updateViewerPlaybackButton = () => {
            if (!viewerPlayPause) return;
            viewerPlayPause.setAttribute('aria-pressed', viewerPaused ? 'false' : 'true');
            viewerPlayPause.setAttribute('aria-label', viewerPaused ? 'Play slideshow' : 'Pause slideshow');
            viewerPlayPause.title = viewerPaused ? 'Play slideshow' : 'Pause slideshow';
            const icon = viewerPlayPause.querySelector('i');
            if (icon) icon.className = viewerPaused ? 'fa-solid fa-play' : 'fa-solid fa-pause';
        };

        const viewerRelativeSlot = (index) => {
            let offset = index - viewerIndex;
            const total = viewerImages.length;
            if (total > 1 && offset > total / 2) offset -= total;
            if (total > 1 && offset < -total / 2) offset += total;
            return offset;
        };

        const showViewerImage = (index) => {
            if (!viewerImages.length) return;

            viewerIndex = (index + viewerImages.length) % viewerImages.length;
            const image = viewerImages[viewerIndex];
            if (viewerCount) viewerCount.textContent = `${viewerIndex + 1} / ${viewerImages.length}`;
            if (viewerStatus) viewerStatus.textContent = `${image.title}. Image ${viewerIndex + 1} of ${viewerImages.length}.`;

            if (lightboxImage) {
                lightboxImage.src = image.img;
                lightboxImage.alt = image.alt;
            }

            viewerCards.forEach((card, indexValue) => {
                const slot = viewerRelativeSlot(indexValue);
                const isActive = indexValue === viewerIndex;
                card.dataset.slot = Math.abs(slot) <= 2 ? String(slot) : 'hidden';
                card.setAttribute('aria-current', isActive ? 'true' : 'false');
                card.setAttribute('aria-label', `${isActive ? 'Open' : 'Show'} ${viewerImages[indexValue].title}`);
                card.tabIndex = isActive ? 0 : -1;
            });

            viewerDots.forEach((dot, indexValue) => {
                const slot = viewerRelativeSlot(indexValue);
                const isActive = indexValue === viewerIndex;
                dot.hidden = Math.abs(slot) > 2;
                dot.style.order = String(slot + 2);
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
                if (isActive) keepActiveDotVisible(dot);
            });

            [-1, 1].forEach((offset) => {
                const nearbyImage = viewerImages[(viewerIndex + offset + viewerImages.length) % viewerImages.length];
                if (!nearbyImage) return;
                const preloadImage = new Image();
                preloadImage.src = nearbyImage.img;
            });

            setLightboxZoom(1);
        };

        const startViewerSlider = () => {
            window.clearInterval(viewerTimer);
            viewerProgress?.classList.remove('is-running');
            if (!viewerPaused
                && viewerImages.length > 1
                && !document.hidden
                && !lightbox?.classList.contains('is-open')
            ) {
                void viewerProgress?.offsetWidth;
                viewerProgress?.classList.add('is-running');
                viewerTimer = window.setInterval(() => {
                    showViewerImage(viewerIndex + 1);
                    startViewerSlider();
                }, 4600);
            }
            updateViewerPlaybackButton();
        };

        const closeLightbox = () => {
            if (!lightbox) return;
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            setLightboxZoom(1);
            previouslyFocusedElement?.focus();
            if (viewerHasStarted) startViewerSlider();
        };

        const openLightbox = (trigger) => {
            if (!lightbox) return;
            previouslyFocusedElement = trigger || document.activeElement;
            window.clearInterval(viewerTimer);
            viewerProgress?.classList.remove('is-running');
            setLightboxZoom(1);
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            lightboxClose?.focus();
        };

        viewerCards.forEach((card) => {
            card.addEventListener('click', (event) => {
                if (viewerSuppressClick) {
                    event.preventDefault();
                    return;
                }
                const cardIndex = Number(card.dataset.index || 0);
                if (cardIndex === viewerIndex) {
                    openLightbox(card);
                    return;
                }
                showViewerImage(cardIndex);
                startViewerSlider();
            });
        });

        viewerDots.forEach((dot) => {
            dot.addEventListener('click', () => {
                showViewerImage(Number(dot.dataset.index || 0));
                startViewerSlider();
            });
        });

        document.getElementById('viewerPrevious')?.addEventListener('click', () => {
            showViewerImage(viewerIndex - 1);
            startViewerSlider();
        });
        document.getElementById('viewerNext')?.addEventListener('click', () => {
            showViewerImage(viewerIndex + 1);
            startViewerSlider();
        });
        viewerPlayPause?.addEventListener('click', () => {
            viewerPaused = !viewerPaused;
            startViewerSlider();
        });
        document.getElementById('viewerExpand')?.addEventListener('click', (event) => {
            openLightbox(event.currentTarget);
        });

        viewerStage?.addEventListener('pointerdown', (event) => {
            if (event.button !== 0 || event.target.closest('.home-viewer-controls, .home-viewer-toolbar')) return;
            viewerPointerStart = event.clientX;
            viewerSuppressClick = false;
            viewerStage.classList.add('is-dragging');
            window.clearInterval(viewerTimer);
            viewerProgress?.classList.remove('is-running');
            viewerStage.setPointerCapture?.(event.pointerId);
        });

        viewerStage?.addEventListener('pointermove', (event) => {
            if (viewerPointerStart === null || !viewerDeck) return;
            const distance = Math.max(-90, Math.min(90, event.clientX - viewerPointerStart));
            if (Math.abs(distance) > 8) viewerSuppressClick = true;
            viewerDeck.style.setProperty('--viewer-drag', `${distance}px`);
        });

        viewerStage?.addEventListener('pointerup', (event) => {
            if (viewerPointerStart === null) return;
            const distance = event.clientX - viewerPointerStart;
            viewerPointerStart = null;
            viewerStage.classList.remove('is-dragging');
            viewerDeck?.style.setProperty('--viewer-drag', '0px');
            if (Math.abs(distance) >= 48) {
                viewerSuppressClick = true;
                showViewerImage(viewerIndex + (distance < 0 ? 1 : -1));
            }
            startViewerSlider();
            window.setTimeout(() => { viewerSuppressClick = false; }, 0);
        });

        viewerStage?.addEventListener('pointercancel', () => {
            viewerPointerStart = null;
            viewerSuppressClick = false;
            viewerStage.classList.remove('is-dragging');
            viewerDeck?.style.setProperty('--viewer-drag', '0px');
            startViewerSlider();
        });

        viewerStage?.addEventListener('mouseleave', () => {
            if (viewerPointerStart !== null) {
                viewerPointerStart = null;
                viewerSuppressClick = false;
                viewerStage.classList.remove('is-dragging');
                viewerDeck?.style.setProperty('--viewer-drag', '0px');
            }
            if (viewerHasStarted) startViewerSlider();
        });

        viewerStage?.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            showViewerImage(viewerIndex + (event.key === 'ArrowRight' ? 1 : -1));
            startViewerSlider();
        });

        lightboxZoomOut?.addEventListener('click', () => setLightboxZoom(lightboxZoom - 0.25));
        lightboxZoomReset?.addEventListener('click', () => setLightboxZoom(1));
        lightboxZoomIn?.addEventListener('click', () => setLightboxZoom(lightboxZoom + 0.25));
        lightboxImage?.addEventListener('click', () => setLightboxZoom(lightboxZoom > 1 ? 1 : 2));
        lightboxImage?.addEventListener('wheel', (event) => {
            if (!lightbox?.classList.contains('is-open')) return;
            event.preventDefault();
            setLightboxZoom(lightboxZoom + (event.deltaY < 0 ? 0.25 : -0.25));
        }, { passive: false });
        lightboxImage?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            setLightboxZoom(lightboxZoom > 1 ? 1 : 2);
        });
        document.getElementById('lightboxPrevious')?.addEventListener('click', () => showViewerImage(viewerIndex - 1));
        document.getElementById('lightboxNext')?.addEventListener('click', () => showViewerImage(viewerIndex + 1));
        lightboxClose?.addEventListener('click', closeLightbox);
        lightbox?.addEventListener('click', (event) => {
            if (event.target === lightbox) closeLightbox();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                window.clearInterval(viewerTimer);
                window.clearInterval(heroTimer);
                window.clearInterval(produceTimer);
            } else {
                if (!lightbox?.classList.contains('is-open')) {
                    if (viewerHasStarted) startViewerSlider();
                }
                startHeroSlider();
                startProduceCarousel();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!lightbox?.classList.contains('is-open')) return;
            if (event.key === 'Escape') closeLightbox();
            if (event.key === 'ArrowLeft') showViewerImage(viewerIndex - 1);
            if (event.key === 'ArrowRight') showViewerImage(viewerIndex + 1);
        });

        showViewerImage(0);
        updateViewerPlaybackButton();
        if ('IntersectionObserver' in window && viewerStage) {
            const viewerObserver = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    viewerHasStarted = true;
                    startViewerSlider();
                    viewerObserver.disconnect();
                }
            }, { rootMargin: '180px 0px' });
            viewerObserver.observe(viewerStage);
        } else {
            viewerHasStarted = true;
            startViewerSlider();
        }

        const appointmentForm = document.getElementById('appointmentForm');
        const appointmentSubmit = document.getElementById('appointmentSubmit');
        const appointmentSubmitLabel = appointmentSubmit?.querySelector('span');
        const captchaFallback = document.getElementById('captchaFallback');
        const captchaRefresh = document.getElementById('captchaRefresh');
        const captchaQuestion = document.getElementById('captchaQuestion');
        const captchaAnswer = document.getElementById('captcha_answer');
        const captchaChallenge = document.getElementById('captchaChallenge');
        const captchaRefreshStatus = document.getElementById('captchaRefreshStatus');
        const recaptchaToken = document.getElementById('recaptchaToken');
        const recaptchaStatus = document.getElementById('recaptchaStatus');
        const recaptchaEnabled = <?= $recaptchaEnabled ? 'true' : 'false' ?>;
        const recaptchaSiteKey = <?= json_encode(
            $recaptchaEnabled ? $recaptchaSiteKey : '',
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        let captchaFallbackReady = <?= $captchaFallbackRequired ? 'true' : 'false' ?>;
        let recaptchaSubmitReady = false;
        let recaptchaBusy = false;
        const captchaRefreshUrl = <?= json_encode(
            home_public_base_path() . '?refresh_advisory_captcha=1',
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        const setSubmitState = (busy, label) => {
            if (appointmentSubmit) appointmentSubmit.disabled = busy;
            if (appointmentSubmitLabel) appointmentSubmitLabel.textContent = label;
        };

        const refreshCaptchaQuestion = async (focusAnswer = true) => {
            captchaRefresh.disabled = true;
            captchaRefresh.setAttribute('aria-busy', 'true');
            if (captchaRefreshStatus) captchaRefreshStatus.textContent = 'Loading a new question...';

            try {
                const response = await fetch(captchaRefreshUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = response.ok ? await response.json() : null;
                if (!data
                    || typeof data.question !== 'string'
                    || typeof data.challenge !== 'string'
                    || !/^[a-f0-9]{24}$/.test(data.challenge)
                ) {
                    throw new Error('Invalid verification response');
                }

                if (captchaQuestion) captchaQuestion.textContent = data.question;
                if (captchaChallenge) captchaChallenge.value = data.challenge;
                if (captchaAnswer) {
                    captchaAnswer.value = '';
                    captchaAnswer.required = true;
                    if (focusAnswer) captchaAnswer.focus();
                }
                captchaFallbackReady = true;
                if (captchaRefreshStatus) captchaRefreshStatus.textContent = 'New question ready.';
                return true;
            } catch (error) {
                if (captchaRefreshStatus) {
                    captchaRefreshStatus.textContent = 'Could not refresh. You can answer the current question.';
                }
                return false;
            } finally {
                captchaRefresh.disabled = false;
                captchaRefresh.removeAttribute('aria-busy');
            }
        };

        const showCaptchaFallback = async (message) => {
            if (captchaFallback) captchaFallback.hidden = false;
            if (captchaAnswer) captchaAnswer.required = true;
            if (recaptchaStatus) recaptchaStatus.textContent = message;
            await refreshCaptchaQuestion(false);
            captchaAnswer?.focus();
        };

        captchaRefresh?.addEventListener('click', async () => {
            await refreshCaptchaQuestion(true);
        });

        appointmentForm?.addEventListener('submit', (event) => {
            if (!appointmentForm.checkValidity()) {
                event.preventDefault();
                appointmentForm.reportValidity();
                return;
            }

            if (recaptchaEnabled && !recaptchaSubmitReady && !captchaFallbackReady) {
                event.preventDefault();
                if (recaptchaBusy) return;

                recaptchaBusy = true;
                setSubmitState(true, 'Checking security...');
                if (recaptchaStatus) recaptchaStatus.textContent = 'Checking this request securely...';

                if (typeof window.grecaptcha === 'undefined') {
                    recaptchaBusy = false;
                    setSubmitState(false, 'Submit Advisory Request');
                    void showCaptchaFallback('Automatic protection could not load. Please use the quick check below.');
                    return;
                }

                window.grecaptcha.ready(() => {
                    window.grecaptcha.execute(recaptchaSiteKey, { action: 'farmer_advisory' })
                        .then((token) => {
                            if (typeof token !== 'string' || token.length < 20 || token.length > 4096) {
                                throw new Error('Invalid security token');
                            }

                            if (recaptchaToken) recaptchaToken.value = token;
                            recaptchaSubmitReady = true;
                            recaptchaBusy = false;
                            if (recaptchaStatus) recaptchaStatus.textContent = 'Security check complete.';
                            appointmentForm.requestSubmit();
                        })
                        .catch(() => {
                            recaptchaBusy = false;
                            setSubmitState(false, 'Submit Advisory Request');
                            void showCaptchaFallback('Automatic protection was unavailable. Please use the quick check below.');
                        });
                });
                return;
            }

            setSubmitState(true, 'Submitting...');
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
