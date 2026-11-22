<?php
declare(strict_types=1);

require_once __DIR__ . '/app/content.php';

function pm_detail_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pm_detail_decimal(mixed $value): string
{
    $formatted = number_format(max(0, (float) $value), 3, '.', ',');
    return rtrim(rtrim($formatted, '0'), '.') ?: '0';
}

function pm_detail_money(mixed $value): string
{
    return number_format(max(0, (float) $value), 2);
}

function pm_detail_clean_photo(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    while (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }
    return ltrim($path, '/');
}

function pm_detail_add_photo(array &$photos, mixed $value): void
{
    $photo = pm_detail_clean_photo((string) $value);
    if ($photo !== '' && !in_array($photo, $photos, true)) {
        $photos[] = $photo;
    }
}

function pm_detail_ensure_commodity_profile_columns(PDO $pdo): void
{
    $columns = [
        'tagline' => 'TEXT NULL',
        'native_identity' => 'TEXT NULL',
        'gi_status' => 'VARCHAR(255) NULL',
        'farmer_linkage' => 'TEXT NULL',
        'brand' => 'VARCHAR(255) NULL',
        'catchment_reach' => 'TEXT NULL',
        'product_highlights' => 'TEXT NULL',
    ];

    foreach ($columns as $column => $definition) {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['planting_material_commodities', $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE planting_material_commodities ADD COLUMN `' . $column . '` ' . $definition);
            }
        } catch (Throwable $e) {
            // Keep the public page available if the database user cannot alter tables.
        }
    }
}

$pdo = function_exists('db') ? db() : null;
$id = max(0, (int) ($_GET['id'] ?? 0));
$material = null;
$photos = [];

if ($pdo instanceof PDO) {
    pm_detail_ensure_commodity_profile_columns($pdo);
}

if ($pdo instanceof PDO && $id > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.commodity_name, c.tagline, c.native_identity, c.gi_status,
                    c.farmer_linkage, c.brand, c.catchment_reach, c.product_highlights,
                    c.rate, c.unit, c.product_photo, c.product_photos, c.product_note,
                    c.register_date,
                    COALESCE(SUM(r.quantity_produced), 0) AS quantity_produced,
                    COALESCE(SUM(r.quantity_sold), 0) AS quantity_sold,
                    COALESCE(SUM(r.quantity_distributed), 0) AS quantity_distributed,
                    COALESCE(SUM(r.revenue_generated), 0) AS revenue_generated,
                    GROUP_CONCAT(DISTINCT NULLIF(r.source, '') ORDER BY r.source SEPARATOR ', ') AS sources,
                    MAX(COALESCE(r.produce_date, r.sold_date, c.register_date, r.created_at, c.created_at)) AS latest_date
             FROM planting_material_commodities c
             LEFT JOIN planting_material_records r ON r.commodity_id = c.id AND r.is_active = 1
             WHERE c.id = ? AND c.is_active = 1
             GROUP BY c.id, c.commodity_name, c.tagline, c.native_identity, c.gi_status,
                      c.farmer_linkage, c.brand, c.catchment_reach, c.product_highlights,
                      c.rate, c.unit, c.product_photo, c.product_photos, c.product_note, c.register_date
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $material = null;
    }
}

if ($material) {
    $storedPhotos = json_decode((string) ($material['product_photos'] ?? ''), true);
    if (is_array($storedPhotos)) {
        foreach ($storedPhotos as $photo) {
            pm_detail_add_photo($photos, $photo);
        }
    }
    pm_detail_add_photo($photos, $material['product_photo'] ?? '');
} else {
    http_response_code(404);
}

$produced = max(0, (float) ($material['quantity_produced'] ?? 0));
$sold = max(0, (float) ($material['quantity_sold'] ?? 0));
$distributed = max(0, (float) ($material['quantity_distributed'] ?? 0));
$available = $material ? max(0, $produced - $sold - $distributed) : 0;
$unit = trim((string) ($material['unit'] ?? '')) ?: 'unit';
$productName = trim((string) ($material['commodity_name'] ?? 'KVK Product')) ?: 'KVK Product';
$availabilityPercent = $produced > 0 ? min(100, ($available / $produced) * 100) : 0;

$field = static fn (string $key): string => trim((string) ($material[$key] ?? ''));
$tagline = $field('tagline');
$nativeIdentity = $field('native_identity');
$giStatus = $field('gi_status');
$farmerLinkage = $field('farmer_linkage');
$brand = $field('brand');
$catchmentReach = $field('catchment_reach');
$productHighlights = $field('product_highlights');
$source = $field('sources') ?: 'KVK Farm Production Unit';
$latestDate = $field('latest_date');
$latestDateLabel = $latestDate !== '' && strtotime($latestDate) !== false
    ? date('d M Y', (int) strtotime($latestDate))
    : 'Current listing';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#113f32">
    <title><?= pm_detail_e($productName) ?> | KVK Kokrajhar</title>
    <style>
        :root{
            --pine:#113f32;--pine-2:#195944;--leaf:#2e8b62;--mint:#eaf5ef;--cream:#f6f3e9;
            --paper:#fffefa;--ink:#172a24;--muted:#64736d;--line:#dce6e0;--danger:#a73f3f;
            --shadow:0 30px 90px rgba(12,42,33,.28);--soft-shadow:0 10px 28px rgba(17,63,50,.08)
        }
        *{box-sizing:border-box}html{color-scheme:light}body{margin:0;min-width:320px;color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 9% 14%,rgba(220,236,169,.48),transparent 28%),radial-gradient(circle at 91% 84%,rgba(91,164,125,.30),transparent 30%),linear-gradient(135deg,#e9f1eb 0%,#d5e5da 48%,#bdd7c8 100%)}
        body::before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.32;background-image:radial-gradient(rgba(17,63,50,.28) .7px,transparent .7px);background-size:15px 15px}
        img{max-width:100%}button,input{font:inherit}button,a{-webkit-tap-highlight-color:transparent}[hidden]{display:none!important}
        .screen{position:relative;z-index:1;min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:16px}
        .quick-view{width:min(1380px,100%);height:min(920px,calc(100dvh - 32px));min-height:700px;display:grid;grid-template-rows:auto minmax(0,1fr);overflow:hidden;border:1px solid rgba(255,255,255,.78);border-radius:30px;background:var(--paper);box-shadow:var(--shadow);animation:modalIn .44s cubic-bezier(.2,.85,.25,1) both}
        @keyframes modalIn{from{opacity:0;transform:translateY(18px) scale(.985)}to{opacity:1;transform:none}}
        .modal-bar{min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:11px 14px 11px 20px;border-bottom:1px solid var(--line);background:rgba(255,254,250,.94)}
        .brand{min-width:0;display:flex;align-items:center;gap:12px}.brand-mark{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;border-radius:14px;color:#fff;background:linear-gradient(145deg,var(--leaf),var(--pine));box-shadow:0 8px 18px rgba(17,63,50,.22);font-size:.74rem;font-weight:950;letter-spacing:.04em}.brand-copy{min-width:0}.brand-copy strong{display:block;color:var(--pine);font-size:.9rem;line-height:1.2}.brand-copy span{display:block;margin-top:3px;color:var(--muted);font-size:.72rem}
        .modal-label{margin-left:auto;padding:7px 10px;border-radius:999px;background:var(--mint);color:var(--pine-2);font-size:.68rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
        .close-button{width:42px;height:42px;display:grid;place-items:center;flex:0 0 auto;border:1px solid var(--line);border-radius:50%;color:var(--ink);background:#fff;text-decoration:none;box-shadow:0 5px 14px rgba(17,63,50,.07);transition:.18s ease}.close-button:hover{transform:rotate(5deg);color:#fff;background:var(--pine)}.close-button svg{width:18px;height:18px}.close-button:focus-visible,.tab:focus-visible,.photo-thumb:focus-visible,.action:focus-visible{outline:3px solid rgba(46,139,98,.34);outline-offset:2px}
        .quick-view__body{min-height:0;display:grid;grid-template-columns:minmax(560px,1fr) 550px}
        .visual-pane{grid-column:2;grid-row:1;min-width:0;min-height:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:16px;padding:24px;overflow:auto;border-left:1px solid var(--line);background:linear-gradient(145deg,#edf5f0,#dcebe2)}
        .media-pane{position:relative;width:500px;height:500px;min-width:500px;min-height:500px;flex:0 0 500px;overflow:hidden;border-radius:0;background:linear-gradient(145deg,#cadfd2,#9ec3ae);box-shadow:0 16px 38px rgba(17,63,50,.18)}.main-photo{width:500px;height:500px;display:block;object-fit:cover;transition:opacity .18s ease,transform .5s ease}.media-pane:hover .main-photo{transform:scale(1.015)}.media-pane::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(180deg,rgba(8,34,26,.03) 48%,rgba(7,28,22,.70) 100%)}
        .no-photo{width:500px;height:500px;min-height:500px;display:grid;place-items:center;padding:40px;color:var(--pine);background:radial-gradient(circle at 50% 40%,rgba(255,255,255,.78),transparent 26%),linear-gradient(145deg,#dcebe2,#b8d4c3);text-align:center}.no-photo svg{width:68px;height:68px;margin-bottom:12px;opacity:.7}.no-photo strong{display:block;font-size:1.1rem}.no-photo span{display:block;margin-top:6px;color:#517363;font-size:.82rem}
        .photo-topline{position:absolute;z-index:2;top:18px;left:18px;display:flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid rgba(255,255,255,.28);border-radius:999px;color:#fff;background:rgba(12,45,35,.58);backdrop-filter:blur(10px);font-size:.72rem;font-weight:850}.photo-topline::before{content:"";width:7px;height:7px;border-radius:50%;background:#bde58d}
        .photo-strip{position:absolute;z-index:3;right:18px;bottom:18px;left:18px;display:flex;align-items:center;justify-content:flex-end;gap:9px}.photo-strip__label{display:none}.photo-thumb{width:50px;height:50px;flex:0 0 auto;overflow:hidden;padding:0;border:2px solid rgba(255,255,255,.5);border-radius:0;background:#d8e6dd;cursor:pointer;box-shadow:0 7px 18px rgba(0,0,0,.2);transition:.18s ease}.photo-thumb:hover,.photo-thumb.is-active{transform:translateY(-2px);border-color:#fff}.photo-thumb img{width:100%;height:100%;display:block;object-fit:cover}
        .details-pane{grid-column:1;grid-row:1;min-width:0;min-height:0;display:flex;flex-direction:column;padding:clamp(24px,3.2vw,42px) clamp(22px,3.4vw,46px) 26px;background:radial-gradient(circle at 100% 0,rgba(220,236,169,.18),transparent 30%),var(--paper)}
        .eyebrow{margin:0 0 8px;color:var(--leaf);font-size:.69rem;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.title-row h1{margin:0;color:var(--pine);font-family:Georgia,"Times New Roman",serif;font-size:clamp(2.15rem,3.6vw,3.55rem);line-height:.98;letter-spacing:-.035em}.stock-badge{display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;margin-top:4px;padding:8px 10px;border-radius:999px;color:var(--pine-2);background:var(--mint);font-size:.7rem;font-weight:900}.stock-badge::before{content:"";width:8px;height:8px;border-radius:50%;background:#2f9b68;box-shadow:0 0 0 4px rgba(47,155,104,.12)}.stock-badge.is-empty{color:var(--danger);background:#faece9}.stock-badge.is-empty::before{background:var(--danger)}
        .tagline{max-width:700px;margin:14px 0 0;color:#5d6e67;font-size:.92rem;line-height:1.65}.muted-copy{color:#8a9691;font-style:italic}.chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.chip{padding:6px 9px;border:1px solid var(--line);border-radius:999px;color:#52675e;background:rgba(255,255,255,.74);font-size:.68rem;font-weight:800}
        .facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:20px}.fact{min-width:0;padding:13px 14px;border:1px solid var(--line);border-radius:15px;background:#fff;box-shadow:0 5px 18px rgba(17,63,50,.045)}.fact span{display:block;margin-bottom:4px;color:var(--muted);font-size:.62rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.fact strong{display:block;overflow:hidden;color:var(--pine);font-size:.92rem;line-height:1.3;text-overflow:ellipsis;white-space:nowrap}
        .tab-list{display:flex;gap:4px;margin-top:21px;padding:4px;border:1px solid var(--line);border-radius:14px;background:#f2f6f3}.tab{min-height:38px;flex:1;padding:8px 10px;border:0;border-radius:10px;color:#63736c;background:transparent;cursor:pointer;font-size:.76rem;font-weight:900;transition:.18s ease}.tab:hover{color:var(--pine)}.tab[aria-selected="true"]{color:var(--pine);background:#fff;box-shadow:0 4px 14px rgba(17,63,50,.08)}.tab-panels{min-height:0;flex:1;margin-top:16px}.tab-panel{height:100%;overflow:auto;padding:1px 5px 4px 1px;scrollbar-width:thin;scrollbar-color:#b7cbc0 transparent}
        .section-heading{display:flex;align-items:end;justify-content:space-between;gap:14px;margin-bottom:11px}.section-heading h2{margin:0;color:var(--pine);font-size:1rem}.section-heading span{color:var(--muted);font-size:.68rem}.highlight{padding:16px 17px;border:1px solid #d6e8dd;border-radius:16px;color:#38584b;background:linear-gradient(135deg,#edf7f1,#f8faf6);font-size:.87rem;line-height:1.65;white-space:pre-line}
        .inventory{margin-top:13px;padding:14px 16px;border:1px solid var(--line);border-radius:16px;background:#fff}.inventory__top{display:flex;align-items:center;justify-content:space-between;gap:12px}.inventory__top strong{color:var(--pine);font-size:.82rem}.inventory__top span{color:var(--leaf);font-size:.72rem;font-weight:900}.progress{height:7px;overflow:hidden;margin-top:10px;border-radius:999px;background:#e5ede8}.progress span{display:block;width:<?= pm_detail_e(number_format($availabilityPercent, 2, '.', '')) ?>%;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--leaf),#7eae56)}.inventory-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:12px}.inventory-grid div{padding-right:8px;border-right:1px solid var(--line)}.inventory-grid div:last-child{padding-right:0;border-right:0}.inventory-grid span{display:block;color:var(--muted);font-size:.6rem;text-transform:uppercase}.inventory-grid strong{display:block;margin-top:3px;color:var(--pine);font-size:.76rem;overflow-wrap:anywhere}
        .story-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.story-card{padding:14px 15px;border:1px solid var(--line);border-radius:15px;background:#fff}.story-card.is-wide{grid-column:1/-1}.story-label{display:flex;align-items:center;gap:8px;margin-bottom:7px;color:var(--leaf);font-size:.63rem;font-weight:950;letter-spacing:.08em;text-transform:uppercase}.story-icon{width:25px;height:25px;display:grid;place-items:center;border-radius:8px;color:var(--pine);background:var(--mint);font-size:.64rem}.story-card p{margin:0;color:#465d53;font-size:.79rem;line-height:1.58;white-space:pre-line;overflow-wrap:anywhere}.story-card p.is-strong{color:var(--pine);font-size:.88rem;font-weight:850}
        .display-sheet{min-height:0;overflow:auto;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:20px;padding:1px 5px 3px 1px;scrollbar-width:thin;scrollbar-color:#b7cbc0 transparent}.sheet-card{padding:15px 16px;border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:0 5px 16px rgba(17,63,50,.045)}.sheet-card--wide{grid-column:1/-1}.sheet-card__heading{display:flex;align-items:center;gap:9px;margin:0 0 9px;color:var(--pine);font-size:.82rem;font-weight:950;letter-spacing:.04em;text-transform:uppercase}.sheet-card__icon{width:28px;height:28px;display:grid;place-items:center;flex:0 0 auto;border-radius:9px;color:var(--pine);background:var(--mint);font-size:.66rem}.sheet-card p{margin:0;color:#465d53;font-size:.8rem;line-height:1.6;white-space:pre-line;overflow-wrap:anywhere}.identity-list{display:grid;gap:10px}.identity-row{padding-top:10px;border-top:1px solid #e8eeea}.identity-row:first-child{padding-top:0;border-top:0}.identity-row strong{display:block;margin-bottom:3px;color:var(--leaf);font-size:.62rem;letter-spacing:.08em;text-transform:uppercase}.sheet-value{color:var(--pine)!important;font-size:.9rem!important;font-weight:850}
        .qr-dock{width:500px;display:grid;grid-template-columns:200px minmax(0,1fr);gap:18px;align-items:center;padding:0;border:0;background:transparent}.qr-box{width:200px;height:200px;min-height:200px;display:grid;place-items:center;padding:0;border:0;border-radius:0;background:#fff;overflow:hidden;box-shadow:0 8px 22px rgba(17,63,50,.12)}.qr-box img,.qr-box canvas{width:200px!important;height:200px!important;max-width:none!important}.share-copy h2{margin:0;color:var(--pine);font-size:1rem;line-height:1.35}
        .trust-note{display:flex;gap:10px;margin-top:13px;padding:12px 13px;border:1px solid #d7e6dd;border-radius:13px;background:#f4f9f6}.trust-note__icon{width:27px;height:27px;flex:0 0 auto;display:grid;place-items:center;border-radius:50%;color:#fff;background:var(--leaf);font-size:.72rem;font-weight:900}.trust-note strong{display:block;color:var(--pine);font-size:.75rem}.trust-note span{display:block;margin-top:2px;color:var(--muted);font-size:.65rem;line-height:1.45}.gallery-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:15px}.gallery-grid a{display:block;overflow:hidden;aspect-ratio:1;border:1px solid var(--line);border-radius:12px;background:#e8f0eb}.gallery-grid img{width:100%;height:100%;display:block;object-fit:cover;transition:.2s ease}.gallery-grid a:hover img{transform:scale(1.04)}
        .not-found{width:min(570px,100%);padding:clamp(34px,7vw,64px);border:1px solid rgba(255,255,255,.8);border-radius:28px;background:var(--paper);box-shadow:var(--shadow);text-align:center}.not-found__icon{width:62px;height:62px;display:grid;place-items:center;margin:0 auto 18px;border-radius:20px;color:var(--pine);background:var(--mint);font-size:1.5rem}.not-found h1{margin:0;color:var(--pine);font-family:Georgia,"Times New Roman",serif;font-size:2.2rem}.not-found p{margin:10px 0 22px;color:var(--muted);line-height:1.6}.return-link{display:inline-flex;align-items:center;gap:8px;padding:11px 15px;border-radius:12px;color:#fff;background:var(--pine);text-decoration:none;font-weight:850;font-size:.84rem}
        @media(max-width:980px){.quick-view{height:calc(100dvh - 24px);min-height:0}.quick-view__body{display:flex;flex-direction:column;overflow-y:auto}.details-pane{order:1;min-height:auto;padding:26px 24px 22px}.visual-pane{order:2;min-height:auto;overflow:auto;padding:24px;border-top:1px solid var(--line);border-left:0}.title-row h1{font-size:clamp(2rem,4.4vw,3rem)}.photo-strip__label{display:none}.photo-strip{justify-content:flex-end}}
        @media(max-width:780px){.screen{place-items:start center;padding:10px}.quick-view{height:calc(100dvh - 20px);min-height:0;border-radius:23px}.modal-bar{min-height:60px;padding:8px 9px 8px 13px}.brand-mark{width:38px;height:38px;border-radius:12px}.brand-copy span,.modal-label{display:none}.brand-copy strong{font-size:.8rem}.close-button{width:39px;height:39px}.quick-view__body{display:flex;flex-direction:column;overflow-y:auto}.details-pane{order:1;min-height:auto;padding:24px 19px 20px}.visual-pane{order:2;display:flex;padding:22px 10px;border-top:1px solid var(--line);border-left:0}.media-pane:hover .main-photo{transform:none}.title-row h1{font-size:clamp(2.1rem,10vw,3rem)}.tagline{font-size:.86rem}.display-sheet{overflow:visible}.photo-topline{top:12px;left:12px}.photo-strip{right:12px;bottom:12px;left:12px}.photo-thumb{width:44px;height:44px;border-radius:0}}
        @media(max-width:540px){.display-sheet{grid-template-columns:1fr}.sheet-card--wide{grid-column:auto}.qr-dock{grid-template-columns:200px minmax(0,1fr);width:500px}.photo-strip__label{display:none}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
        @media print{body{background:#fff}body::before,.close-button,.tab-list{display:none!important}.screen{display:block;padding:0}.quick-view{width:100%;height:auto;min-height:0;overflow:visible;border:0;border-radius:0;box-shadow:none}.quick-view__body{display:block}.media-pane{width:500px;height:500px;min-height:500px}.details-pane{display:block;padding:24px 0}.tab-panels{margin-top:18px}.tab-panel,.tab-panel[hidden]{display:block!important;height:auto;overflow:visible;margin-bottom:18px}}
    </style>
</head>
<body>
<main class="screen">
<?php if ($material): ?>
    <article class="quick-view" role="dialog" aria-modal="true" aria-labelledby="product-title" aria-describedby="product-tagline">
        <header class="modal-bar">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">KVK</div>
                <div class="brand-copy"><strong>Krishi Vigyan Kendra, Kokrajhar</strong><span>Assam Agricultural University · Planting Materials</span></div>
            </div>
            <span class="modal-label">Product quick view</span>
            <a class="close-button" id="closeProductView" href="planting-materials.php#available-materials" aria-label="Close product details">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </a>
        </header>

        <div class="quick-view__body">
            <aside class="visual-pane" aria-label="Product photo and QR code">
            <section class="media-pane" aria-label="Product photos">
                <?php if ($photos): ?>
                    <img class="main-photo" id="mainProductPhoto" src="<?= pm_detail_e($photos[0]) ?>" alt="<?= pm_detail_e($productName) ?> product photo">
                    <span class="photo-topline"><?= count($photos) ?> verified photo<?= count($photos) === 1 ? '' : 's' ?></span>
                    <div class="photo-strip">
                        <div class="photo-strip__label"><strong>KVK product gallery</strong><span>Select a photo to preview</span></div>
                        <?php foreach (array_slice($photos, 0, 4) as $photoIndex => $photo): ?>
                            <button class="photo-thumb<?= $photoIndex === 0 ? ' is-active' : '' ?>" type="button" data-photo-src="<?= pm_detail_e($photo) ?>" data-photo-alt="<?= pm_detail_e($productName) ?> photo <?= $photoIndex + 1 ?>" aria-label="Show product photo <?= $photoIndex + 1 ?>" aria-pressed="<?= $photoIndex === 0 ? 'true' : 'false' ?>">
                                <img src="<?= pm_detail_e($photo) ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-photo"><div>
                        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M32 55V29M32 29c-12 0-20-7-20-18 12 0 20 7 20 18ZM32 38c11 0 19-6 19-16-11 0-19 6-19 16Z"/><path d="M18 55h28"/></svg>
                        <strong>Product photo coming soon</strong><span>KVK Kokrajhar will update this profile.</span>
                    </div></div>
                <?php endif; ?>
            </section>
            <section class="qr-dock" aria-labelledby="qr-heading">
                <div class="qr-box" id="productDetailQr" aria-label="QR code for this product page"></div>
                <div class="share-copy">
                    <h2 id="qr-heading">Scan product QR</h2>
                </div>
            </section>
            </aside>

            <section class="details-pane">
                <p class="eyebrow">Local planting material · Product <?= pm_detail_e((string) $material['id']) ?></p>
                <div class="title-row">
                    <h1 id="product-title"><?= pm_detail_e($productName) ?></h1>
                </div>
                <p class="tagline" id="product-tagline"><?= $tagline !== '' ? pm_detail_e($tagline) : '<span class="muted-copy">Fresh planting material maintained by KVK Kokrajhar.</span>' ?></p>
                <div class="display-sheet" aria-label="Complete product display sheet">
                    <section class="sheet-card sheet-card--wide">
                        <h2 class="sheet-card__heading"><span class="sheet-card__icon">P</span>Product details</h2>
                        <p><?= $productHighlights !== '' ? pm_detail_e($productHighlights) : '<span class="muted-copy">Product details have not yet been added.</span>' ?></p>
                    </section>

                    <section class="sheet-card sheet-card--wide">
                        <h2 class="sheet-card__heading"><span class="sheet-card__icon">IR</span>Identity &amp; reach</h2>
                        <div class="identity-list">
                            <div class="identity-row"><strong>Native identity</strong><p><?= $nativeIdentity !== '' ? pm_detail_e($nativeIdentity) : '<span class="muted-copy">Not provided</span>' ?></p></div>
                            <div class="identity-row"><strong>Farmer linkage</strong><p><?= $farmerLinkage !== '' ? pm_detail_e($farmerLinkage) : '<span class="muted-copy">Not provided</span>' ?></p></div>
                            <div class="identity-row"><strong>Catchment &amp; reach</strong><p><?= $catchmentReach !== '' ? pm_detail_e($catchmentReach) : '<span class="muted-copy">Not provided</span>' ?></p></div>
                        </div>
                    </section>

                    <section class="sheet-card">
                        <h2 class="sheet-card__heading"><span class="sheet-card__icon">B</span>Brand</h2>
                        <p class="sheet-value"><?= $brand !== '' ? pm_detail_e($brand) : '<span class="muted-copy">Not provided</span>' ?></p>
                    </section>

                    <section class="sheet-card">
                        <h2 class="sheet-card__heading"><span class="sheet-card__icon">GI</span>GI tag</h2>
                        <p class="sheet-value"><?= $giStatus !== '' ? pm_detail_e($giStatus) : '<span class="muted-copy">Not provided</span>' ?></p>
                    </section>
                </div>
            </section>
        </div>
    </article>
<?php else: ?>
    <section class="not-found"><div class="not-found__icon" aria-hidden="true">?</div><h1>Product not found</h1><p>The requested planting material is unavailable or may have been removed.</p><a class="return-link" href="planting-materials.php#available-materials"><span aria-hidden="true">←</span> View available materials</a></section>
<?php endif; ?>
</main>

<?php if ($material): ?>
<script src="assets/vendor/qrcode.min.js" defer></script>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const mainPhoto = document.getElementById('mainProductPhoto');
    const photoButtons = Array.from(document.querySelectorAll('[data-photo-src]'));
    const closeButton = document.getElementById('closeProductView');
    const url = window.location.href.split('#')[0];
    const qrBox = document.getElementById('productDetailQr');

    photoButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!mainPhoto) return;
            photoButtons.forEach((item) => {
                const selected = item === button;
                item.classList.toggle('is-active', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            mainPhoto.style.opacity = '.35';
            window.setTimeout(() => {
                mainPhoto.src = button.dataset.photoSrc || '';
                mainPhoto.alt = button.dataset.photoAlt || <?= json_encode($productName . ' product photo', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                mainPhoto.style.opacity = '1';
            }, 130);
        });
    });

    closeButton?.addEventListener('click', function (event) {
        if (window.opener && !window.opener.closed) {
            event.preventDefault();
            window.close();
        }
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeButton?.click(); });

    if (qrBox && typeof window.QRCode === 'function') {
        qrBox.innerHTML = '';
        new QRCode(qrBox, {text:url,width:200,height:200,colorDark:'#113f32',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.H});
    } else if (qrBox) {
        qrBox.textContent = 'QR code unavailable';
    }
});
</script>
<?php endif; ?>
</body>
</html>
