<?php
require_once __DIR__ . '/app/content.php';

$id = (int) ($_GET['id'] ?? 0);
$activity = db_find('activities', $id) ?: (fallback_data('activities')[$id - 1] ?? null);
$page_title = ($activity['title'] ?? 'Activity') . ' | ' . APP_NAME;

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Activity</p>
        <h1><?= e($activity['title'] ?? 'Activity not found') ?></h1>
        <p><?= e($activity['summary'] ?? '') ?></p>
    </div>
</section>
<section class="section">
    <article class="container prose">
        <?php if (!empty($activity['image_path'])): ?><img class="detail-image" src="<?= e($activity['image_path']) ?>" alt="<?= e($activity['title']) ?>"><?php endif; ?>
        <p><?= nl2br(e($activity['details'] ?? $activity['summary'] ?? '')) ?></p>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
