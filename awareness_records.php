<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Awareness Programme | ' . APP_NAME;

function awareness_records_public_ensure_table(?PDO $pdo): void
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

function awareness_records_public_all(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    awareness_records_public_ensure_table($pdo);
    $stmt = $pdo->query("SELECT ar.*, a.title AS activity_title, a.summary AS activity_summary
        FROM awareness_records ar
        LEFT JOIN activities a ON a.id = ar.activity_id
        WHERE ar.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
        ORDER BY COALESCE(ar.date_from, ar.programme_date, DATE(ar.created_at)) DESC, ar.id DESC");

    return $stmt ? $stmt->fetchAll() : [];
}

function awareness_records_public_year(array $record): string
{
    $year = trim((string) ($record['year'] ?? ''));
    if ($year !== '') {
        return financial_year_normalize($year);
    }

    $date = trim((string) (($record['date_from'] ?? '') ?: ($record['programme_date'] ?? '')));
    if ($date === '') {
        $date = trim((string) ($record['created_at'] ?? ''));
    }

    return $date !== '' ? current_financial_year($date) : 'Year not specified';
}

function awareness_records_public_date(?string $date): string
{
    if (!$date) {
        return 'Date not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

function awareness_records_public_date_range(array $record): string
{
    $dateFrom = trim((string) (($record['date_from'] ?? '') ?: ($record['programme_date'] ?? '')));
    $dateTo = trim((string) (($record['date_to'] ?? '') ?: $dateFrom));

    if ($dateFrom === '' && $dateTo === '') {
        return 'Date not specified';
    }

    if ($dateFrom !== '' && ($dateTo === '' || $dateTo === $dateFrom)) {
        return awareness_records_public_date($dateFrom);
    }

    return awareness_records_public_date($dateFrom) . ' to ' . awareness_records_public_date($dateTo);
}

function awareness_records_public_total(array $record): int
{
    return (int) ($record['farmers_male'] ?? 0)
        + (int) ($record['farmers_female'] ?? 0)
        + (int) ($record['ry_male'] ?? 0)
        + (int) ($record['ry_female'] ?? 0)
        + (int) ($record['officials_male'] ?? 0)
        + (int) ($record['officials_female'] ?? 0);
}

$awarenessRecords = awareness_records_public_all();
$recordsByYear = [];
$totalParticipants = 0;
$totalFarmers = 0;
$totalRy = 0;
$totalOfficials = 0;

foreach ($awarenessRecords as $record) {
    $year = awareness_records_public_year($record);
    if (!isset($recordsByYear[$year])) {
        $recordsByYear[$year] = [
            'records' => [],
            'programmes' => 0,
            'participants' => 0,
            'farmers' => 0,
            'ry' => 0,
            'officials' => 0,
        ];
    }

    $farmers = (int) ($record['farmers_male'] ?? 0) + (int) ($record['farmers_female'] ?? 0);
    $ry = (int) ($record['ry_male'] ?? 0) + (int) ($record['ry_female'] ?? 0);
    $officials = (int) ($record['officials_male'] ?? 0) + (int) ($record['officials_female'] ?? 0);
    $participants = $farmers + $ry + $officials;

    $recordsByYear[$year]['records'][] = $record;
    $recordsByYear[$year]['programmes']++;
    $recordsByYear[$year]['participants'] += $participants;
    $recordsByYear[$year]['farmers'] += $farmers;
    $recordsByYear[$year]['ry'] += $ry;
    $recordsByYear[$year]['officials'] += $officials;

    $totalParticipants += $participants;
    $totalFarmers += $farmers;
    $totalRy += $ry;
    $totalOfficials += $officials;
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

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .awareness-page {
        background: #f7faf8;
        color: #18251f;
    }

    .awareness-hero {
        background: linear-gradient(135deg, rgba(10, 77, 46, 0.94), rgba(24, 120, 72, 0.88)), url('assets/img/kvk-building.jpg') center/cover;
        color: #ffffff;
        padding: 4.5rem 0 3.5rem;
    }

    .awareness-hero .eyebrow {
        color: #c9f7d8;
        font-weight: 800;
        letter-spacing: 0;
        margin-bottom: 0.65rem;
        text-transform: uppercase;
    }

    .awareness-hero h1 {
        color: #ffffff;
        font-size: clamp(2rem, 4vw, 3.5rem);
        line-height: 1.08;
        margin: 0 0 1rem;
        max-width: 760px;
    }

    .awareness-hero p {
        color: rgba(255, 255, 255, 0.88);
        max-width: 760px;
        font-size: 1.05rem;
        line-height: 1.7;
    }

    .awareness-hero__stats,
    .awareness-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 2rem;
    }

    .awareness-hero__stats div,
    .awareness-summary article {
        background: rgba(255, 255, 255, 0.13);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 8px;
        padding: 1rem;
    }

    .awareness-hero__stats strong,
    .awareness-summary strong {
        display: block;
        font-size: 1.8rem;
        line-height: 1;
    }

    .awareness-hero__stats span,
    .awareness-summary span {
        display: block;
        margin-top: 0.4rem;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.88rem;
    }

    .awareness-content {
        padding: 2.5rem 0 4rem;
    }

    .awareness-summary {
        margin: 0 0 1.5rem;
    }

    .awareness-summary article {
        background: #ffffff;
        border-color: #e2e8f0;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .awareness-summary strong {
        color: #0a4d2e;
    }

    .awareness-summary span {
        color: #64748b;
    }

    .awareness-year {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
        margin-top: 1.25rem;
        overflow: hidden;
    }

    .awareness-year__head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        background: #0a4d2e;
        color: #ffffff;
        padding: 1rem 1.25rem;
    }

    .awareness-year__head h2 {
        color: #ffffff;
        margin: 0;
        font-size: 1.25rem;
    }

    .awareness-year__head dl {
        display: flex;
        gap: 0.75rem;
        margin: 0;
    }

    .awareness-year__head div {
        min-width: 86px;
    }

    .awareness-year__head dt {
        color: rgba(255, 255, 255, 0.76);
        font-size: 0.72rem;
        margin: 0;
    }

    .awareness-year__head dd {
        font-weight: 800;
        margin: 0.2rem 0 0;
    }

    .awareness-table-wrap {
        overflow-x: auto;
    }

    .awareness-table {
        border-collapse: collapse;
        min-width: 620px;
        width: 100%;
    }

    .awareness-table th,
    .awareness-table td {
        border-bottom: 1px solid #e2e8f0;
        padding: 0.85rem;
        text-align: left;
        vertical-align: top;
    }

    .awareness-table th {
        background: #f1f5f3;
        color: #334155;
        font-size: 0.78rem;
        text-transform: uppercase;
    }

    .awareness-table td {
        color: #27362f;
        font-size: 0.92rem;
    }

    .awareness-table tbody tr:hover {
        background: #f8fbf9;
    }

    .awareness-title {
        font-weight: 800;
        color: #0a4d2e;
        min-width: 220px;
    }

    .awareness-empty {
        background: #ffffff;
        border: 1px dashed #b7c8bd;
        border-radius: 8px;
        color: #64748b;
        padding: 2rem;
        text-align: center;
    }

    @media (max-width: 900px) {
        .awareness-hero__stats,
        .awareness-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .awareness-year__head {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (max-width: 560px) {
        .awareness-hero__stats,
        .awareness-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="awareness-page">
    <div class="awareness-hero">
        <div class="container">
            <p class="eyebrow">Extension Activities</p>
            <h1>Awareness Programme</h1>
            <p>Year-wise awareness programme records conducted by KVK Kokrajhar, with programme period, title, and total participants.</p>
            <div class="awareness-hero__stats">
                <div>
                    <strong><?= e(number_format(count($awarenessRecords))) ?></strong>
                    <span>Total Programmes</span>
                </div>
                <div>
                    <strong><?= e(number_format($totalParticipants)) ?></strong>
                    <span>Total Participants</span>
                </div>
                <div>
                    <strong><?= e(number_format(count($recordsByYear))) ?></strong>
                    <span>Reporting Years</span>
                </div>
                <div>
                    <strong><?= e(number_format($totalParticipants)) ?></strong>
                    <span>Participants Covered</span>
                </div>
            </div>
        </div>
    </div>

    <div class="awareness-content">
        <div class="container">
            <?php if (!$recordsByYear): ?>
                <div class="awareness-empty">
                    No awareness programme records are available yet.
                </div>
            <?php endif; ?>

            <?php foreach ($recordsByYear as $year => $yearGroup): ?>
                <section class="awareness-year" aria-labelledby="awareness-year-<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($year))) ?>">
                    <div class="awareness-year__head">
                        <h2 id="awareness-year-<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($year))) ?>"><?= e($year) ?></h2>
                        <dl>
                            <div>
                                <dt>Programmes</dt>
                                <dd><?= e(number_format((int) $yearGroup['programmes'])) ?></dd>
                            </div>
                            <div>
                                <dt>Participants</dt>
                                <dd><?= e(number_format((int) $yearGroup['participants'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="awareness-table-wrap">
                        <table class="awareness-table">
                            <thead>
                                <tr>
                                    <th>Sl No</th>
                                    <th>Date</th>
                                    <th>Programme Title</th>
                                    <th>Total Participants</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yearGroup['records'] as $recordIndex => $record): ?>
                                    <tr>
                                        <td><?= e((string) ($recordIndex + 1)) ?></td>
                                        <td><?= e(awareness_records_public_date_range($record)) ?></td>
                                        <td class="awareness-title"><?= e((string) (($record['programme_title'] ?? '') ?: ($record['activity_title'] ?? 'Awareness Programme'))) ?></td>
                                        <td><strong><?= e(number_format(awareness_records_public_total($record))) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
