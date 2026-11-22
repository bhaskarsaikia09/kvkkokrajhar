<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Technology Showcasing Details | ' . APP_NAME;

function technology_showcasing_details_ensure_table(?PDO $pdo): void
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

    foreach ([
        'activity_id' => 'ALTER TABLE technology_showcasing_records ADD COLUMN activity_id INT NULL UNIQUE AFTER id',
        'year' => 'ALTER TABLE technology_showcasing_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE technology_showcasing_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'variety' => 'ALTER TABLE technology_showcasing_records ADD COLUMN variety VARCHAR(180) NULL AFTER crop',
        'farmers_involved' => 'ALTER TABLE technology_showcasing_records ADD COLUMN farmers_involved INT DEFAULT 0 AFTER variety',
        'area_covered' => 'ALTER TABLE technology_showcasing_records ADD COLUMN area_covered VARCHAR(120) NULL AFTER farmers_involved',
        'production_tonnes' => 'ALTER TABLE technology_showcasing_records ADD COLUMN production_tonnes DECIMAL(12,3) DEFAULT 0 AFTER area_covered',
        'is_active' => 'ALTER TABLE technology_showcasing_records ADD COLUMN is_active TINYINT(1) DEFAULT 1',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function technology_showcasing_details_ensure_official_table(?PDO $pdo): void
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

function technology_showcasing_details_records(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    technology_showcasing_details_ensure_table($pdo);

    $stmt = $pdo->prepare("SELECT tsr.*, a.title, a.summary, a.image_path
        FROM technology_showcasing_records tsr
        LEFT JOIN activities a ON a.id = tsr.activity_id
        WHERE tsr.is_active = 1
            AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY tsr.year DESC, tsr.crop ASC, tsr.id DESC");
    $stmt->execute();

    $records = $stmt ? $stmt->fetchAll() : [];
    foreach ($records as &$record) {
        $record['source'] = 'technology_showcasing_records';
    }
    unset($record);

    return array_merge($records, technology_showcasing_details_official_records($pdo));
}

function technology_showcasing_details_official_records(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    technology_showcasing_details_ensure_official_table($pdo);
    $stmt = $pdo->prepare("SELECT id, financial_year, data_json, created_at
        FROM official_activity_records
        WHERE is_active = 1 AND record_type = 'technology_showcasing'
        ORDER BY financial_year DESC, created_at DESC, id DESC");
    $stmt->execute();

    $records = [];
    foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        if (!is_array($data)) {
            continue;
        }

        $records[] = [
            'id' => (int) ($row['id'] ?? 0),
            'activity_id' => 0,
            'year' => trim((string) ($data['year'] ?? $row['financial_year'] ?? '')),
            'crop' => trim((string) ($data['crop'] ?? '')),
            'variety' => trim((string) ($data['variety'] ?? '')),
            'farmers_involved' => max(0, (int) ($data['farmers_involved'] ?? 0)),
            'area_covered' => trim((string) ($data['area_covered'] ?? '')),
            'production_tonnes' => max(0, (float) ($data['production_tonnes'] ?? 0)),
            'title' => 'Technology Showcasing',
            'summary' => '',
            'image_path' => '',
            'source' => 'official_activity_records',
        ];
    }

    return $records;
}

function technology_showcasing_details_year(array $record): string
{
    $year = trim((string) ($record['year'] ?? ''));
    return $year !== '' ? financial_year_normalize($year) : 'Year not specified';
}

function technology_showcasing_details_decimal($value): string
{
    $formatted = number_format(max(0, (float) $value), 3);
    return rtrim(rtrim($formatted, '0'), '.');
}

function technology_showcasing_details_title(array $record): string
{
    $crop = trim((string) ($record['crop'] ?? ''));
    $variety = trim((string) ($record['variety'] ?? ''));

    if ($crop !== '' && $variety !== '') {
        return $crop . ' - ' . $variety;
    }

    return $crop !== '' ? $crop : ($variety !== '' ? $variety : 'Technology Showcasing');
}

$technologyRecords = technology_showcasing_details_records();
$recordsByYear = [];
$totalFarmers = 0;
$totalProduction = 0.0;
$crops = [];
$heroPhoto = 'assets/img/campus-1.jpg';

foreach ($technologyRecords as $record) {
    $year = technology_showcasing_details_year($record);
    $crop = trim((string) ($record['crop'] ?? '')) ?: 'Crop not specified';
    $farmers = max(0, (int) ($record['farmers_involved'] ?? 0));
    $production = max(0, (float) ($record['production_tonnes'] ?? 0));

    if (!isset($recordsByYear[$year])) {
        $recordsByYear[$year] = [
            'records' => [],
            'farmers' => 0,
            'production' => 0.0,
            'crops' => [],
        ];
    }

    if (!isset($recordsByYear[$year]['crops'][$crop])) {
        $recordsByYear[$year]['crops'][$crop] = [
            'records' => [],
            'farmers' => 0,
            'production' => 0.0,
        ];
    }

    $recordsByYear[$year]['records'][] = $record;
    $recordsByYear[$year]['farmers'] += $farmers;
    $recordsByYear[$year]['production'] += $production;
    $recordsByYear[$year]['crops'][$crop]['records'][] = $record;
    $recordsByYear[$year]['crops'][$crop]['farmers'] += $farmers;
    $recordsByYear[$year]['crops'][$crop]['production'] += $production;
    $crops[$crop] = true;

    $totalFarmers += $farmers;
    $totalProduction += $production;
    if ($heroPhoto === 'assets/img/campus-1.jpg' && !empty($record['image_path'])) {
        $heroPhoto = (string) $record['image_path'];
    }
}

uksort($recordsByYear, static function (string $left, string $right): int {
    if ($left === $right) {
        return 0;
    }
    if ($left === 'Year not specified') {
        return 1;
    }
    if ($right === 'Year not specified') {
        return -1;
    }

    return strcmp($right, $left);
});
foreach ($recordsByYear as &$yearGroup) {
    ksort($yearGroup['crops'], SORT_NATURAL | SORT_FLAG_CASE);
}
unset($yearGroup);
ksort($crops, SORT_NATURAL | SORT_FLAG_CASE);

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

    .technology-records-page {
        background-color: var(--light-bg);
        padding: 3rem 0;
    }

    .technology-records-intro {
        margin: 0 auto 3rem auto;
        max-width: 900px;
        text-align: center;
    }

    .technology-records-intro span {
        color: var(--accent-green);
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
    }

    .technology-records-intro h2 {
        color: var(--primary-green);
        font-size: 2.25rem;
        font-weight: 800;
        margin: 0 0 1.5rem 0;
    }

    .technology-records-intro__content {
        color: var(--text-muted);
        font-size: 1.05rem;
        line-height: 1.8;
        text-align: justify;
        text-justify: inter-word;
    }

    .technology-records-hero__stats {
        display: flex;
        gap: 2rem;
        margin-top: 1.5rem;
    }

    .technology-records-hero__stats div {
        display: flex;
        flex-direction: column;
    }

    .technology-records-hero__stats strong {
        color: #ffffff;
        font-size: 2rem;
    }

    .technology-records-hero__stats span {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.875rem;
    }

    .technology-records-toolbar {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        display: grid;
        gap: 1.25rem;
        grid-template-columns: 2fr 1fr 1fr;
        margin-bottom: 2.5rem;
        padding: 1.5rem;
    }

    .technology-records-toolbar label {
        color: var(--text-main);
        display: flex;
        flex-direction: column;
        font-size: 0.875rem;
        font-weight: 600;
        gap: 0.5rem;
    }

    .technology-records-toolbar input,
    .technology-records-toolbar select {
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        outline: none;
        padding: 0.75rem 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .technology-records-toolbar input:focus,
    .technology-records-toolbar select:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 0 3px rgba(37, 162, 100, 0.15);
    }

    .technology-fy-group {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .technology-fy-group__head {
        align-items: center;
        background: #ffffff;
        border-bottom: 1px solid transparent;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        padding: 1.25rem 2rem;
        transition: background 0.2s;
        user-select: none;
    }

    .technology-fy-group__head:hover {
        background: #f4f8f5;
    }

    .technology-fy-group.is-open .technology-fy-group__head {
        border-bottom-color: var(--card-border);
    }

    .technology-fy-group__head h3 {
        color: var(--primary-green);
        font-size: 1.4rem;
        font-weight: 700;
        margin: 0;
    }

    .technology-fy-group__head span,
    .technology-fy-group__head dt {
        color: var(--text-muted);
        font-size: 0.75rem;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .technology-fy-group__head dl {
        display: flex;
        gap: 1.25rem;
        margin: 0;
    }

    .technology-fy-group__head dl div {
        background: var(--light-bg);
        border: 1px solid var(--card-border);
        border-radius: 6px;
        padding: 0.4rem 0.8rem;
        text-align: center;
    }

    .technology-fy-group__head dd {
        color: var(--text-main);
        font-weight: 700;
        margin: 0;
    }

    .technology-view-btn,
    .technology-crop-view-btn {
        align-items: center;
        cursor: pointer;
        display: inline-flex;
        font-weight: 600;
        gap: 0.5rem;
    }

    .technology-view-btn {
        background: var(--primary-green);
        border: none;
        border-radius: 30px;
        color: #fff;
        padding: 0.5rem 1.25rem;
    }

    .technology-crop-view-btn {
        background: transparent;
        border: 1px solid var(--card-border);
        border-radius: 20px;
        color: var(--text-main);
        font-size: 0.85rem;
        padding: 0.3rem 0.8rem;
    }

    .technology-view-btn i,
    .technology-crop-view-btn i {
        transition: transform 0.25s ease;
    }

    .technology-fy-group.is-open .technology-view-btn {
        background: var(--accent-green);
    }

    .technology-fy-group.is-open .technology-view-btn i,
    .technology-crop-group.is-open .technology-crop-view-btn i {
        transform: rotate(180deg);
    }

    .technology-fy-body[hidden],
    .technology-crop-body[hidden] {
        display: none !important;
    }

    .technology-fy-body {
        background: #fbfdfc;
        padding: 1.5rem 2rem;
    }

    .technology-crop-group {
        background: #ffffff;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .technology-crop-group:last-child {
        margin-bottom: 0;
    }

    .technology-crop-group__head {
        align-items: center;
        background: #f8faf8;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        user-select: none;
    }

    .technology-crop-group__head h4 {
        color: var(--primary-green);
        font-size: 1.05rem;
        margin: 0;
    }

    .technology-table-wrapper {
        background: #ffffff;
        overflow-x: auto;
    }

    .technology-table {
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 780px;
        text-align: left;
        width: 100%;
    }

    .technology-table th {
        background-color: var(--light-bg);
        border-bottom: 2px solid var(--card-border);
        color: var(--primary-green);
        font-weight: 700;
        padding: 0.85rem 1rem;
        white-space: nowrap;
    }

    .technology-table td {
        border-bottom: 1px solid var(--card-border);
        color: var(--text-main);
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }

    .technology-table tbody tr:hover {
        background-color: #f4f8f5;
    }

    .technology-table .col-sl {
        text-align: center;
        width: 50px;
    }

    .technology-table .col-center {
        font-weight: 600;
        text-align: center;
    }

    .technology-table .col-actions {
        text-align: center;
        width: 140px;
    }

    .technology-detail-btn {
        align-items: center;
        background: transparent;
        border: 1px solid var(--accent-green);
        border-radius: 6px;
        color: var(--accent-green);
        cursor: pointer;
        display: inline-flex;
        font-size: 0.8rem;
        font-weight: 600;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        transition: all 0.2s ease;
    }

    .technology-detail-btn:hover {
        background: var(--accent-green);
        color: #ffffff;
    }

    .technology-modal-backdrop {
        align-items: center;
        background: rgba(10, 25, 18, 0.75);
        backdrop-filter: blur(4px);
        display: flex;
        height: 100vh;
        justify-content: center;
        left: 0;
        opacity: 0;
        padding: 1.5rem;
        pointer-events: none;
        position: fixed;
        top: 0;
        transition: opacity 0.25s ease;
        width: 100vw;
        z-index: 999999;
    }

    .technology-modal-backdrop.is-active {
        opacity: 1;
        pointer-events: auto;
    }

    .technology-modal-window {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 20px 30px rgba(0, 0, 0, 0.25);
        max-height: 85vh;
        max-width: 760px;
        overflow-y: auto;
        padding: 2rem;
        position: relative;
        width: 100%;
    }

    .technology-modal-close {
        align-items: center;
        background: #f1f5f9;
        border: none;
        border-radius: 50%;
        color: var(--text-main);
        cursor: pointer;
        display: flex;
        font-size: 1.25rem;
        height: 38px;
        justify-content: center;
        position: absolute;
        right: 1rem;
        top: 1rem;
        width: 38px;
    }

    .technology-modal-photo {
        border: 1px solid var(--card-border);
        border-radius: 8px;
        display: block;
        height: 220px;
        margin-top: 1.25rem;
        object-fit: cover;
        width: 100%;
    }

    .technology-modal-section,
    .technology-stats-badge {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .technology-modal-section {
        border: 1px solid var(--card-border);
        margin-top: 1rem;
        padding: 1rem;
    }

    .technology-modal-section h5 {
        color: var(--primary-green);
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0 0 0.5rem 0;
    }

    .technology-modal-section p {
        color: var(--text-main);
        font-size: 0.9rem;
        line-height: 1.6;
        margin: 0;
    }

    .technology-stats-badge {
        display: inline-block;
        font-size: 0.85rem;
        margin-right: 0.5rem;
        margin-top: 0.4rem;
        padding: 0.3rem 0.7rem;
    }

    .technology-empty {
        background: #ffffff;
        border: 1px dashed var(--card-border);
        border-radius: var(--radius);
        color: var(--text-muted);
        font-size: 1.05rem;
        padding: 2.5rem;
        text-align: center;
    }

    @media (max-width: 768px) {
        .technology-records-toolbar {
            grid-template-columns: 1fr;
        }

        .technology-fy-group__head {
            align-items: flex-start;
            flex-direction: column;
            gap: 1rem;
        }

        .technology-fy-group__head dl {
            justify-content: space-between;
            width: 100%;
        }
    }
</style>

<section class="page-hero technology-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">Technology Showcasing</p>
        <p>Showcasing crop technologies, varieties, farmer participation, area coverage, and production performance under field conditions.</p>
        <div class="technology-records-hero__stats">
            <div><strong><?= e(number_format(count($technologyRecords))) ?></strong><span>Total Records</span></div>
            <div><strong><?= e(number_format($totalFarmers)) ?></strong><span>Farmers Involved</span></div>
            <div><strong><?= e(technology_showcasing_details_decimal($totalProduction)) ?></strong><span>Tonnes Produced</span></div>
        </div>
    </div>
</section>

<section class="section technology-records-page" data-page-key="technology-showcasing">
    <input type="hidden" name="page_key" value="technology-showcasing">
    <input type="hidden" name="page_slug" value="technology-showcasing-details">
    <div class="container">
        <div class="section-head technology-records-intro">
            <span>Technology Showcasing Details</span>
            <h2>Technology Showcasing at a Glance</h2>
            <div class="technology-records-intro__content">
                <p>Technology Showcasing records present the crop, variety, area covered, production, and farmer participation details saved from the activity module.</p>
                <p>The records below are arranged by financial year and crop, following the same expandable format used for FLD records.</p>
            </div>
        </div>

        <div class="technology-records-toolbar">
            <label>
                <span>Search records</span>
                <input type="search" id="technologyRecordSearch" placeholder="Search crop, variety, area...">
            </label>
            <label>
                <span>Crop</span>
                <select id="technologyRecordCrop">
                    <option value="">All crops</option>
                    <?php foreach (array_keys($crops) as $cropName): ?>
                        <option value="<?= e(strtolower($cropName)) ?>"><?= e($cropName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Financial Year</span>
                <select id="technologyRecordYear">
                    <option value="">All years</option>
                    <?php foreach (array_keys($recordsByYear) as $year): ?>
                        <option value="<?= e(strtolower($year)) ?>"><?= e($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="technology-records-list" id="technology-records-list">
            <?php foreach ($recordsByYear as $year => $yearGroup): ?>
                <section class="technology-fy-group" data-fy="<?= e(strtolower($year)) ?>">
                    <div class="technology-fy-group__head" role="button" tabindex="0" aria-expanded="false">
                        <div>
                            <span>Financial Year</span>
                            <h3><?= e($year) ?></h3>
                        </div>
                        <dl>
                            <div><dt>Records</dt><dd><?= e(number_format(count($yearGroup['records']))) ?></dd></div>
                            <div><dt>Farmers</dt><dd><?= e(number_format((int) $yearGroup['farmers'])) ?></dd></div>
                            <div><dt>Production</dt><dd><?= e(technology_showcasing_details_decimal($yearGroup['production'])) ?> t</dd></div>
                        </dl>
                        <button class="technology-view-btn" type="button" tabindex="-1">
                            <span>View</span> <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>

                    <div class="technology-fy-body" hidden>
                        <?php foreach ($yearGroup['crops'] as $cropName => $cropGroup): ?>
                            <div class="technology-crop-group" data-crop="<?= e(strtolower($cropName)) ?>">
                                <div class="technology-crop-group__head" role="button" tabindex="0" aria-expanded="false">
                                    <h4><?= e($cropName) ?> (<?= e((string) count($cropGroup['records'])) ?> records)</h4>
                                    <button class="technology-crop-view-btn" type="button" tabindex="-1">
                                        <span>View</span> <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>

                                <div class="technology-crop-body" hidden>
                                    <div class="technology-table-wrapper">
                                        <table class="technology-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-sl">Sl. No.</th>
                                                    <th>Variety</th>
                                                    <th class="col-center">Farmers</th>
                                                    <th class="col-center">Area Covered (HA)</th>
                                                    <th class="col-center">Production (In tonnes)</th>
                                                    <th class="col-actions">Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cropGroup['records'] as $recordIndex => $record): ?>
                                                    <?php
                                                        $variety = trim((string) ($record['variety'] ?? '')) ?: 'Not specified';
                                                        $area = trim((string) ($record['area_covered'] ?? '')) ?: 'Not specified';
                                                        $farmers = max(0, (int) ($record['farmers_involved'] ?? 0));
                                                        $production = technology_showcasing_details_decimal($record['production_tonnes'] ?? 0);
                                                        $recordTitle = technology_showcasing_details_title($record);
                                                        $searchText = strtolower(trim($recordTitle . ' ' . $year . ' ' . $cropName . ' ' . $variety . ' ' . $area . ' ' . $farmers . ' ' . $production));
                                                        $modalData = [
                                                            'title' => $recordTitle,
                                                            'year' => $year,
                                                            'crop' => $cropName,
                                                            'variety' => $variety,
                                                            'farmers' => $farmers,
                                                            'area' => $area,
                                                            'production' => $production,
                                                            'summary' => (string) ($record['summary'] ?? ''),
                                                            'image' => (string) ($record['image_path'] ?? ''),
                                                        ];
                                                    ?>
                                                    <tr class="technology-table-row" data-search="<?= e($searchText) ?>" data-crop="<?= e(strtolower($cropName)) ?>" data-fy="<?= e(strtolower($year)) ?>">
                                                        <td class="col-sl"><?= e((string) ($recordIndex + 1)) ?></td>
                                                        <td><strong><?= e($variety) ?></strong></td>
                                                        <td class="col-center"><?= e(number_format($farmers)) ?></td>
                                                        <td class="col-center"><?= e($area) ?></td>
                                                        <td class="col-center"><?= e($production) ?></td>
                                                        <td class="col-actions">
                                                            <button class="technology-detail-btn technology-open-modal" type="button" data-json="<?= e(json_encode($modalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>">
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

        <div class="technology-empty" id="technologyRecordsEmpty" <?= $recordsByYear ? 'hidden' : '' ?>>
            <i class="fa-solid fa-circle-exclamation" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
            <?= $recordsByYear ? 'No technology showcasing records match your search query.' : 'No technology showcasing records are available yet.' ?>
        </div>
    </div>
</section>

<div class="technology-modal-backdrop" id="technologyModalBackdrop" aria-hidden="true">
    <div class="technology-modal-window" role="dialog" aria-modal="true">
        <button class="technology-modal-close" id="technologyModalClose" type="button" aria-label="Close Popup">&times;</button>
        <div id="technologyModalContent"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    const toggleYearGroup = (head) => {
        const group = head.closest('.technology-fy-group');
        const body = group.querySelector('.technology-fy-body');
        const isOpen = group.classList.contains('is-open');

        document.querySelectorAll('.technology-fy-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.technology-fy-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.technology-fy-body').hidden = true;
            const viewText = item.querySelector('.technology-view-btn span');
            if (viewText) {
                viewText.textContent = 'View';
            }
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            const viewText = group.querySelector('.technology-view-btn span');
            if (viewText) {
                viewText.textContent = 'Hide';
            }
        }
    };

    document.querySelectorAll('.technology-fy-group__head').forEach((head) => {
        head.addEventListener('click', () => toggleYearGroup(head));
        head.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleYearGroup(head);
            }
        });
    });

    const toggleCropGroup = (head) => {
        const group = head.closest('.technology-crop-group');
        const body = group.querySelector('.technology-crop-body');
        const isOpen = group.classList.contains('is-open');
        const parentBody = group.closest('.technology-fy-body');

        parentBody.querySelectorAll('.technology-crop-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.technology-crop-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.technology-crop-body').hidden = true;
            const viewText = item.querySelector('.technology-crop-view-btn span');
            if (viewText) {
                viewText.textContent = 'View';
            }
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.technology-crop-view-btn span').textContent = 'Hide';
        }
    };

    document.querySelectorAll('.technology-crop-group__head').forEach((head) => {
        head.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleCropGroup(head);
        });
        head.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                toggleCropGroup(head);
            }
        });
    });

    const modalBackdrop = document.getElementById('technologyModalBackdrop');
    const modalContent = document.getElementById('technologyModalContent');
    const modalClose = document.getElementById('technologyModalClose');

    const openModal = (data) => {
        const photoHtml = data.image ? `<img class="technology-modal-photo" src="${escapeHtml(data.image)}" alt="${escapeHtml(data.title)}">` : '';
        const summaryHtml = data.summary ? `
            <div class="technology-modal-section">
                <h5>Summary</h5>
                <p>${escapeHtml(data.summary)}</p>
            </div>
        ` : '';

        modalContent.innerHTML = `
            <h3 style="margin-top: 0; color: var(--primary-green); font-size: 1.35rem; padding-right: 2rem;">${escapeHtml(data.title)}</h3>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0.75rem;">
                <strong>Financial Year:</strong> ${escapeHtml(data.year)} | <strong>Crop:</strong> ${escapeHtml(data.crop)} | <strong>Variety:</strong> ${escapeHtml(data.variety)}
            </p>
            <div>
                <span class="technology-stats-badge"><strong>Farmers Involved:</strong> ${escapeHtml(data.farmers)}</span>
                <span class="technology-stats-badge"><strong>Area Covered (HA):</strong> ${escapeHtml(data.area)}</span>
                <span class="technology-stats-badge"><strong>Production:</strong> ${escapeHtml(data.production)} tonnes</span>
            </div>
            ${summaryHtml}
            ${photoHtml}
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

    document.querySelectorAll('.technology-open-modal').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const rawJson = button.getAttribute('data-json');
            if (!rawJson) {
                return;
            }
            try {
                openModal(JSON.parse(rawJson));
            } catch (error) {
                console.error('Invalid technology showcasing data', error);
            }
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', (event) => {
            if (event.target === modalBackdrop) {
                closeModal();
            }
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalBackdrop.classList.contains('is-active')) {
            closeModal();
        }
    });

    const searchInput = document.getElementById('technologyRecordSearch');
    const cropSelect = document.getElementById('technologyRecordCrop');
    const yearSelect = document.getElementById('technologyRecordYear');
    const emptyState = document.getElementById('technologyRecordsEmpty');

    const runFilter = () => {
        const query = searchInput.value.trim().toLowerCase();
        const crop = cropSelect.value;
        const year = yearSelect.value;
        let visibleCount = 0;

        document.querySelectorAll('.technology-fy-group').forEach((fyGroup) => {
            let yearHasVisible = false;

            fyGroup.querySelectorAll('.technology-crop-group').forEach((cropGroup) => {
                let cropHasVisible = false;

                cropGroup.querySelectorAll('.technology-table-row').forEach((row) => {
                    const visible = (!query || row.dataset.search.includes(query))
                        && (!crop || row.dataset.crop === crop)
                        && (!year || row.dataset.fy === year);
                    row.hidden = !visible;
                    if (visible) {
                        cropHasVisible = true;
                        visibleCount += 1;
                    }
                });

                cropGroup.hidden = !cropHasVisible;
                if (cropHasVisible) {
                    yearHasVisible = true;
                    if (query || crop || year) {
                        cropGroup.classList.add('is-open');
                        cropGroup.querySelector('.technology-crop-body').hidden = false;
                        cropGroup.querySelector('.technology-crop-view-btn span').textContent = 'Hide';
                    }
                }
            });

            fyGroup.hidden = !yearHasVisible;
            if (yearHasVisible && (query || crop || year)) {
                fyGroup.classList.add('is-open');
                fyGroup.querySelector('.technology-fy-body').hidden = false;
                fyGroup.querySelector('.technology-view-btn span').textContent = 'Hide';
            }
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    if (searchInput && cropSelect && yearSelect) {
        searchInput.addEventListener('input', runFilter);
        cropSelect.addEventListener('change', runFilter);
        yearSelect.addEventListener('change', runFilter);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
