<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/training_registration.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_admin();

$trainingModuleError = null;
try {
    training_registration_ensure_tables();
} catch (Throwable $exception) {
    error_log('Training module startup failed: ' . $exception->getMessage());
    $trainingModuleError = 'Training Registration could not connect to or prepare the existing database. Please check app/db.php and the database tables.';
}

$page_title = 'Training Farmer Registration';
$flash = $_SESSION['training_admin_flash'] ?? null;
unset($_SESSION['training_admin_flash']);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $trainingId = filter_input(INPUT_GET, 'training', FILTER_VALIDATE_INT) ?: 0;
    $training = training_get_by_id((int) $trainingId);
    if (!$training) {
        http_response_code(404);
        exit('Training not found.');
    }
    $participants = training_participants((int) $trainingId);
    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $training['training_code']) . '_participants.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sl. No.', 'Registration No.', 'Name', 'Village', 'District', 'Pincode', 'Gender', 'Age', 'Caste', 'Category', 'Aadhaar', 'Mobile No.', 'Source', 'Registered At']);
    foreach ($participants as $i => $row) {
        fputcsv($out, [
            $i + 1,
            $row['registration_no'] ?? '',
            $row['name'] ?? '',
            $row['village'] ?? '',
            $row['district'] ?? '',
            $row['pincode'] ?? '',
            $row['gender'] ?? '',
            $row['age'] ?? '',
            $row['caste'] ?? '',
            $row['category'] ?? '',
            training_masked_aadhaar($row['aadhaar_last4'] ?? null),
            $row['mobile_no'] ?? '',
            $row['registration_source'] ?? '',
            $row['created_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = is_scalar($_POST['_token'] ?? null) ? (string) $_POST['_token'] : '';
    if (!csrf_valid($token)) {
        $_SESSION['training_admin_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
        header('Location: training-register.php', true, 303);
        exit;
    }

    $action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
    if ($action === 'create_training') {
        [$ok, $message, $newId] = training_create($_POST, isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null);
        $_SESSION['training_admin_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        $location = 'training-register.php' . ($ok && $newId ? '?qr=' . (int) $newId : '');
        header('Location: ' . $location, true, 303);
        exit;
    }

    if ($action === 'update_training') {
        $trainingId = filter_var($_POST['training_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message] = training_update((int) $trainingId, $_POST);
        $_SESSION['training_admin_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        $location = $ok
            ? 'training-register.php?qr=' . (int) $trainingId
            : 'training-register.php?edit=' . (int) $trainingId . '#new-training';
        header('Location: ' . $location, true, 303);
        exit;
    }

    if ($action === 'delete_training') {
        $trainingId = filter_var($_POST['training_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message] = training_delete((int) $trainingId);
        $_SESSION['training_admin_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: training-register.php', true, 303);
        exit;
    }

    if ($action === 'update_participant') {
        $participantId = filter_var($_POST['participant_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message, $trainingId] = training_update_participant((int) $participantId, $_POST);
        $_SESSION['training_admin_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        $location = 'training-register.php?view=' . (int) $trainingId
            . ($ok ? '#participants' : '&participant_edit=' . (int) $participantId . '#participant-edit');
        header('Location: ' . $location, true, 303);
        exit;
    }

    if ($action === 'delete_participant') {
        $participantId = filter_var($_POST['participant_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message, $trainingId] = training_delete_participant((int) $participantId);
        $_SESSION['training_admin_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: training-register.php?view=' . (int) $trainingId . '#participants', true, 303);
        exit;
    }

    if ($action === 'toggle_status') {
        $trainingId = filter_var($_POST['training_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $ok = training_toggle_status((int) $trainingId);
        $_SESSION['training_admin_flash'] = [
            'type' => $ok ? 'success' : 'error',
            'message' => $ok ? 'Registration status updated.' : 'Unable to update registration status.',
        ];
        header('Location: training-register.php', true, 303);
        exit;
    }
}

$trainings = $trainingModuleError === null ? training_list_all() : [];
$editTrainingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editTraining = ($trainingModuleError === null && $editTrainingId) ? training_get_by_id((int) $editTrainingId) : null;
$qrTrainingId = filter_input(INPUT_GET, 'qr', FILTER_VALIDATE_INT) ?: 0;
$qrTraining = ($trainingModuleError === null && $qrTrainingId) ? training_get_by_id((int) $qrTrainingId) : null;
$viewTrainingId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
$viewTraining = ($trainingModuleError === null && $viewTrainingId) ? training_get_by_id((int) $viewTrainingId) : null;
$participants = $viewTraining ? training_participants((int) $viewTraining['id']) : [];
$participantEditId = filter_input(INPUT_GET, 'participant_edit', FILTER_VALIDATE_INT) ?: 0;
$editParticipant = ($trainingModuleError === null && $participantEditId)
    ? training_participant_get((int) $participantEditId)
    : null;
if ($editParticipant && $viewTraining && (int) $editParticipant['training_id'] !== (int) $viewTraining['id']) {
    $editParticipant = null;
}
$csrf = csrf_token();
$existingDatabaseName = $trainingModuleError === null ? training_existing_database_name() : '';

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/training-registration.css">
<link rel="stylesheet" href="assets/css/admin-responsive.css">

<div class="tr-module">
    <section class="tr-admin-hero">
        <div class="container tr-admin-hero__inner">
            <div>
                <span class="tr-kicker">Training Desk</span>
                <h1>Training Farmer Registration</h1>
                <p>Create a training once and instantly get a public registration form, QR code, WhatsApp share link and email share link.</p>
                <?php if ($existingDatabaseName !== ''): ?>
                    <p class="tr-db-note"><i class="fa-solid fa-database" aria-hidden="true"></i> Data is stored in your existing database: <strong><?= e($existingDatabaseName) ?></strong></p>
                <?php endif; ?>
            </div>
            <a class="tr-btn tr-btn--light" href="#new-training"><i class="fa-solid fa-plus"></i> Create Training</a>
        </div>
    </section>

    <main class="container tr-admin-space">
        <?php if ($trainingModuleError !== null): ?>
            <div class="tr-alert tr-alert--error">
                <strong>Training Module Error:</strong> <?= e($trainingModuleError) ?>
            </div>
        <?php endif; ?>

        <?php if (training_registration_is_local_environment()): ?>
            <div class="tr-alert tr-alert--error">
                <strong>Local testing mode:</strong>
                Registration links created here will use localhost and work only on this computer.
                After you upload this module to <strong>kvkkokrajhar.aau.ac.in</strong>, the same page will automatically create the live web link.
                Do not set <code>TRAINING_PUBLIC_URL</code> to the live website until these files are actually uploaded there.
            </div>
        <?php endif; ?>

        <?php if (is_array($flash)): ?>
            <div class="tr-alert tr-alert--<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'error' ?>">
                <?= e((string) ($flash['message'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <?php if ($qrTraining):
            $publicLink = training_registration_url($qrTraining, 'online');
            $qrLink = training_registration_url($qrTraining, 'qr');
            $shareText = 'Register for ' . $qrTraining['training_name'] . ' at KVK Kokrajhar: ' . $publicLink;
            $waLink = 'https://wa.me/?text=' . rawurlencode($shareText);
            $mailLink = 'mailto:?subject=' . rawurlencode('Training Registration - ' . $qrTraining['training_name']) . '&body=' . rawurlencode($shareText);
        ?>
            <section class="tr-share-card" id="training-share-card" data-share-title="<?= e((string) $qrTraining['training_name']) ?>" data-share-url="<?= e($publicLink) ?>">
                <div class="tr-share-copy">
                    <span class="tr-badge tr-badge--open">Registration Form Ready</span>
                    <h2><?= e((string) $qrTraining['training_name']) ?></h2>
                    <p><strong>Training No.: <?= e((string) $qrTraining['training_code']) ?></strong> · <?= e(date('d M Y', strtotime((string) $qrTraining['training_date']))) ?> · <?= e((string) $qrTraining['venue']) ?></p>
                    <label class="tr-label" for="registrationLink">Public web registration link</label>
                    <p class="tr-public-link-note"><i class="fa-solid fa-globe" aria-hidden="true"></i>
                        <?= training_registration_is_local_environment()
                            ? 'Local test link. Upload the module to the live website before sharing it with farmers.'
                            : 'Public web link ready for QR, WhatsApp and Email sharing.' ?>
                    </p>
                    <div class="tr-copy-row">
                        <input id="registrationLink" class="tr-input" value="<?= e($publicLink) ?>" readonly>
                        <button class="tr-btn tr-btn--primary" type="button" data-copy-target="#registrationLink"><i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                    <div class="tr-share-actions">
                        <a class="tr-btn tr-btn--whatsapp" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                        <a class="tr-btn tr-btn--secondary" href="<?= e($mailLink) ?>"><i class="fa-regular fa-envelope"></i> Email</a>
                        <button class="tr-btn tr-btn--secondary" type="button" id="nativeShareBtn"><i class="fa-solid fa-share-nodes"></i> Share</button>
                        <a class="tr-btn tr-btn--secondary" href="training-registration.php?t=<?= e((string) $qrTraining['registration_token']) ?>&src=admin" target="_blank" rel="noopener"><i class="fa-solid fa-user-plus"></i> Register Farmer</a>
                    </div>
                </div>
                <div class="tr-qr-panel">
                    <div id="trainingQr" class="tr-qr" data-qr-text="<?= e($qrLink) ?>"></div>
                    <strong>Scan to register</strong>
                    <small>Training No.: <?= e((string) $qrTraining['training_code']) ?></small>
                    <button class="tr-btn tr-btn--secondary tr-btn--small" type="button" id="printQrBtn"><i class="fa-solid fa-print"></i> Print QR</button>
                </div>
            </section>
        <?php endif; ?>

        <section class="tr-panel" id="new-training">
            <div class="tr-panel__heading tr-panel__heading--row">
                <div>
                    <span class="tr-kicker"><?= $editTraining ? 'Edit Training' : 'Step 1' ?></span>
                    <h2><?= $editTraining ? 'Edit Training Register' : 'Create Training Register' ?></h2>
                    <?php if ($editTraining): ?>
                        <p>Editing Training No. <strong><?= e((string) $editTraining['training_code']) ?></strong>. The Training No. and registration token will remain unchanged.</p>
                    <?php else: ?>
                        <p>After saving, the public farmer registration form and QR code are generated automatically.</p>
                    <?php endif; ?>
                </div>
                <?php if ($editTraining): ?>
                    <a class="tr-btn tr-btn--secondary" href="training-register.php#new-training"><i class="fa-solid fa-xmark"></i> Cancel Edit</a>
                <?php endif; ?>
            </div>

            <?php
                $formTrainingName = $editTraining ? (string) ($editTraining['training_name'] ?? '') : '';
                $formTrainingType = $editTraining ? (string) ($editTraining['training_type'] ?? '') : '';
                $formTrainingDate = $editTraining ? (string) ($editTraining['training_date'] ?? '') : '';
                $formVenue = $editTraining ? (string) ($editTraining['venue'] ?? '') : '';
                $formDeadline = '';
                if ($editTraining && !empty($editTraining['registration_deadline'])) {
                    $deadlineTimestamp = strtotime((string) $editTraining['registration_deadline']);
                    if ($deadlineTimestamp !== false) {
                        $formDeadline = date('Y-m-d\TH:i', $deadlineTimestamp);
                    }
                }
            ?>

            <form method="post" class="tr-form-grid tr-form-grid--admin" autocomplete="off">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editTraining ? 'update_training' : 'create_training' ?>">
                <?php if ($editTraining): ?>
                    <input type="hidden" name="training_id" value="<?= (int) $editTraining['id'] ?>">
                <?php endif; ?>

                <?php if ($editTraining): ?>
                    <div class="tr-field tr-field--wide">
                        <label>Training No.</label>
                        <input class="tr-input" type="text" value="<?= e((string) $editTraining['training_code']) ?>" readonly>
                        <small class="tr-help">Training No. is locked after creation to keep existing QR codes and official references consistent.</small>
                    </div>
                <?php endif; ?>

                <div class="tr-field tr-field--wide">
                    <label for="training_name">Name of the Training <span>*</span></label>
                    <input class="tr-input" id="training_name" name="training_name" type="text" maxlength="255" required value="<?= e($formTrainingName) ?>" placeholder="e.g. Scientific Pig Farming and Management">
                </div>

                <div class="tr-field">
                    <label for="training_type">Training Type <span>*</span></label>
                    <select class="tr-input" id="training_type" name="training_type" required>
                        <option value="">Select training type</option>
                        <?php foreach (training_types() as $type): ?>
                            <option value="<?= e($type) ?>"<?= $formTrainingType === $type ? ' selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tr-field">
                    <label for="training_date">Date <span>*</span></label>
                    <input class="tr-input" id="training_date" name="training_date" type="date" required value="<?= e($formTrainingDate) ?>">
                    <?php if (!$editTraining): ?>
                        <small class="tr-help">Training No. is generated from this month, e.g. TRN-2026-08-001.</small>
                    <?php endif; ?>
                </div>

                <div class="tr-field tr-field--wide">
                    <label for="venue">Venue <span>*</span></label>
                    <input class="tr-input" id="venue" name="venue" type="text" maxlength="255" required value="<?= e($formVenue) ?>" placeholder="KVK Kokrajhar / Village / Training Hall">
                </div>

                <div class="tr-field tr-field--wide">
                    <label for="registration_deadline">Registration Deadline <small>(optional)</small></label>
                    <input class="tr-input" id="registration_deadline" name="registration_deadline" type="datetime-local" value="<?= e($formDeadline) ?>">
                </div>

                <div class="tr-field tr-field--wide tr-form-actions">
                    <button class="tr-btn tr-btn--primary tr-btn--large" type="submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $editTraining ? 'Update Training' : 'Save & Create Registration Form' ?>
                    </button>
                    <?php if ($editTraining): ?>
                        <a class="tr-btn tr-btn--secondary tr-btn--large" href="training-register.php"><i class="fa-solid fa-ban"></i> Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="tr-panel">
            <div class="tr-panel__heading tr-panel__heading--row">
                <div>
                    <span class="tr-kicker">Step 2</span>
                    <h2>Training Register</h2>
                    <p>Manage registration links and view farmer registrations training-wise.</p>
                </div>
                <span class="tr-count-chip"><?= count($trainings) ?> Training<?= count($trainings) === 1 ? '' : 's' ?></span>
            </div>

            <div class="tr-table-wrap">
                <table class="tr-table">
                    <thead>
                        <tr>
                            <th>Training No.</th>
                            <th>Training</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$trainings): ?>
                        <tr><td colspan="8" class="tr-empty">No training has been created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trainings as $training): ?>
                            <tr>
                                <td><strong><?= e((string) $training['training_code']) ?></strong></td>
                                <td><strong><?= e((string) $training['training_name']) ?></strong></td>
                                <td><?= e((string) $training['training_type']) ?></td>
                                <td><?= e(date('d-m-Y', strtotime((string) $training['training_date']))) ?></td>
                                <td><?= e((string) $training['venue']) ?></td>
                                <td><span class="tr-number-pill"><?= (int) $training['participant_count'] ?></span></td>
                                <td><span class="tr-badge tr-badge--<?= ($training['registration_status'] ?? '') === 'open' ? 'open' : 'closed' ?>"><?= e(ucfirst((string) $training['registration_status'])) ?></span></td>
                                <td>
                                    <div class="tr-row-actions">
                                        <a class="tr-icon-link" href="?qr=<?= (int) $training['id'] ?>" title="QR & Share"><i class="fa-solid fa-qrcode"></i></a>
                                        <a class="tr-icon-link" href="?view=<?= (int) $training['id'] ?>#participants" title="Participants"><i class="fa-solid fa-users"></i></a>
                                        <a class="tr-icon-link" href="training-registration.php?t=<?= e((string) $training['registration_token']) ?>&src=admin" target="_blank" rel="noopener" title="Open Registration Form"><i class="fa-solid fa-up-right-from-square"></i></a>
                                        <a class="tr-icon-link tr-icon-link--edit" href="?edit=<?= (int) $training['id'] ?>#new-training" title="Edit Training"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="post" class="tr-inline-form" data-confirm="Delete <?= e((string) $training['training_code']) ?>? This is allowed only when no farmer registrations are linked to the training.">
                                            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete_training">
                                            <input type="hidden" name="training_id" value="<?= (int) $training['id'] ?>">
                                            <button class="tr-icon-link tr-icon-button tr-icon-link--delete" type="submit" title="Delete Training">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="tr-inline-form">
                                            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="training_id" value="<?= (int) $training['id'] ?>">
                                            <button class="tr-icon-link tr-icon-button" type="submit" title="<?= ($training['registration_status'] ?? '') === 'open' ? 'Close Registration' : 'Open Registration' ?>">
                                                <i class="fa-solid fa-<?= ($training['registration_status'] ?? '') === 'open' ? 'lock' : 'lock-open' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($viewTraining): ?>
        <section class="tr-panel" id="participants">
            <div class="tr-panel__heading tr-panel__heading--row">
                <div>
                    <span class="tr-kicker">Participant Register</span>
                    <h2><?= e((string) $viewTraining['training_name']) ?></h2>
                    <p>Training No.: <strong><?= e((string) $viewTraining['training_code']) ?></strong> · <?= e(date('d M Y', strtotime((string) $viewTraining['training_date']))) ?> · <?= e((string) $viewTraining['venue']) ?></p>
                </div>
                <div class="tr-share-actions">
                    <a class="tr-btn tr-btn--secondary" href="?export=csv&training=<?= (int) $viewTraining['id'] ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
                    <button class="tr-btn tr-btn--secondary" type="button" data-print-page><i class="fa-solid fa-print"></i> Print</button>
                </div>
            </div>

            <?php if ($editParticipant): ?>
            <div class="tr-participant-edit" id="participant-edit">
                <div class="tr-panel__heading tr-panel__heading--row">
                    <div>
                        <span class="tr-kicker">Edit Participant</span>
                        <h3><?= e((string) $editParticipant['registration_no']) ?></h3>
                        <p>Update participant details. Registration No. will remain unchanged.</p>
                    </div>
                    <a class="tr-btn tr-btn--secondary" href="?view=<?= (int) $viewTraining['id'] ?>#participants">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                </div>

                <form method="post" class="tr-form-grid tr-form-grid--admin" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="update_participant">
                    <input type="hidden" name="participant_id" value="<?= (int) $editParticipant['id'] ?>">

                    <div class="tr-field tr-field--wide">
                        <label>Participant Name <span>*</span></label>
                        <input class="tr-input" type="text" name="name" maxlength="150" required value="<?= e((string) $editParticipant['name']) ?>">
                    </div>

                    <div class="tr-field">
                        <label>Village <span>*</span></label>
                        <input class="tr-input" type="text" name="village" maxlength="150" required value="<?= e((string) $editParticipant['village']) ?>">
                    </div>

                    <div class="tr-field">
                        <label>District <span>*</span></label>
                        <input class="tr-input" type="text" name="district" maxlength="120" required value="<?= e((string) $editParticipant['district']) ?>">
                    </div>

                    <div class="tr-field">
                        <label>PIN Code <span>*</span></label>
                        <input class="tr-input" type="text" name="pincode" maxlength="6" inputmode="numeric" required value="<?= e((string) $editParticipant['pincode']) ?>">
                    </div>

                    <div class="tr-field">
                        <label>Gender <span>*</span></label>
                        <select class="tr-input" name="gender" required>
                            <?php foreach (training_gender_options() as $option): ?>
                                <option value="<?= e($option) ?>"<?= (string) $editParticipant['gender'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tr-field">
                        <label>Age <span>*</span></label>
                        <input class="tr-input" type="number" name="age" min="12" max="100" required value="<?= (int) $editParticipant['age'] ?>">
                    </div>

                    <div class="tr-field">
                        <label>Caste <span>*</span></label>
                        <select class="tr-input" name="caste" required>
                            <?php foreach (training_caste_options() as $option): ?>
                                <option value="<?= e($option) ?>"<?= (string) $editParticipant['caste'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tr-field">
                        <label>Category <span>*</span></label>
                        <select class="tr-input" name="category" required>
                            <?php foreach (training_category_options() as $option): ?>
                                <option value="<?= e($option) ?>"<?= (string) $editParticipant['category'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tr-field">
                        <label>Aadhaar No. <small>(optional)</small></label>
                        <input class="tr-input" type="text" name="aadhaar_no"
                               maxlength="12" minlength="12" inputmode="numeric"
                               pattern="[0-9]{12}" autocomplete="off"
                               value="<?= e((string) ($editParticipant['aadhaar_no'] ?? '')) ?>"
                               placeholder="123456789012"
                               data-aadhaar-digits-only>
                        <small class="tr-help">
                            <?= !empty($editParticipant['aadhaar_no'])
                                ? 'Enter exactly 12 numbers only. Existing Aadhaar is shown here for authenticated Admin editing.'
                                : (!empty($editParticipant['aadhaar_last4'])
                                    ? 'Older record: enter the full 12-digit Aadhaar once if you want to update the stored value.'
                                    : 'Optional. Enter exactly 12 numbers only; no spaces or hyphens.') ?>
                        </small>
                    </div>

                    <div class="tr-field">
                        <label>Mobile No. <span>*</span></label>
                        <input class="tr-input" type="tel" name="mobile_no" maxlength="10" inputmode="numeric" required value="<?= e((string) $editParticipant['mobile_no']) ?>">
                    </div>

                    <div class="tr-field tr-field--wide">
                        <label>Guardian Name <small>(only if below 18 years)</small></label>
                        <input class="tr-input" type="text" name="guardian_name" maxlength="150" value="<?= e((string) ($editParticipant['guardian_name'] ?? '')) ?>">
                    </div>

                    <div class="tr-field tr-field--wide tr-form-actions">
                        <button class="tr-btn tr-btn--primary" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i> Update Participant
                        </button>
                        <a class="tr-btn tr-btn--secondary" href="?view=<?= (int) $viewTraining['id'] ?>#participants">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="tr-search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input class="tr-input" id="participantSearch" type="search" placeholder="Search name, mobile, village, district, category...">
            </div>

            <div class="tr-table-wrap">
                <table class="tr-table" id="participantTable">
                    <thead>
                        <tr>
                            <th>Sl.</th>
                            <th>Reg. No.</th>
                            <th>Participant</th>
                            <th>Address</th>
                            <th>Gender / Age</th>
                            <th>Caste</th>
                            <th>Category</th>
                            <th>Aadhaar</th>
                            <th>Mobile</th>
                            <th>Photo</th>
                            <th>Source</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$participants): ?>
                        <tr><td colspan="12" class="tr-empty">No farmer has registered for this training yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($participants as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= e((string) $row['registration_no']) ?></strong></td>
                                <td><?= e((string) $row['name']) ?></td>
                                <td><?= e((string) $row['village']) ?>, <?= e((string) $row['district']) ?> - <?= e((string) $row['pincode']) ?></td>
                                <td><?= e((string) $row['gender']) ?> / <?= (int) $row['age'] ?></td>
                                <td><?= e((string) $row['caste']) ?></td>
                                <td><?= e((string) $row['category']) ?></td>
                                <td><?= e(training_masked_aadhaar($row['aadhaar_last4'] ?? null)) ?></td>
                                <td><a href="tel:<?= e((string) $row['mobile_no']) ?>"><?= e((string) $row['mobile_no']) ?></a></td>
                                <td>
                                    <?php if (!empty($row['photo_path'])): ?>
                                        <a class="tr-photo-link" href="training-photo.php?id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><i class="fa-regular fa-image"></i> View</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= e(ucfirst((string) $row['registration_source'])) ?></td>
                                <td>
                                    <div class="tr-row-actions">
                                        <a class="tr-icon-link tr-icon-link--edit"
                                           href="?view=<?= (int) $viewTraining['id'] ?>&participant_edit=<?= (int) $row['id'] ?>#participant-edit"
                                           title="Edit Participant">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="post" class="tr-inline-form"
                                              data-confirm="Delete participant <?= e((string) $row['registration_no']) ?>? This action cannot be undone.">
                                            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete_participant">
                                            <input type="hidden" name="participant_id" value="<?= (int) $row['id'] ?>">
                                            <button class="tr-icon-link tr-icon-button tr-icon-link--delete" type="submit" title="Delete Participant">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<script src="assets/vendor/qrcode.min.js" defer></script>
<script src="assets/js/training-admin.js" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
