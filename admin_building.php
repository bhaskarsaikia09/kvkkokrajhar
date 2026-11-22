<?php
require_once __DIR__ . '/app/content.php';
require_once __DIR__ . '/app/administrative-building.php';

$page_title = 'Administrative Building - KVK Kokrajhar';

$galleryImages = [];

foreach (administrative_building_photos() as $photo) {
    $title = trim((string) ($photo['title'] ?? 'Administrative Building')) ?: 'Administrative Building';
    $galleryImages[] = [
        'img' => (string) ($photo['image'] ?? ''),
        'title' => $title,
        'desc' => (string) ($photo['description'] ?? ''),
        'alt' => $title . ' at KVK Kokrajhar',
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --primary-green: #1f6f43;
        --secondary-green: #2f8550;
        --accent-gold: #e7a52d;
        --dark-navy: #102033;
        --cream: #fbfdf8;
        --white: #ffffff;
        --text-main: #1f2f27;
        --text-light: #63746c;
        --border-color: #dce8d8;
        --radius: 8px;
        --transition: all 0.24s ease;
    }

    .page-header {
        position: relative;
        overflow: hidden;
        background: linear-gradient(100deg, rgba(6, 28, 17, 0.92) 0%, rgba(20, 73, 44, 0.76) 100%), url('assets/img/AB/AB%20(2).jpg') center 46%/cover no-repeat;
        color: var(--white);
        padding: 84px 0 92px;
        text-align: center;
    }

    .page-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 18px;
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.86rem;
        font-weight: 700;
    }

    .page-breadcrumb a { color: #fff; text-decoration: none; }
    .page-breadcrumb a:hover { text-decoration: underline; }

    .page-header h1 {
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2rem, 4vw, 3rem);
        margin: 0 0 10px;
    }

    .page-header p {
        color: rgba(244, 250, 243, 0.9);
        font-size: 1.1rem;
        max-width: 650px;
        margin: 0 auto;
    }

    .section {
        padding: 72px 0 84px;
        background: var(--cream);
    }

    .gallery-intro {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 30px;
    }

    .gallery-intro h2 { margin: 0 0 8px; color: var(--dark-navy); font-family: Merriweather, Georgia, serif; font-size: clamp(1.6rem, 3vw, 2.25rem); }
    .gallery-intro p { margin: 0; color: var(--text-light); }
    .photo-count { flex: 0 0 auto; padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 999px; background: #fff; color: var(--primary-green); font-weight: 800; }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
    }

    .gallery-card {
        background: var(--white);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(23, 69, 45, 0.06);
        transition: var(--transition);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        padding: 0;
        color: inherit;
        text-align: left;
        font: inherit;
    }

    button.gallery-card { width: 100%; }

    .gallery-card:focus-visible,
    .lightbox-btn:focus-visible {
        outline: 3px solid var(--accent-gold);
        outline-offset: 3px;
    }

    .gallery-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 18px 38px rgba(23, 69, 45, 0.12);
        border-color: var(--primary-green);
    }

    .gallery-card-img {
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }

    .gallery-card-media { position: relative; overflow: hidden; }
    .gallery-card-media::after { content: '\f00e'; position: absolute; right: 14px; bottom: 14px; width: 42px; height: 42px; display: grid; place-items: center; border-radius: 50%; background: rgba(12, 55, 32, 0.88); color: #fff; font-family: 'Font Awesome 6 Free'; font-weight: 900; box-shadow: 0 8px 20px rgba(0,0,0,.22); }

    .gallery-card:hover .gallery-card-img {
        transform: scale(1.03);
    }

    .gallery-card-body {
        padding: 16px;
        flex-grow: 1;
    }

    .gallery-card-title {
        font-size: 1.05rem;
        color: var(--dark-navy);
        margin: 0 0 6px;
        font-weight: 700;
    }

    .gallery-card-desc {
        font-size: 0.88rem;
        color: var(--text-light);
        margin: 0;
    }

    .empty-gallery {
        text-align: center;
        padding: 60px 20px;
        background: var(--white);
        border: 1px dashed var(--border-color);
        border-radius: var(--radius);
        color: var(--text-light);
    }

    .empty-gallery i {
        font-size: 3rem;
        color: var(--primary-green);
        margin-bottom: 15px;
    }

    /* Lightbox Modal */
    .lightbox {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 28px;
        background: rgba(6, 22, 14, 0.88);
        backdrop-filter: blur(8px);
    }

    .lightbox.open {
        display: flex;
    }

    .lightbox-stage { display: flex; align-items: center; justify-content: center; max-width: 100%; max-height: 100%; }

    .lightbox-image {
        max-width: 90vw;
        max-height: 80vh;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.5);
        transition: transform 0.25s ease;
    }

    .lightbox-controls {
        position: fixed;
        top: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 10000;
    }

    .lightbox-zoom-controls {
        position: fixed;
        top: 20px;
        left: 20px;
        display: flex;
        gap: 10px;
        z-index: 10000;
    }

    .lightbox-btn {
        width: 42px;
        height: 42px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: var(--radius);
        background: rgba(15, 36, 25, 0.6);
        color: var(--white);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .lightbox-btn:hover {
        background: var(--primary-green);
        transform: translateY(-2px);
    }

    .lightbox-caption {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        color: var(--white);
        text-align: center;
        background: rgba(15, 36, 25, 0.8);
        padding: 12px 24px;
        border-radius: var(--radius);
        max-width: 90vw;
    }

    .lightbox-caption h3 {
        margin: 0 0 4px;
        font-size: 1.1rem;
    }

    .lightbox-caption p {
        margin: 0;
        font-size: 0.88rem;
        color: #dbe7dc;
    }

    @media (max-width: 680px) {
        .page-header { padding: 64px 0 72px; }
        .section { padding: 54px 0 64px; }
        .gallery-intro { align-items: flex-start; flex-direction: column; }
        .gallery-grid { grid-template-columns: 1fr; gap: 18px; }
        .lightbox { padding: 72px 14px 100px; }
        .lightbox-image { max-width: 96vw; max-height: 68vh; }
        .lightbox-caption { bottom: 14px; width: calc(100% - 28px); padding: 10px 14px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .gallery-card, .gallery-card-img, .lightbox-image, .lightbox-btn { transition: none; }
    }
</style>

<div class="administrative-building-page">
    <section class="page-header">
        <div class="container">
            <nav class="page-breadcrumb" aria-label="Breadcrumb">
                <a href="./">Home</a><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><span aria-current="page">Facilities</span>
            </nav>
            <h1>Administrative Building</h1>
            <p>Explore the central facility that supports farmer services, extension programmes, technical coordination, and day-to-day operations at KVK Kokrajhar.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if (!empty($galleryImages)): ?>
                <div class="gallery-intro">
                    <div>
                        <h2>Building photo gallery</h2>
                        <p>Select any photograph to view it at a larger size.</p>
                    </div>
                    <span class="photo-count"><?= count($galleryImages) ?> photos</span>
                </div>
                <div class="gallery-grid">
                    <?php foreach ($galleryImages as $index => $item): ?>
                        <button class="gallery-card" type="button" data-index="<?= (int) $index ?>" aria-label="Open <?= e($item['title']) ?> photo">
                            <span class="gallery-card-media">
                                <img src="<?= e($item['img']) ?>" alt="<?= e($item['alt']) ?>" class="gallery-card-img" loading="lazy" decoding="async">
                            </span>
                            <div class="gallery-card-body">
                                <h2 class="gallery-card-title"><?= e($item['title']) ?></h2>
                                <p class="gallery-card-desc"><?= e($item['desc']) ?></p>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-gallery">
                    <i class="fa-solid fa-images"></i>
                    <h3>No Images Found</h3>
                    <p>No photos were found in the <code>assets/img/AB</code> directory.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Interactive Lightbox -->
    <div class="lightbox" id="galleryLightbox" role="dialog" aria-modal="true" aria-labelledby="lbTitle" aria-hidden="true">
        <div class="lightbox-zoom-controls">
            <button type="button" class="lightbox-btn" id="lbZoomOut" aria-label="Zoom out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <button type="button" class="lightbox-btn" id="lbZoomReset" aria-label="Reset zoom"><i class="fa-solid fa-compress"></i></button>
            <button type="button" class="lightbox-btn" id="lbZoomIn" aria-label="Zoom in"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
        <div class="lightbox-controls">
            <button type="button" class="lightbox-btn" id="lbPrev" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
            <button type="button" class="lightbox-btn" id="lbNext" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
            <button type="button" class="lightbox-btn" id="lbClose" aria-label="Close lightbox"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="lightbox-stage"><img id="lbImage" class="lightbox-image" src="" alt=""></div>
        <div class="lightbox-caption">
            <h3 id="lbTitle"></h3>
            <p id="lbDesc"></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const galleryImages = <?= json_encode($galleryImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        if (!galleryImages.length) return;

        let currentIndex = 0;
        let currentZoom = 1;
        let previousFocus = null;

        const lightbox = document.getElementById('galleryLightbox');
        const lbImage = document.getElementById('lbImage');
        const lbTitle = document.getElementById('lbTitle');
        const lbDesc = document.getElementById('lbDesc');

        const setZoom = (zoom) => {
            currentZoom = Math.min(Math.max(zoom, 1), 4);
            lbImage.style.transform = `scale(${currentZoom})`;
        };

        const showImage = (index) => {
            currentIndex = (index + galleryImages.length) % galleryImages.length;
            const item = galleryImages[currentIndex];
            
            lbImage.src = item.img;
            lbImage.alt = item.alt;
            lbTitle.textContent = item.title;
            lbDesc.textContent = item.desc;
            setZoom(1);
        };

        const openLightbox = (index) => {
            previousFocus = document.activeElement;
            showImage(index);
            lightbox.classList.add('open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            document.getElementById('lbClose')?.focus();
        };

        const closeLightbox = () => {
            lightbox.classList.remove('open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            previousFocus?.focus();
        };

        document.querySelectorAll('.gallery-card').forEach((card) => {
            card.addEventListener('click', () => {
                const index = parseInt(card.dataset.index, 10);
                openLightbox(index);
            });
        });

        document.getElementById('lbPrev')?.addEventListener('click', () => showImage(currentIndex - 1));
        document.getElementById('lbNext')?.addEventListener('click', () => showImage(currentIndex + 1));
        document.getElementById('lbZoomIn')?.addEventListener('click', () => setZoom(currentZoom + 0.25));
        document.getElementById('lbZoomOut')?.addEventListener('click', () => setZoom(currentZoom - 0.25));
        document.getElementById('lbZoomReset')?.addEventListener('click', () => setZoom(1));
        document.getElementById('lbClose')?.addEventListener('click', closeLightbox);

        lightbox?.addEventListener('click', (e) => {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
            if (e.key === 'ArrowRight') showImage(currentIndex + 1);
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
