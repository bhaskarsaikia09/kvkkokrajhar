<?php
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/mailer.php';

if (admin_logged_in()) {
    header('Location: admin/dashboard.php');
    exit;
}

if (user_logged_in()) {
    header('Location: user-dashboard.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? $_SESSION['password_reset_email'] ?? ''));
$message = '';
$ok = false;
$otpSent = !empty($_SESSION['password_reset_email']);

function password_reset_otp_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_type VARCHAR(20) NOT NULL,
        account_id INT NOT NULL,
        email VARCHAR(160) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        attempts INT DEFAULT 0,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_reset_otp_email (email),
        INDEX idx_password_reset_otp_status (used_at, expires_at)
    )");
}

function password_reset_account(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT id, email FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if ($account) {
        return ['type' => 'admin', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if ($account) {
        return ['type' => 'user', 'id' => (int) $account['id'], 'email' => (string) $account['email']];
    }

    return null;
}

function send_reset_otp(array $account, string $otp): bool
{
    $subject = APP_NAME . ' password reset OTP';
    $message = "Your OTP for password reset is {$otp}. It will expire in 10 minutes.";

    return send_site_mail((string) $account['email'], $subject, $message);
}

function latest_reset_otp(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM password_reset_otps WHERE email = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    return $row ?: null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'send_otp');

    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Please try again.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter your registered email address.';
    } else {
        $pdo = db();
        if (!$pdo) {
            $message = 'Database is not connected. Please contact the administrator.';
        } elseif ($action === 'send_otp') {
            try {
                password_reset_otp_table($pdo);
                $account = password_reset_account($pdo, $email);

                if (!$account) {
                    $message = 'No active account was found for this email address.';
                } else {
                    $otp = (string) random_int(100000, 999999);
                    $stmt = $pdo->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE email = ? AND used_at IS NULL');
                    $stmt->execute([$account['email']]);

                    $stmt = $pdo->prepare('INSERT INTO password_reset_otps (account_type, account_id, email, otp_hash, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                    $stmt->execute([$account['type'], $account['id'], $account['email'], password_hash($otp, PASSWORD_DEFAULT)]);

                    if (send_reset_otp($account, $otp)) {
                        $_SESSION['password_reset_email'] = $account['email'];
                        $email = $account['email'];
                        $otpSent = true;
                        $ok = true;
                        $message = 'OTP has been sent to your registered email address.';
                    } else {
                        $message = 'Unable to send OTP email right now. Please contact the administrator.';
                    }
                }
            } catch (Throwable $exception) {
                $message = 'Unable to send OTP right now. Please try again.';
            }
        } elseif ($action === 'reset_password') {
            $otpSent = true;
            $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            try {
                password_reset_otp_table($pdo);
                $reset = latest_reset_otp($pdo, $email);

                if (!$reset) {
                    $message = 'OTP is invalid or has expired. Please request a new OTP.';
                    $otpSent = false;
                    unset($_SESSION['password_reset_email']);
                } elseif ((int) ($reset['attempts'] ?? 0) >= 5) {
                    $stmt = $pdo->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE id = ?');
                    $stmt->execute([(int) $reset['id']]);
                    $message = 'Too many incorrect OTP attempts. Please request a new OTP.';
                    $otpSent = false;
                    unset($_SESSION['password_reset_email']);
                } elseif ($otp === '' || !password_verify($otp, (string) $reset['otp_hash'])) {
                    $stmt = $pdo->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?');
                    $stmt->execute([(int) $reset['id']]);
                    $message = 'Please enter the correct OTP sent to your email.';
                } elseif (strlen($password) < 6) {
                    $message = 'New password must be at least 6 characters.';
                } elseif ($password !== $confirmPassword) {
                    $message = 'Password and confirmation do not match.';
                } else {
                    $table = ($reset['account_type'] ?? '') === 'admin' ? 'admin_users' : 'users';
                    $stmt = $pdo->prepare("UPDATE {$table} SET password_hash = ? WHERE id = ? AND is_active = 1");
                    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['account_id']]);

                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE id = ?');
                        $stmt->execute([(int) $reset['id']]);
                        unset($_SESSION['password_reset_email']);
                        $email = '';
                        $otpSent = false;
                        $ok = true;
                        $message = 'Password reset successfully. You can login now.';
                    } else {
                        $message = 'Unable to update this account. Please contact the administrator.';
                    }
                }
            } catch (Throwable $exception) {
                $message = 'Unable to reset password right now. Please try again.';
            }
        }
    }
}

$page_title = 'Forgot Password | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-section">
    <form class="login-card auth-card" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <h1>Forgot password</h1>
        <p class="auth-card__intro">Verify your registered email address with OTP and create a new password.</p>
        <?php if ($message): ?><div class="<?= $ok ? 'success' : 'alert' ?>" role="<?= $ok ? 'status' : 'alert' ?>"><?= e($message) ?></div><?php endif; ?>

        <?php if (!$otpSent): ?>
            <input type="hidden" name="action" value="send_otp">
            <label class="login-field">
                <span>Registered email address</span>
                <span class="login-input">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" autocomplete="email" placeholder="name@example.com" value="<?= e($email) ?>" required autofocus>
                </span>
            </label>

            <button class="btn btn--primary login-submit" type="submit">
                <span>Send OTP</span>
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        <?php else: ?>
            <input type="hidden" name="action" value="reset_password">
            <label class="login-field">
                <span>Registered email address</span>
                <span class="login-input">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" autocomplete="email" value="<?= e($email) ?>" required readonly>
                </span>
            </label>

            <label class="login-field">
                <span>OTP</span>
                <span class="login-input">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6 digit OTP" required autofocus>
                </span>
            </label>

            <label class="login-field">
                <span>New password</span>
                <span class="login-input login-input--password">
                    <i class="fa-solid fa-key"></i>
                    <input id="forgot-password-new" type="password" name="password" autocomplete="new-password" minlength="6" placeholder="Create new password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-controls="forgot-password-new">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </span>
            </label>

            <label class="login-field">
                <span>Confirm password</span>
                <span class="login-input login-input--password">
                    <i class="fa-solid fa-key"></i>
                    <input id="forgot-password-confirm" type="password" name="confirm_password" autocomplete="new-password" minlength="6" placeholder="Confirm new password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-controls="forgot-password-confirm">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </span>
            </label>

            <button class="btn btn--primary login-submit" type="submit">
                <span>Reset password</span>
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        <?php endif; ?>

        <p class="auth-note"><a href="login.php">Back to login</a></p>
    </form>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
