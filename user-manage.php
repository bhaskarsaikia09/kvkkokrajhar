<?php
require_once dirname(__DIR__) . '/app/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

if (!user_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$userDeskUser = current_user() ?? [];
$allowedUserModules = ['notices', 'activities', 'publications', 'stories', 'gallery'];
$modules = cms_modules();
$key = (string) ($_GET['module'] ?? 'notices');

if ($key === 'projects') {
    header('Location: user-projects.php');
    exit;
}

if (!in_array($key, $allowedUserModules, true) || !isset($modules[$key])) {
    header('Location: ../user-dashboard.php?access=denied');
    exit;
}

$module = $modules[$key];
$pdo = db();
$userDeskUserId = (int) ($userDeskUser['id'] ?? 0);
$userDeskUserName = trim((string) ($userDeskUser['name'] ?? ''));
$userDeskUserEmail = trim((string) ($userDeskUser['email'] ?? ''));

if (!$pdo instanceof PDO || $userDeskUserId <= 0) {
    http_response_code(403);
    exit('A valid approved User account is required.');
}

/**
 * Add lightweight ownership fields to the EXISTING module table.
 * No duplicate content table is created. Admin continues to read every row.
 */
function user_desk_ensure_owner_columns(PDO $pdo, string $table): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return;
    }

    $columns = [
        'submitted_by_user_id' => "ALTER TABLE `{$table}` ADD COLUMN submitted_by_user_id INT NULL",
        'submitted_by_name' => "ALTER TABLE `{$table}` ADD COLUMN submitted_by_name VARCHAR(150) NULL",
        'submitted_by_email' => "ALTER TABLE `{$table}` ADD COLUMN submitted_by_email VARCHAR(190) NULL",
        'submitted_at' => "ALTER TABLE `{$table}` ADD COLUMN submitted_at DATETIME NULL",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $exception) {
            // Keep the existing website usable if the database account cannot ALTER tables.
        }
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, 'idx_submitted_by_user']);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX idx_submitted_by_user (submitted_by_user_id)");
        }
    } catch (Throwable $exception) {
        // Index is optional.
    }
}

function user_desk_find_owned(PDO $pdo, string $table, int $id, int $userId): ?array
{
    if ($id <= 0 || $userId <= 0 || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? AND submitted_by_user_id = ? LIMIT 1");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        return null;
    }
}

user_desk_ensure_owner_columns($pdo, (string) $module['table']);

// Staff/User may add and edit ONLY their own website records. Permanent deletion is reserved for Admin.
$userDeleteBlocked = false;
foreach (['delete', 'official_delete', 'commodity_delete'] as $deleteKey) {
    if (isset($_GET[$deleteKey])) {
        unset($_GET[$deleteKey]);
        $userDeleteBlocked = true;
    }
}

unset($_GET['official_edit'], $_GET['commodity_edit']);

$message = $userDeleteBlocked ? 'Delete is reserved for Admin. You can add or edit only records submitted by your own User account.' : '';
if (isset($_GET['saved'])) {
    $message = $_GET['saved'] === 'updated' ? 'Record updated successfully.' : 'Record saved successfully.';
}
if (isset($_GET['access']) && $_GET['access'] === 'not_owner') {
    $message = 'You can view and edit only the programmes/records submitted from your own User account.';
}
if (isset($_GET['access']) && $_GET['access'] === 'admin_master') {
    $message = 'This shared master/official-format section is controlled by Admin.';
}
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? user_desk_find_owned($pdo, (string) $module['table'], $editId, $userDeskUserId) : null;

function activity_mandated_options(): array
{
    return [
        'training' => 'Training',
        'oft' => 'OFT',
        'fld' => 'FLD',
        'public-outreach-programme' => 'Public Outreach Programme',
        'adopted-village' => 'Adopted Village',
        'technology-showcasing' => 'Technology Showcasing',
        'cluster-demonstration' => 'Cluster Demonstration',
        'seed-hub' => 'Seed Hub',
        'planting-materials' => 'Details of Planting Materials/cuttings/seeds/poultry/fingerlings etc',
        'mgmg' => 'MGMG',
        'soil-health-card' => 'Soil Health Card',
    ];
}

function activity_mandated_descriptions(): array
{
    return [
        'training' => 'Training programmes and participant records.',
        'oft' => 'On Farm Testing assessment, refinement, and field records.',
        'fld' => 'Front Line Demonstration records and programme information.',
        'public-outreach-programme' => 'Exposure visits, camps, melas, media programmes, and other outreach records.',
        'adopted-village' => 'Village adoption records, follow-up work, and intervention details.',
        'technology-showcasing' => 'Technology showcasing, seed production, and farmer-participatory demonstration records.',
        'cluster-demonstration' => 'Cluster demonstration records with crop, variety, farmer, area, and production details.',
        'seed-hub' => 'Seed hub records with crop, variety, area, production, and shortfall reason details.',
        'planting-materials' => 'Distribution details for planting materials, cuttings, seeds, poultry, fingerlings, and related inputs.',
        'mgmg' => 'MGMG activity records and field-level extension visits.',
        'soil-health-card' => 'Soil Health Card activity and distribution records.',
    ];
}

function activity_public_outreach_topics(): array
{
    return [
        'exposure-visit' => 'Exposure Visit',
        'learning-tours' => 'Learning Tours',
        'field-exposure' => 'Field Exposure',
        'exhibition' => 'Exhibition',
        'field-day' => 'Field Day',
        'kisan-mela' => 'Kisan Mela',
        'pm-webcasting' => 'PM Webcasting',
        'animal-health-camp' => 'Animal Health Camp',
        'awareness-camp' => 'Awareness Camp',
        'celebration-of-important-days' => 'Celebration of Important Days',
        'consultancy-services-offered' => 'Consultancy Services Offered',
        'diagnostic-visits' => 'Diagnostic Visits',
        'farm-science-club-conveners-meet' => 'Farm Science Club Conveners Meet',
        'farmers-seminar-workshop' => 'Farmers Seminar/Workshop',
        'farmer-scientists-interaction' => "Farmer Scientists' Interaction",
        'farmers-visit-to-kvk' => 'Farmers Visit to KVK',
        'film-show' => 'Film Show',
        'formation-of-shgs' => 'Formation of SHGs',
        'group-meetings-discussion' => 'Group Meetings/Discussion',
        'kisan-gosthi' => 'Kisan Gosthi',
        'lectures-delivered' => 'Lectures Delivered',
        'mahila-mandal-conveners-meetings' => "Mahila Mandal Conveners' Meetings",
        'phone-in-help-line-programme-kmas' => 'Phone in/Help Line Programme/KMAS',
        'plant-health-camp' => 'Plant Health Camp',
        'pra-exercise' => 'PRA Exercise',
        'radio-talks' => 'Radio Talks',
        'self-help-group-conveners-meetings' => 'Self Help Group Conveners Meetings',
        'tv-talks' => 'TV Talks',
    ];
}

function activity_canonical_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    return match ($slug) {
        'fld', 'frontline-demonstration', 'frontline-demonstrations', 'front-line-demonstration', 'front-line-demonstrations' => 'fld',
        'oft', 'on-farm-testing', 'on-farm-test', 'on-field-trial', 'on-field-trials', 'on-field-trail', 'on-field-trails' => 'oft',
        'public-outreach', 'public-outreach-program', 'public-outreach-programmes' => 'public-outreach-programme',
        'awareness', 'awareness-programme', 'awareness-programmes', 'awareness-programme-camps', 'awareness-camps', 'awareness-programme-camp' => 'awareness-camp',
        'exposure', 'exposure-desk', 'exposure-visits', 'exposure-programme', 'exposure-programmes' => 'exposure-visit',
        'learning-tour' => 'learning-tours',
        'exhibition-mela-organised', 'exhibition-mela-organized' => 'exhibition',
        'mela' => 'kisan-mela',
        'technology', 'technology-showcase', 'technology-showcasing-seed-production', 'technology-showcasing-seed-production-records' => 'technology-showcasing',
        'cluster', 'cluster-demonstrations', 'cluster-frontline-demonstration', 'cluster-frontline-demonstrations', 'cfld' => 'cluster-demonstration',
        'seed', 'seedhub', 'seed-hub-records', 'achievements-under-seed-hub' => 'seed-hub',
        'planting-material', 'planting-materials-cuttings-seeds-poultry-fingerlings', 'planting-materials-cuttings-seeds-poultry-fingerlings-etc' => 'planting-materials',
        'animal-health-camp.', 'animal-health-camps' => 'animal-health-camp',
        'field-days' => 'field-day',
        'kisan-melas' => 'kisan-mela',
        'pm-webcast' => 'pm-webcasting',
        'diagnostic-visit' => 'diagnostic-visits',
        'farmer-scientist-interaction', 'farmer-scientists-interactions' => 'farmer-scientists-interaction',
        'group-meeting-discussion', 'group-meetings', 'group-discussion' => 'group-meetings-discussion',
        'phone-in', 'help-line-programme', 'kmas' => 'phone-in-help-line-programme-kmas',
        'plant-health-camps' => 'plant-health-camp',
        'radio-talk' => 'radio-talks',
        'tv-talk' => 'tv-talks',
        'soil-health', 'soil-health-cards' => 'soil-health-card',
        default => $slug,
    };
}

function activity_is_mandated_slug(string $slug): bool
{
    $canonical = activity_canonical_slug($slug);
    return array_key_exists($canonical, activity_mandated_options())
        || array_key_exists($canonical, activity_public_outreach_topics());
}

function activity_slug_label(string $slug): string
{
    $canonical = activity_canonical_slug($slug);
    $options = activity_mandated_options();
    $publicOutreachTopics = activity_public_outreach_topics();
    return $options[$canonical] ?? $publicOutreachTopics[$canonical] ?? $slug;
}

function activity_outreach_report_date(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return 'Not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

function activity_outreach_report_date_range(array $record, string $fromField, ?string $toField = null): string
{
    $from = trim((string) ($record[$fromField] ?? ''));
    $to = $toField ? trim((string) ($record[$toField] ?? '')) : '';

    if ($from === '' && $to === '') {
        return 'Not specified';
    }
    if ($to === '' || $to === $from) {
        return activity_outreach_report_date($from);
    }

    return activity_outreach_report_date($from) . ' to ' . activity_outreach_report_date($to);
}

function activity_public_outreach_report_specs(): array
{
    return [
        ['slug' => 'exposure-visit', 'label' => 'Exposure Visit', 'table' => 'exposure_visit', 'date' => 'visit_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'learning-tours', 'label' => 'Learning Tours', 'table' => 'learning_tours', 'date' => 'visit_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'field-exposure', 'label' => 'Field Exposure', 'table' => 'field_exposure', 'date' => 'visit_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'exhibition', 'label' => 'Exhibition', 'table' => 'exhibition_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'male' => 'visitors_male', 'female' => 'visitors_female'],
        ['slug' => 'field-day', 'label' => 'Field Day', 'table' => 'field_day_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'male' => 'visitors_male', 'female' => 'visitors_female'],
        ['slug' => 'kisan-mela', 'label' => 'Kisan Mela', 'table' => 'kisan_mela_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'male' => 'visitors_male', 'female' => 'visitors_female'],
        ['slug' => 'pm-webcasting', 'label' => 'PM Webcasting', 'table' => 'pm_webcasting_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'male' => 'visitors_male', 'female' => 'visitors_female'],
        ['slug' => 'animal-health-camp', 'label' => 'Animal Health Camp', 'table' => 'animal_health_camp_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'awareness-camp', 'label' => 'Awareness Camp', 'table' => 'awareness_camp_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'celebration-of-important-days', 'label' => 'Celebration of Important Days', 'table' => 'important_day_records', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'diagnostic-visits', 'label' => 'Diagnostic Visits', 'table' => 'diagnostic_visit_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'farm-science-club-conveners-meet', 'label' => 'Farm Science Club Conveners Meet', 'table' => 'farm_science_club_conveners_meet_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'farmer-scientists-interaction', 'label' => "Farmer Scientists' Interaction", 'table' => 'farmer_scientist_interaction_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'film-show', 'label' => 'Film Show', 'table' => 'film_show_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'formation-of-shgs', 'label' => 'Formation of SHGs', 'table' => 'formation_of_shg_records', 'date' => 'formation_date'],
        ['slug' => 'group-meetings-discussion', 'label' => 'Group Meetings/Discussion', 'table' => 'group_meetings_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'kisan-gosthi', 'label' => 'Kisan Gosthi', 'table' => 'kisan_gosthi_records', 'date' => 'formation_date'],
        ['slug' => 'lectures-delivered', 'label' => 'Lectures Delivered', 'table' => 'lecture_delivered_records', 'date' => 'programme_date'],
        ['slug' => 'mahila-mandal-conveners-meetings', 'label' => "Mahila Mandal Conveners' Meetings", 'table' => 'mahila_mandal_conveners_meeting_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'plant-health-camp', 'label' => 'Plant Health Camp', 'table' => 'plant_health_camp_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'pra-exercise', 'label' => 'PRA Exercise', 'table' => 'pra_exercise_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'radio-talks', 'label' => 'Radio Talks', 'table' => 'radio_talk_records', 'date' => 'talk_date'],
        ['slug' => 'self-help-group-conveners-meetings', 'label' => 'Self Help Group Conveners Meetings', 'table' => 'self_help_group_conveners_meeting_records', 'year' => 'year', 'date' => 'event_date', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
        ['slug' => 'tv-talks', 'label' => 'TV Talks', 'table' => 'tv_talk_records', 'date' => 'talk_date'],
    ];
}

function activity_public_outreach_report(?PDO $pdo, array $activityIds = []): array
{
    if (!$pdo) {
        return [];
    }

    foreach (array_keys(activity_visit_record_tables()) as $slug) {
        exposure_visit_records_ensure_table($pdo, $slug);
    }
    exhibition_records_ensure_table($pdo);
    special_event_records_ensure_tables($pdo);
    beneficiary_event_records_ensure_tables($pdo);
    important_day_records_ensure_table($pdo);
    shg_gosthi_records_ensure_tables($pdo);
    lecture_records_ensure_table($pdo);
    radio_talk_records_ensure_table($pdo);
    tv_talk_records_ensure_table($pdo);

    $activityIds = array_values(array_filter(array_unique(array_map('intval', $activityIds)), static fn(int $id): bool => $id > 0));
    if (!$activityIds) {
        return [];
    }
    $activityIdSql = implode(',', $activityIds);

    $report = [];
    foreach (activity_public_outreach_report_specs() as $spec) {
        try {
            $stmt = $pdo->query("SELECT * FROM {$spec['table']} WHERE is_active = 1 AND activity_id IN ({$activityIdSql}) ORDER BY id DESC");
            $records = $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $exception) {
            $records = [];
        }

        foreach ($records as $record) {
            $dateField = (string) $spec['date'];
            $year = isset($spec['year']) && trim((string) ($record[$spec['year']] ?? '')) !== ''
                ? financial_year_normalize((string) $record[$spec['year']])
                : current_financial_year((string) ($record[$dateField] ?? ''));
            $type = (string) $spec['label'];

            if (!isset($report[$year][$type])) {
                $report[$year][$type] = [
                    'type' => $type,
                    'count' => 0,
                    'dates' => [],
                    'male' => 0,
                    'female' => 0,
                    'has_male' => false,
                    'has_female' => false,
                ];
            }

            $report[$year][$type]['count']++;
            $dateLabel = activity_outreach_report_date_range($record, $dateField, $spec['date_to'] ?? null);
            if ($dateLabel !== 'Not specified') {
                $report[$year][$type]['dates'][$dateLabel] = true;
            }
            if (isset($spec['male'])) {
                $report[$year][$type]['male'] += max(0, (int) ($record[$spec['male']] ?? 0));
                $report[$year][$type]['has_male'] = true;
            }
            if (isset($spec['female'])) {
                $report[$year][$type]['female'] += max(0, (int) ($record[$spec['female']] ?? 0));
                $report[$year][$type]['has_female'] = true;
            }
        }
    }

    uksort($report, static function (string $a, string $b): int {
        preg_match('/\d{4}/', $a, $aMatch);
        preg_match('/\d{4}/', $b, $bMatch);
        return ((int) ($bMatch[0] ?? 0)) <=> ((int) ($aMatch[0] ?? 0));
    });

    return $report;
}

function activity_training_record_fields(): array
{
    return [
        'training_name' => 'Name of training',
        'discipline' => 'Discipline',
        'training_type' => 'Type of Training',
        'training_area' => 'Training Area',
        'duration_from' => 'From Date',
        'duration_to' => 'To Date',
        'farmers_men' => 'Farmers - Men',
        'farmers_women' => 'Farmers - Women',
        'rural_youth_men' => 'Rural Youth - Men',
        'rural_youth_women' => 'Rural Youth - Women',
        'ef_men' => 'EF - Men',
        'ef_women' => 'EF - Women',
        'total_participants' => 'Total No. of participants',
        'photograph_path' => 'Photograph',
        'photograph_path_2' => 'Photograph 2',
        'photograph_path_3' => 'Photograph 3',
        'photograph_path_4' => 'Photograph 4',
        'photograph_path_5' => 'Photograph 5',
    ];
}

function activity_training_record_from_details(string $details): array
{
    $empty = array_fill_keys(array_keys(activity_training_record_fields()), '');
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'training_record') {
        return $empty;
    }

    $data = array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
    if (($data['farmers_women'] ?? '') === '' && isset($decoded['data']['ffw_participants'])) {
        $data['farmers_women'] = (string) $decoded['data']['ffw_participants'];
    }
    if (($data['rural_youth_men'] ?? '') === '' && isset($decoded['data']['ry_participants'])) {
        $data['rural_youth_men'] = (string) $decoded['data']['ry_participants'];
    }
    if (($data['ef_men'] ?? '') === '' && isset($decoded['data']['ef_participants'])) {
        $data['ef_men'] = (string) $decoded['data']['ef_participants'];
    }

    return $data;
}

function training_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS training_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL UNIQUE,
        training_name VARCHAR(220) NOT NULL,
        training_date DATE NULL,
        training_location VARCHAR(220),
        discipline VARCHAR(160),
        training_type VARCHAR(40),
        training_area VARCHAR(220),
        duration_from DATE NULL,
        duration_to DATE NULL,
        farmers_men INT DEFAULT 0,
        farmers_women INT DEFAULT 0,
        rural_youth_men INT DEFAULT 0,
        rural_youth_women INT DEFAULT 0,
        ef_men INT DEFAULT 0,
        ef_women INT DEFAULT 0,
        farm_women INT DEFAULT 0,
        rural_youth INT DEFAULT 0,
        ef INT DEFAULT 0,
        total_participants INT DEFAULT 0,
        photograph_path VARCHAR(255),
        photograph_path_2 VARCHAR(255),
        photograph_path_3 VARCHAR(255),
        photograph_path_4 VARCHAR(255),
        photograph_path_5 VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
    $columns = [
        'training_type' => 'ALTER TABLE training_records ADD COLUMN training_type VARCHAR(40) NULL AFTER discipline',
        'training_area' => 'ALTER TABLE training_records ADD COLUMN training_area VARCHAR(220) NULL AFTER training_type',
        'duration_from' => 'ALTER TABLE training_records ADD COLUMN duration_from DATE NULL AFTER training_area',
        'duration_to' => 'ALTER TABLE training_records ADD COLUMN duration_to DATE NULL AFTER duration_from',
        'farmers_men' => 'ALTER TABLE training_records ADD COLUMN farmers_men INT DEFAULT 0 AFTER duration_to',
        'farmers_women' => 'ALTER TABLE training_records ADD COLUMN farmers_women INT DEFAULT 0 AFTER farmers_men',
        'rural_youth_men' => 'ALTER TABLE training_records ADD COLUMN rural_youth_men INT DEFAULT 0 AFTER farmers_women',
        'rural_youth_women' => 'ALTER TABLE training_records ADD COLUMN rural_youth_women INT DEFAULT 0 AFTER rural_youth_men',
        'ef_men' => 'ALTER TABLE training_records ADD COLUMN ef_men INT DEFAULT 0 AFTER rural_youth_women',
        'ef_women' => 'ALTER TABLE training_records ADD COLUMN ef_women INT DEFAULT 0 AFTER ef_men',
        'photograph_path_2' => 'ALTER TABLE training_records ADD COLUMN photograph_path_2 VARCHAR(255) NULL',
        'photograph_path_3' => 'ALTER TABLE training_records ADD COLUMN photograph_path_3 VARCHAR(255) NULL',
        'photograph_path_4' => 'ALTER TABLE training_records ADD COLUMN photograph_path_4 VARCHAR(255) NULL',
        'photograph_path_5' => 'ALTER TABLE training_records ADD COLUMN photograph_path_5 VARCHAR(255) NULL',
    ];
    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function training_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    training_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM training_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_training_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_training_record_fields()), '');
    $record = training_record_from_database($pdo, (int) ($editing['id'] ?? 0));
    if ($record) {
        return array_merge($empty, array_intersect_key($record, $empty));
    }

    return activity_training_record_from_details((string) ($editing['details'] ?? ''));
}

function training_record_post_value(string $field): string
{
    return trim((string) ($_POST['training_record'][$field] ?? ''));
}

function training_record_int_value(string $field): int
{
    return max(0, (int) training_record_post_value($field));
}

function training_record_date_value(string $field): ?string
{
    $value = training_record_post_value($field);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function training_record_date_input(?string $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function training_record_total_participants(array $trainingRecord): int
{
    return max(0, (int) ($trainingRecord['farmers_men'] ?? 0))
        + max(0, (int) ($trainingRecord['farmers_women'] ?? 0))
        + max(0, (int) ($trainingRecord['rural_youth_men'] ?? 0))
        + max(0, (int) ($trainingRecord['rural_youth_women'] ?? 0))
        + max(0, (int) ($trainingRecord['ef_men'] ?? 0))
        + max(0, (int) ($trainingRecord['ef_women'] ?? 0));
}

function activity_exposure_record_fields(): array
{
    return [
        'visit_date' => 'Date',
        'visit_name' => 'Name of Visit',
        'location' => 'Location',
        'organised_by' => 'Organised/Sponsored by',
        'beneficiaries_male' => 'No. of beneficiaries - Male',
        'beneficiaries_female' => 'No. of beneficiaries - Female',
    ];
}

function activity_visit_record_tables(): array
{
    return [
        'exposure-visit' => 'exposure_visit',
        'learning-tours' => 'learning_tours',
        'field-exposure' => 'field_exposure',
    ];
}

function activity_is_visit_record_slug(string $slug): bool
{
    return array_key_exists(activity_canonical_slug($slug), activity_visit_record_tables());
}

function activity_visit_record_table(string $slug): string
{
    $canonical = activity_canonical_slug($slug);
    $tables = activity_visit_record_tables();

    return $tables[$canonical] ?? $tables['exposure-visit'];
}

function activity_exposure_record_from_details(string $details): array
{
    $empty = array_fill_keys(array_keys(activity_exposure_record_fields()), '');
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'exposure_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function exposure_visit_records_ensure_table(?PDO $pdo, string $slug = 'exposure-visit'): void
{
    if (!$pdo) {
        return;
    }
    $table = activity_visit_record_table($slug);

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        visit_date DATE NULL,
        visit_name VARCHAR(255) NOT NULL,
        location VARCHAR(220) NULL,
        organised_by VARCHAR(220) NULL,
        beneficiaries_male INT DEFAULT 0,
        beneficiaries_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    foreach ([
        'organised_by' => "ALTER TABLE {$table} ADD COLUMN organised_by VARCHAR(220) NULL AFTER location",
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists
        }
    }
}

function exposure_visit_record_from_database(?PDO $pdo, int $activityId, string $slug = 'exposure-visit'): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    $table = activity_visit_record_table($slug);
    exposure_visit_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE activity_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_exposure_record_for_form(?PDO $pdo, ?array $editing, string $slug = ''): array
{
    $empty = array_fill_keys(array_keys(activity_exposure_record_fields()), '');
    $recordSlug = $slug !== '' ? $slug : (string) ($editing['slug'] ?? 'exposure-visit');
    $record = exposure_visit_record_from_database($pdo, (int) ($editing['id'] ?? 0), $recordSlug);

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : activity_exposure_record_from_details((string) ($editing['details'] ?? ''));
}

function exposure_record_post_value(string $field): string
{
    return trim((string) ($_POST['exposure_record'][$field] ?? ''));
}

function exposure_record_int_value(string $field): int
{
    return max(0, (int) exposure_record_post_value($field));
}

function exposure_record_total_beneficiaries(array $exposureRecord): int
{
    return max(0, (int) ($exposureRecord['beneficiaries_male'] ?? 0))
        + max(0, (int) ($exposureRecord['beneficiaries_female'] ?? 0));
}

function exposure_record_details_json(): string
{
    $data = [
        'visit_date' => exposure_record_post_value('visit_date'),
        'visit_name' => exposure_record_post_value('visit_name'),
        'location' => exposure_record_post_value('location'),
        'organised_by' => exposure_record_post_value('organised_by'),
        'beneficiaries_male' => (string) exposure_record_int_value('beneficiaries_male'),
        'beneficiaries_female' => (string) exposure_record_int_value('beneficiaries_female'),
    ];

    return json_encode(['type' => 'exposure_record', 'data' => $data], JSON_UNESCAPED_SLASHES);
}

function exposure_visit_record_save(?PDO $pdo, int $activityId, string $slug = 'exposure-visit'): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    $table = activity_visit_record_table($slug);
    exposure_visit_records_ensure_table($pdo, $slug);
    $visitDate = exposure_record_post_value('visit_date');
    $visitDate = $visitDate !== '' ? $visitDate : null;

    $stmt = $pdo->prepare(
        "INSERT INTO {$table}
            (activity_id, visit_date, visit_name, location, organised_by, beneficiaries_male, beneficiaries_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            visit_date = VALUES(visit_date),
            visit_name = VALUES(visit_name),
            location = VALUES(location),
            organised_by = VALUES(organised_by),
            beneficiaries_male = VALUES(beneficiaries_male),
            beneficiaries_female = VALUES(beneficiaries_female),
            is_active = 1"
    );
    $stmt->execute([
        $activityId,
        $visitDate,
        exposure_record_post_value('visit_name'),
        exposure_record_post_value('location'),
        exposure_record_post_value('organised_by'),
        exposure_record_int_value('beneficiaries_male'),
        exposure_record_int_value('beneficiaries_female'),
    ]);
}

function activity_exhibition_record_fields(): array
{
    return [
        'year' => 'Year',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'organised_by' => 'Organised',
        'exhibition_title' => 'Title of the Exhibition',
        'venue' => 'Venue',
        'collaboration' => 'Collaboration (if any)',
        'visitors_male' => 'No. of visitors - Male',
        'visitors_female' => 'No. of visitors - Female',
    ];
}

function activity_exhibition_record_from_details(string $details): array
{
    $empty = array_fill_keys(array_keys(activity_exhibition_record_fields()), '');
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'exhibition_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function exhibition_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS exhibition_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        date_from DATE NULL,
        date_to DATE NULL,
        organised_by VARCHAR(220) NULL,
        exhibition_title VARCHAR(255) NOT NULL,
        venue VARCHAR(220) NULL,
        collaboration VARCHAR(220) NULL,
        visitors_male INT DEFAULT 0,
        visitors_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function exhibition_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    exhibition_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM exhibition_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_exhibition_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_exhibition_record_fields()), '');
    $record = exhibition_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : activity_exhibition_record_from_details((string) ($editing['details'] ?? ''));
}

function exhibition_record_post_value(string $field): string
{
    return trim((string) ($_POST['exhibition_record'][$field] ?? ''));
}

function exhibition_record_date_value(string $field): ?string
{
    $value = exhibition_record_post_value($field);
    return $value === '' ? null : $value;
}

function exhibition_record_int_value(string $field): int
{
    return max(0, (int) exhibition_record_post_value($field));
}

function exhibition_record_total_visitors(array $exhibitionRecord): int
{
    return max(0, (int) ($exhibitionRecord['visitors_male'] ?? 0))
        + max(0, (int) ($exhibitionRecord['visitors_female'] ?? 0));
}

function exhibition_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    exhibition_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO exhibition_records
            (activity_id, year, date_from, date_to, organised_by, exhibition_title, venue, collaboration, visitors_male, visitors_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            date_from = VALUES(date_from),
            date_to = VALUES(date_to),
            organised_by = VALUES(organised_by),
            exhibition_title = VALUES(exhibition_title),
            venue = VALUES(venue),
            collaboration = VALUES(collaboration),
            visitors_male = VALUES(visitors_male),
            visitors_female = VALUES(visitors_female),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        exhibition_record_post_value('year'),
        exhibition_record_date_value('date_from'),
        exhibition_record_date_value('date_to'),
        exhibition_record_post_value('organised_by'),
        exhibition_record_post_value('exhibition_title'),
        exhibition_record_post_value('venue'),
        exhibition_record_post_value('collaboration'),
        exhibition_record_int_value('visitors_male'),
        exhibition_record_int_value('visitors_female'),
    ]);
}

function activity_special_event_record_types(): array
{
    return [
        'field-day' => [
            'table' => 'field_day_records',
            'label' => 'Field Day',
            'title_label' => 'Title of the Field Day',
        ],
        'kisan-mela' => [
            'table' => 'kisan_mela_records',
            'label' => 'Kisan Mela',
            'title_label' => 'Title of the Kisan Mela',
        ],
        'pm-webcasting' => [
            'table' => 'pm_webcasting_records',
            'label' => 'PM Webcasting',
            'title_label' => 'Title of the PM Webcasting',
        ],
    ];
}

function activity_is_special_event_record_slug(string $slug): bool
{
    return array_key_exists(activity_canonical_slug($slug), activity_special_event_record_types());
}

function activity_special_event_record_config(string $slug): array
{
    $canonical = activity_canonical_slug($slug);
    $types = activity_special_event_record_types();

    return $types[$canonical] ?? $types['field-day'];
}

function activity_special_event_record_fields(): array
{
    return [
        'year' => 'Year',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'organised_by' => 'Organised',
        'event_title' => 'Title',
        'venue' => 'Venue',
        'collaboration' => 'Collaboration (if any)',
        'visitors_male' => 'No. of visitors - Male',
        'visitors_female' => 'No. of visitors - Female',
    ];
}

function activity_special_event_record_from_details(string $details): array
{
    $empty = array_fill_keys(array_keys(activity_special_event_record_fields()), '');
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'special_event_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function special_event_records_ensure_table(?PDO $pdo, string $slug): void
{
    if (!$pdo) {
        return;
    }

    $config = activity_special_event_record_config($slug);
    $table = $config['table'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        date_from DATE NULL,
        date_to DATE NULL,
        organised_by VARCHAR(220) NULL,
        event_title VARCHAR(255) NOT NULL,
        venue VARCHAR(220) NULL,
        collaboration VARCHAR(220) NULL,
        visitors_male INT DEFAULT 0,
        visitors_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function special_event_records_ensure_tables(?PDO $pdo): void
{
    foreach (array_keys(activity_special_event_record_types()) as $slug) {
        special_event_records_ensure_table($pdo, $slug);
    }
}

function special_event_record_from_database(?PDO $pdo, int $activityId, string $slug): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    $config = activity_special_event_record_config($slug);
    $table = $config['table'];
    special_event_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE activity_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_special_event_record_for_form(?PDO $pdo, ?array $editing, string $slug): array
{
    $empty = array_fill_keys(array_keys(activity_special_event_record_fields()), '');
    $record = special_event_record_from_database($pdo, (int) ($editing['id'] ?? 0), $slug);

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : activity_special_event_record_from_details((string) ($editing['details'] ?? ''));
}

function special_event_record_post_value(string $field): string
{
    return trim((string) ($_POST['special_event_record'][$field] ?? ''));
}

function special_event_record_date_value(string $field): ?string
{
    $value = special_event_record_post_value($field);
    return $value === '' ? null : $value;
}

function special_event_record_int_value(string $field): int
{
    return max(0, (int) special_event_record_post_value($field));
}

function special_event_record_total_visitors(array $eventRecord): int
{
    return max(0, (int) ($eventRecord['visitors_male'] ?? 0))
        + max(0, (int) ($eventRecord['visitors_female'] ?? 0));
}

function special_event_record_save(?PDO $pdo, int $activityId, string $slug): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    $config = activity_special_event_record_config($slug);
    $table = $config['table'];
    special_event_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare(
        "INSERT INTO {$table}
            (activity_id, year, date_from, date_to, organised_by, event_title, venue, collaboration, visitors_male, visitors_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            date_from = VALUES(date_from),
            date_to = VALUES(date_to),
            organised_by = VALUES(organised_by),
            event_title = VALUES(event_title),
            venue = VALUES(venue),
            collaboration = VALUES(collaboration),
            visitors_male = VALUES(visitors_male),
            visitors_female = VALUES(visitors_female),
            is_active = 1"
    );
    $stmt->execute([
        $activityId,
        special_event_record_post_value('year'),
        special_event_record_date_value('date_from'),
        special_event_record_date_value('date_to'),
        special_event_record_post_value('organised_by'),
        special_event_record_post_value('event_title'),
        special_event_record_post_value('venue'),
        special_event_record_post_value('collaboration'),
        special_event_record_int_value('visitors_male'),
        special_event_record_int_value('visitors_female'),
    ]);
}

function activity_beneficiary_event_record_types(): array
{
    return [
        'animal-health-camp' => ['table' => 'animal_health_camp_records', 'label' => 'Animal Health Camp'],
        'awareness-camp' => ['table' => 'awareness_camp_records', 'label' => 'Awareness Camp'],
        'diagnostic-visits' => ['table' => 'diagnostic_visit_records', 'label' => 'Diagnostic Visit'],
        'farm-science-club-conveners-meet' => ['table' => 'farm_science_club_conveners_meet_records', 'label' => 'Farm Science Club Conveners Meet'],
        'farmer-scientists-interaction' => ['table' => 'farmer_scientist_interaction_records', 'label' => 'Farmer Scientist Interaction'],
        'film-show' => ['table' => 'film_show_records', 'label' => 'Film Show'],
        'group-meetings-discussion' => ['table' => 'group_meetings_records', 'label' => 'Group Meetings'],
        'mahila-mandal-conveners-meetings' => ['table' => 'mahila_mandal_conveners_meeting_records', 'label' => 'Mahila Mandal Conveners Meeting'],
        'plant-health-camp' => ['table' => 'plant_health_camp_records', 'label' => 'Plant Health Camp'],
        'pra-exercise' => ['table' => 'pra_exercise_records', 'label' => 'PRA Exercise'],
        'self-help-group-conveners-meetings' => ['table' => 'self_help_group_conveners_meeting_records', 'label' => 'Self Help Group Conveners Meeting'],
    ];
}

function activity_is_beneficiary_event_record_slug(string $slug): bool
{
    return array_key_exists(activity_canonical_slug($slug), activity_beneficiary_event_record_types());
}

function activity_beneficiary_event_record_config(string $slug): array
{
    $canonical = activity_canonical_slug($slug);
    $types = activity_beneficiary_event_record_types();

    return $types[$canonical] ?? $types['animal-health-camp'];
}

function activity_beneficiary_event_record_fields(): array
{
    return [
        'year' => 'Year',
        'event_date' => 'Date',
        'particulars' => 'Particulars',
        'topic' => 'Topic',
        'location' => 'Location',
        'beneficiaries_male' => 'Beneficiaries - Male',
        'beneficiaries_female' => 'Beneficiaries - Female',
    ];
}

function activity_beneficiary_event_record_from_details(string $details): array
{
    $empty = array_fill_keys(array_keys(activity_beneficiary_event_record_fields()), '');
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'beneficiary_event_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function beneficiary_event_records_ensure_table(?PDO $pdo, string $slug): void
{
    if (!$pdo) {
        return;
    }

    $config = activity_beneficiary_event_record_config($slug);
    $table = $config['table'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        event_date DATE NULL,
        particulars VARCHAR(255) NULL,
        topic VARCHAR(255) NULL,
        location VARCHAR(220) NULL,
        beneficiaries_male INT DEFAULT 0,
        beneficiaries_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function beneficiary_event_records_ensure_tables(?PDO $pdo): void
{
    foreach (array_keys(activity_beneficiary_event_record_types()) as $slug) {
        beneficiary_event_records_ensure_table($pdo, $slug);
    }
}

function beneficiary_event_record_from_database(?PDO $pdo, int $activityId, string $slug): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    $config = activity_beneficiary_event_record_config($slug);
    $table = $config['table'];
    beneficiary_event_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE activity_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_beneficiary_event_record_for_form(?PDO $pdo, ?array $editing, string $slug): array
{
    $empty = array_fill_keys(array_keys(activity_beneficiary_event_record_fields()), '');
    $record = beneficiary_event_record_from_database($pdo, (int) ($editing['id'] ?? 0), $slug);

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : activity_beneficiary_event_record_from_details((string) ($editing['details'] ?? ''));
}

function beneficiary_event_record_post_value(string $field): string
{
    return trim((string) ($_POST['beneficiary_event_record'][$field] ?? ''));
}

function beneficiary_event_record_date_value(string $field): ?string
{
    $value = beneficiary_event_record_post_value($field);
    return $value === '' ? null : $value;
}

function beneficiary_event_record_int_value(string $field): int
{
    return max(0, (int) beneficiary_event_record_post_value($field));
}

function beneficiary_event_record_total_beneficiaries(array $eventRecord): int
{
    return max(0, (int) ($eventRecord['beneficiaries_male'] ?? 0))
        + max(0, (int) ($eventRecord['beneficiaries_female'] ?? 0));
}

function beneficiary_event_record_save(?PDO $pdo, int $activityId, string $slug): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    $config = activity_beneficiary_event_record_config($slug);
    $table = $config['table'];
    beneficiary_event_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare(
        "INSERT INTO {$table}
            (activity_id, year, event_date, particulars, topic, location, beneficiaries_male, beneficiaries_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            event_date = VALUES(event_date),
            particulars = VALUES(particulars),
            topic = VALUES(topic),
            location = VALUES(location),
            beneficiaries_male = VALUES(beneficiaries_male),
            beneficiaries_female = VALUES(beneficiaries_female),
            is_active = 1"
    );
    $stmt->execute([
        $activityId,
        beneficiary_event_record_post_value('year'),
        beneficiary_event_record_date_value('event_date'),
        beneficiary_event_record_post_value('particulars'),
        beneficiary_event_record_post_value('topic'),
        beneficiary_event_record_post_value('location'),
        beneficiary_event_record_int_value('beneficiaries_male'),
        beneficiary_event_record_int_value('beneficiaries_female'),
    ]);
}

function important_day_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS important_day_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        event_date DATE NULL,
        topic VARCHAR(255) NULL,
        venue VARCHAR(220) NULL,
        beneficiaries_male INT DEFAULT 0,
        beneficiaries_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function important_day_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    important_day_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM important_day_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_important_day_record_from_details(string $details): array
{
    $empty = [
        'event_date' => '',
        'topic' => '',
        'venue' => '',
        'beneficiaries_male' => '',
        'beneficiaries_female' => '',
    ];
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'important_day_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function activity_important_day_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = [
        'event_date' => '',
        'topic' => '',
        'venue' => '',
        'beneficiaries_male' => '',
        'beneficiaries_female' => '',
    ];
    $record = important_day_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : activity_important_day_record_from_details((string) ($editing['details'] ?? ''));
}

function important_day_record_post_value(string $field): string
{
    return trim((string) ($_POST['important_day_record'][$field] ?? ''));
}

function important_day_record_date_value(): ?string
{
    $value = important_day_record_post_value('event_date');
    return $value === '' ? null : $value;
}

function important_day_record_int_value(string $field): int
{
    return max(0, (int) important_day_record_post_value($field));
}

function important_day_record_total_beneficiaries(array $record): int
{
    return max(0, (int) ($record['beneficiaries_male'] ?? 0))
        + max(0, (int) ($record['beneficiaries_female'] ?? 0));
}

function important_day_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    important_day_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO important_day_records
            (activity_id, event_date, topic, venue, beneficiaries_male, beneficiaries_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            event_date = VALUES(event_date),
            topic = VALUES(topic),
            venue = VALUES(venue),
            beneficiaries_male = VALUES(beneficiaries_male),
            beneficiaries_female = VALUES(beneficiaries_female),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        important_day_record_date_value(),
        important_day_record_post_value('topic'),
        important_day_record_post_value('venue'),
        important_day_record_int_value('beneficiaries_male'),
        important_day_record_int_value('beneficiaries_female'),
    ]);
}

function shg_gosthi_record_types(): array
{
    return [
        'formation-of-shgs' => ['table' => 'formation_of_shg_records', 'label' => 'Formation of SHGs'],
        'kisan-gosthi' => ['table' => 'kisan_gosthi_records', 'label' => 'Kisan Gosthi'],
    ];
}

function activity_is_shg_gosthi_record_slug(string $slug): bool
{
    return array_key_exists(activity_canonical_slug($slug), shg_gosthi_record_types());
}

function shg_gosthi_record_config(string $slug): array
{
    $canonical = activity_canonical_slug($slug);
    $types = shg_gosthi_record_types();

    return $types[$canonical] ?? $types['formation-of-shgs'];
}

function shg_gosthi_records_ensure_table(?PDO $pdo, string $slug): void
{
    if (!$pdo) {
        return;
    }

    $config = shg_gosthi_record_config($slug);
    $table = $config['table'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        record_name VARCHAR(255) NULL,
        formation_date DATE NULL,
        location VARCHAR(220) NULL,
        total_participants INT DEFAULT 0,
        remarks TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function shg_gosthi_records_ensure_tables(?PDO $pdo): void
{
    foreach (array_keys(shg_gosthi_record_types()) as $slug) {
        shg_gosthi_records_ensure_table($pdo, $slug);
    }
}

function shg_gosthi_record_from_database(?PDO $pdo, int $activityId, string $slug): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    $config = shg_gosthi_record_config($slug);
    $table = $config['table'];
    shg_gosthi_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE activity_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function shg_gosthi_record_from_details(string $details): array
{
    $empty = [
        'record_name' => '',
        'formation_date' => '',
        'location' => '',
        'total_participants' => '',
        'remarks' => '',
    ];
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'shg_gosthi_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function shg_gosthi_record_for_form(?PDO $pdo, ?array $editing, string $slug): array
{
    $empty = [
        'record_name' => '',
        'formation_date' => '',
        'location' => '',
        'total_participants' => '',
        'remarks' => '',
    ];
    $record = shg_gosthi_record_from_database($pdo, (int) ($editing['id'] ?? 0), $slug);

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : shg_gosthi_record_from_details((string) ($editing['details'] ?? ''));
}

function shg_gosthi_record_post_value(string $field): string
{
    return trim((string) ($_POST['shg_gosthi_record'][$field] ?? ''));
}

function shg_gosthi_record_date_value(): ?string
{
    $value = shg_gosthi_record_post_value('formation_date');
    return $value === '' ? null : $value;
}

function shg_gosthi_record_int_value(string $field): int
{
    return max(0, (int) shg_gosthi_record_post_value($field));
}

function shg_gosthi_record_save(?PDO $pdo, int $activityId, string $slug): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    $config = shg_gosthi_record_config($slug);
    $table = $config['table'];
    shg_gosthi_records_ensure_table($pdo, $slug);
    $stmt = $pdo->prepare(
        "INSERT INTO {$table}
            (activity_id, record_name, formation_date, location, total_participants, remarks, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            record_name = VALUES(record_name),
            formation_date = VALUES(formation_date),
            location = VALUES(location),
            total_participants = VALUES(total_participants),
            remarks = VALUES(remarks),
            is_active = 1"
    );
    $stmt->execute([
        $activityId,
        shg_gosthi_record_post_value('record_name'),
        shg_gosthi_record_date_value(),
        shg_gosthi_record_post_value('location'),
        shg_gosthi_record_int_value('total_participants'),
        shg_gosthi_record_post_value('remarks'),
    ]);
}

function lecture_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS lecture_delivered_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        programme_date DATE NULL,
        programme_name VARCHAR(255) NULL,
        topic VARCHAR(255) NULL,
        organised_by VARCHAR(220) NULL,
        venue VARCHAR(220) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function lecture_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    lecture_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM lecture_delivered_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function lecture_record_from_details(string $details): array
{
    $empty = [
        'programme_date' => '',
        'programme_name' => '',
        'topic' => '',
        'organised_by' => '',
        'venue' => '',
    ];
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'lecture_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function lecture_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = [
        'programme_date' => '',
        'programme_name' => '',
        'topic' => '',
        'organised_by' => '',
        'venue' => '',
    ];
    $record = lecture_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : lecture_record_from_details((string) ($editing['details'] ?? ''));
}

function lecture_record_post_value(string $field): string
{
    return trim((string) ($_POST['lecture_record'][$field] ?? ''));
}

function lecture_record_date_value(): ?string
{
    $value = lecture_record_post_value('programme_date');
    return $value === '' ? null : $value;
}

function lecture_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    lecture_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO lecture_delivered_records
            (activity_id, programme_date, programme_name, topic, organised_by, venue, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            programme_date = VALUES(programme_date),
            programme_name = VALUES(programme_name),
            topic = VALUES(topic),
            organised_by = VALUES(organised_by),
            venue = VALUES(venue),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        lecture_record_date_value(),
        lecture_record_post_value('programme_name'),
        lecture_record_post_value('topic'),
        lecture_record_post_value('organised_by'),
        lecture_record_post_value('venue'),
    ]);
}

function radio_talk_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS radio_talk_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        talk_date DATE NULL,
        topic VARCHAR(255) NULL,
        radio_station VARCHAR(220) NULL,
        targeted_beneficiaries VARCHAR(220) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec('ALTER TABLE radio_talk_records ADD COLUMN talk_date DATE NULL AFTER activity_id');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE radio_talk_records ADD COLUMN radio_station VARCHAR(220) NULL AFTER topic');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE radio_talk_records ADD COLUMN targeted_beneficiaries VARCHAR(220) NULL AFTER radio_station');
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function radio_talk_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    radio_talk_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM radio_talk_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function radio_talk_record_from_details(string $details): array
{
    $empty = [
        'talk_date' => '',
        'topic' => '',
        'radio_station' => '',
        'targeted_beneficiaries' => '',
    ];
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'radio_talk_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function radio_talk_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = [
        'talk_date' => '',
        'topic' => '',
        'radio_station' => '',
        'targeted_beneficiaries' => '',
    ];
    $record = radio_talk_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : radio_talk_record_from_details((string) ($editing['details'] ?? ''));
}

function radio_talk_record_post_value(string $field): string
{
    return trim((string) ($_POST['radio_talk_record'][$field] ?? ''));
}

function radio_talk_record_date_value(): ?string
{
    $value = radio_talk_record_post_value('talk_date');
    return $value === '' ? null : $value;
}

function radio_talk_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    radio_talk_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO radio_talk_records
            (activity_id, talk_date, topic, radio_station, targeted_beneficiaries, is_active)
         VALUES
            (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            talk_date = VALUES(talk_date),
            topic = VALUES(topic),
            radio_station = VALUES(radio_station),
            targeted_beneficiaries = VALUES(targeted_beneficiaries),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        radio_talk_record_date_value(),
        radio_talk_record_post_value('topic'),
        radio_talk_record_post_value('radio_station'),
        radio_talk_record_post_value('targeted_beneficiaries'),
    ]);
}

function tv_talk_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS tv_talk_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        talk_date DATE NULL,
        topic VARCHAR(255) NULL,
        tv_station VARCHAR(220) NULL,
        targeted_beneficiaries VARCHAR(220) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec('ALTER TABLE tv_talk_records ADD COLUMN talk_date DATE NULL AFTER activity_id');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE tv_talk_records ADD COLUMN tv_station VARCHAR(220) NULL AFTER topic');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE tv_talk_records ADD COLUMN targeted_beneficiaries VARCHAR(220) NULL AFTER tv_station');
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function tv_talk_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    tv_talk_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM tv_talk_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function tv_talk_record_from_details(string $details): array
{
    $empty = [
        'talk_date' => '',
        'topic' => '',
        'tv_station' => '',
        'targeted_beneficiaries' => '',
    ];
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'tv_talk_record') {
        return $empty;
    }

    return array_merge($empty, array_intersect_key($decoded['data'] ?? [], $empty));
}

function tv_talk_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = [
        'talk_date' => '',
        'topic' => '',
        'tv_station' => '',
        'targeted_beneficiaries' => '',
    ];
    $record = tv_talk_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record
        ? array_merge($empty, array_intersect_key($record, $empty))
        : tv_talk_record_from_details((string) ($editing['details'] ?? ''));
}

function tv_talk_record_post_value(string $field): string
{
    return trim((string) ($_POST['tv_talk_record'][$field] ?? ''));
}

function tv_talk_record_date_value(): ?string
{
    $value = tv_talk_record_post_value('talk_date');
    return $value === '' ? null : $value;
}

function tv_talk_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    tv_talk_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO tv_talk_records
            (activity_id, talk_date, topic, tv_station, targeted_beneficiaries, is_active)
         VALUES
            (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            talk_date = VALUES(talk_date),
            topic = VALUES(topic),
            tv_station = VALUES(tv_station),
            targeted_beneficiaries = VALUES(targeted_beneficiaries),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        tv_talk_record_date_value(),
        tv_talk_record_post_value('topic'),
        tv_talk_record_post_value('tv_station'),
        tv_talk_record_post_value('targeted_beneficiaries'),
    ]);
}

function training_photo_uploads(?PDO $pdo, int $activityId, string $programmeName, string $currentActivityPhoto = ''): array
{
    $current = training_record_from_database($pdo, $activityId) ?: [];
    $paths = [
        1 => (string) (($current['photograph_path'] ?? '') ?: $currentActivityPhoto),
        2 => (string) ($current['photograph_path_2'] ?? ''),
        3 => (string) ($current['photograph_path_3'] ?? ''),
        4 => (string) ($current['photograph_path_4'] ?? ''),
        5 => (string) ($current['photograph_path_5'] ?? ''),
    ];

    for ($index = 1; $index <= 5; $index++) {
        $paths[$index] = upload_value_to_folder_named(
            'training_photo_' . $index,
            $paths[$index],
            'trainingphoto',
            $programmeName . '-photo-' . $index
        );
    }

    return $paths;
}

function training_record_save(?PDO $pdo, int $activityId, array $photographPaths): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    training_records_ensure_table($pdo);
    $durationFrom = training_record_date_value('duration_from');
    $durationTo = training_record_date_value('duration_to');
    $trainingDate = $durationFrom ? date('Y-m-d', strtotime($durationFrom)) : null;
    $totalParticipants = training_record_int_value('farmers_men')
        + training_record_int_value('farmers_women')
        + training_record_int_value('rural_youth_men')
        + training_record_int_value('rural_youth_women')
        + training_record_int_value('ef_men')
        + training_record_int_value('ef_women');
    $values = [
        $activityId,
        training_record_post_value('training_name'),
        $trainingDate,
        training_record_post_value('discipline'),
        training_record_post_value('training_type'),
        training_record_post_value('training_area'),
        $durationFrom,
        $durationTo,
        training_record_int_value('farmers_men'),
        training_record_int_value('farmers_women'),
        training_record_int_value('rural_youth_men'),
        training_record_int_value('rural_youth_women'),
        training_record_int_value('ef_men'),
        training_record_int_value('ef_women'),
        $totalParticipants,
        $photographPaths[1] ?? '',
        $photographPaths[2] ?? '',
        $photographPaths[3] ?? '',
        $photographPaths[4] ?? '',
        $photographPaths[5] ?? '',
    ];

    $sql = 'INSERT INTO training_records
        (activity_id, training_name, training_date, discipline, training_type, training_area, duration_from, duration_to, farmers_men, farmers_women, rural_youth_men, rural_youth_women, ef_men, ef_women, total_participants, photograph_path, photograph_path_2, photograph_path_3, photograph_path_4, photograph_path_5)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            training_name = VALUES(training_name),
            training_date = VALUES(training_date),
            discipline = VALUES(discipline),
            training_type = VALUES(training_type),
            training_area = VALUES(training_area),
            duration_from = VALUES(duration_from),
            duration_to = VALUES(duration_to),
            farmers_men = VALUES(farmers_men),
            farmers_women = VALUES(farmers_women),
            rural_youth_men = VALUES(rural_youth_men),
            rural_youth_women = VALUES(rural_youth_women),
            ef_men = VALUES(ef_men),
            ef_women = VALUES(ef_women),
            total_participants = VALUES(total_participants),
            photograph_path = VALUES(photograph_path),
            photograph_path_2 = VALUES(photograph_path_2),
            photograph_path_3 = VALUES(photograph_path_3),
            photograph_path_4 = VALUES(photograph_path_4),
            photograph_path_5 = VALUES(photograph_path_5),
            is_active = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function activity_oft_record_fields(): array
{
    return [
        'year' => 'Year',
        'crop' => 'Crop',
        'discipline' => 'Discipline',
        'thematic_area' => 'Thematic Area',
        'oft_title' => 'Title of OFT',
        'location' => 'Location',
        'season' => 'Season',
        'results_summary' => 'Result of OFT',
    ];
}

function oft_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS oft_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        crop VARCHAR(180) NULL,
        oft_title VARCHAR(255) NOT NULL,
        problem_diagnosed TEXT NULL,
        thematic_area VARCHAR(180) NULL,
        discipline VARCHAR(160) NOT NULL,
        season VARCHAR(80) NULL,
        season_year VARCHAR(80) NULL,
        oft_date DATE NULL,
        location VARCHAR(220) NULL,
        number_of_trials INT DEFAULT 0,
        number_of_farmers INT DEFAULT 0,
        technology_tested TEXT NULL,
        results_summary TEXT NULL,
        result_file VARCHAR(255) NULL,
        photograph_path VARCHAR(255) NULL,
        photograph_path_2 VARCHAR(255) NULL,
        photograph_path_3 VARCHAR(255) NULL,
        photograph_path_4 VARCHAR(255) NULL,
        photograph_path_5 VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    $columns = [
        'year' => 'ALTER TABLE oft_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE oft_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'season' => 'ALTER TABLE oft_records ADD COLUMN season VARCHAR(80) NULL AFTER discipline',
        'result_file' => 'ALTER TABLE oft_records ADD COLUMN result_file VARCHAR(255) NULL AFTER results_summary',
        'photograph_path_2' => 'ALTER TABLE oft_records ADD COLUMN photograph_path_2 VARCHAR(255) NULL',
        'photograph_path_3' => 'ALTER TABLE oft_records ADD COLUMN photograph_path_3 VARCHAR(255) NULL',
        'photograph_path_4' => 'ALTER TABLE oft_records ADD COLUMN photograph_path_4 VARCHAR(255) NULL',
        'photograph_path_5' => 'ALTER TABLE oft_records ADD COLUMN photograph_path_5 VARCHAR(255) NULL',
    ];

    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function oft_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    oft_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM oft_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_oft_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_oft_record_fields()), '');
    $record = oft_record_from_database($pdo, (int) ($editing['id'] ?? 0));
    if ($record) {
        $data = array_merge($empty, array_intersect_key($record, $empty));
        if (($data['season'] ?? '') === '' && !empty($record['season_year'])) {
            $data['season'] = (string) $record['season_year'];
        }
        return $data;
    }

    return $empty;
}

function oft_record_post_value(string $field): string
{
    return trim((string) ($_POST['oft_record'][$field] ?? ''));
}

function oft_crop_options(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    oft_records_ensure_table($pdo);
    $stmt = $pdo->query("SELECT DISTINCT crop FROM oft_records WHERE crop IS NOT NULL AND crop <> '' ORDER BY crop ASC");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

function oft_photo_uploads(?PDO $pdo, int $activityId, string $oftTitle, string $currentActivityPhoto = ''): array
{
    $current = oft_record_from_database($pdo, $activityId) ?: [];
    $paths = [
        1 => (string) (($current['photograph_path'] ?? '') ?: $currentActivityPhoto),
        2 => (string) ($current['photograph_path_2'] ?? ''),
        3 => (string) ($current['photograph_path_3'] ?? ''),
        4 => (string) ($current['photograph_path_4'] ?? ''),
        5 => (string) ($current['photograph_path_5'] ?? ''),
    ];

    for ($index = 1; $index <= 5; $index++) {
        $paths[$index] = upload_value_to_folder_named(
            'oft_photo_' . $index,
            $paths[$index],
            'oftphoto',
            $oftTitle . '-photo-' . $index
        );
    }

    return $paths;
}

function oft_record_save(?PDO $pdo, int $activityId, array $photographPaths): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    oft_records_ensure_table($pdo);

    $year = oft_record_post_value('year');
    $season = oft_record_post_value('season');
    $title = oft_record_post_value('oft_title');
    $discipline = oft_record_post_value('discipline');
    $thematicArea = oft_record_post_value('thematic_area');
    $crop = oft_record_post_value('crop');
    $location = oft_record_post_value('location');
    $resultsSummary = oft_record_post_value('results_summary');
    $oftDate = null;
    if (preg_match('/^\d{4}$/', $year)) {
        $oftDate = $year . '-01-01';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO oft_records
            (activity_id, year, crop, oft_title, thematic_area, discipline, location, season, season_year, oft_date, results_summary, photograph_path, photograph_path_2, photograph_path_3, photograph_path_4, photograph_path_5, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            crop = VALUES(crop),
            oft_title = VALUES(oft_title),
            thematic_area = VALUES(thematic_area),
            discipline = VALUES(discipline),
            location = VALUES(location),
            season = VALUES(season),
            season_year = VALUES(season_year),
            oft_date = VALUES(oft_date),
            results_summary = VALUES(results_summary),
            photograph_path = VALUES(photograph_path),
            photograph_path_2 = VALUES(photograph_path_2),
            photograph_path_3 = VALUES(photograph_path_3),
            photograph_path_4 = VALUES(photograph_path_4),
            photograph_path_5 = VALUES(photograph_path_5),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        $year,
        $crop,
        $title,
        $thematicArea,
        $discipline,
        $location,
        $season,
        trim($season . ($year !== '' ? ' ' . $year : '')),
        $oftDate,
        $resultsSummary,
        $photographPaths[1] ?? '',
        $photographPaths[2] ?? '',
        $photographPaths[3] ?? '',
        $photographPaths[4] ?? '',
        $photographPaths[5] ?? '',
    ]);
}

function activity_fld_record_fields(): array
{
    return [
        'year' => 'Year',
        'crop' => 'Crop',
        'discipline' => 'Discipline',
        'thematic_area' => 'Thematic Area',
        'technology_tested' => 'Technology Demonstrated',
        'season' => 'Season',
        'area_covered' => 'Area Covered (Ha/Nos)',
        'location' => 'Location',
        'number_of_farmers' => 'No of Farmers',
    ];
}

function fld_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS fld_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        crop VARCHAR(180) NULL,
        fld_title VARCHAR(255) NOT NULL,
        problem_diagnosed TEXT NULL,
        thematic_area VARCHAR(180) NULL,
        discipline VARCHAR(160) NOT NULL,
        season VARCHAR(80) NULL,
        season_year VARCHAR(80) NULL,
        fld_date DATE NULL,
        location VARCHAR(220) NULL,
        area_covered VARCHAR(80) NULL,
        number_of_trials INT DEFAULT 0,
        number_of_farmers INT DEFAULT 0,
        technology_tested TEXT NULL,
        results_summary TEXT NULL,
        photograph_path VARCHAR(255) NULL,
        photograph_path_2 VARCHAR(255) NULL,
        photograph_path_3 VARCHAR(255) NULL,
        photograph_path_4 VARCHAR(255) NULL,
        photograph_path_5 VARCHAR(255) NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    foreach ([
        'year' => 'ALTER TABLE fld_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE fld_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'season' => 'ALTER TABLE fld_records ADD COLUMN season VARCHAR(80) NULL AFTER discipline',
        'area_covered' => 'ALTER TABLE fld_records ADD COLUMN area_covered VARCHAR(80) NULL AFTER location',
        'photograph_path_2' => 'ALTER TABLE fld_records ADD COLUMN photograph_path_2 VARCHAR(255) NULL',
        'photograph_path_3' => 'ALTER TABLE fld_records ADD COLUMN photograph_path_3 VARCHAR(255) NULL',
        'photograph_path_4' => 'ALTER TABLE fld_records ADD COLUMN photograph_path_4 VARCHAR(255) NULL',
        'photograph_path_5' => 'ALTER TABLE fld_records ADD COLUMN photograph_path_5 VARCHAR(255) NULL',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function fld_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    fld_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM fld_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_fld_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_fld_record_fields()), '');
    $record = fld_record_from_database($pdo, (int) ($editing['id'] ?? 0));
    if ($record) {
        $data = array_merge($empty, array_intersect_key($record, $empty));
        if (($data['season'] ?? '') === '' && !empty($record['season_year'])) {
            $data['season'] = (string) $record['season_year'];
        }
        return $data;
    }

    return $empty;
}

function fld_record_post_value(string $field): string
{
    return trim((string) ($_POST['fld_record'][$field] ?? ''));
}

function fld_crop_options(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    fld_records_ensure_table($pdo);
    $stmt = $pdo->query("SELECT DISTINCT crop FROM fld_records WHERE crop IS NOT NULL AND crop <> '' ORDER BY crop ASC");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

function fld_photo_uploads(?PDO $pdo, int $activityId, string $fldTitle, string $currentActivityPhoto = ''): array
{
    $current = fld_record_from_database($pdo, $activityId) ?: [];
    $paths = [
        1 => (string) (($current['photograph_path'] ?? '') ?: $currentActivityPhoto),
        2 => (string) ($current['photograph_path_2'] ?? ''),
        3 => (string) ($current['photograph_path_3'] ?? ''),
        4 => (string) ($current['photograph_path_4'] ?? ''),
        5 => (string) ($current['photograph_path_5'] ?? ''),
    ];

    for ($index = 1; $index <= 5; $index++) {
        $paths[$index] = upload_value_to_folder_named(
            'fld_photo_' . $index,
            $paths[$index],
            'fldphoto',
            $fldTitle . '-photo-' . $index
        );
    }

    return $paths;
}

function fld_record_save(?PDO $pdo, int $activityId, array $photographPaths): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    fld_records_ensure_table($pdo);

    $year = fld_record_post_value('year');
    $season = fld_record_post_value('season');
    $technology = fld_record_post_value('technology_tested');
    $fldDate = preg_match('/^\d{4}$/', $year) ? $year . '-01-01' : null;
    $farmers = max(0, (int) fld_record_post_value('number_of_farmers'));

    $stmt = $pdo->prepare(
        'INSERT INTO fld_records
            (activity_id, year, crop, fld_title, thematic_area, discipline, season, season_year, fld_date, location, area_covered, number_of_farmers, technology_tested, photograph_path, photograph_path_2, photograph_path_3, photograph_path_4, photograph_path_5, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            crop = VALUES(crop),
            fld_title = VALUES(fld_title),
            thematic_area = VALUES(thematic_area),
            discipline = VALUES(discipline),
            season = VALUES(season),
            season_year = VALUES(season_year),
            fld_date = VALUES(fld_date),
            location = VALUES(location),
            area_covered = VALUES(area_covered),
            number_of_farmers = VALUES(number_of_farmers),
            technology_tested = VALUES(technology_tested),
            photograph_path = VALUES(photograph_path),
            photograph_path_2 = VALUES(photograph_path_2),
            photograph_path_3 = VALUES(photograph_path_3),
            photograph_path_4 = VALUES(photograph_path_4),
            photograph_path_5 = VALUES(photograph_path_5),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        $year,
        fld_record_post_value('crop'),
        $technology,
        fld_record_post_value('thematic_area'),
        fld_record_post_value('discipline'),
        $season,
        trim($season . ($year !== '' ? ' ' . $year : '')),
        $fldDate,
        fld_record_post_value('location'),
        fld_record_post_value('area_covered'),
        $farmers,
        $technology,
        $photographPaths[1] ?? '',
        $photographPaths[2] ?? '',
        $photographPaths[3] ?? '',
        $photographPaths[4] ?? '',
        $photographPaths[5] ?? '',
    ]);
}

function activity_awareness_record_fields(): array
{
    return [
        'year' => 'Year',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'thematic_area' => 'Thematic Area',
        'programme_title' => 'Programme Title',
        'farmers_male' => 'Farmers Male',
        'farmers_female' => 'Farmers Female',
        'ry_male' => 'RY Male',
        'ry_female' => 'RY Female',
        'officials_male' => 'Officials Male',
        'officials_female' => 'Officials Female',
    ];
}

function awareness_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS awareness_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        programme_date DATE NULL,
        date_from DATE NULL,
        date_to DATE NULL,
        thematic_area VARCHAR(180) NULL,
        programme_title VARCHAR(255) NOT NULL,
        farmers_male INT DEFAULT 0,
        farmers_female INT DEFAULT 0,
        ry_male INT DEFAULT 0,
        ry_female INT DEFAULT 0,
        officials_male INT DEFAULT 0,
        officials_female INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    foreach ([
        'date_from' => 'ALTER TABLE awareness_records ADD COLUMN date_from DATE NULL AFTER programme_date',
        'date_to' => 'ALTER TABLE awareness_records ADD COLUMN date_to DATE NULL AFTER date_from',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists
        }
    }
}

function awareness_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    awareness_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM awareness_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_awareness_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_awareness_record_fields()), '');
    $record = awareness_record_from_database($pdo, (int) ($editing['id'] ?? 0));
    if ($record) {
        if (empty($record['date_from']) && !empty($record['programme_date'])) {
            $record['date_from'] = $record['programme_date'];
        }
        if (empty($record['date_to']) && !empty($record['programme_date'])) {
            $record['date_to'] = $record['programme_date'];
        }
        return array_merge($empty, array_intersect_key($record, $empty));
    }

    return $empty;
}

function awareness_record_post_value(string $field): string
{
    return trim((string) ($_POST['awareness_record'][$field] ?? ''));
}

function awareness_record_int_value(string $field): int
{
    return max(0, (int) awareness_record_post_value($field));
}

function awareness_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    awareness_records_ensure_table($pdo);
    $dateFrom = awareness_record_post_value('date_from');
    $dateTo = awareness_record_post_value('date_to');
    $dateFrom = $dateFrom !== '' ? $dateFrom : null;
    $dateTo = $dateTo !== '' ? $dateTo : $dateFrom;

    $stmt = $pdo->prepare(
        'INSERT INTO awareness_records
            (activity_id, year, programme_date, date_from, date_to, thematic_area, programme_title, farmers_male, farmers_female, ry_male, ry_female, officials_male, officials_female, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            programme_date = VALUES(programme_date),
            date_from = VALUES(date_from),
            date_to = VALUES(date_to),
            thematic_area = VALUES(thematic_area),
            programme_title = VALUES(programme_title),
            farmers_male = VALUES(farmers_male),
            farmers_female = VALUES(farmers_female),
            ry_male = VALUES(ry_male),
            ry_female = VALUES(ry_female),
            officials_male = VALUES(officials_male),
            officials_female = VALUES(officials_female),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        awareness_record_post_value('year'),
        $dateFrom,
        $dateFrom,
        $dateTo,
        awareness_record_post_value('thematic_area'),
        awareness_record_post_value('programme_title'),
        awareness_record_int_value('farmers_male'),
        awareness_record_int_value('farmers_female'),
        awareness_record_int_value('ry_male'),
        awareness_record_int_value('ry_female'),
        awareness_record_int_value('officials_male'),
        awareness_record_int_value('officials_female'),
    ]);
}

function activity_technology_showcasing_fields(): array
{
    return [
        'year' => 'Year',
        'crop' => 'Crop',
        'variety' => 'Variety',
        'farmers_involved' => 'No of farmers involved',
        'area_covered' => 'Area Covered (HA)',
        'production_tonnes' => 'Production (In tonnes)',
    ];
}

function technology_showcasing_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS technology_showcasing_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        crop VARCHAR(180) NULL,
        variety VARCHAR(180) NULL,
        farmers_involved INT DEFAULT 0,
        area_covered VARCHAR(120) NULL,
        production_tonnes DECIMAL(12,3) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

}

function technology_showcasing_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    technology_showcasing_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM technology_showcasing_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_technology_showcasing_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_technology_showcasing_fields()), '');
    $record = technology_showcasing_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record ? array_merge($empty, array_intersect_key($record, $empty)) : $empty;
}

function technology_showcasing_record_post_value(string $field): string
{
    return trim((string) ($_POST['technology_showcasing_record'][$field] ?? ''));
}

function technology_showcasing_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    technology_showcasing_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO technology_showcasing_records
            (activity_id, year, crop, variety, farmers_involved, area_covered, production_tonnes, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            crop = VALUES(crop),
            variety = VALUES(variety),
            farmers_involved = VALUES(farmers_involved),
            area_covered = VALUES(area_covered),
            production_tonnes = VALUES(production_tonnes),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        technology_showcasing_record_post_value('year'),
        technology_showcasing_record_post_value('crop'),
        technology_showcasing_record_post_value('variety'),
        max(0, (int) technology_showcasing_record_post_value('farmers_involved')),
        technology_showcasing_record_post_value('area_covered'),
        max(0, (float) technology_showcasing_record_post_value('production_tonnes')),
    ]);
}

function activity_cluster_demonstration_fields(): array
{
    return [
        'year' => 'Year',
        'crop' => 'Crop',
        'variety' => 'Variety',
        'demonstration_count' => 'No of Demonstration',
        'farmers_involved' => 'No of farmers involved',
        'area_covered' => 'Area Covered (HA)',
        'production_tonnes' => 'Production (In tonnes)',
    ];
}

function cluster_demonstration_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cluster_demonstration_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        crop VARCHAR(180) NULL,
        variety VARCHAR(180) NULL,
        demonstration_count INT DEFAULT 0,
        farmers_involved INT DEFAULT 0,
        area_covered VARCHAR(120) NULL,
        production_tonnes DECIMAL(12,3) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    try {
        $pdo->exec('ALTER TABLE cluster_demonstration_records ADD COLUMN demonstration_count INT DEFAULT 0 AFTER variety');
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function cluster_demonstration_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    cluster_demonstration_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM cluster_demonstration_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_cluster_demonstration_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_cluster_demonstration_fields()), '');
    $record = cluster_demonstration_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record ? array_merge($empty, array_intersect_key($record, $empty)) : $empty;
}

function cluster_demonstration_record_post_value(string $field): string
{
    return trim((string) ($_POST['cluster_demonstration_record'][$field] ?? ''));
}

function cluster_demonstration_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    cluster_demonstration_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO cluster_demonstration_records
            (activity_id, year, crop, variety, demonstration_count, farmers_involved, area_covered, production_tonnes, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            crop = VALUES(crop),
            variety = VALUES(variety),
            demonstration_count = VALUES(demonstration_count),
            farmers_involved = VALUES(farmers_involved),
            area_covered = VALUES(area_covered),
            production_tonnes = VALUES(production_tonnes),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        cluster_demonstration_record_post_value('year'),
        cluster_demonstration_record_post_value('crop'),
        cluster_demonstration_record_post_value('variety'),
        max(0, (int) cluster_demonstration_record_post_value('demonstration_count')),
        max(0, (int) cluster_demonstration_record_post_value('farmers_involved')),
        cluster_demonstration_record_post_value('area_covered'),
        max(0, (float) cluster_demonstration_record_post_value('production_tonnes')),
    ]);
}

function activity_seed_hub_fields(): array
{
    return [
        'year' => 'Year',
        'crop' => 'Crop',
        'variety' => 'Variety',
        'area_ha' => 'Area (Ha)',
        'production_q' => 'Production (Q)',
        'shortfall_reason' => 'Reasons for shortfall, if any',
    ];
}

function seed_hub_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS seed_hub_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        year VARCHAR(20) NULL,
        crop VARCHAR(180) NULL,
        variety VARCHAR(180) NULL,
        area_ha VARCHAR(120) NULL,
        production_q DECIMAL(12,3) DEFAULT 0,
        shortfall_reason TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    foreach ([
        'activity_id' => 'ALTER TABLE seed_hub_records ADD COLUMN activity_id INT NULL UNIQUE AFTER id',
        'year' => 'ALTER TABLE seed_hub_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE seed_hub_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'variety' => 'ALTER TABLE seed_hub_records ADD COLUMN variety VARCHAR(180) NULL AFTER crop',
        'area_ha' => 'ALTER TABLE seed_hub_records ADD COLUMN area_ha VARCHAR(120) NULL AFTER variety',
        'production_q' => 'ALTER TABLE seed_hub_records ADD COLUMN production_q DECIMAL(12,3) DEFAULT 0 AFTER area_ha',
        'shortfall_reason' => 'ALTER TABLE seed_hub_records ADD COLUMN shortfall_reason TEXT NULL AFTER production_q',
        'is_active' => 'ALTER TABLE seed_hub_records ADD COLUMN is_active TINYINT(1) DEFAULT 1',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function seed_hub_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    seed_hub_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM seed_hub_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_seed_hub_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_seed_hub_fields()), '');
    $record = seed_hub_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record ? array_merge($empty, array_intersect_key($record, $empty)) : $empty;
}

function seed_hub_record_post_value(string $field): string
{
    return trim((string) ($_POST['seed_hub_record'][$field] ?? ''));
}

function seed_hub_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    seed_hub_records_ensure_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO seed_hub_records
            (activity_id, year, crop, variety, area_ha, production_q, shortfall_reason, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            year = VALUES(year),
            crop = VALUES(crop),
            variety = VALUES(variety),
            area_ha = VALUES(area_ha),
            production_q = VALUES(production_q),
            shortfall_reason = VALUES(shortfall_reason),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        seed_hub_record_post_value('year'),
        seed_hub_record_post_value('crop'),
        seed_hub_record_post_value('variety'),
        seed_hub_record_post_value('area_ha'),
        max(0, (float) seed_hub_record_post_value('production_q')),
        seed_hub_record_post_value('shortfall_reason'),
    ]);
}

function planting_material_commodities_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS planting_material_commodities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        register_date DATE NULL,
        commodity_name VARCHAR(220) NOT NULL,
        rate DECIMAL(12,2) DEFAULT 0,
        unit VARCHAR(80) NULL,
        product_photo VARCHAR(255) NULL,
        product_photos TEXT NULL,
        product_note TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    try {
        $pdo->exec('ALTER TABLE planting_material_commodities ADD COLUMN register_date DATE NULL AFTER id');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE planting_material_commodities ADD COLUMN product_photo VARCHAR(255) NULL AFTER unit');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE planting_material_commodities ADD COLUMN product_photos TEXT NULL AFTER product_photo');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE planting_material_commodities ADD COLUMN product_note TEXT NULL AFTER product_photos');
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function planting_material_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    planting_material_commodities_ensure_table($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS planting_material_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        commodity_id INT NULL,
        commodity_name VARCHAR(220) NULL,
        produce_date DATE NULL,
        sold_date DATE NULL,
        source VARCHAR(220) NULL,
        quantity_produced DECIMAL(12,3) DEFAULT 0,
        value_of_commodity DECIMAL(12,2) DEFAULT 0,
        quantity_sold DECIMAL(12,3) DEFAULT 0,
        quantity_distributed DECIMAL(12,3) DEFAULT 0,
        revenue_generated DECIMAL(12,2) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    try {
        $pdo->exec('ALTER TABLE planting_material_records ADD COLUMN value_of_commodity DECIMAL(12,2) DEFAULT 0 AFTER quantity_produced');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE planting_material_records ADD COLUMN produce_date DATE NULL AFTER commodity_name');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec('ALTER TABLE planting_material_records ADD COLUMN sold_date DATE NULL AFTER produce_date');
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function planting_material_commodities(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    planting_material_commodities_ensure_table($pdo);
    $stmt = $pdo->query('SELECT * FROM planting_material_commodities WHERE is_active = 1 ORDER BY commodity_name ASC');
    return $stmt ? $stmt->fetchAll() : [];
}

function planting_material_commodity_find(?PDO $pdo, int $id): ?array
{
    if (!$pdo || $id <= 0) {
        return null;
    }

    planting_material_commodities_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM planting_material_commodities WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function planting_material_commodity_save(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    planting_material_commodities_ensure_table($pdo);
    $name = trim((string) ($_POST['commodity_name'] ?? ''));
    if ($name === '') {
        return;
    }

    $registerDate = trim((string) ($_POST['commodity_date'] ?? ''));
    $editId = max(0, (int) ($_POST['commodity_edit_id'] ?? 0));
    $uploadedPhotos = planting_material_upload_photos('commodity_photos', 3);
    $productNote = trim((string) ($_POST['commodity_note'] ?? ''));
    $rate = max(0, (float) ($_POST['commodity_rate'] ?? 0));
    $unit = trim((string) ($_POST['commodity_unit'] ?? ''));

    if ($editId > 0) {
        $existing = planting_material_commodity_find($pdo, $editId);
        if (!$existing) {
            return;
        }

        $existingPhotos = planting_material_photo_list($existing);
        $photosToSave = $uploadedPhotos ? array_slice($uploadedPhotos, 0, 3) : array_slice($existingPhotos, 0, 3);
        $primaryPhoto = $photosToSave[0] ?? '';
        $stmt = $pdo->prepare('UPDATE planting_material_commodities SET register_date = ?, commodity_name = ?, rate = ?, unit = ?, product_photo = ?, product_photos = ?, product_note = ? WHERE id = ? AND is_active = 1');
        $stmt->execute([
            $registerDate !== '' ? $registerDate : null,
            $name,
            $rate,
            $unit,
            $primaryPhoto !== '' ? $primaryPhoto : null,
            $photosToSave ? json_encode($photosToSave) : null,
            $productNote !== '' ? $productNote : null,
            $editId,
        ]);

        $recordStmt = $pdo->prepare('UPDATE planting_material_records SET commodity_name = ? WHERE commodity_id = ? AND is_active = 1');
        $recordStmt->execute([$name, $editId]);
        return;
    }

    $primaryPhoto = $uploadedPhotos[0] ?? '';
    $stmt = $pdo->prepare('INSERT INTO planting_material_commodities (register_date, commodity_name, rate, unit, product_photo, product_photos, product_note, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->execute([
        $registerDate !== '' ? $registerDate : null,
        $name,
        $rate,
        $unit,
        $primaryPhoto !== '' ? $primaryPhoto : null,
        $uploadedPhotos ? json_encode(array_slice($uploadedPhotos, 0, 3)) : null,
        $productNote !== '' ? $productNote : null,
    ]);
}

function planting_material_photo_list(array $commodity): array
{
    $photos = [];
    $stored = trim((string) ($commodity['product_photos'] ?? ''));
    if ($stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            foreach ($decoded as $photo) {
                $photo = trim((string) $photo);
                if ($photo !== '') {
                    $photos[] = $photo;
                }
            }
        }
    }

    $primary = trim((string) ($commodity['product_photo'] ?? ''));
    if ($primary !== '' && !in_array($primary, $photos, true)) {
        array_unshift($photos, $primary);
    }

    return array_slice(array_values(array_unique($photos)), 0, 3);
}

function planting_material_commodity_delete(?PDO $pdo, int $id): void
{
    if (!$pdo || $id <= 0) {
        return;
    }

    planting_material_commodities_ensure_table($pdo);
    $stmt = $pdo->prepare('UPDATE planting_material_commodities SET is_active = 0 WHERE id = ?');
    $stmt->execute([$id]);
}

function planting_material_upload_photo(string $field): string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return '';
    }

    $tmpName = (string) ($_FILES[$field]['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return '';
    }

    $imageInfo = @getimagesize($tmpName);
    if (!$imageInfo) {
        return '';
    }

    $extension = strtolower(pathinfo((string) ($_FILES[$field]['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowed, true)) {
        $extension = match ((string) ($imageInfo['mime'] ?? '')) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'planting-materials';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'planting-material-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $target)) {
        return '';
    }

    return 'uploads/planting-materials/' . $fileName;
}

function planting_material_upload_photos(string $field, int $limit = 3): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }

    $names = $_FILES[$field]['name'] ?? [];
    if (!is_array($names)) {
        $single = planting_material_upload_photo($field);
        return $single !== '' ? [$single] : [];
    }

    $uploaded = [];
    $count = min(count($names), $limit);
    for ($index = 0; $index < $count; $index++) {
        $_FILES['_planting_material_photo'] = [
            'name' => $_FILES[$field]['name'][$index] ?? '',
            'type' => $_FILES[$field]['type'][$index] ?? '',
            'tmp_name' => $_FILES[$field]['tmp_name'][$index] ?? '',
            'error' => $_FILES[$field]['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$field]['size'][$index] ?? 0,
        ];
        $path = planting_material_upload_photo('_planting_material_photo');
        if ($path !== '') {
            $uploaded[] = $path;
        }
    }
    unset($_FILES['_planting_material_photo']);

    return $uploaded;
}

function activity_planting_material_fields(): array
{
    return [
        'commodity_id' => 'Commodity',
        'commodity_name' => 'Commodity Name',
        'produce_date' => 'Produce Date',
        'sold_date' => 'Sold Date',
        'source' => 'Source',
        'quantity_produced' => 'Quantity Produced',
        'value_of_commodity' => 'Value of Commodity',
        'quantity_sold' => 'Quantity Sold',
        'quantity_distributed' => 'Quantity Distributed',
        'revenue_generated' => 'Revenue Generated (Rs.)',
    ];
}

function planting_material_record_from_database(?PDO $pdo, int $activityId): ?array
{
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    planting_material_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM planting_material_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function activity_planting_material_record_for_form(?PDO $pdo, ?array $editing): array
{
    $empty = array_fill_keys(array_keys(activity_planting_material_fields()), '');
    $record = planting_material_record_from_database($pdo, (int) ($editing['id'] ?? 0));

    return $record ? array_merge($empty, array_intersect_key($record, $empty)) : $empty;
}

function planting_material_record_post_value(string $field): string
{
    if ($field === 'commodity_id') {
        $value = trim((string) ($_POST['planting_material_record'][$field] ?? ''));
        return $value !== '' ? $value : trim((string) ($_POST['planting_material_sold_commodity_id'] ?? ''));
    }

    return trim((string) ($_POST['planting_material_record'][$field] ?? ''));
}

function planting_material_record_save(?PDO $pdo, int $activityId): void
{
    if (!$pdo || $activityId <= 0) {
        return;
    }

    planting_material_records_ensure_table($pdo);
    $commodityId = max(0, (int) planting_material_record_post_value('commodity_id'));
    $commodityName = '';
    $rate = 0.0;
    if ($commodityId > 0) {
        $stmt = $pdo->prepare('SELECT commodity_name, rate FROM planting_material_commodities WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$commodityId]);
        $commodity = $stmt->fetch();
        if ($commodity) {
            $commodityName = (string) ($commodity['commodity_name'] ?? '');
            $rate = (float) ($commodity['rate'] ?? 0);
        }
    }

    $quantitySold = max(0, (float) planting_material_record_post_value('quantity_sold'));
    $quantityDistributed = max(0, (float) planting_material_record_post_value('quantity_distributed'));
    $quantityProduced = max(0, (float) planting_material_record_post_value('quantity_produced'));
    $valueOfCommodity = round($quantityProduced * $rate, 2);
    $revenue = $quantityDistributed > 0 ? 0 : round($quantitySold * $rate, 2);
    $stmt = $pdo->prepare(
        'INSERT INTO planting_material_records
            (activity_id, commodity_id, commodity_name, produce_date, sold_date, source, quantity_produced, value_of_commodity, quantity_sold, quantity_distributed, revenue_generated, is_active)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            commodity_id = VALUES(commodity_id),
            commodity_name = VALUES(commodity_name),
            produce_date = VALUES(produce_date),
            sold_date = VALUES(sold_date),
            source = VALUES(source),
            quantity_produced = VALUES(quantity_produced),
            value_of_commodity = VALUES(value_of_commodity),
            quantity_sold = VALUES(quantity_sold),
            quantity_distributed = VALUES(quantity_distributed),
            revenue_generated = VALUES(revenue_generated),
            is_active = 1'
    );
    $stmt->execute([
        $activityId,
        $commodityId ?: null,
        $commodityName,
        planting_material_record_post_value('produce_date') ?: null,
        planting_material_record_post_value('sold_date') ?: null,
        planting_material_record_post_value('source'),
        $quantityProduced,
        $valueOfCommodity,
        $quantitySold,
        $quantityDistributed,
        $revenue,
    ]);
}

function official_activity_record_sections(): array
{
    return [
        'summary_totals' => [
            'title' => 'OFT / FLD Discipline Totals',
            'description' => 'Programme and farmer totals by discipline for Format-1.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'activity' => ['label' => 'Activity', 'type' => 'select', 'options' => ['FLD' => 'FLD', 'OFT' => 'OFT']],
                'crop_production_programmes' => ['label' => 'Crop Production - No. of Progs.', 'type' => 'number'],
                'crop_production_farmers' => ['label' => 'Crop Production - No. of farmers', 'type' => 'number'],
                'horticulture_programmes' => ['label' => 'Horticulture - No. of Progs.', 'type' => 'number'],
                'horticulture_farmers' => ['label' => 'Horticulture - No. of farmers', 'type' => 'number'],
                'soil_science_programmes' => ['label' => 'Soil Science - No. of Progs.', 'type' => 'number'],
                'soil_science_farmers' => ['label' => 'Soil Science - No. of farmers', 'type' => 'number'],
                'plant_protection_programmes' => ['label' => 'Plant Protection - No. of Progs.', 'type' => 'number'],
                'plant_protection_farmers' => ['label' => 'Plant Protection - No. of farmers', 'type' => 'number'],
                'animal_science_programmes' => ['label' => 'Animal Science - No. of Progs.', 'type' => 'number'],
                'animal_science_farmers' => ['label' => 'Animal Science - No. of farmers', 'type' => 'number'],
                'fishery_science_programmes' => ['label' => 'Fishery Science - No. of Progs.', 'type' => 'number'],
                'fishery_science_farmers' => ['label' => 'Fishery Science - No. of farmers', 'type' => 'number'],
                'community_science_programmes' => ['label' => 'Community Science - No. of Progs.', 'type' => 'number'],
                'community_science_farmers' => ['label' => 'Community Science - No. of farmers', 'type' => 'number'],
            ],
        ],
        'fld_details' => [
            'title' => 'Details of FLD Programmes',
            'description' => 'One row for each frontline demonstration programme.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'discipline' => ['label' => 'Discipline', 'type' => 'text'],
                'thematic_area' => ['label' => 'Thematic Area', 'type' => 'text'],
                'technology_demonstrated' => ['label' => 'Technology Demonstrated', 'type' => 'textarea'],
                'season_year' => ['label' => 'Season / Year', 'type' => 'text'],
                'area_covered' => ['label' => 'Area Covered (Ha) / Nos.', 'type' => 'text'],
                'demo_count' => ['label' => 'No. of demos.', 'type' => 'number'],
                'farmer_count' => ['label' => 'No. of Farmers', 'type' => 'number'],
            ],
        ],
        'oft_details' => [
            'title' => 'Details of OFT Programmes',
            'description' => 'One row for each on farm testing programme.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'discipline' => ['label' => 'Discipline', 'type' => 'text'],
                'thematic_area' => ['label' => 'Thematic Area', 'type' => 'text'],
                'oft_title' => ['label' => 'Title of OFT', 'type' => 'textarea'],
                'season' => ['label' => 'Season', 'type' => 'text'],
            ],
        ],
        'training_totals' => [
            'title' => 'Total Trainings Conducted',
            'description' => 'Training totals including sponsored and vocational programmes.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'discipline' => ['label' => 'Discipline', 'type' => 'text'],
                'ffw_trainings' => ['label' => 'F-FW - No. of trainings', 'type' => 'number'],
                'ffw_participants' => ['label' => 'F-FW - No. of participants', 'type' => 'number'],
                'ry_trainings' => ['label' => 'RY - No. of trainings', 'type' => 'number'],
                'ry_participants' => ['label' => 'RY - No. of participants', 'type' => 'number'],
                'ef_trainings' => ['label' => 'EF - No. of trainings', 'type' => 'number'],
                'ef_participants' => ['label' => 'EF - No. of participants', 'type' => 'number'],
            ],
        ],
        'vocational_trainings' => [
            'title' => 'Vocational Trainings',
            'description' => 'One row for each vocational training programme.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'disciplines' => ['label' => 'Discipline(s)', 'type' => 'text'],
                'training_area' => ['label' => 'Training Area', 'type' => 'text'],
                'duration_days' => ['label' => 'Duration (days)', 'type' => 'number'],
                'participant_count' => ['label' => 'No. of Participants', 'type' => 'number'],
            ],
        ],
        'sponsored_trainings' => [
            'title' => 'Sponsored Trainings',
            'description' => 'One row for each sponsored training programme.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'sponsoring_authority' => ['label' => 'Sponsoring Authority / Organisation', 'type' => 'text'],
                'disciplines' => ['label' => 'Discipline(s)', 'type' => 'text'],
                'training_area' => ['label' => 'Training Area', 'type' => 'text'],
                'duration_days' => ['label' => 'Duration (days)', 'type' => 'number'],
                'participant_count' => ['label' => 'No. of Participants', 'type' => 'number'],
            ],
        ],
        'technology_showcasing' => [
            'title' => 'Technology Showcasing / Seed Production',
            'description' => 'Participatory seed production and technology showcasing entries.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'variety' => ['label' => 'Variety', 'type' => 'text'],
                'farmers_involved' => ['label' => 'No. of farmers involved', 'type' => 'number'],
                'area_covered' => ['label' => 'Area Covered (HA)', 'type' => 'text'],
                'production_tonnes' => ['label' => 'Production (tonnes)', 'type' => 'text'],
            ],
        ],
        'cluster_oilseed' => [
            'title' => 'Cluster Demonstration on Oilseed',
            'description' => 'Oilseed cluster demonstration achievement entries.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'variety' => ['label' => 'Variety', 'type' => 'text'],
                'demonstration_count' => ['label' => 'No. of Demonstration', 'type' => 'number'],
                'area_covered' => ['label' => 'Area Covered (ha)', 'type' => 'text'],
                'farmer_count' => ['label' => 'Number of farmers', 'type' => 'number'],
                'production_t_ha' => ['label' => 'Production (t/ha)', 'type' => 'text'],
            ],
        ],
        'cluster_pulses' => [
            'title' => 'Cluster Demonstration on Pulses',
            'description' => 'Pulses cluster demonstration achievement entries.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'variety' => ['label' => 'Variety', 'type' => 'text'],
                'demonstration_count' => ['label' => 'No. of Demonstration', 'type' => 'number'],
                'area_covered' => ['label' => 'Area Covered (ha)', 'type' => 'text'],
                'farmer_count' => ['label' => 'Number of farmers', 'type' => 'number'],
                'production_t_ha' => ['label' => 'Production (t/ha)', 'type' => 'text'],
            ],
        ],
        'seed_hub' => [
            'title' => 'Achievements under Seed Hub',
            'description' => 'Seed hub target, achievement, and shortfall details.',
            'fields' => [
                'year' => ['label' => 'Year', 'type' => 'text'],
                'kvk' => ['label' => 'KVK', 'type' => 'text'],
                'crop' => ['label' => 'Crop', 'type' => 'text'],
                'variety' => ['label' => 'Variety', 'type' => 'text'],
                'area_ha' => ['label' => 'Area (Ha)', 'type' => 'text'],
                'target_q' => ['label' => 'Production Target (Q)', 'type' => 'text'],
                'achievement_q' => ['label' => 'Production Achievement (Q)', 'type' => 'text'],
                'shortfall_reason' => ['label' => 'Reasons for shortfall, if any', 'type' => 'textarea'],
            ],
        ],
    ];
}

function official_activity_records_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS official_activity_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_type VARCHAR(80) NOT NULL,
        financial_year VARCHAR(20) NOT NULL,
        data_json LONGTEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");
}

function official_activity_record_type(string $type): string
{
    $sections = official_activity_record_sections();
    return array_key_exists($type, $sections) ? $type : array_key_first($sections);
}

function official_activity_record_data(array $row): array
{
    $decoded = json_decode((string) ($row['data_json'] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function official_activity_records(?PDO $pdo, string $recordType): array
{
    if (!$pdo) {
        return [];
    }

    official_activity_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM official_activity_records WHERE is_active = 1 AND record_type = ? ORDER BY financial_year DESC, created_at DESC LIMIT 200');
    $stmt->execute([$recordType]);

    return $stmt->fetchAll() ?: [];
}

function official_activity_record_counts(?PDO $pdo): array
{
    $counts = array_fill_keys(array_keys(official_activity_record_sections()), 0);
    if (!$pdo) {
        return $counts;
    }

    official_activity_records_ensure_table($pdo);
    $stmt = $pdo->query('SELECT record_type, COUNT(*) AS total FROM official_activity_records WHERE is_active = 1 GROUP BY record_type');
    foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
        $recordType = (string) ($row['record_type'] ?? '');
        if (array_key_exists($recordType, $counts)) {
            $counts[$recordType] = (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

function official_activity_record_find(?PDO $pdo, int $id): ?array
{
    if (!$pdo || $id <= 0) {
        return null;
    }

    official_activity_records_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM official_activity_records WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function official_activity_record_save(?PDO $pdo, string $recordType, int $editId = 0): void
{
    if (!$pdo) {
        return;
    }

    $sections = official_activity_record_sections();
    $recordType = official_activity_record_type($recordType);
    $fields = $sections[$recordType]['fields'];
    $data = [];
    foreach ($fields as $field => $config) {
        $value = trim((string) ($_POST['official_record'][$field] ?? ''));
        $data[$field] = ($config['type'] ?? '') === 'number' ? (string) max(0, (int) $value) : $value;
    }

    $financialYear = financial_year_normalize((string) ($_POST['official_financial_year'] ?? ''));
    if (($data['year'] ?? '') !== '') {
        $financialYear = financial_year_normalize((string) $data['year']);
    }

    official_activity_records_ensure_table($pdo);
    if ($editId > 0) {
        $stmt = $pdo->prepare('UPDATE official_activity_records SET record_type = ?, financial_year = ?, data_json = ? WHERE id = ?');
        $stmt->execute([$recordType, $financialYear, json_encode($data, JSON_UNESCAPED_SLASHES), $editId]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO official_activity_records (record_type, financial_year, data_json) VALUES (?, ?, ?)');
    $stmt->execute([$recordType, $financialYear, json_encode($data, JSON_UNESCAPED_SLASHES)]);
}

function activity_count_tracking_ensure(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    activity_totals_ensure_table($pdo);
    try {
        $pdo->exec('ALTER TABLE activities ADD COLUMN counted_in_totals TINYINT(1) DEFAULT 0');
    } catch (Throwable $exception) {
        // Column already exists.
    }
    try {
        $pdo->exec("ALTER TABLE activities ADD COLUMN counted_financial_year VARCHAR(20) NULL");
    } catch (Throwable $exception) {
        // Column already exists.
    }
}

function activity_counted_in_totals(array $activity): bool
{
    return (int) ($activity['counted_in_totals'] ?? 0) === 1;
}

function staff_expertise_options(): array
{
    $options = [];
    foreach (records('staff', 'staff', 'is_active = 1', 'sort_order ASC, name ASC', 200) as $person) {
        $expertise = trim((string) ($person['expertise'] ?? ''));
        if ($expertise !== '' && $expertise !== '---') {
            $options[$expertise] = $expertise;
        }
    }

    return array_values($options);
}

function staff_head_duration_parts(string $duration): array
{
    $duration = trim($duration);
    if ($duration === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+(?:to|till|until)\s+|\s+-\s+/i', $duration, 2);
    return [
        trim((string) ($parts[0] ?? '')),
        trim((string) ($parts[1] ?? '')),
    ];
}

function staff_head_duration_value(string $from, string $to): string
{
    $from = trim($from);
    $to = trim($to);

    if ($from !== '' && $to !== '') {
        return $from . ' to ' . $to;
    }

    return $from !== '' ? $from : $to;
}

function notices_ensure_columns(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $columns = [
        'image_path' => 'ALTER TABLE notices ADD COLUMN image_path VARCHAR(255) NULL AFTER details',
        'image_path_2' => 'ALTER TABLE notices ADD COLUMN image_path_2 VARCHAR(255) NULL AFTER image_path',
        'image_path_3' => 'ALTER TABLE notices ADD COLUMN image_path_3 VARCHAR(255) NULL AFTER image_path_2',
        'image_path_4' => 'ALTER TABLE notices ADD COLUMN image_path_4 VARCHAR(255) NULL AFTER image_path_3',
        'image_path_5' => 'ALTER TABLE notices ADD COLUMN image_path_5 VARCHAR(255) NULL AFTER image_path_4',
    ];

    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function notice_photo_fields(): array
{
    return ['image_path', 'image_path_2', 'image_path_3', 'image_path_4', 'image_path_5'];
}

function staff_ensure_columns(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $columns = [
        'group_type' => "ALTER TABLE staff ADD COLUMN group_type VARCHAR(40) DEFAULT 'kvk' AFTER name",
        'qualification' => 'ALTER TABLE staff ADD COLUMN qualification VARCHAR(160) NULL AFTER designation',
        'expertise' => 'ALTER TABLE staff ADD COLUMN expertise VARCHAR(160) NULL AFTER qualification',
        'date_of_birth' => 'ALTER TABLE staff ADD COLUMN date_of_birth DATE NULL AFTER email',
        'date_of_joining_aau' => 'ALTER TABLE staff ADD COLUMN date_of_joining_aau DATE NULL AFTER date_of_birth',
        'date_of_joining_present_post' => 'ALTER TABLE staff ADD COLUMN date_of_joining_present_post DATE NULL AFTER date_of_joining_aau',
        'photo_path' => 'ALTER TABLE staff ADD COLUMN photo_path VARCHAR(255) NULL AFTER date_of_joining_present_post',
        'profile_link' => 'ALTER TABLE staff ADD COLUMN profile_link VARCHAR(255) NULL AFTER photo_path',
        'sort_order' => 'ALTER TABLE staff ADD COLUMN sort_order INT DEFAULT 100 AFTER profile_link',
        'inactive_reason' => 'ALTER TABLE staff ADD COLUMN inactive_reason VARCHAR(40) NULL AFTER is_active',
        'inactive_order_no' => 'ALTER TABLE staff ADD COLUMN inactive_order_no VARCHAR(120) NULL AFTER inactive_reason',
    ];

    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function staff_find_any(?PDO $pdo, int $id): ?array
{
    if (!$pdo || $id <= 0) {
        return null;
    }

    try {
        staff_ensure_columns($pdo);
        $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        return null;
    }

    return $row ?: null;
}

if ($key === 'notices' && $pdo) {
    notices_ensure_columns($pdo);
    $editing = $editId ? user_desk_find_owned($pdo, (string) $module['table'], $editId, $userDeskUserId) : null;
} elseif ($key === 'activities' && $pdo) {
    activity_count_tracking_ensure($pdo);
    official_activity_records_ensure_table($pdo);
    oft_records_ensure_table($pdo);
    fld_records_ensure_table($pdo);
    awareness_records_ensure_table($pdo);
    exhibition_records_ensure_table($pdo);
    special_event_records_ensure_tables($pdo);
    beneficiary_event_records_ensure_tables($pdo);
    important_day_records_ensure_table($pdo);
    shg_gosthi_records_ensure_tables($pdo);
    lecture_records_ensure_table($pdo);
    radio_talk_records_ensure_table($pdo);
    tv_talk_records_ensure_table($pdo);
    technology_showcasing_records_ensure_table($pdo);
    cluster_demonstration_records_ensure_table($pdo);
    seed_hub_records_ensure_table($pdo);
    planting_material_records_ensure_table($pdo);
    $editing = $editId ? user_desk_find_owned($pdo, (string) $module['table'], $editId, $userDeskUserId) : null;
} elseif ($key === 'activity_totals' && $pdo) {
    activity_totals_ensure_table($pdo);
    $editing = $editId ? user_desk_find_owned($pdo, (string) $module['table'], $editId, $userDeskUserId) : null;
} elseif ($key === 'staff' && $pdo) {
    staff_ensure_columns($pdo);
    $editing = $editId ? staff_find_any($pdo, $editId) : null;
}

if ($editId > 0 && !$editing) {
    header('Location: user-manage.php?module=' . urlencode($key) . '&access=not_owner');
    exit;
}

if (isset($_GET['official_delete']) && $key === 'activities' && $pdo && csrf_valid($_GET['token'] ?? null)) {
    $deleteId = (int) $_GET['official_delete'];
    $recordType = official_activity_record_type((string) ($_GET['official_type'] ?? ''));
    $officialRecordSections = official_activity_record_sections();
    $officialTitle = (string) (($officialRecordSections[$recordType]['title'] ?? '') ?: 'Official Format-1 record');
    $stmt = $pdo->prepare('UPDATE official_activity_records SET is_active = 0 WHERE id = ?');
    $stmt->execute([$deleteId]);
    notesheet_auto_log($pdo, 'Activities', 'Deleted ' . $officialTitle, 'Official activity record ID #' . $deleteId . ' was deleted from the admin desk.');
    header('Location: user-manage.php?module=activities&desk=records&official_type=' . urlencode($recordType));
    exit;
}

if (isset($_GET['delete']) && $pdo && csrf_valid($_GET['token'] ?? null)) {
    $deleteId = (int) $_GET['delete'];
    $deleteRow = $deleteId ? db_find($module['table'], $deleteId) : null;
    if ($key === 'activities' && $deleteRow && activity_counted_in_totals($deleteRow)) {
        $deleteKey = activity_total_key_from_slug((string) ($deleteRow['slug'] ?? ''));
        $deleteFinancialYear = financial_year_normalize((string) ($deleteRow['counted_financial_year'] ?? ''));
        $deleteBeneficiaries = 0;
        if ($deleteKey === 'training') {
            $deleteTraining = training_record_from_database($pdo, $deleteId);
            $deleteBeneficiaries = (int) ($deleteTraining['total_participants'] ?? 0);
        } elseif ($deleteKey === 'exhibition') {
            $deleteBeneficiaries = exhibition_record_total_visitors(activity_exhibition_record_for_form($pdo, $deleteRow));
        } elseif (activity_is_special_event_record_slug($deleteKey)) {
            $deleteBeneficiaries = special_event_record_total_visitors(activity_special_event_record_for_form($pdo, $deleteRow, $deleteKey));
        } elseif (activity_is_beneficiary_event_record_slug($deleteKey)) {
            $deleteBeneficiaries = beneficiary_event_record_total_beneficiaries(activity_beneficiary_event_record_for_form($pdo, $deleteRow, $deleteKey));
        } elseif ($deleteKey === 'celebration-of-important-days') {
            $deleteBeneficiaries = important_day_record_total_beneficiaries(activity_important_day_record_for_form($pdo, $deleteRow));
        } elseif (activity_is_shg_gosthi_record_slug($deleteKey)) {
            $deleteRecord = shg_gosthi_record_for_form($pdo, $deleteRow, $deleteKey);
            $deleteBeneficiaries = (int) ($deleteRecord['total_participants'] ?? 0);
        } elseif (activity_is_visit_record_slug($deleteKey)) {
            $deleteBeneficiaries = exposure_record_total_beneficiaries(activity_exposure_record_for_form($pdo, $deleteRow, $deleteKey));
        }
        activity_total_adjust($pdo, $deleteKey, -1, -$deleteBeneficiaries, $deleteFinancialYear);
    }
    if ($key === 'activities' && $deleteRow && activity_is_visit_record_slug((string) ($deleteRow['slug'] ?? ''))) {
        $deleteVisitKey = activity_canonical_slug((string) ($deleteRow['slug'] ?? ''));
        $deleteVisitTable = activity_visit_record_table($deleteVisitKey);
        exposure_visit_records_ensure_table($pdo, $deleteVisitKey);
        $stmt = $pdo->prepare("UPDATE {$deleteVisitTable} SET is_active = 0 WHERE activity_id = ?");
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'exhibition') {
        exhibition_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE exhibition_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_is_special_event_record_slug((string) ($deleteRow['slug'] ?? ''))) {
        $deleteEventKey = activity_canonical_slug((string) ($deleteRow['slug'] ?? ''));
        $deleteEventConfig = activity_special_event_record_config($deleteEventKey);
        special_event_records_ensure_table($pdo, $deleteEventKey);
        $stmt = $pdo->prepare("UPDATE {$deleteEventConfig['table']} SET is_active = 0 WHERE activity_id = ?");
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_is_beneficiary_event_record_slug((string) ($deleteRow['slug'] ?? ''))) {
        $deleteBeneficiaryKey = activity_canonical_slug((string) ($deleteRow['slug'] ?? ''));
        $deleteBeneficiaryConfig = activity_beneficiary_event_record_config($deleteBeneficiaryKey);
        beneficiary_event_records_ensure_table($pdo, $deleteBeneficiaryKey);
        $stmt = $pdo->prepare("UPDATE {$deleteBeneficiaryConfig['table']} SET is_active = 0 WHERE activity_id = ?");
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'celebration-of-important-days') {
        important_day_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE important_day_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_is_shg_gosthi_record_slug((string) ($deleteRow['slug'] ?? ''))) {
        $deleteShgKey = activity_canonical_slug((string) ($deleteRow['slug'] ?? ''));
        $deleteShgConfig = shg_gosthi_record_config($deleteShgKey);
        shg_gosthi_records_ensure_table($pdo, $deleteShgKey);
        $stmt = $pdo->prepare("UPDATE {$deleteShgConfig['table']} SET is_active = 0 WHERE activity_id = ?");
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'lectures-delivered') {
        lecture_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE lecture_delivered_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'radio-talks') {
        radio_talk_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE radio_talk_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'tv-talks') {
        tv_talk_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE tv_talk_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    if ($key === 'activities' && $deleteRow && activity_canonical_slug((string) ($deleteRow['slug'] ?? '')) === 'planting-materials') {
        planting_material_records_ensure_table($pdo);
        $stmt = $pdo->prepare('UPDATE planting_material_records SET is_active = 0 WHERE activity_id = ?');
        $stmt->execute([$deleteId]);
    }
    $stmt = $pdo->prepare("UPDATE {$module['table']} SET is_active = 0 WHERE id = ?");
    $stmt->execute([$deleteId]);
    $deletedTitle = (string) (($deleteRow['title'] ?? '') ?: ($deleteRow['name'] ?? '') ?: ($deleteRow['activity_label'] ?? '') ?: ('Record ID #' . $deleteId));
    notesheet_auto_log(
        $pdo,
        (string) ($module['title'] ?? $key),
        'Deleted ' . (string) ($module['title'] ?? 'record'),
        'Deleted "' . $deletedTitle . '" from ' . (string) ($module['title'] ?? $key) . '. Record ID #' . $deleteId . '.'
    );
    if ($key === 'activities') {
        $returnDesk = in_array((string) ($_GET['desk'] ?? ''), ['mandated', 'other', 'records'], true) ? (string) $_GET['desk'] : 'mandated';
        $returnType = activity_canonical_slug((string) ($_GET['activity_type'] ?? 'oft'));
        if (!array_key_exists($returnType, activity_mandated_options()) && !array_key_exists($returnType, activity_public_outreach_topics())) {
            $returnType = 'oft';
        }
        header('Location: user-manage.php?module=activities&desk=' . urlencode($returnDesk) . '&activity_type=' . urlencode($returnType));
    } else {
        header('Location: user-manage.php?module=' . urlencode($key));
    }
    exit;
}

if ($key === 'activities' && isset($_GET['commodity_delete']) && $pdo && csrf_valid($_GET['token'] ?? null)) {
    planting_material_commodity_delete($pdo, (int) $_GET['commodity_delete']);
    header('Location: user-manage.php?module=activities&desk=mandated&activity_type=planting-materials&commodity=deleted');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo && csrf_valid($_POST['csrf_token'] ?? null)) {
    if ($key === 'activities' && isset($_POST['planting_material_commodity_submit'])) {
        header('Location: user-manage.php?module=activities&desk=mandated&activity_type=planting-materials&access=admin_master');
        exit;
    }

    if ($key === 'activities' && isset($_POST['official_record_submit'])) {
        header('Location: user-manage.php?module=activities&access=admin_master');
        exit;
    }

    $postedActivityDesk = (string) ($_POST['activity_desk'] ?? '');
    $postedMandatedType = activity_canonical_slug((string) ($_POST['activity_mandated_type'] ?? ''));
    $isTrainingPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'training';
    $isOftPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'oft';
    $isFldPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'fld';
    $isAwarenessPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'awareness-programme-camps';
    $isExhibitionPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'exhibition';
    $isSpecialEventPost = $key === 'activities' && $postedActivityDesk === 'mandated' && activity_is_special_event_record_slug($postedMandatedType);
    $isBeneficiaryEventPost = $key === 'activities' && $postedActivityDesk === 'mandated' && activity_is_beneficiary_event_record_slug($postedMandatedType);
    $isImportantDayPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'celebration-of-important-days';
    $isShgGosthiPost = $key === 'activities' && $postedActivityDesk === 'mandated' && activity_is_shg_gosthi_record_slug($postedMandatedType);
    $isLecturePost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'lectures-delivered';
    $isRadioTalkPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'radio-talks';
    $isTvTalkPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'tv-talks';
    $isTechnologyShowcasingPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'technology-showcasing';
    $isClusterDemonstrationPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'cluster-demonstration';
    $isSeedHubPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'seed-hub';
    $isPlantingMaterialPost = $key === 'activities' && $postedActivityDesk === 'mandated' && $postedMandatedType === 'planting-materials';
    $isExposurePost = $key === 'activities' && $postedActivityDesk === 'mandated' && activity_is_visit_record_slug($postedMandatedType);
    $oldActivityKey = $key === 'activities' && $editing ? activity_total_key_from_slug((string) ($editing['slug'] ?? '')) : '';
    $oldFinancialYear = $key === 'activities' && $editing ? financial_year_normalize((string) ($editing['counted_financial_year'] ?? '')) : '';
    $oldTrainingParticipants = 0;
    $oldExposureBeneficiaries = 0;
    $oldExhibitionVisitors = 0;
    $oldSpecialEventVisitors = 0;
    $oldBeneficiaryEventBeneficiaries = 0;
    $oldImportantDayBeneficiaries = 0;
    $oldShgGosthiParticipants = 0;
    if ($isTrainingPost && $editing && activity_counted_in_totals($editing)) {
        $oldTrainingRecord = training_record_from_database($pdo, $editId);
        $oldTrainingParticipants = (int) ($oldTrainingRecord['total_participants'] ?? 0);
    }
    if ($isExposurePost && $editing && activity_counted_in_totals($editing)) {
        $oldExposureBeneficiaries = exposure_record_total_beneficiaries(activity_exposure_record_for_form($pdo, $editing, $oldActivityKey));
    }
    if ($isExhibitionPost && $editing && activity_counted_in_totals($editing)) {
        $oldExhibitionVisitors = exhibition_record_total_visitors(activity_exhibition_record_for_form($pdo, $editing));
    }
    if ($isSpecialEventPost && $editing && activity_counted_in_totals($editing)) {
        $oldSpecialEventVisitors = special_event_record_total_visitors(activity_special_event_record_for_form($pdo, $editing, $oldActivityKey));
    }
    if ($isBeneficiaryEventPost && $editing && activity_counted_in_totals($editing)) {
        $oldBeneficiaryEventBeneficiaries = beneficiary_event_record_total_beneficiaries(activity_beneficiary_event_record_for_form($pdo, $editing, $oldActivityKey));
    }
    if ($isImportantDayPost && $editing && activity_counted_in_totals($editing)) {
        $oldImportantDayBeneficiaries = important_day_record_total_beneficiaries(activity_important_day_record_for_form($pdo, $editing));
    }
    if ($isShgGosthiPost && $editing && activity_counted_in_totals($editing)) {
        $oldShgGosthiRecord = shg_gosthi_record_for_form($pdo, $editing, $oldActivityKey);
        $oldShgGosthiParticipants = (int) ($oldShgGosthiRecord['total_participants'] ?? 0);
    }

    if ($key === 'activities'
        && trim((string) ($_POST['title'] ?? '')) === ''
        && trim((string) ($_POST['training_record']['training_name'] ?? '')) !== '') {
        $_POST['title'] = trim((string) $_POST['training_record']['training_name']);
    }
    if ($key === 'activities' && $isOftPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = oft_record_post_value('oft_title');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(oft_record_post_value('crop') . ' - ' . oft_record_post_value('thematic_area'), " \t\n\r\0\x0B-");
        }
    }
    if ($key === 'activities' && $isFldPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = fld_record_post_value('technology_tested');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(fld_record_post_value('crop') . ' - ' . fld_record_post_value('thematic_area'), " \t\n\r\0\x0B-");
        }
    }
    if ($key === 'activities' && $isAwarenessPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = awareness_record_post_value('programme_title');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = awareness_record_post_value('thematic_area');
        }
    }
    if ($key === 'activities' && $isExhibitionPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = exhibition_record_post_value('exhibition_title');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = exhibition_record_post_value('venue');
        }
    }
    if ($key === 'activities' && $isSpecialEventPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = special_event_record_post_value('event_title');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = special_event_record_post_value('venue');
        }
    }
    if ($key === 'activities' && $isBeneficiaryEventPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = beneficiary_event_record_post_value('particulars');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(beneficiary_event_record_post_value('topic') . ' - ' . beneficiary_event_record_post_value('location'), " \t\n\r\0\x0B-");
        }
    }
    if ($key === 'activities' && $isImportantDayPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = important_day_record_post_value('topic');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = important_day_record_post_value('venue');
        }
    }
    if ($key === 'activities' && $isShgGosthiPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = shg_gosthi_record_post_value('record_name');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = shg_gosthi_record_post_value('location');
        }
    }
    if ($key === 'activities' && $isLecturePost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = lecture_record_post_value('programme_name');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(lecture_record_post_value('topic') . ' - ' . lecture_record_post_value('venue'), " \t\n\r\0\x0B-");
        }
    }
    if ($key === 'activities' && $isRadioTalkPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = radio_talk_record_post_value('topic');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = radio_talk_record_post_value('radio_station');
        }
    }
    if ($key === 'activities' && $isTvTalkPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = tv_talk_record_post_value('topic');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = tv_talk_record_post_value('tv_station');
        }
    }
    if ($key === 'activities' && $isTechnologyShowcasingPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = trim(technology_showcasing_record_post_value('crop') . ' ' . technology_showcasing_record_post_value('variety'));
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(technology_showcasing_record_post_value('area_covered') . ' area covered, ' . technology_showcasing_record_post_value('production_tonnes') . ' tonnes production', " \t\n\r\0\x0B,");
        }
    }
    if ($key === 'activities' && $isExposurePost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = exposure_record_post_value('visit_name');
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = exposure_record_post_value('location');
        }
    }
    if ($key === 'activities' && $isClusterDemonstrationPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = trim(cluster_demonstration_record_post_value('crop') . ' ' . cluster_demonstration_record_post_value('variety'));
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(cluster_demonstration_record_post_value('area_covered') . ' area covered, ' . cluster_demonstration_record_post_value('production_tonnes') . ' tonnes production', " \t\n\r\0\x0B,");
        }
    }
    if ($key === 'activities' && $isSeedHubPost) {
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = trim(seed_hub_record_post_value('crop') . ' ' . seed_hub_record_post_value('variety'));
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(seed_hub_record_post_value('area_ha') . ' ha area, ' . seed_hub_record_post_value('production_q') . ' Q production', " \t\n\r\0\x0B,");
        }
    }
    if ($key === 'activities' && $isPlantingMaterialPost) {
        $postedCommodityId = max(0, (int) planting_material_record_post_value('commodity_id'));
        $postedCommodityName = '';
        if ($postedCommodityId > 0) {
            $stmt = $pdo->prepare('SELECT commodity_name FROM planting_material_commodities WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$postedCommodityId]);
            $postedCommodity = $stmt->fetch();
            $postedCommodityName = $postedCommodity ? (string) ($postedCommodity['commodity_name'] ?? '') : '';
        }
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            $_POST['title'] = $postedCommodityName ?: 'Planting Material Record';
        }
        if (trim((string) ($_POST['summary'] ?? '')) === '') {
            $_POST['summary'] = trim(planting_material_record_post_value('source') . ', sold ' . planting_material_record_post_value('quantity_sold'), " \t\n\r\0\x0B,");
        }
    }

    $fields = $module['fields'];
    $fileFields = $module['file_fields'] ?? [];
    $dateFields = $module['date_fields'] ?? [];
    $values = [];
    $trainingPhotoPaths = [];
    $oftPhotoPaths = [];
    $fldPhotoPaths = [];

    foreach ($fields as $field) {
        $current = (string) ($editing[$field] ?? '');
        if (in_array($field, $fileFields, true)) {
            if ($key === 'notices' && in_array($field, notice_photo_fields(), true)) {
                $photoNumber = $field === 'image_path' ? '1' : substr($field, -1);
                $programmeName = trim((string) ($_POST['title'] ?? 'programme'));
                $values[$field] = upload_value_to_folder_named($field, $current, 'Notice/event', $programmeName . '-photo-' . $photoNumber);
            } else {
                $values[$field] = ($isTrainingPost || $isOftPost || $isFldPost || $isAwarenessPost || $isExhibitionPost || $isSpecialEventPost || $isBeneficiaryEventPost || $isImportantDayPost || $isShgGosthiPost || $isLecturePost || $isRadioTalkPost || $isTvTalkPost || $isTechnologyShowcasingPost || $isClusterDemonstrationPost || $isSeedHubPost || $isPlantingMaterialPost || $isExposurePost) && $field === 'image_path' ? $current : upload_value($field, $current);
            }
        } elseif (in_array($field, $dateFields, true)) {
            $dateValue = trim((string) ($_POST[$field] ?? ''));
            $values[$field] = $dateValue === '' ? null : $dateValue;
        } elseif ($key === 'staff_heads' && $field === 'duration') {
            $values[$field] = staff_head_duration_value(
                (string) ($_POST['duration_from'] ?? ''),
                (string) ($_POST['duration_to'] ?? '')
            );
        } elseif ($key === 'activities' && $field === 'slug') {
            $activityDeskValue = (string) ($_POST['activity_desk'] ?? 'mandated');
            $values[$field] = $activityDeskValue === 'mandated'
                ? activity_canonical_slug((string) ($_POST['activity_mandated_type'] ?? 'oft'))
                : trim((string) ($_POST['slug'] ?? ''));
        } elseif ($key === 'activities' && $field === 'details') {
            $values[$field] = ($isTrainingPost || $isOftPost || $isFldPost || $isAwarenessPost || $isExhibitionPost || $isSpecialEventPost || $isBeneficiaryEventPost || $isImportantDayPost || $isShgGosthiPost || $isLecturePost || $isRadioTalkPost || $isTvTalkPost || $isTechnologyShowcasingPost || $isClusterDemonstrationPost || $isSeedHubPost || $isPlantingMaterialPost || $isExposurePost) ? '' : trim((string) ($_POST[$field] ?? ''));
        } elseif ($key === 'staff' && $field === 'is_active') {
            $values[$field] = (int) ($_POST[$field] ?? 1) === 1 ? 1 : 0;
        } elseif ($key === 'staff' && $field === 'inactive_reason') {
            $reason = trim((string) ($_POST[$field] ?? ''));
            $values[$field] = in_array($reason, ['Transfer', 'Resign'], true) ? $reason : '';
        } elseif ($key === 'staff' && $field === 'inactive_order_no') {
            $values[$field] = trim((string) ($_POST[$field] ?? ''));
        } elseif ($key === 'activity_totals' && $field === 'activity_label') {
            $postedTotalKey = (string) ($_POST['activity_key'] ?? '');
            $defaultTotals = activity_total_defaults();
            $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: (string) ($defaultTotals[$postedTotalKey]['activity_label'] ?? $postedTotalKey);
        } elseif ($key === 'activity_totals' && $field === 'financial_year') {
            $values[$field] = financial_year_normalize((string) ($_POST[$field] ?? ''));
        } elseif ($key === 'activity_totals' && in_array($field, ['total_count', 'beneficiary_count'], true)) {
            $values[$field] = max(0, (int) ($_POST[$field] ?? 0));
        } else {
            $values[$field] = trim((string) ($_POST[$field] ?? ''));
        }
    }

    if ($isTrainingPost) {
        $trainingPhotoPaths = training_photo_uploads(
            $pdo,
            $editId,
            training_record_post_value('training_name'),
            (string) ($values['image_path'] ?? '')
        );
        $values['image_path'] = $trainingPhotoPaths[1] ?? (string) ($values['image_path'] ?? '');
    }
    if ($isOftPost) {
        $oftPhotoPaths = oft_photo_uploads(
            $pdo,
            $editId,
            oft_record_post_value('oft_title'),
            (string) ($values['image_path'] ?? '')
        );
        $values['image_path'] = $oftPhotoPaths[1] ?? (string) ($values['image_path'] ?? '');
    }
    if ($isFldPost) {
        $fldPhotoPaths = fld_photo_uploads(
            $pdo,
            $editId,
            fld_record_post_value('technology_tested'),
            (string) ($values['image_path'] ?? '')
        );
        $values['image_path'] = $fldPhotoPaths[1] ?? (string) ($values['image_path'] ?? '');
    }
    if ($isExposurePost) {
        $values['image_path'] = upload_value_to_folder_named(
            'exposure_programme_photo',
            (string) ($values['image_path'] ?? ''),
            'exposurephoto',
            exposure_record_post_value('visit_name') . '-photo'
        );
    }

    if ($key === 'staff' && (int) ($values['is_active'] ?? 1) === 1) {
        $values['inactive_reason'] = '';
        $values['inactive_order_no'] = '';
    }

    $savedActivityId = $editId;
    $wasEditing = (bool) $editing;
    if ($editing) {
        $sets = implode(', ', array_map(fn($field) => "{$field} = ?", $fields));
        $stmt = $pdo->prepare("UPDATE {$module['table']} SET {$sets} WHERE id = ? AND submitted_by_user_id = ?");
        $stmt->execute([...array_values($values), $editId, $userDeskUserId]);
        if ($stmt->rowCount() === 0) {
            header('Location: user-manage.php?module=' . urlencode($key) . '&access=not_owner');
            exit;
        }
        $message = 'Record updated successfully.';
        $editing = user_desk_find_owned($pdo, (string) $module['table'], $editId, $userDeskUserId);
    } else {
        $insertFields = array_merge($fields, ['submitted_by_user_id', 'submitted_by_name', 'submitted_by_email', 'submitted_at']);
        $insertValues = array_merge(array_values($values), [$userDeskUserId, $userDeskUserName, $userDeskUserEmail, date('Y-m-d H:i:s')]);
        $columns = implode(', ', $insertFields);
        $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
        $stmt = $pdo->prepare("INSERT INTO {$module['table']} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($insertValues);
        $savedActivityId = (int) $pdo->lastInsertId();
        $message = 'Record saved successfully.';
    }

    if ($isTrainingPost) {
        if (!$trainingPhotoPaths) {
            $trainingPhotoPaths = training_photo_uploads($pdo, $savedActivityId, training_record_post_value('training_name'), (string) ($values['image_path'] ?? ''));
        }
        training_record_save($pdo, $savedActivityId, $trainingPhotoPaths);
    }
    if ($isOftPost) {
        if (!$oftPhotoPaths) {
            $oftPhotoPaths = oft_photo_uploads($pdo, $savedActivityId, oft_record_post_value('oft_title'), (string) ($values['image_path'] ?? ''));
        }
        oft_record_save($pdo, $savedActivityId, $oftPhotoPaths);
    }
    if ($isFldPost) {
        if (!$fldPhotoPaths) {
            $fldPhotoPaths = fld_photo_uploads($pdo, $savedActivityId, fld_record_post_value('technology_tested'), (string) ($values['image_path'] ?? ''));
        }
        fld_record_save($pdo, $savedActivityId, $fldPhotoPaths);
    }
    if ($isAwarenessPost) {
        awareness_record_save($pdo, $savedActivityId);
    }
    if ($isExhibitionPost) {
        exhibition_record_save($pdo, $savedActivityId);
    }
    if ($isSpecialEventPost) {
        special_event_record_save($pdo, $savedActivityId, $postedMandatedType);
    }
    if ($isBeneficiaryEventPost) {
        beneficiary_event_record_save($pdo, $savedActivityId, $postedMandatedType);
    }
    if ($isImportantDayPost) {
        important_day_record_save($pdo, $savedActivityId);
    }
    if ($isShgGosthiPost) {
        shg_gosthi_record_save($pdo, $savedActivityId, $postedMandatedType);
    }
    if ($isLecturePost) {
        lecture_record_save($pdo, $savedActivityId);
    }
    if ($isRadioTalkPost) {
        radio_talk_record_save($pdo, $savedActivityId);
    }
    if ($isTvTalkPost) {
        tv_talk_record_save($pdo, $savedActivityId);
    }
    if ($isTechnologyShowcasingPost) {
        technology_showcasing_record_save($pdo, $savedActivityId);
    }
    if ($isClusterDemonstrationPost) {
        cluster_demonstration_record_save($pdo, $savedActivityId);
    }
    if ($isSeedHubPost) {
        seed_hub_record_save($pdo, $savedActivityId);
    }
    if ($isPlantingMaterialPost) {
        planting_material_record_save($pdo, $savedActivityId);
    }
    if ($isExposurePost) {
        exposure_visit_record_save($pdo, $savedActivityId, $postedMandatedType);
    }

    if ($key === 'activities') {
        $newActivityKey = activity_total_key_from_slug((string) ($values['slug'] ?? ''));
        $newFinancialYear = $newActivityKey === 'training'
            ? current_financial_year(training_record_post_value('duration_from'))
            : ($newActivityKey === 'exhibition'
                ? current_financial_year(exhibition_record_post_value('date_from'))
                : (activity_is_special_event_record_slug($newActivityKey)
                    ? current_financial_year(special_event_record_post_value('date_from'))
                    : (activity_is_beneficiary_event_record_slug($newActivityKey)
                        ? current_financial_year(beneficiary_event_record_post_value('event_date'))
                        : ($newActivityKey === 'celebration-of-important-days'
                            ? current_financial_year(important_day_record_post_value('event_date'))
                            : (activity_is_shg_gosthi_record_slug($newActivityKey)
                                ? current_financial_year(shg_gosthi_record_post_value('formation_date'))
                                : (activity_is_visit_record_slug($newActivityKey)
                                    ? current_financial_year(exposure_record_post_value('visit_date'))
                                    : current_financial_year()))))));
        $newTrainingParticipants = $newActivityKey === 'training'
            ? training_record_int_value('farmers_men')
                + training_record_int_value('farmers_women')
                + training_record_int_value('rural_youth_men')
                + training_record_int_value('rural_youth_women')
                + training_record_int_value('ef_men')
                + training_record_int_value('ef_women')
            : 0;
        $newExposureBeneficiaries = activity_is_visit_record_slug($newActivityKey)
            ? exposure_record_int_value('beneficiaries_male') + exposure_record_int_value('beneficiaries_female')
            : 0;
        $newExhibitionVisitors = $newActivityKey === 'exhibition'
            ? exhibition_record_int_value('visitors_male') + exhibition_record_int_value('visitors_female')
            : 0;
        $newSpecialEventVisitors = activity_is_special_event_record_slug($newActivityKey)
            ? special_event_record_int_value('visitors_male') + special_event_record_int_value('visitors_female')
            : 0;
        $newBeneficiaryEventBeneficiaries = activity_is_beneficiary_event_record_slug($newActivityKey)
            ? beneficiary_event_record_int_value('beneficiaries_male') + beneficiary_event_record_int_value('beneficiaries_female')
            : 0;
        $newImportantDayBeneficiaries = $newActivityKey === 'celebration-of-important-days'
            ? important_day_record_int_value('beneficiaries_male') + important_day_record_int_value('beneficiaries_female')
            : 0;
        $newShgGosthiParticipants = activity_is_shg_gosthi_record_slug($newActivityKey)
            ? shg_gosthi_record_int_value('total_participants')
            : 0;
        $newBeneficiaries = $newTrainingParticipants + $newExposureBeneficiaries + $newExhibitionVisitors + $newSpecialEventVisitors + $newBeneficiaryEventBeneficiaries + $newImportantDayBeneficiaries + $newShgGosthiParticipants;
        $oldBeneficiaries = ($oldActivityKey === 'training' ? $oldTrainingParticipants : 0)
            + (activity_is_visit_record_slug($oldActivityKey) ? $oldExposureBeneficiaries : 0)
            + ($oldActivityKey === 'exhibition' ? $oldExhibitionVisitors : 0)
            + (activity_is_special_event_record_slug($oldActivityKey) ? $oldSpecialEventVisitors : 0)
            + (activity_is_beneficiary_event_record_slug($oldActivityKey) ? $oldBeneficiaryEventBeneficiaries : 0)
            + ($oldActivityKey === 'celebration-of-important-days' ? $oldImportantDayBeneficiaries : 0)
            + (activity_is_shg_gosthi_record_slug($oldActivityKey) ? $oldShgGosthiParticipants : 0);

        if (!$editing) {
            $stmt = $pdo->prepare('UPDATE activities SET counted_in_totals = 1, counted_financial_year = ? WHERE id = ?');
            $stmt->execute([$newFinancialYear, $savedActivityId]);
            activity_total_adjust($pdo, $newActivityKey, 1, $newBeneficiaries, $newFinancialYear);
        } elseif (activity_counted_in_totals($editing)) {
            if ($oldActivityKey !== $newActivityKey || $oldFinancialYear !== $newFinancialYear) {
                activity_total_adjust($pdo, $oldActivityKey, -1, -$oldBeneficiaries, $oldFinancialYear);
                activity_total_adjust($pdo, $newActivityKey, 1, $newBeneficiaries, $newFinancialYear);
                $stmt = $pdo->prepare('UPDATE activities SET counted_financial_year = ? WHERE id = ?');
                $stmt->execute([$newFinancialYear, $savedActivityId]);
            } elseif ($newActivityKey === 'training' || $newActivityKey === 'exhibition' || $newActivityKey === 'celebration-of-important-days' || activity_is_special_event_record_slug($newActivityKey) || activity_is_beneficiary_event_record_slug($newActivityKey) || activity_is_shg_gosthi_record_slug($newActivityKey) || activity_is_visit_record_slug($newActivityKey)) {
                activity_total_adjust($pdo, $newActivityKey, 0, $newBeneficiaries - $oldBeneficiaries, $newFinancialYear);
            }
        }
    }

    $recordTitle = (string) (($values['title'] ?? '') ?: ($values['name'] ?? '') ?: ($values['activity_label'] ?? '') ?: training_record_post_value('training_name') ?: ('Record ID #' . $savedActivityId));
    notesheet_auto_log(
        $pdo,
        (string) ($module['title'] ?? $key),
        ($wasEditing ? 'Updated ' : 'Added ') . (string) ($module['title'] ?? 'record'),
        ($wasEditing ? 'Updated' : 'Added') . ' "' . $recordTitle . '" in ' . (string) ($module['title'] ?? $key) . '. Record ID #' . $savedActivityId . '.'
    );

    if ($key === 'activities') {
        $returnDesk = $postedActivityDesk === 'other' ? 'other' : 'mandated';
        $returnType = activity_is_mandated_slug($postedMandatedType) ? $postedMandatedType : 'oft';
        header('Location: user-manage.php?module=activities&desk=' . urlencode($returnDesk) . '&activity_type=' . urlencode($returnType) . '&saved=' . ($wasEditing ? 'updated' : 'created'));
    } else {
        header('Location: user-manage.php?module=' . urlencode($key) . '&saved=' . ($wasEditing ? 'updated' : 'created'));
    }
    exit;
}

$userOwnedWhere = 'is_active = 1 AND submitted_by_user_id = ' . $userDeskUserId;
$rows = db_all(
    $module['table'],
    $userOwnedWhere,
    $key === 'activity_totals' ? 'financial_year DESC, activity_key ASC' : 'created_at DESC',
    100
);
$rows = is_array($rows) ? $rows : [];
$activityCounts = ['mandated' => 0, 'other' => 0, 'records' => 0];
$activityDeskRequested = array_key_exists('desk', $_GET);
$activityDesk = $_GET['desk'] ?? 'home';
if (!in_array($activityDesk, ['mandated', 'other'], true)) {
    $activityDesk = 'home';
}
$activityMandatedOptions = activity_mandated_options();
$activityMandatedDescriptions = activity_mandated_descriptions();
$activityPublicOutreachTopics = activity_public_outreach_topics();
$activityMandatedType = activity_canonical_slug((string) ($_GET['activity_type'] ?? 'oft'));
if (!array_key_exists($activityMandatedType, $activityMandatedOptions) && !array_key_exists($activityMandatedType, $activityPublicOutreachTopics)) {
    $activityMandatedType = 'oft';
}
$activityMandatedCounts = array_fill_keys(array_keys($activityMandatedOptions), 0);
$activityPublicOutreachCounts = array_fill_keys(array_keys($activityPublicOutreachTopics), 0);
$activityCurrentLabel = $activityMandatedOptions[$activityMandatedType] ?? $activityPublicOutreachTopics[$activityMandatedType] ?? activity_slug_label($activityMandatedType);
$isPublicOutreachTopicDesk = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'public-outreach-programme';
$activityPublicOutreachReport = [];
$officialSections = official_activity_record_sections();
$officialRecordType = official_activity_record_type((string) ($_GET['official_type'] ?? ''));
$officialEditId = 0;
$officialEditing = null;
if ($officialEditing) {
    $officialRecordType = official_activity_record_type((string) ($officialEditing['record_type'] ?? $officialRecordType));
}
$officialRows = [];
$officialRecordCounts = array_fill_keys(array_keys($officialSections), 0);
if ($key === 'activities') {
    foreach ($rows as $row) {
        $rowSlug = activity_canonical_slug((string) ($row['slug'] ?? ''));
        $bucket = activity_is_mandated_slug($rowSlug) ? 'mandated' : 'other';
        $activityCounts[$bucket]++;
        if ($bucket === 'mandated' && isset($activityMandatedCounts[$rowSlug])) {
            $activityMandatedCounts[$rowSlug]++;
        } elseif ($bucket === 'mandated' && isset($activityPublicOutreachCounts[$rowSlug])) {
            $activityMandatedCounts['public-outreach-programme']++;
            $activityPublicOutreachCounts[$rowSlug]++;
        }
    }
    if ($pdo) {
        $officialRecordCounts = array_fill_keys(array_keys($officialSections), 0);
        $activityCounts['records'] = 0;
        $officialRows = [];
        if ($isPublicOutreachTopicDesk) {
            $ownedActivityIds = array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $rows);
            $activityPublicOutreachReport = activity_public_outreach_report($pdo, $ownedActivityIds);
        }
    }
    $rows = array_values(array_filter($rows, function (array $row) use ($activityDesk, $activityMandatedType): bool {
        $rowSlug = activity_canonical_slug((string) ($row['slug'] ?? ''));
        $isMandated = activity_is_mandated_slug($rowSlug);
        if ($activityDesk === 'mandated') {
            return $isMandated && $rowSlug === $activityMandatedType;
        }
        if ($activityDesk === 'other') {
            return !$isMandated;
        }
        return false;
    }));
}
$formFields = $module['fields'];
if ($key === 'activities') {
    $formFields = array_values(array_unique(['slug', ...$formFields]));
}
$activityFormSlug = activity_canonical_slug((string) ($editing['slug'] ?? ''));
$isTrainingEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'training') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'training'));
$isOftEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'oft') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'oft'));
$isFldEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'fld') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'fld'));
$isAwarenessEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'awareness-programme-camps') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'awareness-programme-camps'));
$isExhibitionEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'exhibition') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'exhibition'));
$isSpecialEventEntryForm = $key === 'activities'
    && (($editing && activity_is_special_event_record_slug($activityFormSlug)) || (!$editing && $activityDesk === 'mandated' && activity_is_special_event_record_slug($activityMandatedType)));
$isBeneficiaryEventEntryForm = $key === 'activities'
    && (($editing && activity_is_beneficiary_event_record_slug($activityFormSlug)) || (!$editing && $activityDesk === 'mandated' && activity_is_beneficiary_event_record_slug($activityMandatedType)));
$isImportantDayEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'celebration-of-important-days') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'celebration-of-important-days'));
$isShgGosthiEntryForm = $key === 'activities'
    && (($editing && activity_is_shg_gosthi_record_slug($activityFormSlug)) || (!$editing && $activityDesk === 'mandated' && activity_is_shg_gosthi_record_slug($activityMandatedType)));
$isLectureEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'lectures-delivered') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'lectures-delivered'));
$isRadioTalkEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'radio-talks') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'radio-talks'));
$isTvTalkEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'tv-talks') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'tv-talks'));
$isTechnologyShowcasingEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'technology-showcasing') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'technology-showcasing'));
$isClusterDemonstrationEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'cluster-demonstration') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'cluster-demonstration'));
$isSeedHubEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'seed-hub') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'seed-hub'));
$isPlantingMaterialEntryForm = $key === 'activities'
    && (($editing && $activityFormSlug === 'planting-materials') || (!$editing && $activityDesk === 'mandated' && $activityMandatedType === 'planting-materials'));
$isExposureEntryForm = $key === 'activities'
    && (($editing && activity_is_visit_record_slug($activityFormSlug)) || (!$editing && $activityDesk === 'mandated' && activity_is_visit_record_slug($activityMandatedType)));
$staffExpertiseOptions = ($isTrainingEntryForm || $isOftEntryForm || $isFldEntryForm) ? staff_expertise_options() : [];
$oftCropOptions = $isOftEntryForm ? oft_crop_options($pdo) : [];
$fldCropOptions = $isFldEntryForm ? fld_crop_options($pdo) : [];
$showTrainingRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'training';
$showOftRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'oft';
$showFldRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'fld';
$showAwarenessRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'awareness-programme-camps';
$showExhibitionRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'exhibition';
$showSpecialEventRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && activity_is_special_event_record_slug($activityMandatedType);
$showBeneficiaryEventRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && activity_is_beneficiary_event_record_slug($activityMandatedType);
$showImportantDayRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'celebration-of-important-days';
$showShgGosthiRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && activity_is_shg_gosthi_record_slug($activityMandatedType);
$showLectureRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'lectures-delivered';
$showRadioTalkRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'radio-talks';
$showTvTalkRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'tv-talks';
$showTechnologyShowcasingColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'technology-showcasing';
$showClusterDemonstrationColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'cluster-demonstration';
$showSeedHubColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'seed-hub';
$showPlantingMaterialColumns = $key === 'activities' && $activityDesk === 'mandated' && $activityMandatedType === 'planting-materials';
$showExposureRecordColumns = $key === 'activities' && $activityDesk === 'mandated' && activity_is_visit_record_slug($activityMandatedType);
$specialEventFormSlug = $editing && $isSpecialEventEntryForm ? $activityFormSlug : $activityMandatedType;
$specialEventFormConfig = $isSpecialEventEntryForm ? activity_special_event_record_config($specialEventFormSlug) : [];
$specialEventLabel = (string) ($specialEventFormConfig['label'] ?? '');
$specialEventTitleLabel = (string) ($specialEventFormConfig['title_label'] ?? 'Title');
$beneficiaryEventFormSlug = $editing && $isBeneficiaryEventEntryForm ? $activityFormSlug : $activityMandatedType;
$beneficiaryEventFormConfig = $isBeneficiaryEventEntryForm ? activity_beneficiary_event_record_config($beneficiaryEventFormSlug) : [];
$beneficiaryEventLabel = (string) ($beneficiaryEventFormConfig['label'] ?? '');
$shgGosthiFormSlug = $editing && $isShgGosthiEntryForm ? $activityFormSlug : $activityMandatedType;
$shgGosthiFormConfig = $isShgGosthiEntryForm ? shg_gosthi_record_config($shgGosthiFormSlug) : [];
$shgGosthiLabel = (string) ($shgGosthiFormConfig['label'] ?? '');
$officialFormData = $officialEditing ? official_activity_record_data($officialEditing) : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($module['title']) ?> | My Data Entry</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(240, 179, 58, .22), transparent 34rem),
                radial-gradient(circle at 86% 18%, rgba(31, 121, 170, .18), transparent 28rem),
                linear-gradient(180deg, #f5fbf2 0%, #eef7f5 48%, #fbfdf7 100%);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image:
                linear-gradient(rgba(23, 69, 45, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(23, 69, 45, .045) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(180deg, rgba(0, 0, 0, .7), transparent 78%);
            pointer-events: none;
        }

        .module-main {
            display: grid;
            gap: 22px;
            width: min(1540px, calc(100% - 36px));
            margin: 0 auto;
            padding: 30px 0 42px;
        }

        .module-topbar {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 158px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, .48);
            border-radius: 18px;
            color: #fff;
            background:
                linear-gradient(135deg, rgba(19, 69, 45, .98), rgba(31, 112, 93, .95) 45%, rgba(18, 80, 118, .93)),
                linear-gradient(90deg, rgba(240, 179, 58, .28), transparent 60%);
            box-shadow: 0 26px 70px rgba(23, 69, 45, .24);
        }

        .module-topbar::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(120deg, transparent, rgba(255, 255, 255, .13), transparent 64%),
                repeating-linear-gradient(90deg, rgba(255, 255, 255, .08) 0 1px, transparent 1px 42px);
            opacity: .8;
            pointer-events: none;
        }

        .module-topbar::after {
            content: '';
            position: absolute;
            right: -78px;
            bottom: -124px;
            width: 360px;
            height: 360px;
            border: 42px solid rgba(240, 179, 58, .2);
            border-radius: 50%;
            box-shadow: inset 0 0 0 18px rgba(255, 255, 255, .07);
            pointer-events: none;
        }

        .module-topbar > * {
            position: relative;
            z-index: 1;
        }

        .module-brandline {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr);
            align-items: center;
            gap: 20px;
        }

        .module-brandline__seal {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            border: 2px solid rgba(255, 255, 255, .52);
            border-radius: 16px;
            color: #17452d;
            background: linear-gradient(180deg, #ffffff, #f5fbef);
            font-size: 19px;
            font-weight: 950;
            box-shadow: 0 16px 34px rgba(0, 0, 0, .2);
        }

        .module-brandline p,
        .module-brandline h1 {
            margin: 0;
        }

        .module-brandline p,
        .eyebrow {
            color: rgba(255, 255, 255, .78);
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .module-brandline h1 {
            color: #fff;
            font-size: clamp(34px, 4.2vw, 54px);
            line-height: 1.02;
        }

        .module-brandline__sub {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 15px;
        }

        .module-brandline__sub span {
            padding: 8px 11px;
            border: 1px solid rgba(255, 255, 255, .24);
            border-radius: 999px;
            color: rgba(255, 255, 255, .92);
            background: rgba(255, 255, 255, .13);
            font-size: 12px;
            font-weight: 900;
            backdrop-filter: blur(12px);
        }

        .module-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .module-actions a,
        .btn {
            border-radius: 999px;
        }

        .module-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 16px;
            border: 1px solid rgba(255, 255, 255, .3);
            color: #fff;
            font-weight: 900;
            background: rgba(255, 255, 255, .13);
            backdrop-filter: blur(10px);
            transition: transform .18s ease, background .18s ease, box-shadow .18s ease;
        }

        .module-actions a:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, .2);
            box-shadow: 0 14px 30px rgba(0, 0, 0, .13);
        }

        .module-actions a:first-child {
            color: #17351f;
            border-color: #f0b33a;
            background: #f0b33a;
        }

        .admin-panel,
        .activity-home-desk,
        .activity-type-desk,
        .staff-section-desk {
            overflow: hidden;
            border: 1px solid rgba(190, 211, 198, .92);
            border-radius: 18px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 18px 42px rgba(23, 69, 45, .1);
            backdrop-filter: blur(14px);
        }

        .admin-panel {
            border-top: 6px solid #256d3b;
        }

        .activity-home-desk,
        .staff-section-desk {
            padding: 24px;
        }

        .activity-home-desk__head,
        .activity-type-desk__head,
        .staff-section-desk__head,
        .admin-panel__title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 0;
            padding: 22px 24px;
            border-bottom: 1px solid rgba(226, 238, 222, .95);
            background: linear-gradient(180deg, rgba(247, 251, 244, .92), rgba(255, 255, 255, .84));
        }

        .activity-home-desk__head {
            margin: -24px -24px 22px;
        }

        .staff-section-desk__head {
            margin: -24px -24px 18px;
        }

        .activity-home-desk__head h2,
        .activity-type-desk__head h2,
        .staff-section-desk__head h2,
        .admin-panel__title h2 {
            margin: 4px 0 0;
            color: #17452d;
            font-size: clamp(24px, 2.5vw, 36px);
            line-height: 1.08;
        }

        .activity-home-desk__head .eyebrow,
        .activity-type-desk__head .eyebrow,
        .staff-section-desk__head .eyebrow {
            color: #2f7c4a;
        }

        .activity-home-grid,
        .staff-section-cards,
        .official-record-cards {
            display: grid;
            gap: 16px;
        }

        .activity-home-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .activity-home-desk {
            padding: 0;
        }

        .activity-home-desk__head {
            margin: 0;
            padding: 28px 30px;
            border-bottom: 0;
            color: #fff;
            background:
                radial-gradient(circle at 88% 18%, rgba(240, 179, 58, .28), transparent 17rem),
                linear-gradient(135deg, #17452d 0%, #246c49 52%, #1b6f86 100%);
        }

        .activity-home-desk__head h2 {
            color: #fff;
            font-size: clamp(28px, 3vw, 42px);
        }

        .activity-home-desk__head .eyebrow {
            color: #d9f4df;
        }

        .activity-home-desk__subtitle {
            max-width: 780px;
            margin: 9px 0 0;
            color: rgba(255, 255, 255, .82);
            font-weight: 700;
            line-height: 1.55;
        }

        .activity-home-desk__head .btn {
            min-height: 44px;
            border-color: rgba(255, 255, 255, .35);
            color: #17452d;
            background: #fff;
            box-shadow: 0 12px 26px rgba(0, 0, 0, .12);
        }

        .activity-home-body {
            padding: 24px 26px 28px;
        }

        .activity-overview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .activity-overview-stat {
            position: relative;
            overflow: hidden;
            min-height: 112px;
            padding: 18px 20px;
            border: 1px solid #dce9df;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff, #f7fbf6);
            box-shadow: 0 10px 24px rgba(23, 69, 45, .07);
        }

        .activity-overview-stat::after {
            content: '';
            position: absolute;
            right: -32px;
            bottom: -42px;
            width: 110px;
            height: 110px;
            border: 20px solid rgba(37, 109, 59, .07);
            border-radius: 50%;
        }

        .activity-overview-stat small,
        .activity-overview-stat strong,
        .activity-overview-stat span {
            position: relative;
            z-index: 1;
            display: block;
        }

        .activity-overview-stat small {
            margin-bottom: 7px;
            color: #65766c;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .activity-overview-stat strong {
            color: #17452d;
            font-size: 31px;
            line-height: 1;
        }

        .activity-overview-stat span {
            margin-top: 8px;
            color: #68766d;
            font-size: 13px;
            font-weight: 700;
        }

        .activity-home-toolbar {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin: 0 0 17px;
            padding: 0 2px;
        }

        .activity-home-toolbar h3,
        .activity-home-toolbar p {
            margin: 0;
        }

        .activity-home-toolbar h3 {
            color: #17452d;
            font-size: 22px;
        }

        .activity-home-toolbar p {
            margin-top: 4px;
            color: #6d7a72;
            font-size: 13px;
            font-weight: 700;
        }

        .activity-home-search {
            position: relative;
            width: min(360px, 100%);
        }

        .activity-home-search::before {
            content: '⌕';
            position: absolute;
            left: 14px;
            top: 50%;
            z-index: 2;
            transform: translateY(-51%);
            color: #397755;
            font-size: 22px;
            line-height: 1;
            pointer-events: none;
        }

        .activity-home-search input {
            width: 100%;
            min-height: 46px;
            padding: 10px 15px 10px 42px;
            border: 1px solid #cfe0d4;
            border-radius: 14px;
            outline: none;
            color: #17351f;
            background: #fff;
            font: inherit;
            font-weight: 750;
            box-shadow: 0 8px 20px rgba(23, 69, 45, .06);
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .activity-home-search input:focus {
            border-color: #2e8a4e;
            box-shadow: 0 0 0 4px rgba(46, 138, 78, .12);
        }

        .activity-card-empty {
            display: none;
            margin-top: 12px;
            padding: 22px;
            border: 1px dashed #c9dbce;
            border-radius: 16px;
            color: #63726a;
            background: #f8fbf7;
            text-align: center;
            font-weight: 800;
        }

        .activity-card-empty.is-visible {
            display: block;
        }

        .staff-section-cards {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .activity-public-topic-grid,
        .official-record-cards {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            padding: 20px;
        }

        .activity-public-topic-grid {
            display: grid;
            gap: 14px;
            background: linear-gradient(180deg, rgba(247, 251, 244, .5), rgba(255, 255, 255, .92));
        }

        .public-outreach-report {
            margin-top: 24px;
        }

        .public-outreach-report__year {
            margin-top: 16px;
            border: 1px solid rgba(28, 82, 50, .12);
            border-radius: 18px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 15px 32px rgba(23, 69, 45, .08);
        }

        .public-outreach-report__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #17452d, #2e8a4e);
            color: #fff;
        }

        .public-outreach-report__head h3,
        .public-outreach-report__head p {
            margin: 0;
        }

        .public-outreach-report__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .public-outreach-report__actions button {
            border: 1px solid rgba(255, 255, 255, .55);
            border-radius: 999px;
            padding: 8px 14px;
            color: #17452d;
            background: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .public-outreach-report__table-wrap {
            overflow-x: auto;
        }

        .public-outreach-report table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }

        .public-outreach-report th,
        .public-outreach-report td {
            padding: 13px 14px;
            border-bottom: 1px solid rgba(28, 82, 50, .1);
            color: #24382d;
            text-align: left;
            vertical-align: top;
        }

        .public-outreach-report th {
            color: #17452d;
            background: #f3f8f0;
            font-weight: 900;
        }

        .public-outreach-report tr:last-child td {
            border-bottom: 0;
        }

        .admin-export-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .admin-export-actions button,
        .admin-export-actions select {
            border: 1px solid rgba(23, 69, 45, .24);
            border-radius: 999px;
            padding: 9px 14px;
            color: #17452d;
            background: #fff;
            font-weight: 850;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(23, 69, 45, .08);
        }

        .admin-export-actions button:hover,
        .admin-export-actions button:focus {
            color: #fff;
            background: #17452d;
        }

        .admin-export-actions select {
            min-width: 170px;
            color: #17452d;
            background: #f7fbf4;
        }

        .commodity-register-dialog {
            width: min(900px, calc(100vw - 32px));
            border: 0;
            border-radius: 18px;
            padding: 0;
            color: #24382d;
            box-shadow: 0 28px 70px rgba(15, 50, 30, .28);
        }

        .commodity-register-dialog::backdrop {
            background: rgba(10, 30, 18, .42);
        }

        .commodity-register-dialog form {
            padding: 22px;
        }

        .commodity-register-dialog__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .commodity-register-dialog__head h3,
        .commodity-register-dialog__head p {
            margin: 0;
        }

        .commodity-register-dialog__head h3 {
            color: #17452d;
            font-size: 1.4rem;
        }

        .commodity-register-dialog__head button {
            border: 0;
            border-radius: 999px;
            width: 34px;
            height: 34px;
            color: #17452d;
            background: #f3f8f0;
            font-weight: 900;
            cursor: pointer;
        }

        .commodity-register-dialog__grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 12px;
        }

        .commodity-register-dialog__table {
            margin-top: 18px;
            max-height: 260px;
            overflow: auto;
        }

        .planting-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }

        .planting-tabs button {
            border: 1px solid rgba(23, 69, 45, .18);
            border-radius: 999px;
            padding: 10px 16px;
            color: #17452d;
            background: #fff;
            font-weight: 900;
            cursor: pointer;
        }

        .planting-tabs button.is-active {
            color: #fff;
            background: #17452d;
        }

        .planting-tab-panel {
            display: none;
        }

        .planting-tab-panel.is-active {
            display: block;
        }

        .activity-admin-tabs {
            display: flex;
            margin: 0;
            padding: 0;
        }

        .activity-admin-tabs a,
        .activity-home-card,
        .official-record-card,
        .staff-section-card {
            box-shadow: 0 15px 32px rgba(23, 69, 45, .09);
        }

        .activity-admin-tabs a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            width: 100%;
            padding: 16px 18px;
            border: 1px solid rgba(190, 211, 198, .92);
            border-radius: 18px;
            color: #17452d;
            background: linear-gradient(180deg, #fff, #f7fbf4);
        }

        .activity-admin-tabs__mark {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            color: #fff;
            background: linear-gradient(135deg, #256d3b, #1f79aa);
            font-weight: 950;
        }

        .activity-admin-tabs strong,
        .activity-admin-tabs small {
            display: block;
        }

        .activity-admin-tabs small {
            color: #63726a;
            font-weight: 700;
        }

        .activity-admin-tabs b {
            min-width: 42px;
            padding: 7px 10px;
            border-radius: 999px;
            color: #17452d;
            background: #f8e4ad;
            text-align: center;
        }

        .activity-home-card,
        .official-record-card,
        .staff-section-card {
            position: relative;
            overflow: hidden;
            display: grid;
            align-content: space-between;
            gap: 16px;
            min-height: 196px;
            padding: 20px;
            border: 1px solid rgba(209, 226, 214, .95);
            border-radius: 16px;
            color: #17351f;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(247, 251, 244, .96)),
                linear-gradient(135deg, rgba(145, 189, 67, .16), transparent 58%);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .activity-home-grid .activity-home-card {
            gap: 14px;
            min-height: 220px;
            padding: 20px 20px 18px;
            border-radius: 20px;
            background: linear-gradient(160deg, #fff 0%, #fbfdfb 68%, #f0f7f2 100%);
        }

        .activity-card__top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .activity-card__number {
            display: grid !important;
            place-items: center;
            width: 44px;
            height: 44px;
            border: 1px solid rgba(37, 109, 59, .12);
            border-radius: 14px;
            color: #256d3b !important;
            background: #eef7f0;
            font-size: 13px !important;
            font-weight: 950 !important;
            letter-spacing: .05em;
        }

        .activity-card__body {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 8px;
        }

        .activity-home-card .activity-card__title {
            color: #173f2a;
            font-size: 19px;
            line-height: 1.24;
        }

        .activity-home-card .activity-card__count {
            min-width: 52px;
            padding: 7px 12px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, #17452d, #2e8a4e);
            box-shadow: 0 8px 18px rgba(23, 69, 45, .15);
            font-size: 20px;
        }

        .activity-home-card .activity-card__open {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-self: start;
            padding: 9px 13px;
            color: #17452d;
            background: #edf6ef;
        }

        .activity-home-card:hover .activity-card__open {
            color: #fff;
            background: #256d3b;
        }

        .activity-public-topic-card {
            min-height: 150px;
        }

        .activity-home-card::before,
        .official-record-card::before,
        .staff-section-card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 7px;
            background: linear-gradient(90deg, #256d3b, #91bd43);
        }

        .activity-home-card::after,
        .official-record-card::after,
        .staff-section-card::after {
            content: '';
            position: absolute;
            right: -58px;
            bottom: -58px;
            width: 170px;
            height: 170px;
            border: 28px solid rgba(145, 189, 67, .12);
            border-radius: 50%;
            pointer-events: none;
        }

        .activity-home-card:nth-child(4n + 2)::before,
        .activity-public-topic-card:nth-child(4n + 2)::before {
            background: linear-gradient(90deg, #1f79aa, #62b6cb);
        }

        .activity-home-card:nth-child(4n + 3)::before,
        .activity-public-topic-card:nth-child(4n + 3)::before {
            background: linear-gradient(90deg, #c36b2c, #f0b33a);
        }

        .activity-home-card:nth-child(4n + 4)::before,
        .activity-public-topic-card:nth-child(4n + 4)::before {
            background: linear-gradient(90deg, #8a5aa8, #d36b7f);
        }

        .activity-home-card:hover,
        .official-record-card:hover,
        .staff-section-card:hover {
            transform: translateY(-5px);
            border-color: rgba(37, 109, 59, .35);
            background: #fff;
            box-shadow: 0 26px 54px rgba(23, 69, 45, .16);
        }

        .activity-home-card span,
        .activity-home-card small,
        .activity-home-card b,
        .activity-home-card strong,
        .official-record-card span,
        .official-record-card small,
        .official-record-card b,
        .staff-section-card span,
        .staff-section-card small,
        .staff-section-card b {
            position: relative;
            z-index: 1;
            display: block;
        }

        .activity-home-card span,
        .official-record-card span,
        .staff-section-card span {
            color: #17452d;
            font-size: 18px;
            font-weight: 950;
            line-height: 1.18;
        }

        .activity-home-card strong {
            width: fit-content;
            min-width: 58px;
            padding: 8px 13px;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, #17452d, #256d3b);
            font-size: 25px;
            line-height: 1;
            text-align: center;
        }

        .activity-home-card small,
        .official-record-card small,
        .staff-section-card small {
            color: #63726a;
            font-weight: 750;
            line-height: 1.42;
        }

        .activity-home-card b,
        .official-record-card b,
        .staff-section-card b {
            justify-self: start;
            padding: 9px 12px;
            border-radius: 999px;
            color: #125076;
            background: #eef7fb;
            font-size: 13px;
        }

        .form--admin {
            padding: 24px;
        }

        .form--admin label,
        fieldset.training-record,
        .oft-record__photos,
        .training-record__photos {
            border-radius: 14px;
        }

        .form--admin input,
        .form--admin select,
        .form--admin textarea,
        .oft-entry-table input,
        .oft-entry-table select,
        .oft-entry-table textarea {
            border-radius: 10px;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .form--admin input:focus,
        .form--admin select:focus,
        .form--admin textarea:focus,
        .oft-entry-table input:focus,
        .oft-entry-table select:focus,
        .oft-entry-table textarea:focus {
            outline: none;
            border-color: #256d3b;
            background: #fcfffb;
            box-shadow: 0 0 0 4px rgba(37, 109, 59, .12);
        }

        .activity-details-field[data-training="yes"] .activity-details-field__general,
        .activity-details-field[data-oft="yes"] .activity-details-field__general,
        .activity-details-field:not([data-training="yes"]) fieldset:not(.oft-record),
        .activity-details-field:not([data-oft="yes"]) .oft-record {
            display: none;
        }

        .training-record__wide,
        .oft-record--entry,
        .oft-record--entry + .form-actions {
            grid-column: 1 / -1;
        }

        .oft-record--entry {
            display: block;
            width: 100%;
            margin: 0;
            padding: 0;
            border: 0;
            min-inline-size: 0;
        }

        .oft-record--entry legend {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 14px;
            padding: 9px 13px;
            border-radius: 999px;
            color: #125076;
            background: #eef7fb;
            font-size: 13px;
            font-weight: 950;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .oft-record--entry legend::before {
            content: '+';
            display: grid;
            place-items: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #fff;
            background: #256d3b;
            font-size: 16px;
            line-height: 1;
        }

        .oft-entry-table-wrap,
        .table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(209, 226, 214, .95);
            border-radius: 14px;
            background: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .85), 0 12px 24px rgba(23, 69, 45, .07);
        }

        .oft-entry-table {
            min-width: 1480px;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .oft-entry-table th,
        .oft-entry-table td {
            padding: 14px;
            border-right: 1px solid #e4edf3;
            border-bottom: 1px solid #e4edf3;
            vertical-align: top;
        }

        .oft-entry-table tr > :last-child,
        table tr > :last-child {
            border-right: 0;
        }

        .oft-entry-table tbody tr:last-child > *,
        table tbody tr:last-child > * {
            border-bottom: 0;
        }

        .oft-entry-table th,
        .table-wrap table th {
            color: #17452d;
            background: linear-gradient(180deg, #f4faef, #eef7fb);
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .035em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .oft-entry-table textarea {
            min-width: 240px;
            resize: vertical;
        }

        .beneficiary-pair {
            display: grid;
            grid-template-columns: repeat(2, minmax(90px, 1fr));
            gap: 8px;
            min-width: 210px;
        }

        .oft-entry-table td:nth-child(1) input {
            min-width: 96px;
        }

        .oft-entry-table td:nth-child(5) textarea {
            min-width: 320px;
        }

        .oft-entry-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            color: #63726a;
            font-size: 13px;
            font-weight: 800;
        }

        .oft-entry-actions span {
            display: inline-flex;
            align-items: center;
            min-height: 32px;
            padding: 6px 10px;
            border: 1px solid #d9e8de;
            border-radius: 999px;
            background: #fff;
        }

        .oft-record__photos,
        .training-record__photos {
            margin-top: 18px;
            padding: 18px;
            border: 1px solid #d9e8de;
            background: #fbfdf7;
        }

        .oft-record__photos p,
        .training-record__photos p {
            margin: 0 0 12px;
            color: #17452d;
            font-weight: 950;
        }

        .oft-record__photos > div,
        .training-record__photos > div {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 12px;
        }

        .oft-record__photos label,
        .training-record__photos label {
            display: grid;
            gap: 8px;
            padding: 12px;
            border: 1px solid #e2eede;
            border-radius: 12px;
            background: #fff;
            color: #17452d;
            font-size: 13px;
            font-weight: 850;
        }

        .oft-record__photos input[type="file"],
        .training-record__photos input[type="file"] {
            max-width: 100%;
            font-size: 12px;
        }

        @media (max-width: 1180px) {
            .activity-home-grid,
            .activity-public-topic-grid,
            .official-record-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .activity-home-body {
                padding: 18px;
            }

            .activity-overview-grid {
                grid-template-columns: 1fr;
            }

            .activity-home-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .activity-home-search {
                width: 100%;
            }
            .module-main {
                width: min(100% - 24px, 760px);
                padding-top: 18px;
            }

            .module-topbar,
            .activity-home-desk__head,
            .activity-type-desk__head,
            .staff-section-desk__head,
            .admin-panel__title {
                align-items: flex-start;
                flex-direction: column;
            }

            .module-brandline {
                grid-template-columns: 58px minmax(0, 1fr);
            }

            .module-brandline__seal {
                width: 58px;
                height: 58px;
                border-radius: 13px;
                font-size: 16px;
            }

            .module-actions {
                justify-content: flex-start;
            }

            .activity-home-grid,
            .activity-public-topic-grid,
            .staff-section-cards,
            .official-record-cards {
                grid-template-columns: 1fr;
            }

            .oft-record__photos > div,
            .training-record__photos > div {
                grid-template-columns: 1fr;
            }
        }
    
        /* User Desk safety: staff can add/edit shared content; deletion stays with Admin. */
        a[href*="delete="], a[href*="_delete="] { display: none !important; }
        .user-entry-note {
            margin: 0 0 16px;
            padding: 11px 14px;
            border: 1px solid #cfe3d7;
            border-radius: 12px;
            background: #eff8f2;
            color: #24543b;
            font-size: .82rem;
            font-weight: 700;
        }
</style>
</head>
<body>
<div class="container" style="padding-top:12px"><div class="user-entry-note"><strong>My Data Entry:</strong> Entries saved here use the same existing website database and are visible to Admin through the corresponding Admin module.</div></div>
<div>
    <main class="admin-main module-main">
        <section class="module-topbar">
            <div class="module-brandline">
                <span class="module-brandline__seal">AAU</span>
                <div>
                    <p>Centralized Data Management System</p>
                    <h1><?= e($module['title']) ?></h1>
                    <div class="module-brandline__sub">
                        <span><?= e(date('d M Y')) ?></span>
                        <span><?= e((string) ($key === 'activities' && $activityDesk === 'home' ? ($activityCounts['mandated'] + $activityCounts['other']) : count($rows))) ?> records</span>
                        <span>User data entry</span>
                    </div>
                </div>
            </div>
            <div class="module-actions">
                <a href="../user-dashboard.php">User Desk</a>
                <a href="notesheet.php">Notesheet</a>
                <a href="../index.php">View Website</a>
                <a href="logout.php">Logout</a>
            </div>
        </section>
        <?php if (!$pdo): ?>
            <div class="alert">Database is not connected. Import database/schema.sql and check config.php before saving records.</div>
        <?php endif; ?>
        <?php if ($message): ?><div class="success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($key === 'staff'): ?>
            <section class="staff-section-desk" aria-label="Staff page section editors">
                <div class="staff-section-desk__head">
                    <div>
                        <p class="eyebrow">Staff Page Sections</p>
                        <h2>Edit staff.php tables</h2>
                    </div>
                    <a class="btn btn--primary" href="../staff.php" target="_blank">View Staff Page</a>
                </div>
                <div class="staff-section-cards">
                    <a class="staff-section-card" href="user-manage.php?module=staff_positions">
                        <span>Staff Position of KVK</span>
                        <small>Edit designation, strength, in-position count, and display order shown on staff.php.</small>
                        <b>Edit / Update</b>
                    </a>
                    <a class="staff-section-card" href="user-manage.php?module=staff_cadres">
                        <span>Cadre Wise Position</span>
                        <small>Modify technical and administrative cadre strength and in-position figures.</small>
                        <b>Edit / Update</b>
                    </a>
                    <a class="staff-section-card" href="user-manage.php?module=staff_heads">
                        <span>List of Senior Scientist & Head</span>
                        <small>Add, edit, modify, and order the head list displayed in the Staff page table.</small>
                        <b>Edit / Update</b>
                    </a>
                </div>
            </section>
        <?php endif; ?>
        <?php if ($key === 'activities'): ?>
            <?php if ($activityDesk === 'home'): ?>
                <section class="activity-home-desk" aria-label="Activities card desk">
                    <div class="activity-home-desk__head">
                        <div>
                            <p class="eyebrow">KVK Activity Management</p>
                            <h2>Activities Desk</h2>
                            <p class="activity-home-desk__subtitle">Select an activity module to add, update, review, print, or export your KVK activity records from one organized workspace.</p>
                        </div>
                        <a class="btn btn--primary" href="../activities.php" target="_blank" rel="noopener">View Public Activities</a>
                    </div>

                    <div class="activity-home-body">
                        <div class="activity-overview-grid" aria-label="Activities overview">
                            <div class="activity-overview-stat">
                                <small>Activity Modules</small>
                                <strong><?= e((string) count($activityMandatedOptions)) ?></strong>
                                <span>Available data-entry sections</span>
                            </div>
                            <div class="activity-overview-stat">
                                <small>Your Activity Records</small>
                                <strong><?= e((string) array_sum($activityMandatedCounts)) ?></strong>
                                <span>Total records entered in activity cards</span>
                            </div>
                            <div class="activity-overview-stat">
                                <small>Workspace</small>
                                <strong>KVK</strong>
                                <span>Central activity entry and reporting desk</span>
                            </div>
                        </div>

                        <div class="activity-home-toolbar">
                            <div>
                                <h3>Activity Modules</h3>
                                <p>Open a card below or search by activity name.</p>
                            </div>
                            <label class="activity-home-search" for="activityCardSearch">
                                <input type="search" id="activityCardSearch" placeholder="Search activities..." autocomplete="off" aria-label="Search activity modules">
                            </label>
                        </div>

                        <div class="activity-home-grid" id="activityCardGrid">
                            <?php $activityCardIndex = 0; ?>
                            <?php foreach ($activityMandatedOptions as $typeSlug => $typeLabel): ?>
                                <?php
                                $activityCardIndex++;
                                $activityCardDescription = (string) ($activityMandatedDescriptions[$typeSlug] ?? 'Activity database records');
                                $activityCardSearchText = strtolower($typeLabel . ' ' . $activityCardDescription . ' ' . $typeSlug);
                                ?>
                                <a class="activity-home-card"
                                   data-activity-card
                                   data-search="<?= e($activityCardSearchText) ?>"
                                   href="user-manage.php?module=activities&desk=mandated&activity_type=<?= e($typeSlug) ?>">
                                    <div class="activity-card__top">
                                        <span class="activity-card__number"><?= e(str_pad((string) $activityCardIndex, 2, '0', STR_PAD_LEFT)) ?></span>
                                        <strong class="activity-card__count" title="Saved records"><?= e((string) ($activityMandatedCounts[$typeSlug] ?? 0)) ?></strong>
                                    </div>
                                    <div class="activity-card__body">
                                        <span class="activity-card__title"><?= e($typeLabel) ?></span>
                                        <small><?= e($activityCardDescription) ?></small>
                                    </div>
                                    <b class="activity-card__open">Open Activity <span aria-hidden="true">→</span></b>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="activity-card-empty" id="activityCardEmpty">No matching activity module was found.</div>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($activityDesk === 'mandated' || $activityDesk === 'other'): ?>
                <nav class="activity-admin-tabs" aria-label="Activity desk">
                    <a href="user-manage.php?module=activities">
                        <span class="activity-admin-tabs__mark">A</span>
                        <span>
                            <strong>Activities Cards</strong>
                            <small>Back to main activities desk</small>
                        </span>
                        <b><?= e((string) $activityCounts['mandated']) ?></b>
                    </a>
                </nav>
            <?php endif; ?>
            <?php if ($activityDesk === 'mandated'): ?>
                <section class="activity-type-desk" aria-label="Mandated activity types">
                    <div class="activity-type-desk__head">
                        <div>
                            <p class="eyebrow">Activity Entry Page</p>
                            <h2><?= e($activityCurrentLabel) ?></h2>
                        </div>
                        <a class="btn btn--light" href="user-manage.php?module=activities">Back to Activity Cards</a>
                    </div>
                    <?php if ($isPublicOutreachTopicDesk): ?>
                        <div class="activity-public-topic-grid" aria-label="Public outreach programme topics">
                            <?php foreach ($activityPublicOutreachTopics as $topicSlug => $topicLabel): ?>
                                <a class="activity-home-card activity-public-topic-card" href="user-manage.php?module=activities&desk=mandated&activity_type=<?= e($topicSlug) ?>">
                                    <span><?= e($topicLabel) ?></span>
                                    <strong><?= e((string) ($activityPublicOutreachCounts[$topicSlug] ?? 0)) ?></strong>
                                    <small><?= $topicSlug === 'other' ? 'Use this card to type the name of any programme not listed above.' : 'Public outreach programme database records.' ?></small>
                                    <b>Open card</b>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="public-outreach-report" aria-label="Public outreach programme financial year report">
                            <div class="admin-panel__title">
                                <div>
                                    <h2>Financial Year Programme Summary</h2>
                                    <p>Print or export the year-wise programme summary for official records.</p>
                                </div>
                            </div>
                            <?php if (!$activityPublicOutreachReport): ?>
                                <div class="empty-state">No Public Outreach Programme records are available for the financial year summary yet.</div>
                            <?php endif; ?>
                            <?php foreach ($activityPublicOutreachReport as $reportYear => $reportRows): ?>
                                <?php $reportId = 'admin-public-outreach-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($reportYear)); ?>
                                <section class="public-outreach-report__year">
                                    <div class="public-outreach-report__head">
                                        <div>
                                            <p>Financial Year</p>
                                            <h3><?= e($reportYear) ?></h3>
                                        </div>
                                        <div class="public-outreach-report__actions">
                                            <button type="button" data-admin-print-target="<?= e($reportId) ?>">Print</button>
                                            <button type="button" data-admin-export-target="<?= e($reportId) ?>" data-admin-export-name="public-outreach-programme-<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($reportYear))) ?>">Excel Export</button>
                                        </div>
                                    </div>
                                    <div class="public-outreach-report__table-wrap" id="<?= e($reportId) ?>">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Type of Programme</th>
                                                    <th>Total Programme Conducted</th>
                                                    <th>Dates</th>
                                                    <th>Beneficiary Male</th>
                                                    <th>Beneficiary Female</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportRows as $reportRow): ?>
                                                    <tr>
                                                        <td><?= e($reportRow['type']) ?></td>
                                                        <td><?= e((string) $reportRow['count']) ?></td>
                                                        <td><?= e($reportRow['dates'] ? implode(', ', array_keys($reportRow['dates'])) : '-') ?></td>
                                                        <td><?= e($reportRow['has_male'] ? (string) $reportRow['male'] : '-') ?></td>
                                                        <td><?= e($reportRow['has_female'] ? (string) $reportRow['female'] : '-') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($activityDesk === 'other'): ?>
                <section class="activity-type-desk activity-type-desk--simple">
                    <div class="activity-type-desk__head">
                        <div>
                            <p class="eyebrow">Activity Entry Page</p>
                            <h2>Other activities desk</h2>
                        </div>
                        <a class="btn btn--light" href="user-manage.php?module=activities">Back to Activity Cards</a>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($key === 'activities' && $activityDesk === 'records'): ?>
            <?php $officialSection = $officialSections[$officialRecordType]; ?>
            <section class="admin-panel">
                <div class="admin-panel__title">
                    <div>
                        <h2>Official Format-1 tables</h2>
                        <p>Click a card to open its table, fill one row, then save and add the next row.</p>
                    </div>
                </div>
                <div class="official-record-cards">
                    <?php foreach ($officialSections as $type => $section): ?>
                        <a class="official-record-card <?= $officialRecordType === $type ? 'is-active' : '' ?>" href="user-manage.php?module=activities&desk=records&official_type=<?= e($type) ?>">
                            <span><?= e($section['title']) ?></span>
                            <small><?= e($section['description']) ?></small>
                            <b><?= e((string) ($officialRecordCounts[$type] ?? 0)) ?> saved</b>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="admin-panel">
                <div class="admin-panel__title">
                    <div>
                        <h2><?= $officialEditing ? 'Edit row' : 'Fill table row' ?>: <?= e($officialSection['title']) ?></h2>
                        <p><?= e($officialSection['description']) ?></p>
                    </div>
                    <?php if ($officialRows || $officialEditing): ?>
                        <a class="btn btn--light" href="user-manage.php?module=activities&desk=records&official_type=<?= e($officialRecordType) ?>">Add another row</a>
                    <?php endif; ?>
                </div>
                <form class="form form--admin official-record-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="official_record_submit" value="1">
                    <input type="hidden" name="official_edit_id" value="<?= e((string) ($officialEditing['id'] ?? 0)) ?>">
                    <input type="hidden" name="official_record_type" value="<?= e($officialRecordType) ?>">
                    <label>Financial year
                        <input name="official_financial_year" value="<?= e((string) ($officialEditing['financial_year'] ?? current_financial_year())) ?>" placeholder="2025-26">
                    </label>
                    <fieldset class="official-record">
                        <legend><?= e($officialSection['title']) ?></legend>
                        <div class="official-record__grid">
                            <?php foreach ($officialSection['fields'] as $field => $config): ?>
                                <?php $value = (string) ($officialFormData[$field] ?? ''); ?>
                                <label><?= e($config['label']) ?>
                                    <?php if (($config['type'] ?? '') === 'textarea'): ?>
                                        <textarea name="official_record[<?= e($field) ?>]" rows="3"><?= e($value) ?></textarea>
                                    <?php elseif (($config['type'] ?? '') === 'select'): ?>
                                        <select name="official_record[<?= e($field) ?>]">
                                            <?php foreach (($config['options'] ?? []) as $optionValue => $optionLabel): ?>
                                                <option value="<?= e((string) $optionValue) ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>><?= e((string) $optionLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?= ($config['type'] ?? '') === 'number' ? 'number' : 'text' ?>" <?= ($config['type'] ?? '') === 'number' ? 'min="0"' : '' ?> name="official_record[<?= e($field) ?>]" value="<?= e($value) ?>">
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit" <?= $pdo ? '' : 'disabled' ?>><?= $officialEditing ? 'Update row' : 'Save & add next' ?></button>
                        <?php if ($officialEditing): ?><a class="btn btn--light" href="user-manage.php?module=activities&desk=records&official_type=<?= e($officialRecordType) ?>">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </section>
            <section class="admin-panel">
                <div class="admin-panel__title">
                    <div>
                        <h2><?= e($officialSection['title']) ?></h2>
                        <p>Stored rows for the selected Format-1 table.</p>
                    </div>
                    <div class="admin-export-actions">
                        <select data-card-year-filter="official-record-export-table" aria-label="Sort by financial year">
                            <option value="">All Financial Years</option>
                        </select>
                        <button type="button" data-card-print-target="official-record-export-table" data-card-export-title="<?= e($officialSection['title']) ?>">Print</button>
                        <button type="button" data-card-export-target="official-record-export-table" data-card-export-name="<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($officialSection['title']))) ?>">Excel Export</button>
                    </div>
                </div>
                <div class="table-wrap official-record-table">
                    <table id="official-record-export-table">
                        <thead>
                        <tr>
                            <th>Financial Year</th>
                            <?php foreach ($officialSection['fields'] as $config): ?>
                                <th><?= e($config['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($officialRows as $row): ?>
                            <?php $rowData = official_activity_record_data($row); ?>
                            <tr>
                                <td><?= e((string) ($row['financial_year'] ?? '')) ?></td>
                                <?php foreach ($officialSection['fields'] as $field => $config): ?>
                                    <td><?= e(excerpt((string) ($rowData[$field] ?? ''), 90)) ?></td>
                                <?php endforeach; ?>
                                <td class="table-actions">
                                    <a href="user-manage.php?module=activities&desk=records&official_type=<?= e($officialRecordType) ?>&official_edit=<?= e((string) $row['id']) ?>">Edit</a>
                                    <a href="user-manage.php?module=activities&desk=records&official_type=<?= e($officialRecordType) ?>&official_delete=<?= e((string) $row['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this official record?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$officialRows): ?>
                            <tr>
                                <td colspan="<?= e((string) (count($officialSection['fields']) + 2)) ?>"><span class="muted">No official records saved for this format yet.</span></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php elseif (!($key === 'activities' && ($activityDesk === 'home' || $isPublicOutreachTopicDesk))): ?>
        <section class="admin-panel" <?= $key === 'activities' ? 'id="activity-entry"' : '' ?>>
            <div class="admin-panel__title">
                <div>
                    <h2><?= $isSeedHubEntryForm ? ($editing ? 'Edit Seed Hub record' : 'Seed Hub data entry') : ($isClusterDemonstrationEntryForm ? ($editing ? 'Edit Cluster Demonstration record' : 'Cluster Demonstration data entry') : ($isTechnologyShowcasingEntryForm ? ($editing ? 'Edit Technology Showcasing record' : 'Technology Showcasing data entry') : ($isTvTalkEntryForm ? ($editing ? 'Edit TV Talk record' : 'TV Talk data entry') : ($isRadioTalkEntryForm ? ($editing ? 'Edit Radio Talk record' : 'Radio Talk data entry') : ($isLectureEntryForm ? ($editing ? 'Edit Lecture Delivered record' : 'Lectures Delivered data entry') : ($isShgGosthiEntryForm ? ($editing ? 'Edit ' . e($shgGosthiLabel) . ' record' : e($shgGosthiLabel) . ' data entry') : ($isImportantDayEntryForm ? ($editing ? 'Edit Celebration of Important Days record' : 'Celebration of Important Days data entry') : ($isBeneficiaryEventEntryForm ? ($editing ? 'Edit ' . e($beneficiaryEventLabel) . ' record' : e($beneficiaryEventLabel) . ' data entry') : ($isSpecialEventEntryForm ? ($editing ? 'Edit ' . e($specialEventLabel) . ' record' : e($specialEventLabel) . ' data entry') : ($isExhibitionEntryForm ? ($editing ? 'Edit Exhibition record' : 'Exhibition data entry') : ($isAwarenessEntryForm ? ($editing ? 'Edit Awareness Programme record' : 'Awareness Programme data entry') : ($isFldEntryForm ? ($editing ? 'Edit FLD record' : 'FLD data entry') : ($isOftEntryForm ? ($editing ? 'Edit OFT record' : 'OFT data entry') : ($editing ? 'Edit record' : ($key === 'activities' ? 'Store activity data' : 'Add new record'))))))))))))))) ?></h2>
                    <?php if ($key === 'activities'): ?>
                        <p><?= $isSeedHubEntryForm ? 'Enter one Seed Hub row and save it to the seed hub database records.' : ($isClusterDemonstrationEntryForm ? 'Enter one Cluster Demonstration row and save it to the cluster demonstration database records.' : ($isTechnologyShowcasingEntryForm ? 'Enter one Technology Showcasing row and save it to the technology showcasing database records.' : ($isTvTalkEntryForm ? 'Enter one TV Talk row and save it to the TV talk database records.' : ($isRadioTalkEntryForm ? 'Enter one Radio Talk row and save it to the radio talk database records.' : ($isLectureEntryForm ? 'Enter one Lectures Delivered row and save it to the lecture database records.' : ($isShgGosthiEntryForm ? 'Enter one ' . e($shgGosthiLabel) . ' row and save it to the ' . e($shgGosthiLabel) . ' database records.' : ($isImportantDayEntryForm ? 'Enter one Celebration of Important Days row and save it to the important days database records.' : ($isBeneficiaryEventEntryForm ? 'Enter one ' . e($beneficiaryEventLabel) . ' row and save it to the ' . e($beneficiaryEventLabel) . ' database records.' : ($isSpecialEventEntryForm ? 'Enter one ' . e($specialEventLabel) . ' row and save it to the ' . e($specialEventLabel) . ' database records.' : ($isExhibitionEntryForm ? 'Enter one Exhibition row and save it to the exhibition database records.' : ($isAwarenessEntryForm ? 'Enter one Awareness Programme row and save it to the awareness database records.' : ($isFldEntryForm ? 'Enter one FLD row and save it to the FLD database records.' : ($isOftEntryForm ? 'Enter one OFT row and save it to the OFT database records.' : ($activityDesk === 'mandated' ? 'Create or update a ' . e($activityCurrentLabel) . ' database record.' : 'Create or update an activity outside the mandated activity group.')))))))))))))) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($key === 'activities'): ?><a class="btn btn--light" href="user-manage.php?module=activities">Back to Cards</a><?php endif; ?>
            </div>
            <form class="form form--admin" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php if ($isExposureEntryForm): ?>
                    <?php
                        $visitRecordSlug = $editing ? $activityFormSlug : $activityMandatedType;
                        $visitRecordLabel = activity_slug_label($visitRecordSlug);
                        $exposureRecord = activity_exposure_record_for_form($pdo, $editing, $visitRecordSlug);
                    ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="<?= e($visitRecordSlug) ?>">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend><?= e($visitRecordLabel) ?> data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Name of Visit</th>
                                        <th>Location</th>
                                        <th>Organised/Sponsored by</th>
                                        <th>No. of beneficiaries (Male, Female)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="exposure_record[visit_date]" value="<?= e((string) ($exposureRecord['visit_date'] ?? '')) ?>" required></td>
                                        <td><input name="exposure_record[visit_name]" value="<?= e((string) ($exposureRecord['visit_name'] ?? '')) ?>" placeholder="<?= e($visitRecordLabel) ?> name" required></td>
                                        <td><input name="exposure_record[location]" value="<?= e((string) ($exposureRecord['location'] ?? '')) ?>" placeholder="Location" required></td>
                                        <td><input name="exposure_record[organised_by]" value="<?= e((string) ($exposureRecord['organised_by'] ?? '')) ?>" placeholder="Organised/Sponsored by" required></td>
                                        <td>
                                            <div class="beneficiary-pair">
                                                <input type="number" min="0" name="exposure_record[beneficiaries_male]" value="<?= e((string) ($exposureRecord['beneficiaries_male'] ?? '0')) ?>" placeholder="Male">
                                                <input type="number" min="0" name="exposure_record[beneficiaries_female]" value="<?= e((string) ($exposureRecord['beneficiaries_female'] ?? '0')) ?>" placeholder="Female">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isTvTalkEntryForm): ?>
                    <?php $tvTalkRecord = tv_talk_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="tv-talks">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>TV Talk data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Topic</th>
                                        <th>TV Station</th>
                                        <th>Targeted Beneficiaries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="tv_talk_record[talk_date]" value="<?= e((string) ($tvTalkRecord['talk_date'] ?? '')) ?>" required></td>
                                        <td><input name="tv_talk_record[topic]" value="<?= e((string) ($tvTalkRecord['topic'] ?? '')) ?>" placeholder="Topic" required></td>
                                        <td><input name="tv_talk_record[tv_station]" value="<?= e((string) ($tvTalkRecord['tv_station'] ?? '')) ?>" placeholder="TV Station" required></td>
                                        <td><input name="tv_talk_record[targeted_beneficiaries]" value="<?= e((string) ($tvTalkRecord['targeted_beneficiaries'] ?? '')) ?>" placeholder="Targeted Beneficiaries" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isRadioTalkEntryForm): ?>
                    <?php $radioTalkRecord = radio_talk_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="radio-talks">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Radio Talk data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Topic</th>
                                        <th>Radio Station</th>
                                        <th>Targeted Beneficiaries</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="radio_talk_record[talk_date]" value="<?= e((string) ($radioTalkRecord['talk_date'] ?? '')) ?>" required></td>
                                        <td><input name="radio_talk_record[topic]" value="<?= e((string) ($radioTalkRecord['topic'] ?? '')) ?>" placeholder="Topic" required></td>
                                        <td><input name="radio_talk_record[radio_station]" value="<?= e((string) ($radioTalkRecord['radio_station'] ?? '')) ?>" placeholder="Radio Station" required></td>
                                        <td><input name="radio_talk_record[targeted_beneficiaries]" value="<?= e((string) ($radioTalkRecord['targeted_beneficiaries'] ?? '')) ?>" placeholder="Targeted Beneficiaries" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isLectureEntryForm): ?>
                    <?php $lectureRecord = lecture_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="lectures-delivered">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Lectures Delivered data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Name of the Programme</th>
                                        <th>Topic</th>
                                        <th>Organised By</th>
                                        <th>Venue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="lecture_record[programme_date]" value="<?= e((string) ($lectureRecord['programme_date'] ?? '')) ?>" required></td>
                                        <td><input name="lecture_record[programme_name]" value="<?= e((string) ($lectureRecord['programme_name'] ?? '')) ?>" placeholder="Name of the Programme" required></td>
                                        <td><input name="lecture_record[topic]" value="<?= e((string) ($lectureRecord['topic'] ?? '')) ?>" placeholder="Topic" required></td>
                                        <td><input name="lecture_record[organised_by]" value="<?= e((string) ($lectureRecord['organised_by'] ?? '')) ?>" placeholder="Organised By" required></td>
                                        <td><input name="lecture_record[venue]" value="<?= e((string) ($lectureRecord['venue'] ?? '')) ?>" placeholder="Venue" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isShgGosthiEntryForm): ?>
                    <?php $shgGosthiRecord = shg_gosthi_record_for_form($pdo, $editing, $shgGosthiFormSlug); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="<?= e($shgGosthiFormSlug) ?>">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend><?= e($shgGosthiLabel) ?> data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Date of Formation/Registration</th>
                                        <th>Location</th>
                                        <th>Total SHG/Kisan Gosthi participants</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="shg_gosthi_record[record_name]" value="<?= e((string) ($shgGosthiRecord['record_name'] ?? '')) ?>" placeholder="Name" required></td>
                                        <td><input type="date" name="shg_gosthi_record[formation_date]" value="<?= e((string) ($shgGosthiRecord['formation_date'] ?? '')) ?>" required></td>
                                        <td><input name="shg_gosthi_record[location]" value="<?= e((string) ($shgGosthiRecord['location'] ?? '')) ?>" placeholder="Location" required></td>
                                        <td><input type="number" min="0" name="shg_gosthi_record[total_participants]" value="<?= e((string) ($shgGosthiRecord['total_participants'] ?? '0')) ?>" placeholder="Total participants"></td>
                                        <td><textarea name="shg_gosthi_record[remarks]" rows="2" placeholder="Remarks"><?= e((string) ($shgGosthiRecord['remarks'] ?? '')) ?></textarea></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="training-record__photos oft-record__photos">
                            <p>Programme photograph</p>
                            <div>
                                <label>Photo
                                    <input type="file" name="exposure_programme_photo" accept="image/*">
                                    <?php if (!empty($editing['image_path'])): ?>
                                        <small>Current: <?= e((string) $editing['image_path']) ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    </fieldset>
                <?php elseif ($isImportantDayEntryForm): ?>
                    <?php $importantDayRecord = activity_important_day_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="celebration-of-important-days">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Celebration of Important Days data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Topic</th>
                                        <th>Venue</th>
                                        <th>Beneficiaries (Male & Female)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="important_day_record[event_date]" value="<?= e((string) ($importantDayRecord['event_date'] ?? '')) ?>" required></td>
                                        <td><input name="important_day_record[topic]" value="<?= e((string) ($importantDayRecord['topic'] ?? '')) ?>" placeholder="Topic" required></td>
                                        <td><input name="important_day_record[venue]" value="<?= e((string) ($importantDayRecord['venue'] ?? '')) ?>" placeholder="Venue" required></td>
                                        <td>
                                            <div class="beneficiary-pair">
                                                <input type="number" min="0" name="important_day_record[beneficiaries_male]" value="<?= e((string) ($importantDayRecord['beneficiaries_male'] ?? '0')) ?>" placeholder="Male">
                                                <input type="number" min="0" name="important_day_record[beneficiaries_female]" value="<?= e((string) ($importantDayRecord['beneficiaries_female'] ?? '0')) ?>" placeholder="Female">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isBeneficiaryEventEntryForm): ?>
                    <?php $beneficiaryEventRecord = activity_beneficiary_event_record_for_form($pdo, $editing, $beneficiaryEventFormSlug); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="<?= e($beneficiaryEventFormSlug) ?>">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend><?= e($beneficiaryEventLabel) ?> data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Date</th>
                                        <th>Particulars</th>
                                        <th>Topic</th>
                                        <th>Location</th>
                                        <th>Beneficiaries (Male & Female)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="beneficiary_event_record[year]" value="<?= e((string) ($beneficiaryEventRecord['year'] ?? '')) ?>" placeholder="2026-27" required></td>
                                        <td><input type="date" name="beneficiary_event_record[event_date]" value="<?= e((string) ($beneficiaryEventRecord['event_date'] ?? '')) ?>" required></td>
                                        <td><input name="beneficiary_event_record[particulars]" value="<?= e((string) ($beneficiaryEventRecord['particulars'] ?? '')) ?>" placeholder="Particulars" required></td>
                                        <td><input name="beneficiary_event_record[topic]" value="<?= e((string) ($beneficiaryEventRecord['topic'] ?? '')) ?>" placeholder="Topic" required></td>
                                        <td><input name="beneficiary_event_record[location]" value="<?= e((string) ($beneficiaryEventRecord['location'] ?? '')) ?>" placeholder="Location" required></td>
                                        <td>
                                            <div class="beneficiary-pair">
                                                <input type="number" min="0" name="beneficiary_event_record[beneficiaries_male]" value="<?= e((string) ($beneficiaryEventRecord['beneficiaries_male'] ?? '0')) ?>" placeholder="Male">
                                                <input type="number" min="0" name="beneficiary_event_record[beneficiaries_female]" value="<?= e((string) ($beneficiaryEventRecord['beneficiaries_female'] ?? '0')) ?>" placeholder="Female">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isSpecialEventEntryForm): ?>
                    <?php $specialEventRecord = activity_special_event_record_for_form($pdo, $editing, $specialEventFormSlug); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="<?= e($specialEventFormSlug) ?>">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend><?= e($specialEventLabel) ?> data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Date From</th>
                                        <th>Date To</th>
                                        <th>Organised</th>
                                        <th><?= e($specialEventTitleLabel) ?></th>
                                        <th>Venue</th>
                                        <th>Collaboration (if any)</th>
                                        <th>No. of visitors (Male, Female)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="special_event_record[year]" value="<?= e((string) ($specialEventRecord['year'] ?? '')) ?>" placeholder="2026-27" required></td>
                                        <td><input type="date" name="special_event_record[date_from]" value="<?= e((string) ($specialEventRecord['date_from'] ?? '')) ?>" required></td>
                                        <td><input type="date" name="special_event_record[date_to]" value="<?= e((string) ($specialEventRecord['date_to'] ?? '')) ?>" required></td>
                                        <td><input name="special_event_record[organised_by]" value="<?= e((string) ($specialEventRecord['organised_by'] ?? '')) ?>" placeholder="Organised by" required></td>
                                        <td><input name="special_event_record[event_title]" value="<?= e((string) ($specialEventRecord['event_title'] ?? '')) ?>" placeholder="<?= e($specialEventTitleLabel) ?>" required></td>
                                        <td><input name="special_event_record[venue]" value="<?= e((string) ($specialEventRecord['venue'] ?? '')) ?>" placeholder="Venue" required></td>
                                        <td><input name="special_event_record[collaboration]" value="<?= e((string) ($specialEventRecord['collaboration'] ?? '')) ?>" placeholder="Collaboration, if any"></td>
                                        <td>
                                            <div class="beneficiary-pair">
                                                <input type="number" min="0" name="special_event_record[visitors_male]" value="<?= e((string) ($specialEventRecord['visitors_male'] ?? '0')) ?>" placeholder="Male">
                                                <input type="number" min="0" name="special_event_record[visitors_female]" value="<?= e((string) ($specialEventRecord['visitors_female'] ?? '0')) ?>" placeholder="Female">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isExhibitionEntryForm): ?>
                    <?php $exhibitionRecord = activity_exhibition_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="exhibition">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Exhibition data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Date From</th>
                                        <th>Date To</th>
                                        <th>Organised</th>
                                        <th>Title of the Exhibition</th>
                                        <th>Venue</th>
                                        <th>Collaboration (if any)</th>
                                        <th>No. of visitors (Male, Female)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="exhibition_record[year]" value="<?= e((string) ($exhibitionRecord['year'] ?? '')) ?>" placeholder="2026-27" required></td>
                                        <td><input type="date" name="exhibition_record[date_from]" value="<?= e((string) ($exhibitionRecord['date_from'] ?? '')) ?>" required></td>
                                        <td><input type="date" name="exhibition_record[date_to]" value="<?= e((string) ($exhibitionRecord['date_to'] ?? '')) ?>" required></td>
                                        <td><input name="exhibition_record[organised_by]" value="<?= e((string) ($exhibitionRecord['organised_by'] ?? '')) ?>" placeholder="Organised by" required></td>
                                        <td><input name="exhibition_record[exhibition_title]" value="<?= e((string) ($exhibitionRecord['exhibition_title'] ?? '')) ?>" placeholder="Title of the Exhibition" required></td>
                                        <td><input name="exhibition_record[venue]" value="<?= e((string) ($exhibitionRecord['venue'] ?? '')) ?>" placeholder="Venue" required></td>
                                        <td><input name="exhibition_record[collaboration]" value="<?= e((string) ($exhibitionRecord['collaboration'] ?? '')) ?>" placeholder="Collaboration, if any"></td>
                                        <td>
                                            <div class="beneficiary-pair">
                                                <input type="number" min="0" name="exhibition_record[visitors_male]" value="<?= e((string) ($exhibitionRecord['visitors_male'] ?? '0')) ?>" placeholder="Male">
                                                <input type="number" min="0" name="exhibition_record[visitors_female]" value="<?= e((string) ($exhibitionRecord['visitors_female'] ?? '0')) ?>" placeholder="Female">
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>
                <?php elseif ($isAwarenessEntryForm): ?>
                    <?php $awarenessRecord = activity_awareness_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="awareness-programme-camps">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Awareness Programme data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">Year</th>
                                        <th rowspan="2">Date From</th>
                                        <th rowspan="2">Date To</th>
                                        <th rowspan="2">Thematic Area</th>
                                        <th rowspan="2">Programme Title</th>
                                        <th colspan="2">Farmers</th>
                                        <th colspan="2">RY</th>
                                        <th colspan="2">Officials</th>
                                    </tr>
                                    <tr>
                                        <th>Male</th>
                                        <th>Female</th>
                                        <th>Male</th>
                                        <th>Female</th>
                                        <th>Male</th>
                                        <th>Female</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="awareness_record[year]" value="<?= e($awarenessRecord['year']) ?>" placeholder="2026" required></td>
                                        <td><input type="date" name="awareness_record[date_from]" value="<?= e($awarenessRecord['date_from']) ?>" required></td>
                                        <td><input type="date" name="awareness_record[date_to]" value="<?= e($awarenessRecord['date_to']) ?>" required></td>
                                        <td><input name="awareness_record[thematic_area]" value="<?= e($awarenessRecord['thematic_area']) ?>" placeholder="Thematic area" required></td>
                                        <td><textarea name="awareness_record[programme_title]" rows="3" placeholder="Programme title" required><?= e($awarenessRecord['programme_title']) ?></textarea></td>
                                        <td><input type="number" min="0" name="awareness_record[farmers_male]" value="<?= e($awarenessRecord['farmers_male']) ?>" placeholder="0"></td>
                                        <td><input type="number" min="0" name="awareness_record[farmers_female]" value="<?= e($awarenessRecord['farmers_female']) ?>" placeholder="0"></td>
                                        <td><input type="number" min="0" name="awareness_record[ry_male]" value="<?= e($awarenessRecord['ry_male']) ?>" placeholder="0"></td>
                                        <td><input type="number" min="0" name="awareness_record[ry_female]" value="<?= e($awarenessRecord['ry_female']) ?>" placeholder="0"></td>
                                        <td><input type="number" min="0" name="awareness_record[officials_male]" value="<?= e($awarenessRecord['officials_male']) ?>" placeholder="0"></td>
                                        <td><input type="number" min="0" name="awareness_record[officials_female]" value="<?= e($awarenessRecord['officials_female']) ?>" placeholder="0"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="Awareness entry status">
                            <span>Saved to Awareness Programme records</span>
                            <span>Participants split by Male and Female</span>
                            <span>Farmers, RY, and Officials tracked separately</span>
                        </div>
                    </fieldset>
                <?php elseif ($isTechnologyShowcasingEntryForm): ?>
                    <?php $technologyRecord = activity_technology_showcasing_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="technology-showcasing">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Technology Showcasing data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Crop</th>
                                        <th>Variety</th>
                                        <th>No of Demonstration</th>
                                        <th>No of farmers involved</th>
                                        <th>Area Covered (HA)</th>
                                        <th>Production (In tonnes)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="technology_showcasing_record[year]" value="<?= e($technologyRecord['year']) ?>" placeholder="2026" required></td>
                                        <td><input name="technology_showcasing_record[crop]" value="<?= e($technologyRecord['crop']) ?>" placeholder="Crop" required></td>
                                        <td><input name="technology_showcasing_record[variety]" value="<?= e($technologyRecord['variety']) ?>" placeholder="Variety" required></td>
                                        <td><input type="number" min="0" name="technology_showcasing_record[farmers_involved]" value="<?= e((string) $technologyRecord['farmers_involved']) ?>" placeholder="0" required></td>
                                        <td><input name="technology_showcasing_record[area_covered]" value="<?= e($technologyRecord['area_covered']) ?>" placeholder="Area covered in HA" required></td>
                                        <td><input type="number" min="0" step="0.001" name="technology_showcasing_record[production_tonnes]" value="<?= e((string) $technologyRecord['production_tonnes']) ?>" placeholder="0.000" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="Technology Showcasing entry status">
                            <span>Saved to Technology Showcasing database records</span>
                            <span>Linked with this activity card</span>
                            <span>Stores crop, variety, area, farmers, and production</span>
                        </div>
                    </fieldset>
                <?php elseif ($isClusterDemonstrationEntryForm): ?>
                    <?php $clusterRecord = activity_cluster_demonstration_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="cluster-demonstration">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Cluster Demonstration data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Crop</th>
                                        <th>Variety</th>
                                        <th>No of Demonstration</th>
                                        <th>No of farmers involved</th>
                                        <th>Area Covered (HA)</th>
                                        <th>Production (In tonnes)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="cluster_demonstration_record[year]" value="<?= e($clusterRecord['year']) ?>" placeholder="2026" required></td>
                                        <td><input name="cluster_demonstration_record[crop]" value="<?= e($clusterRecord['crop']) ?>" placeholder="Crop" required></td>
                                        <td><input name="cluster_demonstration_record[variety]" value="<?= e($clusterRecord['variety']) ?>" placeholder="Variety" required></td>
                                        <td><input type="number" min="0" name="cluster_demonstration_record[demonstration_count]" value="<?= e((string) $clusterRecord['demonstration_count']) ?>" placeholder="0" required></td>
                                        <td><input type="number" min="0" name="cluster_demonstration_record[farmers_involved]" value="<?= e((string) $clusterRecord['farmers_involved']) ?>" placeholder="0" required></td>
                                        <td><input name="cluster_demonstration_record[area_covered]" value="<?= e($clusterRecord['area_covered']) ?>" placeholder="Area covered in HA" required></td>
                                        <td><input type="number" min="0" step="0.001" name="cluster_demonstration_record[production_tonnes]" value="<?= e((string) $clusterRecord['production_tonnes']) ?>" placeholder="0.000" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="Cluster Demonstration entry status">
                            <span>Saved to Cluster Demonstration database records</span>
                            <span>Linked with this activity card</span>
                            <span>Stores crop, variety, area, farmers, and production</span>
                        </div>
                    </fieldset>
                <?php elseif ($isSeedHubEntryForm): ?>
                    <?php $seedHubRecord = activity_seed_hub_record_for_form($pdo, $editing); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="seed-hub">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Seed Hub data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Crop</th>
                                        <th>Variety</th>
                                        <th>Area (Ha)</th>
                                        <th>Production (Q)</th>
                                        <th>Reasons for shortfall, if any</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="seed_hub_record[year]" value="<?= e($seedHubRecord['year']) ?>" placeholder="2026" required></td>
                                        <td><input name="seed_hub_record[crop]" value="<?= e($seedHubRecord['crop']) ?>" placeholder="Crop" required></td>
                                        <td><input name="seed_hub_record[variety]" value="<?= e($seedHubRecord['variety']) ?>" placeholder="Variety" required></td>
                                        <td><input name="seed_hub_record[area_ha]" value="<?= e($seedHubRecord['area_ha']) ?>" placeholder="Area in Ha" required></td>
                                        <td><input type="number" min="0" step="0.001" name="seed_hub_record[production_q]" value="<?= e((string) $seedHubRecord['production_q']) ?>" placeholder="0.000" required></td>
                                        <td><textarea name="seed_hub_record[shortfall_reason]" rows="2" placeholder="Write reason if any"><?= e($seedHubRecord['shortfall_reason']) ?></textarea></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="Seed Hub entry status">
                            <span>Saved to Seed Hub database records</span>
                            <span>Linked with this activity card</span>
                            <span>Stores year, crop, variety, area, production, and shortfall reason</span>
                        </div>
                    </fieldset>
                <?php elseif ($isPlantingMaterialEntryForm): ?>
                    <?php $plantingRecord = activity_planting_material_record_for_form($pdo, $editing); ?>
                    <?php $plantingCommodities = planting_material_commodities($pdo); ?>
                    <?php $plantingCommodityEditing = planting_material_commodity_find($pdo, (int) ($_GET['commodity_edit'] ?? 0)); ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="planting-materials">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>Details of Planting Materials/cuttings/seeds/poultry/fingerlings etc</legend>
                        <div class="planting-tabs" data-planting-tabs>
                            <button class="is-active" type="button" data-planting-tab-button="register">1. Register Commodity</button>
                            <button type="button" data-planting-tab-button="produce">2. Produce</button>
                            <button type="button" data-planting-tab-button="sold">3. Sold</button>
                            <button type="button" data-planting-tab-button="abstract">4. Abstract (Profit &amp; Loss Statement)</button>
                        </div>
                        <div class="planting-tab-panel is-active" data-planting-tab-panel="register">
                            <div class="oft-entry-table-wrap">
                                <table class="oft-entry-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Commodity Name</th>
                                            <th>Rate</th>
                                            <th>Unit Produce</th>
                                            <th>Photo</th>
                                            <th>Short Note</th>
                                            <th>Save</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td><input form="planting-commodity-register-form" type="date" name="commodity_date" value="<?= e((string) ($plantingCommodityEditing['register_date'] ?? '')) ?>" required></td>
                                            <td><input form="planting-commodity-register-form" name="commodity_name" value="<?= e((string) ($plantingCommodityEditing['commodity_name'] ?? '')) ?>" placeholder="Commodity name" required></td>
                                            <td><input form="planting-commodity-register-form" type="number" min="0" step="0.01" name="commodity_rate" value="<?= e((string) ($plantingCommodityEditing['rate'] ?? '')) ?>" placeholder="0.00" required></td>
                                            <td><input form="planting-commodity-register-form" name="commodity_unit" value="<?= e((string) ($plantingCommodityEditing['unit'] ?? '')) ?>" placeholder="Q/kg/nos. etc." required></td>
                                            <td>
                                                <input form="planting-commodity-register-form" type="file" name="commodity_photos[]" accept="image/*" multiple>
                                                <?php if ($plantingCommodityEditing && planting_material_photo_list($plantingCommodityEditing)): ?>
                                                    <span class="muted">Current photos saved. Upload new photos to replace them.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><textarea form="planting-commodity-register-form" name="commodity_note" rows="2" placeholder="Short note on product"><?= e((string) ($plantingCommodityEditing['product_note'] ?? '')) ?></textarea></td>
                                            <td>
                                                <button class="btn btn--primary" form="planting-commodity-register-form" type="submit"><?= $plantingCommodityEditing ? 'Update Commodity' : 'Save Commodity' ?></button>
                                                <?php if ($plantingCommodityEditing): ?>
                                                    <a class="btn btn--light" href="user-manage.php?module=activities&desk=mandated&activity_type=planting-materials">Cancel</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-wrap commodity-register-dialog__table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Commodity</th>
                                            <th>Rate</th>
                                            <th>Unit Produce</th>
                                            <th>Photo</th>
                                            <th>Short Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($plantingCommodities as $commodity): ?>
                                            <tr>
                                                <td><?= e((string) ($commodity['register_date'] ?? '')) ?></td>
                                                <td><?= e((string) $commodity['commodity_name']) ?></td>
                                                <td>Rs. <?= e((string) $commodity['rate']) ?></td>
                                                <td><?= e((string) ($commodity['unit'] ?? '-')) ?></td>
                                                <td>
                                                    <?php $commodityPhotos = planting_material_photo_list($commodity); ?>
                                                    <?php if ($commodityPhotos): ?>
                                                        <?php foreach ($commodityPhotos as $commodityPhoto): ?>
                                                            <img src="../<?= e($commodityPhoto) ?>" alt="" style="width:54px;height:42px;object-fit:cover;border-radius:6px;margin:2px;">
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="muted">No photo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= e(excerpt((string) ($commodity['product_note'] ?? ''), 120)) ?></td>
                                                <td class="table-actions">
                                                    <a href="user-manage.php?module=activities&desk=mandated&activity_type=planting-materials&commodity_edit=<?= e((string) $commodity['id']) ?>">Edit</a>
                                                    <a href="user-manage.php?module=activities&desk=mandated&activity_type=planting-materials&commodity_delete=<?= e((string) $commodity['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this commodity?')">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$plantingCommodities): ?>
                                            <tr><td colspan="7"><span class="muted">No commodities registered yet.</span></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="planting-tab-panel" data-planting-tab-panel="produce">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Commodity</th>
                                        <th>Source (KVK Farm/TS etc.)</th>
                                        <th>Produce (In Unit as mentioned in Register)</th>
                                        <th>Value of Commodity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="planting_material_record[produce_date]" value="<?= e((string) ($plantingRecord['produce_date'] ?? '')) ?>"></td>
                                        <td>
                                            <select name="planting_material_record[commodity_id]" data-planting-commodity>
                                                <option value="">Select commodity</option>
                                                <?php foreach ($plantingCommodities as $commodity): ?>
                                                    <option value="<?= e((string) $commodity['id']) ?>" data-rate="<?= e((string) $commodity['rate']) ?>" <?= (string) ($plantingRecord['commodity_id'] ?? '') === (string) $commodity['id'] ? 'selected' : '' ?>>
                                                        <?= e((string) $commodity['commodity_name']) ?><?= trim((string) ($commodity['unit'] ?? '')) !== '' ? ' (' . e((string) $commodity['unit']) . ')' : '' ?> - Rs. <?= e((string) $commodity['rate']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input name="planting_material_record[source]" value="<?= e((string) ($plantingRecord['source'] ?? '')) ?>" placeholder="KVK Farm/TS etc."></td>
                                        <td><input type="number" min="0" step="0.001" name="planting_material_record[quantity_produced]" value="<?= e((string) ($plantingRecord['quantity_produced'] ?? '')) ?>" placeholder="0.000" data-planting-quantity-produced></td>
                                        <td><input type="number" min="0" step="0.01" name="planting_material_record[value_of_commodity]" value="<?= e((string) ($plantingRecord['value_of_commodity'] ?? '')) ?>" placeholder="0.00" data-planting-value readonly></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="table-wrap commodity-register-dialog__table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Commodity</th>
                                            <th>Source</th>
                                            <th>Produce</th>
                                            <th>Value of Commodity</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $plantingProduceTabCount = 0; ?>
                                        <?php foreach ($rows as $produceTabRow): ?>
                                            <?php $plantingProduceTab = activity_planting_material_record_for_form($pdo, $produceTabRow); ?>
                                            <?php if ((float) ($plantingProduceTab['quantity_produced'] ?? 0) > 0): ?>
                                                <?php $plantingProduceTabCount++; ?>
                                                <tr>
                                                    <td><?= e((string) ($plantingProduceTab['produce_date'] ?? '')) ?></td>
                                                    <td><?= e(excerpt((string) ($plantingProduceTab['commodity_name'] ?? ''), 100)) ?></td>
                                                    <td><?= e(excerpt((string) ($plantingProduceTab['source'] ?? ''), 100)) ?></td>
                                                    <td><?= e((string) ($plantingProduceTab['quantity_produced'] ?? '0')) ?></td>
                                                    <td><?= e((string) ($plantingProduceTab['value_of_commodity'] ?? '0.00')) ?></td>
                                                    <td class="table-actions">
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&edit=<?= e((string) $produceTabRow['id']) ?>">Edit</a>
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&delete=<?= e((string) $produceTabRow['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this record?')">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if ($plantingProduceTabCount === 0): ?>
                                            <tr><td colspan="6"><span class="muted">No produce records saved yet.</span></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="planting-tab-panel" data-planting-tab-panel="sold">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Commodity</th>
                                        <th>Quantity Sold</th>
                                        <th>Quantity Distributed</th>
                                        <th>Revenue Earned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="date" name="planting_material_record[sold_date]" value="<?= e((string) ($plantingRecord['sold_date'] ?? '')) ?>"></td>
                                        <td>
                                            <select name="planting_material_sold_commodity_id" data-planting-sold-commodity>
                                                <option value="">Select commodity</option>
                                                <?php foreach ($plantingCommodities as $commodity): ?>
                                                    <option value="<?= e((string) $commodity['id']) ?>" data-rate="<?= e((string) $commodity['rate']) ?>" <?= (string) ($plantingRecord['commodity_id'] ?? '') === (string) $commodity['id'] ? 'selected' : '' ?>>
                                                        <?= e((string) $commodity['commodity_name']) ?><?= trim((string) ($commodity['unit'] ?? '')) !== '' ? ' (' . e((string) $commodity['unit']) . ')' : '' ?> - Rs. <?= e((string) $commodity['rate']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" min="0" step="0.001" name="planting_material_record[quantity_sold]" value="<?= e((string) ($plantingRecord['quantity_sold'] ?? '')) ?>" placeholder="0.000" data-planting-quantity-sold></td>
                                        <td><input type="number" min="0" step="0.001" name="planting_material_record[quantity_distributed]" value="<?= e((string) ($plantingRecord['quantity_distributed'] ?? '')) ?>" placeholder="0.000" data-planting-quantity-distributed></td>
                                        <td><input type="number" min="0" step="0.01" name="planting_material_record[revenue_generated]" value="<?= e((string) ($plantingRecord['revenue_generated'] ?? '')) ?>" placeholder="0.00" data-planting-revenue readonly></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="table-wrap commodity-register-dialog__table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Commodity</th>
                                            <th>Quantity Sold</th>
                                            <th>Quantity Distributed</th>
                                            <th>Revenue Earned</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $plantingSoldTabCount = 0; ?>
                                        <?php foreach ($rows as $soldTabRow): ?>
                                            <?php $plantingSoldTab = activity_planting_material_record_for_form($pdo, $soldTabRow); ?>
                                            <?php if ((float) ($plantingSoldTab['quantity_sold'] ?? 0) > 0 || (float) ($plantingSoldTab['quantity_distributed'] ?? 0) > 0): ?>
                                                <?php $plantingSoldTabCount++; ?>
                                                <tr>
                                                    <td><?= e((string) ($plantingSoldTab['sold_date'] ?? '')) ?></td>
                                                    <td><?= e(excerpt((string) ($plantingSoldTab['commodity_name'] ?? ''), 100)) ?></td>
                                                    <td><?= e((string) ($plantingSoldTab['quantity_sold'] ?? '0')) ?></td>
                                                    <td><?= e((string) ($plantingSoldTab['quantity_distributed'] ?? '0')) ?></td>
                                                    <td><?= e((string) ($plantingSoldTab['revenue_generated'] ?? '0.00')) ?></td>
                                                    <td class="table-actions">
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&edit=<?= e((string) $soldTabRow['id']) ?>">Edit</a>
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&delete=<?= e((string) $soldTabRow['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this record?')">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php if ($plantingSoldTabCount === 0): ?>
                                            <tr><td colspan="6"><span class="muted">No sold/distributed records saved yet.</span></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="planting-tab-panel" data-planting-tab-panel="abstract">
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Commodity</th>
                                            <th>Source</th>
                                            <th>Quantity Produced</th>
                                            <th>Value of Commodity</th>
                                            <th>Quantity Sold</th>
                                            <th>Quantity Distributed</th>
                                            <th>Revenue Generated (Rs.)</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $plantingAbstractRows = [];
                                            foreach ($rows as $abstractRow) {
                                                $plantingAbstract = activity_planting_material_record_for_form($pdo, $abstractRow);
                                                $commodityName = trim((string) ($plantingAbstract['commodity_name'] ?? ''));
                                                if ($commodityName === '') {
                                                    continue;
                                                }
                                                $dateForYear = (string) (($plantingAbstract['produce_date'] ?? '') ?: ($plantingAbstract['sold_date'] ?? '') ?: ($abstractRow['created_at'] ?? ''));
                                                $financialYear = $dateForYear !== '' ? current_financial_year($dateForYear) : 'Year not specified';
                                                $commodityKey = (string) (($plantingAbstract['commodity_id'] ?? '') ?: strtolower($commodityName));
                                                $abstractKey = $commodityKey . '|' . $financialYear;
                                                if (!isset($plantingAbstractRows[$abstractKey])) {
                                                    $plantingAbstractRows[$abstractKey] = [
                                                        'date' => [],
                                                        'financial_year' => $financialYear,
                                                        'commodity' => $commodityName,
                                                        'source' => [],
                                                        'quantity_produced' => 0.0,
                                                        'value_of_commodity' => 0.0,
                                                        'quantity_sold' => 0.0,
                                                        'quantity_distributed' => 0.0,
                                                        'revenue_generated' => 0.0,
                                                        'actions' => [],
                                                    ];
                                                }
                                                foreach (['produce_date', 'sold_date'] as $dateField) {
                                                    $dateValue = trim((string) ($plantingAbstract[$dateField] ?? ''));
                                                    if ($dateValue !== '') {
                                                        $plantingAbstractRows[$abstractKey]['date'][$dateValue] = true;
                                                    }
                                                }
                                                $sourceValue = trim((string) ($plantingAbstract['source'] ?? ''));
                                                if ($sourceValue !== '') {
                                                    $plantingAbstractRows[$abstractKey]['source'][$sourceValue] = true;
                                                }
                                                $plantingAbstractRows[$abstractKey]['quantity_produced'] += (float) ($plantingAbstract['quantity_produced'] ?? 0);
                                                $plantingAbstractRows[$abstractKey]['value_of_commodity'] += (float) ($plantingAbstract['value_of_commodity'] ?? 0);
                                                $plantingAbstractRows[$abstractKey]['quantity_sold'] += (float) ($plantingAbstract['quantity_sold'] ?? 0);
                                                $plantingAbstractRows[$abstractKey]['quantity_distributed'] += (float) ($plantingAbstract['quantity_distributed'] ?? 0);
                                                $plantingAbstractRows[$abstractKey]['revenue_generated'] += (float) ($plantingAbstract['revenue_generated'] ?? 0);
                                                $plantingAbstractRows[$abstractKey]['actions'][] = $abstractRow;
                                            }
                                            uasort($plantingAbstractRows, static function (array $a, array $b): int {
                                                preg_match('/\d{4}/', (string) $a['financial_year'], $aMatch);
                                                preg_match('/\d{4}/', (string) $b['financial_year'], $bMatch);
                                                $yearCompare = ((int) ($bMatch[0] ?? 0)) <=> ((int) ($aMatch[0] ?? 0));
                                                return $yearCompare ?: strcmp((string) $a['commodity'], (string) $b['commodity']);
                                            });
                                        ?>
                                        <?php foreach ($plantingAbstractRows as $plantingAbstract): ?>
                                            <tr>
                                                <td><?= e($plantingAbstract['date'] ? implode(', ', array_keys($plantingAbstract['date'])) : '-') ?></td>
                                                <td><?= e(excerpt((string) $plantingAbstract['commodity'], 100)) ?></td>
                                                <td><?= e($plantingAbstract['source'] ? excerpt(implode(', ', array_keys($plantingAbstract['source'])), 100) : '-') ?></td>
                                                <td><?= e((string) $plantingAbstract['quantity_produced']) ?></td>
                                                <td><?= e(number_format((float) $plantingAbstract['value_of_commodity'], 2, '.', '')) ?></td>
                                                <td><?= e((string) $plantingAbstract['quantity_sold']) ?></td>
                                                <td><?= e((string) $plantingAbstract['quantity_distributed']) ?></td>
                                                <td><?= e(number_format((float) $plantingAbstract['revenue_generated'], 2, '.', '')) ?></td>
                                                <td class="table-actions">
                                                    <?php foreach ($plantingAbstract['actions'] as $actionRow): ?>
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&edit=<?= e((string) $actionRow['id']) ?>">Edit</a>
                                                        <a href="user-manage.php?module=activities&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>&delete=<?= e((string) $actionRow['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this record?')">Delete</a>
                                                    <?php endforeach; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$plantingAbstractRows): ?>
                                            <tr><td colspan="9"><span class="muted">No abstract records saved yet.</span></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </fieldset>
                <?php elseif ($isFldEntryForm): ?>
                    <?php $fldRecord = activity_fld_record_for_form($pdo, $editing); ?>
                    <?php $fldPhotoRecord = fld_record_from_database($pdo, (int) ($editing['id'] ?? 0)) ?: []; ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="fld">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>FLD data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Crop</th>
                                        <th>Discipline</th>
                                        <th>Thematic Area</th>
                                        <th>Technology Demonstrated</th>
                                        <th>Season</th>
                                        <th>Area Covered (Ha/Nos)</th>
                                        <th>Location</th>
                                        <th>No of Farmers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="fld_record[year]" value="<?= e($fldRecord['year']) ?>" placeholder="2026" required></td>
                                        <td><input name="fld_record[crop]" value="<?= e($fldRecord['crop']) ?>" placeholder="Crop" required></td>
                                        <td>
                                            <select name="fld_record[discipline]" required>
                                                <option value="">Select discipline</option>
                                                <?php foreach ($staffExpertiseOptions as $expertise): ?>
                                                    <option value="<?= e($expertise) ?>" <?= $fldRecord['discipline'] === $expertise ? 'selected' : '' ?>><?= e($expertise) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($fldRecord['discipline'] !== '' && !in_array($fldRecord['discipline'], $staffExpertiseOptions, true)): ?>
                                                    <option value="<?= e($fldRecord['discipline']) ?>" selected><?= e($fldRecord['discipline']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td><input name="fld_record[thematic_area]" value="<?= e($fldRecord['thematic_area']) ?>" placeholder="Thematic area" required></td>
                                        <td><textarea name="fld_record[technology_tested]" rows="3" placeholder="Technology demonstrated" required><?= e($fldRecord['technology_tested']) ?></textarea></td>
                                        <td>
                                            <select name="fld_record[season]" required>
                                                <option value="">Select season</option>
                                                <?php foreach (['Kharif', 'Rabi', 'Summer', 'Winter', 'Pre-Kharif', 'Year Round'] as $seasonOption): ?>
                                                    <option value="<?= e($seasonOption) ?>" <?= $fldRecord['season'] === $seasonOption ? 'selected' : '' ?>><?= e($seasonOption) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($fldRecord['season'] !== '' && !in_array($fldRecord['season'], ['Kharif', 'Rabi', 'Summer', 'Winter', 'Pre-Kharif', 'Year Round'], true)): ?>
                                                    <option value="<?= e($fldRecord['season']) ?>" selected><?= e($fldRecord['season']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td><input name="fld_record[area_covered]" value="<?= e($fldRecord['area_covered']) ?>" placeholder="Ha / Nos." required></td>
                                        <td><input name="fld_record[location]" value="<?= e($fldRecord['location']) ?>" placeholder="Village / block / location" required></td>
                                        <td><input type="number" min="0" name="fld_record[number_of_farmers]" value="<?= e($fldRecord['number_of_farmers']) ?>" placeholder="0" required></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="FLD entry status">
                            <span>Saved to FLD database records</span>
                            <span>Visible on public FLD page</span>
                            <span>Upload up to 5 FLD photos</span>
                        </div>
                        <div class="training-record__photos oft-record__photos">
                            <p>FLD photographs</p>
                            <div>
                                <?php for ($photoIndex = 1; $photoIndex <= 5; $photoIndex++): ?>
                                    <?php $photoField = $photoIndex === 1 ? 'photograph_path' : 'photograph_path_' . $photoIndex; ?>
                                    <label>Photo <?= e((string) $photoIndex) ?>
                                        <input type="file" name="fld_photo_<?= e((string) $photoIndex) ?>" accept="image/*">
                                        <?php if (!empty($fldPhotoRecord[$photoField])): ?>
                                            <small>Current: <?= e((string) $fldPhotoRecord[$photoField]) ?></small>
                                        <?php endif; ?>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </fieldset>
                <?php elseif ($isOftEntryForm): ?>
                    <?php $oftRecord = activity_oft_record_for_form($pdo, $editing); ?>
                    <?php $oftPhotoRecord = oft_record_from_database($pdo, (int) ($editing['id'] ?? 0)) ?: []; ?>
                    <input type="hidden" name="activity_desk" value="mandated">
                    <input type="hidden" name="activity_mandated_type" value="oft">
                    <fieldset class="training-record oft-record oft-record--entry">
                        <legend>OFT data record</legend>
                        <div class="oft-entry-table-wrap">
                            <table class="oft-entry-table">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Crop</th>
                                        <th>Discipline</th>
                                        <th>Thematic Area</th>
                                        <th>Title of OFT</th>
                                        <th>Location</th>
                                        <th>Season</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input name="oft_record[year]" value="<?= e($oftRecord['year']) ?>" placeholder="2026" required></td>
                                        <td>
                                            <select name="oft_record[crop]" required>
                                                <option value="">Select crop</option>
                                                <?php foreach ($oftCropOptions as $cropOption): ?>
                                                    <option value="<?= e((string) $cropOption) ?>" <?= $oftRecord['crop'] === (string) $cropOption ? 'selected' : '' ?>><?= e((string) $cropOption) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($oftRecord['crop'] !== '' && !in_array($oftRecord['crop'], array_map('strval', $oftCropOptions), true)): ?>
                                                    <option value="<?= e($oftRecord['crop']) ?>" selected><?= e($oftRecord['crop']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="oft_record[discipline]" required>
                                                <option value="">Select discipline</option>
                                                <?php foreach ($staffExpertiseOptions as $expertise): ?>
                                                    <option value="<?= e($expertise) ?>" <?= $oftRecord['discipline'] === $expertise ? 'selected' : '' ?>><?= e($expertise) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($oftRecord['discipline'] !== '' && !in_array($oftRecord['discipline'], $staffExpertiseOptions, true)): ?>
                                                    <option value="<?= e($oftRecord['discipline']) ?>" selected><?= e($oftRecord['discipline']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td><input name="oft_record[thematic_area]" value="<?= e($oftRecord['thematic_area']) ?>" placeholder="Thematic area" required></td>
                                        <td><textarea name="oft_record[oft_title]" rows="3" placeholder="Title of OFT" required><?= e($oftRecord['oft_title']) ?></textarea></td>
                                        <td><input name="oft_record[location]" value="<?= e($oftRecord['location']) ?>" placeholder="Village / block / location" required></td>
                                        <td>
                                            <select name="oft_record[season]" required>
                                                <option value="">Select season</option>
                                                <?php foreach (['Kharif', 'Rabi', 'Summer', 'Winter', 'Pre-Kharif', 'Year Round'] as $seasonOption): ?>
                                                    <option value="<?= e($seasonOption) ?>" <?= $oftRecord['season'] === $seasonOption ? 'selected' : '' ?>><?= e($seasonOption) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($oftRecord['season'] !== '' && !in_array($oftRecord['season'], ['Kharif', 'Rabi', 'Summer', 'Winter', 'Pre-Kharif', 'Year Round'], true)): ?>
                                                    <option value="<?= e($oftRecord['season']) ?>" selected><?= e($oftRecord['season']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Result of OFT</th>
                                        <td colspan="6">
                                            <textarea name="oft_record[results_summary]" rows="4" placeholder="Result can be entered now. File upload can be added later."><?= e($oftRecord['results_summary']) ?></textarea>
                                            <small>Result upload can be added later.</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="oft-entry-actions" aria-label="OFT entry status">
                            <span>Saved to OFT database records</span>
                            <span>Visible on public OFT page</span>
                            <span>Upload up to 5 OFT photos</span>
                        </div>
                        <div class="training-record__photos oft-record__photos">
                            <p>OFT photographs</p>
                            <div>
                                <?php for ($photoIndex = 1; $photoIndex <= 5; $photoIndex++): ?>
                                    <?php $photoField = $photoIndex === 1 ? 'photograph_path' : 'photograph_path_' . $photoIndex; ?>
                                    <label>Photo <?= e((string) $photoIndex) ?>
                                        <input type="file" name="oft_photo_<?= e((string) $photoIndex) ?>" accept="image/*">
                                        <?php if (!empty($oftPhotoRecord[$photoField])): ?>
                                            <small>Current: <?= e((string) $oftPhotoRecord[$photoField]) ?></small>
                                        <?php endif; ?>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </fieldset>
                <?php else: ?>
                <?php foreach ($formFields as $field): ?>
                    <?php if (($isTrainingEntryForm || $isOftEntryForm || $isFldEntryForm || $isAwarenessEntryForm || $isExhibitionEntryForm || $isSpecialEventEntryForm || $isBeneficiaryEventEntryForm || $isImportantDayEntryForm || $isShgGosthiEntryForm || $isLectureEntryForm || $isRadioTalkEntryForm || $isTvTalkEntryForm || $isTechnologyShowcasingEntryForm || $isClusterDemonstrationEntryForm) && in_array($field, ['title', 'summary', 'image_path'], true)): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php if ($key === 'staff_heads' && $field === 'duration'): ?>
                        <?php [$durationFrom, $durationTo] = staff_head_duration_parts((string) ($editing[$field] ?? '')); ?>
                        <fieldset class="duration-pair">
                            <legend>Duration</legend>
                            <div class="duration-pair__fields">
                                <label>From
                                    <input name="duration_from" value="<?= e($durationFrom) ?>" placeholder="From year/date">
                                </label>
                                <label>To
                                    <input name="duration_to" value="<?= e($durationTo) ?>" placeholder="To year/date">
                                </label>
                            </div>
                        </fieldset>
                    <?php elseif ($key === 'activities' && $field === 'slug'): ?>
                        <?php
                            $currentSlug = (string) ($editing[$field] ?? '');
                            $canonicalSlug = activity_canonical_slug($currentSlug);
                            $isMandatedActivity = $editing ? activity_is_mandated_slug($currentSlug) : $activityDesk === 'mandated';
                            $selectedMandated = $isMandatedActivity && $canonicalSlug !== '' ? $canonicalSlug : 'oft';
                        ?>
                        <input type="hidden" name="activity_desk" value="<?= $activityDesk === 'other' ? 'other' : 'mandated' ?>">
                        <input type="hidden" name="activity_mandated_type" value="<?= e($editing ? ($selectedMandated ?: 'oft') : $activityMandatedType) ?>">
                        <?php if ($activityDesk === 'other'): ?>
                            <label>Other activity slug
                                <input name="slug" value="<?= e($currentSlug) ?>" placeholder="Example: arya">
                            </label>
                        <?php endif; ?>
                    <?php else: ?>
                    <?php if ($key === 'activities' && in_array($field, ['title', 'summary'], true)): ?>
                        <label class="activity-general-field activity-general-field--<?= e($field) ?>"><?= e($field === 'title' ? 'Activity title' : 'Summary') ?>
                    <?php elseif (($isTrainingEntryForm || $isOftEntryForm || $isFldEntryForm || $isAwarenessEntryForm || $isExhibitionEntryForm || $isSpecialEventEntryForm || $isBeneficiaryEventEntryForm || $isImportantDayEntryForm || $isShgGosthiEntryForm || $isLectureEntryForm || $isRadioTalkEntryForm || $isTvTalkEntryForm || $isTechnologyShowcasingEntryForm || $isClusterDemonstrationEntryForm) && $field === 'details'): ?>
                        <label class="training-record-shell">
                    <?php else: ?>
                        <label><?= e($module['labels'][$field] ?? ucwords(str_replace('_', ' ', $field))) ?>
                    <?php endif; ?>
                        <?php if ($key === 'activities' && $field === 'details'): ?>
                            <?php $trainingRecord = activity_training_record_for_form($pdo, $editing); ?>
                            <?php $oftRecord = activity_oft_record_for_form($pdo, $editing); ?>
                            <div class="activity-details-field" data-training="<?= $isTrainingEntryForm ? 'yes' : 'no' ?>" data-oft="<?= $isOftEntryForm ? 'yes' : 'no' ?>">
                                <div class="activity-details-field__general">
                                    <textarea name="<?= e($field) ?>" rows="4"><?= e((string) ($editing[$field] ?? '')) ?></textarea>
                                </div>
                                <fieldset class="training-record oft-record">
                                    <legend>OFT details</legend>
                                    <div class="oft-entry-table-wrap">
                                        <table class="oft-entry-table">
                                            <thead>
                                                <tr>
                                                    <th>Year</th>
                                                    <th>Crop</th>
                                                    <th>Discipline</th>
                                                    <th>Thematic Area</th>
                                                    <th>Title of OFT</th>
                                                    <th>Season</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input name="oft_record[year]" value="<?= e($oftRecord['year']) ?>" placeholder="2026" required></td>
                                                    <td>
                                                        <select name="oft_record[crop]" required>
                                                            <option value="">Select crop</option>
                                                            <?php foreach ($oftCropOptions as $cropOption): ?>
                                                                <option value="<?= e((string) $cropOption) ?>" <?= $oftRecord['crop'] === (string) $cropOption ? 'selected' : '' ?>><?= e((string) $cropOption) ?></option>
                                                            <?php endforeach; ?>
                                                            <?php if ($oftRecord['crop'] !== '' && !in_array($oftRecord['crop'], array_map('strval', $oftCropOptions), true)): ?>
                                                                <option value="<?= e($oftRecord['crop']) ?>" selected><?= e($oftRecord['crop']) ?></option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="oft_record[discipline]" required>
                                                            <option value="">Select discipline</option>
                                                            <?php foreach ($staffExpertiseOptions as $expertise): ?>
                                                                <option value="<?= e($expertise) ?>" <?= $oftRecord['discipline'] === $expertise ? 'selected' : '' ?>><?= e($expertise) ?></option>
                                                            <?php endforeach; ?>
                                                            <?php if ($oftRecord['discipline'] !== '' && !in_array($oftRecord['discipline'], $staffExpertiseOptions, true)): ?>
                                                                <option value="<?= e($oftRecord['discipline']) ?>" selected><?= e($oftRecord['discipline']) ?></option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </td>
                                                    <td><input name="oft_record[thematic_area]" value="<?= e($oftRecord['thematic_area']) ?>" placeholder="Thematic area" required></td>
                                                    <td><textarea name="oft_record[oft_title]" rows="3" placeholder="Title of OFT" required><?= e($oftRecord['oft_title']) ?></textarea></td>
                                                    <td><input name="oft_record[season]" value="<?= e($oftRecord['season']) ?>" placeholder="Kharif / Rabi / Summer" required></td>
                                                </tr>
                                                <tr>
                                                    <th>Result of OFT</th>
                                                    <td colspan="5">
                                                        <textarea name="oft_record[results_summary]" rows="4" placeholder="Result can be entered now. File upload can be added later."><?= e($oftRecord['results_summary']) ?></textarea>
                                                        <small>Result upload can be added later.</small>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </fieldset>
                                <fieldset class="training-record">
                                    <legend>Training data record</legend>
                                    <div class="training-record__grid">
                                        <label>Name of training
                                            <input name="training_record[training_name]" value="<?= e($trainingRecord['training_name']) ?>" placeholder="Enter training name" required>
                                        </label>
                                        <label>Discipline
                                            <select name="training_record[discipline]">
                                                <option value="">Select discipline</option>
                                                <?php foreach ($staffExpertiseOptions as $expertise): ?>
                                                    <option value="<?= e($expertise) ?>" <?= $trainingRecord['discipline'] === $expertise ? 'selected' : '' ?>><?= e($expertise) ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($trainingRecord['discipline'] !== '' && !in_array($trainingRecord['discipline'], $staffExpertiseOptions, true)): ?>
                                                    <option value="<?= e($trainingRecord['discipline']) ?>" selected><?= e($trainingRecord['discipline']) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </label>
                                        <label>Type of Training
                                            <select name="training_record[training_type]" required>
                                                <?php foreach (['Regular', 'Sponsored', 'Vocational'] as $trainingType): ?>
                                                    <option value="<?= e($trainingType) ?>" <?= ($trainingRecord['training_type'] ?? '') === $trainingType ? 'selected' : '' ?>><?= e($trainingType) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Training Area
                                            <input name="training_record[training_area]" value="<?= e($trainingRecord['training_area']) ?>" placeholder="Enter training area" required>
                                        </label>
                                        <label>From Date
                                            <input type="date" name="training_record[duration_from]" value="<?= e(training_record_date_input($trainingRecord['duration_from'] ?? '')) ?>" required>
                                        </label>
                                        <label>To Date
                                            <input type="date" name="training_record[duration_to]" value="<?= e(training_record_date_input($trainingRecord['duration_to'] ?? '')) ?>" required>
                                        </label>
                                    </div>
                                    <div class="training-record__participants">
                                        <p>No of Participants</p>
                                        <div>
                                            <label>Farmers - Man
                                                <input type="number" min="0" name="training_record[farmers_men]" value="<?= e($trainingRecord['farmers_men']) ?>">
                                            </label>
                                            <label>Farmers - Women
                                                <input type="number" min="0" name="training_record[farmers_women]" value="<?= e($trainingRecord['farmers_women']) ?>">
                                            </label>
                                            <label>Rural Youth - Men
                                                <input type="number" min="0" name="training_record[rural_youth_men]" value="<?= e($trainingRecord['rural_youth_men']) ?>">
                                            </label>
                                            <label>Rural Youth - Women
                                                <input type="number" min="0" name="training_record[rural_youth_women]" value="<?= e($trainingRecord['rural_youth_women']) ?>">
                                            </label>
                                            <label>EF - Men
                                                <input type="number" min="0" name="training_record[ef_men]" value="<?= e($trainingRecord['ef_men']) ?>">
                                            </label>
                                            <label>EF - Women
                                                <input type="number" min="0" name="training_record[ef_women]" value="<?= e($trainingRecord['ef_women']) ?>">
                                            </label>
                                        </div>
                                    </div>
                                    <div class="training-record__photos">
                                        <p>Training photographs</p>
                                        <div>
                                            <?php for ($photoIndex = 1; $photoIndex <= 5; $photoIndex++): ?>
                                                <?php $photoField = $photoIndex === 1 ? 'photograph_path' : 'photograph_path_' . $photoIndex; ?>
                                                <label>Photo <?= e((string) $photoIndex) ?>
                                                    <input type="file" name="training_photo_<?= e((string) $photoIndex) ?>" accept="image/*">
                                                    <?php if (!empty($trainingRecord[$photoField])): ?>
                                                        <small>Current: <?= e((string) $trainingRecord[$photoField]) ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        <?php elseif ($key === 'activity_totals' && $field === 'activity_key'): ?>
                            <?php $selectedTotalKey = (string) ($editing[$field] ?? 'training'); ?>
                            <select name="<?= e($field) ?>" required>
                                <?php foreach (activity_total_defaults() as $totalKey => $totalDefault): ?>
                                    <option value="<?= e($totalKey) ?>" <?= $selectedTotalKey === $totalKey ? 'selected' : '' ?>><?= e($totalDefault['activity_label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($key === 'activity_totals' && $field === 'financial_year'): ?>
                            <input name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? current_financial_year())) ?>" placeholder="2026-27" required>
                        <?php elseif ($key === 'staff' && $field === 'group_type'): ?>
                            <?php $selectedGroup = (string) ($editing[$field] ?? 'kvk'); ?>
                            <select name="<?= e($field) ?>" required>
                                <option value="kvk" <?= $selectedGroup === 'kvk' ? 'selected' : '' ?>>KVK Kokrajhar Staff</option>
                                <option value="directorate" <?= $selectedGroup === 'directorate' ? 'selected' : '' ?>>Directorate of Extension Education</option>
                            </select>
                        <?php elseif (in_array($field, $module['textarea'] ?? [], true)): ?>
                            <textarea name="<?= e($field) ?>" rows="4"><?= e((string) ($editing[$field] ?? '')) ?></textarea>
                        <?php elseif (in_array($field, $module['file_fields'] ?? [], true)): ?>
                            <input type="file" name="<?= e($field) ?>">
                            <?php if (!empty($editing[$field])): ?><small>Current: <?= e((string) $editing[$field]) ?></small><?php endif; ?>
                        <?php elseif (in_array($field, $module['date_fields'] ?? [], true)): ?>
                            <input type="date" name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? '')) ?>">
                        <?php elseif ($key === 'staff' && $field === 'sort_order'): ?>
                            <input type="number" min="0" name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? '100')) ?>">
                        <?php elseif ($key === 'staff' && $field === 'is_active'): ?>
                            <?php $selectedStatus = (int) ($editing[$field] ?? 1); ?>
                            <select name="<?= e($field) ?>" data-staff-status>
                                <option value="1" <?= $selectedStatus === 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= $selectedStatus === 0 ? 'selected' : '' ?>>Deactive</option>
                            </select>
                        <?php elseif ($key === 'staff' && $field === 'inactive_reason'): ?>
                            <?php $selectedReason = (string) ($editing[$field] ?? ''); ?>
                            <select name="<?= e($field) ?>" data-staff-inactive-field>
                                <option value="">Select reason</option>
                                <option value="Transfer" <?= $selectedReason === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                                <option value="Resign" <?= $selectedReason === 'Resign' ? 'selected' : '' ?>>Resign</option>
                            </select>
                        <?php elseif ($key === 'staff' && $field === 'inactive_order_no'): ?>
                            <input name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? '')) ?>" placeholder="Order no. / memo no." data-staff-inactive-field>
                        <?php elseif ($key === 'activity_totals' && in_array($field, ['total_count', 'beneficiary_count'], true)): ?>
                            <input type="number" min="0" name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? '0')) ?>" required>
                        <?php else: ?>
                            <input name="<?= e($field) ?>" value="<?= e((string) ($editing[$field] ?? '')) ?>" <?= in_array($field, ['title', 'name'], true) ? 'required' : '' ?>>
                        <?php endif; ?>
                    </label>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="form-actions">
                    <button class="btn btn--primary" type="submit" <?= $pdo ? '' : 'disabled' ?>><?= $isSeedHubEntryForm ? ($editing ? 'Update Seed Hub Record' : 'Save Seed Hub Record') : ($isClusterDemonstrationEntryForm ? ($editing ? 'Update Cluster Demonstration Record' : 'Save Cluster Demonstration Record') : ($isTechnologyShowcasingEntryForm ? ($editing ? 'Update Technology Showcasing Record' : 'Save Technology Showcasing Record') : ($isTvTalkEntryForm ? ($editing ? 'Update TV Talk Record' : 'Save TV Talk Record') : ($isRadioTalkEntryForm ? ($editing ? 'Update Radio Talk Record' : 'Save Radio Talk Record') : ($isLectureEntryForm ? ($editing ? 'Update Lecture Record' : 'Save Lecture Record') : ($isShgGosthiEntryForm ? ($editing ? 'Update ' . e($shgGosthiLabel) . ' Record' : 'Save ' . e($shgGosthiLabel) . ' Record') : ($isImportantDayEntryForm ? ($editing ? 'Update Important Day Record' : 'Save Important Day Record') : ($isBeneficiaryEventEntryForm ? ($editing ? 'Update ' . e($beneficiaryEventLabel) . ' Record' : 'Save ' . e($beneficiaryEventLabel) . ' Record') : ($isSpecialEventEntryForm ? ($editing ? 'Update ' . e($specialEventLabel) . ' Record' : 'Save ' . e($specialEventLabel) . ' Record') : ($isExhibitionEntryForm ? ($editing ? 'Update Exhibition Record' : 'Save Exhibition Record') : ($isAwarenessEntryForm ? ($editing ? 'Update Awareness Record' : 'Save Awareness Record') : ($isFldEntryForm ? ($editing ? 'Update FLD Record' : 'Save FLD Record') : ($isOftEntryForm ? ($editing ? 'Update OFT Record' : 'Save OFT Record') : ($editing ? 'Update' : 'Save')))))))))))))) ?></button>
                    <?php if ($editing): ?><a class="btn btn--light" href="user-manage.php?module=<?= e($key) ?><?= $key === 'activities' ? '&desk=' . e($activityDesk) . '&activity_type=' . e($activityMandatedType) : '' ?>">Cancel</a><?php endif; ?>
                </div>
            </form>
            <?php if ($isPlantingMaterialEntryForm): ?>
                <form id="planting-commodity-register-form" method="post" action="user-manage.php?module=activities&desk=mandated&activity_type=planting-materials" enctype="multipart/form-data" hidden>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="planting_material_commodity_submit" value="1">
                    <input type="hidden" name="commodity_edit_id" value="<?= e((string) ($plantingCommodityEditing['id'] ?? 0)) ?>">
                </form>
            <?php endif; ?>
        </section>
        <section class="admin-panel">
            <div class="admin-panel__title">
                <div>
                    <h2><?= $key === 'activities' && $activityDesk === 'mandated' ? e($activityCurrentLabel) . ' database records' : 'Current records' ?></h2>
                    <?php if ($key === 'activities'): ?><p>Click an activity card above to switch the table records.</p><?php endif; ?>
                </div>
                <div class="admin-export-actions">
                    <select data-card-year-filter="admin-current-records-table" aria-label="Sort by financial year">
                        <option value="">All Financial Years</option>
                    </select>
                    <button type="button" data-card-print-target="admin-current-records-table" data-card-export-title="<?= e($key === 'activities' && $activityDesk === 'mandated' ? $activityCurrentLabel . ' database records' : 'Current records') ?>">Print</button>
                    <button type="button" data-card-export-target="admin-current-records-table" data-card-export-name="<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($key === 'activities' && $activityDesk === 'mandated' ? $activityCurrentLabel : $module['title']))) ?>">Excel Export</button>
                    <?php if ($key === 'activities'): ?><a class="btn btn--primary" href="user-manage.php?module=<?= e($key) ?>&desk=<?= e($activityDesk) ?>&activity_type=<?= e($activityMandatedType) ?>">Add New</a><?php endif; ?>
                </div>
            </div>
            <div class="table-wrap">
                <table id="admin-current-records-table">
                    <thead>
                    <tr>
                        <?php if ($showExposureRecordColumns): ?>
                            <th>Date</th>
                            <th>Name of Visit</th>
                            <th>Location</th>
                            <th>Organised/Sponsored by</th>
                            <th>No. of beneficiaries (Male, Female)</th>
                            <th>Photo</th>
                        <?php elseif ($showTrainingRecordColumns): ?>
                            <th>Name of Training</th>
                            <th>Discipline</th>
                            <th>Type</th>
                            <th>Training Area</th>
                            <th>Duration</th>
                            <th>Farmers M/W</th>
                            <th>Rural Youth M/W</th>
                            <th>EF M/W</th>
                            <th>Total</th>
                            <th>Photographs</th>
                        <?php elseif ($showOftRecordColumns): ?>
                            <th>Year</th>
                            <th>Crop</th>
                            <th>Discipline</th>
                            <th>Thematic Area</th>
                            <th>Title of OFT</th>
                            <th>Location</th>
                            <th>Season</th>
                            <th>Result of OFT</th>
                            <th>Photographs</th>
                        <?php elseif ($showFldRecordColumns): ?>
                            <th>Year</th>
                            <th>Crop</th>
                            <th>Discipline</th>
                            <th>Thematic Area</th>
                            <th>Technology Demonstrated</th>
                            <th>Season</th>
                            <th>Area Covered (HA)</th>
                            <th>Location</th>
                            <th>No of Farmers</th>
                            <th>Photographs</th>
                        <?php elseif ($showAwarenessRecordColumns): ?>
                            <th>Year</th>
                            <th>Date From</th>
                            <th>Date To</th>
                            <th>Thematic Area</th>
                            <th>Programme Title</th>
                            <th>Farmers M/F</th>
                            <th>RY M/F</th>
                            <th>Officials M/F</th>
                            <th>Total</th>
                        <?php elseif ($showExhibitionRecordColumns): ?>
                            <th>Year</th>
                            <th>Date From</th>
                            <th>Date To</th>
                            <th>Organised</th>
                            <th>Title of the Exhibition</th>
                            <th>Venue</th>
                            <th>Collaboration</th>
                            <th>Visitors M/F</th>
                            <th>Total</th>
                        <?php elseif ($showSpecialEventRecordColumns): ?>
                            <?php $specialEventListConfig = activity_special_event_record_config($activityMandatedType); ?>
                            <th>Year</th>
                            <th>Date From</th>
                            <th>Date To</th>
                            <th>Organised</th>
                            <th><?= e((string) ($specialEventListConfig['title_label'] ?? 'Title')) ?></th>
                            <th>Venue</th>
                            <th>Collaboration</th>
                            <th>Visitors M/F</th>
                            <th>Total</th>
                        <?php elseif ($showBeneficiaryEventRecordColumns): ?>
                            <th>Year</th>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th>Topic</th>
                            <th>Location</th>
                            <th>Beneficiaries M/F</th>
                            <th>Total</th>
                        <?php elseif ($showImportantDayRecordColumns): ?>
                            <th>Date</th>
                            <th>Topic</th>
                            <th>Venue</th>
                            <th>Beneficiaries M/F</th>
                            <th>Total</th>
                        <?php elseif ($showShgGosthiRecordColumns): ?>
                            <th>Name</th>
                            <th>Date of Formation/Registration</th>
                            <th>Location</th>
                            <th>Total Participants</th>
                            <th>Remarks</th>
                        <?php elseif ($showLectureRecordColumns): ?>
                            <th>Date</th>
                            <th>Name of the Programme</th>
                            <th>Topic</th>
                            <th>Organised By</th>
                            <th>Venue</th>
                        <?php elseif ($showRadioTalkRecordColumns): ?>
                            <th>Date</th>
                            <th>Topic</th>
                            <th>Radio Station</th>
                            <th>Targeted Beneficiaries</th>
                        <?php elseif ($showTvTalkRecordColumns): ?>
                            <th>Date</th>
                            <th>Topic</th>
                            <th>TV Station</th>
                            <th>Targeted Beneficiaries</th>
                        <?php elseif ($showTechnologyShowcasingColumns): ?>
                            <th>Year</th>
                            <th>Crop</th>
                            <th>Variety</th>
                            <th>No of farmers involved</th>
                            <th>Area Covered (HA)</th>
                            <th>Production (In tonnes)</th>
                        <?php elseif ($showClusterDemonstrationColumns): ?>
                            <th>Year</th>
                            <th>Crop</th>
                            <th>Variety</th>
                            <th>No of Demonstration</th>
                            <th>No of farmers involved</th>
                            <th>Area Covered (HA)</th>
                            <th>Production (In tonnes)</th>
                        <?php elseif ($showSeedHubColumns): ?>
                            <th>Year</th>
                            <th>Crop</th>
                            <th>Variety</th>
                            <th>Area (Ha)</th>
                            <th>Production (Q)</th>
                            <th>Reasons for shortfall, if any</th>
                        <?php elseif ($showPlantingMaterialColumns): ?>
                            <th>Produce Date</th>
                            <th>Sold Date</th>
                            <th>Commodity</th>
                            <th>Source</th>
                            <th>Quantity Produced</th>
                            <th>Value of Commodity</th>
                            <th>Quantity Sold</th>
                            <th>Quantity Distributed</th>
                            <th>Revenue Generated (Rs.)</th>
                        <?php else: ?>
                            <?php foreach ($module['list'] as $field): ?>
                                <?php if ($key === 'staff_heads' && $field === 'duration'): ?>
                                    <th>Duration From</th>
                                    <th>Duration To</th>
                                <?php elseif ($key === 'activities' && $field === 'slug'): ?>
                                    <th>Activity Field</th>
                                <?php else: ?>
                                    <th><?= e(ucwords(str_replace('_', ' ', $field))) ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($showExposureRecordColumns): ?>
                                <?php $exposureRow = activity_exposure_record_for_form($pdo, $row, $activityMandatedType); ?>
                                <td><?= e((string) ($exposureRow['visit_date'] ?? '')) ?></td>
                                <td><?= e(excerpt((string) (($exposureRow['visit_name'] ?? '') ?: ($row['title'] ?? '')), 90)) ?></td>
                                <td><?= e(excerpt((string) ($exposureRow['location'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($exposureRow['organised_by'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($exposureRow['beneficiaries_male'] ?? '0')) ?> / <?= e((string) ($exposureRow['beneficiaries_female'] ?? '0')) ?></td>
                                <td>
                                    <?php if (!empty($row['image_path'])): ?>
                                        <a href="../<?= e((string) $row['image_path']) ?>" target="_blank">View photo</a>
                                    <?php else: ?>
                                        <span class="muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($showTrainingRecordColumns): ?>
                                <?php $trainingRow = activity_training_record_for_form($pdo, $row); ?>
                                <?php
                                    $trainingPhotos = [];
                                    foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $photoField) {
                                        if (!empty($trainingRow[$photoField])) {
                                            $trainingPhotos[] = (string) $trainingRow[$photoField];
                                        }
                                    }
                                    if (!$trainingPhotos && !empty($row['image_path'])) {
                                        $trainingPhotos[] = (string) $row['image_path'];
                                    }
                                ?>
                                <td><?= e(excerpt((string) ($trainingRow['training_name'] ?: ($row['title'] ?? '')), 90)) ?></td>
                                <td><?= e(excerpt((string) ($trainingRow['discipline'] ?? ''), 70)) ?></td>
                                <td><?= e((string) ($trainingRow['training_type'] ?? '')) ?></td>
                                <td><?= e(excerpt((string) ($trainingRow['training_area'] ?? ''), 70)) ?></td>
                                <td><?= e(trim((string) ($trainingRow['duration_from'] ?? '') . ' to ' . (string) ($trainingRow['duration_to'] ?? ''))) ?></td>
                                <td><?= e((string) ($trainingRow['farmers_men'] ?? '0')) ?> / <?= e((string) ($trainingRow['farmers_women'] ?? '0')) ?></td>
                                <td><?= e((string) ($trainingRow['rural_youth_men'] ?? '0')) ?> / <?= e((string) ($trainingRow['rural_youth_women'] ?? '0')) ?></td>
                                <td><?= e((string) ($trainingRow['ef_men'] ?? '0')) ?> / <?= e((string) ($trainingRow['ef_women'] ?? '0')) ?></td>
                                <td><?= e((string) training_record_total_participants($trainingRow)) ?></td>
                                <td>
                                    <?php if ($trainingPhotos): ?>
                                        <?php foreach ($trainingPhotos as $photoIndex => $trainingPhoto): ?>
                                            <a href="../<?= e($trainingPhoto) ?>" target="_blank">Photo <?= e((string) ($photoIndex + 1)) ?></a><?= $photoIndex < count($trainingPhotos) - 1 ? '<br>' : '' ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($showOftRecordColumns): ?>
                                <?php $oftRow = activity_oft_record_for_form($pdo, $row); ?>
                                <?php $oftPhotoRow = oft_record_from_database($pdo, (int) ($row['id'] ?? 0)) ?: []; ?>
                                <?php
                                    $oftPhotos = [];
                                    foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $photoField) {
                                        if (!empty($oftPhotoRow[$photoField])) {
                                            $oftPhotos[] = (string) $oftPhotoRow[$photoField];
                                        }
                                    }
                                    if (!$oftPhotos && !empty($row['image_path'])) {
                                        $oftPhotos[] = (string) $row['image_path'];
                                    }
                                ?>
                                <td><?= e(excerpt((string) ($oftRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['crop'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['discipline'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['thematic_area'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) (($oftRow['oft_title'] ?? '') ?: ($row['title'] ?? '')), 110)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['location'] ?? ''), 80)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['season'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($oftRow['results_summary'] ?? ''), 90)) ?></td>
                                <td>
                                    <?php if ($oftPhotos): ?>
                                        <?php foreach ($oftPhotos as $photoIndex => $oftPhoto): ?>
                                            <a href="../<?= e($oftPhoto) ?>" target="_blank">Photo <?= e((string) ($photoIndex + 1)) ?></a><?= $photoIndex < count($oftPhotos) - 1 ? '<br>' : '' ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($showFldRecordColumns): ?>
                                <?php $fldRow = activity_fld_record_for_form($pdo, $row); ?>
                                <?php $fldPhotoRow = fld_record_from_database($pdo, (int) ($row['id'] ?? 0)) ?: []; ?>
                                <?php
                                    $fldPhotos = [];
                                    foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $photoField) {
                                        if (!empty($fldPhotoRow[$photoField])) {
                                            $fldPhotos[] = (string) $fldPhotoRow[$photoField];
                                        }
                                    }
                                    if (!$fldPhotos && !empty($row['image_path'])) {
                                        $fldPhotos[] = (string) $row['image_path'];
                                    }
                                ?>
                                <td><?= e(excerpt((string) ($fldRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['crop'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['discipline'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['thematic_area'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) (($fldRow['technology_tested'] ?? '') ?: ($row['title'] ?? '')), 110)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['season'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['area_covered'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['location'] ?? ''), 80)) ?></td>
                                <td><?= e(excerpt((string) ($fldRow['number_of_farmers'] ?? ''), 40)) ?></td>
                                <td>
                                    <?php if ($fldPhotos): ?>
                                        <?php foreach ($fldPhotos as $photoIndex => $fldPhoto): ?>
                                            <a href="../<?= e($fldPhoto) ?>" target="_blank">Photo <?= e((string) ($photoIndex + 1)) ?></a><?= $photoIndex < count($fldPhotos) - 1 ? '<br>' : '' ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="muted">No photo</span>
                                    <?php endif; ?>
                                </td>
                            <?php elseif ($showAwarenessRecordColumns): ?>
                                <?php $awarenessRow = activity_awareness_record_for_form($pdo, $row); ?>
                                <?php
                                    $awarenessTotal = (int) ($awarenessRow['farmers_male'] ?? 0)
                                        + (int) ($awarenessRow['farmers_female'] ?? 0)
                                        + (int) ($awarenessRow['ry_male'] ?? 0)
                                        + (int) ($awarenessRow['ry_female'] ?? 0)
                                        + (int) ($awarenessRow['officials_male'] ?? 0)
                                        + (int) ($awarenessRow['officials_female'] ?? 0);
                                ?>
                                <td><?= e(excerpt((string) ($awarenessRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($awarenessRow['date_from'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($awarenessRow['date_to'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($awarenessRow['thematic_area'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) (($awarenessRow['programme_title'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e((string) ($awarenessRow['farmers_male'] ?? '0')) ?> / <?= e((string) ($awarenessRow['farmers_female'] ?? '0')) ?></td>
                                <td><?= e((string) ($awarenessRow['ry_male'] ?? '0')) ?> / <?= e((string) ($awarenessRow['ry_female'] ?? '0')) ?></td>
                                <td><?= e((string) ($awarenessRow['officials_male'] ?? '0')) ?> / <?= e((string) ($awarenessRow['officials_female'] ?? '0')) ?></td>
                                <td><?= e((string) $awarenessTotal) ?></td>
                            <?php elseif ($showExhibitionRecordColumns): ?>
                                <?php $exhibitionRow = activity_exhibition_record_for_form($pdo, $row); ?>
                                <?php $exhibitionTotal = exhibition_record_total_visitors($exhibitionRow); ?>
                                <td><?= e(excerpt((string) ($exhibitionRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($exhibitionRow['date_from'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($exhibitionRow['date_to'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($exhibitionRow['organised_by'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) (($exhibitionRow['exhibition_title'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($exhibitionRow['venue'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($exhibitionRow['collaboration'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($exhibitionRow['visitors_male'] ?? '0')) ?> / <?= e((string) ($exhibitionRow['visitors_female'] ?? '0')) ?></td>
                                <td><?= e((string) $exhibitionTotal) ?></td>
                            <?php elseif ($showSpecialEventRecordColumns): ?>
                                <?php $specialEventRow = activity_special_event_record_for_form($pdo, $row, $activityMandatedType); ?>
                                <?php $specialEventTotal = special_event_record_total_visitors($specialEventRow); ?>
                                <td><?= e(excerpt((string) ($specialEventRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($specialEventRow['date_from'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($specialEventRow['date_to'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($specialEventRow['organised_by'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) (($specialEventRow['event_title'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($specialEventRow['venue'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($specialEventRow['collaboration'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($specialEventRow['visitors_male'] ?? '0')) ?> / <?= e((string) ($specialEventRow['visitors_female'] ?? '0')) ?></td>
                                <td><?= e((string) $specialEventTotal) ?></td>
                            <?php elseif ($showBeneficiaryEventRecordColumns): ?>
                                <?php $beneficiaryEventRow = activity_beneficiary_event_record_for_form($pdo, $row, $activityMandatedType); ?>
                                <?php $beneficiaryEventTotal = beneficiary_event_record_total_beneficiaries($beneficiaryEventRow); ?>
                                <td><?= e(excerpt((string) ($beneficiaryEventRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($beneficiaryEventRow['event_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) (($beneficiaryEventRow['particulars'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($beneficiaryEventRow['topic'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($beneficiaryEventRow['location'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($beneficiaryEventRow['beneficiaries_male'] ?? '0')) ?> / <?= e((string) ($beneficiaryEventRow['beneficiaries_female'] ?? '0')) ?></td>
                                <td><?= e((string) $beneficiaryEventTotal) ?></td>
                            <?php elseif ($showImportantDayRecordColumns): ?>
                                <?php $importantDayRow = activity_important_day_record_for_form($pdo, $row); ?>
                                <?php $importantDayTotal = important_day_record_total_beneficiaries($importantDayRow); ?>
                                <td><?= e(excerpt((string) ($importantDayRow['event_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) (($importantDayRow['topic'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($importantDayRow['venue'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($importantDayRow['beneficiaries_male'] ?? '0')) ?> / <?= e((string) ($importantDayRow['beneficiaries_female'] ?? '0')) ?></td>
                                <td><?= e((string) $importantDayTotal) ?></td>
                            <?php elseif ($showShgGosthiRecordColumns): ?>
                                <?php $shgGosthiRow = shg_gosthi_record_for_form($pdo, $row, $activityMandatedType); ?>
                                <td><?= e(excerpt((string) (($shgGosthiRow['record_name'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($shgGosthiRow['formation_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($shgGosthiRow['location'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($shgGosthiRow['total_participants'] ?? '0')) ?></td>
                                <td><?= e(excerpt((string) ($shgGosthiRow['remarks'] ?? ''), 120)) ?></td>
                            <?php elseif ($showLectureRecordColumns): ?>
                                <?php $lectureRow = lecture_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($lectureRow['programme_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) (($lectureRow['programme_name'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($lectureRow['topic'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($lectureRow['organised_by'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($lectureRow['venue'] ?? ''), 90)) ?></td>
                            <?php elseif ($showRadioTalkRecordColumns): ?>
                                <?php $radioTalkRow = radio_talk_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($radioTalkRow['talk_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) (($radioTalkRow['topic'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($radioTalkRow['radio_station'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($radioTalkRow['targeted_beneficiaries'] ?? ''), 90)) ?></td>
                            <?php elseif ($showTvTalkRecordColumns): ?>
                                <?php $tvTalkRow = tv_talk_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($tvTalkRow['talk_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) (($tvTalkRow['topic'] ?? '') ?: ($row['title'] ?? '')), 120)) ?></td>
                                <td><?= e(excerpt((string) ($tvTalkRow['tv_station'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($tvTalkRow['targeted_beneficiaries'] ?? ''), 90)) ?></td>
                            <?php elseif ($showTechnologyShowcasingColumns): ?>
                                <?php $technologyRow = activity_technology_showcasing_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($technologyRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($technologyRow['crop'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($technologyRow['variety'] ?? ''), 80)) ?></td>
                                <td><?= e((string) ($technologyRow['farmers_involved'] ?? '0')) ?></td>
                                <td><?= e(excerpt((string) ($technologyRow['area_covered'] ?? ''), 70)) ?></td>
                                <td><?= e((string) ($technologyRow['production_tonnes'] ?? '0')) ?></td>
                            <?php elseif ($showClusterDemonstrationColumns): ?>
                                <?php $clusterRow = activity_cluster_demonstration_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($clusterRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($clusterRow['crop'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($clusterRow['variety'] ?? ''), 80)) ?></td>
                                <td><?= e((string) ($clusterRow['demonstration_count'] ?? '0')) ?></td>
                                <td><?= e((string) ($clusterRow['farmers_involved'] ?? '0')) ?></td>
                                <td><?= e(excerpt((string) ($clusterRow['area_covered'] ?? ''), 70)) ?></td>
                                <td><?= e((string) ($clusterRow['production_tonnes'] ?? '0')) ?></td>
                            <?php elseif ($showSeedHubColumns): ?>
                                <?php $seedHubRow = activity_seed_hub_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($seedHubRow['year'] ?? ''), 40)) ?></td>
                                <td><?= e(excerpt((string) ($seedHubRow['crop'] ?? ''), 70)) ?></td>
                                <td><?= e(excerpt((string) ($seedHubRow['variety'] ?? ''), 80)) ?></td>
                                <td><?= e(excerpt((string) ($seedHubRow['area_ha'] ?? ''), 70)) ?></td>
                                <td><?= e((string) ($seedHubRow['production_q'] ?? '0')) ?></td>
                                <td><?= e(excerpt((string) ($seedHubRow['shortfall_reason'] ?? ''), 100)) ?></td>
                            <?php elseif ($showPlantingMaterialColumns): ?>
                                <?php $plantingRow = activity_planting_material_record_for_form($pdo, $row); ?>
                                <td><?= e(excerpt((string) ($plantingRow['produce_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($plantingRow['sold_date'] ?? ''), 60)) ?></td>
                                <td><?= e(excerpt((string) ($plantingRow['commodity_name'] ?? ''), 90)) ?></td>
                                <td><?= e(excerpt((string) ($plantingRow['source'] ?? ''), 90)) ?></td>
                                <td><?= e((string) ($plantingRow['quantity_produced'] ?? '0')) ?></td>
                                <td><?= e((string) ($plantingRow['value_of_commodity'] ?? '0.00')) ?></td>
                                <td><?= e((string) ($plantingRow['quantity_sold'] ?? '0')) ?></td>
                                <td><?= e((string) ($plantingRow['quantity_distributed'] ?? '0')) ?></td>
                                <td><?= e((string) ($plantingRow['revenue_generated'] ?? '0.00')) ?></td>
                            <?php else: ?>
                                <?php foreach ($module['list'] as $field): ?>
                                    <?php if ($key === 'staff_heads' && $field === 'duration'): ?>
                                        <?php [$durationFrom, $durationTo] = staff_head_duration_parts((string) ($row[$field] ?? '')); ?>
                                        <td><?= e(excerpt($durationFrom, 90)) ?></td>
                                        <td><?= e(excerpt($durationTo, 90)) ?></td>
                                    <?php elseif ($key === 'activities' && $field === 'slug'): ?>
                                        <td><?= e(excerpt(activity_slug_label((string) ($row[$field] ?? '')), 90)) ?></td>
                                    <?php elseif ($key === 'staff' && $field === 'is_active'): ?>
                                        <td><?= (int) ($row[$field] ?? 1) === 1 ? 'Active' : 'Deactive' ?></td>
                                    <?php else: ?>
                                        <td><?= e(excerpt((string) ($row[$field] ?? ''), 90)) ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <td class="table-actions">
                                <?php if (!empty($row['id'])): ?>
                                    <a href="user-manage.php?module=<?= e($key) ?><?= $key === 'activities' ? '&desk=' . e($activityDesk) . '&activity_type=' . e($activityMandatedType) : '' ?>&edit=<?= e((string) $row['id']) ?>">Edit</a>
                                    <a href="user-manage.php?module=<?= e($key) ?><?= $key === 'activities' ? '&desk=' . e($activityDesk) . '&activity_type=' . e($activityMandatedType) : '' ?>&delete=<?= e((string) $row['id']) ?>&token=<?= e(csrf_token()) ?>" onclick="return confirm('Delete this record?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <?php
                            $emptyColspan = count($module['list']) + 1;
                            if ($showExposureRecordColumns) {
                                $emptyColspan = 6;
                            } elseif ($showTrainingRecordColumns || $showOftRecordColumns) {
                                $emptyColspan = 10;
                            } elseif ($showFldRecordColumns) {
                                $emptyColspan = 11;
                            } elseif ($showAwarenessRecordColumns) {
                                $emptyColspan = 10;
                            } elseif ($showTechnologyShowcasingColumns) {
                                $emptyColspan = 7;
                            } elseif ($showClusterDemonstrationColumns) {
                                $emptyColspan = 8;
                            } elseif ($showSeedHubColumns) {
                                $emptyColspan = 7;
                            } elseif ($showPlantingMaterialColumns) {
                                $emptyColspan = 10;
                            }
                        ?>
                        <tr>
                            <td colspan="<?= e((string) $emptyColspan) ?>"><span class="muted">No database records saved for this desk yet.</span></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
<?php if ($key === 'activities'): ?>
<script>
const syncActivitySpecialFields = (selected, mandatedType) => {
    const details = document.querySelector('.activity-details-field');
    if (!details) {
        return;
    }
    const isTraining = selected === 'mandated' && mandatedType === 'training';
    const isOft = selected === 'mandated' && mandatedType === 'oft';
    details.dataset.training = isTraining ? 'yes' : 'no';
    details.dataset.oft = isOft ? 'yes' : 'no';
    details.querySelectorAll('fieldset:not(.oft-record) input, fieldset:not(.oft-record) select, fieldset:not(.oft-record) textarea').forEach((input) => {
        input.disabled = !isTraining;
    });
    details.querySelectorAll('.oft-record input, .oft-record select, .oft-record textarea').forEach((input) => {
        input.disabled = !isOft;
    });
    document.querySelectorAll('.activity-general-field').forEach((field) => {
        field.hidden = selected === 'mandated' && (mandatedType === 'training' || mandatedType === 'oft');
    });
};
document.querySelectorAll('.activity-desk').forEach((desk) => {
    const sync = () => {
        const selected = desk.querySelector('input[name="activity_desk"]:checked')?.value || 'mandated';
        desk.dataset.selected = selected;
        const mandatedType = desk.querySelector('select[name="activity_mandated_type"]')?.value || '';
        syncActivitySpecialFields(selected, mandatedType);
    };
    desk.querySelectorAll('input[name="activity_desk"]').forEach((input) => input.addEventListener('change', sync));
    desk.querySelector('select[name="activity_mandated_type"]')?.addEventListener('change', sync);
    sync();
});
syncActivitySpecialFields(
    document.querySelector('input[name="activity_desk"]')?.value || 'mandated',
    document.querySelector('input[name="activity_mandated_type"]')?.value || ''
);
const trainingName = document.querySelector('input[name="training_record[training_name]"]');
const titleInput = document.querySelector('input[name="title"]');
trainingName?.addEventListener('input', () => {
    if (titleInput && titleInput.value.trim() === '') {
        titleInput.value = trainingName.value;
    }
});
const oftTitle = document.querySelector('textarea[name="oft_record[oft_title]"]');
oftTitle?.addEventListener('input', () => {
    if (titleInput && titleInput.value.trim() === '') {
        titleInput.value = oftTitle.value;
    }
});
const participantInputs = [
    'training_record[farm_women]',
    'training_record[rural_youth]',
    'training_record[ef]'
].map((name) => document.querySelector(`input[name="${name}"]`));
const totalInput = document.querySelector('input[name="training_record[total_participants]"]');
const syncTotal = () => {
    if (!totalInput) {
        return;
    }
    const total = participantInputs.reduce((sum, input) => sum + Number(input?.value || 0), 0);
    totalInput.value = total ? String(total) : totalInput.value;
};
participantInputs.forEach((input) => input?.addEventListener('input', syncTotal));
document.querySelectorAll('[data-planting-tab-button]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = button.dataset.plantingTabButton;
        document.querySelectorAll('[data-planting-tab-button]').forEach((tabButton) => {
            tabButton.classList.toggle('is-active', tabButton === button);
        });
        document.querySelectorAll('[data-planting-tab-panel]').forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.plantingTabPanel === target);
        });
    });
});
const plantingCommodity = document.querySelector('[data-planting-commodity]');
const plantingSoldCommodity = document.querySelector('[data-planting-sold-commodity]');
const plantingQuantityProduced = document.querySelector('[data-planting-quantity-produced]');
const plantingValue = document.querySelector('[data-planting-value]');
const plantingQuantitySold = document.querySelector('[data-planting-quantity-sold]');
const plantingQuantityDistributed = document.querySelector('[data-planting-quantity-distributed]');
const plantingRevenue = document.querySelector('[data-planting-revenue]');
const syncPlantingCommodity = (source) => {
    if (!plantingCommodity || !plantingSoldCommodity || !source?.value) {
        return;
    }
    if (source === plantingCommodity) {
        plantingSoldCommodity.value = plantingCommodity.value;
    } else {
        plantingCommodity.value = plantingSoldCommodity.value;
    }
};
const plantingRate = () => Number(plantingCommodity?.selectedOptions[0]?.dataset.rate || 0);
const syncPlantingValue = () => {
    if (!plantingQuantityProduced || !plantingValue) {
        return;
    }
    plantingValue.value = (plantingRate() * Number(plantingQuantityProduced.value || 0)).toFixed(2);
};
const syncPlantingRevenue = () => {
    if (!plantingCommodity || !plantingQuantitySold || !plantingQuantityDistributed || !plantingRevenue) {
        return;
    }
    const quantitySold = Number(plantingQuantitySold.value || 0);
    const quantityDistributed = Number(plantingQuantityDistributed.value || 0);
    plantingRevenue.value = quantityDistributed > 0 ? '0.00' : (plantingRate() * quantitySold).toFixed(2);
};
plantingCommodity?.addEventListener('change', () => {
    syncPlantingCommodity(plantingCommodity);
    syncPlantingValue();
    syncPlantingRevenue();
});
plantingSoldCommodity?.addEventListener('change', () => {
    syncPlantingCommodity(plantingSoldCommodity);
    syncPlantingValue();
    syncPlantingRevenue();
});
plantingQuantityProduced?.addEventListener('input', syncPlantingValue);
plantingQuantitySold?.addEventListener('input', syncPlantingRevenue);
plantingQuantityDistributed?.addEventListener('input', syncPlantingRevenue);
syncPlantingCommodity(plantingCommodity);
syncPlantingValue();
syncPlantingRevenue();
document.querySelectorAll('[data-scroll-target]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById(button.dataset.scrollTarget)?.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
});
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-admin-print-target], [data-admin-export-target]');
    if (!button) {
        return;
    }

    if (button.dataset.adminPrintTarget) {
        const printArea = document.getElementById(button.dataset.adminPrintTarget);
        if (!printArea) {
            return;
        }

        const yearTitle = button.closest('.public-outreach-report__year')?.querySelector('h3')?.textContent.trim() || '';
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<!doctype html><html><head><title>Public Outreach Programme - ' + yearTitle + '</title>');
        printWindow.document.write('<style>body{font-family:Arial,sans-serif;color:#24382d;padding:24px}h1{font-size:22px;margin:0 0 16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfdccc;padding:9px;text-align:left;vertical-align:top}th{background:#f3f8f0;color:#17452d}</style>');
        printWindow.document.write('</head><body><h1>Public Outreach Programme - Financial Year ' + yearTitle + '</h1>' + printArea.innerHTML + '</body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        return;
    }

    const exportArea = document.getElementById(button.dataset.adminExportTarget);
    if (!exportArea) {
        return;
    }

    const rows = Array.from(exportArea.querySelectorAll('tr')).map((row) => {
        return Array.from(row.children).map((cell) => '"' + cell.textContent.trim().replace(/"/g, '""') + '"').join(',');
    }).join('\n');
    const blob = new Blob([rows], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = (button.dataset.adminExportName || 'public-outreach-programme') + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
});
</script>
<?php endif; ?>
<script>
const cardFinancialYearFromText = (text) => {
    const isoDate = text.match(/\b(20\d{2})-(\d{2})-\d{2}\b/);
    if (isoDate) {
        const year = Number(isoDate[1]);
        const month = Number(isoDate[2]);
        const start = month >= 4 ? year : year - 1;
        return start + '-' + String(start + 1).slice(-2);
    }

    const namedDate = text.match(/\b\d{1,2}\s+[A-Za-z]{3,9}\s+(20\d{2})\b/);
    if (namedDate) {
        const date = new Date(namedDate[0]);
        if (!Number.isNaN(date.getTime())) {
            const year = date.getFullYear();
            const start = date.getMonth() >= 3 ? year : year - 1;
            return start + '-' + String(start + 1).slice(-2);
        }
    }

    const direct = text.match(/\b(20\d{2})\s*[-\/]\s*(\d{2})\b/);
    if (direct) {
        return direct[1] + '-' + direct[2];
    }

    const yearOnly = text.match(/\b(20\d{2})\b/);
    if (yearOnly) {
        const start = Number(yearOnly[1]);
        return start + '-' + String(start + 1).slice(-2);
    }

    return '';
};

const cardFinancialYearFromRow = (row, table) => {
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim().toLowerCase());
    const cells = Array.from(row.children);
    const dateIndexes = headers.reduce((indexes, header, index) => {
        if (
            header === 'date'
            || header.includes('date from')
            || header.includes('date to')
            || header.includes('duration')
            || header.includes('formation')
            || header.includes('registration')
        ) {
            indexes.push(index);
        }
        return indexes;
    }, []);

    for (const index of dateIndexes) {
        const year = cardFinancialYearFromText(cells[index]?.textContent || '');
        if (year) {
            return year;
        }
    }

    const yearIndex = headers.findIndex((header) => header === 'year' || header === 'financial year');
    if (yearIndex >= 0) {
        return cardFinancialYearFromText(cells[yearIndex]?.textContent || '');
    }

    return cardFinancialYearFromText(row.textContent);
};

const cardFinancialYearStart = (year) => {
    const match = String(year).match(/\b(20\d{2})/);
    return match ? Number(match[1]) : 0;
};

const prepareCardYearFilter = (select) => {
    const table = document.getElementById(select.dataset.cardYearFilter);
    const tbody = table?.querySelector('tbody');
    if (!table || !tbody) {
        select.hidden = true;
        return;
    }

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const years = new Set();
    rows.forEach((row, index) => {
        row.dataset.originalIndex = String(index);
        const year = cardFinancialYearFromRow(row, table);
        row.dataset.financialYear = year;
        if (year) {
            years.add(year);
        }
    });

    Array.from(years)
        .sort((a, b) => cardFinancialYearStart(b) - cardFinancialYearStart(a))
        .forEach((year) => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            select.appendChild(option);
        });

    const sortRows = () => {
        rows
            .sort((a, b) => {
                const yearDiff = cardFinancialYearStart(b.dataset.financialYear || '') - cardFinancialYearStart(a.dataset.financialYear || '');
                return yearDiff || Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            })
            .forEach((row) => tbody.appendChild(row));
    };
    const filterRows = () => {
        const selectedYear = select.value;
        rows.forEach((row) => {
            row.hidden = selectedYear !== '' && row.dataset.financialYear !== selectedYear;
        });
    };

    sortRows();
    filterRows();
    select.disabled = years.size === 0;
    select.addEventListener('change', filterRows);
};

document.querySelectorAll('[data-card-year-filter]').forEach(prepareCardYearFilter);

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-card-print-target], [data-card-export-target]');
    if (!button) {
        return;
    }

    const tableId = button.dataset.cardPrintTarget || button.dataset.cardExportTarget;
    const table = document.getElementById(tableId);
    if (!table) {
        return;
    }

    const exportTitle = button.dataset.cardExportTitle || 'Database records';
    const actionColumnIndexes = Array.from(table.querySelectorAll('thead th')).reduce((indexes, th, index) => {
        const label = th.textContent.trim().toLowerCase();
        if (label === 'action' || label === 'actions') {
            indexes.push(index);
        }
        return indexes;
    }, []);
    const cleanTable = table.cloneNode(true);
    Array.from(cleanTable.querySelectorAll('tr')).forEach((row) => {
        actionColumnIndexes.slice().reverse().forEach((index) => row.children[index]?.remove());
        if (row.hidden) {
            row.remove();
        }
    });

    if (button.dataset.cardPrintTarget) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<!doctype html><html><head><title>' + exportTitle + '</title>');
        printWindow.document.write('<style>body{font-family:Arial,sans-serif;color:#24382d;padding:24px}h1{font-size:22px;margin:0 0 16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #cfdccc;padding:9px;text-align:left;vertical-align:top}th{background:#f3f8f0;color:#17452d}.muted{color:#666}</style>');
        printWindow.document.write('</head><body><h1>' + exportTitle + '</h1>' + cleanTable.outerHTML + '</body></html>');
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        return;
    }

    const rows = Array.from(cleanTable.querySelectorAll('tr')).map((row) => {
        return Array.from(row.children).map((cell) => '"' + cell.textContent.trim().replace(/"/g, '""') + '"').join(',');
    }).join('\n');
    const blob = new Blob([rows], {type: 'text/csv;charset=utf-8;'});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = (button.dataset.cardExportName || 'database-records') + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
});
</script>
<?php if ($key === 'activities' && $activityDesk === 'home'): ?>
<script>
(() => {
    const searchInput = document.getElementById('activityCardSearch');
    const cards = Array.from(document.querySelectorAll('[data-activity-card]'));
    const emptyState = document.getElementById('activityCardEmpty');

    if (!searchInput || !cards.length) {
        return;
    }

    const filterActivityCards = () => {
        const query = searchInput.value.trim().toLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const haystack = (card.dataset.search || card.textContent || '').toLowerCase();
            const show = query === '' || haystack.includes(query);
            card.hidden = !show;
            if (show) visible++;
        });

        emptyState?.classList.toggle('is-visible', visible === 0);
    };

    searchInput.addEventListener('input', filterActivityCards);
})();
</script>
<?php endif; ?>

<?php if ($key === 'staff'): ?>
<script>
const staffStatus = document.querySelector('[data-staff-status]');
const staffInactiveFields = document.querySelectorAll('[data-staff-inactive-field]');
const syncStaffStatusFields = () => {
    const isInactive = staffStatus?.value === '0';
    staffInactiveFields.forEach((field) => {
        const label = field.closest('label');
        if (label) {
            label.hidden = !isInactive;
        }
        field.required = isInactive;
    });
};
staffStatus?.addEventListener('change', syncStaffStatusFields);
syncStaffStatusFields();
</script>
<?php endif; ?>
</body>
</html>
