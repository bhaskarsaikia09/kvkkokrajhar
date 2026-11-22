<?php
require_once __DIR__ . '/app/content.php';
$page_title = 'Projects | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$projects = records('projects', 'projects', 'is_active = 1', 'year DESC, created_at DESC', 100);
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Projects</p>
        <h1>Ongoing and completed programmes</h1>
    </div>
</section>
<section class="section">
    <div class="container list-panel">
        <?php foreach ($projects as $project): ?>
            <article class="list-item list-item--linked">
                <span><?= e($project['category'] ?? 'Project') ?> · <?= e($project['year'] ?? '') ?></span>
                <h2><?= e($project['title']) ?></h2>
                <p><?= e($project['summary'] ?? '') ?></p>
                <?php if (!empty($project['file_path'])): ?><a href="<?= e($project['file_path']) ?>">Download file</a><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
