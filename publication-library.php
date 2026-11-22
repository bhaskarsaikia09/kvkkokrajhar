<?php
declare(strict_types=1);

require_once __DIR__ . '/app/content.php';

$publicationLibraries = [
    'booklets' => [
        'title' => 'Booklets',
        'singular' => 'booklet',
        'aliases' => ['booklet', 'booklets'],
        'description' => 'Browse practical extension booklets and farmer reference materials published by KVK Kokrajhar.',
        'icon' => 'fa-book-open',
    ],
    'newsletters' => [
        'title' => 'Newsletters',
        'singular' => 'newsletter',
        'aliases' => ['newsletter', 'newsletters'],
        'description' => 'Read KVK Kokrajhar newsletters featuring recent programmes, field activities, achievements, and updates.',
        'icon' => 'fa-newspaper',
    ],
    'bulletins' => [
        'title' => 'Bulletins',
        'singular' => 'bulletin',
        'aliases' => ['bulletin', 'bulletins'],
        'description' => 'Access technical and information bulletins prepared for farmers, extension workers, and rural communities.',
        'icon' => 'fa-file-lines',
    ],
    'leaflets' => [
        'title' => 'Leaflets',
        'singular' => 'leaflet',
        'aliases' => ['leaflet', 'leaflets'],
        'description' => 'Download concise farmer information leaflets on useful agricultural practices and timely advisories.',
        'icon' => 'fa-sheet-plastic',
    ],
];

$publicationLibraryKey = isset($publicationLibraryKey) && is_string($publicationLibraryKey)
    ? strtolower(trim($publicationLibraryKey))
    : '';

if (!isset($publicationLibraries[$publicationLibraryKey])) {
    http_response_code(404);
    exit('Publication library not found.');
}

$library = $publicationLibraries[$publicationLibraryKey];
$page_title = $library['title'] . ' | ' . APP_NAME;
$pageFile = $publicationLibraryKey . '.php';

function kvk_publication_query_value(string $key, int $maxLength = 160): string
{
    $value = $_GET[$key] ?? '';
    if (!is_scalar($value)) {
        return '';
    }

    $value = trim(strip_tags((string) $value));
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength, 'UTF-8')
        : substr($value, 0, $maxLength);
}

function kvk_publication_file_url(?string $path): string
{
    $path = ltrim(trim(str_replace('\\', '/', (string) $path)), '/');
    if ($path === ''
        || strlen($path) > 500
        || str_contains($path, '..')
        || str_contains($path, '://')
        || preg_match('/[\x00-\x20<>"\'`]/', $path)
    ) {
        return '';
    }

    return $path;
}

function kvk_publication_file_size(string $relativePath): string
{
    if ($relativePath === '') {
        return '';
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        return '';
    }

    $bytes = (int) filesize($absolutePath);
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / 1048576, 1) . ' MB';
}

$search = kvk_publication_query_value('q');
$yearFilter = kvk_publication_query_value('year', 4);
$activeFilters = array_filter([
    'q' => $search,
    'year' => $yearFilter,
], static fn (string $value): bool => $value !== '');

$filterUrl = static function (array $overrides = []) use ($activeFilters, $pageFile): string {
    $params = array_merge($activeFilters, $overrides);
    $params = array_filter($params, static fn ($value): bool => $value !== null && $value !== '');
    return $pageFile . ($params ? '?' . http_build_query($params) : '');
};

$publications = [];
$publicationYears = [];
$summary = [
    'total_publications' => 0,
    'years_covered' => 0,
    'latest_year' => null,
];
$databaseError = '';

$pdo = db();
if ($pdo instanceof PDO) {
    try {
        $aliasPlaceholders = [];
        $aliasParams = [];
        foreach ($library['aliases'] as $index => $alias) {
            $placeholder = ':alias_' . $index;
            $aliasPlaceholders[] = $placeholder;
            $aliasParams[$placeholder] = strtolower((string) $alias);
        }
        $typeWhere = 'LOWER(TRIM(category)) IN (' . implode(', ', $aliasPlaceholders) . ')';

        $summaryStatement = $pdo->prepare(
            "SELECT COUNT(*) AS total_publications,
                    COUNT(DISTINCT NULLIF(TRIM(year), '')) AS years_covered,
                    MAX(CASE WHEN year REGEXP '^[0-9]{4}$' THEN CAST(year AS UNSIGNED) ELSE NULL END) AS latest_year
             FROM publications
             WHERE is_active = 1 AND {$typeWhere}"
        );
        $summaryStatement->execute($aliasParams);
        $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: $summary;

        $yearStatement = $pdo->prepare(
            "SELECT DISTINCT year
             FROM publications
             WHERE is_active = 1 AND {$typeWhere} AND TRIM(year) <> ''
             ORDER BY year DESC"
        );
        $yearStatement->execute($aliasParams);
        $publicationYears = $yearStatement->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $where = ['is_active = 1', $typeWhere];
        $params = $aliasParams;
        if ($search !== '') {
            $where[] = '(title LIKE :search_title OR file_path LIKE :search_file)';
            $params[':search_title'] = '%' . $search . '%';
            $params[':search_file'] = '%' . $search . '%';
        }
        if ($yearFilter !== '' && preg_match('/^[0-9]{4}$/D', $yearFilter)) {
            $where[] = 'year = :publication_year';
            $params[':publication_year'] = $yearFilter;
        }

        $listStatement = $pdo->prepare(
            'SELECT id, title, category, year, file_path, created_at
             FROM publications
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY year DESC, created_at DESC, title ASC'
        );
        $listStatement->execute($params);
        $publications = $listStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        error_log($library['title'] . ' public library failed: ' . $exception->getMessage());
        $databaseError = $library['title'] . ' are temporarily unavailable.';
    }
} else {
    $databaseError = 'Database connection is unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero publication-hero">
    <div class="container publication-hero__inner">
        <nav class="publication-breadcrumb" aria-label="Breadcrumb">
            <a href="./">Home</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <a href="downloads.php">Downloads</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span aria-current="page"><?= e($library['title']) ?></span>
        </nav>
        <div class="publication-hero__content">
            <div>
                <p class="publication-kicker"><i class="fa-solid <?= e($library['icon']) ?>" aria-hidden="true"></i> Publication library</p>
                <h1><?= e($library['title']) ?></h1>
                <p><?= e($library['description']) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section publication-library">
    <div class="container">
        <div class="publication-summary" aria-label="<?= e($library['title']) ?> summary">
            <div class="publication-summary__item">
                <span class="publication-summary__icon"><i class="fa-solid <?= e($library['icon']) ?>" aria-hidden="true"></i></span>
                <span><strong><?= e((string) (int) ($summary['total_publications'] ?? 0)) ?></strong><small>Total <?= e($library['title']) ?></small></span>
            </div>
            <div class="publication-summary__item">
                <span class="publication-summary__icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                <span><strong><?= e((string) (int) ($summary['years_covered'] ?? 0)) ?></strong><small>Years covered</small></span>
            </div>
            <div class="publication-summary__item">
                <span class="publication-summary__icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
                <span><strong><?= e((string) ($summary['latest_year'] ?? '')) ?: 'N/A' ?></strong><small>Latest edition</small></span>
            </div>
        </div>

        <div class="publication-filter-panel">
            <div class="publication-filter-heading">
                <div><p class="publication-section-kicker">Document finder</p><h2>Find a <?= e($library['singular']) ?></h2></div>
                <p><strong><?= e((string) count($publications)) ?></strong> <?= count($publications) === 1 ? 'result' : 'results' ?> shown</p>
            </div>
            <form class="publication-filter" method="get">
                <label class="publication-field">
                    <span>Search <?= e(strtolower($library['title'])) ?></span>
                    <span class="publication-control"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by title or file name"></span>
                </label>
                <label class="publication-field">
                    <span>Publication year</span>
                    <span class="publication-control publication-control--select"><i class="fa-regular fa-calendar" aria-hidden="true"></i><select name="year"><option value="">All years</option><?php foreach ($publicationYears as $year): ?><option value="<?= e((string) $year) ?>"<?= $yearFilter === (string) $year ? ' selected' : '' ?>><?= e((string) $year) ?></option><?php endforeach; ?></select></span>
                </label>
                <button type="submit"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Apply filters</button>
            </form>
            <?php if ($activeFilters): ?>
                <div class="publication-active-filters" aria-label="Active filters">
                    <span>Active filters</span>
                    <?php if ($search !== ''): ?><a href="<?= e($filterUrl(['q' => null])) ?>">Search: <?= e($search) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($yearFilter !== ''): ?><a href="<?= e($filterUrl(['year' => null])) ?>"><?= e($yearFilter) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i></a><?php endif; ?>
                    <a class="publication-clear" href="<?= e($pageFile) ?>">Clear all</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($databaseError !== ''): ?>
            <div class="publication-empty"><span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><h2>Documents are temporarily unavailable</h2><p><?= e($databaseError) ?></p></div>
        <?php elseif ($publications): ?>
            <div class="publication-results-heading"><div><p class="publication-section-kicker">Browse library</p><h2>Available <?= e($library['title']) ?></h2></div><span>Sorted by newest first</span></div>
            <div class="publication-grid">
                <?php foreach ($publications as $publication): ?>
                    <?php
                    $documentUrl = kvk_publication_file_url($publication['file_path'] ?? '');
                    $documentName = trim((string) ($publication['title'] ?? '')) ?: 'Untitled ' . $library['singular'];
                    $fileName = $documentUrl !== '' ? basename($documentUrl) : '';
                    $fileSize = kvk_publication_file_size($documentUrl);
                    ?>
                    <article class="publication-card">
                        <span class="publication-card__document" aria-hidden="true"><i class="fa-regular fa-file-pdf"></i><small>PDF</small></span>
                        <div class="publication-card__content">
                            <div class="publication-card__meta"><span><i class="fa-solid <?= e($library['icon']) ?>" aria-hidden="true"></i> <?= e(ucfirst($library['singular'])) ?></span><?php if (trim((string) ($publication['year'] ?? '')) !== ''): ?><span class="publication-card__year"><?= e((string) $publication['year']) ?></span><?php endif; ?></div>
                            <h3><?= e($documentName) ?></h3>
                            <?php if ($fileName !== ''): ?><div class="publication-card__file"><span title="<?= e($fileName) ?>"><?= e($fileName) ?></span><?php if ($fileSize !== ''): ?><small><?= e($fileSize) ?></small><?php endif; ?></div><?php endif; ?>
                        </div>
                        <?php if ($documentUrl !== ''): ?>
                            <div class="publication-card__actions"><a href="<?= e($documentUrl) ?>" target="_blank" rel="noopener" aria-label="View <?= e($documentName) ?> PDF"><i class="fa-regular fa-eye" aria-hidden="true"></i> View <?= e($library['singular']) ?></a></div>
                        <?php else: ?>
                            <p class="publication-card__unavailable"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Digital file not available</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="publication-empty">
                <span><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
                <?php if ($activeFilters): ?><h2>No <?= e(strtolower($library['title'])) ?> match those filters</h2><p>Try a broader search or clear the selected filters.</p><a href="<?= e($pageFile) ?>">View all <?= e(strtolower($library['title'])) ?></a>
                <?php else: ?><h2><?= e($library['title']) ?> will appear here</h2><p>Published <?= e(strtolower($library['title'])) ?> will be listed here as soon as they are added by KVK Kokrajhar.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
    .publication-hero { position: relative; isolation: isolate; min-height: 390px; padding: 48px 0 76px; overflow: hidden; background-position: center 38%; }
    .publication-hero::before, .publication-hero::after { content: ""; position: absolute; z-index: -1; border-radius: 50%; pointer-events: none; }
    .publication-hero::before { width: 360px; height: 360px; top: -210px; right: 8%; border: 1px solid rgba(255,255,255,.18); box-shadow: 0 0 0 52px rgba(255,255,255,.04), 0 0 0 104px rgba(255,255,255,.025); }
    .publication-hero::after { width: 210px; height: 210px; right: -70px; bottom: -100px; background: rgba(231,177,49,.16); }
    .publication-hero__inner { position: relative; z-index: 1; }
    .publication-breadcrumb { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin-bottom: 34px; color: rgba(255,255,255,.72); font-size: 13px; font-weight: 700; }
    .publication-breadcrumb a { color: rgba(255,255,255,.86); text-decoration: none; }
    .publication-breadcrumb a:hover { color: #f4ca62; }
    .publication-breadcrumb i { font-size: 9px; opacity: .65; }
    .publication-hero__content { display: grid; grid-template-columns: minmax(0,780px) auto; align-items: end; justify-content: space-between; gap: 48px; }
    .publication-kicker, .publication-section-kicker { margin: 0 0 10px; color: #eac052; font-size: 13px; font-weight: 900; letter-spacing: .11em; text-transform: uppercase; }
    .publication-kicker i { margin-right: 7px; }
    .publication-hero h1 { margin: 0 0 16px; color: #fff; font-size: clamp(44px,5.4vw,68px); letter-spacing: -.035em; text-shadow: 0 3px 24px rgba(0,0,0,.24); }
    .publication-hero__content > div > p:last-child { max-width: 720px; margin: 0; color: rgba(255,255,255,.86); font-size: clamp(17px,2vw,20px); line-height: 1.65; }
    .publication-library { position: relative; padding: 0 0 84px; background: #f5f8f5; }
    .publication-summary { position: relative; z-index: 2; display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 16px; margin: -38px 0 30px; }
    .publication-summary__item { display: flex; align-items: center; gap: 16px; min-height: 106px; padding: 20px 22px; border: 1px solid #dfe9e1; border-radius: 18px; background: #fff; box-shadow: 0 16px 38px rgba(16,66,40,.09); }
    .publication-summary__icon { display: grid; flex: 0 0 52px; width: 52px; height: 52px; place-items: center; border-radius: 16px; color: #0d6b40; background: #eaf6ed; font-size: 21px; }
    .publication-summary strong { display: block; color: #123d29; font-size: 30px; line-height: 1; }
    .publication-summary small { display: block; margin-top: 7px; color: #687970; font-size: 13px; font-weight: 800; }
    .publication-filter-panel { margin-bottom: 40px; padding: 26px; border: 1px solid #dce7df; border-radius: 20px; background: #fff; box-shadow: 0 14px 40px rgba(25,64,42,.055); }
    .publication-filter-heading, .publication-results-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
    .publication-filter-heading { margin-bottom: 20px; }
    .publication-filter-heading h2, .publication-results-heading h2 { margin: 0; color: #183d2a; font-size: clamp(24px,3vw,32px); letter-spacing: -.025em; }
    .publication-section-kicker { margin-bottom: 5px; color: #998022; font-size: 11px; }
    .publication-filter-heading > p, .publication-results-heading > span { margin: 0; color: #718078; font-size: 14px; font-weight: 700; }
    .publication-filter-heading > p strong { color: #0b6840; font-size: 18px; }
    .publication-filter { display: grid; grid-template-columns: minmax(280px,1fr) minmax(190px,.42fr) auto; align-items: end; gap: 14px; }
    .publication-field { display: grid; gap: 7px; color: #40594b; font-size: 12px; font-weight: 800; }
    .publication-control { position: relative; display: block; }
    .publication-control > i { position: absolute; z-index: 1; top: 50%; left: 15px; color: #779083; transform: translateY(-50%); pointer-events: none; }
    .publication-control input, .publication-control select { width: 100%; min-height: 50px; padding: 11px 14px 11px 43px; border: 1px solid #cad9ce; border-radius: 12px; color: #244534; background: #fbfdfb; font: inherit; font-size: 14px; }
    .publication-control input:focus, .publication-control select:focus { outline: 3px solid rgba(18,132,84,.14); border-color: #128454; }
    .publication-control--select::after { content: ""; position: absolute; top: 50%; right: 16px; width: 7px; height: 7px; border-right: 2px solid #668074; border-bottom: 2px solid #668074; transform: translateY(-70%) rotate(45deg); pointer-events: none; }
    .publication-control select { appearance: none; padding-right: 38px; }
    .publication-filter button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 50px; padding: 11px 19px; border: 1px solid #0b623b; border-radius: 12px; color: #fff; background: linear-gradient(135deg,#0b623b,#128454); box-shadow: 0 9px 20px rgba(11,98,59,.18); font-weight: 800; cursor: pointer; }
    .publication-filter button:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(11,98,59,.24); }
    .publication-active-filters { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 17px; padding-top: 17px; border-top: 1px solid #e9efeb; }
    .publication-active-filters > span { margin-right: 3px; color: #718078; font-size: 12px; font-weight: 800; text-transform: uppercase; }
    .publication-active-filters a { display: inline-flex; align-items: center; gap: 7px; padding: 7px 10px; border-radius: 999px; color: #276348; background: #eaf6ed; font-size: 12px; font-weight: 800; text-decoration: none; }
    .publication-active-filters .publication-clear { color: #8b3d34; background: #fff0ee; }
    .publication-results-heading { margin: 0 0 20px; }
    .publication-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
    .publication-card { position: relative; display: grid; grid-template-columns: 58px minmax(0,1fr) minmax(164px,190px); gap: 20px; align-items: center; min-width: 0; padding: 22px 24px; overflow: hidden; border: 1px solid #dce7df; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(28,76,48,.055); transition: transform .25s, box-shadow .25s, border-color .25s; }
    .publication-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 4px; background: linear-gradient(90deg,#16794b,#e5b638); opacity: 0; transition: opacity .25s; }
    .publication-card:hover { border-color: #c7dbcd; box-shadow: 0 14px 34px rgba(24,72,43,.1); transform: translateX(3px); }
    .publication-card:hover::before { opacity: 1; }
    .publication-card__document { display: grid; width: 54px; height: 62px; place-content: center; gap: 2px; border: 1px solid #efd4cf; border-radius: 13px; color: #c54433; background: #fff4f2; text-align: center; }
    .publication-card__document i { font-size: 22px; }
    .publication-card__document small { font-size: 9px; font-weight: 900; letter-spacing: .08em; }
    .publication-card__content { min-width: 0; }
    .publication-card__meta { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 8px; }
    .publication-card__meta span { display: inline-flex; align-items: center; gap: 5px; padding: 6px 9px; border-radius: 999px; color: #276348; background: #edf7ef; font-size: 11px; font-weight: 800; }
    .publication-card__meta .publication-card__year { color: #725c0d; background: #fff8dd; }
    .publication-card h3 { margin: 0; color: #173b29; font-size: 20px; line-height: 1.35; }
    .publication-card__file { display: flex; align-items: center; gap: 10px; margin-top: 13px; color: #73827b; font-size: 11px; font-weight: 700; }
    .publication-card__file span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .publication-card__file small { flex: 0 0 auto; padding: 4px 7px; border-radius: 6px; background: #f1f5f2; font-size: 10px; }
    .publication-card__actions, .publication-card__unavailable { align-self: stretch; display: flex; align-items: center; justify-content: center; margin: 0; padding-left: 22px; border-left: 1px solid #e7eee9; }
    .publication-card__actions a, .publication-empty a { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 9px 14px; border: 1px solid #0b623b; border-radius: 11px; color: #fff; background: #0b623b; font-size: 13px; font-weight: 850; text-decoration: none; }
    .publication-card__actions a:hover, .publication-empty a:hover { color: #fff; background: #138252; transform: translateY(-1px); }
    .publication-card__unavailable { color: #7b675c; font-size: 13px; font-weight: 700; text-align: center; }
    .publication-empty { padding: 58px 28px; border: 1px dashed #cbdcd0; border-radius: 20px; color: #65756c; background: #fff; text-align: center; }
    .publication-empty > span { display: grid; width: 64px; height: 64px; margin: 0 auto 18px; place-items: center; border-radius: 20px; color: #19754a; background: #eaf6ed; font-size: 26px; }
    .publication-empty h2 { margin: 0 0 8px; color: #173b29; font-size: 24px; }
    .publication-empty p { max-width: 520px; margin: 0 auto 20px; }
    .publication-empty a { width: fit-content; margin: 0 auto; }
    @media (max-width: 900px) { .publication-card { grid-template-columns: 58px minmax(0,1fr); } .publication-card__actions, .publication-card__unavailable { grid-column: 2; justify-content: flex-start; padding: 16px 0 0; border-top: 1px solid #e7eee9; border-left: 0; } }
    @media (max-width: 760px) { .publication-hero { min-height: 0; padding: 34px 0 66px; } .publication-breadcrumb { margin-bottom: 26px; } .publication-hero h1 { font-size: clamp(38px,12vw,54px); } .publication-hero__content > div > p:last-child { font-size: 16px; } .publication-summary { grid-template-columns: 1fr; gap: 10px; margin-top: -30px; } .publication-summary__item { min-height: 84px; padding: 15px 17px; } .publication-filter-panel { padding: 20px; } .publication-filter-heading, .publication-results-heading { align-items: flex-start; flex-direction: column; gap: 8px; } .publication-filter { grid-template-columns: 1fr; } .publication-card { grid-template-columns: 48px minmax(0,1fr); padding: 19px 18px; gap: 13px; } .publication-card__document { width: 46px; height: 52px; } .publication-card__content { align-self: center; } .publication-card h3 { font-size: 18px; } .publication-card__file { grid-column: 1 / -1; } .publication-card__actions, .publication-card__unavailable { grid-column: 1 / -1; justify-content: stretch; } .publication-card__actions a { width: 100%; } }
    @media (max-width: 430px) { .publication-breadcrumb span { display: none; } .publication-library { padding-bottom: 60px; } .publication-filter-panel { padding: 17px; } }
    @media (prefers-reduced-motion: reduce) { .publication-card, .publication-card::before, .publication-filter button, .publication-card__actions a { transition: none; } }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
