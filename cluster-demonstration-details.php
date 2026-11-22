<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Cluster Demonstration Details | ' . APP_NAME;

function cluster_demonstration_details_ensure_table(?PDO $pdo): void
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

    foreach ([
        'activity_id' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN activity_id INT NULL UNIQUE AFTER id',
        'year' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN year VARCHAR(20) NULL AFTER activity_id',
        'crop' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN crop VARCHAR(180) NULL AFTER year',
        'variety' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN variety VARCHAR(180) NULL AFTER crop',
        'demonstration_count' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN demonstration_count INT DEFAULT 0 AFTER variety',
        'farmers_involved' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN farmers_involved INT DEFAULT 0 AFTER demonstration_count',
        'area_covered' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN area_covered VARCHAR(120) NULL AFTER farmers_involved',
        'production_tonnes' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN production_tonnes DECIMAL(12,3) DEFAULT 0 AFTER area_covered',
        'is_active' => 'ALTER TABLE cluster_demonstration_records ADD COLUMN is_active TINYINT(1) DEFAULT 1',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function cluster_demonstration_details_records(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    cluster_demonstration_details_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT cdr.*, a.title, a.summary, a.image_path
        FROM cluster_demonstration_records cdr
        LEFT JOIN activities a ON a.id = cdr.activity_id
        WHERE cdr.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY cdr.year DESC, cdr.crop ASC, cdr.id DESC");
    $stmt->execute();

    return $stmt ? $stmt->fetchAll() : [];
}

function cluster_demonstration_details_year(array $record): string
{
    $year = trim((string) ($record['year'] ?? ''));
    return $year !== '' ? financial_year_normalize($year) : 'Year not specified';
}

function cluster_demonstration_details_decimal($value): string
{
    $formatted = number_format(max(0, (float) $value), 3);
    return rtrim(rtrim($formatted, '0'), '.');
}

function cluster_demonstration_details_title(array $record): string
{
    $crop = trim((string) ($record['crop'] ?? ''));
    $variety = trim((string) ($record['variety'] ?? ''));

    if ($crop !== '' && $variety !== '') {
        return $crop . ' - ' . $variety;
    }

    return $crop !== '' ? $crop : ($variety !== '' ? $variety : 'Cluster Demonstration');
}

$clusterRecords = cluster_demonstration_details_records();
$recordsByYear = [];
$crops = [];
$totalDemonstrations = 0;
$totalFarmers = 0;
$totalProduction = 0.0;
$heroPhoto = 'assets/img/campus-1.jpg';

foreach ($clusterRecords as $record) {
    $year = cluster_demonstration_details_year($record);
    $crop = trim((string) ($record['crop'] ?? '')) ?: 'Crop not specified';
    $demonstrations = max(0, (int) ($record['demonstration_count'] ?? 0));
    $farmers = max(0, (int) ($record['farmers_involved'] ?? 0));
    $production = max(0, (float) ($record['production_tonnes'] ?? 0));

    if (!isset($recordsByYear[$year])) {
        $recordsByYear[$year] = [
            'records' => [],
            'demonstrations' => 0,
            'farmers' => 0,
            'production' => 0.0,
            'crops' => [],
        ];
    }

    if (!isset($recordsByYear[$year]['crops'][$crop])) {
        $recordsByYear[$year]['crops'][$crop] = [
            'records' => [],
            'demonstrations' => 0,
            'farmers' => 0,
            'production' => 0.0,
        ];
    }

    $recordsByYear[$year]['records'][] = $record;
    $recordsByYear[$year]['demonstrations'] += $demonstrations;
    $recordsByYear[$year]['farmers'] += $farmers;
    $recordsByYear[$year]['production'] += $production;
    $recordsByYear[$year]['crops'][$crop]['records'][] = $record;
    $recordsByYear[$year]['crops'][$crop]['demonstrations'] += $demonstrations;
    $recordsByYear[$year]['crops'][$crop]['farmers'] += $farmers;
    $recordsByYear[$year]['crops'][$crop]['production'] += $production;
    $crops[$crop] = true;

    $totalDemonstrations += $demonstrations;
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

    .cluster-records-page { background-color: var(--light-bg); padding: 3rem 0; }
    .cluster-records-intro { margin: 0 auto 3rem auto; max-width: 900px; text-align: center; }
    .cluster-records-intro span { color: var(--accent-green); display: block; font-size: .85rem; font-weight: 700; letter-spacing: 1.5px; margin-bottom: .5rem; text-transform: uppercase; }
    .cluster-records-intro h2 { color: var(--primary-green); font-size: 2.25rem; font-weight: 800; margin: 0 0 1.5rem 0; }
    .cluster-records-intro__content { color: var(--text-muted); font-size: 1.05rem; line-height: 1.8; text-align: justify; text-justify: inter-word; }
    .cluster-records-hero__stats { display: flex; gap: 2rem; margin-top: 1.5rem; }
    .cluster-records-hero__stats div { display: flex; flex-direction: column; }
    .cluster-records-hero__stats strong { color: #fff; font-size: 2rem; }
    .cluster-records-hero__stats span { color: rgba(255,255,255,.8); font-size: .875rem; }
    .cluster-records-toolbar { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); box-shadow: var(--shadow-sm); display: grid; gap: 1.25rem; grid-template-columns: 2fr 1fr 1fr; margin-bottom: 2.5rem; padding: 1.5rem; }
    .cluster-records-toolbar label { color: var(--text-main); display: flex; flex-direction: column; font-size: .875rem; font-weight: 600; gap: .5rem; }
    .cluster-records-toolbar input, .cluster-records-toolbar select { background: #fff; border: 1px solid var(--card-border); border-radius: 8px; outline: none; padding: .75rem 1rem; transition: border-color .2s, box-shadow .2s; }
    .cluster-records-toolbar input:focus, .cluster-records-toolbar select:focus { border-color: var(--accent-green); box-shadow: 0 0 0 3px rgba(37,162,100,.15); }
    .cluster-fy-group { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; overflow: hidden; }
    .cluster-fy-group__head { align-items: center; background: #fff; border-bottom: 1px solid transparent; cursor: pointer; display: flex; justify-content: space-between; padding: 1.25rem 2rem; transition: background .2s; user-select: none; }
    .cluster-fy-group__head:hover { background: #f4f8f5; }
    .cluster-fy-group.is-open .cluster-fy-group__head { border-bottom-color: var(--card-border); }
    .cluster-fy-group__head h3 { color: var(--primary-green); font-size: 1.4rem; font-weight: 700; margin: 0; }
    .cluster-fy-group__head span, .cluster-fy-group__head dt { color: var(--text-muted); font-size: .75rem; letter-spacing: 1px; text-transform: uppercase; }
    .cluster-fy-group__head dl { display: flex; gap: 1.25rem; margin: 0; }
    .cluster-fy-group__head dl div { background: var(--light-bg); border: 1px solid var(--card-border); border-radius: 6px; padding: .4rem .8rem; text-align: center; }
    .cluster-fy-group__head dd { color: var(--text-main); font-weight: 700; margin: 0; }
    .cluster-view-btn, .cluster-crop-view-btn { align-items: center; cursor: pointer; display: inline-flex; font-weight: 600; gap: .5rem; }
    .cluster-view-btn { background: var(--primary-green); border: none; border-radius: 30px; color: #fff; padding: .5rem 1.25rem; }
    .cluster-crop-view-btn { background: transparent; border: 1px solid var(--card-border); border-radius: 20px; color: var(--text-main); font-size: .85rem; padding: .3rem .8rem; }
    .cluster-view-btn i, .cluster-crop-view-btn i { transition: transform .25s ease; }
    .cluster-fy-group.is-open .cluster-view-btn { background: var(--accent-green); }
    .cluster-fy-group.is-open .cluster-view-btn i, .cluster-crop-group.is-open .cluster-crop-view-btn i { transform: rotate(180deg); }
    .cluster-fy-body[hidden], .cluster-crop-body[hidden] { display: none !important; }
    .cluster-fy-body { background: #fbfdfc; padding: 1.5rem 2rem; }
    .cluster-crop-group { background: #fff; border: 1px solid var(--card-border); border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
    .cluster-crop-group:last-child { margin-bottom: 0; }
    .cluster-crop-group__head { align-items: center; background: #f8faf8; cursor: pointer; display: flex; justify-content: space-between; padding: 1rem 1.25rem; user-select: none; }
    .cluster-crop-group__head h4 { color: var(--primary-green); font-size: 1.05rem; margin: 0; }
    .cluster-table-wrapper { background: #fff; overflow-x: auto; }
    .cluster-table { border-collapse: collapse; font-size: .9rem; min-width: 880px; text-align: left; width: 100%; }
    .cluster-table th { background-color: var(--light-bg); border-bottom: 2px solid var(--card-border); color: var(--primary-green); font-weight: 700; padding: .85rem 1rem; white-space: nowrap; }
    .cluster-table td { border-bottom: 1px solid var(--card-border); color: var(--text-main); padding: .85rem 1rem; vertical-align: middle; }
    .cluster-table tbody tr:hover { background-color: #f4f8f5; }
    .cluster-table .col-sl { text-align: center; width: 50px; }
    .cluster-table .col-center { font-weight: 600; text-align: center; }
    .cluster-empty { background: #fff; border: 1px dashed var(--card-border); border-radius: var(--radius); color: var(--text-muted); font-size: 1.05rem; padding: 2.5rem; text-align: center; }

    @media (max-width: 768px) {
        .cluster-records-toolbar { grid-template-columns: 1fr; }
        .cluster-fy-group__head { align-items: flex-start; flex-direction: column; gap: 1rem; }
        .cluster-fy-group__head dl { flex-wrap: wrap; width: 100%; }
        .cluster-records-hero__stats { flex-wrap: wrap; gap: 1rem; }
    }
</style>

<section class="page-hero cluster-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">Cluster Demonstration</p>
        <p>Cluster demonstrations arranged by financial year and crop, with demonstration count, farmer involvement, area covered, and production performance.</p>
        <div class="cluster-records-hero__stats">
            <div><strong><?= e(number_format($totalDemonstrations)) ?></strong><span>Total Demonstrations</span></div>
            <div><strong><?= e(number_format($totalFarmers)) ?></strong><span>Farmers Involved</span></div>
            <div><strong><?= e(cluster_demonstration_details_decimal($totalProduction)) ?></strong><span>Tonnes Produced</span></div>
        </div>
    </div>
</section>

<section class="section cluster-records-page" data-page-key="cluster-demonstration">
    <input type="hidden" name="page_key" value="cluster-demonstration">
    <input type="hidden" name="page_slug" value="cluster-demonstration-details">
    <div class="container">
        <div class="section-head cluster-records-intro">
            <span>Cluster Demonstration Details</span>
            <h2>Cluster Demonstration at a Glance</h2>
            <div class="cluster-records-intro__content">
                <p>Records shown here are saved from the Cluster Demonstration activity module and displayed in the same expandable format as Technology Showcasing.</p>
            </div>
        </div>

        <div class="cluster-records-toolbar">
            <label><span>Search records</span><input type="search" id="clusterRecordSearch" placeholder="Search crop, variety, area..."></label>
            <label><span>Crop</span><select id="clusterRecordCrop"><option value="">All crops</option><?php foreach (array_keys($crops) as $cropName): ?><option value="<?= e(strtolower($cropName)) ?>"><?= e($cropName) ?></option><?php endforeach; ?></select></label>
            <label><span>Financial Year</span><select id="clusterRecordYear"><option value="">All years</option><?php foreach (array_keys($recordsByYear) as $year): ?><option value="<?= e(strtolower($year)) ?>"><?= e($year) ?></option><?php endforeach; ?></select></label>
        </div>

        <div class="cluster-records-list" id="cluster-records-list">
            <?php foreach ($recordsByYear as $year => $yearGroup): ?>
                <section class="cluster-fy-group" data-fy="<?= e(strtolower($year)) ?>">
                    <div class="cluster-fy-group__head" role="button" tabindex="0" aria-expanded="false">
                        <div><span>Financial Year</span><h3><?= e($year) ?></h3></div>
                        <dl>
                            <div><dt>Records</dt><dd><?= e(number_format(count($yearGroup['records']))) ?></dd></div>
                            <div><dt>Demos</dt><dd><?= e(number_format((int) $yearGroup['demonstrations'])) ?></dd></div>
                            <div><dt>Farmers</dt><dd><?= e(number_format((int) $yearGroup['farmers'])) ?></dd></div>
                            <div><dt>Production</dt><dd><?= e(cluster_demonstration_details_decimal($yearGroup['production'])) ?> t</dd></div>
                        </dl>
                        <button class="cluster-view-btn" type="button" tabindex="-1"><span>View</span> <i class="fa-solid fa-chevron-down"></i></button>
                    </div>

                    <div class="cluster-fy-body" hidden>
                        <?php foreach ($yearGroup['crops'] as $cropName => $cropGroup): ?>
                            <div class="cluster-crop-group" data-crop="<?= e(strtolower($cropName)) ?>">
                                <div class="cluster-crop-group__head" role="button" tabindex="0" aria-expanded="false">
                                    <h4><?= e($cropName) ?> (<?= e((string) count($cropGroup['records'])) ?> records)</h4>
                                    <button class="cluster-crop-view-btn" type="button" tabindex="-1"><span>View</span> <i class="fa-solid fa-chevron-down"></i></button>
                                </div>
                                <div class="cluster-crop-body" hidden>
                                    <div class="cluster-table-wrapper">
                                        <table class="cluster-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-sl">Sl. No.</th>
                                                    <th>Variety</th>
                                                    <th class="col-center">No of Demonstration</th>
                                                    <th class="col-center">Farmers</th>
                                                    <th class="col-center">Area Covered (HA)</th>
                                                    <th class="col-center">Production (In tonnes)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cropGroup['records'] as $recordIndex => $record): ?>
                                                    <?php
                                                        $variety = trim((string) ($record['variety'] ?? '')) ?: 'Not specified';
                                                        $area = trim((string) ($record['area_covered'] ?? '')) ?: 'Not specified';
                                                        $demonstrations = max(0, (int) ($record['demonstration_count'] ?? 0));
                                                        $farmers = max(0, (int) ($record['farmers_involved'] ?? 0));
                                                        $production = cluster_demonstration_details_decimal($record['production_tonnes'] ?? 0);
                                                        $searchText = strtolower(trim(cluster_demonstration_details_title($record) . ' ' . $year . ' ' . $cropName . ' ' . $variety . ' ' . $area . ' ' . $demonstrations . ' ' . $farmers . ' ' . $production));
                                                    ?>
                                                    <tr class="cluster-table-row" data-search="<?= e($searchText) ?>" data-crop="<?= e(strtolower($cropName)) ?>" data-fy="<?= e(strtolower($year)) ?>">
                                                        <td class="col-sl"><?= e((string) ($recordIndex + 1)) ?></td>
                                                        <td><strong><?= e($variety) ?></strong></td>
                                                        <td class="col-center"><?= e(number_format($demonstrations)) ?></td>
                                                        <td class="col-center"><?= e(number_format($farmers)) ?></td>
                                                        <td class="col-center"><?= e($area) ?></td>
                                                        <td class="col-center"><?= e($production) ?></td>
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

        <div class="cluster-empty" id="clusterRecordsEmpty" <?= $recordsByYear ? 'hidden' : '' ?>>
            <?= $recordsByYear ? 'No cluster demonstration records match your search query.' : 'No cluster demonstration records are available yet.' ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleYearGroup = (head) => {
        const group = head.closest('.cluster-fy-group');
        const body = group.querySelector('.cluster-fy-body');
        const isOpen = group.classList.contains('is-open');

        document.querySelectorAll('.cluster-fy-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.cluster-fy-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.cluster-fy-body').hidden = true;
            item.querySelector('.cluster-view-btn span').textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.cluster-view-btn span').textContent = 'Hide';
        }
    };

    const toggleCropGroup = (head) => {
        const group = head.closest('.cluster-crop-group');
        const body = group.querySelector('.cluster-crop-body');
        const isOpen = group.classList.contains('is-open');

        group.closest('.cluster-fy-body').querySelectorAll('.cluster-crop-group').forEach((item) => {
            item.classList.remove('is-open');
            item.querySelector('.cluster-crop-group__head').setAttribute('aria-expanded', 'false');
            item.querySelector('.cluster-crop-body').hidden = true;
            item.querySelector('.cluster-crop-view-btn span').textContent = 'View';
        });

        if (!isOpen) {
            group.classList.add('is-open');
            head.setAttribute('aria-expanded', 'true');
            body.hidden = false;
            group.querySelector('.cluster-crop-view-btn span').textContent = 'Hide';
        }
    };

    document.querySelectorAll('.cluster-fy-group__head').forEach((head) => {
        head.addEventListener('click', () => toggleYearGroup(head));
        head.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleYearGroup(head);
            }
        });
    });

    document.querySelectorAll('.cluster-crop-group__head').forEach((head) => {
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

    const searchInput = document.getElementById('clusterRecordSearch');
    const cropSelect = document.getElementById('clusterRecordCrop');
    const yearSelect = document.getElementById('clusterRecordYear');
    const emptyState = document.getElementById('clusterRecordsEmpty');

    const runFilter = () => {
        const query = searchInput.value.trim().toLowerCase();
        const crop = cropSelect.value;
        const year = yearSelect.value;
        let visibleCount = 0;

        document.querySelectorAll('.cluster-fy-group').forEach((fyGroup) => {
            let yearHasVisible = false;
            fyGroup.querySelectorAll('.cluster-crop-group').forEach((cropGroup) => {
                let cropHasVisible = false;
                cropGroup.querySelectorAll('.cluster-table-row').forEach((row) => {
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
                        cropGroup.querySelector('.cluster-crop-body').hidden = false;
                        cropGroup.querySelector('.cluster-crop-view-btn span').textContent = 'Hide';
                    }
                }
            });
            fyGroup.hidden = !yearHasVisible;
            if (yearHasVisible && (query || crop || year)) {
                fyGroup.classList.add('is-open');
                fyGroup.querySelector('.cluster-fy-body').hidden = false;
                fyGroup.querySelector('.cluster-view-btn span').textContent = 'Hide';
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
