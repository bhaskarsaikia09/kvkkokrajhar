<?php
require_once __DIR__ . '/app/content.php';
$page_title = 'Events & Trainings | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$events = records('events', 'events', 'is_active = 1', 'event_date DESC, created_at DESC', 100);
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Events</p>
        <h1>Trainings, field days, and extension programmes</h1>
    </div>
</section>
<section class="section">
    <div class="container card-grid">
        <?php foreach ($events as $event): ?>
            <article class="card card--large">
                <span class="pill"><?= e($event['event_date'] ?? '') ?></span>
                <h2><?= e($event['title']) ?></h2>
                <p><?= e($event['summary'] ?? '') ?></p>
                <a href="event.php?id=<?= e((string) ($event['id'] ?? 0)) ?>">View event</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
