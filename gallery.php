<?php
declare(strict_types=1);

require_once __DIR__ . '/app/content.php';

$page_title = 'Gallery | ' . APP_NAME;

function gallery_safe_image_url(?string $path): string
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

function gallery_clean_text(?string $value, int $limit = 180): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $limit
            ? rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…'
            : $value;
    }

    return strlen($value) > $limit ? rtrim(substr($value, 0, $limit - 1)) . '…' : $value;
}

function gallery_caption(?string $date, ?string $description = ''): string
{
    $parts = [];
    $timestamp = strtotime((string) $date);
    if ($timestamp !== false && $timestamp > 0) {
        $parts[] = date('d M Y', $timestamp);
    }

    $description = gallery_clean_text($description);
    if ($description !== '') {
        $parts[] = $description;
    }

    return implode(' • ', $parts);
}

function gallery_add_photo(
    array &$photos,
    array &$seenPhotos,
    int &$sequence,
    ?string $path,
    ?string $title,
    ?string $category,
    ?string $caption = '',
    ?string $sortDate = null
): void {
    $imagePath = gallery_safe_image_url($path);
    if ($imagePath === '') {
        return;
    }

    $imageKey = strtolower(rawurldecode($imagePath));
    if (isset($seenPhotos[$imageKey])) {
        return;
    }
    $seenPhotos[$imageKey] = true;

    $title = gallery_clean_text($title) ?: 'KVK Kokrajhar photograph';
    $category = gallery_clean_text($category, 80) ?: 'Gallery';
    $title = str_ireplace('Demostration', 'Demonstration', $title);
    $category = str_ireplace('Demostration', 'Demonstration', $category);
    $categoryKey = function_exists('mb_strtolower')
        ? mb_strtolower($category, 'UTF-8')
        : strtolower($category);

    $sortTime = strtotime((string) $sortDate);
    $photos[] = [
        'image' => $imagePath,
        'title' => $title,
        'category' => $category,
        'categoryKey' => $categoryKey,
        'caption' => gallery_clean_text($caption),
        '_sortTime' => $sortTime !== false ? $sortTime : 0,
        '_sequence' => $sequence++,
    ];
}

$photoRows = records('gallery', 'gallery', 'is_active = 1', 'created_at DESC', 60);
$photos = [];
$seenPhotos = [];
$sequence = 0;
$categoryCounts = [];

foreach ($photoRows as $photo) {
    gallery_add_photo(
        $photos,
        $seenPhotos,
        $sequence,
        $photo['image_path'] ?? '',
        $photo['title'] ?? '',
        $photo['category'] ?? 'Gallery',
        $photo['caption'] ?? '',
        $photo['created_at'] ?? null
    );
}

try {
    $pdo = db();

    $noticeRows = $pdo->query(
        'SELECT title, category, date, summary, image_path, image_path_2, image_path_3, image_path_4, image_path_5, created_at
         FROM notices
         WHERE is_active = 1
         ORDER BY created_at DESC
         LIMIT 80'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($noticeRows as $notice) {
        $caption = gallery_caption($notice['date'] ?? null, $notice['summary'] ?? ($notice['category'] ?? ''));
        foreach (['image_path', 'image_path_2', 'image_path_3', 'image_path_4', 'image_path_5'] as $imageField) {
            gallery_add_photo(
                $photos,
                $seenPhotos,
                $sequence,
                $notice[$imageField] ?? '',
                $notice['title'] ?? '',
                'Notifications',
                $caption,
                $notice['created_at'] ?? ($notice['date'] ?? null)
            );
        }
    }

    $trainingRows = $pdo->query(
        'SELECT training_name, training_date, training_location, photograph_path, photograph_path_2,
                photograph_path_3, photograph_path_4, photograph_path_5, created_at
         FROM training_records
         WHERE is_active = 1
         ORDER BY created_at DESC
         LIMIT 80'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trainingRows as $training) {
        $caption = gallery_caption($training['training_date'] ?? null, $training['training_location'] ?? '');
        foreach (['photograph_path', 'photograph_path_2', 'photograph_path_3', 'photograph_path_4', 'photograph_path_5'] as $imageField) {
            gallery_add_photo(
                $photos,
                $seenPhotos,
                $sequence,
                $training[$imageField] ?? '',
                $training['training_name'] ?? '',
                'Training',
                $caption,
                $training['created_at'] ?? ($training['training_date'] ?? null)
            );
        }
    }

    $eventRows = $pdo->query(
        'SELECT title, event_date, location, summary, image_path, created_at
         FROM events
         WHERE is_active = 1
         ORDER BY created_at DESC
         LIMIT 80'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($eventRows as $event) {
        $description = trim(implode(' — ', array_filter([
            gallery_clean_text($event['location'] ?? '', 80),
            gallery_clean_text($event['summary'] ?? '', 120),
        ])));
        gallery_add_photo(
            $photos,
            $seenPhotos,
            $sequence,
            $event['image_path'] ?? '',
            $event['title'] ?? '',
            'Events',
            gallery_caption($event['event_date'] ?? null, $description),
            $event['created_at'] ?? ($event['event_date'] ?? null)
        );
    }
} catch (Throwable $error) {
    error_log('Gallery automatic photo aggregation failed: ' . $error->getMessage());
}

usort($photos, static function (array $left, array $right): int {
    $dateOrder = ($right['_sortTime'] ?? 0) <=> ($left['_sortTime'] ?? 0);
    return $dateOrder !== 0 ? $dateOrder : (($left['_sequence'] ?? 0) <=> ($right['_sequence'] ?? 0));
});

foreach ($photos as &$photo) {
    unset($photo['_sortTime'], $photo['_sequence']);
    $categoryCounts[$photo['category']] = ($categoryCounts[$photo['category']] ?? 0) + 1;
}
unset($photo);

uksort($categoryCounts, 'strnatcasecmp');
$heroPhoto = $photos[0]['image'] ?? 'assets/img/campus-2.jpg';
$galleryJson = json_encode(
    $photos,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($galleryJson)) {
    $galleryJson = '[]';
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="gallery-hero">
    <img class="gallery-hero__backdrop" src="<?= e($heroPhoto) ?>" alt="" aria-hidden="true" fetchpriority="high">
    <div class="gallery-hero__shade" aria-hidden="true"></div>
    <div class="container gallery-hero__inner">
        <h1>Gallery</h1>
    </div>
</section>

<main class="gallery-page" id="gallery-content">
    <div class="container">
        <header class="gallery-aau-heading">
            <h2>Our Gallery</h2>
            <span aria-hidden="true"></span>
        </header>

        <?php if ($photos): ?>
            <div class="gallery-source-bar" aria-label="Filter gallery by source">
                <div class="gallery-filters">
                    <button class="gallery-filter is-active" type="button" data-filter="all" aria-pressed="true">
                        All photos <span><?= e((string) count($photos)) ?></span>
                    </button>
                    <?php foreach ($categoryCounts as $category => $count): ?>
                        <?php $filterKey = function_exists('mb_strtolower') ? mb_strtolower($category, 'UTF-8') : strtolower($category); ?>
                        <button class="gallery-filter" type="button" data-filter="<?= e($filterKey) ?>" aria-pressed="false">
                            <?= e($category) ?> <span><?= e((string) $count) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p id="galleryResultCount"><strong><?= e((string) count($photos)) ?></strong> photos shown</p>
            </div>

            <section class="gallery-collection" id="galleryCollection" aria-label="KVK Kokrajhar photographs">
                <?php foreach ($photos as $index => $photo): ?>
                    <button
                        class="gallery-card"
                        type="button"
                        data-index="<?= e((string) $index) ?>"
                        data-category="<?= e($photo['categoryKey']) ?>"
                        aria-label="Open <?= e($photo['title']) ?>"
                    >
                        <img
                            src="<?= e($photo['image']) ?>"
                            alt="<?= e($photo['title']) ?>"
                            loading="<?= $index < 3 ? 'eager' : 'lazy' ?>"
                            fetchpriority="<?= $index === 0 ? 'high' : 'auto' ?>"
                            decoding="async"
                        >
                        <span class="gallery-card__scrim" aria-hidden="true"></span>
                        <span class="gallery-card__zoom" aria-hidden="true"><i class="fa-solid fa-expand"></i></span>
                        <span class="gallery-card__content">
                            <span class="gallery-card__category"><?= e($photo['category']) ?></span>
                            <strong><?= e($photo['title']) ?></strong>
                            <?php if ($photo['caption'] !== ''): ?><small><?= e($photo['caption']) ?></small><?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="gallery-empty">
                <span><i class="fa-regular fa-images" aria-hidden="true"></i></span>
                <h2>New photographs are coming soon</h2>
                <p>Moments from KVK Kokrajhar programmes and field activities will appear here as they are published.</p>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php if ($photos): ?>
    <div class="gallery-lightbox" id="galleryLightbox" role="dialog" aria-modal="true" aria-label="Photograph viewer" aria-hidden="true">
        <div class="gallery-lightbox__topbar">
            <span class="gallery-lightbox__count" id="galleryLightboxCount">1 / <?= e((string) count($photos)) ?></span>
            <div class="gallery-lightbox__tools" aria-label="Image zoom controls">
                <button type="button" id="galleryZoomOut" aria-label="Zoom out" title="Zoom out"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></button>
                <button type="button" id="galleryZoomReset" aria-label="Reset zoom" title="Reset zoom"><span id="galleryZoomLevel">100%</span></button>
                <button type="button" id="galleryZoomIn" aria-label="Zoom in" title="Zoom in"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
            </div>
            <button class="gallery-lightbox__close" type="button" id="galleryLightboxClose" aria-label="Close viewer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        <button class="gallery-lightbox__nav gallery-lightbox__nav--previous" type="button" id="galleryLightboxPrevious" aria-label="Previous photograph"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
        <div class="gallery-lightbox__stage" id="galleryLightboxStage">
            <img id="galleryLightboxImage" src="<?= e($photos[0]['image']) ?>" alt="<?= e($photos[0]['title']) ?>" draggable="false">
        </div>
        <button class="gallery-lightbox__nav gallery-lightbox__nav--next" type="button" id="galleryLightboxNext" aria-label="Next photograph"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>

        <div class="gallery-lightbox__caption">
            <span id="galleryLightboxCategory"><?= e($photos[0]['category']) ?></span>
            <h2 id="galleryLightboxTitle"><?= e($photos[0]['title']) ?></h2>
            <p id="galleryLightboxCaption"><?= e($photos[0]['caption']) ?></p>
        </div>
        <p class="gallery-visually-hidden" id="galleryLightboxStatus" aria-live="polite"></p>
    </div>
<?php endif; ?>

<style>
    .gallery-hero { position: relative; isolation: isolate; min-height: 540px; display: flex; align-items: end; overflow: hidden; color: #fff; background: #092d1c; }
    .gallery-hero__backdrop, .gallery-hero__shade { position: absolute; inset: 0; width: 100%; height: 100%; }
    .gallery-hero__backdrop { z-index: -3; object-fit: cover; object-position: center 42%; filter: saturate(.92); transform: scale(1.02); }
    .gallery-hero__shade { z-index: -2; background: linear-gradient(90deg, rgba(3,25,14,.94) 0%, rgba(5,43,25,.82) 42%, rgba(5,30,18,.3) 74%, rgba(3,18,10,.45) 100%), linear-gradient(0deg, rgba(3,20,11,.7), transparent 54%); }
    .gallery-hero::after { content: ""; position: absolute; z-index: -1; width: 420px; height: 420px; right: -160px; bottom: -260px; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; box-shadow: 0 0 0 72px rgba(255,255,255,.035), 0 0 0 144px rgba(255,255,255,.02); }
    .gallery-hero__inner { width: 100%; padding-top: 48px; padding-bottom: 78px; }
    .gallery-breadcrumb { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin-bottom: 62px; color: rgba(255,255,255,.7); font-size: 13px; font-weight: 750; }
    .gallery-breadcrumb a { color: rgba(255,255,255,.88); text-decoration: none; }
    .gallery-breadcrumb a:hover { color: #f3c654; }
    .gallery-breadcrumb i { font-size: 9px; opacity: .65; }
    .gallery-hero__content { display: flex; align-items: end; justify-content: space-between; gap: 48px; }
    .gallery-eyebrow, .gallery-section-kicker { margin: 0 0 12px; color: #f0c557; font-size: 12px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .gallery-eyebrow i { margin-right: 7px; }
    .gallery-hero h1 { max-width: 780px; margin: 0; color: #fff; font-size: clamp(44px,6vw,76px); line-height: 1.02; letter-spacing: -.045em; text-wrap: balance; text-shadow: 0 5px 28px rgba(0,0,0,.3); }
    .gallery-hero__copy { max-width: 720px; margin: 22px 0 0; color: rgba(255,255,255,.82); font-size: clamp(16px,1.7vw,20px); line-height: 1.65; }
    .gallery-hero__stats { display: grid; flex: 0 0 auto; grid-template-columns: repeat(2, minmax(100px,1fr)); overflow: hidden; border: 1px solid rgba(255,255,255,.2); border-radius: 22px; background: rgba(255,255,255,.1); box-shadow: 0 18px 50px rgba(0,0,0,.16); backdrop-filter: blur(16px); }
    .gallery-hero__stats span { min-width: 120px; padding: 21px 22px; }
    .gallery-hero__stats span + span { border-left: 1px solid rgba(255,255,255,.17); }
    .gallery-hero__stats strong { display: block; color: #fff; font-size: 30px; line-height: 1; }
    .gallery-hero__stats small { display: block; margin-top: 7px; color: rgba(255,255,255,.7); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .gallery-page { padding: 0 0 96px; background: linear-gradient(180deg,#f2f7f3 0%,#fff 44%,#f7faf7 100%); }
    .gallery-toolbar { position: relative; z-index: 2; margin: -34px 0 34px; padding: 24px 26px; border: 1px solid #dbe8de; border-radius: 22px; background: rgba(255,255,255,.96); box-shadow: 0 20px 50px rgba(15,67,38,.1); backdrop-filter: blur(15px); }
    .gallery-toolbar__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 22px; }
    .gallery-section-kicker { margin-bottom: 5px; color: #9a7a13; font-size: 10px; }
    .gallery-toolbar h2 { margin: 0; color: #153c28; font-size: clamp(25px,3vw,34px); letter-spacing: -.03em; }
    .gallery-toolbar__heading > p { margin: 0; color: #708078; font-size: 13px; font-weight: 700; }
    .gallery-toolbar__heading > p strong { color: #0d6b40; font-size: 18px; }
    .gallery-filters { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 20px; padding-top: 19px; border-top: 1px solid #e7eee9; }
    .gallery-filter { display: inline-flex; align-items: center; gap: 8px; min-height: 39px; padding: 8px 13px; border: 1px solid #d6e2d9; border-radius: 999px; color: #476054; background: #f8fbf9; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; transition: color .2s, background .2s, border-color .2s, transform .2s; }
    .gallery-filter span { min-width: 23px; padding: 3px 6px; border-radius: 999px; color: #587064; background: #e8f0ea; font-size: 10px; text-align: center; }
    .gallery-filter:hover { border-color: #95bea4; transform: translateY(-1px); }
    .gallery-filter.is-active { color: #fff; border-color: #0c6b40; background: linear-gradient(135deg,#0d603b,#138453); box-shadow: 0 8px 18px rgba(13,96,59,.18); }
    .gallery-filter.is-active span { color: #175d3b; background: #fff; }
    .gallery-collection { display: grid; grid-auto-flow: dense; grid-auto-rows: 235px; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 16px; }
    .gallery-collection.is-filtered { grid-auto-rows: 310px; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); }
    .gallery-collection.is-filtered .gallery-card { grid-column: span 1; grid-row: span 1; }
    .gallery-card { position: relative; min-width: 0; min-height: 0; padding: 0; overflow: hidden; border: 0; border-radius: 20px; color: #fff; background: #143c29; box-shadow: 0 12px 30px rgba(18,60,38,.12); cursor: zoom-in; text-align: left; transition: transform .28s ease, box-shadow .28s ease; }
    .gallery-card.is-featured { grid-column: span 2; grid-row: span 2; }
    .gallery-card.is-wide { grid-column: span 2; }
    .gallery-card[hidden] { display: none !important; }
    .gallery-card img, .gallery-card__scrim { position: absolute; inset: 0; width: 100%; height: 100%; }
    .gallery-card img { object-fit: cover; transition: transform .65s cubic-bezier(.22,1,.36,1), filter .4s; }
    .gallery-card__scrim { background: linear-gradient(180deg,transparent 28%,rgba(3,23,12,.88) 100%); transition: opacity .3s; }
    .gallery-card__zoom { position: absolute; top: 16px; right: 16px; display: grid; width: 42px; height: 42px; place-items: center; border: 1px solid rgba(255,255,255,.36); border-radius: 50%; background: rgba(5,30,17,.5); opacity: 0; backdrop-filter: blur(10px); transform: translateY(-7px); transition: opacity .25s, transform .25s; }
    .gallery-card__content { position: absolute; right: 0; bottom: 0; left: 0; display: block; padding: 46px 20px 19px; }
    .gallery-card__category { display: inline-flex; width: fit-content; margin-bottom: 8px; padding: 5px 8px; border: 1px solid rgba(255,255,255,.22); border-radius: 999px; color: #f4d271; background: rgba(8,42,24,.42); font-size: 9px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; backdrop-filter: blur(8px); }
    .gallery-card strong { display: block; color: #fff; font-size: clamp(16px,1.6vw,22px); line-height: 1.22; text-shadow: 0 2px 15px rgba(0,0,0,.35); }
    .gallery-card small { display: block; max-height: 0; margin-top: 0; overflow: hidden; color: rgba(255,255,255,.73); font-size: 12px; line-height: 1.45; opacity: 0; transition: max-height .3s, margin .3s, opacity .3s; }
    .gallery-card:hover, .gallery-card:focus-visible { box-shadow: 0 20px 42px rgba(15,55,34,.2); transform: translateY(-4px); }
    .gallery-card:hover img, .gallery-card:focus-visible img { filter: saturate(1.08); transform: scale(1.055); }
    .gallery-card:hover .gallery-card__zoom, .gallery-card:focus-visible .gallery-card__zoom { opacity: 1; transform: translateY(0); }
    .gallery-card:hover small, .gallery-card:focus-visible small { max-height: 50px; margin-top: 7px; opacity: 1; }
    .gallery-card:focus-visible { outline: 4px solid #e9bc48; outline-offset: 4px; }
    .gallery-empty { padding: 72px 30px; border: 1px dashed #cbdcd0; border-radius: 22px; color: #64756c; background: #fff; text-align: center; }
    .gallery-empty > span { display: grid; width: 70px; height: 70px; margin: 0 auto 18px; place-items: center; border-radius: 22px; color: #19754a; background: #eaf6ed; font-size: 29px; }
    .gallery-empty h2 { margin: 0 0 8px; color: #173b29; }
    .gallery-empty p { max-width: 550px; margin: 0 auto; }
    .gallery-lightbox { position: fixed; inset: 0; z-index: 9999; display: none; color: #fff; background: rgba(2,13,7,.96); }
    .gallery-lightbox.is-open { display: grid; grid-template-rows: auto minmax(0,1fr) auto; }
    .gallery-lightbox__topbar { position: relative; z-index: 5; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 18px; padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,.1); background: rgba(2,18,10,.7); backdrop-filter: blur(15px); }
    .gallery-lightbox__count { justify-self: start; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 850; letter-spacing: .08em; }
    .gallery-lightbox__tools { display: flex; gap: 8px; }
    .gallery-lightbox button { display: inline-grid; width: 42px; height: 42px; place-items: center; padding: 0; border: 1px solid rgba(255,255,255,.2); border-radius: 50%; color: #fff; background: rgba(255,255,255,.09); font: inherit; cursor: pointer; transition: background .2s, transform .2s, opacity .2s; }
    .gallery-lightbox button:hover:not(:disabled) { background: #117b4b; transform: translateY(-1px); }
    .gallery-lightbox button:disabled { opacity: .35; cursor: not-allowed; }
    .gallery-lightbox__tools button:nth-child(2) { width: 62px; border-radius: 999px; font-size: 11px; font-weight: 850; }
    .gallery-lightbox__close { justify-self: end; font-size: 18px !important; }
    .gallery-lightbox__stage { position: relative; display: grid; min-width: 0; min-height: 0; place-items: center; overflow: auto; padding: 25px 90px; touch-action: pan-x pan-y; }
    .gallery-lightbox__stage img { max-width: min(100%,1600px); max-height: 72vh; object-fit: contain; border-radius: 10px; box-shadow: 0 28px 80px rgba(0,0,0,.5); transform-origin: center; transition: transform .22s ease; user-select: none; }
    .gallery-lightbox__stage img.is-zoomed { cursor: zoom-out; }
    .gallery-lightbox__nav { position: absolute; z-index: 6; top: 50%; width: 48px !important; height: 48px !important; transform: translateY(-50%); }
    .gallery-lightbox__nav:hover { transform: translateY(-50%) !important; }
    .gallery-lightbox__nav--previous { left: 22px; }
    .gallery-lightbox__nav--next { right: 22px; }
    .gallery-lightbox__caption { position: relative; z-index: 4; padding: 18px max(24px,calc((100vw - 1000px)/2)); border-top: 1px solid rgba(255,255,255,.1); background: rgba(2,18,10,.78); text-align: center; backdrop-filter: blur(15px); }
    .gallery-lightbox__caption span { display: inline-flex; margin-bottom: 7px; padding: 5px 9px; border-radius: 999px; color: #f1cc63; background: rgba(255,255,255,.08); font-size: 9px; font-weight: 900; letter-spacing: .09em; text-transform: uppercase; }
    .gallery-lightbox__caption h2 { margin: 0; color: #fff; font-size: clamp(20px,3vw,29px); }
    .gallery-lightbox__caption p { margin: 7px 0 0; color: rgba(255,255,255,.68); font-size: 13px; }
    .gallery-lightbox__caption p:empty { display: none; }
    .gallery-visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important; }
    @media (max-width: 1050px) { .gallery-hero__content { align-items: flex-start; flex-direction: column; } .gallery-breadcrumb { margin-bottom: 44px; } .gallery-collection { grid-template-columns: repeat(3,minmax(0,1fr)); } }
    @media (max-width: 760px) { .gallery-hero { min-height: 500px; } .gallery-hero__inner { padding-top: 34px; padding-bottom: 66px; } .gallery-breadcrumb { margin-bottom: 34px; } .gallery-hero h1 { font-size: clamp(40px,11vw,58px); } .gallery-hero__copy { font-size: 15px; } .gallery-hero__stats { width: 100%; } .gallery-hero__stats span { min-width: 0; padding: 17px; } .gallery-toolbar { margin-top: -28px; padding: 20px; } .gallery-toolbar__heading { align-items: flex-start; flex-direction: column; gap: 8px; } .gallery-filters { flex-wrap: nowrap; margin-right: -20px; margin-left: -20px; padding-right: 20px; padding-left: 20px; overflow-x: auto; scrollbar-width: none; } .gallery-filters::-webkit-scrollbar { display: none; } .gallery-filter { flex: 0 0 auto; } .gallery-collection { grid-auto-rows: 210px; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; } .gallery-card { border-radius: 16px; } .gallery-card.is-featured { grid-row: span 2; } .gallery-card__content { padding: 40px 15px 15px; } .gallery-card strong { font-size: 16px; } .gallery-card small { display: none; } .gallery-lightbox__topbar { grid-template-columns: auto 1fr auto; padding: 12px; } .gallery-lightbox__tools { justify-self: center; } .gallery-lightbox__stage { padding: 18px 14px; } .gallery-lightbox__stage img { max-height: 68vh; } .gallery-lightbox__nav { top: auto; bottom: 112px; } .gallery-lightbox__nav--previous { left: 14px; } .gallery-lightbox__nav--next { right: 14px; } .gallery-lightbox__caption { padding: 15px 70px; } }
    @media (max-width: 480px) { .gallery-breadcrumb span { display: none; } .gallery-hero__stats strong { font-size: 25px; } .gallery-page { padding-bottom: 64px; } .gallery-collection { grid-auto-rows: 190px; } .gallery-card.is-featured, .gallery-card.is-wide { grid-column: span 2; } .gallery-card.is-featured { grid-row: span 2; } .gallery-card__zoom { width: 36px; height: 36px; top: 12px; right: 12px; } .gallery-lightbox__count { display: none; } .gallery-lightbox__topbar { grid-template-columns: 1fr auto; } .gallery-lightbox__tools { justify-self: start; } .gallery-lightbox__caption { padding-right: 58px; padding-left: 58px; } }
    @media (prefers-reduced-motion: reduce) { .gallery-card, .gallery-card img, .gallery-card__zoom, .gallery-card small, .gallery-lightbox__stage img { transition: none; } }

    /* AAU gallery-inspired public presentation. */
    .gallery-hero {
        min-height: 310px;
        align-items: center;
        background: #080d3b;
    }

    .gallery-hero__backdrop {
        object-position: center 45%;
        filter: saturate(.7) contrast(1.05);
    }

    .gallery-hero__shade {
        background:
            linear-gradient(90deg, rgba(4, 9, 49, .9), rgba(8, 18, 72, .78)),
            linear-gradient(0deg, rgba(5, 10, 48, .38), rgba(5, 10, 48, .38));
    }

    .gallery-hero::after {
        display: none;
    }

    .gallery-hero__inner {
        display: grid;
        min-height: 310px;
        place-items: center;
        padding-top: 42px;
        padding-bottom: 42px;
    }

    .gallery-hero h1 {
        margin: 0;
        color: #fff;
        font-family: inherit;
        font-size: clamp(38px, 5vw, 56px);
        font-weight: 800;
        letter-spacing: .025em;
        text-align: center;
        text-transform: uppercase;
    }

    .gallery-page {
        padding: 54px 0 88px;
        background: #f4f7fb;
    }

    .gallery-aau-heading {
        margin: 0 0 28px;
        text-align: center;
    }

    .gallery-aau-heading h2 {
        margin: 0 0 17px;
        color: #087451;
        font-size: clamp(25px, 3vw, 34px);
        font-weight: 900;
        letter-spacing: .01em;
        text-transform: uppercase;
    }

    .gallery-aau-heading span {
        display: block;
        width: 100%;
        height: 4px;
        background: #f59a13;
    }

    .gallery-source-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin: 0 0 28px;
    }

    .gallery-source-bar .gallery-filters {
        flex: 1 1 auto;
        margin: 0;
        padding: 0;
        border: 0;
    }

    .gallery-source-bar > p {
        flex: 0 0 auto;
        margin: 0;
        color: #617267;
        font-size: 13px;
        font-weight: 700;
    }

    .gallery-source-bar > p strong { color: #087451; font-size: 17px; }

    .gallery-collection,
    .gallery-collection.is-filtered {
        display: grid;
        grid-auto-flow: row;
        grid-auto-rows: auto;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 24px 28px;
    }

    .gallery-collection .gallery-card,
    .gallery-collection .gallery-card.is-featured,
    .gallery-collection .gallery-card.is-wide,
    .gallery-collection.is-filtered .gallery-card {
        grid-column: span 1;
        grid-row: span 1;
        aspect-ratio: 1 / 1.08;
        border: 0;
        border-radius: 0;
        background: #dfe7e3;
        box-shadow: none;
        transform: none;
    }

    .gallery-card img {
        filter: none;
    }

    .gallery-card__scrim {
        background: linear-gradient(180deg, transparent 45%, rgba(4, 27, 16, .86) 100%);
        opacity: 0;
    }

    .gallery-card__content {
        padding: 52px 17px 16px;
        opacity: 0;
        transform: translateY(12px);
        transition: opacity .25s ease, transform .25s ease;
    }

    .gallery-card__category {
        margin-bottom: 6px;
        color: #ffe08b;
    }

    .gallery-card strong {
        font-size: 16px;
    }

    .gallery-card small {
        display: none;
    }

    .gallery-card:hover,
    .gallery-card:focus-visible {
        box-shadow: 0 14px 32px rgba(15, 48, 34, .18);
        transform: translateY(-3px);
    }

    .gallery-card:hover img,
    .gallery-card:focus-visible img {
        filter: none;
        transform: scale(1.045);
    }

    .gallery-card:hover .gallery-card__scrim,
    .gallery-card:focus-visible .gallery-card__scrim,
    .gallery-card:hover .gallery-card__content,
    .gallery-card:focus-visible .gallery-card__content {
        opacity: 1;
        transform: translateY(0);
    }

    @media (max-width: 980px) {
        .gallery-collection,
        .gallery-collection.is-filtered {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }
    }

    @media (max-width: 680px) {
        .gallery-hero,
        .gallery-hero__inner {
            min-height: 230px;
        }

        .gallery-page {
            padding-top: 40px;
        }

        .gallery-source-bar { align-items: flex-start; flex-direction: column; }

        .gallery-collection,
        .gallery-collection.is-filtered {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
    }

    @media (max-width: 420px) {
        .gallery-collection,
        .gallery-collection.is-filtered {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if ($photos): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const photos = <?= $galleryJson ?>;
        const cards = Array.from(document.querySelectorAll('.gallery-card'));
        const filters = Array.from(document.querySelectorAll('.gallery-filter'));
        const collection = document.getElementById('galleryCollection');
        const resultCount = document.getElementById('galleryResultCount');
        const lightbox = document.getElementById('galleryLightbox');
        const lightboxImage = document.getElementById('galleryLightboxImage');
        const lightboxCategory = document.getElementById('galleryLightboxCategory');
        const lightboxTitle = document.getElementById('galleryLightboxTitle');
        const lightboxCaption = document.getElementById('galleryLightboxCaption');
        const lightboxCount = document.getElementById('galleryLightboxCount');
        const lightboxStatus = document.getElementById('galleryLightboxStatus');
        const lightboxClose = document.getElementById('galleryLightboxClose');
        const lightboxPrevious = document.getElementById('galleryLightboxPrevious');
        const lightboxNext = document.getElementById('galleryLightboxNext');
        const zoomOut = document.getElementById('galleryZoomOut');
        const zoomReset = document.getElementById('galleryZoomReset');
        const zoomIn = document.getElementById('galleryZoomIn');
        const zoomLevel = document.getElementById('galleryZoomLevel');
        const stage = document.getElementById('galleryLightboxStage');
        let activeIndex = 0;
        let zoom = 1;
        let previouslyFocused = null;
        let pointerStart = null;

        const visibleIndices = () => cards
            .filter((card) => !card.hidden)
            .map((card) => Number(card.dataset.index));

        const setZoom = (nextZoom) => {
            zoom = Math.round(Math.min(4, Math.max(1, nextZoom)) * 4) / 4;
            lightboxImage.style.transform = `scale(${zoom})`;
            lightboxImage.classList.toggle('is-zoomed', zoom > 1);
            zoomLevel.textContent = `${Math.round(zoom * 100)}%`;
            zoomOut.disabled = zoom <= 1;
            zoomReset.disabled = zoom <= 1;
            zoomIn.disabled = zoom >= 4;
            if (zoom === 1) stage.scrollTo({ top: 0, left: 0 });
        };

        const showPhoto = (index) => {
            if (!photos[index]) return;
            activeIndex = index;
            const photo = photos[index];
            const available = visibleIndices();
            const visiblePosition = Math.max(0, available.indexOf(index));
            lightboxImage.src = photo.image;
            lightboxImage.alt = photo.title;
            lightboxCategory.textContent = photo.category;
            lightboxTitle.textContent = photo.title;
            lightboxCaption.textContent = photo.caption || '';
            lightboxCount.textContent = `${visiblePosition + 1} / ${available.length}`;
            lightboxStatus.textContent = `${photo.title}. Photograph ${visiblePosition + 1} of ${available.length}.`;
            setZoom(1);

            [-1, 1].forEach((offset) => {
                const nearbyPosition = (visiblePosition + offset + available.length) % available.length;
                const nearbyPhoto = photos[available[nearbyPosition]];
                if (nearbyPhoto) {
                    const preload = new Image();
                    preload.src = nearbyPhoto.image;
                }
            });
        };

        const movePhoto = (direction) => {
            const available = visibleIndices();
            if (!available.length) return;
            const currentPosition = Math.max(0, available.indexOf(activeIndex));
            showPhoto(available[(currentPosition + direction + available.length) % available.length]);
        };

        const openLightbox = (index, trigger) => {
            previouslyFocused = trigger || document.activeElement;
            showPhoto(index);
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            lightboxClose.focus();
        };

        const closeLightbox = () => {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            setZoom(1);
            previouslyFocused?.focus();
        };

        filters.forEach((filterButton) => {
            filterButton.addEventListener('click', () => {
                const activeFilter = filterButton.dataset.filter || 'all';
                filters.forEach((button) => {
                    const isActive = button === filterButton;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                cards.forEach((card) => {
                    card.hidden = activeFilter !== 'all' && card.dataset.category !== activeFilter;
                    card.classList.remove('is-featured');
                });
                const visibleCards = cards.filter((card) => !card.hidden);
                collection?.classList.toggle('is-filtered', activeFilter !== 'all');
                if (activeFilter === 'all') visibleCards[0]?.classList.add('is-featured');
                if (resultCount) {
                    resultCount.innerHTML = `<strong>${visibleCards.length}</strong> ${visibleCards.length === 1 ? 'photo' : 'photos'} shown`;
                }
            });
        });

        cards.forEach((card) => card.addEventListener('click', () => openLightbox(Number(card.dataset.index), card)));
        lightboxClose.addEventListener('click', closeLightbox);
        lightboxPrevious.addEventListener('click', () => movePhoto(-1));
        lightboxNext.addEventListener('click', () => movePhoto(1));
        zoomOut.addEventListener('click', () => setZoom(zoom - 0.25));
        zoomReset.addEventListener('click', () => setZoom(1));
        zoomIn.addEventListener('click', () => setZoom(zoom + 0.25));
        lightboxImage.addEventListener('dblclick', () => setZoom(zoom > 1 ? 1 : 2));
        lightboxImage.addEventListener('wheel', (event) => {
            event.preventDefault();
            setZoom(zoom + (event.deltaY < 0 ? 0.25 : -0.25));
        }, { passive: false });
        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox || event.target === stage) closeLightbox();
        });

        stage.addEventListener('pointerdown', (event) => {
            if (zoom > 1 || event.button !== 0) return;
            pointerStart = event.clientX;
            stage.setPointerCapture?.(event.pointerId);
        });
        stage.addEventListener('pointerup', (event) => {
            if (pointerStart === null) return;
            const distance = event.clientX - pointerStart;
            pointerStart = null;
            if (Math.abs(distance) > 55) movePhoto(distance < 0 ? 1 : -1);
        });
        stage.addEventListener('pointercancel', () => { pointerStart = null; });

        document.addEventListener('keydown', (event) => {
            if (!lightbox.classList.contains('is-open')) return;
            if (event.key === 'Escape') closeLightbox();
            if (event.key === 'ArrowLeft') movePhoto(-1);
            if (event.key === 'ArrowRight') movePhoto(1);
            if (event.key === '+' || event.key === '=') setZoom(zoom + 0.25);
            if (event.key === '-') setZoom(zoom - 0.25);
            if (event.key === '0') setZoom(1);
        });

        setZoom(1);
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
