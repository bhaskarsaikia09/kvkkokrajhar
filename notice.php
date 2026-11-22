<?php
require_once __DIR__ . '/app/content.php';
$id = (int) ($_GET['id'] ?? 0);
$notice = null;
$pdo = db();

if ($id > 0 && $pdo) {
    try {
        notices_ensure_table($pdo);
        $stmt = $pdo->prepare(
            "SELECT * FROM notices WHERE id = ? AND is_active = 1 AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $notice = is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        error_log('Unable to load notice detail.');
    }
} elseif ($id > 0) {
    foreach (fallback_data('notices') as $fallbackNotice) {
        if ((int) ($fallbackNotice['id'] ?? 0) === $id) {
            $notice = $fallbackNotice;
            break;
        }
    }
}

if (!$notice) {
    http_response_code(404);
}

$page_title = ($notice['title'] ?? 'Notice') . ' | ' . APP_NAME;
$category = (string) ($notice['category'] ?? 'Notice');
$dateText = (string) ($notice['date'] ?? '');
$programmeName = (string) (($notice['programme_name'] ?? '') ?: ($notice['details'] ?? ''));
$dateTimestamp = $dateText !== '' ? strtotime($dateText) : false;
$displayDate = $dateTimestamp !== false ? date('d M Y', $dateTimestamp) : 'Date not specified';
$attachmentPath = trim((string) ($notice['file_path'] ?? ''));
$programmePhotos = [];
foreach (['image_path', 'image_path_2', 'image_path_3', 'image_path_4', 'image_path_5'] as $photoField) {
    if (!empty($notice[$photoField])) {
        $programmePhotos[] = (string) $notice[$photoField];
    }
}
$programmePhoto = (string) ($programmePhotos[0] ?? '');
$heroPhoto = $programmePhoto !== '' ? $programmePhoto : 'assets/img/campus-1.jpg';
require_once __DIR__ . '/includes/header.php';
?>
<style>
    .notice-popup-page {
        position: relative;
        min-height: calc(100vh - 90px);
        box-sizing: border-box;
        display: grid;
        grid-template-columns: minmax(0, 980px);
        justify-content: center;
        place-items: center;
        padding: 54px 18px;
        overflow: hidden;
        background:
            linear-gradient(135deg, rgba(12, 63, 95, .88), rgba(8, 119, 79, .78) 58%, rgba(145, 42, 44, .7)),
            url('<?= e($heroPhoto) ?>') center/cover;
    }

    .notice-popup-page::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            rgba(8, 24, 33, .34);
        backdrop-filter: blur(10px);
    }

    .notice-popup-page::after {
        content: '';
        position: absolute;
        right: -120px;
        bottom: -160px;
        width: 460px;
        height: 320px;
        border: 50px solid rgba(245, 200, 75, .18);
        border-radius: 50%;
        pointer-events: none;
    }

    .notice-popup {
        position: relative;
        z-index: 1;
        width: 100%;
        min-width: 0;
        max-width: 980px;
        box-sizing: border-box;
        border: 1px solid rgba(255, 255, 255, .34);
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 34px 90px rgba(3, 20, 28, .42);
    }

    .notice-popup__top {
        position: relative;
        min-height: 260px;
        display: grid;
        align-items: end;
        padding: 26px;
        color: #fff;
        background:
            linear-gradient(180deg, transparent, rgba(8, 24, 33, .82)),
            url('<?= e($heroPhoto) ?>') center/cover;
    }

    .notice-popup__close {
        position: absolute;
        top: 16px;
        right: 16px;
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        border: 1px solid rgba(255, 255, 255, .32);
        border-radius: 50%;
        color: #17384e;
        background: #fff;
        font-size: 24px;
        font-weight: 900;
        box-shadow: 0 10px 24px rgba(0, 0, 0, .18);
    }

    .notice-popup__top h1 {
        max-width: 760px;
        margin: 12px 0 0;
        color: #fff;
        font-size: clamp(28px, 4.4vw, 48px);
        line-height: 1.06;
    }

    .notice-popup__body {
        padding: 26px;
    }

    .notice-popup__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 18px;
    }

    .notice-popup__meta span {
        padding: 8px 10px;
        border-radius: 999px;
        color: #17384e;
        background: #eef6fb;
        font-size: 13px;
        font-weight: 900;
    }

    .notice-detail-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 999px;
        color: #fff;
        background: rgba(255, 255, 255, .14);
        font-weight: 900;
    }

    .notice-detail-badge--event {
        color: #fff;
        background: rgba(185, 79, 37, .92);
        border-color: rgba(255, 255, 255, .36);
    }

    .notice-popup h2 {
        margin: 0 0 16px;
        color: #17384e;
        font-size: 28px;
    }

    .notice-summary-box {
        margin: 0 0 22px;
        padding: 20px;
        border-left: 5px solid #0a8754;
        border-radius: 8px;
        background:
            linear-gradient(180deg, #f4fbf7, #ffffff);
        box-shadow: inset 0 0 0 1px rgba(10, 135, 84, .08);
    }

    .notice-summary-box h2 {
        margin-bottom: 8px;
        font-size: 22px;
    }

    .notice-summary-box p {
        margin: 0;
        color: #315244;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.6;
    }

    .notice-image-scroller {
        overflow: hidden;
        margin-bottom: 22px;
        border: 1px solid #d9e4ec;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 18px 42px rgba(37, 72, 91, .14);
    }

    .notice-image-stage {
        position: relative;
        overflow: hidden;
        background: #102d3f;
    }

    .notice-image-stage img {
        display: block;
        width: 100%;
        height: min(520px, 56vw);
        min-height: 320px;
        object-fit: cover;
        transition: opacity .2s ease;
    }

    .notice-image-stage__count {
        position: absolute;
        left: 16px;
        bottom: 16px;
        padding: 8px 10px;
        border-radius: 999px;
        color: #fff;
        background: rgba(8, 24, 33, .72);
        font-size: 13px;
        font-weight: 900;
        backdrop-filter: blur(8px);
    }

    .notice-image-control {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        border: 1px solid rgba(255, 255, 255, .32);
        border-radius: 50%;
        color: #17384e;
        background: rgba(255, 255, 255, .94);
        font-size: 26px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 12px 28px rgba(0, 0, 0, .22);
    }

    .notice-image-control--prev {
        left: 16px;
    }

    .notice-image-control--next {
        right: 16px;
    }

    .notice-image-thumbs {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(116px, 1fr);
        gap: 10px;
        overflow-x: auto;
        padding: 12px;
        background: #f7fbf8;
        scroll-snap-type: x proximity;
    }

    .notice-image-thumb {
        overflow: hidden;
        min-height: 82px;
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 0;
        background: #fff;
        cursor: pointer;
        scroll-snap-align: start;
    }

    .notice-image-thumb.is-active {
        border-color: #0a8754;
        box-shadow: 0 0 0 3px rgba(10, 135, 84, .14);
    }

    .notice-image-thumb img {
        display: block;
        width: 100%;
        height: 82px;
        object-fit: cover;
    }

    .notice-image-caption {
        padding: 12px 14px;
        color: #17384e;
        font-size: 13px;
        font-weight: 900;
        background: #fff;
    }

    .notice-detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px solid #d9e4ec;
    }

    @media (max-width: 900px) {
        .notice-popup-page {
            padding: 18px;
            place-items: start center;
        }

        .notice-popup__top,
        .notice-popup__body {
            padding: 20px;
        }

        .notice-popup__top h1 {
            overflow-wrap: anywhere;
        }

        .notice-image-stage img {
            height: 280px;
            min-height: 280px;
        }
    }
</style>
<section class="notice-popup-page">
    <article class="notice-popup" aria-labelledby="notice-title">
        <div class="notice-popup__top">
            <a class="notice-popup__close" href="index.php" aria-label="Close notice">&times;</a>
            <div>
                <span class="notice-detail-badge <?= strtolower($category) === 'event' ? 'notice-detail-badge--event' : '' ?>"><?= e($category) ?></span>
                <h1 id="notice-title"><?= e($notice['title'] ?? 'Notice not found') ?></h1>
            </div>
        </div>
        <div class="notice-popup__body">
            <?php if ($notice): ?>
                <?php if ($programmePhotos): ?>
                    <section class="notice-image-scroller" data-notice-scroller>
                        <div class="notice-image-stage">
                            <a data-notice-main-link href="<?= e($programmePhotos[0]) ?>" target="_blank" rel="noopener noreferrer">
                                <img data-notice-main-image src="<?= e($programmePhotos[0]) ?>" alt="<?= e((string) ($notice['title'] ?? 'Programme photo')) ?> photo 1">
                            </a>
                            <?php if (count($programmePhotos) > 1): ?>
                                <button class="notice-image-control notice-image-control--prev" type="button" data-notice-prev aria-label="Previous photo">&lsaquo;</button>
                                <button class="notice-image-control notice-image-control--next" type="button" data-notice-next aria-label="Next photo">&rsaquo;</button>
                            <?php endif; ?>
                            <span class="notice-image-stage__count" data-notice-count>1 / <?= e((string) count($programmePhotos)) ?></span>
                        </div>
                        <?php if (count($programmePhotos) > 1): ?>
                            <div class="notice-image-thumbs" aria-label="Programme photo thumbnails">
                                <?php foreach ($programmePhotos as $photoIndex => $photo): ?>
                                    <button class="notice-image-thumb <?= $photoIndex === 0 ? 'is-active' : '' ?>" type="button" data-notice-thumb="<?= e((string) $photoIndex) ?>">
                                        <img src="<?= e($photo) ?>" alt="<?= e((string) ($notice['title'] ?? 'Programme photo')) ?> thumbnail <?= e((string) ($photoIndex + 1)) ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="notice-image-caption">Programme photo gallery</div>
                    </section>
                <?php endif; ?>
                <div class="notice-popup__meta" aria-label="Notice details">
                    <span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> <?= e($displayDate) ?></span>
                    <span><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= e($category) ?></span>
                </div>
                <?php if (!empty($notice['summary'])): ?>
                    <div class="notice-summary-box">
                        <h2>Summary</h2>
                        <p><?= nl2br(e((string) $notice['summary'])) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($programmeName !== ''): ?>
                    <div class="notice-summary-box">
                        <h2>Programme / Project Name</h2>
                        <p><?= nl2br(e($programmeName)) ?></p>
                    </div>
                <?php endif; ?>
                <div class="notice-detail-actions">
                    <?php if ($attachmentPath !== ''): ?>
                        <a class="btn btn--light" href="<?= e($attachmentPath) ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fa-solid fa-paperclip" aria-hidden="true"></i> View attachment
                        </a>
                    <?php endif; ?>
                    <a class="btn btn--primary" href="index.php">Close</a>
                </div>
            <?php else: ?>
                <h2>Notice not available</h2>
                <p>This notice is not available.</p>
                <div class="notice-detail-actions"><a class="btn btn--primary" href="index.php">Close</a></div>
            <?php endif; ?>
        </div>
    </article>
</section>
<?php if ($programmePhotos): ?>
<script>
(() => {
    const photos = <?= json_encode(
        $programmePhotos,
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const noticeTitle = <?= json_encode(
        (string) ($notice['title'] ?? 'Programme'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const scroller = document.querySelector('[data-notice-scroller]');
    const image = scroller?.querySelector('[data-notice-main-image]');
    const link = scroller?.querySelector('[data-notice-main-link]');
    const count = scroller?.querySelector('[data-notice-count]');
    const thumbs = Array.from(scroller?.querySelectorAll('[data-notice-thumb]') || []);
    let index = 0;

    const show = (nextIndex) => {
        if (!photos.length || !image || !link || !count) {
            return;
        }
        index = (nextIndex + photos.length) % photos.length;
        image.style.opacity = '0';
        window.setTimeout(() => {
            image.src = photos[index];
            image.alt = `${noticeTitle} photo ${index + 1}`;
            link.href = photos[index];
            count.textContent = `${index + 1} / ${photos.length}`;
            thumbs.forEach((thumb, thumbIndex) => thumb.classList.toggle('is-active', thumbIndex === index));
            image.style.opacity = '1';
        }, 120);
    };

    scroller?.querySelector('[data-notice-prev]')?.addEventListener('click', () => show(index - 1));
    scroller?.querySelector('[data-notice-next]')?.addEventListener('click', () => show(index + 1));
    thumbs.forEach((thumb) => thumb.addEventListener('click', () => show(Number(thumb.dataset.noticeThumb || 0))));
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
