<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'On-Farm Testing (OFT) | ' . APP_NAME;

// Define Historical Baseline Counts for OFT
define('BASELINE_OFTS', 185);
define('BASELINE_OFT_FARMERS', 2450);

// 1. Ensure OFT Table Structure Exists
function oft_records_list_ensure_table(?PDO $pdo): void
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
        'year' => 'ALTER TABLE oft_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE oft_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'season' => 'ALTER TABLE oft_records ADD COLUMN season VARCHAR(80) NULL AFTER discipline',
        'result_file' => 'ALTER TABLE oft_records ADD COLUMN result_file VARCHAR(255) NULL AFTER results_summary',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists
        }
    }

    foreach (['photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $column) {
        try {
            $pdo->exec("ALTER TABLE oft_records ADD COLUMN {$column} VARCHAR(255) NULL");
        } catch (Throwable $exception) {
            // Column already exists
        }
    }
}

// 2. Fetch All Active OFT Records
function oft_records_list_all(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    oft_records_list_ensure_table($pdo);
    $stmt = $pdo->query("SELECT oft.*, a.title AS activity_title, a.summary AS activity_summary, a.image_path
        FROM oft_records oft
        LEFT JOIN activities a ON a.id = oft.activity_id
        WHERE oft.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY COALESCE(oft.oft_date, DATE(oft.created_at)) DESC, oft.discipline ASC, oft.id DESC");

    return $stmt ? $stmt->fetchAll() : [];
}

// Helper: Extract Associated Photos
function oft_records_list_photos(array $record): array
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
function oft_records_list_date(?string $date): string
{
    if (!$date) {
        return 'Date not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

// Helper: Format Financial Year
function oft_records_list_financial_year(?string $date): string
{
    return $date ? current_financial_year($date) : 'Year not specified';
}

function oft_records_list_record_financial_year(array $record): string
{
    $year = trim((string) ($record['year'] ?? ''));
    if ($year !== '') {
        return financial_year_normalize($year);
    }

    $date = trim((string) ($record['oft_date'] ?? ''));
    if ($date === '') {
        $date = trim((string) ($record['created_at'] ?? ''));
    }

    return oft_records_list_financial_year($date !== '' ? $date : null);
}

// Fetch Records
$oftRecords = oft_records_list_all();

// Dynamic Calculations
$dbOftCount = count($oftRecords);
$dbFarmersCount = 0;
$dbTrialsCount = 0;

$heroPhoto = '';
$totalPhotos = 0;
$disciplinesList = [];
$recordsByFYAndDiscipline = [];

foreach ($oftRecords as $record) {
    $recordPhotos = oft_records_list_photos($record);
    
    $trials = (int) ($record['number_of_trials'] ?? 0);
    $farmers = (int) ($record['number_of_farmers'] ?? 0);

    $dbTrialsCount += $trials;
    $dbFarmersCount += $farmers;
    $totalPhotos += count($recordPhotos);
    
    if (!$heroPhoto && $recordPhotos) {
        $heroPhoto = $recordPhotos[0];
    }

    $financialYear = oft_records_list_record_financial_year($record);
    $discipline = trim((string) ($record['discipline'] ?? 'Agronomy')) ?: 'Agronomy';
    $disciplinesList[$discipline] = true;

    if (!isset($recordsByFYAndDiscipline[$financialYear])) {
        $recordsByFYAndDiscipline[$financialYear] = [
            'total_records' => 0,
            'total_trials' => 0,
            'total_farmers' => 0,
            'total_photos' => 0,
            'disciplines' => []
        ];
    }

    if (!isset($recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline])) {
        $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline] = [
            'records' => [],
            'trials' => 0,
            'farmers' => 0,
            'photos' => 0
        ];
    }

    $recordsByFYAndDiscipline[$financialYear]['total_records']++;
    $recordsByFYAndDiscipline[$financialYear]['total_trials'] += $trials;
    $recordsByFYAndDiscipline[$financialYear]['total_farmers'] += $farmers;
    $recordsByFYAndDiscipline[$financialYear]['total_photos'] += count($recordPhotos);

    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['records'][] = $record;
    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['trials'] += $trials;
    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['farmers'] += $farmers;
    $recordsByFYAndDiscipline[$financialYear]['disciplines'][$discipline]['photos'] += count($recordPhotos);
}

// Aggregated Totals
$officialOftsOrganised = BASELINE_OFTS + $dbOftCount;
$officialFarmersCovered = BASELINE_OFT_FARMERS + $dbFarmersCount;

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

    .oft-records-page {
        background-color: var(--light-bg);
        padding: 3rem 0;
    }

    .oft-records-intro {
        max-width: 900px;
        margin: 0 auto 3rem auto;
        text-align: center;
    }
    .oft-records-intro span {
        color: var(--accent-green);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1.5px;
        font-size: 0.85rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .oft-records-intro h2 {
        font-size: 2.25rem;
        color: var(--primary-green);
        margin: 0 0 1.5rem 0;
        font-weight: 800;
    }
    .oft-records-intro__content {
        text-align: justify;
        text-justify: inter-word;
        color: var(--text-muted);
        line-height: 1.8;
        font-size: 1.05rem;
    }
    .oft-records-intro__content p {
        margin-bottom: 1.25rem;
    }

    /* Hero Stats Bar */
    .oft-records-hero__stats {
        display: flex;
        gap: 2rem;
        margin-top: 1.5rem;
    }
    .oft-records-hero__stats div {
        display: flex;
        flex-direction: column;
    }
    .oft-records-hero__stats strong {
        font-size: 2rem;
        color: #ffffff;
    }
    .oft-records-hero__stats span {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Filters Toolbar */
    .oft-records-toolbar {
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
    .oft-records-toolbar label {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-main);
    }
    .oft-records-toolbar input,
    .oft-records-toolbar select {
        padding: 0.75rem 1rem;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        outline: none;
        background: #fff;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .oft-records-toolbar input:focus,
    .oft-records-toolbar select:focus {
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
    .oft-table-wrapper { overflow-x: auto; background: #ffffff; }
    .oft-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left; }
    .oft-table th {
        background-color: var(--light-bg);
        color: var(--primary-green);
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 2px solid var(--card-border);
        white-space: nowrap;
    }
    .oft-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--card-border);
        color: var(--text-main);
        vertical-align: middle;
    }
    .oft-table tbody tr:hover { background-color: #f4f8f5; }
    .oft-table .col-sl { width: 50px; text-align: center; }
    .oft-table .col-center { text-align: center; font-weight: 600; }
    .oft-table .col-actions { text-align: center; width: 140px; }

    .btn-view-details {
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
    .btn-view-details:hover { background: var(--accent-green); color: #ffffff; }

    /* Modal Lightbox Popup */
    .oft-modal-backdrop {
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
    .oft-modal-backdrop.is-active { opacity: 1; pointer-events: auto; }
    .oft-modal-window {
        background: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 850px;
        max-height: 85vh;
        overflow-y: auto;
        position: relative;
        padding: 2rem;
        box-shadow: 0 20px 30px rgba(0, 0, 0, 0.25);
    }
    .oft-modal-close {
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
    .oft-modal-photos {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1.25rem;
    }
    .oft-modal-photos a {
        display: block;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-sm);
        height: 160px;
    }
    .oft-modal-photos img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
    .oft-modal-photos img:hover { transform: scale(1.05); }

    .modal-section {
        margin-top: 1rem;
        padding: 1rem;
        background: #f8faf8;
        border-radius: 8px;
        border: 1px solid var(--card-border);
    }
    .modal-section h5 {
        margin: 0 0 0.5rem 0;
        color: var(--primary-green);
        font-size: 0.95rem;
        font-weight: 700;
    }
    .modal-section p {
        margin: 0;
        font-size: 0.9rem;
        color: var(--text-main);
        line-height: 1.6;
    }

    .modal-stats-badge {
        display: inline-block;
        background: #f1f5f9;
        padding: 0.3rem 0.7rem;
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
        .oft-records-toolbar { grid-template-columns: 1fr; }
        .fy-group__head { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .fy-group__head dl { width: 100%; justify-content: space-between; }
    }
</style>

<!-- Hero Section -->
<section class="page-hero oft-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">On-Farm Testing (OFT)</p>
        <p>Testing and refining location-specific agricultural technologies directly on farmers' fields.</p>
        <div class="oft-records-hero__stats">
            <div><strong><?= e(number_format($officialOftsOrganised)) ?></strong><span>Total OFTs Conducted</span></div>
            <div><strong><?= e(number_format($officialFarmersCovered)) ?></strong><span>Farmers Associated</span></div>
        </div>
    </div>
</section>

<section class="section oft-records-page">
    <div class="container">
        <div class="section-head oft-records-intro">
            <span>On-Farm Testing Details</span>
            <h2>OFT Interventions at a Glance</h2>
            <div class="oft-records-intro__content">
                <p>On-Farm Testing (OFT) aims to test and evaluate location-specific agricultural technologies under real farming conditions to solve specific production constraints faced by local farmers.</p>
                <p>KVK Kokrajhar assesses candidate technologies across multiple disciplines—such as Agronomy, Horticulture, Plant Protection, and Animal Husbandry—to determine their suitability prior to large-scale dissemination via Frontline Demonstrations (FLD).</p>
            </div>
        </div>
       
        <!-- Filter Bar -->
        <div class="oft-records-toolbar">
            <label>
                <span>Search records</span>
                <input type="search" id="oftRecordSearch" placeholder="Search title, technology, location...">
            </label>
            <label>
                <span>Discipline</span>
                <select id="oftRecordDiscipline">
                    <option value="">All disciplines</option>
                    <?php foreach (array_keys($disciplinesList) as $discName): ?>
                        <option value="<?= e(strtolower($discName)) ?>"><?= e($discName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Financial Year</span>
                <select id="oftRecordYear">
                    <option value="">All years</option>
                    <?php foreach (array_keys($recordsByFYAndDiscipline) as $fy): ?>
                        <option value="<?= e(strtolower($fy)) ?>"><?= e($fy) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <!-- Nested Records Accordion -->
        <div class="oft-records-list" id="oft-records-list">
            <?php foreach ($recordsByFYAndDiscipline as $fy => $fyGroup): ?>
                <!-- LEVEL 1: FINANCIAL YEAR -->
                <section class="fy-group" data-fy="<?= e(strtolower($fy)) ?>">
                    <div class="fy-group__head" role="button" tabindex="0" aria-expanded="false">
                        <div>
                            <span>Financial Year</span>
                            <h3><?= e($fy) ?></h3>
                        </div>
                        <dl>
                            <div><dt>OFT Trials</dt><dd><?= e($fyGroup['total_records']) ?></dd></div>
                            <div><dt>Total Locations</dt><dd><?= e(number_format($fyGroup['total_trials'])) ?></dd></div>
                            <div><dt>Farmers Covered</dt><dd><?= e(number_format($fyGroup['total_farmers'])) ?></dd></div>
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
                                    <h4><?= e($disciplineName) ?> (<?= count($disciplineGroup['records']) ?> OFTs)</h4>
                                    <button class="discipline-view-btn" type="button" tabindex="-1">
                                        <span>View</span> <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>

                                <div class="discipline-body" hidden>
                                    <div class="oft-table-wrapper">
                                        <table class="oft-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-sl">Sl. No.</th>
                                                    <th>Title of On-Farm Testing</th>
                                                    <th>Crop</th>
                                                    <th>Thematic Area</th>
                                                    <th>Season</th>
                                                    <th class="col-center">Trials</th>
                                                    <th class="col-center">Farmers</th>
                                                    <th class="col-actions">Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $slNo = 1;
                                                foreach ($disciplineGroup['records'] as $record): 
                                                    $photos = oft_records_list_photos($record);
                                                    $oftTitle = (string) ($record['oft_title'] ?: ($record['activity_title'] ?? 'OFT Project'));
                                                    $crop = (string) ($record['crop'] ?? '');
                                                    $thematicArea = (string) ($record['thematic_area'] ?: 'General Agriculture');
                                                    $season = (string) (($record['season'] ?? '') ?: ($record['season_year'] ?? ''));
                                                    $location = (string) ($record['location'] ?: 'Not specified');
                                                    $recordDate = oft_records_list_date($record['oft_date'] ?? '');
                                                    $trials = (int) ($record['number_of_trials'] ?? 0);
                                                    $farmers = (int) ($record['number_of_farmers'] ?? 0);
                                                    $problem = (string) ($record['problem_diagnosed'] ?? '');
                                                    $technology = (string) ($record['technology_tested'] ?? '');
                                                    $results = (string) ($record['results_summary'] ?? '');

                                                    $searchText = strtolower(trim($oftTitle . ' ' . $crop . ' ' . $thematicArea . ' ' . $season . ' ' . $location . ' ' . $problem . ' ' . $technology . ' ' . $disciplineName . ' ' . $fy));
                                                    
                                                    $modalData = [
                                                        'title' => $oftTitle,
                                                        'discipline' => $disciplineName,
                                                        'crop' => $crop,
                                                        'thematic_area' => $thematicArea,
                                                        'season' => $season,
                                                        'fy' => $fy,
                                                        'date' => $recordDate,
                                                        'location' => $location,
                                                        'trials' => $trials,
                                                        'farmers' => $farmers,
                                                        'problem' => $problem,
                                                        'technology' => $technology,
                                                        'results' => $results,
                                                        'photos' => $photos
                                                    ];
                                                ?>
                                                    <tr class="oft-table-row" data-search="<?= e($searchText) ?>" data-discipline="<?= e(strtolower($disciplineName)) ?>" data-fy="<?= e(strtolower($fy)) ?>">
                                                        <td class="col-sl"><?= $slNo++ ?></td>
                                                        <td>
                                                            <strong><?= e($oftTitle) ?></strong>
                                                            <?php if ($location !== 'Not specified'): ?>
                                                                <br><small style="color: var(--text-muted);"><i class="fa-solid fa-location-dot"></i> <?= e($location) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= e($crop !== '' ? $crop : 'Not specified') ?></td>
                                                        <td><?= e($thematicArea) ?></td>
                                                        <td><?= e($season !== '' ? $season : 'Not specified') ?></td>
                                                        <td class="col-center"><?= e($trials) ?></td>
                                                        <td class="col-center"><?= e($farmers) ?></td>
                                                        <td class="col-actions">
                                                            <button 
                                                                class="btn-view-details btn-open-modal" 
                                                                type="button" 
                                                                data-json="<?= e(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
                                                            >
                                                                <i class="fa-solid fa-circle-info"></i>
                                                                <span>View Details</span>
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
        <div class="alert-empty" id="oftRecordsEmpty" hidden>
            <i class="fa-solid fa-circle-exclamation" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
            No OFT records match your search query.
        </div>
    </div>
</section>

<!-- Lightbox Modal -->
<div class="oft-modal-backdrop" id="oftModalBackdrop" aria-hidden="true">
    <div class="oft-modal-window" role="dialog" aria-modal="true">
        <button class="oft-modal-close" id="oftModalClose" type="button" aria-label="Close Popup">&times;</button>
        <div id="oftModalContent"></div>
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
    const modalBackdrop = document.getElementById('oftModalBackdrop');
    const modalContent = document.getElementById('oftModalContent');
    const modalClose = document.getElementById('oftModalClose');

    const openModal = (data) => {
        let photosHtml = '';
        if (data.photos && data.photos.length > 0) {
            photosHtml = `
                <h4 style="margin-top: 1.25rem; margin-bottom: 0.5rem; color: var(--primary-green);">Field Photographs</h4>
                <div class="oft-modal-photos">
                    ${data.photos.map(p => `
                        <a href="${p}" target="_blank" title="Click to view full image">
                            <img src="${p}" alt="OFT Field Image">
                        </a>
                    `).join('')}
                </div>
            `;
        }

        modalContent.innerHTML = `
            <h3 style="margin-top: 0; color: var(--primary-green); font-size: 1.35rem; padding-right: 2rem;">${data.title}</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0.75rem;">
                <strong>Discipline:</strong> ${data.discipline} | <strong>Crop:</strong> ${data.crop || 'Not specified'}<br>
                <strong>Thematic Area:</strong> ${data.thematic_area} | <strong>Season:</strong> ${data.season || 'Not specified'}<br>
                <strong>Financial Year:</strong> ${data.fy} | <strong>Date:</strong> ${data.date} | <strong>Location:</strong> ${data.location}
            </p>
            <div>
                <span class="modal-stats-badge"><strong>Number of Trials:</strong> ${data.trials}</span>
                <span class="modal-stats-badge"><strong>Farmers Covered:</strong> ${data.farmers}</span>
            </div>

            ${data.problem ? `
                <div class="modal-section">
                    <h5>Problem Diagnosed</h5>
                    <p>${data.problem}</p>
                </div>
            ` : ''}

            ${data.technology ? `
                <div class="modal-section">
                    <h5>Technology Tested / Interventions</h5>
                    <p>${data.technology}</p>
                </div>
            ` : ''}

            ${data.results ? `
                <div class="modal-section">
                    <h5>Results / Key Findings</h5>
                    <p>${data.results}</p>
                </div>
            ` : ''}

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
    const searchInput = document.getElementById('oftRecordSearch');
    const disciplineSelect = document.getElementById('oftRecordDiscipline');
    const yearSelect = document.getElementById('oftRecordYear');
    const emptyStateAlert = document.getElementById('oftRecordsEmpty');

    const runFilter = () => {
        const q = searchInput.value.trim().toLowerCase();
        const d = disciplineSelect.value;
        const y = yearSelect.value;
        let overallVisibleCount = 0;

        document.querySelectorAll('.fy-group').forEach(fyGroup => {
            let fyHasVisible = false;

            fyGroup.querySelectorAll('.discipline-group').forEach(discGroup => {
                let discHasVisible = false;

                discGroup.querySelectorAll('.oft-table-row').forEach(row => {
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
