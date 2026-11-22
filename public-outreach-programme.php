<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Public Outreach Programme | ' . APP_NAME;

function outreach_date(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return 'Not specified';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : $date;
}

function outreach_date_range(array $record, string $fromField, ?string $toField = null): string
{
    $from = trim((string) ($record[$fromField] ?? ''));
    $to = $toField ? trim((string) ($record[$toField] ?? '')) : '';

    if ($from === '' && $to === '') {
        return 'Not specified';
    }

    if ($to === '' || $to === $from) {
        return outreach_date($from);
    }

    return outreach_date($from) . ' to ' . outreach_date($to);
}

function outreach_year(array $record, ?string $yearField, string $dateField): string
{
    $year = $yearField ? trim((string) ($record[$yearField] ?? '')) : '';
    if ($year !== '') {
        return financial_year_normalize($year);
    }

    $date = trim((string) ($record[$dateField] ?? $record['created_at'] ?? ''));
    return $date !== '' ? current_financial_year($date) : 'Year not specified';
}

function outreach_total(array $record, ?string $maleField, ?string $femaleField, ?string $totalField = null): string
{
    if ($totalField) {
        return (string) max(0, (int) ($record[$totalField] ?? 0));
    }

    if (!$maleField && !$femaleField) {
        return '';
    }

    return (string) (max(0, (int) ($record[$maleField ?? ''] ?? 0)) + max(0, (int) ($record[$femaleField ?? ''] ?? 0)));
}

function outreach_fetch(string $table): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT r.*, a.title AS activity_title, a.slug AS activity_slug
            FROM {$table} r
            LEFT JOIN activities a ON a.id = r.activity_id
            WHERE r.is_active = 1 AND (a.is_active = 1 OR a.id IS NULL)
            ORDER BY r.id DESC");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $exception) {
        return [];
    }
}

$outreachSpecs = [
    ['label' => 'Exposure Visit', 'table' => 'exposure_visit', 'date' => 'visit_date', 'title' => 'visit_name', 'topic' => null, 'place' => 'location', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Learning Tours', 'table' => 'learning_tours', 'date' => 'visit_date', 'title' => 'visit_name', 'topic' => null, 'place' => 'location', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Field Exposure', 'table' => 'field_exposure', 'date' => 'visit_date', 'title' => 'visit_name', 'topic' => null, 'place' => 'location', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Exhibition', 'table' => 'exhibition_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'title' => 'exhibition_title', 'topic' => null, 'place' => 'venue', 'extra_label' => 'Collaboration', 'extra' => 'collaboration', 'male' => 'visitors_male', 'female' => 'visitors_female'],
    ['label' => 'Field Day', 'table' => 'field_day_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'title' => 'event_title', 'topic' => null, 'place' => 'venue', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'visitors_male', 'female' => 'visitors_female'],
    ['label' => 'Kisan Mela', 'table' => 'kisan_mela_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'title' => 'event_title', 'topic' => null, 'place' => 'venue', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'visitors_male', 'female' => 'visitors_female'],
    ['label' => 'PM Webcasting', 'table' => 'pm_webcasting_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'title' => 'event_title', 'topic' => null, 'place' => 'venue', 'extra_label' => 'Organised By', 'extra' => 'organised_by', 'male' => 'visitors_male', 'female' => 'visitors_female'],
    ['label' => 'Awareness Programme', 'table' => 'awareness_records', 'year' => 'year', 'date' => 'date_from', 'date_to' => 'date_to', 'title' => 'programme_title', 'topic' => 'thematic_area', 'place' => null, 'extra_label' => 'Officials M/F', 'extra_pair' => ['officials_male', 'officials_female'], 'male' => 'farmers_male', 'female' => 'farmers_female'],
    ['label' => 'Animal Health Camp', 'table' => 'animal_health_camp_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Awareness Camp', 'table' => 'awareness_camp_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Diagnostic Visit', 'table' => 'diagnostic_visit_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Farm Science Club Conveners Meet', 'table' => 'farm_science_club_conveners_meet_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Farmer Scientist Interaction', 'table' => 'farmer_scientist_interaction_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Film Show', 'table' => 'film_show_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Group Meetings', 'table' => 'group_meetings_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Mahila Mandal Conveners Meeting', 'table' => 'mahila_mandal_conveners_meeting_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Plant Health Camp', 'table' => 'plant_health_camp_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'PRA Exercise', 'table' => 'pra_exercise_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Self Help Group Conveners Meeting', 'table' => 'self_help_group_conveners_meeting_records', 'year' => 'year', 'date' => 'event_date', 'title' => 'particulars', 'topic' => 'topic', 'place' => 'location', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Celebration of Important Days', 'table' => 'important_day_records', 'date' => 'event_date', 'title' => 'topic', 'topic' => null, 'place' => 'venue', 'male' => 'beneficiaries_male', 'female' => 'beneficiaries_female'],
    ['label' => 'Formation of SHGs', 'table' => 'formation_of_shg_records', 'date' => 'formation_date', 'title' => 'record_name', 'topic' => null, 'place' => 'location', 'extra_label' => 'Remarks', 'extra' => 'remarks', 'total' => 'total_participants'],
    ['label' => 'Kisan Gosthi', 'table' => 'kisan_gosthi_records', 'date' => 'formation_date', 'title' => 'record_name', 'topic' => null, 'place' => 'location', 'extra_label' => 'Remarks', 'extra' => 'remarks', 'total' => 'total_participants'],
    ['label' => 'Lectures Delivered', 'table' => 'lecture_delivered_records', 'date' => 'programme_date', 'title' => 'programme_name', 'topic' => 'topic', 'place' => 'venue', 'extra_label' => 'Organised By', 'extra' => 'organised_by'],
    ['label' => 'Radio Talks', 'table' => 'radio_talk_records', 'date' => 'talk_date', 'title' => 'topic', 'topic' => null, 'place_label' => 'Radio Station', 'place' => 'radio_station', 'extra_label' => 'Targeted Beneficiaries', 'extra' => 'targeted_beneficiaries'],
    ['label' => 'TV Talks', 'table' => 'tv_talk_records', 'date' => 'talk_date', 'title' => 'topic', 'topic' => null, 'place_label' => 'TV Station', 'place' => 'tv_station', 'extra_label' => 'Targeted Beneficiaries', 'extra' => 'targeted_beneficiaries'],
];

$recordsByYear = [];
$totalRecords = 0;
$totalBeneficiaries = 0;

foreach ($outreachSpecs as $spec) {
    foreach (outreach_fetch($spec['table']) as $record) {
        $dateField = (string) $spec['date'];
        $year = outreach_year($record, $spec['year'] ?? null, $dateField);
        $total = outreach_total($record, $spec['male'] ?? null, $spec['female'] ?? null, $spec['total'] ?? null);
        if ($total !== '') {
            $totalBeneficiaries += (int) $total;
        }

        $recordsByYear[$year][] = [
            'card' => $spec['label'],
            'date' => outreach_date_range($record, $dateField, $spec['date_to'] ?? null),
            'title' => (string) (($record[$spec['title']] ?? '') ?: ($record['activity_title'] ?? $spec['label'])),
            'topic' => isset($spec['topic']) ? (string) ($record[$spec['topic']] ?? '') : '',
            'place_label' => (string) ($spec['place_label'] ?? 'Venue/Location'),
            'place' => isset($spec['place']) ? (string) ($record[$spec['place']] ?? '') : '',
            'extra_label' => (string) ($spec['extra_label'] ?? ''),
            'extra' => isset($spec['extra_pair'])
                ? (string) ($record[$spec['extra_pair'][0]] ?? '0') . ' / ' . (string) ($record[$spec['extra_pair'][1]] ?? '0')
                : (isset($spec['extra']) ? (string) ($record[$spec['extra']] ?? '') : ''),
            'male' => isset($spec['male']) ? (string) ($record[$spec['male']] ?? '0') : '',
            'female' => isset($spec['female']) ? (string) ($record[$spec['female']] ?? '0') : '',
            'total' => $total,
        ];
        $totalRecords++;
    }
}

uksort($recordsByYear, static function (string $a, string $b): int {
    preg_match('/\d{4}/', $a, $aMatch);
    preg_match('/\d{4}/', $b, $bMatch);
    return ((int) ($bMatch[0] ?? 0)) <=> ((int) ($aMatch[0] ?? 0));
});

require_once __DIR__ . '/includes/header.php';
?>
<style>
    .outreach-page {
        background: #f7fbf5;
    }

    .outreach-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .outreach-kpi,
    .outreach-year-block {
        border: 1px solid #dce8da;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 14px 34px rgba(20, 65, 40, .08);
    }

    .outreach-kpi {
        padding: 18px;
    }

    .outreach-kpi span {
        display: block;
        color: #66756d;
        font-weight: 700;
        font-size: .9rem;
    }

    .outreach-kpi strong {
        display: block;
        margin-top: 6px;
        color: #176b3a;
        font-size: 2rem;
        line-height: 1;
    }

    .outreach-year-block {
        margin-top: 22px;
        overflow: hidden;
    }

    .outreach-year-head {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        color: #fff;
        background: linear-gradient(135deg, #176b3a, #2f8a50);
    }

    .outreach-year-head h2,
    .outreach-year-head p {
        margin: 0;
    }

    .outreach-title {
        color: #102a1c;
        font-weight: 800;
    }

    .outreach-years {
        display: grid;
        gap: 14px;
    }

    .outreach-year-block {
        overflow: hidden;
    }

    .outreach-year-block > summary,
    .outreach-programme > summary {
        cursor: pointer;
        list-style: none;
    }

    .outreach-year-block > summary::-webkit-details-marker,
    .outreach-programme > summary::-webkit-details-marker {
        display: none;
    }

    .outreach-empty {
        padding: 28px;
        border: 1px dashed #cbd8c8;
        border-radius: 8px;
        color: #66756d;
        background: #fff;
        text-align: center;
    }

    .outreach-programme-list {
        display: grid;
        gap: 12px;
        padding: 18px;
        background: #f8fbf6;
    }

    .outreach-programme {
        border: 1px solid #dce8da;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .outreach-programme > summary {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px;
    }

    .outreach-programme-main {
        display: grid;
        gap: 8px;
    }

    .outreach-programme-title {
        margin: 0;
        color: #102a1c;
        font-size: 1.02rem;
        font-weight: 900;
        line-height: 1.35;
    }

    .outreach-programme-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: #66756d;
        font-size: .9rem;
    }

    .outreach-open-mark {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        color: #176b3a;
        background: #eef8f1;
        font-weight: 900;
        transition: transform .2s ease;
    }

    details[open] > summary .outreach-open-mark {
        transform: rotate(45deg);
    }

    .outreach-details-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        padding: 0 16px 16px;
    }

    .outreach-detail-item {
        min-height: 82px;
        padding: 12px;
        border: 1px solid #e6eee3;
        border-radius: 8px;
        background: #fbfdf9;
    }

    .outreach-detail-item span {
        display: block;
        margin-bottom: 5px;
        color: #66756d;
        font-size: .78rem;
        font-weight: 900;
        text-transform: uppercase;
    }

    .outreach-detail-item strong,
    .outreach-detail-item p {
        margin: 0;
        color: #102a1c;
        line-height: 1.45;
    }

    @media (max-width: 780px) {
        .outreach-summary,
        .outreach-details-grid {
            grid-template-columns: 1fr;
        }

        .outreach-programme > summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Extension Activities</p>
        <h1>Public Outreach Programme Details</h1>
        <p>Year-wise records displayed from the Public Outreach Programme cards maintained in the admin activity desk.</p>
    </div>
</section>

<section class="section outreach-page">
    <div class="container">
        <div class="outreach-summary">
            <article class="outreach-kpi"><span>Total outreach records</span><strong><?= e((string) $totalRecords) ?></strong></article>
            <article class="outreach-kpi"><span>Years covered</span><strong><?= e((string) count($recordsByYear)) ?></strong></article>
            <article class="outreach-kpi"><span>Total beneficiaries/participants</span><strong><?= e((string) $totalBeneficiaries) ?></strong></article>
        </div>

        <?php if (!$recordsByYear): ?>
            <div class="outreach-empty">No Public Outreach Programme records have been published yet.</div>
        <?php endif; ?>

        <div class="outreach-years">
            <?php foreach ($recordsByYear as $year => $records): ?>
                <details class="outreach-year-block">
                    <summary class="outreach-year-head">
                        <div>
                            <p>Financial Year</p>
                            <h2><?= e($year) ?></h2>
                        </div>
                        <p><?= e((string) count($records)) ?> programmes <span class="outreach-open-mark">+</span></p>
                    </summary>
                    <div class="outreach-programme-list">
                        <?php foreach ($records as $record): ?>
                            <details class="outreach-programme">
                                <summary>
                                    <div class="outreach-programme-main">
                                        <h3 class="outreach-programme-title"><?= e($record['title']) ?></h3>
                                        <div class="outreach-programme-meta">
                                            <span><?= e($record['date']) ?></span>
                                            <?php if ($record['topic'] !== ''): ?><span>Topic: <?= e($record['topic']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="outreach-open-mark">+</span>
                                </summary>
                                <div class="outreach-details-grid">
                                    <article class="outreach-detail-item">
                                        <span>Date</span>
                                        <strong><?= e($record['date']) ?></strong>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span>Programme / Particulars</span>
                                        <p><?= e($record['title']) ?></p>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span>Topic</span>
                                        <p><?= e($record['topic'] ?: '-') ?></p>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span><?= e($record['place_label']) ?></span>
                                        <p><?= e($record['place'] ?: '-') ?></p>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span>Beneficiaries Male / Female</span>
                                        <strong><?= $record['male'] !== '' || $record['female'] !== '' ? e($record['male'] . ' / ' . $record['female']) : '-' ?></strong>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span>Total</span>
                                        <strong><?= e($record['total'] !== '' ? $record['total'] : '-') ?></strong>
                                    </article>
                                    <article class="outreach-detail-item">
                                        <span><?= e($record['extra_label'] ?: 'Other Details') ?></span>
                                        <p><?= e($record['extra'] ?: '-') ?></p>
                                    </article>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
