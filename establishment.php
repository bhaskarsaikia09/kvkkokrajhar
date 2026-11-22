<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Establishment | ' . APP_NAME;

/**
 * Returns the first available project image from a list of candidates.
 * The first path is retained as the preferred deployment path when this
 * standalone file is checked outside the complete website project.
 */
function establishment_photo(array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (is_file(__DIR__ . '/' . $candidate)) {
            return $candidate;
        }
    }

    return $candidates[0] ?? '';
}

$photos = [
    'hero' => establishment_photo([
        'assets/img/campus-1-web.jpg',
        'assets/img/campus-1.jpg',
        'assets/img/slider-1.jpg',
    ]),
    'nars' => establishment_photo([
        'assets/img/campus-5.jpeg',
        'assets/img/campus-1-web.jpg',
        'assets/img/campus-1.jpg',
    ]),
    'kvk' => establishment_photo([
        'assets/img/kvk-kokrajhar-team.png.png',
        'assets/img/Group(1).png',
        'assets/img/slider-2.jpeg',
    ]),
    'aau' => establishment_photo([
        'assets/img/AAU.jpg',
        'assets/img/campus-1-web.jpg',
    ]),
    'atari' => establishment_photo([
        'assets/img/ATARIVI.jpg',
        'assets/img/about-vision-mission.png',
        'assets/img/about-location-map.jpg',
    ]),
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .est-page {
        --est-green: #1e7148;
        --est-green-dark: #134d33;
        --est-green-deep: #0b3524;
        --est-gold: #dca52f;
        --est-ink: #202a25;
        --est-muted: #68736d;
        --est-border: #dde6e0;
        --est-soft: #f5f8f5;
        --est-white: #ffffff;
        color: var(--est-ink);
        background: var(--est-white);
        overflow: hidden;
    }

    .est-page *,
    .est-page *::before,
    .est-page *::after {
        box-sizing: border-box;
    }

    .est-page .est-container {
        width: min(1140px, calc(100% - 32px));
        margin-inline: auto;
    }

    /* Page banner intentionally follows the compact inner-page style of the
       reference website rather than using a large homepage-style hero. */
    .est-page .est-page-banner {
        position: relative;
        min-height: 280px;
        display: flex;
        align-items: center;
        isolation: isolate;
        color: var(--est-white);
        background:
            linear-gradient(90deg, rgba(8, 46, 30, .92), rgba(20, 86, 54, .72), rgba(10, 52, 35, .48)),
            url('<?= e($photos['hero']) ?>') center 45% / cover no-repeat;
    }

    .est-page .est-page-banner::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(180deg, rgba(0, 0, 0, .08), rgba(0, 0, 0, .30));
    }

    .est-page .est-page-banner__content {
        padding: 68px 0 58px;
        text-align: left;
    }

    .est-page .est-page-banner h1 {
        margin: 0 0 13px;
        color: var(--est-white);
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(2.25rem, 5vw, 4rem);
        line-height: 1.12;
        text-shadow: 0 8px 25px rgba(0, 0, 0, .28);
    }

    .est-page .est-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 9px;
        margin: 0;
        padding: 0;
        list-style: none;
        color: rgba(255, 255, 255, .90);
        font-size: .94rem;
        font-weight: 650;
    }

    .est-page .est-breadcrumb a {
        color: #ffe3a0;
        text-decoration: none;
    }

    .est-page .est-breadcrumb a:hover {
        color: var(--est-white);
        text-decoration: underline;
    }

    .est-page .est-breadcrumb__separator {
        color: rgba(255, 255, 255, .62);
    }

    .est-page .est-main {
        padding: 76px 0 88px;
    }

    .est-page .est-article {
        max-width: 1060px;
        margin-inline: auto;
    }

    .est-page .est-section {
        padding: 0 0 62px;
    }

    .est-page .est-section + .est-section {
        padding-top: 62px;
        border-top: 1px solid var(--est-border);
    }

    .est-page .est-heading {
        position: relative;
        margin: 0 0 25px;
        padding-left: 22px;
        color: var(--est-green-dark);
        font-family: Merriweather, Georgia, serif;
        font-size: clamp(1.65rem, 3vw, 2.35rem);
        line-height: 1.25;
        text-align: left;
    }

    .est-page .est-heading::before {
        content: '';
        position: absolute;
        top: .11em;
        bottom: .09em;
        left: 0;
        width: 5px;
        border-radius: 999px;
        background: linear-gradient(180deg, var(--est-green), var(--est-gold));
    }

    .est-page .est-copy {
        text-align: left;
    }

    .est-page .est-copy p {
        margin: 0 0 18px;
        color: var(--est-muted);
        font-size: 1rem;
        line-height: 1.88;
    }

    .est-page .est-copy p:last-child {
        margin-bottom: 0;
    }

    .est-page .est-lead {
        color: #33423a !important;
        font-size: 1.075rem !important;
    }

    .est-page .est-photo {
        position: relative;
        margin: 34px 0 0;
        overflow: hidden;
        border: 1px solid var(--est-border);
        background: var(--est-soft);
        box-shadow: 0 15px 38px rgba(25, 73, 49, .10);
    }

    .est-page .est-photo--wide {
        border-radius: 8px;
    }

    .est-page .est-photo img {
        width: 100%;
        display: block;
        object-fit: cover;
    }

    .est-page .est-photo--wide img {
        height: clamp(310px, 47vw, 520px);
    }

    .est-page .est-photo figcaption {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        padding: 38px 24px 19px;
        color: var(--est-white);
        background: linear-gradient(180deg, transparent, rgba(7, 42, 27, .88));
        font-size: .92rem;
        font-weight: 700;
        text-align: left;
    }

    .est-page .est-split {
        display: grid;
        grid-template-columns: minmax(300px, .88fr) minmax(0, 1.12fr);
        gap: 48px;
        align-items: center;
    }

    .est-page .est-split--reverse {
        grid-template-columns: minmax(0, 1.12fr) minmax(300px, .88fr);
    }

    .est-page .est-split--reverse .est-split__media {
        order: 2;
    }

    .est-page .est-split__media {
        position: relative;
    }

    .est-page .est-split__media::before {
        content: '';
        position: absolute;
        top: 20px;
        right: -18px;
        bottom: -18px;
        left: 20px;
        z-index: 0;
        border: 2px solid rgba(220, 165, 47, .58);
        border-radius: 8px;
    }

    .est-page .est-split--reverse .est-split__media::before {
        right: 20px;
        left: -18px;
    }

    .est-page .est-split__media img {
        position: relative;
        z-index: 1;
        width: 100%;
        min-height: 350px;
        display: block;
        border-radius: 8px;
        object-fit: cover;
        box-shadow: 0 16px 38px rgba(25, 73, 49, .12);
    }

    .est-page .est-section-label {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 13px;
        color: var(--est-green);
        font-size: .76rem;
        font-weight: 900;
        letter-spacing: .11em;
        text-transform: uppercase;
    }

    .est-page .est-section-label::before {
        content: '';
        width: 18px;
        height: 2px;
        background: var(--est-gold);
    }

    .est-page .est-info-strip {
        margin-top: 31px;
        padding: 23px 25px;
        border-left: 4px solid var(--est-gold);
        background: var(--est-soft);
    }

    .est-page .est-info-strip strong {
        display: block;
        margin-bottom: 7px;
        color: var(--est-green-dark);
        font-size: 1rem;
    }

    .est-page .est-info-strip p {
        margin: 0;
        font-size: .94rem;
        line-height: 1.7;
    }

    .est-page .est-network {
        margin-top: 65px;
        padding: 38px;
        border: 1px solid var(--est-border);
        border-radius: 9px;
        background: linear-gradient(135deg, #f5f9f5, #ffffff);
    }

    .est-page .est-network h3 {
        margin: 0 0 26px;
        color: var(--est-green-dark);
        font-family: Merriweather, Georgia, serif;
        font-size: 1.45rem;
        text-align: left;
    }

    .est-page .est-network__flow {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 36px;
    }

    .est-page .est-network__item {
        position: relative;
        padding: 22px;
        border: 1px solid var(--est-border);
        background: var(--est-white);
        text-align: left;
    }

    .est-page .est-network__item:not(:last-child)::after {
        content: '→';
        position: absolute;
        top: 50%;
        right: -27px;
        color: var(--est-gold);
        font-size: 1.65rem;
        font-weight: 800;
        transform: translateY(-50%);
    }

    .est-page .est-network__item span {
        display: block;
        margin-bottom: 7px;
        color: var(--est-green);
        font-size: .75rem;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .est-page .est-network__item strong {
        display: block;
        margin-bottom: 8px;
        color: var(--est-green-dark);
        line-height: 1.35;
    }

    .est-page .est-network__item p {
        margin: 0;
        color: var(--est-muted);
        font-size: .88rem;
        line-height: 1.65;
    }

    .est-page .est-photo-placeholder {
        min-height: 330px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 30px;
        border-radius: 8px;
        color: var(--est-green-dark);
        background: linear-gradient(135deg, #e9f3ec, #f8fbf8);
        font-weight: 800;
        text-align: center;
    }

    .est-page .is-missing .est-photo-placeholder {
        display: flex;
    }

    .est-page .is-missing > img {
        display: none;
    }

    @media (max-width: 900px) {
        .est-page .est-split,
        .est-page .est-split--reverse {
            grid-template-columns: 1fr;
            gap: 34px;
        }

        .est-page .est-split--reverse .est-split__media {
            order: 0;
        }

        .est-page .est-split__media {
            max-width: 680px;
        }

        .est-page .est-network__flow {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .est-page .est-network__item:not(:last-child)::after {
            content: '↓';
            top: auto;
            right: auto;
            bottom: -21px;
            left: 50%;
            transform: translateX(-50%);
        }
    }

    @media (max-width: 600px) {
        .est-page .est-container {
            width: min(100% - 22px, 1140px);
        }

        .est-page .est-page-banner {
            min-height: 225px;
        }

        .est-page .est-page-banner__content {
            padding: 54px 0 45px;
        }

        .est-page .est-main {
            padding: 52px 0 65px;
        }

        .est-page .est-section {
            padding-bottom: 45px;
        }

        .est-page .est-section + .est-section {
            padding-top: 45px;
        }

        .est-page .est-heading {
            padding-left: 17px;
            font-size: 1.65rem;
        }

        .est-page .est-copy p {
            font-size: .96rem;
            line-height: 1.78;
        }

        .est-page .est-photo--wide img {
            height: 290px;
        }

        .est-page .est-split__media::before {
            display: none;
        }

        .est-page .est-split__media img {
            min-height: 275px;
        }

        .est-page .est-network {
            margin-top: 44px;
            padding: 24px 18px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .est-page * {
            scroll-behavior: auto !important;
        }
    }
</style>

<main class="est-page">
    <section class="est-page-banner" aria-labelledby="establishment-page-title">
        <div class="est-container est-page-banner__content">
            <h1 id="establishment-page-title">Establishment</h1>
            <nav aria-label="Breadcrumb">
                <ol class="est-breadcrumb">
                    <li><a href="index.php">Home</a></li>
                    <li class="est-breadcrumb__separator" aria-hidden="true">/</li>
                    <li aria-current="page">Establishment</li>
                </ol>
            </nav>
        </div>
    </section>

    <div class="est-main">
        <div class="est-container est-article">
            <section class="est-section" id="nars">
                <h2 class="est-heading">KVK as an Integral Part of NARS</h2>
                <div class="est-copy">
                    <p class="est-lead">Krishi Vigyan Kendra (KVK) is a planned project of the Indian Council of Agricultural Research (ICAR), New Delhi, and an integral part of the National Agricultural Research System (NARS) of the country.</p>
                    <p>It aims at the assessment of location-specific technology modules in agriculture and allied enterprises through technology assessment, refinement, and frontline demonstrations.</p>
                    <p>KVKs function as Knowledge and Resource Centres of agricultural technology, supporting initiatives of public, private, and voluntary sectors to improve the agricultural economy of the district. They work at the grass-root level, with a district serving as their operational area.</p>
                    <p>Across the country, KVKs operate under diversified organisations such as ICAR institutes, State Agricultural Universities, non-government organisations, public-sector institutions, and Central Universities. They form one of the largest agricultural extension networks, undertaking adaptive trials at the community level.</p>
                </div>

                <figure class="est-photo est-photo--wide est-image-wrap">
					<img src="<?= e($photos['nars']) ?>" alt="Administrative building and campus of Krishi Vigyan Kendra Kokrajhar" width="1600" height="900" loading="lazy" decoding="async">
                   
                    <div class="est-photo-placeholder">KVK Kokrajhar campus photograph</div>
                    <figcaption>Krishi Vigyan Kendra Kokrajhar — connecting agricultural research with district-level extension.</figcaption>
                </figure>
            </section>

            <section class="est-section" id="kvk-kokrajhar">
                <div class="est-split">
                    <div class="est-split__media est-image-wrap">
                        <img src="<?= e($photos['kvk']) ?>" alt="Officials and staff associated with KVK Kokrajhar" width="649" height="866" loading="lazy" decoding="async">
                        <div class="est-photo-placeholder">KVK Kokrajhar team photograph</div>
                    </div>
                    <div class="est-split__content">
                        <p class="est-section-label">District extension centre</p>
                        <h2 class="est-heading">Establishment of KVK-Kokrajhar</h2>
                        <div class="est-copy">
                            <p>KVK Kokrajhar operates under the administrative control of Assam Agricultural University (AAU), Jorhat. The KVK is situated at Gossaigaon in Kokrajhar district of Assam, within the Bodoland Territorial Region.</p>
                            <p>Established to address the specific agro-climatic needs of the Lower Brahmaputra Valley Zone, the Kendra serves as a primary frontline extension hub for the rural and farming communities of the district.</p>
                            <p>It conducts on-farm trials, capacity-building programmes, vocational training for rural youth, and technology demonstrations suited to local cropping systems, livestock, and fishery management.</p>
                        </div>
                        <div class="est-info-strip">
                            <strong>Operational focus</strong>
                            <p>Location-specific assessment, demonstration, skill development, advisory support, and dissemination of agricultural technologies.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="est-section" id="host-organisation">
                <div class="est-split est-split--reverse">
                    <div class="est-split__media est-image-wrap">
                        <img src="<?= e($photos['aau']) ?>" alt="Agricultural campus representing Assam Agricultural University" width="1600" height="900" loading="lazy" decoding="async">
                        <div class="est-photo-placeholder">Assam Agricultural University photograph</div>
                    </div>
                    <div class="est-split__content">
                        <p class="est-section-label">Host organisation</p>
                        <h2 class="est-heading">Assam Agricultural University (AAU)</h2>
                        <div class="est-copy">
                            <p>Assam Agricultural University, established in 1969, is headquartered at Jorhat, Assam. It is the pioneer agricultural university of the North Eastern Region of India, functioning within the framework of ICAR and the Government of Assam.</p>
                            <p>AAU is dedicated to developing skilled human resources, conducting location-specific research, and transferring green technologies in agriculture and allied sectors to support food and nutritional security across the region.</p>
                            <p>As the host institution, AAU provides the administrative, scientific, and institutional framework under which KVK Kokrajhar delivers frontline extension services.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="est-section" id="atari-zone-vi">
                <div class="est-split">
                    <div class="est-split__media est-image-wrap">
						<img src="<?= e($photos['atari']) ?>" alt="Institutional representation of ICAR ATARI Zone VI" width="1200" height="800" loading="lazy" decoding="async">
                        <div class="est-photo-placeholder">ICAR–ATARI Zone VI photograph</div>
                    </div>
                    <div class="est-split__content">
                        <p class="est-section-label">Coordination and monitoring</p>
                        <h2 class="est-heading">ICAR–ATARI Zone VI</h2>
                        <div class="est-copy">
                            <p>ICAR–Agricultural Technology Application Research Institute (ATARI) Zone VI, located in Guwahati, Assam, coordinates and monitors agricultural extension activities across Assam, Arunachal Pradesh, and Sikkim.</p>
                            <p>The institute oversees the functioning of KVKs in this northeastern region, supporting effective technology dissemination, capacity building, and frontline extension services under the aegis of the Indian Council of Agricultural Research.</p>
                        </div>
                    </div>
                </div>

                <div class="est-network" aria-label="Institutional relationship">
                    <h3>Institutional relationship</h3>
                    <div class="est-network__flow">
                        <article class="est-network__item">
                            <span>National system</span>
                            <strong>Indian Council of Agricultural Research</strong>
                            <p>Provides the national framework for agricultural research, education, and frontline extension.</p>
                        </article>
                        <article class="est-network__item">
                            <span>Host institution</span>
                            <strong>Assam Agricultural University</strong>
                            <p>Provides administrative and scientific support to KVK Kokrajhar.</p>
                        </article>
                        <article class="est-network__item">
                            <span>District centre</span>
                            <strong>KVK Kokrajhar</strong>
                            <p>Delivers location-specific technology assessment, training, demonstrations, and advisory services.</p>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.est-page .est-image-wrap img').forEach(function (image) {
            const markMissing = function () {
                const wrapper = image.closest('.est-image-wrap');
                if (wrapper) wrapper.classList.add('is-missing');
            };

            image.addEventListener('error', markMissing, { once: true });
            if (image.complete && image.naturalWidth === 0) markMissing();
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
