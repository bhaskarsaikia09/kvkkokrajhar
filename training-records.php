<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Training Records | ' . APP_NAME;

// Define Historical Baseline Counts
define('BASELINE_TRAININGS', 758);
define('BASELINE_FARMERS', 10468);

// 1. Ensure Table Structure Exists Automatically
function training_records_list_ensure_table(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS training_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        training_name VARCHAR(220) NOT NULL,
        financial_year VARCHAR(12),
        training_date DATE NULL,
        training_location VARCHAR(220),
        location_type VARCHAR(30),
        discipline VARCHAR(160),
        training_type VARCHAR(40),
        training_area VARCHAR(220),
        duration_from DATE NULL,
        duration_to DATE NULL,
        duration_days INT DEFAULT 0,
        trainee_category VARCHAR(100),
        general_male INT DEFAULT 0,
        general_female INT DEFAULT 0,
        obc_male INT DEFAULT 0,
        obc_female INT DEFAULT 0,
        scst_male INT DEFAULT 0,
        scst_female INT DEFAULT 0,
        others_male INT DEFAULT 0,
        others_female INT DEFAULT 0,
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
        'financial_year' => 'VARCHAR(12) NULL',
        'location_type' => 'VARCHAR(30) NULL',
        'training_type' => 'VARCHAR(40) NULL',
        'training_area' => 'VARCHAR(220) NULL',
        'duration_from' => 'DATE NULL',
        'duration_to' => 'DATE NULL',
        'duration_days' => 'INT DEFAULT 0',
        'trainee_category' => 'VARCHAR(100) NULL',
        'general_male' => 'INT DEFAULT 0',
        'general_female' => 'INT DEFAULT 0',
        'obc_male' => 'INT DEFAULT 0',
        'obc_female' => 'INT DEFAULT 0',
        'scst_male' => 'INT DEFAULT 0',
        'scst_female' => 'INT DEFAULT 0',
        'others_male' => 'INT DEFAULT 0',
        'others_female' => 'INT DEFAULT 0',
        'photograph_path_2' => 'VARCHAR(255) NULL',
        'photograph_path_3' => 'VARCHAR(255) NULL',
        'photograph_path_4' => 'VARCHAR(255) NULL',
        'photograph_path_5' => 'VARCHAR(255) NULL',
    ];
    foreach ($columns as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE training_records ADD COLUMN {$column} {$definition}");
        } catch (Throwable $exception) {
            // Column already exists
        }
    }
}

// 2. Fetch All Active Records from Database
function training_records_list_all(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    training_records_list_ensure_table($pdo);
    $stmt = $pdo->query("SELECT tr.*, a.title, a.summary, a.image_path
        FROM training_records tr
        LEFT JOIN activities a ON a.id = tr.activity_id
        WHERE tr.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY COALESCE(tr.training_date, DATE(tr.created_at)) DESC, tr.discipline ASC, tr.id DESC");

    return $stmt ? $stmt->fetchAll() : [];
}

// Helper: Extract Associated Photos
function training_records_list_photos(array $record): array
{
    $photos = [];
    foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $field) {
        if (!empty($record[$field])) {
            $photos[] = (string) $record[$field];
        }
    }
    if (!$photos && !empty($record['image_path'])) {
        $photos[] = (string) $record['image_path'];
    }

    return array_values(array_unique($photos));
}

// Helper: Format Date
function training_records_list_date(?string $date): string
{
    if (!$date) {
        return 'Date not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

// Helper: Format Financial Year
function training_records_list_financial_year(?string $date): string
{
    return $date ? current_financial_year($date) : 'Year not specified';
}

function training_records_list_record_financial_year(array $record): string
{
    $storedFinancialYear = trim((string) ($record['financial_year'] ?? ''));
    if ($storedFinancialYear !== '') {
        return $storedFinancialYear;
    }
    $date = trim((string) ($record['training_date'] ?? ''));
    if ($date === '') {
        $date = trim((string) ($record['created_at'] ?? ''));
    }

    return training_records_list_financial_year($date !== '' ? $date : null);
}

function training_records_list_total(array $record): int
{
    $classifiedTotal = (int) ($record['general_male'] ?? 0) + (int) ($record['general_female'] ?? 0)
        + (int) ($record['obc_male'] ?? 0) + (int) ($record['obc_female'] ?? 0)
        + (int) ($record['scst_male'] ?? 0) + (int) ($record['scst_female'] ?? 0)
        + (int) ($record['others_male'] ?? 0) + (int) ($record['others_female'] ?? 0);
    if ($classifiedTotal > 0) {
        return $classifiedTotal;
    }
    $storedTotal = (int) ($record['total_participants'] ?? 0);
    return $storedTotal > 0 ? $storedTotal : (int) ($record['farm_women'] ?? 0) + (int) ($record['rural_youth'] ?? 0) + (int) ($record['ef'] ?? 0);
}

// Fetch all records
$trainingRecords = training_records_list_all();

// Real-time Calculations + Previous Years Baseline Data
$dbTrainingsCount = count($trainingRecords);
$dbFarmersCount = 0;

$heroPhoto = '';
$totalPhotos = 0;
$disciplinesList = [];
$recordsByFYAndDiscipline = [];

foreach ($trainingRecords as $record) {
    $recordPhotos = training_records_list_photos($record);
    
    // Sum total participants dynamically
    $calculatedTotal = training_records_list_total($record);

    $dbFarmersCount += $calculatedTotal;
    $totalPhotos += count($recordPhotos);
    
    if (!$heroPhoto && $recordPhotos) {
        $heroPhoto = $recordPhotos[0];
    }

    $financialYear = training_records_list_record_financial_year($record);
    $discipline = trim((string) ($record['discipline'] ?? 'General')) ?: 'General';
    $disciplinesList[$discipline] = true;

    if (!isset($recordsByFYAndDiscipline[$financialYear])) {
        $recordsByFYAndDiscipline[$financialYear] = [
            'total_records' => 0,
            'total_participants' => 0,
            'total_photos' => 0,
            'disciplines' => []
        ];
    }

    if (!isset($recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline])) {
        $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline] = [
            'records' => [],
            'participants' => 0,
            'photos' => 0
        ];
    }

    $recordsByFYAndDiscipline[$financialYear]['total_records']++;
    $recordsByFYAndDiscipline[$financialYear]['total_participants'] += $calculatedTotal;
    $recordsByFYAndDiscipline[$financialYear]['total_photos'] += count($recordPhotos);

    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['records'][] = $record;
    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['participants'] += $calculatedTotal;
    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['photos'] += count($recordPhotos);
}

// Final Aggregated Counts (Historical Baseline + Database Live Count)
$officialTrainingsOrganised = BASELINE_TRAININGS + $dbTrainingsCount;
$officialFarmersTrained = BASELINE_FARMERS + $dbFarmersCount;

uksort($recordsByFYAndDiscipline, static function (string $left, string $right): int {
    if ($left === $right) return 0;
    if ($left === 'Year not specified') return 1;
    if ($right === 'Year not specified') return -1;
    return strcmp($right, $left);
});

ksort($disciplinesList, SORT_NATURAL | SORT_FLAG_CASE);

require_once __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --primary-green: #0a4d2e;
        --accent-green: #25a264;
        --light-bg: #f8faf9;
        --card-border: #e2e8f0;
        --text-main: #1a202c;
        --text-muted: #64748b;
        --shadow-sm: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
        --radius: 12px;
    }

    .training-records-page {
        background-color: var(--light-bg);
        padding: 3rem 0;
    }

    .training-records-intro {
        max-width: 900px;
        margin: 0 auto 3rem auto;
        text-align: center;
    }
    .training-records-intro span {
        color: var(--accent-green);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1.5px;
        font-size: 0.85rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .training-records-intro h2 {
        font-size: 2.25rem;
        color: var(--primary-green);
        margin: 0 0 1.5rem 0;
        font-weight: 800;
    }
    .training-records-intro__content {
        text-align: justify;
        text-justify: inter-word;
        color: var(--text-muted);
        line-height: 1.8;
        font-size: 1.05rem;
    }
    .training-records-intro__content p {
        margin-bottom: 1.25rem;
    }

    /* Hero Stats Bar */
    .training-records-hero__stats {
        display: flex;
        gap: 2rem;
        margin-top: 1.5rem;
    }
    .training-records-hero__stats div {
        display: flex;
        flex-direction: column;
    }
    .training-records-hero__stats strong {
        font-size: 2rem;
        color: #ffffff;
    }
    .training-records-hero__stats span {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Filters Toolbar */
    .training-records-toolbar {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 1.25rem;
        background: #ffffff;
        padding: 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--card-border);
        margin-bottom: 2.5rem;
    }
    .training-records-toolbar label {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-main);
    }
    .training-records-toolbar input,
    .training-records-toolbar select {
        padding: 0.75rem 1rem;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        outline: none;
        background: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .training-records-toolbar input:focus,
    .training-records-toolbar select:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 3px rgba(37, 162, 100, 0.15);
    }

    /* Accordion Level 1: Financial Year Group */
    .fy-group {
        background: #ffffff;
        border-radius: var(--radius);
        border: 1px solid var(--card-border);
        margin-bottom: 1.5rem;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .fy-group__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 2rem;
        background: #ffffff;
        cursor: pointer;
        border-bottom: 1px solid transparent;
        transition: background 0.2s;
        user-select: none;
    }
    .fy-group__head:hover { background: #f4f8f5; }
    .fy-group.is-open .fy-group__head { border-bottom-color: var(--card-border); }
    .fy-group__head h3 { margin: 0; font-size: 1.4rem; color: var(--primary-green); font-weight: 700; }
    .fy-group__head span { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
    .fy-group__head dl { display: flex; gap: 1.25rem; margin: 0; }
    .fy-group__head dl div {
        text-align: center;
        background: var(--light-bg);
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        border: 1px solid var(--card-border);
    }
    .fy-group__head dt { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }
    .fy-group__head dd { margin: 0; font-weight: 700; color: var(--text-main); }

    .fy-view-btn {
        background: var(--primary-green);
        color: #fff;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 30px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    .fy-view-btn i { transition: transform 0.25s ease; }
    .fy-group.is-open .fy-view-btn { background: var(--accent-green); }
    .fy-group.is-open .fy-view-btn i { transform: rotate(180deg); }

    /* Accordion Level 2: Discipline Group */
    .fy-body[hidden] { display: none !important; }
    .fy-body { padding: 1.5rem 2rem; background: #fbfdfc; }
    .discipline-group {
        border: 1px solid var(--card-border);
        border-radius: 8px;
        background: #ffffff;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    .discipline-group:last-child { margin-bottom: 0; }
    .discipline-group__head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: #f8faf8;
        cursor: pointer;
        user-select: none;
    }
    .discipline-group__head h4 { margin: 0; font-size: 1.05rem; color: var(--primary-green); }
    .discipline-view-btn {
        background: transparent;
        border: 1px solid var(--card-border);
        color: var(--text-main);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
    }
    .discipline-view-btn i { transition: transform 0.25s ease; }
    .discipline-group.is-open .discipline-view-btn i { transform: rotate(180deg); }

    /* Table Styles */
    .discipline-body[hidden] { display: none !important; }
    .training-table-wrapper { overflow-x: auto; background: #ffffff; }
    .training-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
    .training-table th {
        background-color: var(--light-bg);
        color: var(--primary-green);
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 2px solid var(--card-border);
        white-space: nowrap;
    }
    .training-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--card-border);
        color: var(--text-main);
        vertical-align: middle;
    }
    .training-table tbody tr:hover { background-color: #f4f8f5; }
    .training-table .col-sl { width: 60px; text-align: center; }
    .training-table .col-date { white-space: nowrap; }
    .training-table .col-participants { text-align: center; font-weight: 600; }
    .training-table .col-actions { text-align: center; width: 140px; }

    .btn-view-photos {
        background: transparent;
        border: 1px solid var(--accent-green);
        color: var(--accent-green);
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
    }
    .btn-view-photos:hover:not(:disabled) { background: var(--accent-green); color: #ffffff; }
    .btn-view-photos:disabled { border-color: var(--card-border); color: var(--text-muted); cursor: not-allowed; opacity: 0.5; }

    /* Modal Lightbox Popup */
    .training-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(10, 25, 18, 0.75);
        backdrop-filter: blur(4px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 999999;
        padding: 1.5rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }
    .training-modal-backdrop.is-active { opacity: 1; pointer-events: auto; }
    .training-modal-window {
        background: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 800px;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
        padding: 2rem;
        box-shadow: 0 20px 30px rgba(0, 0, 0, 0.25);
    }
    .training-modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #f1f5f9;
        border: none;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        font-size: 1.25rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
    }
    .training-modal-photos {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1.25rem;
    }
    .training-modal-photos a {
        display: block;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-sm);
        height: 160px;
    }
    .training-modal-photos img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
    .training-modal-photos img:hover { transform: scale(1.05); }

    .modal-stats-badge {
        display: inline-block;
        background: #f1f5f9;
        padding: 0.25rem 0.6rem;
        border-radius: 4px;
        font-size: 0.85rem;
        margin-right: 0.5rem;
        margin-top: 0.4rem;
    }
    .alert-empty {
        text-align: center;
        padding: 2.5rem;
        background: #ffffff;
        border: 1px dashed var(--card-border);
        border-radius: var(--radius);
        color: var(--text-muted);
        font-size: 1.05rem;
    }

    @media (max-width: 768px) {
        .training-records-toolbar { grid-template-columns: 1fr; }
        .fy-group__head { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .fy-group__head dl { width: 100%; justify-content: space-between; }
    }
</style>

<!-- Dynamic Hero Banner with Baseline + Database Totals -->
<section class="page-hero training-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">Training Records</p>
        <p>View KVK Kokrajhar training programmes with farmer reach, participant data, and training photographs.</p>
        <div class="training-records-hero__stats">
            <div><strong><?= e(number_format($officialTrainingsOrganised)) ?></strong><span>Total Trainings organised</span></div>
            <div><strong><?= e(number_format($officialFarmersTrained)) ?></strong><span>Total Farmers trained</span></div>
        </div>
    </div>
</section>

<section class="section training-records-page">
    <div class="container">
        <div class="section-head training-records-intro">
            <span>Official Training Details</span>
            <h2>Training programmes at a glance</h2>
            <div class="training-records-intro__content">
                <p>The training programmes of Krishi Vigyan Kendra (KVK) Kokrajhar are closely linked with the On-Farm Testing (OFT) and Frontline Demonstrations (FLD) undertaken across the district every year.</p>
                <p>Alongside these mandatory operational linkages, KVK Kokrajhar organizes need-based training modules tailored to address the region's specific agro-climatic requirements and farming challenges.</p>
            </div>
        </div>
       
        <!-- Filter Bar -->
        <div class="training-records-toolbar">
            <label>
                <span>Search records</span>
                <input type="search" id="trainingRecordSearch" placeholder="Search by name, location...">
            </label>
            <label>
                <span>Discipline</span>
                <select id="trainingRecordDiscipline">
                    <option value="">All disciplines</option>
                    <?php foreach (array_keys($disciplinesList) as $discName): ?>
                        <option value="<?= e(strtolower($discName)) ?>"><?= e($discName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Financial Year</span>
                <select id="trainingRecordYear">
                    <option value="">All years</option>
                    <?php foreach (array_keys($recordsByFYAndDiscipline) as $fy): ?>
                        <option value="<?= e(strtolower($fy)) ?>"><?= e($fy) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <!-- Nested Records Accordion -->
        <div class="training-records-list" id="training-records-list">
            <?php foreach ($recordsByFYAndDiscipline as $fy => $fyGroup): ?>
                <!-- LEVEL 1: FINANCIAL YEAR -->
                <section class="fy-group" data-fy="<?= e(strtolower($fy)) ?>">
                    <div class="fy-group__head" role="button" tabindex="0" aria-expanded="false">
                        <div>
                            <span>Financial Year</span>
                            <h3><?= e($fy) ?></h3>
                        </div>
                        <dl>
                            <div><dt>Trainings</dt><dd><?= e($fyGroup['total_records']) ?></dd></div>
                            <div><dt>Participants</dt><dd><?= e(number_format($fyGroup['total_participants'])) ?></dd></div>
                            <div><dt>Photos</dt><dd><?= e(number_format($fyGroup['total_photos'])) ?></dd></div>
                        </dl>
                        <button class="fy-view-btn" type="button" tabindex="-1">
                            <span>View</span> <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>

                    <div class="fy-body" hidden>
                        <?php foreach ($fyGroup['disciplines'] as $disciplineName => $disciplineGroup): ?>
                            <!-- LEVEL 2: DISCIPLINE -->
                            <div class="discipline-group" data-discipline="<?= e(strtolower($disciplineName)) ?>">
                                <div class="discipline-group__head" role="button" tabindex="0" aria-expanded="false">
                                    <h4><?= e($disciplineName) ?> (<?= count($disciplineGroup['records']) ?> Trainings)</h4>
                                    <button class="discipline-view-btn" type="button" tabindex="-1">
                                        <span>View</span> <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>

                                <div class="discipline-body" hidden>
                                    <div class="training-table-wrapper">
                                        <table class="training-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-sl">Sl. No.</th>
                                                    <th>Training Schedule</th>
                                                    <th>Type / Thematic Area</th>
                                                    <th class="col-date">Schedule</th>
                                                    <th>Location</th>
                                                    <th class="col-participants">Total Participants</th>
                                                    <th class="col-actions">Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $slNo = 1;
                                                foreach ($disciplineGroup['records'] as $record): 
                                                    $photos = training_records_list_photos($record);
                                                    $photoCount = count($photos);
                                                    $trainingName = (string) ($record['training_name'] ?: ($record['title'] ?? 'Training Record'));
                                                    $recordDate = training_records_list_date($record['training_date'] ?? '');
                                                    $recordLocation = (string) ($record['training_location'] ?: 'Not specified');
                                                    $locationType = (string) ($record['location_type'] ?? '');
                                                    $startDate = training_records_list_date($record['duration_from'] ?? ($record['training_date'] ?? ''));
                                                    $endDate = training_records_list_date($record['duration_to'] ?? ($record['training_date'] ?? ''));
                                                    $durationDays = (int) ($record['duration_days'] ?? 0);
                                                    $totalParticipants = training_records_list_total($record);

                                                    $searchText = strtolower(trim($trainingName . ' ' . $recordLocation . ' ' . $disciplineName . ' ' . ($record['training_type'] ?? '') . ' ' . ($record['training_area'] ?? '') . ' ' . $recordDate . ' ' . $fy));
                                                    
                                                    $modalData = [
                                                        'title' => $trainingName,
                                                        'discipline' => $disciplineName,
                                                        'fy' => $fy,
                                                        'date' => $recordDate,
                                                        'start_date' => $startDate,
                                                        'end_date' => $endDate,
                                                        'duration_days' => $durationDays,
                                                        'training_type' => (string) ($record['training_type'] ?? ''),
                                                        'thematic_area' => (string) ($record['training_area'] ?? ''),
                                                        'location_type' => $locationType,
                                                        'location' => $recordLocation,
                                                        'trainee_category' => (string) ($record['trainee_category'] ?? ''),
                                                        'participants' => [
                                                            'general_male' => (int) ($record['general_male'] ?? 0),
                                                            'general_female' => (int) ($record['general_female'] ?? 0),
                                                            'obc_male' => (int) ($record['obc_male'] ?? 0),
                                                            'obc_female' => (int) ($record['obc_female'] ?? 0),
                                                            'scst_male' => (int) ($record['scst_male'] ?? 0),
                                                            'scst_female' => (int) ($record['scst_female'] ?? 0),
                                                            'others_male' => (int) ($record['others_male'] ?? 0),
                                                            'others_female' => (int) ($record['others_female'] ?? 0),
                                                            'total' => $totalParticipants
                                                        ],
                                                        'photos' => $photos
                                                    ];
                                                ?>
                                                    <tr class="training-table-row" data-search="<?= e($searchText) ?>" data-discipline="<?= e(strtolower($disciplineName)) ?>" data-fy="<?= e(strtolower($fy)) ?>">
                                                        <td class="col-sl"><?= $slNo++ ?></td>
                                                        <td>
                                                            <strong><?= e($trainingName) ?></strong>
                                                            <?php if (!empty($record['summary'])): ?>
                                                                <br><small style="color: var(--text-muted);"><?= e($record['summary']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><strong><?= e((string) ($record['training_type'] ?? 'Not specified')) ?></strong><br><small><?= e((string) ($record['training_area'] ?? '')) ?></small></td>
                                                        <td class="col-date"><?= e($startDate) ?><br><small>to <?= e($endDate) ?><?= $durationDays > 0 ? ' · ' . e((string) $durationDays) . ' day(s)' : '' ?></small></td>
                                                        <td><?= $locationType !== '' ? '<small>' . e($locationType) . '</small><br>' : '' ?><?= e($recordLocation) ?></td>
                                                        <td class="col-participants"><?= e(number_format($totalParticipants)) ?></td>
                                                        <td class="col-actions">
                                                            <button 
                                                                class="btn-view-photos btn-open-modal" 
                                                                type="button" 
                                                                data-json="<?= e(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
                                                            >
                                                                <i class="fa-solid fa-eye"></i>
                                                                <span>View<?= $photoCount > 0 ? " ($photoCount photo" . ($photoCount === 1 ? ')' : 's)') : '' ?></span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <div class="alert-empty" id="trainingRecordsEmpty" hidden>
            <i class="fa-solid fa-circle-exclamation" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
            No training records match your search query.
        </div>
    </div>
</section>

<!-- Lightbox Modal -->
<div class="training-modal-backdrop" id="trainingModalBackdrop" aria-hidden="true">
    <div class="training-modal-window" role="dialog" aria-modal="true">
        <button class="training-modal-close" id="trainingModalClose" type="button" aria-label="Close Popup">&times;</button>
        <div id="trainingModalContent"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Level 1 Accordion
    const toggleFYGroup = (head) => {
        const group = head.closest('.fy-group');
        const body = group.querySelector('.fy-body');
        const isOpen = group.classList.contains('is-open');

        document.querySelectorAll('.fy-group').forEach(g => {
            g.classList.remove('is-open');
            g.querySelector('.fy-group__head').setAttribute('aria-expanded', 'false');
            g.querySelector('.fy-body').hidden = true;
            const viewBtn = g.querySelector('.fy-view-btn span');
            if (viewBtn) viewBtn.textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            const viewBtn = group.querySelector('.fy-view-btn span');
            if (viewBtn) viewBtn.textContent = 'Hide';
        }
    };

    document.querySelectorAll('.fy-group__head').forEach(head => {
        head.addEventListener('click', () => toggleFYGroup(head));
        head.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleFYGroup(head);
            }
        });
    });

    // Level 2 Accordion
    const toggleDisciplineGroup = (head) => {
        const group = head.closest('.discipline-group');
        const body = group.querySelector('.discipline-body');
        const isOpen = group.classList.contains('is-open');

        const parentFyBody = group.closest('.fy-body');
        parentFyBody.querySelectorAll('.discipline-group').forEach(g => {
            g.classList.remove('is-open');
            g.querySelector('.discipline-group__head').setAttribute('aria-expanded', 'false');
            g.querySelector('.discipline-body').hidden = true;
            const viewBtn = g.querySelector('.discipline-view-btn span');
            if (viewBtn) viewBtn.textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.discipline-view-btn span').textContent = 'Hide';
        }
    };

    document.querySelectorAll('.discipline-group__head').forEach(head => {
        head.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDisciplineGroup(head);
        });
        head.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                toggleDisciplineGroup(head);
            }
        });
    });

    // Modal
    const modalBackdrop = document.getElementById('trainingModalBackdrop');
    const modalContent = document.getElementById('trainingModalContent');
    const modalClose = document.getElementById('trainingModalClose');
    const safeText = (value) => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));

    const openModal = (data) => {
        let photosHtml = '';
        if (data.photos && data.photos.length > 0) {
            photosHtml = `
                <h4 style="margin-top: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-green);">Training Photographs</h4>
                <div class="training-modal-photos">
                    ${data.photos.map(p => `
                        <a href="${p}" target="_blank" title="Click to view full image">
                            <img src="${p}" alt="Training Image">
                        </a>
                    `).join('')}
                </div>
            `;
        }

        modalContent.innerHTML = `
            <h3 style="margin-top: 0; color: var(--primary-green); font-size: 1.35rem; padding-right: 2rem;">${safeText(data.title)}</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0.75rem;">
                <strong>Financial Year:</strong> ${safeText(data.fy)} | <strong>Discipline:</strong> ${safeText(data.discipline)}<br>
                <strong>Training Type:</strong> ${safeText(data.training_type || 'Not specified')} | <strong>Thematic Area:</strong> ${safeText(data.thematic_area || 'Not specified')}<br>
                <strong>Schedule:</strong> ${safeText(data.start_date)} to ${safeText(data.end_date)}${data.duration_days ? ` (${data.duration_days} day${data.duration_days === 1 ? '' : 's'})` : ''}<br>
                <strong>Location:</strong> ${safeText(data.location_type || 'Not specified')} - ${safeText(data.location)}<br>
                <strong>Trainee Classification:</strong> ${safeText(data.trainee_category || 'Not specified')}
            </p>
            <div>
                <span class="modal-stats-badge"><strong>General M/F:</strong> ${data.participants.general_male} / ${data.participants.general_female}</span>
                <span class="modal-stats-badge"><strong>OBC M/F:</strong> ${data.participants.obc_male} / ${data.participants.obc_female}</span>
                <span class="modal-stats-badge"><strong>SC/ST M/F:</strong> ${data.participants.scst_male} / ${data.participants.scst_female}</span>
                <span class="modal-stats-badge"><strong>Others M/F:</strong> ${data.participants.others_male} / ${data.participants.others_female}</span>
                <span class="modal-stats-badge" style="background: #e2e8f0;"><strong>Total:</strong> ${data.participants.total}</span>
            </div>
            <hr style="border: 0; border-top: 1px solid var(--card-border); margin-top: 1rem;">
            ${photosHtml}
        `;

        modalBackdrop.classList.add('is-active');
        modalBackdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modalBackdrop.classList.remove('is-active');
        modalBackdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.btn-open-modal').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const rawJson = btn.getAttribute('data-json');
            if (rawJson) {
                try {
                    const data = JSON.parse(rawJson);
                    openModal(data);
                } catch (err) {
                    console.error('Invalid modal dataset', err);
                }
            }
        });
    });

    if (modalClose) modalClose.addEventListener('click', closeModal);
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalBackdrop.classList.contains('is-active')) {
            closeModal();
        }
    });

    // Dynamic Search Filter
    const searchInput = document.getElementById('trainingRecordSearch');
    const disciplineSelect = document.getElementById('trainingRecordDiscipline');
    const yearSelect = document.getElementById('trainingRecordYear');
    const emptyStateAlert = document.getElementById('trainingRecordsEmpty');

    const runFilter = () => {
        const q = searchInput.value.trim().toLowerCase();
        const d = disciplineSelect.value;
        const y = yearSelect.value;
        let overallVisibleCount = 0;

        document.querySelectorAll('.fy-group').forEach(fyGroup => {
            let fyHasVisible = false;

            fyGroup.querySelectorAll('.discipline-group').forEach(discGroup => {
                let discHasVisible = false;

                discGroup.querySelectorAll('.training-table-row').forEach(row => {
                    const matchQ = !q || row.dataset.search.includes(q);
                    const matchD = !d || row.dataset.discipline === d;
                    const matchY = !y || row.dataset.fy === y;

                    const visible = matchQ && matchD && matchY;
                    row.hidden = !visible;
                    if (visible) {
                        discHasVisible = true;
                        overallVisibleCount++;
                    }
                });

                discGroup.hidden = !discHasVisible;
                if (discHasVisible) {
                    fyHasVisible = true;
                    if (q || d || y) {
                        discGroup.classList.add('is-open');
                        discGroup.querySelector('.discipline-body').hidden = false;
                        discGroup.querySelector('.discipline-view-btn span').textContent = 'Hide';
                    }
                }
            });

            fyGroup.hidden = !fyHasVisible;
            if (fyHasVisible && (q || d || y)) {
                fyGroup.classList.add('is-open');
                fyGroup.querySelector('.fy-body').hidden = false;
                fyGroup.querySelector('.fy-view-btn span').textContent = 'Hide';
            }
        });

        if (emptyStateAlert) {
            emptyStateAlert.hidden = overallVisibleCount > 0;
        }
    };

    if (searchInput && disciplineSelect && yearSelect) {
        searchInput.addEventListener('input', runFilter);
        disciplineSelect.addEventListener('change', runFilter);
        yearSelect.addEventListener('change', runFilter);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
