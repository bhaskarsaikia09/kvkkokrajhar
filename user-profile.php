<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_user();
auth_no_store_headers();

$sessionUser = current_user() ?? [];
$pdo = function_exists('db') ? db() : null;
$userId = (int) ($sessionUser['id'] ?? 0);
$errors = [];
$staffErrors = [];
$success = isset($_GET['updated']) && $_GET['updated'] === '1';
$staffSuccess = isset($_GET['staff_updated']) && $_GET['staff_updated'] === '1';
$formAction = is_scalar($_POST['form_action'] ?? null) ? (string) $_POST['form_action'] : '';

function up_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function up_text(mixed $value, int $maxLength): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $value = trim(strip_tags((string) $value));
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function up_date(mixed $value): ?string
{
    $value = up_text($value, 10);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : null;
}

function up_identity_name(mixed $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/^(?:(?:dr|mr|mrs|ms|miss|smt|smti|shri|sri)\.?\s+)+/iu', '', $value) ?? $value;
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
}

function up_identity_email(mixed $value): string
{
    $value = function_exists('mb_strtolower')
        ? mb_strtolower(trim((string) $value), 'UTF-8')
        : strtolower(trim((string) $value));
    $value = preg_replace('/\s*(?:\[at\]|\(at\)|\bat\b)\s*/iu', '@', $value) ?? $value;
    return preg_replace('/\s+/u', '', $value) ?? '';
}

function up_identity_phone(mixed $value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
    return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}

function up_staff_match(PDO $pdo, string $email, string $name, string $phone): array
{
    if ($email === '' && $name === '' && $phone === '') {
        return [null, 'not_found', ''];
    }

    try {
        $rows = $pdo->query('SELECT * FROM staff ORDER BY is_active DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return [null, 'unavailable', ''];
    }

    $accountEmail = up_identity_email($email);
    $accountName = up_identity_name($name);
    $accountPhone = up_identity_phone($phone);
    $candidates = [];
    foreach ($rows as $row) {
        $evidence = [];
        $score = 0;
        if ($accountEmail !== '' && $accountEmail === up_identity_email($row['email'] ?? '')) {
            $evidence[] = 'email';
            $score += 4;
        }
        if ($accountPhone !== '' && strlen($accountPhone) >= 7 && $accountPhone === up_identity_phone($row['phone'] ?? '')) {
            $evidence[] = 'phone';
            $score += 3;
        }
        if ($accountName !== '' && $accountName === up_identity_name($row['name'] ?? '')) {
            $evidence[] = 'name';
            $score += 2;
        }
        if ($score > 0) {
            $candidates[] = ['record' => $row, 'score' => $score, 'evidence' => $evidence];
        }
    }

    if (!$candidates) {
        return [null, 'not_found', ''];
    }

    $activeCandidates = array_values(array_filter(
        $candidates,
        static fn (array $candidate): bool => (int) ($candidate['record']['is_active'] ?? 1) === 1
    ));
    if ($activeCandidates) {
        $candidates = $activeCandidates;
    }
    usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
    $topScore = (int) $candidates[0]['score'];
    $topCandidates = array_values(array_filter(
        $candidates,
        static fn (array $candidate): bool => (int) $candidate['score'] === $topScore
    ));
    if (count($topCandidates) !== 1) {
        return [null, 'ambiguous', ''];
    }

    $record = $topCandidates[0]['record'];
    return [
        $record,
        ((int) ($record['is_active'] ?? 1) === 1 ? 'matched' : 'inactive'),
        implode(', ', $topCandidates[0]['evidence']),
    ];
}

function up_staff_photo_upload(array $file, int $staffId): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['', ''];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['', 'The staff photo could not be uploaded.'];
    }

    $size = (int) ($file['size'] ?? 0);
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($size < 1 || $size > 4 * 1024 * 1024 || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        return ['', 'The staff photo must be a valid image no larger than 4 MB.'];
    }

    $info = @getimagesize($temporaryPath);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset($allowed[$mime]) || (int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
        return ['', 'Please select a valid JPG, PNG or WebP staff photo.'];
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff';
    if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
        return ['', 'The staff photo folder is not available.'];
    }

    try {
        $fileName = 'staff-' . $staffId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    } catch (Throwable $exception) {
        return ['', 'The staff photo could not be prepared.'];
    }

    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
        return ['', 'The staff photo could not be saved.'];
    }

    return ['uploads/staff/' . $fileName, ''];
}

$profile = [
    'name' => (string) ($sessionUser['name'] ?? ''),
    'email' => (string) ($sessionUser['email'] ?? ''),
    'phone' => (string) ($sessionUser['phone'] ?? ''),
    'role' => (string) ($sessionUser['role'] ?? 'office_staff'),
    'password_hash' => '',
];

if (!$pdo instanceof PDO || $userId < 1) {
    $errors[] = 'Your profile is temporarily unavailable. Please sign in again or contact the administrator.';
} else {
    try {
        $stmt = $pdo->prepare('SELECT name, email, phone, role, password_hash FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $profile = array_merge($profile, $row);
        } else {
            $errors[] = 'Your active user account could not be found.';
        }
    } catch (Throwable $exception) {
        $errors[] = 'Your profile could not be loaded at this time.';
    }
}

$staffRecord = null;
$staffMatchState = 'not_found';
$staffMatchMethod = '';
if ($pdo instanceof PDO && $userId > 0 && !$errors) {
    [$staffRecord, $staffMatchState, $staffMatchMethod] = up_staff_match(
        $pdo,
        trim((string) $profile['email']),
        trim((string) $profile['name']),
        trim((string) $profile['phone'])
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$errors && $formAction === 'account') {
    $name = up_text($_POST['name'] ?? '', 120);
    $email = auth_normalize_email(up_text($_POST['email'] ?? '', 254));
    $phone = up_text($_POST['phone'] ?? '', 30);
    $currentPassword = is_scalar($_POST['current_password'] ?? null) ? (string) $_POST['current_password'] : '';
    $newPassword = is_scalar($_POST['new_password'] ?? null) ? (string) $_POST['new_password'] : '';
    $confirmPassword = is_scalar($_POST['confirm_password'] ?? null) ? (string) $_POST['confirm_password'] : '';

    $profile['name'] = $name;
    $profile['email'] = $email;
    $profile['phone'] = $phone;

    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if (!auth_request_is_same_origin() || !csrf_valid(is_scalar($_POST['csrf_token'] ?? null) ? (string) $_POST['csrf_token'] : null)) {
        $errors[] = 'The form session expired. Please refresh the page and try again.';
    }
    if ($nameLength < 2 || $nameLength > 120) {
        $errors[] = 'Please enter a name between 2 and 120 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '') {
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
            $errors[] = 'Please enter a valid phone number containing 7 to 15 digits.';
        }
    }
    if ($currentPassword === '' || !password_verify($currentPassword, (string) $profile['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    if ($newPassword !== '') {
        if (strlen($newPassword) < 10 || strlen($newPassword) > 128) {
            $errors[] = 'The new password must be between 10 and 128 characters.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $errors[] = 'The new password and confirmation do not match.';
        } elseif (password_verify($newPassword, (string) $profile['password_hash'])) {
            $errors[] = 'Choose a new password that is different from your current password.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'That email address is already used by another account.';
            }
        } catch (Throwable $exception) {
            $errors[] = 'The email address could not be verified at this time.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if ($newPassword !== '') {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (!is_string($newHash) || $newHash === '') {
                    throw new RuntimeException('Password hashing failed.');
                }
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ? AND is_active = 1');
                $stmt->execute([$name, $email, $phone, $newHash, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND is_active = 1');
                $stmt->execute([$name, $email, $phone, $userId]);
            }

            if (is_array($staffRecord) && $staffMatchState === 'matched') {
                $stmt = $pdo->prepare(
                    'UPDATE staff SET name = ?, email = ?, phone = ? WHERE id = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND LOWER(TRIM(name)) = LOWER(TRIM(?))'
                );
                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    (int) $staffRecord['id'],
                    (string) $staffRecord['email'],
                    (string) $staffRecord['name'],
                ]);
            }

            $pdo->commit();

            $_SESSION['user_name'] = auth_safe_session_text($name, 120);
            $_SESSION['user_email'] = auth_safe_session_text($email, 254);
            $_SESSION['user_phone'] = auth_safe_session_text($phone, 30);
            csrf_rotate();
            header('Location: user-profile.php?updated=1', true, 303);
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = (string) $exception->getCode() === '23000'
                ? 'That email address is already used by another account.'
                : 'Your profile could not be updated. Please try again.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Your profile could not be updated. Please try again.';
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && !$errors
    && $formAction === 'staff'
    && is_array($staffRecord)
    && $staffMatchState === 'matched') {
    $staffId = (int) ($staffRecord['id'] ?? 0);
    $staffOriginalName = (string) ($staffRecord['name'] ?? '');
    $staffOriginalEmail = (string) ($staffRecord['email'] ?? '');
    $staffName = up_text($_POST['staff_name'] ?? '', 160);
    $staffEmail = auth_normalize_email(up_text($_POST['staff_email'] ?? '', 160));
    $staffPhone = up_text($_POST['staff_phone'] ?? '', 60);
    $designation = up_text($_POST['designation'] ?? '', 160);
    $discipline = up_text($_POST['discipline'] ?? '', 160);
    $qualification = up_text($_POST['qualification'] ?? '', 160);
    $expertise = up_text($_POST['expertise'] ?? '', 160);
    $dateOfBirthInput = up_text($_POST['date_of_birth'] ?? '', 10);
    $joiningAauInput = up_text($_POST['date_of_joining_aau'] ?? '', 10);
    $joiningPostInput = up_text($_POST['date_of_joining_present_post'] ?? '', 10);
    $dateOfBirth = up_date($dateOfBirthInput);
    $joiningAau = up_date($joiningAauInput);
    $joiningPost = up_date($joiningPostInput);
    $profileLink = up_text($_POST['profile_link'] ?? '', 255);
    $currentPassword = is_scalar($_POST['staff_current_password'] ?? null) ? (string) $_POST['staff_current_password'] : '';

    foreach ([
        'name' => $staffName,
        'email' => $staffEmail,
        'phone' => $staffPhone,
        'designation' => $designation,
        'discipline' => $discipline,
        'qualification' => $qualification,
        'expertise' => $expertise,
        'date_of_birth' => $dateOfBirthInput,
        'date_of_joining_aau' => $joiningAauInput,
        'date_of_joining_present_post' => $joiningPostInput,
        'profile_link' => $profileLink,
    ] as $field => $value) {
        $staffRecord[$field] = $value;
    }

    $staffNameLength = function_exists('mb_strlen') ? mb_strlen($staffName, 'UTF-8') : strlen($staffName);
    if (!auth_request_is_same_origin() || !csrf_valid(is_scalar($_POST['csrf_token'] ?? null) ? (string) $_POST['csrf_token'] : null)) {
        $staffErrors[] = 'The form session expired. Please refresh the page and try again.';
    }
    if ($staffNameLength < 2 || $staffNameLength > 160) {
        $staffErrors[] = 'Please enter a staff name between 2 and 160 characters.';
    }
    if (!filter_var($staffEmail, FILTER_VALIDATE_EMAIL) || strlen($staffEmail) > 160) {
        $staffErrors[] = 'Please enter a valid staff email address.';
    }
    if ($staffPhone !== '') {
        $staffPhoneDigits = preg_replace('/\D+/', '', $staffPhone) ?? '';
        if (strlen($staffPhoneDigits) < 7 || strlen($staffPhoneDigits) > 15) {
            $staffErrors[] = 'Please enter a valid staff phone number containing 7 to 15 digits.';
        }
    }
    foreach ([
        'Date of birth' => [$dateOfBirthInput, $dateOfBirth],
        'Date of joining AAU' => [$joiningAauInput, $joiningAau],
        'Date of joining present post' => [$joiningPostInput, $joiningPost],
    ] as $label => [$input, $date]) {
        if ($input !== '' && $date === null) {
            $staffErrors[] = $label . ' is not a valid date.';
        }
    }
    if ($profileLink !== '') {
        $scheme = strtolower((string) parse_url($profileLink, PHP_URL_SCHEME));
        if (!filter_var($profileLink, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $staffErrors[] = 'The AAU profile link must be a valid http or https URL.';
        }
    }
    if ($currentPassword === '' || !password_verify($currentPassword, (string) $profile['password_hash'])) {
        $staffErrors[] = 'Your current password is incorrect.';
    }

    if (!$staffErrors) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$staffEmail, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $staffErrors[] = 'That email address is already used by another account.';
            }
        } catch (Throwable $exception) {
            $staffErrors[] = 'The email address could not be verified at this time.';
        }
    }

    $photoPath = (string) ($staffRecord['photo_path'] ?? '');
    if (!$staffErrors) {
        [$uploadedPhoto, $photoError] = up_staff_photo_upload(
            is_array($_FILES['staff_photo'] ?? null) ? $_FILES['staff_photo'] : [],
            $staffId
        );
        if ($photoError !== '') {
            $staffErrors[] = $photoError;
        } elseif ($uploadedPhoto !== '') {
            $photoPath = $uploadedPhoto;
            $staffRecord['photo_path'] = $uploadedPhoto;
        }
    }

    if (!$staffErrors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT id FROM staff WHERE id = ? AND is_active = 1 AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND LOWER(TRIM(name)) = LOWER(TRIM(?)) FOR UPDATE'
            );
            $stmt->execute([$staffId, $staffOriginalEmail, $staffOriginalName]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('The staff identity changed while the form was open.');
            }

            $stmt = $pdo->prepare(
                'UPDATE staff SET name = ?, email = ?, phone = ?, designation = ?, discipline = ?, qualification = ?, expertise = ?, date_of_birth = ?, date_of_joining_aau = ?, date_of_joining_present_post = ?, photo_path = ?, profile_link = ? WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([
                $staffName,
                $staffEmail,
                $staffPhone,
                $designation,
                $discipline,
                $qualification,
                $expertise,
                $dateOfBirth,
                $joiningAau,
                $joiningPost,
                $photoPath,
                $profileLink,
                $staffId,
            ]);
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND is_active = 1');
            $stmt->execute([$staffName, $staffEmail, $staffPhone, $userId]);
            $pdo->commit();

            $_SESSION['user_name'] = auth_safe_session_text($staffName, 120);
            $_SESSION['user_email'] = auth_safe_session_text($staffEmail, 254);
            $_SESSION['user_phone'] = auth_safe_session_text($staffPhone, 30);
            csrf_rotate();
            header('Location: user-profile.php?staff_updated=1#staff-record', true, 303);
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $staffErrors[] = (string) $exception->getCode() === '23000'
                ? 'That email address is already used by another record.'
                : 'The staff record could not be updated. Please try again.';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $staffErrors[] = 'The staff record could not be updated. Please refresh and try again.';
        }
    }
}

$userName = trim((string) $profile['name']) ?: 'User';
$userRole = ucwords(str_replace('_', ' ', (string) $profile['role']));
$page_title = 'Update Profile | KVK Kokrajhar';
require_once __DIR__ . '/includes/user-mis-shell.php';
require_once __DIR__ . '/includes/header.php';
?>
<style>
@import url('assets/css/user-mis.css');

:root {
    --up-green: #0b6a46;
    --up-green-dark: #073f2c;
    --up-ink: #17384e;
    --up-muted: #667a70;
    --up-border: #dce8e1;
    --up-bg: #f3f7f4;
}

.profile-page {
    min-height: 72vh;
    padding: 42px 0 72px;
    color: var(--up-ink);
    background:
        radial-gradient(circle at 7% 8%, rgba(142,198,63,.11), transparent 25%),
        radial-gradient(circle at 94% 28%, rgba(227,178,60,.10), transparent 22%),
        linear-gradient(180deg, #f9fcfa 0%, var(--up-bg) 100%);
}

.up-shell { max-width: 1050px; }
.up-back {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 18px;
    color: var(--up-green);
    font-size: .78rem;
    font-weight: 900;
    text-decoration: none;
}
.up-back:hover { color: var(--up-green-dark); }

.up-layout {
    display: grid;
    grid-template-columns: minmax(250px, .72fr) minmax(0, 1.55fr);
    gap: 20px;
    align-items: start;
}

.up-card {
    overflow: hidden;
    border: 1px solid var(--up-border);
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(20,67,48,.09);
}

.up-summary__head {
    position: relative;
    overflow: hidden;
    padding: 30px 24px 26px;
    color: #fff;
    background: linear-gradient(135deg, #063d2c, #0b7653 68%, #6ca336);
}
.up-summary__head::after {
    content: '';
    position: absolute;
    width: 170px;
    height: 170px;
    right: -95px;
    top: -95px;
    border: 27px solid rgba(255,255,255,.10);
    border-radius: 50%;
}
.up-avatar {
    display: grid;
    width: 72px;
    height: 72px;
    place-items: center;
    margin-bottom: 16px;
    border: 5px solid rgba(255,255,255,.19);
    border-radius: 22px;
    background: rgba(255,255,255,.16);
    font-size: 1.65rem;
    font-weight: 900;
    backdrop-filter: blur(8px);
}
.up-summary h1 { margin: 0 0 5px; color: #fff; font-size: 1.4rem; line-height: 1.2; }
.up-summary__role { margin: 0; color: rgba(255,255,255,.8); font-size: .78rem; font-weight: 750; }
.up-summary__body { padding: 21px 24px 24px; }
.up-summary__body h2 { margin: 0 0 9px; color: #173b2c; font-size: .94rem; }
.up-summary__body p { margin: 0; color: var(--up-muted); font-size: .76rem; line-height: 1.6; }
.up-security-list { display: grid; gap: 11px; margin: 20px 0 0; padding: 18px 0 0; border-top: 1px solid #edf2ef; }
.up-security-item { display: flex; gap: 10px; align-items: center; color: #496558; font-size: .72rem; font-weight: 750; }
.up-security-item i { display: grid; width: 27px; height: 27px; place-items: center; border-radius: 9px; color: var(--up-green); background: #edf7f1; }

.up-form { padding: 26px; }
.up-form-head { margin-bottom: 23px; }
.up-form-head span { display: block; margin-bottom: 5px; color: var(--up-green); font-size: .68rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.up-form-head h2 { margin: 0 0 7px; color: #173b2c; font-size: clamp(1.35rem, 2.5vw, 1.8rem); }
.up-form-head p { margin: 0; color: var(--up-muted); font-size: .78rem; line-height: 1.5; }

.up-alert { display: flex; gap: 11px; align-items: start; margin-bottom: 18px; padding: 13px 14px; border-radius: 13px; font-size: .76rem; font-weight: 750; line-height: 1.5; }
.up-alert--success { border: 1px solid #b9dfc8; color: #155c37; background: #edf9f2; }
.up-alert--error { border: 1px solid #efc9c9; color: #913d3d; background: #fff4f4; }
.up-alert ul { margin: 0; padding-left: 18px; }

.up-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px; }
.up-field { min-width: 0; }
.up-field--full { grid-column: 1 / -1; }
.up-field label { display: block; margin-bottom: 7px; color: #294d3b; font-size: .72rem; font-weight: 900; }
.up-field label span { color: #a24c4c; }
.up-input-wrap { position: relative; }
.up-input-wrap > i { position: absolute; top: 50%; left: 14px; color: #789084; font-size: .8rem; transform: translateY(-50%); pointer-events: none; }
.up-field input {
    width: 100%;
    min-height: 46px;
    box-sizing: border-box;
    padding: 10px 13px 10px 39px;
    border: 1px solid #cfddd5;
    border-radius: 12px;
    outline: none;
    color: #254637;
    background: #fbfdfc;
    font: inherit;
    font-size: .8rem;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
.up-field input:focus { border-color: #4c9e77; background: #fff; box-shadow: 0 0 0 4px rgba(11,106,70,.09); }
.up-field small { display: block; margin-top: 6px; color: #7a8d83; font-size: .67rem; line-height: 1.45; }
.up-divider { grid-column: 1 / -1; display: flex; gap: 12px; align-items: center; margin: 4px 0 -2px; color: #385c49; font-size: .72rem; font-weight: 900; }
.up-divider::after { content: ''; height: 1px; flex: 1; background: #e4ece7; }
.up-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; margin-top: 23px; padding-top: 19px; border-top: 1px solid #e8efeb; }
.up-btn { display: inline-flex; gap: 8px; align-items: center; justify-content: center; min-height: 43px; padding: 10px 16px; border: 1px solid #cfddd5; border-radius: 12px; color: #365446; background: #fff; font-size: .76rem; font-weight: 900; text-decoration: none; cursor: pointer; }
.up-btn--primary { border-color: var(--up-green); color: #fff; background: var(--up-green); box-shadow: 0 8px 18px rgba(11,106,70,.18); }
.up-btn--primary:hover { color: #fff; background: var(--up-green-dark); }

.up-staff { margin-top: 22px; padding: 27px; }
.up-staff-head { display: flex; gap: 20px; justify-content: space-between; align-items: start; margin-bottom: 22px; }
.up-staff-head__title { display: flex; gap: 14px; align-items: center; }
.up-staff-head__icon { display: grid; width: 47px; height: 47px; flex: 0 0 auto; place-items: center; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #0b6a46, #83bd3f); box-shadow: 0 8px 18px rgba(11,106,70,.18); }
.up-staff-head span { display: block; margin-bottom: 4px; color: var(--up-green); font-size: .67rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.up-staff-head h2 { margin: 0 0 5px; color: #173b2c; font-size: clamp(1.3rem, 2.4vw, 1.72rem); }
.up-staff-head p { max-width: 660px; margin: 0; color: var(--up-muted); font-size: .76rem; line-height: 1.5; }
.up-match-badge { display: inline-flex; gap: 7px; align-items: center; flex: 0 0 auto; padding: 8px 11px; border: 1px solid #b9dfc8; border-radius: 999px; color: #17613a; background: #edf9f2; font-size: .67rem; font-weight: 900; white-space: nowrap; }
.up-staff-photo-row { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 18px; align-items: center; grid-column: 1 / -1; padding: 16px; border: 1px solid #e2ebe6; border-radius: 15px; background: #f9fcfa; }
.up-staff-photo { display: grid; width: 86px; height: 102px; place-items: center; overflow: hidden; border: 4px solid #fff; border-radius: 16px; color: var(--up-green); background: #e8f3ed; box-shadow: 0 7px 18px rgba(22,64,45,.11); font-size: 1.35rem; }
.up-staff-photo img { width: 100%; height: 100%; object-fit: cover; }
.up-file-input { width: 100%; color: #4d675a; font-size: .74rem; }
.up-file-input::file-selector-button { margin-right: 11px; padding: 8px 11px; border: 0; border-radius: 9px; color: #fff; background: var(--up-green); font-size: .7rem; font-weight: 850; cursor: pointer; }
.up-admin-meta { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 9px; grid-column: 1 / -1; }
.up-admin-meta__item { padding: 12px; border: 1px solid #e2ebe6; border-radius: 12px; background: #fbfdfc; }
.up-admin-meta__item span { display: block; margin-bottom: 4px; color: #829188; font-size: .61rem; font-weight: 900; letter-spacing: .05em; text-transform: uppercase; }
.up-admin-meta__item strong { display: block; color: #315141; font-size: .73rem; overflow-wrap: anywhere; }
.up-match-message { padding: 22px; border: 1px dashed #cbdad2; border-radius: 15px; color: #60776b; background: #fafcfb; text-align: center; font-size: .77rem; font-weight: 700; line-height: 1.6; }
.up-match-message i { display: block; margin-bottom: 9px; color: #7b9789; font-size: 1.35rem; }

@media (max-width: 780px) {
    .profile-page { padding-top: 28px; }
    .up-layout { grid-template-columns: 1fr; }
    .up-summary { display: grid; grid-template-columns: .85fr 1.15fr; }
    .up-summary__body { align-self: center; }
    .up-admin-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 560px) {
    .up-summary { display: block; }
    .up-fields { grid-template-columns: 1fr; }
    .up-field--full, .up-divider { grid-column: auto; }
    .up-form { padding: 20px 17px; }
    .up-staff { padding: 20px 17px; }
    .up-staff-head { flex-direction: column; }
    .up-staff-photo-row { grid-template-columns: 1fr; }
    .up-admin-meta { grid-template-columns: 1fr; }
    .up-actions { flex-direction: column-reverse; }
    .up-btn { width: 100%; }
}

/* MIS visual alignment shared with the user desk and data-entry pages. */
.profile-page {
    min-height: calc(100vh - 64px);
    padding: 0;
    color: var(--mis-text);
    background: var(--mis-bg);
}

.profile-page .up-shell {
    max-width: 1040px;
    padding-top: 42px;
    padding-bottom: 72px;
}

.profile-page .mis-page-heading { margin-bottom: 28px; }

.up-layout { grid-template-columns: minmax(250px, .7fr) minmax(0, 1.55fr); gap: 18px; }

.up-card {
    border-color: var(--mis-border);
    border-radius: 9px;
    box-shadow: 0 1px 2px rgba(20, 29, 45, .03);
}

.up-summary__head {
    padding: 26px 22px 23px;
    background: var(--mis-blue);
}

.up-summary__head::after { display: none; }
.up-avatar { width: 64px; height: 64px; border-width: 3px; border-radius: 50%; }
.up-summary__body { padding: 20px 22px 22px; }
.up-summary__body h2,
.up-form-head h2,
.up-staff-head h2 { color: var(--mis-text); }
.up-summary__body p,
.up-form-head p,
.up-staff-head p { color: var(--mis-muted); }
.up-security-item { color: #566174; }
.up-security-item i { color: var(--mis-blue); background: var(--mis-blue-soft); }

.up-form { padding: 24px; }
.up-form-head span,
.up-staff-head span { color: var(--mis-blue); }
.up-field label { color: #374255; }
.up-field input { min-height: 44px; border-color: #cfd5df; border-radius: 6px; color: #283345; background: #fff; }
.up-field input:focus { border-color: #6681d5; box-shadow: 0 0 0 3px rgba(41, 77, 189, .1); }
.up-divider { color: #4a5568; }
.up-btn { min-height: 42px; border-color: var(--mis-border); border-radius: 6px; color: #344054; background: #eef0f3; }
.up-btn--primary { border-color: var(--mis-blue); color: #fff; background: var(--mis-blue); box-shadow: none; }
.up-btn--primary:hover { color: #fff; background: var(--mis-blue-dark); }

.up-staff { margin-top: 18px; padding: 24px; }
.up-staff-head__icon { border-radius: 7px; background: var(--mis-blue); box-shadow: none; }
.up-match-badge { border-color: #cdd6f3; color: var(--mis-blue-dark); background: var(--mis-blue-soft); }
.up-staff-photo-row,
.up-admin-meta__item { border-color: #e1e5ec; border-radius: 7px; background: #fafbfc; }
.up-file-input::file-selector-button { border-radius: 5px; background: var(--mis-blue); }

@media (max-width: 780px) {
    .profile-page .up-shell { padding-top: 28px; }
    .up-layout { grid-template-columns: 1fr; }
}

@media (max-width: 560px) {
    .profile-page .up-shell { width: calc(100% - 32px); }
    .up-summary { display: block; }
    .up-form,
    .up-staff { padding: 18px 15px; }
    .up-fields { gap: 14px; }
}
</style>

<?php render_user_mis_nav($profile, 'profile', ''); ?>

<div class="profile-page">
    <div class="container up-shell">
        <header class="mis-page-heading">
            <div>
                <p class="mis-page-heading__eyebrow">Account and staff settings</p>
                <h1>My profile</h1>
                <p>Keep your account, contact details and linked staff-directory information accurate.</p>
            </div>
            <div class="mis-page-heading__actions"><a class="mis-button" href="user-dashboard.php">← Back to Dashboard</a></div>
        </header>

        <div class="up-layout">
            <aside class="up-card up-summary" aria-label="Account summary">
                <div class="up-summary__head">
                    <div class="up-avatar" aria-hidden="true"><?= up_e(strtoupper(substr($userName, 0, 1))) ?></div>
                    <h1><?= up_e($userName) ?></h1>
                    <p class="up-summary__role"><?= up_e($userRole) ?></p>
                </div>
                <div class="up-summary__body">
                    <h2>Your secure profile</h2>
                    <p>Keep your contact details current so the KVK team can identify and reach you when needed.</p>
                    <div class="up-security-list">
                        <div class="up-security-item"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Password confirmation required</div>
                        <div class="up-security-item"><i class="fa-solid fa-lock" aria-hidden="true"></i> Protected account information</div>
                        <div class="up-security-item"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Changes update immediately</div>
                    </div>
                </div>
            </aside>

            <section class="up-card up-form" aria-labelledby="profileFormTitle">
                <div class="up-form-head">
                    <span>Account settings</span>
                    <h2 id="profileFormTitle">Update profile</h2>
                    <p>Edit your personal details or set a new password. Your role remains managed by the administrator.</p>
                </div>

                <?php if ($success): ?>
                    <div class="up-alert up-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Your profile was updated successfully.</span></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="up-alert up-alert--error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <ul><?php foreach ($errors as $error): ?><li><?= up_e($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="user-profile.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= up_e(csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="account">
                    <div class="up-fields">
                        <div class="up-field up-field--full">
                            <label for="profileName">Full name <span aria-hidden="true">*</span></label>
                            <div class="up-input-wrap"><i class="fa-solid fa-user" aria-hidden="true"></i><input id="profileName" name="name" type="text" value="<?= up_e($profile['name']) ?>" minlength="2" maxlength="120" autocomplete="name" required></div>
                        </div>
                        <div class="up-field">
                            <label for="profileEmail">Email address <span aria-hidden="true">*</span></label>
                            <div class="up-input-wrap"><i class="fa-solid fa-envelope" aria-hidden="true"></i><input id="profileEmail" name="email" type="email" value="<?= up_e($profile['email']) ?>" maxlength="254" autocomplete="email" required></div>
                        </div>
                        <div class="up-field">
                            <label for="profilePhone">Phone number</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-phone" aria-hidden="true"></i><input id="profilePhone" name="phone" type="tel" value="<?= up_e($profile['phone']) ?>" maxlength="30" autocomplete="tel" inputmode="tel"></div>
                        </div>

                        <div class="up-divider"><i class="fa-solid fa-key" aria-hidden="true"></i> Security confirmation</div>

                        <div class="up-field up-field--full">
                            <label for="currentPassword">Current password <span aria-hidden="true">*</span></label>
                            <div class="up-input-wrap"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="currentPassword" name="current_password" type="password" maxlength="4096" autocomplete="current-password" required></div>
                            <small>Required to save any profile changes.</small>
                        </div>
                        <div class="up-field">
                            <label for="newPassword">New password</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-key" aria-hidden="true"></i><input id="newPassword" name="new_password" type="password" minlength="10" maxlength="128" autocomplete="new-password"></div>
                            <small>Leave blank to keep your current password.</small>
                        </div>
                        <div class="up-field">
                            <label for="confirmPassword">Confirm new password</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-check" aria-hidden="true"></i><input id="confirmPassword" name="confirm_password" type="password" minlength="10" maxlength="128" autocomplete="new-password"></div>
                        </div>
                    </div>

                    <div class="up-actions">
                        <a class="up-btn" href="user-dashboard.php">Cancel</a>
                        <button class="up-btn up-btn--primary" type="submit"<?= (!$pdo instanceof PDO || $userId < 1) ? ' disabled' : '' ?>><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save profile changes</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="up-card up-staff" id="staff-record" aria-labelledby="staffRecordTitle">
            <div class="up-staff-head">
                <div class="up-staff-head__title">
                    <div class="up-staff-head__icon" aria-hidden="true"><i class="fa-solid fa-address-card"></i></div>
                    <div>
                        <span>Staff directory record</span>
                        <h2 id="staffRecordTitle">My staff information</h2>
                        <p>The signed-in email and name are checked against the staff table. Editing is enabled only when they identify one unique staff record without a conflict.</p>
                    </div>
                </div>
                <?php if ($staffMatchState === 'matched'): ?>
                    <div class="up-match-badge"><i class="fa-solid fa-link" aria-hidden="true"></i> Matched by <?= up_e($staffMatchMethod) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($staffSuccess): ?>
                <div class="up-alert up-alert--success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Your staff information and linked account details were updated successfully.</span></div>
            <?php endif; ?>

            <?php if ($staffErrors): ?>
                <div class="up-alert up-alert--error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <ul><?php foreach ($staffErrors as $error): ?><li><?= up_e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php if (is_array($staffRecord)): ?>
                <?php $canEditStaff = $staffMatchState === 'matched'; ?>
                <?php if (!$canEditStaff): ?>
                    <div class="up-alert up-alert--error" role="status"><i class="fa-solid fa-user-lock" aria-hidden="true"></i><span>This staff record is inactive. Its details are visible, but only an administrator can reactivate or edit it.</span></div>
                <?php endif; ?>

                <form method="post" action="user-profile.php#staff-record" enctype="multipart/form-data" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= up_e(csrf_token()) ?>">
                    <input type="hidden" name="form_action" value="staff">
                    <div class="up-fields">
                        <div class="up-staff-photo-row">
                            <div class="up-staff-photo" aria-label="Current staff photo">
                                <?php if (trim((string) ($staffRecord['photo_path'] ?? '')) !== ''): ?>
                                    <img src="<?= up_e($staffRecord['photo_path']) ?>" alt="<?= up_e((string) ($staffRecord['name'] ?? 'Staff member')) ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <?php endif; ?>
                            </div>
                            <div class="up-field">
                                <label for="staffPhoto">Staff photograph</label>
                                <input class="up-file-input" id="staffPhoto" name="staff_photo" type="file" accept="image/jpeg,image/png,image/webp"<?= $canEditStaff ? '' : ' disabled' ?>>
                                <small>Optional. JPG, PNG or WebP, up to 4 MB. The existing photo remains unless a new one is uploaded.</small>
                            </div>
                        </div>

                        <div class="up-field">
                            <label for="staffName">Staff name <span aria-hidden="true">*</span></label>
                            <div class="up-input-wrap"><i class="fa-solid fa-user" aria-hidden="true"></i><input id="staffName" name="staff_name" type="text" value="<?= up_e($staffRecord['name'] ?? '') ?>" minlength="2" maxlength="160" required<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffEmail">Staff email <span aria-hidden="true">*</span></label>
                            <div class="up-input-wrap"><i class="fa-solid fa-envelope" aria-hidden="true"></i><input id="staffEmail" name="staff_email" type="email" value="<?= up_e($staffRecord['email'] ?? '') ?>" maxlength="160" required<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffPhone">Phone number</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-phone" aria-hidden="true"></i><input id="staffPhone" name="staff_phone" type="tel" value="<?= up_e($staffRecord['phone'] ?? '') ?>" maxlength="60" inputmode="tel"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffDesignation">Designation</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-briefcase" aria-hidden="true"></i><input id="staffDesignation" name="designation" type="text" value="<?= up_e($staffRecord['designation'] ?? '') ?>" maxlength="160"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffDiscipline">Discipline</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-leaf" aria-hidden="true"></i><input id="staffDiscipline" name="discipline" type="text" value="<?= up_e($staffRecord['discipline'] ?? '') ?>" maxlength="160"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffQualification">Qualification</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><input id="staffQualification" name="qualification" type="text" value="<?= up_e($staffRecord['qualification'] ?? '') ?>" maxlength="160"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field up-field--full">
                            <label for="staffExpertise">Area of expertise</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-star" aria-hidden="true"></i><input id="staffExpertise" name="expertise" type="text" value="<?= up_e($staffRecord['expertise'] ?? '') ?>" maxlength="160"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>

                        <div class="up-divider"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Service dates</div>

                        <div class="up-field">
                            <label for="staffDob">Date of birth</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-cake-candles" aria-hidden="true"></i><input id="staffDob" name="date_of_birth" type="date" value="<?= up_e($staffRecord['date_of_birth'] ?? '') ?>"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffJoinAau">Date of joining AAU</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><input id="staffJoinAau" name="date_of_joining_aau" type="date" value="<?= up_e($staffRecord['date_of_joining_aau'] ?? '') ?>"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffJoinPost">Date of joining present post</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><input id="staffJoinPost" name="date_of_joining_present_post" type="date" value="<?= up_e($staffRecord['date_of_joining_present_post'] ?? '') ?>"<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>
                        <div class="up-field">
                            <label for="staffProfileLink">AAU profile link</label>
                            <div class="up-input-wrap"><i class="fa-solid fa-link" aria-hidden="true"></i><input id="staffProfileLink" name="profile_link" type="url" value="<?= up_e(trim((string) ($staffRecord['profile_link'] ?? '')) === '#' ? '' : ($staffRecord['profile_link'] ?? '')) ?>" maxlength="255" placeholder="https://..."<?= $canEditStaff ? '' : ' disabled' ?>></div>
                        </div>

                        <div class="up-divider"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Administrator-managed information</div>
                        <div class="up-admin-meta">
                            <div class="up-admin-meta__item"><span>Record ID</span><strong>#<?= (int) ($staffRecord['id'] ?? 0) ?></strong></div>
                            <div class="up-admin-meta__item"><span>Staff group</span><strong><?= up_e(ucwords(str_replace('_', ' ', (string) ($staffRecord['group_type'] ?? 'kvk')))) ?></strong></div>
                            <div class="up-admin-meta__item"><span>Status</span><strong><?= (int) ($staffRecord['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?></strong></div>
                            <div class="up-admin-meta__item"><span>Display order</span><strong><?= (int) ($staffRecord['sort_order'] ?? 100) ?></strong></div>
                            <div class="up-admin-meta__item"><span>Created</span><strong><?= up_e($staffRecord['created_at'] ?? 'Not available') ?></strong></div>
                            <?php if (trim((string) ($staffRecord['inactive_reason'] ?? '')) !== ''): ?>
                                <div class="up-admin-meta__item"><span>Inactive reason</span><strong><?= up_e($staffRecord['inactive_reason']) ?></strong></div>
                            <?php endif; ?>
                            <?php if (trim((string) ($staffRecord['inactive_order_no'] ?? '')) !== ''): ?>
                                <div class="up-admin-meta__item"><span>Order number</span><strong><?= up_e($staffRecord['inactive_order_no']) ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($canEditStaff): ?>
                            <div class="up-divider"><i class="fa-solid fa-key" aria-hidden="true"></i> Confirm this update</div>
                            <div class="up-field up-field--full">
                                <label for="staffCurrentPassword">Current account password <span aria-hidden="true">*</span></label>
                                <div class="up-input-wrap"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="staffCurrentPassword" name="staff_current_password" type="password" maxlength="4096" autocomplete="current-password" required></div>
                                <small>Required before staff-table data can be changed.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canEditStaff): ?>
                        <div class="up-actions">
                            <a class="up-btn" href="staff.php" target="_blank" rel="noopener">View public staff page</a>
                            <button class="up-btn up-btn--primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Update staff information</button>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="up-match-message">
                    <i class="fa-solid <?= $staffMatchState === 'ambiguous' ? 'fa-code-compare' : ($staffMatchState === 'unavailable' ? 'fa-database' : 'fa-magnifying-glass') ?>" aria-hidden="true"></i>
                    <?php if ($staffMatchState === 'ambiguous'): ?>
                        More than one staff record matches this email or name. Ask the administrator to remove the duplicate before self-service editing is enabled.
                    <?php elseif ($staffMatchState === 'conflict'): ?>
                        The account email and name point to different staff records. Ask the administrator to correct the identity details before self-service editing is enabled.
                    <?php elseif ($staffMatchState === 'unavailable'): ?>
                        The staff directory could not be checked at this time. Please try again later.
                    <?php else: ?>
                        No staff record uniquely matches <strong><?= up_e((string) $profile['name']) ?></strong> or <strong><?= up_e((string) $profile['email']) ?></strong>. Ask the administrator to correct the user or staff identity details.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="assets/js/user-mis.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
