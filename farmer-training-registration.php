<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/training-registration-lib.php';

$pdo = db();
farmer_registration_ensure_tables($pdo);
$programmeId = (int) ($_GET['programme'] ?? $_POST['programme_id'] ?? 0);
$programme = farmer_training_programme($pdo, $programmeId);
$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && $programme
    && (!auth_request_is_same_origin() || !csrf_valid($_POST['csrf_token'] ?? null))
) {
    $error = 'Your form session expired. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $programme) {
    $name = trim((string) ($_POST['farmer_name'] ?? ''));
    $age = (int) ($_POST['age'] ?? 0);
    $village = trim((string) ($_POST['village'] ?? ''));
    $district = trim((string) ($_POST['district'] ?? ''));
    $state = trim((string) ($_POST['state'] ?? ''));
    $pincode = preg_replace('/\D+/', '', (string) ($_POST['pincode'] ?? ''));
    $aadhaar = preg_replace('/\D+/', '', (string) ($_POST['aadhaar_no'] ?? ''));
    $photo = $_FILES['photo'] ?? [];
    if ($name === '' || $age < 18 || $age > 120 || $village === '' || $district === '' || $state === '') {
        $error = 'Please complete all fields and enter a valid age.';
    } elseif (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
        $error = 'Please enter a valid 6-digit pincode.';
    } elseif (!preg_match('/^[2-9][0-9]{11}$/', $aadhaar)) {
        $error = 'Please enter a valid 12-digit Aadhaar number.';
    } elseif (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($photo['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Please upload a farmer photo up to 5 MB.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $photo['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            $error = 'Photo must be JPG, PNG, or WebP.';
        } else {
            $folder = UPLOAD_DIR . '/farmer-registrations/' . $programmeId;
            if (!is_dir($folder)) mkdir($folder, 0775, true);
            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            $relative = UPLOAD_URL . '/farmer-registrations/' . $programmeId . '/' . $filename;
            if (!move_uploaded_file((string) $photo['tmp_name'], $folder . '/' . $filename)) {
                $error = 'Photo upload failed. Please try again.';
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO farmer_training_registrations (activity_id, farmer_name, age, village, district, state, pincode, aadhaar_no, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$programmeId, $name, $age, $village, $district, $state, $pincode, $aadhaar, $relative]);
                    $saved = true;
                } catch (Throwable $exception) {
                    @unlink($folder . '/' . $filename);
                    $error = 'Registration could not be saved. Please contact KVK.';
                }
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Farmer Training Registration</title>
<style>body{margin:0;background:#f3f7f1;color:#203529;font-family:Arial,sans-serif}.wrap{max-width:760px;margin:32px auto;padding:16px}.card{background:#fff;border-radius:18px;padding:26px;box-shadow:0 10px 35px #183b2020}h1{color:#176536;margin-top:0}.programme{background:#eef8ed;border-left:5px solid #2b8a4b;padding:14px;margin:0 0 20px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{display:block;font-weight:700}input{box-sizing:border-box;width:100%;margin-top:7px;padding:12px;border:1px solid #b9c9bd;border-radius:9px;font-size:16px}.wide{grid-column:1/-1}button{border:0;border-radius:10px;padding:14px 22px;background:#176536;color:white;font-size:16px;font-weight:700;cursor:pointer}.error,.success{padding:14px;border-radius:9px;margin:14px 0}.error{background:#fff0ee;color:#912b20}.success{background:#eaf8ed;color:#176536}@media(max-width:600px){.grid{grid-template-columns:1fr}.wide{grid-column:auto}.wrap{margin:8px auto}.card{padding:18px}}</style></head><body><main class="wrap"><section class="card">
<h1>Farmer Training Registration</h1>
<?php if (!$programme): ?><div class="error">This training programme is unavailable or the registration link is invalid.</div>
<?php else: ?><div class="programme"><strong><?= e((string) ($programme['training_name'] ?: $programme['title'])) ?></strong><br><?= e((string) $programme['duration_from']) ?> to <?= e((string) $programme['duration_to']) ?> · <?= e((string) $programme['training_location']) ?></div>
<?php if ($saved): ?><div class="success"><strong>Registration completed.</strong><br>Your details have been submitted to KVK Kokrajhar.</div>
<?php else: ?><?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" accept-charset="UTF-8"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="programme_id" value="<?= $programmeId ?>"><div class="grid">
<label class="wide">Name of the Farmer<input name="farmer_name" maxlength="160" required value="<?= e((string) ($_POST['farmer_name'] ?? '')) ?>"></label>
<label>Age<input type="number" name="age" min="18" max="120" required value="<?= e((string) ($_POST['age'] ?? '')) ?>"></label>
<label>Village<input name="village" maxlength="160" required value="<?= e((string) ($_POST['village'] ?? '')) ?>"></label>
<label>District<input name="district" maxlength="160" required value="<?= e((string) ($_POST['district'] ?? '')) ?>"></label>
<label>State<input name="state" maxlength="160" required value="<?= e((string) ($_POST['state'] ?? 'Assam')) ?>"></label>
<label>Pincode<input name="pincode" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" required value="<?= e((string) ($_POST['pincode'] ?? '')) ?>"></label>
<label>Aadhaar No.<input name="aadhaar_no" inputmode="numeric" pattern="[2-9][0-9]{11}" maxlength="12" autocomplete="off" required value="<?= e((string) ($_POST['aadhaar_no'] ?? '')) ?>"></label>
<label class="wide">Farmer Photo (JPG, PNG or WebP; maximum 5 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" required></label>
<div class="wide"><button type="submit">Submit Registration</button></div></div></form><?php endif; ?><?php endif; ?>
</section></main></body></html>
