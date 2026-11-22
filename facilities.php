<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Facilities | ' . APP_NAME;

$facilityStats = [
    ['label' => 'Demonstration farm', 'value' => '11 ha'],
    ['label' => 'Seasonal trial crops', 'value' => '7 ha'],
    ['label' => 'Orchard and agro-forestry', 'value' => '2.5 ha'],
    ['label' => 'Office and lab infrastructure', 'value' => '1.5 ha'],
];

$facilities = [
    [
        'icon' => 'fa-tractor',
        'title' => 'Demonstration and instructional farm',
        'text' => 'An 11-hectare farm supports On-Farm Testing, Front Line Demonstrations, seasonal trial crops, orchard blocks, agro-forestry, office facilities, and laboratory infrastructure.',
    ],
    [
        'icon' => 'fa-flask-vial',
        'title' => 'Soil and water testing laboratory',
        'text' => 'The laboratory assesses soil health, tests water parameters, and supports Soil Health Card generation with customized fertilizer and nutrient recommendations for farmers.',
    ],
    [
        'icon' => 'fa-bug-slash',
        'title' => 'Plant protection and diagnostic services',
        'text' => 'Farmers receive diagnostic support for crop pests, diseases, and nutrient deficiencies, along with eco-friendly integrated pest management advisories.',
    ],
    [
        'icon' => 'fa-chalkboard-user',
        'title' => 'Training infrastructure',
        'text' => 'Dedicated training halls host capacity-building programmes, vocational workshops, and skill development sessions for farmers, rural youth, and extension personnel.',
    ],
    [
        'icon' => 'fa-seedling',
        'title' => 'Seed and planting material production',
        'text' => 'Nursery and crop units produce and supply high-yielding seeds, saplings, and planting materials suited to the local agro-climatic zone.',
    ],
    [
        'icon' => 'fa-box-open',
        'title' => 'Agri-enterprise units',
        'text' => 'Hands-on vocational training is supported in off-farm and allied enterprises such as vermicomposting, mushroom cultivation, and beekeeping under programmes like ARYA.',
    ],
    [
        'icon' => 'fa-mobile-screen-button',
        'title' => 'ICT and mobile advisory unit',
        'text' => 'Registered local farmers receive real-time agricultural news, weather advisories, market information, and pest warnings through SMS and digital channels.',
    ],
];

$servicePath = [
    ['step' => '01', 'title' => 'Discuss the need', 'text' => 'Farmers and groups can contact KVK with crop, soil, water, pest, enterprise, or training requirements.'],
    ['step' => '02', 'title' => 'Use the right facility', 'text' => 'The team connects the need with farm demonstrations, diagnostic support, lab testing, training halls, or enterprise units.'],
    ['step' => '03', 'title' => 'Apply in the field', 'text' => 'Recommendations, inputs, and skill support are taken back to farms for practical use and follow-up advisory.'],
];

require_once __DIR__ . '/includes/header.php';
?>
<section class="facilities-hero">
    <div class="container facilities-hero__inner">
        <div>
            <p class="eyebrow">Facilities</p>
            <h1>Facilities that turn farm problems into practical field solutions.</h1>
            <p>KVK Kokrajhar at Telipara, Gossaigaon brings together demonstration farms, diagnostic services, training spaces, planting material units, and digital advisory support for farmers of the district.</p>
            <div class="facilities-hero__actions">
                <a class="btn btn--primary" href="#facility-list">View facilities</a>
                <a class="btn btn--light" href="contact.php">Contact KVK</a>
            </div>
        </div>
        <figure>
            <img src="assets/img/campus-1.jpg" alt="KVK Kokrajhar campus facility">
            <figcaption>Training, testing, demonstration, and advisory support from one district resource centre.</figcaption>
        </figure>
    </div>
</section>

<section class="section section--tinted">
    <div class="container facilities-stats">
        <?php foreach ($facilityStats as $stat): ?>
            <article>
                <strong><?= e($stat['value']) ?></strong>
                <span><?= e($stat['label']) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" id="facility-list">
    <div class="container">
        <div class="section-title">
            <p class="eyebrow">Key facilities</p>
            <h2>Facilities available at KVK Kokrajhar</h2>
            <p>Physical infrastructure, farm units, advisory systems, and enterprise facilities support demonstrations, testing, trainings, diagnostics, and farmer services.</p>
        </div>
        <div class="facilities-grid">
            <?php foreach ($facilities as $index => $facility): ?>
                <article>
                    <span><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                    <i class="fa-solid <?= e($facility['icon']) ?>"></i>
                    <h3><?= e($facility['title']) ?></h3>
                    <p><?= e($facility['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--tinted">
    <div class="container facilities-showcase">
        <figure>
            <img src="assets/img/campus-2.jpg" alt="KVK Kokrajhar farm and campus infrastructure">
        </figure>
        <article>
            <p class="eyebrow">Integrated support</p>
            <h2>One campus, many farmer services</h2>
            <p>The facilities work together as a practical support system: farmers can test soil and water, diagnose field problems, observe improved technologies, learn new skills, access quality planting material, and receive timely advisories.</p>
            <ul>
                <li>Field demonstration and hands-on learning for location-specific technologies.</li>
                <li>Scientific diagnostic and advisory support for better crop decisions.</li>
                <li>Enterprise-oriented training for youth, farm women, and farmer groups.</li>
            </ul>
        </article>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title">
            <p class="eyebrow">How it helps</p>
            <h2>From enquiry to field application</h2>
        </div>
        <div class="facilities-path">
            <?php foreach ($servicePath as $item): ?>
                <article>
                    <span><?= e($item['step']) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--compact">
    <div class="container facilities-cta">
        <div>
            <p class="eyebrow">Farmer support</p>
            <h2>Use KVK facilities for training, testing, demonstration, and advisory services.</h2>
            <p>Farmers, farm women, rural youth, and extension workers can connect with KVK Kokrajhar for need-based support and capacity-building programmes.</p>
        </div>
        <a class="btn btn--primary" href="contact.php">Get in touch</a>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
