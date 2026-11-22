<?php
require_once __DIR__ . '/app/content.php';
$id = (int) ($_GET['id'] ?? 0);
$event = db_find('events', $id) ?: (fallback_data('events')[$id - 1] ?? null);
$page_title = ($event['title'] ?? 'Event') . ' | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Event</p>
        <h1><?= e($event['title'] ?? 'Event not found') ?></h1>
        <p><?= e($event['event_date'] ?? '') ?> · <?= e($event['location'] ?? '') ?></p>
    </div>
</section>
<section class="section">
    <article class="container prose">
        <?php if (!empty($event['image_path'])): ?><img class="detail-image" src="<?= e($event['image_path']) ?>" alt="<?= e($event['title']) ?>"><?php endif; ?>
        <p><?= nl2br(e($event['details'] ?? $event['summary'] ?? '')) ?></p>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
