<?php
require_once __DIR__ . '/app/content.php';
$id = (int) ($_GET['id'] ?? 0);
$story = db_find('success_stories', $id) ?: (fallback_data('stories')[$id - 1] ?? null);
$page_title = ($story['title'] ?? 'Success Story') . ' | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Success story</p>
        <h1><?= e($story['title'] ?? 'Story not found') ?></h1>
        <p><?= e($story['farmer_name'] ?? '') ?> · <?= e($story['village'] ?? '') ?></p>
    </div>
</section>
<section class="section">
    <article class="container prose">
        <?php if (!empty($story['image_path'])): ?><img class="detail-image" src="<?= e($story['image_path']) ?>" alt="<?= e($story['title']) ?>"><?php endif; ?>
        <p><?= nl2br(e($story['details'] ?? $story['summary'] ?? '')) ?></p>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
