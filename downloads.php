<?php
require_once __DIR__ . '/app/content.php';
$page_title = 'Downloads | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$publications = records('publications', 'publications', 'is_active = 1', 'year DESC, created_at DESC', 50);
$notices = latest_notices(20);
?>
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">Downloads</p>
        <h1>Reports, notices, tenders, and publications</h1>
    </div>
</section>
<section class="section">
    <div class="container split">
        <div>
            <h2>Publications</h2>
            <div class="list-panel">
                <?php foreach ($publications as $item): ?>
                    <?php
                    $publicationCategory = strtolower(trim((string) ($item['category'] ?? '')));
                    $publicationLibraryRoutes = [
                        'booklet' => 'booklets.php',
                        'booklets' => 'booklets.php',
                        'newsletter' => 'newsletters.php',
                        'newsletters' => 'newsletters.php',
                        'bulletin' => 'bulletins.php',
                        'bulletins' => 'bulletins.php',
                        'leaflet' => 'leaflets.php',
                        'leaflets' => 'leaflets.php',
                    ];
                    ?>
                    <article class="list-item">
                        <span><?= e($item['category']) ?> · <?= e($item['year']) ?></span>
                        <h3><?= e($item['title']) ?></h3>
                        <?php if (strcasecmp((string) ($item['title'] ?? ''), 'Annual Action Plan') === 0): ?>
                            <a href="aap.php">Browse annual action plans</a>
                        <?php elseif (strcasecmp((string) ($item['title'] ?? ''), 'Annual Report') === 0): ?>
                            <a href="annual-reports.php">Browse annual reports</a>
                        <?php elseif (isset($publicationLibraryRoutes[$publicationCategory])): ?>
                            <a href="<?= e($publicationLibraryRoutes[$publicationCategory]) ?>">Browse <?= e($publicationCategory) ?></a>
                        <?php elseif (!empty($item['file_path'])): ?>
                            <a href="<?= e($item['file_path']) ?>">Download</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h2>Notices & tenders</h2>
            <div class="list-panel">
                <?php foreach ($notices as $notice): ?>
                    <article class="list-item">
                        <span><?= e($notice['category']) ?> · <?= e($notice['date']) ?></span>
                        <h3><?= e($notice['title']) ?></h3>
                        <p><?= e($notice['summary']) ?></p>
                        <?php if (!empty($notice['file_path'])): ?><a href="<?= e($notice['file_path']) ?>">Download attachment</a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
