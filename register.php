<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/mailer.php';

const REGISTRATION_OTP_TTL_SECONDS = 600;
const REGISTRATION_OTP_MAX_ATTEMPTS = 5;

function registration_clear_pending(): void
{
    unset($_SESSION['registration_pending']);
}

function registration_normalize_mobile(mixed $value): ?string
{
    $raw = trim(auth_safe_session_text($value, 30));
    if ($raw === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 10 && preg_match('/^[6-9]\d{9}$/D', $digits)) {
        $digits = '91' . $digits;
    }

    return preg_match('/^91[6-9]\d{9}$/D', $digits) ? '+' . $digits : null;
}

function registration_mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return ($local === '' || $domain === '')
        ? $email
        : substr($local, 0, 1) . str_repeat('*', max(3, min(8, strlen($local) - 1))) . '@' . $domain;
}

function registration_code_hash(string $code, string $channel, string $email, string $mobile, string $secret): string
{
    return hash_hmac('sha256', $code . '|' . $channel . '|' . $email . '|' . $mobile, $secret);
}

function registration_pending(): ?array
{
    $pending = $_SESSION['registration_pending'] ?? null;
    if (!is_array($pending)
        || (int) ($pending['expires_at'] ?? 0) <= time()
        || !preg_match('/^[a-f0-9]{64}$/D', (string) ($pending['secret'] ?? ''))
        || !preg_match('/^[a-f0-9]{64}$/D', (string) ($pending['email_otp_hash'] ?? ''))
        || !filter_var((string) ($pending['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        || registration_normalize_mobile($pending['phone'] ?? '') !== (string) ($pending['phone'] ?? '')
        || !is_string($pending['password_hash'] ?? null)
        || strlen((string) $pending['password_hash']) > 255
    ) {
        registration_clear_pending();
        return null;
    }
    return $pending;
}

function registration_validate_details(array $data, PDO $pdo): array
{
    $name = trim(strip_tags(auth_safe_session_text($data['name'] ?? '', 120)));
    $email = auth_normalize_email(auth_safe_session_text($data['email'] ?? '', 254));
    $phone = registration_normalize_mobile($data['phone'] ?? '');
    $password = is_scalar($data['password'] ?? null) ? (string) $data['password'] : '';
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if ($nameLength < 2 || $nameLength > 120) {
        return [false, 'Please enter a valid full name.', null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        return [false, 'Please enter a valid email address.', null];
    }
    if ($phone === null) {
        return [false, 'Enter a valid 10-digit Indian mobile number.', null];
    }
    if (strlen($password) < 10 || strlen($password) > 128) {
        return [false, 'Password must be between 10 and 128 characters.', null];
    }

    $stmt = $pdo->prepare(
        'SELECT email FROM users WHERE email = ? '
        . 'UNION ALL SELECT email FROM admin_users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email, $email]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        return [false, 'This email is already registered.', null];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        return [false, 'Unable to prepare registration at this time.', null];
    }

    return [true, '', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'role' => 'office_staff',
        'password_hash' => $passwordHash,
    ]];
}

function registration_send_email_code(string $email, string $code): bool
{
    return send_site_mail(
        $email,
        APP_NAME . ' registration verification code',
        "Your email verification code is: {$code}\n\nThis code expires in 10 minutes and can be used only once.\nDo not share it with anyone."
    );
}

function registration_create_verified_account(PDO $pdo, array $pending): array
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([(string) $pending['email']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            return [false, 'This email is already registered.'];
        }
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([(string) $pending['email']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            return [false, 'This email is already registered.'];
        }

        $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, role, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->execute([
            (string) $pending['name'],
            (string) $pending['email'],
            (string) $pending['phone'],
            'office_staff',
            (string) $pending['password_hash'],
        ]);
        $pdo->commit();
        return [true, 'Email verified. Your account request is awaiting admin approval.'];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string) $exception->getCode() === '23000') {
            return [false, 'This email is already registered.'];
        }
        error_log('Verified registration database operation failed.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Verified registration failed.');
    }
    return [false, 'Unable to create the account request at this time.'];
}

$message = '';
$ok = false;
$notice = is_string($_SESSION['registration_notice'] ?? null) ? $_SESSION['registration_notice'] : '';
unset($_SESSION['registration_notice']);
$pending = registration_pending();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'send_codes');
    if (!csrf_valid($_POST['csrf_token'] ?? null) || !auth_request_is_same_origin()) {
        $message = 'Your session expired. Please refresh the page and try again.';
    } elseif ($action === 'restart') {
        registration_clear_pending();
        csrf_rotate();
        header('Location: register.php', true, 303);
        exit;
    } elseif ($action === 'send_codes') {
        $pdo = db();
        if (!$pdo) {
            $message = 'Registration is temporarily unavailable. Please try again later.';
        } else {
            try {
                [$valid, $validationMessage, $details] = registration_validate_details($_POST, $pdo);
                if (!$valid || !is_array($details)) {
                    $message = $validationMessage;
                } elseif (auth_rate_limited('register_otp_send', (string) $details['email'])) {
                    $message = 'Too many verification requests. Please wait 15 minutes and try again.';
                } else {
                    $emailCode = (string) random_int(100000, 999999);
                    $secret = bin2hex(random_bytes(32));
                    if (!registration_send_email_code((string) $details['email'], $emailCode)) {
                        $message = 'Unable to send the email verification code. Please try again.';
                    } else {
                        $_SESSION['registration_pending'] = $details + [
                            'secret' => $secret,
                            'email_otp_hash' => registration_code_hash($emailCode, 'email', (string) $details['email'], (string) $details['phone'], $secret),
                            'expires_at' => time() + REGISTRATION_OTP_TTL_SECONDS,
                            'attempts' => 0,
                        ];
                        auth_record_login_failure('register_otp_send', (string) $details['email']);
                        csrf_rotate();
                        $_SESSION['registration_notice'] = 'A verification code was sent to your email. Enter it within 10 minutes.';
                        header('Location: register.php', true, 303);
                        exit;
                    }
                }
            } catch (Throwable $exception) {
                error_log('Unable to start verified registration.');
                $message = 'Registration is temporarily unavailable. Please try again later.';
            }
        }
    } elseif ($action === 'verify_codes') {
        $pending = registration_pending();
        $emailCode = preg_replace('/\D+/', '', (string) ($_POST['email_otp'] ?? '')) ?? '';
        if (!$pending) {
            $message = 'Your email verification code has expired. Please restart registration.';
        } elseif (!preg_match('/^\d{6}$/D', $emailCode)) {
            $message = 'Enter the 6-digit verification code from your email.';
        } elseif ((int) ($pending['attempts'] ?? 0) >= REGISTRATION_OTP_MAX_ATTEMPTS
            || auth_rate_limited('register_otp_verify', (string) $pending['email'])
        ) {
            registration_clear_pending();
            $message = 'Too many incorrect attempts. Please restart registration.';
        } else {
            $emailValid = hash_equals((string) $pending['email_otp_hash'], registration_code_hash($emailCode, 'email', (string) $pending['email'], (string) $pending['phone'], (string) $pending['secret']));
            if (!$emailValid) {
                $attempts = (int) ($pending['attempts'] ?? 0) + 1;
                $_SESSION['registration_pending']['attempts'] = $attempts;
                auth_record_login_failure('register_otp_verify', (string) $pending['email']);
                if ($attempts >= REGISTRATION_OTP_MAX_ATTEMPTS) {
                    registration_clear_pending();
                    $message = 'Too many incorrect attempts. Please restart registration.';
                } else {
                    $message = 'The email verification code is incorrect. Please try again.';
                }
            } else {
                $pdo = db();
                if (!$pdo) {
                    $message = 'Registration is temporarily unavailable. Please try again later.';
                } else {
                    [$ok, $message] = registration_create_verified_account($pdo, $pending);
                    if ($ok) {
                        auth_clear_login_failures('register_otp_send', (string) $pending['email']);
                        auth_clear_login_failures('register_otp_verify', (string) $pending['email']);
                        registration_clear_pending();
                        csrf_rotate();
                    }
                }
            }
        }
    } else {
        $message = 'Invalid registration request. Please try again.';
    }
    $pending = registration_pending();
}

$page_title = 'Office Staff Registration | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .registration-progress {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 10px;
        margin: 0 0 22px;
        color: #748078;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .registration-progress span { display: inline-flex; align-items: center; gap: 7px; }
    .registration-progress b {
        display: grid;
        width: 26px;
        height: 26px;
        place-items: center;
        border: 1px solid #ccd8d0;
        border-radius: 50%;
        background: #fff;
    }
    .registration-progress .is-active,
    .registration-progress .is-complete { color: var(--green-900); }
    .registration-progress .is-active b,
    .registration-progress .is-complete b { border-color: var(--green-700); color: #fff; background: var(--green-700); }
    .registration-progress__line { height: 2px; background: #dce5df; }
    .registration-progress__line.is-complete { background: var(--green-700); }
    .registration-channel-grid,
    .registration-otp-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
    .registration-channel-grid { margin-bottom: 18px; }
    .registration-channel {
        display: flex;
        gap: 10px;
        align-items: center;
        min-width: 0;
        padding: 12px;
        border: 1px solid #dce8e0;
        border-radius: 13px;
        background: #f6faf7;
    }
    .registration-channel i {
        display: grid;
        flex: 0 0 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        color: #fff;
        background: var(--green-700);
    }
    .registration-channel span { min-width: 0; }
    .registration-channel small,
    .registration-channel strong { display: block; }
    .registration-channel small { color: #6d786f; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .registration-channel strong { overflow: hidden; color: var(--green-900); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .registration-otp-grid input { font-size: 20px; font-weight: 900; letter-spacing: .22em; text-align: center; }
    .registration-security-note {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        margin: 4px 0 18px;
        color: #66746b;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
    }
    .registration-security-note i { margin-top: 2px; color: var(--green-700); }
    @media (max-width: 580px) {
        .registration-progress { font-size: 9px; }
    }
</style>
<section class="auth-section auth-section--register">
    <div class="register-shell">
        <aside class="register-panel" aria-label="Registration information">
            <p class="eyebrow">Verified staff access</p>
            <h1>Join the KVK Kokrajhar Portal</h1>
            <p>Verify your email address before submitting your staff account request.</p>
            <div class="register-panel__note">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Email-verified requests are activated after admin approval.</span>
            </div>
        </aside>

        <form class="login-card auth-card register-card" method="post" action="register.php" data-registration-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="registration-progress" aria-label="Registration progress">
                <span class="<?= $pending ? 'is-complete' : 'is-active' ?>">
                    <b aria-hidden="true"><?= $pending ? '<i class="fa-solid fa-check"></i>' : '1' ?></b> Details
                </span>
                <i class="registration-progress__line <?= $pending ? 'is-complete' : '' ?>" aria-hidden="true"></i>
                <span class="<?= $pending ? 'is-active' : '' ?>"><b aria-hidden="true">2</b> Verify</span>
            </div>

            <div class="auth-card__header">
                <span class="auth-lock"><i class="fa-solid <?= $pending ? 'fa-shield-halved' : 'fa-user-plus' ?>" aria-hidden="true"></i></span>
                <div>
                    <p class="eyebrow"><?= $pending ? 'Identity verification' : 'Registration portal' ?></p>
                    <p class="eyebrow nowrap">Krishi Vigyan Kendra Kokrajhar</p>
                </div>
            </div>
            <p class="auth-card__intro">
                <?= $pending
                    ? 'Enter the verification code sent to your email address.'
                    : 'Create your staff account with verified contact details. Login starts after admin approval.' ?>
            </p>
            <div class="register-approval-strip">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span><?= $pending ? 'The code expires after 10 minutes' : 'Email verification required' ?></span>
            </div>

            <?php if ($notice): ?><div class="success" role="status"><?= e($notice) ?></div><?php endif; ?>
            <?php if ($message): ?><div class="<?= $ok ? 'success' : 'alert' ?>" role="<?= $ok ? 'status' : 'alert' ?>"><?= e($message) ?></div><?php endif; ?>

            <?php if (!$pending): ?>
                <input type="hidden" name="action" value="send_codes">
                <div class="register-form-grid">
                    <label class="login-field" for="register-name">
                        <span>Full name</span>
                        <span class="login-input">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <input id="register-name" name="name" autocomplete="name" placeholder="Enter full name" maxlength="120" value="<?= e((string) ($_POST['name'] ?? '')) ?>" required autofocus>
                        </span>
                    </label>

                    <label class="login-field" for="register-email">
                        <span>Email address</span>
                        <span class="login-input">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <input id="register-email" type="email" name="email" autocomplete="email" placeholder="name@example.com" maxlength="254" value="<?= e((string) ($_POST['email'] ?? '')) ?>" required>
                        </span>
                    </label>

                    <label class="login-field" for="register-phone">
                        <span>Mobile number <small>(optional)</small></span>
                        <span class="login-input">
                            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                            <input id="register-phone" type="tel" name="phone" autocomplete="tel" inputmode="numeric" pattern="(?:\+?91[ -]?)?[6-9][0-9]{9}" placeholder="10-digit mobile number" maxlength="14" value="<?= e((string) ($_POST['phone'] ?? '')) ?>">
                        </span>
                    </label>
                </div>

                <input type="hidden" name="role" value="office_staff">
                <label class="login-field" for="register-password">
                    <span>Password</span>
                    <span class="login-input login-input--password">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <input id="register-password" type="password" name="password" autocomplete="new-password" minlength="10" maxlength="128" placeholder="Create password" required>
                        <button class="password-toggle" type="button" aria-label="Show password" aria-controls="register-password">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </span>
                </label>

                <button class="btn btn--primary login-submit" type="submit" data-submit-button data-loading-label="Sending verification code...">
                    <span>Send email verification code</span>
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            <?php else: ?>
                <input type="hidden" name="action" value="verify_codes">
                <div class="registration-channel-grid" aria-label="Verification destinations">
                    <div class="registration-channel">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <span><small>Email code sent to</small><strong><?= e(registration_mask_email((string) $pending['email'])) ?></strong></span>
                    </div>
                </div>

                <div class="registration-otp-grid">
                    <label class="login-field" for="registration-email-otp">
                        <span>Email OTP</span>
                        <span class="login-input">
                            <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
                            <input id="registration-email-otp" type="text" name="email_otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus data-registration-otp>
                        </span>
                    </label>
                </div>
                <p class="registration-security-note"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Your account request is created only after the email code is verified.</span></p>

                <button class="btn btn--primary login-submit" type="submit" data-submit-button data-loading-label="Verifying details...">
                    <span>Verify and submit request</span>
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </button>
                <button class="btn btn--light login-submit" type="submit" name="action" value="restart" formnovalidate>
                    <span>Change details and resend</span>
                    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                </button>
            <?php endif; ?>

            <p class="auth-note">Already registered? <a href="login.php">Login here</a></p>
        </form>
    </div>
</section>
<script>
(() => {
    const form = document.querySelector('[data-registration-form]');
    const submitButton = form?.querySelector('[data-submit-button]');

    form?.querySelectorAll('[data-registration-otp]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
        });
    });

    form?.addEventListener('submit', (event) => {
        if (!submitButton || event.submitter !== submitButton || !form.checkValidity()) {
            return;
        }
        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        const label = submitButton.querySelector('span');
        const icon = submitButton.querySelector('i');
        if (label) {
            label.textContent = submitButton.dataset.loadingLabel || 'Please wait...';
        }
        if (icon) {
            icon.className = 'fa-solid fa-circle-notch fa-spin';
        }
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
