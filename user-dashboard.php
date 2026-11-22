<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_user();
auth_no_store_headers();

$user = current_user() ?? [];
$pdo = function_exists('db') ? db() : null;
$userId = (int) ($user['id'] ?? 0);
$userName = trim((string) ($user['name'] ?? 'User')) ?: 'User';
$userEmail = trim((string) ($user['email'] ?? ''));
$userPhone = trim((string) ($user['phone'] ?? ''));
$userRole = trim((string) ($user['role'] ?? 'User')) ?: 'User';
$profileFields = [$userName !== 'User', $userEmail !== '', $userPhone !== ''];
$profileCompletion = (int) round((count(array_filter($profileFields)) / count($profileFields)) * 100);

function ud_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ud_table_exists(?PDO $pdo, string $table): bool
{
    if (!$pdo instanceof PDO || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function ud_column_exists(?PDO $pdo, string $table, string $column): bool
{
    if (!$pdo instanceof PDO
        || !preg_match('/^[A-Za-z0-9_]+$/', $table)
        || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function ud_count(?PDO $pdo, string $table, string $where = '1=1', array $params = []): int
{
    if (!$pdo instanceof PDO || !ud_table_exists($pdo, $table)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function ud_database_name(?PDO $pdo): string
{
    if (!$pdo instanceof PDO) {
        return '';
    }

    try {
        $name = $pdo->query('SELECT DATABASE()')->fetchColumn();
        return is_string($name) ? $name : '';
    } catch (Throwable $exception) {
        return '';
    }
}

function ud_display_date(mixed $value): string
{
    $value = trim((string) $value);
    $timestamp = $value !== '' ? strtotime($value) : false;
    return $timestamp ? date('d M Y', $timestamp) : 'Not provided';
}

function ud_http_url(mixed $value): string
{
    $value = trim((string) $value);
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true) ? $value : '';
}

function ud_identity_name(mixed $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/^(?:(?:dr|mr|mrs|ms|miss|smt|smti|shri|sri)\.?\s+)+/iu', '', $value) ?? $value;
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
}

function ud_identity_email(mixed $value): string
{
    $value = function_exists('mb_strtolower')
        ? mb_strtolower(trim((string) $value), 'UTF-8')
        : strtolower(trim((string) $value));
    $value = preg_replace('/\s*(?:\[at\]|\(at\)|\bat\b)\s*/iu', '@', $value) ?? $value;
    return preg_replace('/\s+/u', '', $value) ?? '';
}

function ud_identity_phone(mixed $value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
    return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}

function ud_staff_match(?PDO $pdo, string $email, string $name, string $phone): array
{
    if (!$pdo instanceof PDO || !ud_table_exists($pdo, 'staff')) {
        return [null, 'not_found', ''];
    }

    try {
        $rows = $pdo->query('SELECT * FROM staff ORDER BY is_active DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return [null, 'unavailable', ''];
    }

    $accountEmail = ud_identity_email($email);
    $accountName = ud_identity_name($name);
    $accountPhone = ud_identity_phone($phone);
    $candidates = [];
    foreach ($rows as $row) {
        $evidence = [];
        $score = 0;
        if ($accountEmail !== '' && $accountEmail === ud_identity_email($row['email'] ?? '')) {
            $evidence[] = 'email';
            $score += 4;
        }
        if ($accountPhone !== '' && strlen($accountPhone) >= 7 && $accountPhone === ud_identity_phone($row['phone'] ?? '')) {
            $evidence[] = 'phone';
            $score += 3;
        }
        if ($accountName !== '' && $accountName === ud_identity_name($row['name'] ?? '')) {
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
    $method = implode(', ', $topCandidates[0]['evidence']);

    return [
        $record,
        (int) ($record['is_active'] ?? 1) === 1 ? 'matched' : 'inactive',
        $method,
    ];
}

$databaseName = ud_database_name($pdo);
$staffRecord = null;
$staffMatchState = 'not_found';
$staffMatchMethod = '';
[$staffRecord, $staffMatchState, $staffMatchMethod] = ud_staff_match($pdo, $userEmail, $userName, $userPhone);
$hasProjectAssignment = ud_table_exists($pdo, 'projects') && ud_column_exists($pdo, 'projects', 'nodal_user_id');

$projectCount = 0;
$projectInProgress = 0;
$projectCompleted = 0;
$projectOnHold = 0;
if ($hasProjectAssignment && $userId > 0) {
    $activeWhere = ud_column_exists($pdo, 'projects', 'is_active') ? ' AND is_active = 1' : '';
    $projectCount = ud_count($pdo, 'projects', 'nodal_user_id = ?' . $activeWhere, [$userId]);
    if (ud_column_exists($pdo, 'projects', 'status')) {
        $projectInProgress = ud_count($pdo, 'projects', "nodal_user_id = ? AND status = 'In Progress'" . $activeWhere, [$userId]);
        $projectCompleted = ud_count($pdo, 'projects', "nodal_user_id = ? AND status = 'Completed'" . $activeWhere, [$userId]);
        $projectOnHold = ud_count($pdo, 'projects', "nodal_user_id = ? AND status = 'On Hold'" . $activeWhere, [$userId]);
    }
}

$noticeCount = ud_count($pdo, 'notices', ud_column_exists($pdo, 'notices', 'is_active') ? 'is_active = 1' : '1=1');
$activityCount = ud_count($pdo, 'activities', ud_column_exists($pdo, 'activities', 'is_active') ? 'is_active = 1' : '1=1');
$publicationCount = ud_count($pdo, 'publications', ud_column_exists($pdo, 'publications', 'is_active') ? 'is_active = 1' : '1=1');
$storyCount = ud_count($pdo, 'success_stories', ud_column_exists($pdo, 'success_stories', 'is_active') ? 'is_active = 1' : '1=1');
$galleryCount = ud_count($pdo, 'gallery', ud_column_exists($pdo, 'gallery', 'is_active') ? 'is_active = 1' : '1=1');

$latestNotices = [];
try {
    if (function_exists('latest_notices')) {
        $latestNotices = latest_notices(4) ?: [];
    }
} catch (Throwable $exception) {
    $latestNotices = [];
}

$projectHref = 'admin/user-projects.php';

$cards = [
    [
        'title' => 'Notices',
        'value' => $noticeCount,
        'text' => 'Add and edit website notices, programme updates, attachments and programme photographs in the same Notices table used by Admin.',
        'href' => 'admin/user-manage.php?module=notices',
        'icon' => 'fa-bell',
        'tone' => 'orange',
        'cta' => 'Open notice entry desk',
    ],
    [
        'title' => 'Activities Desk',
        'value' => $activityCount,
        'text' => 'Enter Training, OFT, FLD and other KVK activity records directly into the existing Activities database used by Admin and the website.',
        'href' => 'admin/user-manage.php?module=activities',
        'icon' => 'fa-seedling',
        'tone' => 'leaf',
        'cta' => 'Open activities desk',
    ],
    [
        'title' => 'Publications',
        'value' => $publicationCount,
        'text' => 'Add and update publication/download records in the existing Publications table so the same data remains available to Admin.',
        'href' => 'admin/user-manage.php?module=publications',
        'icon' => 'fa-book-open',
        'tone' => 'blue',
        'cta' => 'Open publication desk',
    ],
    [
        'title' => 'My Assigned Projects',
        'value' => $projectCount,
        'text' => 'Projects deputed to you as Nodal Scientist. Update annual activities, outcome and project status from your assigned-project desk.',
        'href' => $projectHref,
        'icon' => 'fa-diagram-project',
        'tone' => 'green',
        'cta' => 'Open assigned projects',
    ],
    [
        'title' => 'Success Stories',
        'value' => $storyCount,
        'text' => 'Add farmer success stories, village details, narrative, outcomes and photographs to the same Success Stories database used by Admin.',
        'href' => 'admin/user-manage.php?module=stories',
        'icon' => 'fa-award',
        'tone' => 'gold',
        'cta' => 'Open success story desk',
    ],
    [
        'title' => 'Gallery',
        'value' => $galleryCount,
        'text' => 'Upload and edit activity photographs, captions and categories in the existing Gallery records used by the public website and Admin Desk.',
        'href' => 'admin/user-manage.php?module=gallery',
        'icon' => 'fa-images',
        'tone' => 'purple',
        'cta' => 'Open gallery desk',
    ],
];

$page_title = 'User Desk | KVK Kokrajhar';
require_once __DIR__ . '/includes/header.php';
?>
<style>
:root {
    --ud-green: #0b6a46;
    --ud-green-dark: #073f2c;
    --ud-green-deep: #062c21;
    --ud-lime: #8ec63f;
    --ud-gold: #e3b23c;
    --ud-navy: #17384e;
    --ud-muted: #667a70;
    --ud-border: #dce8e1;
    --ud-bg: #f3f7f4;
    --ud-white: #fff;
    --ud-shadow: 0 14px 36px rgba(20, 67, 48, .08);
}

.user-desk {
    min-height: 70vh;
    background:
        radial-gradient(circle at 5% 10%, rgba(142, 198, 63, .08), transparent 26%),
        linear-gradient(180deg, #f9fcfa 0, var(--ud-bg) 100%);
    color: var(--ud-navy);
}

.ud-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: 44px 0 40px;
    color: #fff;
    background:
        radial-gradient(circle at 86% 20%, rgba(227,178,60,.34), transparent 19%),
        radial-gradient(circle at 72% 110%, rgba(142,198,63,.28), transparent 32%),
        linear-gradient(125deg, #062d21 0%, #09583d 45%, #0b7653 73%, #126b45 100%);
    box-shadow: inset 0 -1px rgba(255,255,255,.12);
}

.ud-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    opacity: .28;
    background-image:
        linear-gradient(rgba(255,255,255,.13) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.11) 1px, transparent 1px),
        repeating-linear-gradient(118deg, transparent 0 42px, rgba(197,235,139,.10) 43px 46px, transparent 47px 88px);
    background-size: 54px 54px, 54px 54px, 100% 100%;
    mask-image: linear-gradient(90deg, rgba(0,0,0,.9), rgba(0,0,0,.2));
}

.ud-hero::after {
    content: '';
    position: absolute;
    z-index: -1;
    width: 390px;
    height: 390px;
    right: -155px;
    bottom: -250px;
    border: 48px solid rgba(255,255,255,.10);
    border-radius: 50%;
}

.ud-hero__inner {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 30px;
    align-items: center;
}

.ud-kicker {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
    padding: 7px 12px;
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 999px;
    background: rgba(255,255,255,.10);
    color: #dff5e8;
    font-size: .73rem;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    backdrop-filter: blur(8px);
}

.ud-hero h1 {
    margin: 0 0 10px;
    color: #fff;
    font-size: clamp(2rem, 4vw, 3.35rem);
    line-height: 1.03;
    letter-spacing: -.035em;
}

.ud-hero__lead {
    max-width: 760px;
    margin: 0;
    color: rgba(255,255,255,.88);
    font-size: 1rem;
    line-height: 1.65;
}

.ud-db-note {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin: 16px 0 0;
    padding: 9px 12px;
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 12px;
    background: rgba(5, 38, 27, .28);
    color: #e9f8ef;
    font-size: .78rem;
    backdrop-filter: blur(10px);
}

.ud-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.ud-btn {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    min-height: 43px;
    padding: 10px 14px;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 11px;
    color: #fff;
    background: rgba(255,255,255,.10);
    font-size: .82rem;
    font-weight: 850;
    text-decoration: none;
    backdrop-filter: blur(8px);
}

.ud-btn--light {
    border-color: #fff;
    color: var(--ud-green-dark);
    background: #fff;
}

.ud-btn:hover { transform: translateY(-1px); }

.ud-main { padding: 30px 0 70px; }

.ud-account-strip {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) repeat(3, minmax(110px, auto));
    gap: 14px;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px 18px;
    border: 1px solid var(--ud-border);
    border-radius: 18px;
    background: #fff;
    box-shadow: var(--ud-shadow);
}

.ud-avatar {
    display: grid;
    width: 50px;
    height: 50px;
    place-items: center;
    border-radius: 15px;
    color: #fff;
    background: linear-gradient(135deg, #0b7653, #7ebd38);
    font-size: 1.18rem;
    font-weight: 900;
}

.ud-account-copy { min-width: 0; }
.ud-account-copy h2 { margin: 0 0 3px; font-size: 1rem; color: #183b2c; }
.ud-account-copy p { margin: 0; color: var(--ud-muted); font-size: .78rem; overflow-wrap: anywhere; }

.ud-mini-stat {
    padding-left: 14px;
    border-left: 1px solid #e5ede8;
}
.ud-mini-stat span { display: block; color: #7a8c82; font-size: .66rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
.ud-mini-stat strong { display: block; margin-top: 3px; color: #173e30; font-size: 1.05rem; }

.ud-section-head {
    display: flex;
    justify-content: space-between;
    gap: 22px;
    align-items: end;
    margin: 0 0 16px;
}
.ud-section-head p { margin: 0 0 4px; color: var(--ud-green); font-size: .7rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.ud-section-head h2 { margin: 0; color: #173b2c; font-size: clamp(1.4rem, 2vw, 1.85rem); }
.ud-section-head__help { max-width: 500px; color: var(--ud-muted) !important; font-size: .78rem !important; font-weight: 650 !important; line-height: 1.5; text-transform: none !important; letter-spacing: 0 !important; }

.ud-profile-card {
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) minmax(180px, 250px) auto;
    gap: 18px;
    align-items: center;
    overflow: hidden;
    margin-bottom: 16px;
    padding: 22px 24px;
    border: 1px solid #cfe4d8;
    border-radius: 20px;
    background:
        radial-gradient(circle at 94% 12%, rgba(227,178,60,.18), transparent 25%),
        linear-gradient(120deg, #ffffff 0%, #f5fbf7 64%, #edf8f1 100%);
    box-shadow: 0 12px 30px rgba(24, 67, 50, .07);
}

.ud-profile-card::after {
    content: '';
    position: absolute;
    width: 150px;
    height: 150px;
    right: -78px;
    top: -90px;
    border: 25px solid rgba(11,118,83,.07);
    border-radius: 50%;
    pointer-events: none;
}

.ud-profile-card__avatar {
    display: grid;
    width: 62px;
    height: 62px;
    place-items: center;
    border: 5px solid #e4f2e9;
    border-radius: 19px;
    color: #fff;
    background: linear-gradient(135deg, #07583d, #83bd3f);
    box-shadow: 0 9px 20px rgba(11,106,70,.18);
    font-size: 1.4rem;
    font-weight: 900;
}

.ud-profile-card__copy { min-width: 0; }
.ud-profile-card__eyebrow { display: block; margin-bottom: 4px; color: var(--ud-green); font-size: .67rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.ud-profile-card h3 { margin: 0 0 5px; color: #153d2c; font-size: 1.08rem; }
.ud-profile-card p { margin: 0; color: var(--ud-muted); font-size: .77rem; font-weight: 620; line-height: 1.5; }

.ud-profile-progress { min-width: 0; }
.ud-profile-progress__meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 7px; color: #547064; font-size: .69rem; font-weight: 850; }
.ud-profile-progress__track { overflow: hidden; height: 8px; border-radius: 999px; background: #dcebe2; }
.ud-profile-progress__bar { display: block; width: var(--profile-progress); height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0b7653, #8ec63f); }

.ud-profile-card__action {
    position: relative;
    z-index: 1;
    display: inline-flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 15px;
    border-radius: 12px;
    color: #fff;
    background: var(--ud-green);
    box-shadow: 0 8px 18px rgba(11,106,70,.18);
    font-size: .76rem;
    font-weight: 900;
    text-decoration: none;
    white-space: nowrap;
    transition: transform .2s ease, background .2s ease;
}
.ud-profile-card__action:hover { transform: translateY(-2px); color: #fff; background: var(--ud-green-dark); }

.ud-staff-card {
    overflow: hidden;
    margin-bottom: 28px;
    border: 1px solid #cfe2d7;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(20,67,48,.08);
}

.ud-staff-card > summary {
    list-style: none;
}

.ud-staff-card > summary::-webkit-details-marker {
    display: none;
}

.ud-staff-card[open] {
    box-shadow: 0 18px 42px rgba(20,67,48,.12);
}

.ud-staff-card__head {
    position: relative;
    display: flex;
    gap: 20px;
    justify-content: space-between;
    align-items: center;
    overflow: hidden;
    padding: 16px 18px;
    color: #fff;
    background: linear-gradient(120deg, #063d2c 0%, #0a6949 65%, #5f9631 100%);
    cursor: pointer;
}
.ud-staff-card__head::after { content: ''; position: absolute; width: 170px; height: 170px; right: -85px; top: -105px; border: 27px solid rgba(255,255,255,.09); border-radius: 50%; }
.ud-staff-identity { position: relative; z-index: 1; display: flex; gap: 15px; align-items: center; min-width: 0; }
.ud-staff-photo { display: grid; width: 54px; height: 58px; flex: 0 0 auto; place-items: center; overflow: hidden; border: 3px solid rgba(255,255,255,.23); border-radius: 14px; color: #fff; background: rgba(255,255,255,.13); font-size: 1.18rem; }
.ud-staff-photo img { width: 100%; height: 100%; object-fit: cover; }
.ud-staff-identity__copy { min-width: 0; }
.ud-staff-identity__copy span { display: block; margin-bottom: 4px; color: #cfeadd; font-size: .66rem; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
.ud-staff-identity__copy h2 { margin: 0 0 4px; color: #fff; font-size: clamp(1.05rem, 2.1vw, 1.35rem); }
.ud-staff-identity__copy p { margin: 0; color: rgba(255,255,255,.8); font-size: .76rem; font-weight: 700; }
.ud-staff-edit { position: relative; z-index: 1; display: inline-flex; gap: 8px; align-items: center; justify-content: center; min-height: 38px; padding: 9px 13px; border: 1px solid rgba(255,255,255,.7); border-radius: 11px; color: var(--ud-green-dark); background: #fff; font-size: .72rem; font-weight: 900; text-decoration: none; white-space: nowrap; box-shadow: 0 8px 18px rgba(0,0,0,.12); }
.ud-staff-edit:hover { color: var(--ud-green-dark); transform: translateY(-2px); }
.ud-staff-toggle { color: #073f2c; background: #fff; }
.ud-staff-card[open] .ud-staff-toggle i { transform: rotate(90deg); }
.ud-staff-toggle i { transition: transform .18s ease; }
.ud-staff-card__body { padding: 18px; }
.ud-staff-action-row { margin: 0 0 14px; }
.ud-match-line { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.ud-match-line p { margin: 0; color: var(--ud-muted); font-size: .73rem; font-weight: 700; }
.ud-match-badge { display: inline-flex; gap: 7px; align-items: center; padding: 7px 10px; border: 1px solid #b9dfc8; border-radius: 999px; color: #17613a; background: #edf9f2; font-size: .65rem; font-weight: 900; }
.ud-staff-details { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.ud-staff-detail { min-width: 0; padding: 13px 14px; border: 1px solid #e2ebe6; border-radius: 13px; background: #fbfdfc; }
.ud-staff-detail span { display: flex; gap: 7px; align-items: center; margin-bottom: 5px; color: #7d9085; font-size: .62rem; font-weight: 900; letter-spacing: .045em; text-transform: uppercase; }
.ud-staff-detail span i { color: var(--ud-green); }
.ud-staff-detail strong { display: block; color: #2d4c3d; font-size: .76rem; line-height: 1.4; overflow-wrap: anywhere; }
.ud-staff-detail--wide { grid-column: span 2; }
.ud-staff-unlinked { display: flex; gap: 14px; align-items: center; justify-content: space-between; padding: 18px; border: 1px dashed #c9d9d1; border-radius: 15px; color: #5e7569; background: #fafcfb; }
.ud-staff-unlinked__copy { display: flex; gap: 12px; align-items: center; }
.ud-staff-unlinked__copy > i { color: #799487; font-size: 1.3rem; }
.ud-staff-unlinked h3 { margin: 0 0 4px; color: #315141; font-size: .9rem; }
.ud-staff-unlinked p { margin: 0; font-size: .72rem; line-height: 1.5; }

.ud-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(205px, 1fr));
    gap: 12px;
}

.ud-card {
    position: relative;
    display: flex;
    min-height: 150px;
    flex-direction: column;
    overflow: hidden;
    padding: 14px;
    border: 1px solid var(--ud-border);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(24, 67, 50, .06);
    color: inherit;
    text-decoration: none;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}

.ud-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: var(--card-accent, #0b7653);
}

.ud-card::after {
    content: '';
    position: absolute;
    width: 82px;
    height: 82px;
    right: -42px;
    bottom: -44px;
    border: 16px solid color-mix(in srgb, var(--card-accent, #0b7653) 9%, transparent);
    border-radius: 50%;
}

.ud-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--card-accent, #0b7653) 34%, #dce8e1);
    box-shadow: 0 18px 38px rgba(24, 67, 50, .12);
}

.ud-card--green { --card-accent: #0b7653; }
.ud-card--blue { --card-accent: #2b6cb0; }
.ud-card--gold { --card-accent: #c68b18; }
.ud-card--orange { --card-accent: #c45c28; }
.ud-card--leaf { --card-accent: #639b28; }
.ud-card--purple { --card-accent: #7653a6; }

.ud-card__top {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    align-items: start;
}

.ud-card__icon {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 10px;
    color: var(--card-accent, #0b7653);
    background: color-mix(in srgb, var(--card-accent, #0b7653) 10%, white);
    font-size: .9rem;
}

.ud-card__value {
    color: var(--card-accent, #0b7653);
    font-size: 1.35rem;
    font-weight: 900;
    line-height: 1;
}

.ud-card h3 {
    margin: 12px 0 5px;
    color: #193a2d;
    font-size: .91rem;
}

.ud-card p {
    margin: 0;
    color: var(--ud-muted);
    font-size: .72rem;
    font-weight: 620;
    line-height: 1.4;
}

.ud-card__cta {
    display: inline-flex;
    gap: 7px;
    align-items: center;
    margin-top: auto;
    padding-top: 11px;
    color: var(--card-accent, #0b7653);
    font-size: .7rem;
    font-weight: 900;
}

.ud-lower-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(300px, .75fr);
    gap: 18px;
    margin-top: 24px;
}

.ud-panel {
    position: relative;
    overflow: hidden;
    padding: 20px;
    border: 1px solid var(--ud-border);
    border-radius: 19px;
    background: #fff;
    box-shadow: var(--ud-shadow);
}

.ud-panel::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: #0b7653;
}

.ud-panel > * {
    position: relative;
    z-index: 1;
}

.ud-panel-head {
    display: flex;
    gap: 14px;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 14px;
}
.ud-panel-head p { margin: 0 0 3px; color: var(--ud-green); font-size: .68rem; font-weight: 900; text-transform: uppercase; letter-spacing: .07em; }
.ud-panel-head h2 { margin: 0; color: #173b2c; font-size: 1.08rem; }
.ud-panel-head a { color: var(--ud-green); font-size: .75rem; font-weight: 850; text-decoration: none; }

.ud-notice-list { display: grid; gap: 9px; }
.ud-notice {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr);
    gap: 12px;
    padding: 12px;
    border: 1px solid #e6eee9;
    border-radius: 13px;
    background: #fbfdfb;
}
.ud-notice time { color: #73867b; font-size: .68rem; font-weight: 800; }
.ud-notice strong { display: block; color: #274738; font-size: .82rem; line-height: 1.4; }
.ud-empty { padding: 22px; border: 1px dashed #cedbd3; border-radius: 13px; color: var(--ud-muted); text-align: center; font-size: .8rem; }

.ud-account-table { display: grid; gap: 10px; }
.ud-account-row { display: grid; grid-template-columns: 88px minmax(0, 1fr); gap: 10px; padding-bottom: 10px; border-bottom: 1px solid #edf2ef; }
.ud-account-row:last-child { padding-bottom: 0; border-bottom: 0; }
.ud-account-row span { color: #819187; font-size: .7rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
.ud-account-row strong { color: #2e493c; font-size: .79rem; overflow-wrap: anywhere; }

@media (max-width: 1050px) {
    .ud-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ud-account-strip { grid-template-columns: auto minmax(0, 1fr) repeat(2, minmax(100px, auto)); }
    .ud-mini-stat:last-child { display: none; }
    .ud-profile-card { grid-template-columns: auto minmax(0, 1fr) auto; }
    .ud-profile-progress { grid-column: 2 / 4; }
    .ud-staff-details { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 760px) {
    .ud-hero { padding: 34px 0 32px; }
    .ud-hero__inner { grid-template-columns: 1fr; }
    .ud-hero__actions { justify-content: flex-start; }
    .ud-account-strip { grid-template-columns: auto 1fr; }
    .ud-mini-stat { display: none; }
    .ud-card-grid, .ud-lower-grid { grid-template-columns: 1fr; }
    .ud-section-head { align-items: start; flex-direction: column; }
    .ud-profile-card { grid-template-columns: auto minmax(0, 1fr); padding: 18px; }
    .ud-profile-progress { grid-column: 1 / -1; }
    .ud-profile-card__action { grid-column: 1 / -1; }
    .ud-staff-card__head { align-items: flex-start; flex-direction: column; }
    .ud-staff-details { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ud-staff-edit { width: 100%; }
}

@media (max-width: 480px) {
    .ud-card { min-height: 190px; border-radius: 16px; }
    .ud-account-strip { border-radius: 16px; }
    .ud-notice { grid-template-columns: 1fr; gap: 4px; }
    .ud-profile-card__avatar { width: 52px; height: 52px; border-radius: 16px; }
    .ud-staff-card__head, .ud-staff-card__body { padding: 18px; }
    .ud-staff-details { grid-template-columns: 1fr; }
    .ud-staff-detail--wide { grid-column: auto; }
    .ud-staff-unlinked { align-items: stretch; flex-direction: column; }
}

@media (prefers-reduced-motion: reduce) {
    .ud-card, .ud-btn { transition: none !important; }
}
</style>

<div class="user-desk">
    <section class="ud-hero">
        <div class="container ud-hero__inner">
            <div>
                <span class="ud-kicker"><i class="fa-solid fa-user-check" aria-hidden="true"></i> User Desk · KVK Kokrajhar</span>
                <h1>Welcome, <?= ud_e($userName) ?></h1>
                <p class="ud-hero__lead">Your staff data-entry desk for Notices, Activities, Publications, Assigned Projects, Success Stories and Gallery using the same KVK website database as Admin.</p>
                <?php if ($databaseName !== ''): ?>
                    <p class="ud-db-note"><i class="fa-solid fa-database" aria-hidden="true"></i> Live data from existing database: <strong><?= ud_e($databaseName) ?></strong></p>
                <?php endif; ?>
            </div>
            <div class="ud-hero__actions">
                <a class="ud-btn ud-btn--light" href="index.php"><i class="fa-solid fa-globe" aria-hidden="true"></i> View Website</a>
                
            </div>
        </div>
    </section>

    <main class="container ud-main">
        <section class="ud-account-strip" id="account-summary" aria-label="User account summary">
            <div class="ud-avatar" aria-hidden="true"><?= ud_e(strtoupper(substr($userName, 0, 1))) ?></div>
            <div class="ud-account-copy">
                <h2><?= ud_e($userName) ?></h2>
                <p><?= ud_e($userEmail !== '' ? $userEmail : 'Approved KVK user account') ?> · <?= ud_e(ucwords(str_replace('_', ' ', $userRole))) ?></p>
            </div>
            <div class="ud-mini-stat"><span>Assigned</span><strong><?= $projectCount ?></strong></div>
            <div class="ud-mini-stat"><span>In Progress</span><strong><?= $projectInProgress ?></strong></div>
            <div class="ud-mini-stat"><span>Completed</span><strong><?= $projectCompleted ?></strong></div>
        </section>

        <details class="ud-staff-card" id="my-staff-record">
            <?php if (is_array($staffRecord)): ?>
                <summary class="ud-staff-card__head">
                    <div class="ud-staff-identity">
                        <div class="ud-staff-photo">
                            <?php if (trim((string) ($staffRecord['photo_path'] ?? '')) !== ''): ?>
                                <img src="<?= ud_e($staffRecord['photo_path']) ?>" alt="<?= ud_e((string) ($staffRecord['name'] ?? 'Staff member')) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ud-staff-identity__copy">
                            <span>My staff directory record</span>
                            <h2 id="myStaffRecordTitle"><?= ud_e($staffRecord['name'] ?? $userName) ?></h2>
                            <p><?= ud_e(trim((string) ($staffRecord['designation'] ?? '')) ?: 'KVK staff member') ?><?= trim((string) ($staffRecord['discipline'] ?? '')) !== '' ? ' · ' . ud_e($staffRecord['discipline']) : '' ?></p>
                        </div>
                    </div>
                    <span class="ud-staff-edit ud-staff-toggle"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i> View staff details</span>
                </summary>
                <div class="ud-staff-card__body">
                    <div class="ud-match-line">
                        <p>Live information from the website staff table.</p>
                        <span class="ud-match-badge"><i class="fa-solid fa-link" aria-hidden="true"></i> <?= $staffMatchState === 'matched' ? 'Matched by ' . ud_e($staffMatchMethod) : 'Inactive staff record' ?></span>
                    </div>
                    <?php if ($staffMatchState === 'matched'): ?>
                        <p class="ud-staff-action-row"><a class="ud-profile-card__action" href="user-profile.php#staff-record"><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Edit staff information</a></p>
                    <?php endif; ?>
                    <div class="ud-staff-details">
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-envelope" aria-hidden="true"></i> Email</span><strong><?= ud_e(trim((string) ($staffRecord['email'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-phone" aria-hidden="true"></i> Phone</span><strong><?= ud_e(trim((string) ($staffRecord['phone'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-briefcase" aria-hidden="true"></i> Designation</span><strong><?= ud_e(trim((string) ($staffRecord['designation'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-leaf" aria-hidden="true"></i> Discipline</span><strong><?= ud_e(trim((string) ($staffRecord['discipline'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Qualification</span><strong><?= ud_e(trim((string) ($staffRecord['qualification'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail ud-staff-detail--wide"><span><i class="fa-solid fa-star" aria-hidden="true"></i> Expertise</span><strong><?= ud_e(trim((string) ($staffRecord['expertise'] ?? '')) ?: 'Not provided') ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-cake-candles" aria-hidden="true"></i> Date of birth</span><strong><?= ud_e(ud_display_date($staffRecord['date_of_birth'] ?? '')) ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-building-columns" aria-hidden="true"></i> Joined AAU</span><strong><?= ud_e(ud_display_date($staffRecord['date_of_joining_aau'] ?? '')) ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Present post</span><strong><?= ud_e(ud_display_date($staffRecord['date_of_joining_present_post'] ?? '')) ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-users" aria-hidden="true"></i> Staff group</span><strong><?= ud_e(ucwords(str_replace('_', ' ', (string) ($staffRecord['group_type'] ?? 'kvk')))) ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Status</span><strong><?= (int) ($staffRecord['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?></strong></div>
                        <div class="ud-staff-detail"><span><i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i> Display order</span><strong><?= (int) ($staffRecord['sort_order'] ?? 100) ?></strong></div>
                        <div class="ud-staff-detail ud-staff-detail--wide"><span><i class="fa-solid fa-link" aria-hidden="true"></i> AAU profile</span><strong><?php $staffProfileLink = ud_http_url($staffRecord['profile_link'] ?? ''); ?><?php if ($staffProfileLink !== ''): ?><a href="<?= ud_e($staffProfileLink) ?>" target="_blank" rel="noopener noreferrer">Open profile <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php else: ?>Not provided<?php endif; ?></strong></div>
                    </div>
                </div>
            <?php else: ?>
                <summary class="ud-staff-card__head">
                    <div class="ud-staff-identity">
                        <div class="ud-staff-photo"><i class="fa-solid fa-address-card" aria-hidden="true"></i></div>
                        <div class="ud-staff-identity__copy">
                            <span>My staff directory record</span>
                            <h2 id="myStaffRecordTitle">Staff profile not linked</h2>
                            <p>Your account is active, but a unique staff-table record was not found.</p>
                        </div>
                    </div>
                    <span class="ud-staff-edit ud-staff-toggle"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i> View matching details</span>
                </summary>
                <div class="ud-staff-card__body">
                    <div class="ud-staff-unlinked">
                        <div class="ud-staff-unlinked__copy">
                            <i class="fa-solid <?= in_array($staffMatchState, ['ambiguous', 'conflict'], true) ? 'fa-code-compare' : 'fa-magnifying-glass' ?>" aria-hidden="true"></i>
                            <div>
                                <h3><?= $staffMatchState === 'conflict' ? 'Name and email identify different records' : ($staffMatchState === 'ambiguous' ? 'More than one record matches' : 'No matching staff record') ?></h3>
                                <p>The system checked both <?= ud_e($userName) ?> and <?= ud_e($userEmail !== '' ? $userEmail : 'your account email') ?>. An administrator can correct duplicate or mismatched staff data.</p>
                            </div>
                        </div>
                        <a class="ud-profile-card__action" href="user-profile.php#staff-record">Open profile help</a>
                    </div>
                </div>
            <?php endif; ?>
        </details>

        <section aria-labelledby="importantCardsTitle">
            <div class="ud-section-head">
                <div>
                    <p>Data entry desk</p>
                    <h2 id="importantCardsTitle">Website content entry modules</h2>
                </div>
                <p class="ud-section-head__help">Each card uses the same existing database table already used by the Admin Desk. Records saved by staff are therefore available to Admin and the website without duplicate data entry.</p>
            </div>

            

            <div class="ud-card-grid">
                <?php foreach ($cards as $card): ?>
                    <a class="ud-card ud-card--<?= ud_e($card['tone']) ?>" href="<?= ud_e($card['href']) ?>">
                        <div class="ud-card__top">
                            <span class="ud-card__icon"><i class="fa-solid <?= ud_e($card['icon']) ?>" aria-hidden="true"></i></span>
                            <strong class="ud-card__value"><?= (int) $card['value'] ?></strong>
                        </div>
                        <h3><?= ud_e($card['title']) ?></h3>
                        <p><?= ud_e($card['text']) ?></p>
                        <span class="ud-card__cta"><?= ud_e($card['cta']) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="ud-lower-grid">
            <section class="ud-panel" aria-labelledby="latestNoticeTitle">
                <div class="ud-panel-head">
                    <div>
                        <p>Keep informed</p>
                        <h2 id="latestNoticeTitle">Latest notices</h2>
                    </div>
                    <a href="index.php#updates">View website updates <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>

                <?php if ($latestNotices): ?>
                    <div class="ud-notice-list">
                        <?php foreach ($latestNotices as $notice):
                            $noticeDate = trim((string) ($notice['date'] ?? $notice['created_at'] ?? ''));
                            $noticeTs = $noticeDate !== '' ? strtotime($noticeDate) : false;
                            $noticeTitle = trim((string) ($notice['title'] ?? 'Website notice')) ?: 'Website notice';
                        ?>
                            <article class="ud-notice">
                                <time><?= ud_e($noticeTs ? date('d M Y', $noticeTs) : 'Latest') ?></time>
                                <strong><?= ud_e($noticeTitle) ?></strong>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="ud-empty">No current notices are available to display.</div>
                <?php endif; ?>
            </section>

            <aside class="ud-panel" aria-labelledby="accountTitle">
                <div class="ud-panel-head">
                    <div>
                        <p>Signed-in account</p>
                        <h2 id="accountTitle">My account</h2>
                    </div>
                </div>
                <div class="ud-account-table">
                    <div class="ud-account-row"><span>Name</span><strong><?= ud_e($userName) ?></strong></div>
                    <div class="ud-account-row"><span>Email</span><strong><?= ud_e($userEmail !== '' ? $userEmail : 'Not available') ?></strong></div>
                    <div class="ud-account-row"><span>Role</span><strong><?= ud_e(ucwords(str_replace('_', ' ', $userRole))) ?></strong></div>
                    <div class="ud-account-row"><span>Project hold</span><strong><?= $projectOnHold ?></strong></div>
                    <div class="ud-account-row"><span>Database</span><strong><?= ud_e($databaseName !== '' ? $databaseName : 'Website database') ?></strong></div>
                </div>
            </aside>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
