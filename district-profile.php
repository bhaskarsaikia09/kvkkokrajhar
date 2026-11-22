<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'District Profile | ' . APP_NAME;

$districtFacts = [
    ['icon' => 'fa-cloud-sun-rain', 'label' => 'Agro-climatic zone', 'value' => 'Lower Brahmaputra Valley'],
    ['icon' => 'fa-people-group', 'label' => 'Population served', 'value' => 'Approx. 887,000'],
    ['icon' => 'fa-map-location-dot', 'label' => 'Civil subdivisions', 'value' => '3'],
    ['icon' => 'fa-location-crosshairs', 'label' => 'Revenue circles', 'value' => '5'],
    ['icon' => 'fa-ruler-combined', 'label' => 'Geographical area', 'value' => '3,169+ sq. km'],
    ['icon' => 'fa-temperature-half', 'label' => 'Climate', 'value' => 'Sub-tropical'],
];

$cropCards = [
    ['icon' => 'fa-wheat-awn', 'title' => 'Paddy-based farming', 'text' => 'Rice is the principal crop, creating scope for surplus grain marketing and local value chains.'],
    ['icon' => 'fa-seedling', 'title' => 'Crop diversification', 'text' => 'Maize, pulses, oilseeds, potatoes, cabbage, cauliflower, and other winter vegetables support diversified production.'],
    ['icon' => 'fa-leaf', 'title' => 'Horticulture growth', 'text' => 'Improved horticultural crop adoption is helping farmers enhance seasonal vegetable and fruit-crop yields.'],
    ['icon' => 'fa-fish', 'title' => 'Allied rural economy', 'text' => 'Forest resources, inland fisheries, animal husbandry, and agriculture remain central to livelihood systems.'],
];

require_once __DIR__ . '/includes/header.php';
?>
<section class="district-profile-hero">
    <div class="container district-profile-hero__inner">
        <div>
            <p class="eyebrow">District Profile</p>
            <h1>Kokrajhar district agricultural profile</h1>
            <p>Operational setting of KVK Kokrajhar in Assam's Lower Brahmaputra Valley Agro-Climatic Zone.</p>
            <div class="district-profile-hero__badges">
                <span><i class="fa-solid fa-seedling"></i> Paddy-based agriculture</span>
                <span><i class="fa-solid fa-water"></i> Inland fisheries</span>
                <span><i class="fa-solid fa-tree"></i> Eco-tourism potential</span>
            </div>
        </div>
        <figure>
            <img src="assets/img/kokrajhar-kvk-map.png" alt="Kokrajhar district map and KVK location">
            <figcaption>KVK Kokrajhar operational context</figcaption>
        </figure>
    </div>
</section>

<section class="section">
    <div class="container district-profile-facts">
        <?php foreach ($districtFacts as $fact): ?>
            <article>
                <i class="fa-solid <?= e($fact['icon']) ?>"></i>
                <span><?= e($fact['label']) ?></span>
                <strong><?= e($fact['value']) ?></strong>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section section--tinted">
    <div class="container district-profile-layout">
        <article class="district-profile-panel">
            <p class="eyebrow">Overview</p>
            <h2>KVK Kokrajhar in the district context</h2>
            <p>The Krishi Vigyan Kendra is situated in Kokrajhar district within the Lower Brahmaputra Valley Agro-Climatic Zone of Assam. The district has a sub-tropical climate with hot, humid summers, cool winters, and heavy monsoon rainfall.</p>
            <p>The KVK serves a population of approximately 887,000 across 3 civil subdivisions and 5 revenue circles, working through multiple development blocks. Its operational area covers more than 3,169 square kilometres across the district.</p>
            <p>Soils generally range from alluvial to sandy-loam types, supporting paddy-based agriculture along with diversified crop and horticultural systems.</p>
        </article>
        <aside class="district-profile-image">
            <img src="assets/img/campus-2.jpg" alt="KVK Kokrajhar campus">
            <div>
                <span>KVK Kokrajhar</span>
                <strong>Serving farmers, farm women, rural youth, and extension personnel.</strong>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title">
            <p class="eyebrow">Agriculture and allied sectors</p>
            <h2>Major production systems and opportunities</h2>
            <p>The district economy is closely linked with agriculture, animal husbandry, fisheries, forest resources, and emerging value-chain opportunities.</p>
        </div>
        <div class="district-profile-cards">
            <?php foreach ($cropCards as $card): ?>
                <article>
                    <i class="fa-solid <?= e($card['icon']) ?>"></i>
                    <h3><?= e($card['title']) ?></h3>
                    <p><?= e($card['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--compact">
    <div class="container district-profile-opportunity">
        <div>
            <p class="eyebrow">Growth potential</p>
            <h2>Agro-processing, eco-tourism, and ethno-tourism</h2>
            <p>The district's paddy-based farming system offers opportunities for surplus grain marketing and local agro-processing. Rich natural resources, inland fisheries, and attractions such as Manas National Park and Raimona National Park also create potential for eco-tourism and ethno-tourism linked with rural development.</p>
        </div>
        <a class="btn btn--primary" href="contact.php">Contact KVK</a>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
