<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/training_registration.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

$trainingModuleError = null;
try {
    training_registration_ensure_tables();
} catch (Throwable $exception) {
    error_log('Public training registration startup failed: ' . $exception->getMessage());
    $trainingModuleError = 'The training registration service is temporarily unavailable because the database module could not be prepared.';
}

$token = is_scalar($_GET['t'] ?? null) ? strtolower(trim((string) $_GET['t'])) : '';
if ($trainingModuleError !== null) {
    http_response_code(503);
    $training = null;
} elseif (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    $training = null;
} else {
    $training = training_get_by_token($token);
    if (!$training) {
        http_response_code(404);
    }
}

$page_title = $training ? 'Register - ' . (string) $training['training_name'] : 'Training Registration';
$source = is_scalar($_GET['src'] ?? null) ? strtolower(trim((string) $_GET['src'])) : 'online';
if ($source === 'admin' && !admin_logged_in()) {
    $source = 'online';
}
if (!in_array($source, ['online', 'qr', 'admin'], true)) {
    $source = 'online';
}

$errors = [];
$success = $_SESSION['training_public_success'] ?? null;
unset($_SESSION['training_public_success']);
$formData = [
    'name' => '', 'village' => '', 'district' => 'Kokrajhar', 'pincode' => '', 'gender' => '', 'age' => '',
    'caste' => '', 'category' => '', 'mobile_no' => '', 'guardian_name' => '',
];

if ($training && !isset($_SESSION['training_form_started'][$token])) {
    $_SESSION['training_form_started'][$token] = time();
}

if ($training && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($formData) as $key) {
        $formData[$key] = training_clean_text($_POST[$key] ?? '', $key === 'name' || $key === 'guardian_name' ? 150 : 120);
    }

    $csrf = is_scalar($_POST['_token'] ?? null) ? (string) $_POST['_token'] : '';
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $started = (int) ($_SESSION['training_form_started'][$token] ?? 0);

    if (!training_same_origin_post_valid()) {
        $errors[] = 'Cross-site submission was blocked for your security.';
    }
    if (!csrf_valid($csrf)) {
        $errors[] = 'Your form session has expired. Please refresh the page and try again.';
    }
    if ($honeypot !== '') {
        $errors[] = 'The submission could not be verified.';
    }
    if ($started > 0 && time() - $started < 1) {
        $errors[] = 'Please review the form before submitting.';
    }
    if (!training_form_rate_limit_ok()) {
        $errors[] = 'Too many submissions were attempted. Please try again later.';
    }

    if (!$errors) {
        $_POST['registration_source'] = $source;
        [$ok, $registrationErrors, $receipt] = training_register_farmer($training, $_POST, $_FILES);
        if ($ok && is_array($receipt)) {
            $_SESSION['training_public_success'] = [
                'registration_no' => $receipt['registration_no'],
                'name' => $receipt['name'],
            ];
            $_SESSION['training_form_started'][$token] = time();
            header('Location: training-registration.php?t=' . rawurlencode($token) . '&src=' . rawurlencode($source), true, 303);
            exit;
        }
        $errors = array_merge($errors, $registrationErrors);
    }
}

$csrfToken = csrf_token();
$registrationOpen = $training ? training_registration_is_open($training) : false;

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/training-registration.css">

<div class="tr-module tr-public-page">
    <main class="container tr-public-space">
        <?php if (!$training): ?>
            <section class="tr-not-found">
                <div class="tr-not-found__icon"><i class="fa-solid fa-link-slash"></i></div>
                <?php if ($trainingModuleError !== null): ?>
                    <h1>Registration Service Unavailable</h1>
                    <p><?= e($trainingModuleError) ?></p>
                <?php else: ?>
                    <h1>Registration Link Not Found</h1>
                    <p>This training registration link is invalid or no longer available. Please contact KVK Kokrajhar for the correct registration link.</p>
                <?php endif; ?>
                <a class="tr-btn tr-btn--primary" href="./">Return to Home</a>
            </section>
        <?php else: ?>
            <section class="tr-training-banner">
                <div class="tr-training-banner__content">
                    <span class="tr-kicker">KVK Kokrajhar · Farmer Registration</span>
                    <h1><?= e((string) $training['training_name']) ?></h1>
                    <div class="tr-training-meta">
                        <span><i class="fa-solid fa-layer-group"></i><?= e((string) $training['training_type']) ?></span>
                        <span><i class="fa-regular fa-calendar"></i><?= e(date('d F Y', strtotime((string) $training['training_date']))) ?></span>
                        <span><i class="fa-solid fa-location-dot"></i><?= e((string) $training['venue']) ?></span>
                        <span><i class="fa-solid fa-hashtag"></i>Training No.: <?= e((string) $training['training_code']) ?></span>
                    </div>
                </div>
                <div class="tr-training-banner__status">
                    <span class="tr-badge tr-badge--<?= $registrationOpen ? 'open' : 'closed' ?>"><?= $registrationOpen ? 'Registration Open' : 'Registration Closed' ?></span>
                </div>
            </section>

            <?php if (is_array($success)): ?>
                <section class="tr-success-card">
                    <div class="tr-success-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div>
                        <span class="tr-kicker">Registration Successful</span>
                        <h2>Thank you, <?= e((string) ($success['name'] ?? 'Participant')) ?>.</h2>
                        <p>Your registration has been recorded successfully. Please keep your registration number for reference.</p>
                        <div class="tr-registration-number"><?= e((string) ($success['registration_no'] ?? '')) ?></div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="tr-alert tr-alert--error">
                    <strong>Please correct the following:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$registrationOpen): ?>
                <section class="tr-closed-card">
                    <i class="fa-solid fa-lock"></i>
                    <h2>Registration is closed</h2>
                    <p>Online registration for this training is currently closed. Please contact KVK Kokrajhar if you need assistance.</p>
                </section>
            <?php else: ?>
                <section class="tr-public-grid">
                    <div class="tr-form-card">
                        <div class="tr-form-card__heading">
                            <span class="tr-kicker">Participant Details</span>
                            <h2>Farmer Registration Form</h2>
                            <p>Fields marked with <span class="tr-required">*</span> are required.</p>
                        </div>

                        <form method="post" enctype="multipart/form-data" id="farmerRegistrationForm" autocomplete="on" novalidate>
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="registration_source" value="<?= e($source) ?>">
                            <input type="hidden" name="captured_photo" id="capturedPhotoInput" value="">
                            <div class="tr-honeypot" aria-hidden="true">
                                <label for="website">Website</label>
                                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="tr-form-grid">
                                <div class="tr-field tr-field--wide">
                                    <label for="name">Name <span>*</span></label>
                                    <input class="tr-input" type="text" id="name" name="name" maxlength="150" value="<?= e($formData['name']) ?>" required autocomplete="name" placeholder="Enter full name">
                                </div>

                                <div class="tr-section-label tr-field--wide"><i class="fa-solid fa-house"></i> Address</div>
                                <div class="tr-field">
                                    <label for="village">Village <span>*</span></label>
                                    <input class="tr-input" type="text" id="village" name="village" maxlength="150" value="<?= e($formData['village']) ?>" required placeholder="Village name">
                                </div>
                                <div class="tr-field">
                                    <label for="district">District <span>*</span></label>
                                    <input class="tr-input" type="text" id="district" name="district" maxlength="120" value="<?= e($formData['district']) ?>" required placeholder="District">
                                </div>
                                <div class="tr-field">
                                    <label for="pincode">PIN Code <span>*</span></label>
                                    <input class="tr-input" type="text" id="pincode" name="pincode" maxlength="6" inputmode="numeric" pattern="[1-9][0-9]{5}" value="<?= e($formData['pincode']) ?>" required placeholder="6-digit PIN">
                                </div>
                                <div class="tr-field">
                                    <label for="mobile_no">Mobile No. <span>*</span></label>
                                    <input class="tr-input" type="tel" id="mobile_no" name="mobile_no" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" value="<?= e($formData['mobile_no']) ?>" required autocomplete="tel" placeholder="10-digit mobile number">
                                </div>

                                <div class="tr-field">
                                    <label for="gender">Gender <span>*</span></label>
                                    <select class="tr-input" id="gender" name="gender" required>
                                        <option value="">Select</option>
                                        <?php foreach (training_gender_options() as $option): ?>
                                            <option value="<?= e($option) ?>"<?= $formData['gender'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tr-field">
                                    <label for="age">Age <span>*</span></label>
                                    <input class="tr-input" type="number" id="age" name="age" min="12" max="100" value="<?= e($formData['age']) ?>" required placeholder="Age">
                                </div>
                                <div class="tr-field">
                                    <label for="caste">Caste <span>*</span></label>
                                    <select class="tr-input" id="caste" name="caste" required>
                                        <option value="">Select</option>
                                        <?php foreach (training_caste_options() as $option): ?>
                                            <option value="<?= e($option) ?>"<?= $formData['caste'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tr-field">
                                    <label for="category">Category <span>*</span></label>
                                    <select class="tr-input" id="category" name="category" required>
                                        <option value="">Select</option>
                                        <?php foreach (training_category_options() as $option): ?>
                                            <option value="<?= e($option) ?>"<?= $formData['category'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="tr-field">
                                    <label for="aadhaar_no">Aadhaar No. <small>(optional)</small></label>
                                    <input class="tr-input" type="text" id="aadhaar_no" name="aadhaar_no"
                                           maxlength="12" minlength="12" inputmode="numeric"
                                           pattern="[0-9]{12}" autocomplete="off"
                                           placeholder="123456789012"
                                           aria-describedby="aadhaarHelp">
                                    <small class="tr-help" id="aadhaarHelp">Optional. Enter exactly 12 numbers only, without spaces or hyphens. Example: 123456789012.</small>
                                </div>

                                <div class="tr-field tr-field--wide tr-guardian" id="guardianSection" hidden>
                                    <div class="tr-guardian-box">
                                        <strong>Participant below 18 years</strong>
                                        <p>Guardian details and consent are required.</p>
                                        <label for="guardian_name">Guardian Name <span>*</span></label>
                                        <input class="tr-input" type="text" id="guardian_name" name="guardian_name" maxlength="150" value="<?= e($formData['guardian_name']) ?>" placeholder="Parent / guardian name">
                                        <label class="tr-check-row">
                                            <input type="checkbox" name="guardian_consent" value="1">
                                            <span>I am the parent/guardian and consent to this registration and processing of the participant's information for this training.</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tr-field tr-field--wide">
                                    <label>Passport-size Photo <small>(optional)</small></label>
                                    <div class="tr-photo-area">
                                        <div class="tr-photo-preview tr-photo-preview--passport" id="photoPreview">
                                            <i class="fa-regular fa-user"></i>
                                            <span>Optional photo</span>
                                        </div>
                                        <div class="tr-photo-actions">
                                            <label class="tr-btn tr-btn--primary" for="photo"><i class="fa-solid fa-camera"></i> Take Passport Photo</label>
                                            <input class="tr-file-input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" capture="user">
                                            <button class="tr-btn tr-btn--secondary" type="button" id="openCameraBtn"><i class="fa-solid fa-camera-retro"></i> Live Camera</button>
                                            <button class="tr-btn tr-btn--ghost" type="button" id="clearPhotoBtn" hidden>Remove Photo</button>
                                        </div>
                                    </div>
                                    <small class="tr-help"><strong>Photo is not compulsory.</strong> If added, it is automatically cropped to passport-style portrait format (700 × 900 px). Maximum source file size: 4 MB.</small>
                                </div>

                                <div class="tr-field tr-field--wide">
                                    <label class="tr-check-row tr-consent-row">
                                        <input type="checkbox" name="consent" value="1" required>
                                        <span>I consent to KVK Kokrajhar collecting and using the information submitted in this form solely for training registration, participant records, communication and programme reporting. <span class="tr-required">*</span></span>
                                    </label>
                                </div>

                                <div class="tr-field tr-field--wide tr-submit-row">
                                    <button class="tr-btn tr-btn--primary tr-btn--large" type="submit" id="registrationSubmitBtn"><i class="fa-solid fa-paper-plane"></i> Submit Registration</button>
                                    <p><i class="fa-solid fa-shield-halved"></i> Your registration is submitted securely to KVK Kokrajhar.</p>
                                </div>
                            </div>
                        </form>
                    </div>

                    <aside class="tr-info-card">
                        <div class="tr-info-icon"><i class="fa-solid fa-seedling"></i></div>
                        <h3>Before you submit</h3>
                        <ul>
                            <li>Enter your name and address correctly.</li>
                            <li>Use an active mobile number for training communication.</li>
                            <li>Photo is optional. If you add one, use the camera for a clear passport-style front-facing photograph.</li>
                            <li>Keep the registration number shown after submission.</li>
                            <li>Contact KVK Kokrajhar if any correction is required.</li>
                        </ul>
                        <div class="tr-privacy-note">
                            <i class="fa-solid fa-lock"></i>
                            <div><strong>Privacy-aware registration</strong><span>Aadhaar is encrypted in storage, masked in normal records, and participant photos are kept in protected storage.</span></div>
                        </div>
                    </aside>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<div class="tr-camera-modal" id="cameraModal" hidden aria-hidden="true">
    <div class="tr-camera-dialog" role="dialog" aria-modal="true" aria-labelledby="cameraTitle">
        <div class="tr-camera-head">
            <h2 id="cameraTitle">Take Passport-size Photo</h2>
            <button type="button" class="tr-camera-close" id="cameraCloseBtn" aria-label="Close camera"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="tr-passport-camera-frame">
            <video id="cameraVideo" autoplay playsinline muted></video>
            <span class="tr-passport-guide" aria-hidden="true"></span>
        </div>
        <p class="tr-camera-tip">Keep the face centred, look straight at the camera, and keep the head and shoulders inside the guide.</p>
        <canvas id="cameraCanvas" hidden></canvas>
        <div class="tr-camera-actions">
            <button type="button" class="tr-btn tr-btn--primary" id="capturePhotoBtn"><i class="fa-solid fa-camera"></i> Capture Photo</button>
        </div>
    </div>
</div>

<script src="assets/js/training-registration.js" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
