<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/mailer.php';

auth_no_store_headers();

const LOGIN_OTP_TTL_SECONDS = 600;
const LOGIN_OTP_MAX_ATTEMPTS = 5;
const LOGIN_OTP_RESEND_SECONDS = 60;

function login_otp_ensure_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_otps (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_type VARCHAR(20) NOT NULL,
        account_id INT NOT NULL,
        email VARCHAR(160) NOT NULL,
        otp_hash CHAR(64) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at INT UNSIGNED NOT NULL,
        used_at INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_otp_email (email),
        INDEX idx_login_otp_status (used_at, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function login_otp_find_account(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($account)) {
        $account['type'] = 'admin';
        return $account;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, phone, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($account)) {
        $account['type'] = 'user';
        return $account;
    }

    return null;
}

function login_otp_hash(string $otp, string $email, string $type, int $accountId, string $secret): string
{
    return hash_hmac('sha256', $otp . '|' . $email . '|' . $type . '|' . $accountId, $secret);
}

function login_otp_send_mail(array $account, string $otp): bool
{
    $subject = APP_NAME . ' secure login code';
    $body = "Your one-time login code is: {$otp}\n\n"
        . "This code expires in 10 minutes and can be used only once.\n"
        . "Never share this code with anyone. KVK Kokrajhar staff will not ask for it.\n\n"
        . "If you did not request this code, you can safely ignore this email.";

    return send_site_mail((string) $account['email'], $subject, $body);
}

function login_otp_clear_pending(): void
{
    unset($_SESSION['login_otp_pending']);
}

function login_otp_pending(): ?array
{
    $pending = $_SESSION['login_otp_pending'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $email = auth_normalize_email(auth_safe_session_text($pending['email'] ?? '', 160));
    $expiresAt = (int) ($pending['expires_at'] ?? 0);
    $secret = (string) ($pending['secret'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || $expiresAt <= time()
        || !preg_match('/^[a-f0-9]{64}$/D', $secret)
    ) {
        login_otp_clear_pending();
        return null;
    }

    return $pending;
}

function login_otp_mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') {
        return $email;
    }

    $first = function_exists('mb_substr') ? mb_substr($local, 0, 1, 'UTF-8') : substr($local, 0, 1);
    return $first . str_repeat('*', max(3, min(8, strlen($local) - 1))) . '@' . $domain;
}

function login_recaptcha_ca_bundle(): ?string
{
    $candidates = [
        trim((string) ini_get('curl.cainfo')),
        trim((string) ini_get('openssl.cafile')),
        __DIR__ . '/app/security/cacert.pem',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Validate the visible reCAPTCHA v2 response before checking credentials.
 */
function login_verify_recaptcha_v2(
    string $token,
    string $secretKey,
    string $expectedHost,
    bool $testMode
): bool {
    if ($token === ''
        || strlen($token) > 4096
        || !preg_match('/^[A-Za-z0-9._-]+$/D', $token)
    ) {
        return false;
    }

    $requestBody = http_build_query([
        'secret' => $secretKey,
        'response' => $token,
    ], '', '&', PHP_QUERY_RFC3986);
    $responseBody = false;
    $caBundle = login_recaptcha_ca_bundle();

    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        if ($curl !== false) {
            $curlOptions = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if ($caBundle !== null) {
                $curlOptions[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($curl, $curlOptions);
            $responseBody = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlErrorNumber = curl_errno($curl);
            curl_close($curl);

            if ($httpStatus !== 200) {
                error_log('Login reCAPTCHA connection failed with cURL code ' . $curlErrorNumber . '.');
                $responseBody = false;
            }
        }
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];
        if ($caBundle !== null) {
            $sslOptions['cafile'] = $caBundle;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . 'Content-Length: ' . strlen($requestBody) . "\r\n",
                'content' => $requestBody,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => $sslOptions,
        ]);
        $responseBody = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $context
        );
    }

    if (!is_string($responseBody) || $responseBody === '' || strlen($responseBody) > 16384) {
        error_log('Login reCAPTCHA verification returned no usable response.');
        return false;
    }

    $result = json_decode($responseBody, true);
    if (!is_array($result) || ($result['success'] ?? false) !== true) {
        $errorCodes = is_array($result['error-codes'] ?? null)
            ? implode(',', array_map('strval', $result['error-codes']))
            : 'unknown';
        error_log('Login reCAPTCHA was rejected: ' . $errorCodes . '.');
        return false;
    }

    // Google's official v2 test key uses its own response hostname.
    if ($testMode) {
        return true;
    }

    $responseHost = strtolower(rtrim((string) ($result['hostname'] ?? ''), '.'));
    $timestamp = strtotime((string) ($result['challenge_ts'] ?? ''));
    $isFresh = $timestamp !== false && $timestamp >= (time() - 180) && $timestamp <= (time() + 30);

    return $expectedHost !== ''
        && $responseHost !== ''
        && hash_equals($expectedHost, $responseHost)
        && $isFresh;
}

/**
 * Provide a session-backed human-verification fallback when the host has no
 * reCAPTCHA v2 keys. The answer expires after ten minutes and is single-use.
 *
 * @return array{left:int,right:int}
 */
function login_local_challenge(): array
{
    $challenge = $_SESSION['login_local_challenge'] ?? null;
    $issuedAt = is_array($challenge) ? (int) ($challenge['issued_at'] ?? 0) : 0;

    if (!is_array($challenge) || $issuedAt <= 0 || (time() - $issuedAt) > 600) {
        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $challenge = [
            'left' => $left,
            'right' => $right,
            'answer' => $left + $right,
            'issued_at' => time(),
        ];
        $_SESSION['login_local_challenge'] = $challenge;
    }

    return [
        'left' => (int) $challenge['left'],
        'right' => (int) $challenge['right'],
    ];
}

function login_verify_local_challenge(mixed $answer): bool
{
    $challenge = $_SESSION['login_local_challenge'] ?? null;
    unset($_SESSION['login_local_challenge']);

    if (!is_array($challenge)
        || !is_scalar($answer)
        || !preg_match('/^\d{1,2}$/D', trim((string) $answer))
    ) {
        return false;
    }

    $issuedAt = (int) ($challenge['issued_at'] ?? 0);
    $expected = (string) ((int) ($challenge['answer'] ?? -1));

    return $issuedAt > 0
        && (time() - $issuedAt) <= 600
        && hash_equals($expected, trim((string) $answer));
}

if (admin_logged_in()) {
    header('Location: admin/dashboard.php');
    exit;
}

if (user_logged_in()) {
    header('Location: user-dashboard.php');
    exit;
}

$requestHost = strtolower((string) parse_url(
    'http://' . trim((string) ($_SERVER['HTTP_HOST'] ?? '')),
    PHP_URL_HOST
));
$recaptchaV2TestMode = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
$recaptchaV2SiteKey = defined('RECAPTCHA_V2_SITE_KEY') ? trim((string) RECAPTCHA_V2_SITE_KEY) : '';
$recaptchaV2SecretKey = defined('RECAPTCHA_V2_SECRET_KEY') ? trim((string) RECAPTCHA_V2_SECRET_KEY) : '';

if ($recaptchaV2TestMode && ($recaptchaV2SiteKey === '' || $recaptchaV2SecretKey === '')) {
    // Official Google v2 test keys: always pass and must never be used in production.
    $recaptchaV2SiteKey = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
    $recaptchaV2SecretKey = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';
}

$recaptchaV2Enabled = preg_match('/^[A-Za-z0-9_-]{20,256}$/D', $recaptchaV2SiteKey) === 1
    && preg_match('/^[A-Za-z0-9_-]{20,256}$/D', $recaptchaV2SecretKey) === 1;

if ($recaptchaV2Enabled) {
    unset($_SESSION['login_local_challenge']);
}

$error = '';
$notice = auth_safe_session_text($_SESSION['login_otp_notice'] ?? '', 300);
unset($_SESSION['login_otp_notice']);
$email = '';
$pendingOtp = login_otp_pending();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'send_otp');

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (!auth_request_is_same_origin()) {
        $error = 'This request could not be verified. Please try again.';
    } elseif ($action === 'restart') {
        login_otp_clear_pending();
        csrf_rotate();
        header('Location: login.php', true, 303);
        exit;
    } elseif ($action === 'send_otp') {
        $email = auth_normalize_email(auth_safe_session_text($_POST['email'] ?? '', 160));
        $recaptchaResponse = trim((string) ($_POST['g-recaptcha-response'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            $error = 'Please enter a valid registered email address.';
        } elseif ($recaptchaV2Enabled && !login_verify_recaptcha_v2(
            $recaptchaResponse,
            $recaptchaV2SecretKey,
            $requestHost,
            $recaptchaV2TestMode
        )) {
            $error = 'Please confirm that you are not a robot before requesting a code.';
        } elseif (!$recaptchaV2Enabled && !login_verify_local_challenge($_POST['login_challenge'] ?? null)) {
            $error = 'Please answer the security question before requesting a code.';
        } else {
            $emailKey = hash('sha256', $email);
            $lastRequests = is_array($_SESSION['login_otp_last_requests'] ?? null)
                ? $_SESSION['login_otp_last_requests']
                : [];
            $secondsRemaining = LOGIN_OTP_RESEND_SECONDS - (time() - (int) ($lastRequests[$emailKey] ?? 0));

            if ($secondsRemaining > 0) {
                $error = 'Please wait ' . $secondsRemaining . ' seconds before requesting another code.';
            } elseif (auth_rate_limited('login_otp_send', $email)) {
                $error = 'Too many code requests. Please wait 15 minutes and try again.';
            } else {
                $pdo = db();
                if (!$pdo) {
                    $error = 'Secure login is temporarily unavailable. Please try again later.';
                } else {
                    try {
                        login_otp_ensure_table($pdo);
                        $stmt = $pdo->prepare('DELETE FROM login_otps WHERE expires_at < ? LIMIT 500');
                        $stmt->execute([time() - 86400]);
                        $account = login_otp_find_account($pdo, $email);
                        $secret = bin2hex(random_bytes(32));
                        $expiresAt = time() + LOGIN_OTP_TTL_SECONDS;
                        $recordId = 0;

                        if (is_array($account)) {
                            $email = auth_normalize_email((string) $account['email']);
                            $otp = (string) random_int(100000, 999999);
                            $otpHash = login_otp_hash(
                                $otp,
                                $email,
                                (string) $account['type'],
                                (int) $account['id'],
                                $secret
                            );

                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare(
                                'UPDATE login_otps SET used_at = ? WHERE email = ? AND used_at IS NULL'
                            );
                            $stmt->execute([time(), $email]);
                            $stmt = $pdo->prepare(
                                'INSERT INTO login_otps (account_type, account_id, email, otp_hash, expires_at) '
                                . 'VALUES (?, ?, ?, ?, ?)'
                            );
                            $stmt->execute([
                                $account['type'],
                                (int) $account['id'],
                                $email,
                                $otpHash,
                                $expiresAt,
                            ]);
                            $recordId = (int) $pdo->lastInsertId();
                            $pdo->commit();

                            if (!login_otp_send_mail($account, $otp)) {
                                error_log('Login OTP email delivery failed.');
                                $stmt = $pdo->prepare('UPDATE login_otps SET used_at = ? WHERE id = ?');
                                $stmt->execute([time(), $recordId]);
                                $recordId = 0;
                            }
                        } else {
                            // Reduce timing-based account discovery without sending mail to unknown addresses.
                            login_otp_hash('000000', $email, 'none', 0, $secret);
                            usleep(random_int(400000, 800000));
                        }

                        $_SESSION['login_otp_pending'] = [
                            'record_id' => $recordId,
                            'email' => $email,
                            'secret' => $secret,
                            'expires_at' => $expiresAt,
                            'attempts' => 0,
                        ];
                        $lastRequests[$emailKey] = time();
                        $_SESSION['login_otp_last_requests'] = array_slice($lastRequests, -10, null, true);
                        auth_record_login_failure('login_otp_send', $email);
                        csrf_rotate();
                        $_SESSION['login_otp_notice'] = 'If this email belongs to an active account, a 6-digit code has been sent. It expires in 10 minutes.';
                        header('Location: login.php', true, 303);
                        exit;
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Unable to create login OTP.');
                        $error = 'Secure login is temporarily unavailable. Please try again later.';
                    }
                }
            }
        }
    } elseif ($action === 'verify_otp') {
        $pendingOtp = login_otp_pending();
        $otp = trim((string) ($_POST['otp'] ?? ''));

        if (!$pendingOtp) {
            $error = 'Your login code has expired. Please request a new code.';
        } elseif (!preg_match('/^\d{6}$/D', $otp)) {
            $error = 'Enter the 6-digit code from your email.';
        } else {
            $email = auth_normalize_email((string) $pendingOtp['email']);
            $sessionAttempts = (int) ($pendingOtp['attempts'] ?? 0);

            if ($sessionAttempts >= LOGIN_OTP_MAX_ATTEMPTS
                || auth_rate_limited('login_otp_verify', $email)
            ) {
                login_otp_clear_pending();
                $error = 'Too many incorrect attempts. Please wait and request a new code.';
            } else {
                $pdo = db();
                $verifiedAccount = null;
                $invalidCode = true;

                try {
                    if ($pdo && (int) ($pendingOtp['record_id'] ?? 0) > 0) {
                        login_otp_ensure_table($pdo);
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'SELECT id, account_type, account_id, email, otp_hash, attempts, expires_at, used_at '
                            . 'FROM login_otps WHERE id = ? FOR UPDATE'
                        );
                        $stmt->execute([(int) $pendingOtp['record_id']]);
                        $record = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (is_array($record)
                            && $record['used_at'] === null
                            && (int) $record['expires_at'] >= time()
                            && (int) $record['attempts'] < LOGIN_OTP_MAX_ATTEMPTS
                            && hash_equals((string) $record['email'], $email)
                        ) {
                            $expectedHash = login_otp_hash(
                                $otp,
                                $email,
                                (string) $record['account_type'],
                                (int) $record['account_id'],
                                (string) $pendingOtp['secret']
                            );

                            if (hash_equals((string) $record['otp_hash'], $expectedHash)) {
                                $type = (string) $record['account_type'];
                                if ($type === 'admin') {
                                    $stmt = $pdo->prepare(
                                        'SELECT id, name, email FROM admin_users '
                                        . 'WHERE id = ? AND email = ? AND is_active = 1 LIMIT 1'
                                    );
                                } else {
                                    $stmt = $pdo->prepare(
                                        'SELECT id, name, email, phone, role FROM users '
                                        . 'WHERE id = ? AND email = ? AND is_active = 1 LIMIT 1'
                                    );
                                }
                                $stmt->execute([(int) $record['account_id'], $email]);
                                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                                if (is_array($account) && in_array($type, ['admin', 'user'], true)) {
                                    $stmt = $pdo->prepare(
                                        'UPDATE login_otps SET used_at = ? WHERE id = ? AND used_at IS NULL'
                                    );
                                    $stmt->execute([time(), (int) $record['id']]);
                                    if ($stmt->rowCount() === 1) {
                                        $verifiedAccount = ['type' => $type, 'data' => $account];
                                        $invalidCode = false;
                                    }
                                }
                            }

                            if ($invalidCode) {
                                $newAttempts = (int) $record['attempts'] + 1;
                                $usedAt = $newAttempts >= LOGIN_OTP_MAX_ATTEMPTS ? time() : null;
                                $stmt = $pdo->prepare(
                                    'UPDATE login_otps SET attempts = ?, used_at = ? WHERE id = ?'
                                );
                                $stmt->execute([$newAttempts, $usedAt, (int) $record['id']]);
                            }
                        }
                        $pdo->commit();
                    } else {
                        // Perform a constant-time comparison for non-account requests too.
                        hash_equals(str_repeat('0', 64), hash('sha256', $otp));
                    }
                } catch (Throwable $exception) {
                    if ($pdo && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Login OTP verification failed.');
                }

                if (is_array($verifiedAccount)) {
                    login_otp_clear_pending();
                    auth_clear_login_failures('login_otp_verify', $email);
                    auth_clear_login_failures('admin', $email);
                    auth_clear_login_failures('user', $email);

                    if ($verifiedAccount['type'] === 'admin') {
                        auth_establish_admin_session($verifiedAccount['data']);
                        header('Location: admin/dashboard.php', true, 303);
                    } else {
                        auth_establish_user_session($verifiedAccount['data']);
                        header('Location: user-dashboard.php', true, 303);
                    }
                    exit;
                }

                auth_record_login_failure('login_otp_verify', $email);
                $_SESSION['login_otp_pending']['attempts'] = $sessionAttempts + 1;
                if (($sessionAttempts + 1) >= LOGIN_OTP_MAX_ATTEMPTS) {
                    login_otp_clear_pending();
                    $error = 'Too many incorrect attempts. Please wait and request a new code.';
                } else {
                    $error = 'The code is incorrect or has expired. Please try again.';
                }
            }
        }
    } else {
        $error = 'Invalid login request. Please try again.';
    }
}

$pendingOtp = login_otp_pending();

$page_title = 'Login | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .login-experience {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding-block: 64px;
    }

    .login-experience::before,
    .login-experience::after {
        content: '';
        position: absolute;
        z-index: -1;
        border-radius: 50%;
        pointer-events: none;
    }

    .login-experience::before {
        width: 360px;
        height: 360px;
        top: -190px;
        right: -110px;
        border: 58px solid rgba(255, 210, 92, .13);
    }

    .login-experience::after {
        width: 260px;
        height: 260px;
        bottom: -150px;
        left: -90px;
        background: rgba(255, 255, 255, .06);
        filter: blur(2px);
    }

    .login-experience .auth-shell {
        width: min(1140px, calc(100% - 32px));
        grid-template-columns: minmax(0, 1.12fr) minmax(400px, 470px);
        gap: 28px;
    }

    .login-experience .auth-panel {
        min-height: 600px;
        padding: 48px;
        border-radius: 26px;
        box-shadow: 0 30px 80px rgba(3, 22, 13, .34);
    }

    .login-experience .auth-panel::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(6, 28, 18, .22), transparent 55%);
        pointer-events: none;
    }

    .login-experience .auth-panel > * {
        z-index: 2;
    }

    .login-portal-kicker {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        width: fit-content;
        margin-bottom: 16px;
        padding: 8px 12px;
        border: 1px solid rgba(255, 255, 255, .25);
        border-radius: 999px;
        color: #fff;
        background: rgba(6, 35, 21, .38);
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        backdrop-filter: blur(10px);
    }

    .login-portal-kicker__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #f4c54f;
        box-shadow: 0 0 0 5px rgba(244, 197, 79, .16);
    }

    .login-experience .auth-panel h1 {
        max-width: 650px;
        font-size: clamp(38px, 4.4vw, 58px);
        letter-spacing: -.025em;
    }

    .login-experience .auth-feature-list span {
        border-radius: 999px;
        padding-inline: 14px;
    }

    .login-panel-note {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 22px !important;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, .2);
        color: rgba(255, 255, 255, .88) !important;
        font-size: 13px !important;
        font-weight: 800;
    }

    .login-experience .unified-login-card {
        position: relative;
        overflow: hidden;
        padding: 38px;
        border: 1px solid rgba(255, 255, 255, .58);
        border-radius: 26px;
        box-shadow: 0 30px 80px rgba(3, 22, 13, .28);
    }

    .login-experience .unified-login-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--green-700), var(--leaf), #f0b83c);
    }

    .login-card__utility {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: -4px 0 22px;
        font-size: 12px;
        font-weight: 900;
    }

    .login-card__utility a,
    .login-card__utility span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .login-card__utility a {
        color: var(--green-900);
        text-decoration: none;
    }

    .login-card__utility a:hover,
    .login-card__utility a:focus-visible {
        color: var(--green-700);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .login-card__utility span {
        padding: 7px 10px;
        border: 1px solid #dbe8df;
        border-radius: 999px;
        color: var(--green-700);
        background: #f4f9f5;
    }

    .login-progress {
        display: grid;
        grid-template-columns: 1fr 34px 1fr;
        align-items: center;
        gap: 8px;
        margin-bottom: 26px;
        color: #7c8982;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .login-progress__step {
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
    }

    .login-progress__step:last-child {
        justify-content: flex-end;
    }

    .login-progress__step b {
        display: grid;
        place-items: center;
        width: 27px;
        height: 27px;
        border: 1px solid #d8e1dc;
        border-radius: 50%;
        background: #f5f8f6;
        color: #75827b;
        font-size: 12px;
    }

    .login-progress__step.is-active,
    .login-progress__step.is-complete {
        color: var(--green-900);
    }

    .login-progress__step.is-active b,
    .login-progress__step.is-complete b {
        border-color: var(--green-700);
        color: #fff;
        background: var(--green-700);
        box-shadow: 0 0 0 4px rgba(37, 109, 59, .1);
    }

    .login-progress__line {
        height: 2px;
        border-radius: 999px;
        background: #dce5df;
    }

    .login-progress__line.is-complete {
        background: var(--green-700);
    }

    .login-experience .auth-card__header {
        margin-bottom: 12px;
    }

    .login-experience .auth-lock {
        border-radius: 14px;
        background: linear-gradient(145deg, var(--green-900), var(--leaf));
    }

    .login-experience .auth-card__intro {
        margin-bottom: 18px;
    }

    .login-field__help {
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }

    .login-experience .login-input {
        min-height: 52px;
        border-color: #cbd9d0;
        border-radius: 12px;
        box-shadow: 0 1px 0 rgba(16, 54, 32, .03);
    }

    .login-experience .login-input:focus-within {
        border-color: var(--green-700);
        box-shadow: 0 0 0 4px rgba(37, 109, 59, .12), 0 8px 22px rgba(21, 70, 41, .08);
    }

    .otp-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .otp-label-row small {
        padding: 4px 8px;
        border: 1px solid #dce8e0;
        border-radius: 999px;
        color: var(--green-700);
        background: #f3f8f5;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
    }

    .otp-code-field {
        position: relative;
        display: block;
        margin-top: 3px;
        border-radius: 14px;
    }

    .otp-code-input {
        position: absolute;
        z-index: 2;
        inset: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: inherit;
        opacity: .01;
        color: transparent;
        background: transparent;
        caret-color: transparent;
        cursor: text;
    }

    .otp-code-input:focus {
        outline: 0;
    }

    .otp-code-cells {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 9px;
    }

    .otp-code-cell {
        display: grid;
        min-width: 0;
        min-height: 56px;
        place-items: center;
        border: 1.5px solid #cbd9d0;
        border-radius: 13px;
        color: var(--green-900);
        background: linear-gradient(180deg, #fff 0%, #f8fbf9 100%);
        box-shadow: 0 3px 10px rgba(16, 54, 32, .05);
        font-size: 24px;
        font-weight: 900;
        line-height: 1;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .otp-code-field:focus-within .otp-code-cell {
        border-color: #a9c8b3;
    }

    .otp-code-cell.is-filled {
        border-color: #72a783;
        background: #eef8f1;
        box-shadow: inset 0 0 0 1px rgba(37, 109, 59, .08);
    }

    .otp-code-cell.is-active {
        border-color: var(--green-700);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(37, 109, 59, .12), 0 8px 20px rgba(21, 70, 41, .1);
        transform: translateY(-2px);
    }

    .otp-code-field.is-invalid .otp-code-cell {
        border-color: #d35f5f;
        background: #fff8f8;
    }

    .otp-code-help {
        display: flex;
        align-items: center;
        gap: 7px;
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }

    .otp-code-help i {
        color: var(--green-700);
    }

    .login-experience .login-submit {
        min-height: 50px;
        border-radius: 12px;
        box-shadow: 0 12px 26px rgba(27, 104, 56, .17);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .login-experience .login-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 32px rgba(27, 104, 56, .23);
    }

    .login-experience .login-submit:disabled {
        cursor: wait;
        opacity: .78;
        transform: none;
        box-shadow: none;
    }

    .login-assurance-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 7px;
        margin-top: 15px;
    }

    .login-assurance-grid span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 9px;
        border: 1px solid #e0ebe4;
        border-radius: 999px;
        color: #66766c;
        background: #f7faf8;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.2;
    }

    .login-assurance-grid i {
        color: var(--green-700);
    }

    .login-experience .auth-note {
        margin: 18px 0 0;
        padding-top: 16px;
        border-top: 1px solid #e5ece8;
        text-align: center;
    }

    @media (max-width: 900px) {
        .login-experience {
            min-height: 0;
            padding-block: 38px;
        }

        .login-experience .auth-shell {
            width: min(560px, calc(100% - 28px));
            grid-template-columns: minmax(0, 1fr);
            gap: 20px;
        }

        .login-experience .unified-login-card {
            order: 1;
        }

        .login-experience .auth-panel {
            order: 2;
            min-height: 280px;
            padding: 34px;
        }

        .login-experience .auth-panel h1 {
            font-size: clamp(32px, 8vw, 44px);
        }

        .login-panel-note {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .login-experience {
            padding-block: 20px;
        }

        .login-experience .auth-shell {
            width: calc(100% - 20px);
            gap: 14px;
        }

        .login-experience .unified-login-card {
            padding: 24px 20px;
            border-radius: 20px;
        }

        .login-card__utility {
            margin-bottom: 18px;
        }

        .login-progress {
            margin-bottom: 20px;
            font-size: 10px;
        }

        .login-progress__step {
            gap: 6px;
        }

        .login-progress__step b {
            width: 24px;
            height: 24px;
        }

        .otp-code-cells {
            gap: 6px;
        }

        .otp-code-cell {
            min-height: 48px;
            border-radius: 10px;
            font-size: 21px;
        }

        .login-experience .auth-panel {
            min-height: 230px;
            padding: 26px;
            border-radius: 20px;
        }

        .login-experience .auth-panel h1 {
            font-size: 30px;
        }

        .login-experience .auth-panel p:not(.eyebrow) {
            display: none;
        }

        .login-experience .auth-feature-list {
            margin-top: 16px;
        }

        .login-experience .auth-feature-list span {
            font-size: 11px;
        }

        .login-assurance-grid {
            gap: 6px;
        }

        .login-assurance-grid span {
            padding: 6px 8px;
            font-size: 10px;
        }
    }
</style>
<section class="auth-section auth-section--unified login-experience">
    <div class="auth-shell">
        <div class="auth-panel" aria-label="Login information">
            <div class="login-portal-kicker"><span class="login-portal-kicker__dot" aria-hidden="true"></span> KVK staff workspace</div>
            <h1>KVK Kokrajhar Admin & Staff Portal</h1>
            <p>One secure doorway for website administration, office coordination, trainings, notices, enquiries, and staff services.</p>
            <div class="auth-feature-list" aria-label="Portal security features">
                <span><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Role-based access</span>
                <span><i class="fa-solid fa-clock" aria-hidden="true"></i> Secure sessions</span>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Login protection</span>
            </div>
            <p class="login-panel-note"><i class="fa-solid fa-key" aria-hidden="true"></i> Password-free access with a single-use email code</p>
        </div>

        <form class="login-card auth-card unified-login-card" method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="login-card__utility">
                <a href="index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to website</a>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Official portal</span>
            </div>
            <div class="login-progress" aria-label="Login progress">
                <span class="login-progress__step <?= $pendingOtp ? 'is-complete' : 'is-active' ?>" aria-current="<?= $pendingOtp ? 'false' : 'step' ?>">
                    <b aria-hidden="true"><?= $pendingOtp ? '<i class="fa-solid fa-check"></i>' : '1' ?></b>
                    Email
                </span>
                <span class="login-progress__line <?= $pendingOtp ? 'is-complete' : '' ?>" aria-hidden="true"></span>
                <span class="login-progress__step <?= $pendingOtp ? 'is-active' : '' ?>" aria-current="<?= $pendingOtp ? 'step' : 'false' ?>">
                    <b aria-hidden="true">2</b>
                    Code
                </span>
            </div>
            <div class="auth-card__header">
                <span class="auth-lock" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                <div>
                    <p class="eyebrow"><?= $pendingOtp ? 'Code verification' : 'Secure login' ?></p>
                    <h2><?= $pendingOtp ? 'Enter your login code' : 'Welcome back' ?></h2>
                </div>
            </div>
            <p class="auth-card__intro" id="login-help">
                <?= $pendingOtp
                    ? 'Enter the one-time code sent to ' . e(login_otp_mask_email((string) $pendingOtp['email'])) . '.'
                    : 'Enter your registered email address to receive a secure one-time login code.' ?>
            </p>
            <?php if ($notice): ?>
                <div class="success" role="status"><?= e($notice) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert login-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$pendingOtp): ?>
                <input type="hidden" name="action" value="send_otp">
                <label class="login-field" for="login-email">
                    <span>Registered email address</span>
                    <span class="login-input">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <input id="login-email" type="email" name="email" autocomplete="email" placeholder="name@example.com" value="<?= e($email) ?>" maxlength="160" aria-describedby="login-help" required autofocus>
                    </span>
                    <small class="login-field__help">Use the email address registered with the KVK office.</small>
                </label>

                <?php if ($recaptchaV2Enabled): ?>
                    <div class="login-recaptcha" aria-label="Human verification">
                        <div class="g-recaptcha" data-sitekey="<?= e($recaptchaV2SiteKey) ?>"></div>
                    </div>
                <?php else: ?>
                    <?php $localChallenge = login_local_challenge(); ?>
                    <label class="login-field login-security-check" for="login-challenge">
                        <span>Security check</span>
                        <small>What is <?= $localChallenge['left'] ?> + <?= $localChallenge['right'] ?>?</small>
                        <span class="login-input">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <input id="login-challenge" type="text" name="login_challenge" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" placeholder="Enter the answer" required>
                        </span>
                    </label>
                <?php endif; ?>

                <button class="btn btn--primary login-submit" type="submit" data-login-primary data-loading-label="Sending code...">
                    <span>Send login code</span>
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            <?php else: ?>
                <input type="hidden" name="action" value="verify_otp">
                <label class="login-field" for="login-otp">
                    <span class="otp-label-row">
                        <span>6-digit login code</span>
                        <small><span data-otp-count>0</span>/6 entered</small>
                    </span>
                    <span class="otp-code-field" data-otp-field>
                        <input class="otp-code-input" id="login-otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" aria-label="6-digit login code" aria-describedby="login-help otp-code-help" required autofocus>
                        <span class="otp-code-cells" aria-hidden="true">
                            <span class="otp-code-cell" data-otp-cell></span>
                            <span class="otp-code-cell" data-otp-cell></span>
                            <span class="otp-code-cell" data-otp-cell></span>
                            <span class="otp-code-cell" data-otp-cell></span>
                            <span class="otp-code-cell" data-otp-cell></span>
                            <span class="otp-code-cell" data-otp-cell></span>
                        </span>
                    </span>
                    <small class="otp-code-help" id="otp-code-help"><i class="fa-regular fa-clipboard" aria-hidden="true"></i> Type or paste the code from your email.</small>
                </label>

                <button class="btn btn--primary login-submit" type="submit" data-login-primary data-loading-label="Verifying code...">
                    <span>Verify and login</span>
                    <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                </button>

                <button class="btn btn--light login-submit" type="submit" name="action" value="restart" formnovalidate>
                    <span>Resend or change email</span>
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                </button>
            <?php endif; ?>

            <div class="login-assurance-grid" aria-label="Login security assurances">
                <span><i class="fa-regular fa-clock" aria-hidden="true"></i> 10-minute expiry</span>
                <span><i class="fa-solid fa-fingerprint" aria-hidden="true"></i> One-time use</span>
                <span><i class="fa-solid fa-shield" aria-hidden="true"></i> Attempt protected</span>
            </div>

            <p class="auth-note">New office staff? <a href="register.php">Request portal access</a></p>
        </form>
    </div>
</section>
<?php if ($recaptchaV2Enabled): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
<script>
(() => {
    const form = document.querySelector('.unified-login-card');
    const primaryButton = form?.querySelector('[data-login-primary]');
    const otpInput = form?.querySelector('#login-otp');
    const otpField = form?.querySelector('[data-otp-field]');
    const otpCells = [...(form?.querySelectorAll('[data-otp-cell]') || [])];
    const otpCount = form?.querySelector('[data-otp-count]');

    const syncOtpCells = (showActive = document.activeElement === otpInput) => {
        if (!otpInput) {
            return;
        }

        otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
        const digits = otpInput.value.split('');
        const activeIndex = Math.min(digits.length, otpCells.length - 1);

        otpCells.forEach((cell, index) => {
            cell.textContent = digits[index] || '';
            cell.classList.toggle('is-filled', index < digits.length);
            cell.classList.toggle('is-active', showActive && index === activeIndex);
        });

        if (otpCount) {
            otpCount.textContent = String(digits.length);
        }
        otpInput.setCustomValidity(digits.length === otpCells.length ? '' : 'Enter all 6 digits from the email.');
        otpField?.classList.remove('is-invalid');
    };

    otpInput?.addEventListener('input', () => syncOtpCells(true));
    otpInput?.addEventListener('focus', () => syncOtpCells(true));
    otpInput?.addEventListener('blur', () => syncOtpCells(false));
    otpInput?.addEventListener('invalid', () => {
        otpField?.classList.add('is-invalid');
    });
    syncOtpCells();

    form?.addEventListener('submit', (event) => {
        if (!primaryButton || event.submitter !== primaryButton || !form.checkValidity()) {
            return;
        }

        primaryButton.disabled = true;
        primaryButton.setAttribute('aria-busy', 'true');
        const label = primaryButton.querySelector('span');
        const icon = primaryButton.querySelector('i');
        if (label) {
            label.textContent = primaryButton.dataset.loadingLabel || 'Please wait...';
        }
        if (icon) {
            icon.className = 'fa-solid fa-circle-notch fa-spin';
        }
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
