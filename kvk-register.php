<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/kvk_register.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) session_start();
require_admin();

$moduleError = null;
try {
    kvk_register_ensure_tables();
} catch (Throwable $exception) {
    error_log('KVK Register startup failed: ' . $exception->getMessage());
    $moduleError = 'KVK Register could not prepare the website database.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = is_scalar($_POST['_token'] ?? null) ? (string) $_POST['_token'] : '';
    if (!csrf_valid($token)) {
        $_SESSION['kvk_register_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
        header('Location: kvk-register.php', true, 303);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        [$ok, $message, $id] = kvk_programme_create($_POST, isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null);
        $_SESSION['kvk_register_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: kvk-register.php' . ($ok && $id ? '?created=' . (int) $id : '') . '#register-form', true, 303);
        exit;
    }
    if ($action === 'update') {
        $id = filter_var($_POST['programme_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message] = kvk_programme_update((int) $id, $_POST);
        $_SESSION['kvk_register_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: kvk-register.php' . ($ok ? '' : '?edit=' . (int) $id) . '#register-form', true, 303);
        exit;
    }
    if ($action === 'delete') {
        $id = filter_var($_POST['programme_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        [$ok, $message] = kvk_programme_delete((int) $id);
        $_SESSION['kvk_register_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: kvk-register.php', true, 303);
        exit;
    }
}

$categories = kvk_programme_categories();
$filterCategory = trim((string) ($_GET['category'] ?? ''));
if ($filterCategory !== '' && !isset($categories[$filterCategory])) $filterCategory = '';
$programmes = $moduleError === null ? kvk_programme_list($filterCategory ?: null) : [];
$counts = $moduleError === null ? kvk_programme_counts() : [];
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editing = ($moduleError === null && $editId) ? kvk_programme_find((int) $editId) : null;
$createdId = filter_input(INPUT_GET, 'created', FILTER_VALIDATE_INT) ?: 0;
$createdProgramme = ($moduleError === null && $createdId) ? kvk_programme_find((int) $createdId) : null;
$flash = $_SESSION['kvk_register_flash'] ?? null;
unset($_SESSION['kvk_register_flash']);
$csrf = csrf_token();
$totalProgrammes = array_sum($counts);
$trainingCount = (int) ($counts['training'] ?? 0);

$formCategory = (string) ($editing['activity_slug'] ?? ($filterCategory ?: 'training'));
$formTitle = (string) ($editing['title'] ?? '');
$formType = (string) ($editing['programme_type'] ?? '');
$formStart = (string) ($editing['start_date'] ?? '');
$formEnd = (string) ($editing['end_date'] ?? '');
$formVenue = (string) ($editing['venue'] ?? '');
$formStatus = (string) ($editing['status'] ?? 'planned');
$formDeadline = '';
if (!empty($editing['registration_deadline'])) {
    $timestamp = strtotime((string) $editing['registration_deadline']);
    if ($timestamp) $formDeadline = date('Y-m-d\TH:i', $timestamp);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KVK Register | <?= e(APP_SHORT_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root { --ink:#17384e; --muted:#687d8b; --green:#087a4c; --green2:#0a9660; --blue:#125076; --line:#d8e5e8; --gold:#f5c84b; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--ink); background:radial-gradient(circle at 8% 4%,rgba(10,135,84,.15),transparent 27rem),radial-gradient(circle at 94% 8%,rgba(18,80,118,.14),transparent 28rem),linear-gradient(180deg,#edf6f5,#f8fbf8 55%,#edf5e8); }
        a { text-decoration:none; }
        .kr-shell { width:min(1380px,calc(100% - 36px)); margin:auto; padding:24px 0 50px; }
        .kr-nav { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:14px; }
        .kr-crumb { margin:0; color:var(--muted); font-size:12px; font-weight:800; }
        .kr-back { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid var(--line); border-radius:10px; color:var(--blue); background:#fff; font-size:12px; font-weight:900; }
        .kr-hero { position:relative; overflow:hidden; display:grid; grid-template-columns:1fr auto; align-items:center; gap:24px; min-height:210px; padding:34px 38px; border-radius:18px; color:#fff; background:linear-gradient(120deg,#0c3f5c,#087a4c 68%,#0a9660); box-shadow:0 22px 54px rgba(20,72,96,.22); }
        .kr-hero:after { content:''; position:absolute; width:330px; height:330px; right:-85px; top:-170px; border:44px solid rgba(245,200,75,.16); border-radius:50%; }
        .kr-hero>* { position:relative; z-index:1; }
        .kr-kicker { margin:0 0 10px; color:#f8d875; font-size:11px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
        .kr-hero h1 { margin:0; font-size:clamp(32px,5vw,50px); letter-spacing:-.035em; }
        .kr-hero p:last-child { max-width:690px; margin:12px 0 0; color:rgba(255,255,255,.78); font-size:14px; font-weight:700; line-height:1.6; }
        .kr-stats { display:grid; grid-template-columns:repeat(2,120px); gap:10px; }
        .kr-stat { padding:17px; border:1px solid rgba(255,255,255,.18); border-radius:14px; background:rgba(255,255,255,.1); backdrop-filter:blur(8px); }
        .kr-stat span { display:block; color:rgba(255,255,255,.7); font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.07em; }
        .kr-stat strong { display:block; margin-top:6px; font-size:30px; }
        .kr-alert { margin:18px 0; padding:13px 16px; border-radius:10px; font-size:12px; font-weight:800; }
        .kr-alert--success { color:#075c39; border:1px solid #a9d8bf; background:#edf9f2; }
        .kr-alert--error { color:#8a2b2b; border:1px solid #efb5b5; background:#fff1f1; }
        .kr-created { display:flex; justify-content:space-between; align-items:center; gap:14px; margin:18px 0; padding:16px 18px; border:1px solid #d6c26f; border-radius:12px; background:#fffae8; }
        .kr-created h2,.kr-created p { margin:0; }.kr-created h2{font-size:16px}.kr-created p{margin-top:4px;color:#75652d;font-size:11px;font-weight:700}
        .kr-created a { padding:9px 12px; border-radius:8px; color:#fff; background:var(--blue); font-size:11px; font-weight:900; white-space:nowrap; }
        .kr-grid { display:grid; grid-template-columns:minmax(330px,.75fr) minmax(0,1.65fr); gap:18px; margin-top:20px; align-items:start; }
        .kr-panel { border:1px solid var(--line); border-radius:15px; background:rgba(255,255,255,.95); box-shadow:0 14px 34px rgba(37,72,91,.08); }
        .kr-panel__head { display:flex; justify-content:space-between; gap:14px; padding:20px 22px 15px; border-bottom:1px solid #e8eff1; }
        .kr-panel__head h2,.kr-panel__head p { margin:0; }.kr-panel__head h2{font-size:19px}.kr-panel__head p{margin-top:5px;color:var(--muted);font-size:11px;font-weight:700;line-height:1.45}
        .kr-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; padding:20px 22px 23px; }
        .kr-field { display:grid; gap:6px; }.kr-field--wide{grid-column:1/-1}
        .kr-field label { font-size:11px; font-weight:900; }.kr-field label span{color:#b33b32}
        .kr-field input,.kr-field select { width:100%; min-height:43px; padding:10px 11px; border:1px solid #ccdce1; border-radius:9px; color:var(--ink); background:#fff; font:inherit; font-size:12px; }
        .kr-field input:focus,.kr-field select:focus { outline:3px solid rgba(10,135,84,.12); border-color:var(--green); }
        .kr-help { color:var(--muted); font-size:9px; font-weight:700; line-height:1.35; }
        .kr-actions { display:flex; flex-wrap:wrap; gap:9px; grid-column:1/-1; padding-top:3px; }
        .kr-btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 14px; border:0; border-radius:9px; color:#fff; background:linear-gradient(135deg,#06663f,var(--green2)); font:inherit; font-size:11px; font-weight:900; cursor:pointer; }
        .kr-btn--secondary { color:var(--blue); border:1px solid #cbdde5; background:#eef6fb; }
        .kr-filter { display:flex; flex-wrap:wrap; gap:8px; padding:16px 20px; border-bottom:1px solid #e8eff1; }
        .kr-filter a { padding:7px 10px; border:1px solid #d6e3e6; border-radius:999px; color:#5d7481; background:#f8fbfb; font-size:9px; font-weight:900; }
        .kr-filter a.is-active { color:#fff; border-color:var(--green); background:var(--green); }
        .kr-table-wrap { overflow:auto; }.kr-table{width:100%;min-width:920px;border-collapse:collapse}.kr-table th{padding:11px 12px;color:#fff;background:#125076;font-size:9px;text-align:left;text-transform:uppercase;letter-spacing:.04em}.kr-table td{padding:12px;border-bottom:1px solid #e7eef0;color:#536b78;font-size:10px;vertical-align:middle}.kr-table tbody tr:hover{background:#f7fbf9}.kr-table strong{color:var(--ink)}
        .kr-code { display:inline-block;padding:5px 7px;border-radius:6px;color:#075c39;background:#eaf7f0;font-size:9px;font-weight:900; }
        .kr-status { display:inline-block;padding:5px 8px;border-radius:999px;color:#17613f;background:#eaf7f0;font-size:9px;font-weight:900;text-transform:capitalize}.kr-status--completed{color:#315d7a;background:#eaf2f7}.kr-status--cancelled{color:#8b3232;background:#faeaea}
        .kr-row-actions { display:flex; flex-wrap:wrap; gap:5px; }.kr-row-actions a,.kr-row-actions button{padding:6px 8px;border:1px solid #d3e1e5;border-radius:6px;color:var(--blue);background:#f4f9fb;font:inherit;font-size:9px;font-weight:900;cursor:pointer;white-space:nowrap}.kr-row-actions form{display:inline}.kr-row-actions .delete{color:#9a3333;border-color:#ecd0d0;background:#fff4f4}
        .kr-empty { padding:38px!important;text-align:center;color:var(--muted)!important;font-weight:800; }
        @media(max-width:1000px){.kr-grid{grid-template-columns:1fr}.kr-hero{grid-template-columns:1fr}.kr-stats{width:min(100%,300px)}}
        @media(max-width:620px){.kr-shell{width:min(100% - 24px,1380px);padding-top:14px}.kr-crumb{display:none}.kr-nav{justify-content:flex-end}.kr-hero{padding:26px 22px;border-radius:14px}.kr-stats{grid-template-columns:repeat(2,minmax(0,1fr));width:100%}.kr-form{grid-template-columns:1fr}.kr-field--wide{grid-column:auto}.kr-created{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<main class="kr-shell">
    <nav class="kr-nav"><p class="kr-crumb">Admin Dashboard / Activities Desk / KVK Register</p><a class="kr-back" href="admin/activities-desk.php">&larr; Back to Activities Desk</a></nav>
    <header class="kr-hero">
        <div><p class="kr-kicker">One programme, one official source</p><h1>KVK Register</h1><p>Register every KVK programme here first. Each Activities card then receives only the programmes registered under its category. Training programmes also connect to farmer registration, QR sharing, and participant records.</p></div>
        <div class="kr-stats"><div class="kr-stat"><span>All programmes</span><strong><?= e((string) $totalProgrammes) ?></strong></div><div class="kr-stat"><span>Trainings</span><strong><?= e((string) $trainingCount) ?></strong></div></div>
    </header>

    <?php if ($moduleError): ?><div class="kr-alert kr-alert--error"><?= e($moduleError) ?></div><?php endif; ?>
    <?php if (is_array($flash)): ?><div class="kr-alert kr-alert--<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'error' ?>"><?= e((string) ($flash['message'] ?? '')) ?></div><?php endif; ?>
    <?php if ($createdProgramme && (string) $createdProgramme['activity_slug'] === 'training' && (int) ($createdProgramme['training_programme_id'] ?? 0) > 0): ?>
        <section class="kr-created"><div><h2>Training is ready for farmer registration</h2><p>The programme is stored in KVK Register and has also been added to the Training registration system.</p></div><a href="training-register.php?qr=<?= (int) $createdProgramme['training_programme_id'] ?>">Open QR &amp; Farmer Registration</a></section>
    <?php endif; ?>

    <div class="kr-grid">
        <section class="kr-panel" id="register-form">
            <div class="kr-panel__head"><div><h2><?= $editing ? 'Edit programme' : 'Register a programme' ?></h2><p><?= $editing ? 'The activity card is locked to preserve existing links.' : 'This is the first step before entering detailed activity data.' ?></p></div></div>
            <form class="kr-form" method="post">
                <input type="hidden" name="_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <?php if ($editing): ?><input type="hidden" name="programme_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                <div class="kr-field kr-field--wide"><label for="activity_slug">Activities card <span>*</span></label><select id="activity_slug" name="activity_slug" required<?= $editing ? ' disabled' : '' ?>><option value="">Select the destination activity card</option><?php foreach ($categories as $slug => $label): ?><option value="<?= e($slug) ?>"<?= $formCategory === $slug ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if ($editing): ?><input type="hidden" name="activity_slug" value="<?= e($formCategory) ?>"><?php endif; ?><small class="kr-help">The selected card controls where this programme appears in Activities.</small></div>
                <div class="kr-field kr-field--wide"><label for="title">Programme title <span>*</span></label><input id="title" name="title" maxlength="255" required value="<?= e($formTitle) ?>" placeholder="Name of the programme"></div>
                <div class="kr-field kr-field--wide"><label for="programme_type">Programme type / subtype <span>*</span></label><input id="programme_type" name="programme_type" list="training-types" required value="<?= e($formType) ?>" placeholder="For Training, select a training type"><datalist id="training-types"><?php foreach (training_types() as $type): ?><option value="<?= e($type) ?>"><?php endforeach; ?></datalist><small class="kr-help">Training must use one of the suggested training types. Other cards may use a suitable subtype.</small></div>
                <div class="kr-field"><label for="start_date">Start date <span>*</span></label><input id="start_date" name="start_date" type="date" required value="<?= e($formStart) ?>"></div>
                <div class="kr-field"><label for="end_date">End date</label><input id="end_date" name="end_date" type="date" value="<?= e($formEnd) ?>"></div>
                <div class="kr-field kr-field--wide"><label for="venue">Venue / location <span>*</span></label><input id="venue" name="venue" maxlength="255" required value="<?= e($formVenue) ?>" placeholder="KVK campus, village, field, or venue"></div>
                <div class="kr-field"><label for="status">Programme status</label><select id="status" name="status"><?php foreach (['planned'=>'Planned','open'=>'Open','completed'=>'Completed','cancelled'=>'Cancelled'] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $formStatus === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="kr-field"><label for="registration_deadline">Farmer registration deadline</label><input id="registration_deadline" name="registration_deadline" type="datetime-local" value="<?= e($formDeadline) ?>"><small class="kr-help">Used only when the Activities card is Training.</small></div>
                <div class="kr-actions"><button class="kr-btn" type="submit"><?= $editing ? 'Update programme' : 'Save in KVK Register' ?></button><?php if ($editing): ?><a class="kr-btn kr-btn--secondary" href="kvk-register.php">Cancel edit</a><?php endif; ?></div>
            </form>
        </section>

        <section class="kr-panel">
            <div class="kr-panel__head"><div><h2>Programme register</h2><p>Each record is available to its matching Activities card.</p></div><span class="kr-code"><?= count($programmes) ?> shown</span></div>
            <nav class="kr-filter" aria-label="Filter KVK Register"><a class="<?= $filterCategory === '' ? 'is-active' : '' ?>" href="kvk-register.php">All</a><?php foreach ($categories as $slug => $label): ?><?php if (($counts[$slug] ?? 0) > 0): ?><a class="<?= $filterCategory === $slug ? 'is-active' : '' ?>" href="?category=<?= e($slug) ?>"><?= e($label) ?> (<?= (int) $counts[$slug] ?>)</a><?php endif; ?><?php endforeach; ?></nav>
            <div class="kr-table-wrap"><table class="kr-table"><thead><tr><th>Register No.</th><th>Activity Card</th><th>Programme</th><th>Date</th><th>Venue</th><th>Status</th><th>Used</th><th>Actions</th></tr></thead><tbody>
                <?php if (!$programmes): ?><tr><td class="kr-empty" colspan="8">No programmes are registered in this view.</td></tr><?php endif; ?>
                <?php foreach ($programmes as $programme): ?><tr>
                    <td><span class="kr-code"><?= e((string) $programme['programme_code']) ?></span></td>
                    <td><strong><?= e($categories[(string) $programme['activity_slug']] ?? (string) $programme['activity_slug']) ?></strong></td>
                    <td><strong><?= e((string) $programme['title']) ?></strong><br><?= e((string) $programme['programme_type']) ?></td>
                    <td><?= e(date('d M Y', strtotime((string) $programme['start_date']))) ?><?= !empty($programme['end_date']) && $programme['end_date'] !== $programme['start_date'] ? '<br>to ' . e(date('d M Y', strtotime((string) $programme['end_date']))) : '' ?></td>
                    <td><?= e((string) $programme['venue']) ?></td>
                    <td><span class="kr-status kr-status--<?= e((string) $programme['status']) ?>"><?= e((string) $programme['status']) ?></span></td>
                    <td><?= (int) $programme['activity_record_count'] ?> record<?= (int) $programme['activity_record_count'] === 1 ? '' : 's' ?></td>
                    <td><div class="kr-row-actions"><a href="admin/manage.php?module=activities&desk=mandated&activity_type=<?= e((string) $programme['activity_slug']) ?>&programme=<?= (int) $programme['id'] ?>#activity-entry">Use in Activity</a><?php if ((int) ($programme['training_programme_id'] ?? 0) > 0): ?><a href="training-register.php?qr=<?= (int) $programme['training_programme_id'] ?>">Farmer Registration</a><?php endif; ?><a href="?edit=<?= (int) $programme['id'] ?>#register-form">Edit</a><form method="post" onsubmit="return confirm('Delete this programme from KVK Register?')"><input type="hidden" name="_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="programme_id" value="<?= (int) $programme['id'] ?>"><button class="delete" type="submit">Delete</button></form></div></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    </div>
</main>
</body>
</html>
