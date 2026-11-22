<?php
require_once __DIR__ . '/app/content.php';
$page_title = 'Success Stories | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$stories = records('stories', 'success_stories', 'is_active = 1', 'created_at DESC', 100);
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Success stories</p>
        <h1>Farmer achievements and field impact</h1>
    </div>
</section>
<section class="section">
    <div class="container card-grid">
        <?php foreach ($stories as $story): ?>
            <article class="card card--large">
                <?php if (!empty($story['image_path'])): ?><img class="card-image" src="<?= e($story['image_path']) ?>" alt="<?= e($story['title']) ?>"><?php endif; ?>
                <span class="pill"><?= e($story['village'] ?? '') ?></span>
                <h2><?= e($story['title']) ?></h2>
                <p><?= e($story['summary'] ?? '') ?></p>
                <a href="story.php?id=<?= e((string) ($story['id'] ?? 0)) ?>">Read story</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
