<?php
require_once __DIR__ . '/app/content.php';
$page_title = 'Activities | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$activities = records('activities', 'activities', 'is_active = 1', 'title ASC', 50);

function activity_page_canonical_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    return match ($slug) {
        'fld', 'frontline-demonstration', 'frontline-demonstrations', 'front-line-demonstrations' => 'front-line-demonstration',
        'oft', 'on-farm-testing', 'on-farm-test', 'on-field-trail', 'on-field-trails' => 'on-field-trial',
        default => $slug,
    };
}

function activity_page_mandated_labels(): array
{
    return [
        'training' => 'Training',
        'front-line-demonstration' => 'Front Line Demonstration',
        'on-field-trial' => 'On Field Trial',
    ];
}

function activity_page_field_label(string $slug): string
{
    $labels = activity_page_mandated_labels();
    $canonical = activity_page_canonical_slug($slug);
    return $labels[$canonical] ?? ucwords(str_replace('-', ' ', $slug));
}

function activity_page_is_training_record(string $details): bool
{
    $decoded = json_decode($details, true);
    return is_array($decoded) && ($decoded['type'] ?? '') === 'training_record';
}

$mandatedActivities = [];
$otherActivities = [];
foreach ($activities as $activity) {
    $canonicalSlug = activity_page_canonical_slug((string) ($activity['slug'] ?? ''));
    if (array_key_exists($canonicalSlug, activity_page_mandated_labels())) {
        $mandatedActivities[] = $activity;
    } else {
        $otherActivities[] = $activity;
    }
}
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Activities</p>
        <h1>Programmes and extension services</h1>
        <p>Explore the main activities delivered by KVK Kokrajhar for farmers, farm women, rural youth, and extension workers.</p>
    </div>
</section>
<section class="section activities-page">
    <div class="container">
        <div class="section-head">
            <span>Mandated Activity</span>
            <h2>Core KVK activity fields</h2>
            <p>Training, front line demonstrations, and field trials are grouped separately for easier access.</p>
            <a class="btn btn--light" href="training-records.php">View all training records</a>
        </div>
        <div class="activity-card-grid">
            <?php foreach ($mandatedActivities as $activity): ?>
                <?php
                    $fieldLabel = activity_page_field_label((string) ($activity['slug'] ?? ''));
                    $isTrainingRecord = activity_page_canonical_slug((string) ($activity['slug'] ?? '')) === 'training'
                        || activity_page_is_training_record((string) ($activity['details'] ?? ''));
                ?>
                <article class="activity-list-card activity-list-card--mandated">
                    <?php if (!empty($activity['image_path'])): ?><img class="card-image" src="<?= e($activity['image_path']) ?>" alt="<?= e($activity['title']) ?>"><?php endif; ?>
                    <span class="pill"><?= e($fieldLabel) ?></span>
                    <h3><?= e($activity['title']) ?></h3>
                    <p><?= e($activity['summary']) ?></p>
                    <?php if ($isTrainingRecord): ?><small>Training data record available</small><?php endif; ?>
                    <a href="<?= $isTrainingRecord ? 'training-record.php' : 'activity.php' ?>?id=<?= e((string) ($activity['id'] ?? 0)) ?>"><?= $isTrainingRecord ? 'View training record' : 'View details' ?></a>
                </article>
            <?php endforeach; ?>
            <?php if (!$mandatedActivities): ?>
                <p class="muted">No mandated activities have been added yet.</p>
            <?php endif; ?>
        </div>

        <div class="section-head section-head--spaced">
            <span>Other Activities</span>
            <h2>Additional programmes and initiatives</h2>
            <p>Other extension services and special projects are listed here.</p>
            <a class="btn btn--light" href="public-outreach-programme.php">View Public Outreach Programme details</a>
            <a class="btn btn--light" href="planting-materials.php">View Planting Materials Available</a>
        </div>
        <div class="activity-card-grid">
            <?php foreach ($otherActivities as $activity): ?>
                <article class="activity-list-card">
                    <?php if (!empty($activity['image_path'])): ?><img class="card-image" src="<?= e($activity['image_path']) ?>" alt="<?= e($activity['title']) ?>"><?php endif; ?>
                    <?php if (!empty($activity['slug'])): ?><span class="pill"><?= e(activity_page_field_label((string) $activity['slug'])) ?></span><?php endif; ?>
                    <h3><?= e($activity['title']) ?></h3>
                    <p><?= e($activity['summary']) ?></p>
                    <a href="activity.php?id=<?= e((string) ($activity['id'] ?? 0)) ?>">View details</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$otherActivities): ?>
                <p class="muted">No other activities have been added yet.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
