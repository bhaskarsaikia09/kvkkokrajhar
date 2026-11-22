<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Vocational Training Details | ' . APP_NAME;

function vocational_training_ensure_table(?PDO $pdo): void
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

function vocational_training_records(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    vocational_training_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT tr.*, a.summary, a.image_path
        FROM training_records tr
        LEFT JOIN activities a ON a.id = tr.activity_id
        WHERE tr.is_active = 1
            AND (a.is_active = 1 OR a.id IS NULL)
            AND LOWER(COALESCE(tr.training_type, '')) = 'vocational'
        ORDER BY COALESCE(tr.duration_from, tr.training_date, DATE(tr.created_at)) DESC, tr.id DESC");
    $stmt->execute();

    $records = $stmt ? $stmt->fetchAll() : [];
    foreach ($records as &$record) {
        $record['source'] = 'training_records';
    }
    unset($record);

    return array_merge($records, vocational_training_official_records($pdo));
}

function vocational_training_ensure_official_table(?PDO $pdo): void
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

function vocational_training_official_records(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    vocational_training_ensure_official_table($pdo);
    $stmt = $pdo->prepare("SELECT id, financial_year, data_json, created_at
        FROM official_activity_records
        WHERE is_active = 1 AND record_type = 'vocational_trainings'
        ORDER BY financial_year DESC, created_at DESC, id DESC");
    $stmt->execute();

    $records = [];
    foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        if (!is_array($data)) {
            continue;
        }

        $trainingArea = trim((string) ($data['training_area'] ?? ''));
        $disciplines = trim((string) ($data['disciplines'] ?? ''));
        $durationDays = max(0, (int) ($data['duration_days'] ?? 0));
        $participantCount = max(0, (int) ($data['participant_count'] ?? 0));
        $financialYear = trim((string) ($row['financial_year'] ?? ($data['year'] ?? '')));

        $records[] = [
            'id' => (int) ($row['id'] ?? 0),
            'activity_id' => 0,
            'training_name' => $trainingArea !== '' ? $trainingArea : 'Vocational Training',
            'training_date' => '',
            'training_location' => '',
            'discipline' => $disciplines,
            'training_type' => 'Vocational',
            'training_area' => $trainingArea,
            'duration_from' => '',
            'duration_to' => '',
            'duration_days' => $durationDays,
            'financial_year' => $financialYear,
            'farmers_men' => 0,
            'farmers_women' => 0,
            'rural_youth_men' => 0,
            'rural_youth_women' => 0,
            'ef_men' => 0,
            'ef_women' => 0,
            'total_participants' => $participantCount,
            'summary' => '',
            'image_path' => '',
            'source' => 'official_activity_records',
        ];
    }

    return $records;
}

function vocational_training_photos(array $record): array
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

function vocational_training_date(?string $date): string
{
    if (!$date) {
        return 'Not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

function vocational_training_duration(array $record): string
{
    if (!empty($record['duration_days'])) {
        $days = max(0, (int) $record['duration_days']);
        return $days . ' day' . ($days === 1 ? '' : 's');
    }

    $from = vocational_training_date($record['duration_from'] ?? ($record['training_date'] ?? ''));
    $to = vocational_training_date($record['duration_to'] ?? '');

    if ($from === 'Not specified' && $to === 'Not specified') {
        return 'Not specified';
    }
    if ($to === 'Not specified' || $to === $from) {
        return $from;
    }

    return $from . ' to ' . $to;
}

function vocational_training_total(array $record): int
{
    $total = max(0, (int) ($record['farmers_men'] ?? 0))
        + max(0, (int) ($record['farmers_women'] ?? 0))
        + max(0, (int) ($record['rural_youth_men'] ?? 0))
        + max(0, (int) ($record['rural_youth_women'] ?? 0))
        + max(0, (int) ($record['ef_men'] ?? 0))
        + max(0, (int) ($record['ef_women'] ?? 0));

    return $total ?: max(0, (int) ($record['total_participants'] ?? 0));
}

$vocationalRecords = vocational_training_records();
$totalParticipants = 0;
$trainingAreas = [];
$heroPhoto = '';
foreach ($vocationalRecords as $record) {
    $totalParticipants += vocational_training_total($record);
    $area = trim((string) ($record['training_area'] ?? ''));
    if ($area !== '') {
        $trainingAreas[$area] = true;
    }
    if (!$heroPhoto) {
        $photos = vocational_training_photos($record);
        $heroPhoto = $photos[0] ?? '';
    }
}
ksort($trainingAreas, SORT_NATURAL | SORT_FLAG_CASE);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero training-records-hero"<?= $heroPhoto ? ' style="background-image: linear-gradient(90deg, rgba(8, 24, 18, .88), rgba(16, 66, 41, .48)), url(\'' . e($heroPhoto) . '\');"' : '' ?>>
    <div class="container">
        <p class="eyebrow">Vocational Training</p>
        <h1>Vocational training details</h1>
        <p>Browse vocational training programmes saved in the training database, including discipline, training area, duration, participants, and photographs.</p>
        <div class="training-records-hero__stats">
            <div><strong><?= e(number_format(count($vocationalRecords))) ?></strong><span>Vocational records</span></div>
            <div><strong><?= e(number_format($totalParticipants)) ?></strong><span>Participants</span></div>
            <div><strong><?= e(number_format(count($trainingAreas))) ?></strong><span>Training areas</span></div>
        </div>
    </div>
</section>
<section class="section training-records-page">
    <div class="container">
        <div class="section-head training-records-intro">
            <span>Training Database</span>
            <h2>Vocational programmes</h2>
            <p>Records shown here are filtered from the training desk where the type of training is marked as Vocational, including the official vocational training table.</p>
        </div>
        <div class="training-records-toolbar vocational-training-toolbar" aria-label="Vocational training filters">
            <label>
                <span>Search records</span>
                <input type="search" id="vocationalTrainingSearch" placeholder="Search by name, area, discipline">
            </label>
            <label>
                <span>Training area</span>
                <select id="vocationalTrainingArea">
                    <option value="">All areas</option>
                    <?php foreach (array_keys($trainingAreas) as $area): ?>
                        <option value="<?= e(strtolower($area)) ?>"><?= e($area) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if (!$vocationalRecords): ?>
            <div class="alert">No vocational training records have been added yet.</div>
        <?php endif; ?>
        <div class="vocational-training-grid" id="vocational-training-list">
            <?php foreach ($vocationalRecords as $record): ?>
                <?php
                    $photos = vocational_training_photos($record);
                    $trainingName = (string) ($record['training_name'] ?: 'Vocational Training');
                    $recordId = (int) ($record['activity_id'] ?? 0);
                    $area = trim((string) ($record['training_area'] ?? ''));
                    $financialYear = trim((string) ($record['financial_year'] ?? ''));
                    $searchText = strtolower(trim($trainingName . ' ' . ($record['discipline'] ?? '') . ' ' . $area . ' ' . vocational_training_duration($record) . ' ' . $financialYear));
                ?>
                <article class="vocational-training-card" data-search="<?= e($searchText) ?>" data-area="<?= e(strtolower($area)) ?>">
                    <?php if ($photos): ?>
                        <img src="<?= e($photos[0]) ?>" alt="<?= e($trainingName) ?>">
                    <?php else: ?>
                        <div class="vocational-training-card__placeholder">Vocational Training</div>
                    <?php endif; ?>
                    <div>
                        <span><?= e($record['discipline'] ?: 'General') ?></span>
                        <h3><?= e($trainingName) ?></h3>
                        <dl>
                            <?php if ($financialYear !== ''): ?>
                                <div><dt>Financial year</dt><dd><?= e($financialYear) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Training area</dt><dd><?= e($area ?: 'Not specified') ?></dd></div>
                            <div><dt>Duration</dt><dd><?= e(vocational_training_duration($record)) ?></dd></div>
                            <div><dt>Participants</dt><dd><?= e((string) vocational_training_total($record)) ?></dd></div>
                        </dl>
                        <?php if ($recordId > 0): ?>
                            <a class="btn btn--primary" href="training-record.php?id=<?= e((string) $recordId) ?>">View details</a>
                        <?php else: ?>
                            <span class="pill">Official Format-1 Record</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="alert training-records-empty" id="vocationalTrainingEmpty" hidden>No vocational training records match your search.</div>
    </div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('vocationalTrainingSearch');
        const areaSelect = document.getElementById('vocationalTrainingArea');
        const cards = Array.from(document.querySelectorAll('.vocational-training-card'));
        const emptyState = document.getElementById('vocationalTrainingEmpty');

        if (!searchInput || !areaSelect || !cards.length) {
            return;
        }

        const filterCards = () => {
            const query = searchInput.value.trim().toLowerCase();
            const area = areaSelect.value;
            let visibleCount = 0;

            cards.forEach((card) => {
                const visible = (!query || card.dataset.search.includes(query)) && (!area || card.dataset.area === area);
                card.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            emptyState.hidden = visibleCount !== 0;
        };

        searchInput.addEventListener('input', filterCards);
        areaSelect.addEventListener('change', filterCards);
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
