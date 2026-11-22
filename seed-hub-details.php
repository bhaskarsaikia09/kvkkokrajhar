<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Seed Hub Report | ' . APP_NAME;

function seed_hub_details_ensure_table(?PDO $pdo): void
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

function seed_hub_details_records(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    seed_hub_details_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT shr.*, a.title, a.summary, a.image_path
        FROM seed_hub_records shr
        LEFT JOIN activities a ON a.id = shr.activity_id
        WHERE shr.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY shr.year DESC, shr.crop ASC, shr.id DESC");
    $stmt->execute();

    return $stmt ? $stmt->fetchAll() : [];
}

function seed_hub_details_year(array $record): string
{
    $year = trim((string) ($record['year'] ?? ''));
    return $year !== '' ? financial_year_normalize($year) : 'Year not specified';
}

function seed_hub_details_decimal($value): string
{
    $formatted = number_format(max(0, (float) $value), 3);
    return rtrim(rtrim($formatted, '0'), '.');
}

function seed_hub_details_title(array $record): string
{
    $crop = trim((string) ($record['crop'] ?? ''));
    $variety = trim((string) ($record['variety'] ?? ''));

    if ($crop !== '' && $variety !== '') {
        return $crop . ' - ' . $variety;
    }

    return $crop !== '' ? $crop : ($variety !== '' ? $variety : 'Seed Hub');
}

$seedHubRecords = seed_hub_details_records();
$recordsByYear = [];
$crops = [];
$totalRecords = 0;
$totalArea = 0.0;
$totalProduction = 0.0;
$heroPhoto = 'assets/img/campus-1.jpg';

foreach ($seedHubRecords as $record) {
    $year = seed_hub_details_year($record);
    $crop = trim((string) ($record['crop'] ?? '')) ?: 'Crop not specified';
    $area = max(0, (float) ($record['area_ha'] ?? 0));
    $production = max(0, (float) ($record['production_q'] ?? 0));

    if (!isset($recordsByYear[$year])) {
        $recordsByYear[$year] = [
            'records' => [],
            'area' => 0.0,
            'production' => 0.0,
            'crops' => [],
        ];
    }

    if (!isset($recordsByYear[$year]['crops'][$crop])) {
        $recordsByYear[$year]['crops'][$crop] = [
            'records' => [],
            'area' => 0.0,
            'production' => 0.0,
        ];
    }

    $recordsByYear[$year]['records'][] = $record;
    $recordsByYear[$year]['area'] += $area;
    $recordsByYear[$year]['production'] += $production;
    $recordsByYear[$year]['crops'][$crop]['records'][] = $record;
    $recordsByYear[$year]['crops'][$crop]['area'] += $area;
    $recordsByYear[$year]['crops'][$crop]['production'] += $production;
    $crops[$crop] = true;

    $totalRecords++;
    $totalArea += $area;
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

    .seed-records-page { background-color: var(--light-bg); padding: 3rem 0; }
    .seed-records-intro { margin: 0 auto 3rem auto; max-width: 900px; text-align: center; }
    .seed-records-intro span { color: var(--accent-green); display: block; font-size: .85rem; font-weight: 700; letter-spacing: 1.5px; margin-bottom: .5rem; text-transform: uppercase; }
    .seed-records-intro h2 { color: var(--primary-green); font-size: 2.25rem; font-weight: 800; margin: 0 0 1.5rem 0; }
    .seed-records-intro__content { color: var(--text-muted); font-size: 1.05rem; line-height: 1.8; text-align: justify; text-justify: inter-word; }
    .seed-records-hero__stats { display: flex; gap: 2rem; margin-top: 1.5rem; }
    .seed-records-hero__stats div { display: flex; flex-direction: column; }
    .seed-records-hero__stats strong { color: #fff; font-size: 2rem; }
    .seed-records-hero__stats span { color: rgba(255,255,255,.8); font-size: .875rem; }
    .seed-records-toolbar { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); box-shadow: var(--shadow-sm); display: grid; gap: 1.25rem; grid-template-columns: 2fr 1fr 1fr; margin-bottom: 2.5rem; padding: 1.5rem; }
    .seed-records-toolbar label { color: var(--text-main); display: flex; flex-direction: column; font-size: .875rem; font-weight: 600; gap: .5rem; }
    .seed-records-toolbar input, .seed-records-toolbar select { background: #fff; border: 1px solid var(--card-border); border-radius: 8px; outline: none; padding: .75rem 1rem; transition: border-color .2s, box-shadow .2s; }
    .seed-records-toolbar input:focus, .seed-records-toolbar select:focus { border-color: var(--accent-green); box-shadow: 0 0 0 3px rgba(37,162,100,.15); }
    .seed-fy-group { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; overflow: hidden; }
    .seed-fy-group__head { align-items: center; background: #fff; border-bottom: 1px solid transparent; cursor: pointer; display: flex; justify-content: space-between; padding: 1.25rem 2rem; transition: background .2s; user-select: none; }
    .seed-fy-group__head:hover { background: #f4f8f5; }
    .seed-fy-group.is-open .seed-fy-group__head { border-bottom-color: var(--card-border); }
    .seed-fy-group__head h3 { color: var(--primary-green); font-size: 1.4rem; font-weight: 700; margin: 0; }
    .seed-fy-group__head span, .seed-fy-group__head dt { color: var(--text-muted); font-size: .75rem; letter-spacing: 1px; text-transform: uppercase; }
    .seed-fy-group__head dl { display: flex; gap: 1.25rem; margin: 0; }
    .seed-fy-group__head dl div { background: var(--light-bg); border: 1px solid var(--card-border); border-radius: 6px; padding: .4rem .8rem; text-align: center; }
    .seed-fy-group__head dd { color: var(--text-main); font-weight: 700; margin: 0; }
    .seed-view-btn, .seed-crop-view-btn { align-items: center; cursor: pointer; display: inline-flex; font-weight: 600; gap: .5rem; }
    .seed-view-btn { background: var(--primary-green); border: none; border-radius: 30px; color: #fff; padding: .5rem 1.25rem; }
    .seed-crop-view-btn { background: transparent; border: 1px solid var(--card-border); border-radius: 20px; color: var(--text-main); font-size: .85rem; padding: .3rem .8rem; }
    .seed-view-btn i, .seed-crop-view-btn i { transition: transform .25s ease; }
    .seed-fy-group.is-open .seed-view-btn { background: var(--accent-green); }
    .seed-fy-group.is-open .seed-view-btn i, .seed-crop-group.is-open .seed-crop-view-btn i { transform: rotate(180deg); }
    .seed-fy-body[hidden], .seed-crop-body[hidden] { display: none !important; }
    .seed-fy-body { background: #fbfdfc; padding: 1.5rem 2rem; }
    .seed-crop-group { background: #fff; border: 1px solid var(--card-border); border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
    .seed-crop-group:last-child { margin-bottom: 0; }
    .seed-crop-group__head { align-items: center; background: #f8faf8; cursor: pointer; display: flex; justify-content: space-between; padding: 1rem 1.25rem; user-select: none; }
    .seed-crop-group__head h4 { color: var(--primary-green); font-size: 1.05rem; margin: 0; }
    .seed-table-wrapper { background: #fff; overflow-x: auto; }
    .seed-table { border-collapse: collapse; font-size: .9rem; min-width: 900px; text-align: left; width: 100%; }
    .seed-table th { background-color: var(--light-bg); border-bottom: 2px solid var(--card-border); color: var(--primary-green); font-weight: 700; padding: .85rem 1rem; white-space: nowrap; }
    .seed-table td { border-bottom: 1px solid var(--card-border); color: var(--text-main); padding: .85rem 1rem; vertical-align: middle; }
    .seed-table tbody tr:hover { background-color: #f4f8f5; }
    .seed-table .col-sl { text-align: center; width: 50px; }
    .seed-table .col-center { font-weight: 600; text-align: center; }
    .seed-empty { background: #fff; border: 1px dashed var(--card-border); border-radius: var(--radius); color: var(--text-muted); font-size: 1.05rem; padding: 2.5rem; text-align: center; }

    @media (max-width: 768px) {
        .seed-records-toolbar { grid-template-columns: 1fr; }
        .seed-fy-group__head { align-items: flex-start; flex-direction: column; gap: 1rem; }
        .seed-fy-group__head dl { flex-wrap: wrap; width: 100%; }
        .seed-records-hero__stats { flex-wrap: wrap; gap: 1rem; }
    }
</style>

<section class="page-hero seed-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">Seed Hub</p>
        <p>Seed Hub records arranged by financial year and crop, with area coverage, production, and shortfall notes for KVK Kokrajhar.</p>
        <div class="seed-records-hero__stats">
            <div><strong><?= e(number_format($totalRecords)) ?></strong><span>Total Records</span></div>
            <div><strong><?= e(seed_hub_details_decimal($totalArea)) ?></strong><span>Area Covered (Ha)</span></div>
            <div><strong><?= e(seed_hub_details_decimal($totalProduction)) ?></strong><span>Production (Q)</span></div>
        </div>
    </div>
</section>

<section class="section seed-records-page" data-page-key="seed-hub">
    <input type="hidden" name="page_key" value="seed-hub">
    <input type="hidden" name="page_slug" value="seed-hub-details">
    <div class="container">
        <div class="section-head seed-records-intro">
            <span>Seed Hub Report</span>
            <h2>Seed Hub at a Glance</h2>
            <div class="seed-records-intro__content">
                <p>Records shown here are saved from the Seed Hub activity module and displayed in the same expandable format as Cluster Demonstration.</p>
            </div>
        </div>

        <div class="seed-records-toolbar">
            <label><span>Search records</span><input type="search" id="seedRecordSearch" placeholder="Search crop, variety, reason..."></label>
            <label><span>Crop</span><select id="seedRecordCrop"><option value="">All crops</option><?php foreach (array_keys($crops) as $cropName): ?><option value="<?= e(strtolower($cropName)) ?>"><?= e($cropName) ?></option><?php endforeach; ?></select></label>
            <label><span>Financial Year</span><select id="seedRecordYear"><option value="">All years</option><?php foreach (array_keys($recordsByYear) as $year): ?><option value="<?= e(strtolower($year)) ?>"><?= e($year) ?></option><?php endforeach; ?></select></label>
        </div>

        <div class="seed-records-list" id="seed-records-list">
            <?php foreach ($recordsByYear as $year => $yearGroup): ?>
                <section class="seed-fy-group" data-fy="<?= e(strtolower($year)) ?>">
                    <div class="seed-fy-group__head" role="button" tabindex="0" aria-expanded="false">
                        <div><span>Financial Year</span><h3><?= e($year) ?></h3></div>
                        <dl>
                            <div><dt>Records</dt><dd><?= e(number_format(count($yearGroup['records']))) ?></dd></div>
                            <div><dt>Area</dt><dd><?= e(seed_hub_details_decimal($yearGroup['area'])) ?> ha</dd></div>
                            <div><dt>Production</dt><dd><?= e(seed_hub_details_decimal($yearGroup['production'])) ?> Q</dd></div>
                        </dl>
                        <button class="seed-view-btn" type="button" tabindex="-1"><span>View</span> <i class="fa-solid fa-chevron-down"></i></button>
                    </div>

                    <div class="seed-fy-body" hidden>
                        <?php foreach ($yearGroup['crops'] as $cropName => $cropGroup): ?>
                            <div class="seed-crop-group" data-crop="<?= e(strtolower($cropName)) ?>">
                                <div class="seed-crop-group__head" role="button" tabindex="0" aria-expanded="false">
                                    <h4><?= e($cropName) ?> (<?= e((string) count($cropGroup['records'])) ?> records)</h4>
                                    <button class="seed-crop-view-btn" type="button" tabindex="-1"><span>View</span> <i class="fa-solid fa-chevron-down"></i></button>
                                </div>
                                <div class="seed-crop-body" hidden>
                                    <div class="seed-table-wrapper">
                                        <table class="seed-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-sl">Sl. No.</th>
                                                    <th>Variety</th>
                                                    <th class="col-center">Area (Ha)</th>
                                                    <th class="col-center">Production (Q)</th>
                                                    <th>Reasons for shortfall, if any</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cropGroup['records'] as $recordIndex => $record): ?>
                                                    <?php
                                                        $variety = trim((string) ($record['variety'] ?? '')) ?: 'Not specified';
                                                        $area = trim((string) ($record['area_ha'] ?? '')) ?: 'Not specified';
                                                        $production = seed_hub_details_decimal($record['production_q'] ?? 0);
                                                        $shortfallReason = trim((string) ($record['shortfall_reason'] ?? '')) ?: 'None';
                                                        $searchText = strtolower(trim(seed_hub_details_title($record) . ' ' . $year . ' ' . $cropName . ' ' . $variety . ' ' . $area . ' ' . $production . ' ' . $shortfallReason));
                                                    ?>
                                                    <tr class="seed-table-row" data-search="<?= e($searchText) ?>" data-crop="<?= e(strtolower($cropName)) ?>" data-fy="<?= e(strtolower($year)) ?>">
                                                        <td class="col-sl"><?= e((string) ($recordIndex + 1)) ?></td>
                                                        <td><strong><?= e($variety) ?></strong></td>
                                                        <td class="col-center"><?= e($area) ?></td>
                                                        <td class="col-center"><?= e($production) ?></td>
                                                        <td><?= e($shortfallReason) ?></td>
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

        <div class="seed-empty" id="seedRecordsEmpty" <?= $recordsByYear ? 'hidden' : '' ?>>
            <?= $recordsByYear ? 'No Seed Hub records match your search query.' : 'No Seed Hub records are available yet.' ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleYearGroup = (head) => {
        const group = head.closest('.seed-fy-group');
        const body = group.querySelector('.seed-fy-body');
        const isOpen = group.classList.contains('is-open');

        document.querySelectorAll('.seed-fy-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.seed-fy-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.seed-fy-body').hidden = true;
            item.querySelector('.seed-view-btn span').textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.seed-view-btn span').textContent = 'Hide';
        }
    };

    const toggleCropGroup = (head) => {
        const group = head.closest('.seed-crop-group');
        const body = group.querySelector('.seed-crop-body');
        const isOpen = group.classList.contains('is-open');

        group.closest('.seed-fy-body').querySelectorAll('.seed-crop-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.seed-crop-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.seed-crop-body').hidden = true;
            item.querySelector('.seed-crop-view-btn span').textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.seed-crop-view-btn span').textContent = 'Hide';
        }
    };

    document.querySelectorAll('.seed-fy-group__head').forEach((head) => {
        head.addEventListener('click', () => toggleYearGroup(head));
        head.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleYearGroup(head);
            }
        });
    });

    document.querySelectorAll('.seed-crop-group__head').forEach((head) => {
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

    const searchInput = document.getElementById('seedRecordSearch');
    const cropSelect = document.getElementById('seedRecordCrop');
    const yearSelect = document.getElementById('seedRecordYear');
    const emptyState = document.getElementById('seedRecordsEmpty');

    const runFilter = () => {
        const query = searchInput.value.trim().toLowerCase();
        const crop = cropSelect.value;
        const year = yearSelect.value;
        let visibleCount = 0;

        document.querySelectorAll('.seed-fy-group').forEach((fyGroup) => {
            let yearHasVisible = false;
            fyGroup.querySelectorAll('.seed-crop-group').forEach((cropGroup) => {
                let cropHasVisible = false;
                cropGroup.querySelectorAll('.seed-table-row').forEach((row) => {
                    const visible = (!query || row.dataset.search.includes(query)) && (!crop || row.dataset.crop === crop) && (!year || row.dataset.fy === year);
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
                        cropGroup.querySelector('.seed-crop-body').hidden = false;
                        cropGroup.querySelector('.seed-crop-view-btn span').textContent = 'Hide';
                    }
                }
            });
            fyGroup.hidden = !yearHasVisible;
            if (yearHasVisible && (query || crop || year)) {
                fyGroup.classList.add('is-open');
                fyGroup.querySelector('.seed-fy-body').hidden = false;
                fyGroup.querySelector('.seed-view-btn span').textContent = 'Hide';
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
