<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/training-registration-lib.php';
if (!admin_logged_in() && !user_logged_in()) { header('Location: login.php'); exit; }
$pdo = db();
farmer_registration_ensure_tables($pdo);
$deskMessage = '';
$deskError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['desk_action'] ?? '') === 'create_programme') {
    $name = trim((string) ($_POST['training_name'] ?? ''));
    $from = trim((string) ($_POST['duration_from'] ?? ''));
    $to = trim((string) ($_POST['duration_to'] ?? ''));
    $location = trim((string) ($_POST['training_location'] ?? ''));
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $deskError = 'Your session expired. Please try again.';
    } elseif (!$pdo || $name === '' || $from === '' || $to === '' || $location === '' || $to < $from) {
        $deskError = 'Enter the programme name, valid dates, and location.';
    } else {
        try {
            $pdo->beginTransaction();
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') . '-' . date('YmdHis');
            $details = json_encode(['type' => 'training_record', 'data' => ['training_name' => $name, 'duration_from' => $from, 'duration_to' => $to, 'training_location' => $location]], JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare('INSERT INTO activities (title, slug, summary, details, is_active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$name, $slug, 'Training programme at ' . $location, $details]);
            $activityId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO training_records (activity_id, training_name, training_date, training_location, duration_from, duration_to, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$activityId, $name, $from, $location, $from, $to]);
            $pdo->commit();
            header('Location: training-registration-desk.php?programme=' . $activityId . '&created=1');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $deskError = 'The training programme could not be created. Please check the database setup.';
        }
    }
}
$programmes = farmer_training_programmes($pdo);
$selectedId = (int) ($_GET['programme'] ?? ($programmes[0]['id'] ?? 0));
$selected = farmer_training_programme($pdo, $selectedId);
$registrations = [];
if ($selected && $pdo) { $stmt=$pdo->prepare('SELECT * FROM farmer_training_registrations WHERE activity_id = ? ORDER BY created_at DESC'); $stmt->execute([$selectedId]); $registrations=$stmt->fetchAll(); }
$url = $selected ? farmer_registration_url($selectedId) : '';
$whatsapp = $url ? 'https://wa.me/?text=' . rawurlencode('Farmer registration for ' . ($selected['training_name'] ?: $selected['title']) . ': ' . $url) : '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Training Registration Desk</title>
<style>body{margin:0;background:#f4f7f3;color:#24372c;font-family:Arial,sans-serif}.wrap{max-width:1200px;margin:30px auto;padding:16px}.card{background:white;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 8px 28px #183b2018}h1,h2{color:#175f36}.tools{display:flex;gap:10px;flex-wrap:wrap;align-items:end}label{font-weight:700}select,input{display:block;padding:11px;border:1px solid #b9c8bc;border-radius:8px;min-width:280px;margin-top:6px}.btn{display:inline-block;background:#176536;color:white;text-decoration:none;padding:12px 16px;border-radius:9px;font-weight:700}.btn.alt{background:#257b35}.url{word-break:break-all;background:#f0f5ef;padding:12px;border-radius:8px}table{border-collapse:collapse;width:100%;min-width:900px}th,td{padding:10px;border-bottom:1px solid #dce5dd;text-align:left}th{background:#edf5ed}.scroll{overflow:auto}.photo{width:52px;height:52px;object-fit:cover;border-radius:6px}.muted{color:#65746b}</style></head><body><main class="wrap"><h1>Training Registration Desk</h1>
<section class="card"><h2>Register a Training Programme</h2><?php if(isset($_GET['created'])): ?><p class="url">Training programme created. Its farmer registration form is ready below.</p><?php endif; ?><?php if($deskError): ?><p class="url"><?= e($deskError) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="desk_action" value="create_programme"><div class="tools"><label>Programme name<input name="training_name" required></label><label>From date<input type="date" name="duration_from" required></label><label>To date<input type="date" name="duration_to" required></label><label>Venue / location<input name="training_location" required></label><button class="btn" type="submit">Create Programme & Form</button></div></form></section>
<section class="card"><form method="get" class="tools"><label>Training programme<select name="programme" onchange="this.form.submit()"><?php foreach($programmes as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===$selectedId?'selected':'' ?>><?= e((string)($p['training_name'] ?: $p['title'])) ?></option><?php endforeach; ?></select></label></form>
<?php if (!$selected): ?><p class="muted">No training programme is available. Create a Training record in the Activities Desk first.</p><?php else: ?><p class="url" id="registrationUrl"><?= e($url) ?></p><div class="tools"><a class="btn" href="<?= e($url) ?>" target="_blank">Open Form</a><a class="btn alt" href="<?= e($whatsapp) ?>" target="_blank">Share on WhatsApp</a><button class="btn" type="button" onclick="navigator.clipboard.writeText(<?= htmlspecialchars(json_encode($url), ENT_QUOTES) ?>)">Copy Link</button></div>
<p><img width="220" height="220" alt="Registration QR code" src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&amp;data=<?= rawurlencode($url) ?>"></p><?php endif; ?></section>
<?php if ($selected): ?><section class="card"><h2>Registered Farmers (<?= count($registrations) ?>)</h2><div class="scroll"><table><thead><tr><th>Name</th><th>Age</th><th>Address</th><th>Aadhaar</th><th>Photo</th><th>Registered</th></tr></thead><tbody><?php foreach($registrations as $r): ?><tr><td><?= e((string)$r['farmer_name']) ?></td><td><?= (int)$r['age'] ?></td><td><?= e($r['village'].', '.$r['district'].', '.$r['state'].' - '.$r['pincode']) ?></td><td><?= e(farmer_mask_aadhaar((string)$r['aadhaar_no'])) ?></td><td><a href="<?= e((string)$r['photo_path']) ?>" target="_blank"><img class="photo" src="<?= e((string)$r['photo_path']) ?>" alt="Farmer photo"></a></td><td><?= e((string)$r['created_at']) ?></td></tr><?php endforeach; ?><?php if(!$registrations): ?><tr><td colspan="6" class="muted">No farmer registrations received yet.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
</main></body></html>
