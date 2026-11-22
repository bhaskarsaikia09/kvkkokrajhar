<?php
require_once __DIR__ . '/app/content.php';

$id = (int) ($_GET['id'] ?? 0);
$activity = db_find('activities', $id) ?: (fallback_data('activities')[$id - 1] ?? null);

function training_records_page_ensure_table(?PDO $pdo): void
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
    foreach (['photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $column) {
        try {
            $pdo->exec("ALTER TABLE training_records ADD COLUMN {$column} VARCHAR(255) NULL");
        } catch (Throwable $exception) {
            // Column already exists.
        }
    }
}

function training_record_from_database(int $activityId): ?array
{
    $pdo = db();
    if (!$pdo || $activityId <= 0) {
        return null;
    }

    training_records_page_ensure_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM training_records WHERE activity_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$activityId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function training_record_from_activity_details(string $details): ?array
{
    $decoded = json_decode($details, true);
    if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'training_record') {
        return null;
    }

    $fields = [
        'training_name' => '',
        'training_date' => '',
        'training_location' => '',
        'discipline' => '',
        'farm_women' => '',
        'rural_youth' => '',
        'ef' => '',
        'total_participants' => '',
        'photograph_path' => '',
        'photograph_path_2' => '',
        'photograph_path_3' => '',
        'photograph_path_4' => '',
        'photograph_path_5' => '',
    ];

    $data = array_merge($fields, array_intersect_key($decoded['data'] ?? [], $fields));
    if ($data['farm_women'] === '' && isset($decoded['data']['ffw_participants'])) {
        $data['farm_women'] = (string) $decoded['data']['ffw_participants'];
    }
    if ($data['rural_youth'] === '' && isset($decoded['data']['ry_participants'])) {
        $data['rural_youth'] = (string) $decoded['data']['ry_participants'];
    }
    if ($data['ef'] === '' && isset($decoded['data']['ef_participants'])) {
        $data['ef'] = (string) $decoded['data']['ef_participants'];
    }

    return $data;
}

$trainingRecord = training_record_from_database((int) ($activity['id'] ?? 0))
    ?: training_record_from_activity_details((string) ($activity['details'] ?? ''));
$trainingTitle = $trainingRecord['training_name'] ?? ($activity['title'] ?? 'Training Record');
$trainingPhotos = [];
foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $photoField) {
    if (!empty($trainingRecord[$photoField])) {
        $trainingPhotos[] = (string) $trainingRecord[$photoField];
    }
}
if (!$trainingPhotos && !empty($activity['image_path'])) {
    $trainingPhotos[] = (string) $activity['image_path'];
}
$page_title = $trainingTitle . ' | ' . APP_NAME;

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Training Record</p>
        <h1><?= e($trainingTitle) ?></h1>
        <p><?= e($activity['summary'] ?? 'Training data record of KVK Kokrajhar.') ?></p>
    </div>
</section>
<section class="section">
    <article class="container training-detail-page">
        <?php if (!$trainingRecord): ?>
            <div class="alert">Training record not found.</div>
        <?php else: ?>
            <div class="training-detail-layout">
                <div class="training-detail-media">
                    <?php if ($trainingPhotos): ?>
                        <img src="<?= e($trainingPhotos[0]) ?>" alt="<?= e($trainingTitle) ?>">
                    <?php else: ?>
                        <div class="training-detail-media__placeholder">Training Photograph</div>
                    <?php endif; ?>
                </div>
                <div class="training-detail-card">
                    <span>Training Record</span>
                    <h2><?= e($trainingTitle) ?></h2>
                    <dl class="training-meta-list">
                        <div>
                            <dt>Date of training</dt>
                            <dd><?= e($trainingRecord['training_date'] ?: 'Not specified') ?></dd>
                        </div>
                        <div>
                            <dt>Location of training</dt>
                            <dd><?= e($trainingRecord['training_location'] ?: 'Not specified') ?></dd>
                        </div>
                        <div>
                            <dt>Discipline</dt>
                            <dd><?= e($trainingRecord['discipline'] ?: 'Not specified') ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
            <div class="training-participant-panel">
                <div>
                    <span>Participant Present</span>
                    <h2><?= e($trainingRecord['total_participants'] ?: '0') ?></h2>
                    <p>Total No. of participants</p>
                </div>
                <article>
                    <strong><?= e($trainingRecord['farm_women'] ?: '0') ?></strong>
                    <span>Farm Women</span>
                </article>
                <article>
                    <strong><?= e($trainingRecord['rural_youth'] ?: '0') ?></strong>
                    <span>Rural Youth</span>
                </article>
                <article>
                    <strong><?= e($trainingRecord['ef'] ?: '0') ?></strong>
                    <span>EF</span>
                </article>
            </div>
            <?php if (count($trainingPhotos) > 1): ?>
                <div class="training-photo-gallery">
                    <?php foreach ($trainingPhotos as $index => $photoPath): ?>
                        <a href="<?= e($photoPath) ?>" target="_blank">
                            <img src="<?= e($photoPath) ?>" alt="<?= e($trainingTitle . ' photo ' . ($index + 1)) ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
