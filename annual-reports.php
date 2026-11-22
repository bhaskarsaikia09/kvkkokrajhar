<?php
declare(strict_types=1);

require_once __DIR__ . '/app/content.php';

$page_title = 'Annual Reports | ' . APP_NAME;

function pop_public_value(string $key, int $maxLength = 120): string
{
    $value = trim((string) ($_GET[$key] ?? ''));

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function pop_public_file_url(?string $path): string
{
    $path = trim(str_replace('\\', '/', (string) $path));
    $path = ltrim($path, '/');

    if ($path === '' || str_contains($path, '..')) {
        return '';
    }

    return $path;
}

function pop_public_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / 1048576, 1) . ' MB';
}

$search = pop_public_value('q', 160);
$categoryFilter = pop_public_value('category', 150);
$yearFilter = pop_public_value('year', 4);

$activeFilters = array_filter([
    'q' => $search,
    'category' => $categoryFilter,
    'year' => $yearFilter,
], static fn (string $value): bool => $value !== '');

$filterUrl = static function (array $overrides = []) use ($activeFilters): string {
    $params = array_merge($activeFilters, $overrides);
    $params = array_filter($params, static fn ($value): bool => $value !== null && $value !== '');

    return 'annual-reports.php' . ($params ? '?' . http_build_query($params) : '');
};

$plans = [];
$planTypes = [];
$publicationYears = [];
$summary = [
    'total_reports' => 0,
    'total_report_types' => 0,
    'latest_year' => null,
];
$databaseError = '';

$pdo = db();
if ($pdo instanceof PDO) {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS annual_reports (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                report_type VARCHAR(150) NOT NULL,
                published_by VARCHAR(180) NOT NULL,
                published_year SMALLINT UNSIGNED NOT NULL,
                document_file VARCHAR(500) NOT NULL,
                original_file_name VARCHAR(255) NOT NULL,
                file_size INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ar_report_type (report_type),
                KEY idx_ar_published_year (published_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(name LIKE :search_name OR report_type LIKE :search_type OR published_by LIKE :search_publisher OR original_file_name LIKE :search_file)';
            $searchPattern = '%' . $search . '%';
            $params[':search_name'] = $searchPattern;
            $params[':search_type'] = $searchPattern;
            $params[':search_publisher'] = $searchPattern;
            $params[':search_file'] = $searchPattern;
        }

        if ($categoryFilter !== '') {
            $where[] = 'report_type = :report_type';
            $params[':report_type'] = $categoryFilter;
        }

        if ($yearFilter !== '' && ctype_digit($yearFilter)) {
            $where[] = 'published_year = :published_year';
            $params[':published_year'] = (int) $yearFilter;
        }

        $listSql = 'SELECT id, name, report_type, published_by, published_year, document_file, original_file_name, file_size, created_at
                    FROM annual_reports';
        if ($where) {
            $listSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $listSql .= ' ORDER BY published_year DESC, name ASC, id DESC';

        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute($params);
        $plans = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $planTypes = $pdo->query("SELECT DISTINCT report_type FROM annual_reports WHERE report_type <> '' ORDER BY report_type ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $publicationYears = $pdo->query('SELECT DISTINCT published_year FROM annual_reports ORDER BY published_year DESC')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $summary = $pdo->query(
            'SELECT COUNT(*) AS total_reports,
                    COUNT(DISTINCT report_type) AS total_report_types,
                    MAX(published_year) AS latest_year
             FROM annual_reports'
        )->fetch(PDO::FETCH_ASSOC) ?: $summary;
    } catch (Throwable $exception) {
        $databaseError = 'Annual Report records are not available at the moment.';
        $plans = [];
        $planTypes = [];
        $publicationYears = [];
    }
} else {
    $databaseError = 'Database connection is unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero pop-hero">
    <div class="container pop-hero__inner">
        <nav class="pop-breadcrumb" aria-label="Breadcrumb">
            <a href="./">Home</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <a href="downloads.php">Downloads</a>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">Annual Reports</span>
        </nav>
        <div class="pop-hero__content">
            <div>
              
                <h1>Annual Reports</h1>
                <p class="pop-hero-copy">Review KVK Kokrajhar’s yearly achievements, activities, outcomes, and institutional highlights.</p>
            </div>
            
        </div>
    </div>
</section>

<section class="section pop-public">
    <div class="container">
        <div class="pop-summary" aria-label="Annual Reports summary">
            <div class="pop-summary__item">
                <span class="pop-summary__icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                <span><strong><?= e((string) (int) ($summary['total_reports'] ?? 0)) ?></strong><small>Annual reports</small></span>
            </div>
            <div class="pop-summary__item">
                <span class="pop-summary__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                <span><strong><?= e((string) (int) ($summary['total_report_types'] ?? 0)) ?></strong><small>Report categories</small></span>
            </div>
            <div class="pop-summary__item">
                <span class="pop-summary__icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
                <span><strong><?= e((string) ($summary['latest_year'] ?? '')) ?: 'N/A' ?></strong><small>Latest edition</small></span>
            </div>
        </div>

        <div class="pop-filter-panel">
            <div class="pop-filter-panel__heading">
                <div>
                    <p class="pop-section-kicker">Document finder</p>
                    <h2>Find an annual report</h2>
                </div>
                <p><strong><?= e((string) count($plans)) ?></strong> <?= count($plans) === 1 ? 'result' : 'results' ?> shown</p>
            </div>

            <form class="pop-filter" method="get">
                <label class="pop-field pop-field--search">
                    <span>Search annual reports</span>
                    <span class="pop-control">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Title, year, or publisher">
                    </span>
                </label>

                <label class="pop-field">
                    <span>Report category</span>
                    <span class="pop-control pop-control--select">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <select name="category">
                            <option value="">All report categories</option>
                            <?php foreach ($planTypes as $planType): ?>
                                <option value="<?= e((string) $planType) ?>"<?= $categoryFilter === (string) $planType ? ' selected' : '' ?>><?= e((string) $planType) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>

                <label class="pop-field">
                    <span>Publication year</span>
                    <span class="pop-control pop-control--select">
                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <select name="year">
                            <option value="">All years</option>
                            <?php foreach ($publicationYears as $publicationYear): ?>
                                <option value="<?= e((string) $publicationYear) ?>"<?= $yearFilter === (string) $publicationYear ? ' selected' : '' ?>><?= e((string) $publicationYear) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>

                <button type="submit"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Apply filters</button>
            </form>

            <?php if ($activeFilters): ?>
                <div class="pop-active-filters" aria-label="Active filters">
                    <span>Active filters</span>
                    <?php if ($search !== ''): ?>
                        <a href="<?= e($filterUrl(['q' => null])) ?>">Search: <?= e($search) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if ($categoryFilter !== ''): ?>
                        <a href="<?= e($filterUrl(['category' => null])) ?>"><?= e($categoryFilter) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php if ($yearFilter !== ''): ?>
                        <a href="<?= e($filterUrl(['year' => null])) ?>"><?= e($yearFilter) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <a class="pop-clear-all" href="annual-reports.php">Clear all</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($databaseError !== ''): ?>
            <div class="pop-empty">
                <span class="pop-empty__icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                <h2>Documents are temporarily unavailable</h2>
                <p><?= e($databaseError) ?></p>
            </div>
        <?php elseif ($plans): ?>
            <div class="pop-results-heading">
                <div>
                    <p class="pop-section-kicker">Browse library</p>
                    <h2>Available Annual Reports</h2>
                </div>
                <span>Sorted by newest first</span>
            </div>
            <div class="pop-grid">
                <?php foreach ($plans as $publication): ?>
                    <?php
                    $documentUrl = pop_public_file_url($publication['document_file'] ?? '');
                    $fileName = trim((string) ($publication['original_file_name'] ?? ''));
                    $publicationName = trim((string) ($publication['name'] ?? 'Untitled annual report'));
                    ?>
                    <article class="pop-card">
                        <div class="pop-card__top">
                            <span class="pop-card__document" aria-hidden="true">
                                <i class="fa-regular fa-file-pdf"></i>
                                <small>PDF</small>
                            </span>
                            <div class="pop-card__meta">
                                <?php if (trim((string) ($publication['report_type'] ?? '')) !== ''): ?>
                                    <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i> <?= e((string) $publication['report_type']) ?></span>
                                <?php endif; ?>
                                <?php if (trim((string) ($publication['published_year'] ?? '')) !== ''): ?>
                                    <span class="pop-card__year"><?= e((string) $publication['published_year']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <h3><?= e($publicationName) ?></h3>
                        <?php if (trim((string) ($publication['published_by'] ?? '')) !== ''): ?>
                            <p class="pop-card__publisher"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><span>Published by <strong><?= e((string) $publication['published_by']) ?></strong></span></p>
                        <?php endif; ?>
                        <?php if ($fileName !== ''): ?>
                            <div class="pop-card__file">
                                <span title="<?= e($fileName) ?>"><?= e($fileName) ?></span>
                                <small><?= e(pop_public_format_bytes((int) ($publication['file_size'] ?? 0))) ?></small>
                            </div>
                        <?php endif; ?>
                        <?php if ($documentUrl !== ''): ?>
                            <div class="pop-card__actions">
                                <a class="pop-card__primary" href="<?= e($documentUrl) ?>" target="_blank" rel="noopener" aria-label="View <?= e($publicationName) ?> PDF"><i class="fa-regular fa-eye" aria-hidden="true"></i> View report</a>
                                
                            </div>
                        <?php else: ?>
                            <p class="pop-card__unavailable"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Digital file not available</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pop-empty">
                <span class="pop-empty__icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></span>
                <?php if ($activeFilters): ?>
                    <h2>No annual reports match those filters</h2>
                    <p>Try a broader search or clear the selected filters to see every available report.</p>
                    <a href="annual-reports.php">View all annual reports</a>
                <?php else: ?>
                    <h2>Annual Reports will appear here</h2>
                    <p>Published reports will be listed here as soon as they are added by KVK Kokrajhar.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
    .pop-hero {
        position: relative;
        isolation: isolate;
        min-height: 390px;
        padding: 48px 0 76px;
        overflow: hidden;
        background-position: center 38%;
    }

    .pop-hero::before,
    .pop-hero::after {
        content: "";
        position: absolute;
        z-index: -1;
        border-radius: 50%;
        pointer-events: none;
    }

    .pop-hero::before {
        width: 360px;
        height: 360px;
        top: -210px;
        right: 8%;
        border: 1px solid rgba(255, 255, 255, .18);
        box-shadow: 0 0 0 52px rgba(255, 255, 255, .04), 0 0 0 104px rgba(255, 255, 255, .025);
    }

    .pop-hero::after {
        width: 210px;
        height: 210px;
        right: -70px;
        bottom: -100px;
        background: rgba(231, 177, 49, .16);
        filter: blur(1px);
    }

    .pop-hero__inner {
        position: relative;
        z-index: 1;
    }

    .pop-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 9px;
        margin-bottom: 34px;
        color: rgba(255, 255, 255, .72);
        font-size: 13px;
        font-weight: 700;
    }

    .pop-breadcrumb a {
        color: rgba(255, 255, 255, .86);
        text-decoration: none;
    }

    .pop-breadcrumb a:hover { color: #f4ca62; }
    .pop-breadcrumb i { font-size: 9px; opacity: .65; }

    .pop-hero__content {
        display: grid;
        grid-template-columns: minmax(0, 780px) auto;
        align-items: end;
        justify-content: space-between;
        gap: 48px;
    }

    .pop-kicker,
    .pop-section-kicker {
        margin: 0 0 10px;
        color: #eac052;
        font-size: 13px;
        font-weight: 900;
        letter-spacing: .11em;
        text-transform: uppercase;
    }

    .pop-kicker i { margin-right: 7px; }

    .pop-hero h1 {
        max-width: none;
        margin: 0 0 16px;
        color: #fff;
        font-size: clamp(44px, 5.4vw, 68px);
        letter-spacing: -.035em;
        text-shadow: 0 3px 24px rgba(0, 0, 0, .24);
    }

    .pop-hero-copy {
        max-width: 720px;
        margin: 0;
        color: rgba(255, 255, 255, .86);
        font-size: clamp(17px, 2vw, 20px);
        line-height: 1.65;
    }

    .pop-hero__mark {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 210px;
        padding: 18px 20px;
        border: 1px solid rgba(255, 255, 255, .2);
        border-radius: 18px;
        color: #fff;
        background: rgba(255, 255, 255, .09);
        box-shadow: 0 18px 50px rgba(0, 0, 0, .12);
        backdrop-filter: blur(10px);
        font-weight: 800;
        line-height: 1.35;
    }

    .pop-hero__mark i { color: #f4ca62; font-size: 30px; }

    .pop-public {
        position: relative;
        padding: 0 0 84px;
        background: #f5f8f5;
    }

    .pop-summary {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin: -38px 0 30px;
    }

    .pop-summary__item {
        display: flex;
        align-items: center;
        gap: 16px;
        min-height: 106px;
        padding: 20px 22px;
        border: 1px solid #dfe9e1;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 16px 38px rgba(16, 66, 40, .09);
    }

    .pop-summary__icon {
        display: grid;
        flex: 0 0 52px;
        width: 52px;
        height: 52px;
        place-items: center;
        border-radius: 16px;
        color: #0d6b40;
        background: #eaf6ed;
        font-size: 21px;
    }

    .pop-summary strong {
        display: block;
        color: #123d29;
        font-size: 30px;
        line-height: 1;
    }

    .pop-summary small {
        display: block;
        margin-top: 7px;
        color: #687970;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .pop-filter-panel {
        margin-bottom: 40px;
        padding: 26px;
        border: 1px solid #dce7df;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 14px 40px rgba(25, 64, 42, .055);
    }

    .pop-filter-panel__heading,
    .pop-results-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
    }

    .pop-filter-panel__heading { margin-bottom: 20px; }

    .pop-filter-panel__heading h2,
    .pop-results-heading h2 {
        margin: 0;
        color: #183d2a;
        font-size: clamp(24px, 3vw, 32px);
        letter-spacing: -.025em;
    }

    .pop-section-kicker { margin-bottom: 5px; color: #998022; font-size: 11px; }

    .pop-filter-panel__heading > p,
    .pop-results-heading > span {
        margin: 0;
        color: #718078;
        font-size: 14px;
        font-weight: 700;
    }

    .pop-filter-panel__heading > p strong {
        color: #0b6840;
        font-size: 18px;
    }

    .pop-filter {
        display: grid;
        grid-template-columns: minmax(260px, 1.25fr) minmax(210px, .8fr) minmax(155px, .55fr) auto;
        gap: 14px;
        align-items: end;
    }

    .pop-field {
        display: grid;
        gap: 7px;
        min-width: 0;
        color: #344e40;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .035em;
    }

    .pop-control {
        position: relative;
        display: block;
    }

    .pop-control > i {
        position: absolute;
        z-index: 1;
        top: 50%;
        left: 15px;
        color: #769084;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .pop-filter input,
    .pop-filter select {
        width: 100%;
        min-height: 50px;
        padding: 11px 38px 11px 43px;
        border: 1px solid #cedbd2;
        border-radius: 12px;
        outline: none;
        color: #1d3a2b;
        font: inherit;
        font-size: 14px;
        font-weight: 650;
        background: #fff;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .pop-filter input::placeholder { color: #8a9991; }

    .pop-filter input:focus,
    .pop-filter select:focus {
        border-color: #208253;
        background: #fbfefc;
        box-shadow: 0 0 0 4px rgba(32, 130, 83, .1);
    }

    .pop-control--select::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 16px;
        width: 7px;
        height: 7px;
        border-right: 2px solid #668074;
        border-bottom: 2px solid #668074;
        transform: translateY(-70%) rotate(45deg);
        pointer-events: none;
    }

    .pop-filter select {
        appearance: none;
        padding-right: 38px;
    }

    .pop-filter button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 50px;
        padding: 11px 19px;
        border: 1px solid #0b623b;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #0b623b, #128454);
        box-shadow: 0 9px 20px rgba(11, 98, 59, .18);
        font-weight: 800;
        cursor: pointer;
        transition: transform .2s, box-shadow .2s;
    }

    .pop-filter button:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(11, 98, 59, .24);
    }

    .pop-active-filters {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 17px;
        padding-top: 17px;
        border-top: 1px solid #e9efeb;
    }

    .pop-active-filters > span {
        margin-right: 3px;
        color: #718078;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .pop-active-filters a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 10px;
        border-radius: 999px;
        color: #276348;
        background: #eaf6ed;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .pop-active-filters .pop-clear-all {
        color: #8b3d34;
        background: #fff0ee;
    }

    .pop-results-heading { margin: 0 0 20px; }

    .pop-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .pop-card {
        position: relative;
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) minmax(164px, 190px);
        grid-template-rows: repeat(4, auto);
        column-gap: 20px;
        min-width: 0;
        min-height: 0;
        padding: 22px 24px;
        overflow: hidden;
        border: 1px solid #dce7df;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(28, 76, 48, .055);
        transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }

    .pop-card::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 4px;
        background: linear-gradient(90deg, #16794b, #e5b638);
        opacity: 0;
        transition: opacity .25s;
    }

    .pop-card:hover {
        border-color: #c7dbcd;
        box-shadow: 0 14px 34px rgba(24, 72, 43, .1);
        transform: translateX(3px);
    }

    .pop-card:hover::before { opacity: 1; }

    .pop-card__top {
        display: contents;
    }

    .pop-card__document {
        display: grid;
        grid-column: 1;
        grid-row: 1 / 5;
        align-self: start;
        width: 54px;
        height: 62px;
        place-content: center;
        gap: 2px;
        border: 1px solid #efd4cf;
        border-radius: 13px;
        color: #c54433;
        background: #fff4f2;
        text-align: center;
    }

    .pop-card__document i { font-size: 22px; }
    .pop-card__document small { font-size: 9px; font-weight: 900; letter-spacing: .08em; }

    .pop-card__meta {
        display: flex;
        grid-column: 2;
        grid-row: 1;
        justify-content: flex-start;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 8px;
    }

    .pop-card__meta span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: min(100%, 480px);
        padding: 6px 9px;
        border-radius: 999px;
        color: #276348;
        background: #edf7ef;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.25;
        text-align: left;
    }

    .pop-card__meta .pop-card__year {
        color: #725c0d;
        background: #fff8dd;
    }

    .pop-card h3 {
        grid-column: 2;
        grid-row: 2;
        margin: 0 0 8px;
        color: #173b29;
        font-size: 20px;
        line-height: 1.35;
        letter-spacing: -.015em;
    }

    .pop-card__publisher {
        display: flex;
        grid-column: 2;
        grid-row: 3;
        align-items: flex-start;
        gap: 9px;
        margin: 0;
        color: #667970;
        font-size: 13px;
        line-height: 1.5;
    }

    .pop-card__publisher i { margin-top: 4px; color: #8ba097; }
    .pop-card__publisher strong { color: #40584b; font-weight: 750; }

    .pop-card__file {
        display: flex;
        grid-column: 2;
        grid-row: 4;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        margin-top: 13px;
        padding-top: 0;
        border-top: 0;
        color: #73827b;
        font-size: 11px;
        font-weight: 700;
    }

    .pop-card__file span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pop-card__file small {
        flex: 0 0 auto;
        padding: 4px 7px;
        border-radius: 6px;
        background: #f1f5f2;
        font-size: 10px;
    }

    .pop-card__actions {
        display: flex;
        grid-column: 3;
        grid-row: 1 / 5;
        align-self: stretch;
        flex-direction: column;
        justify-content: center;
        gap: 9px;
        margin: 0;
        padding-left: 22px;
        border-left: 1px solid #e7eee9;
    }

    .pop-card__actions a,
    .pop-empty a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 44px;
        padding: 9px 14px;
        border: 1px solid #0b623b;
        border-radius: 11px;
        font-size: 13px;
        font-weight: 850;
        text-decoration: none;
        transition: color .2s, background .2s, transform .2s;
    }

    .pop-card__primary,
    .pop-empty a { color: #fff; background: #0b623b; }

    .pop-card__secondary { width: auto; color: #0b623b; background: #fff; }
    .pop-card__secondary span { position: static; width: auto; height: auto; overflow: visible; clip: auto; }
    .pop-card__actions a:hover,
    .pop-empty a:hover { color: #fff; background: #138252; transform: translateY(-1px); }

    .pop-card__unavailable {
        grid-column: 3;
        grid-row: 1 / 5;
        align-self: center;
        margin: 0;
        padding-left: 22px;
        border-left: 1px solid #e7eee9;
        color: #7b675c;
        font-size: 13px;
        font-weight: 700;
    }

    .pop-empty {
        padding: 58px 28px;
        border: 1px dashed #cbdcd0;
        border-radius: 20px;
        color: #65756c;
        background: #fff;
        text-align: center;
    }

    .pop-empty__icon {
        display: grid;
        width: 64px;
        height: 64px;
        margin: 0 auto 18px;
        place-items: center;
        border-radius: 20px;
        color: #19754a;
        background: #eaf6ed;
        font-size: 26px;
    }

    .pop-empty h2 { margin: 0 0 8px; color: #173b29; font-size: 24px; }
    .pop-empty p { margin: 0 auto 20px; max-width: 520px; }
    .pop-empty a { width: fit-content; margin: 0 auto; }

    @media (max-width: 1080px) {
        .pop-filter { grid-template-columns: minmax(260px, 1fr) minmax(210px, .7fr) minmax(160px, .45fr); }
        .pop-filter button { grid-column: 1 / -1; }
        .pop-card { grid-template-columns: 58px minmax(0, 1fr); }
        .pop-card__actions,
        .pop-card__unavailable {
            grid-column: 2;
            grid-row: 5;
            align-self: auto;
            flex-direction: row;
            margin-top: 16px;
            padding: 16px 0 0;
            border-top: 1px solid #e7eee9;
            border-left: 0;
        }
        .pop-card__actions a { flex: 1 1 0; }
    }

    @media (max-width: 760px) {
        .pop-hero { min-height: 0; padding: 34px 0 66px; }
        .pop-breadcrumb { margin-bottom: 26px; }
        .pop-hero__content { display: block; }
        .pop-hero__mark { display: none; }
        .pop-hero h1 { font-size: clamp(38px, 12vw, 54px); }
        .pop-hero-copy { font-size: 16px; }
        .pop-summary { grid-template-columns: 1fr; gap: 10px; margin-top: -30px; }
        .pop-summary__item { min-height: 84px; padding: 15px 17px; border-radius: 15px; }
        .pop-summary__icon { flex-basis: 46px; width: 46px; height: 46px; border-radius: 13px; }
        .pop-summary strong { font-size: 25px; }
        .pop-filter-panel { padding: 20px; border-radius: 17px; }
        .pop-filter-panel__heading,
        .pop-results-heading { align-items: flex-start; flex-direction: column; gap: 8px; }
        .pop-filter { grid-template-columns: 1fr; }
        .pop-filter button { grid-column: auto; }
        .pop-card {
            grid-template-columns: 48px minmax(0, 1fr);
            column-gap: 13px;
            padding: 19px 18px;
        }
        .pop-card__document {
            grid-column: 1;
            grid-row: 1;
            width: 46px;
            height: 52px;
            border-radius: 11px;
        }
        .pop-card__meta { grid-column: 2; grid-row: 1; align-self: center; margin-bottom: 0; }
        .pop-card h3 { grid-column: 1 / -1; grid-row: 2; margin-top: 16px; }
        .pop-card__publisher { grid-column: 1 / -1; grid-row: 3; }
        .pop-card__file { grid-column: 1 / -1; grid-row: 4; }
        .pop-card__actions,
        .pop-card__unavailable { grid-column: 1 / -1; grid-row: 5; }
        .pop-results-heading > span { font-size: 12px; }
    }

    @media (max-width: 430px) {
        .pop-breadcrumb span { display: none; }
        .pop-public { padding-bottom: 60px; }
        .pop-filter-panel { padding: 17px; }
        .pop-card__meta span { max-width: 230px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .pop-card,
        .pop-card::before,
        .pop-filter button,
        .pop-card__actions a { transition: none; }
    }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
