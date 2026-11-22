<?php
require_once __DIR__ . '/app/content.php';

$page_title = 'Planting Materials Available | ' . APP_NAME;

function planting_public_ensure_tables(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS planting_material_commodities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        register_date DATE NULL,
        commodity_name VARCHAR(220) NOT NULL,
        rate DECIMAL(12,2) DEFAULT 0,
        unit VARCHAR(80) NULL,
        product_photo VARCHAR(255) NULL,
        product_photos TEXT NULL,
        product_note TEXT NULL,
        tagline TEXT NULL,
        native_identity TEXT NULL,
        gi_status VARCHAR(255) NULL,
        farmer_linkage TEXT NULL,
        brand VARCHAR(255) NULL,
        catchment_reach TEXT NULL,
        product_highlights TEXT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS planting_material_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NULL UNIQUE,
        commodity_id INT NULL,
        commodity_name VARCHAR(220) NULL,
        produce_date DATE NULL,
        sold_date DATE NULL,
        source VARCHAR(220) NULL,
        quantity_produced DECIMAL(12,3) DEFAULT 0,
        value_of_commodity DECIMAL(12,2) DEFAULT 0,
        quantity_sold DECIMAL(12,3) DEFAULT 0,
        quantity_distributed DECIMAL(12,3) DEFAULT 0,
        revenue_generated DECIMAL(12,2) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS planting_material_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        commodity_id INT NOT NULL,
        commodity_name VARCHAR(220) NOT NULL,
        customer_name VARCHAR(220) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        customer_address TEXT NULL,
        quantity_ordered DECIMAL(12,3) NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Desk Notifications Table for Admin & Head Desks
    $pdo->exec("CREATE TABLE IF NOT EXISTS desk_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'order',
        target_role VARCHAR(50) NOT NULL,
        reference_id INT NULL,
        link VARCHAR(255) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $alterStatements = [
        "ALTER TABLE planting_material_commodities ADD COLUMN register_date DATE NULL AFTER id",
        "ALTER TABLE planting_material_commodities ADD COLUMN product_photo VARCHAR(255) NULL AFTER unit",
        "ALTER TABLE planting_material_commodities ADD COLUMN product_photos TEXT NULL AFTER product_photo",
        "ALTER TABLE planting_material_commodities ADD COLUMN product_note TEXT NULL AFTER product_photos",
        "ALTER TABLE planting_material_commodities ADD COLUMN tagline TEXT NULL AFTER product_note",
        "ALTER TABLE planting_material_commodities ADD COLUMN native_identity TEXT NULL AFTER tagline",
        "ALTER TABLE planting_material_commodities ADD COLUMN gi_status VARCHAR(255) NULL AFTER native_identity",
        "ALTER TABLE planting_material_commodities ADD COLUMN farmer_linkage TEXT NULL AFTER gi_status",
        "ALTER TABLE planting_material_commodities ADD COLUMN brand VARCHAR(255) NULL AFTER farmer_linkage",
        "ALTER TABLE planting_material_commodities ADD COLUMN catchment_reach TEXT NULL AFTER brand",
        "ALTER TABLE planting_material_commodities ADD COLUMN product_highlights TEXT NULL AFTER catchment_reach",
        "ALTER TABLE planting_material_records ADD COLUMN produce_date DATE NULL AFTER commodity_name",
        "ALTER TABLE planting_material_records ADD COLUMN sold_date DATE NULL AFTER produce_date",
        "ALTER TABLE planting_material_records ADD COLUMN value_of_commodity DECIMAL(12,2) DEFAULT 0 AFTER quantity_produced",
    ];

    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Older installs may already have these columns.
        }
    }
}

function planting_public_rows(): array
{
    $pdo = db();
    if (!$pdo) {
        return [];
    }

    planting_public_ensure_tables($pdo);
    $stmt = $pdo->query("SELECT
            c.id,
            c.commodity_name,
            c.rate,
            c.unit,
            c.product_photo,
            c.product_photos,
            c.product_note,
            c.tagline,
            c.native_identity,
            c.gi_status,
            c.farmer_linkage,
            c.brand,
            c.catchment_reach,
            c.product_highlights,
            c.register_date,
            COALESCE(SUM(r.quantity_produced), 0) AS quantity_produced,
            COALESCE(SUM(r.quantity_sold), 0) AS quantity_sold,
            COALESCE(SUM(r.quantity_distributed), 0) AS quantity_distributed,
            COALESCE(SUM(r.value_of_commodity), 0) AS value_of_commodity,
            COALESCE(SUM(r.revenue_generated), 0) AS revenue_generated,
            GROUP_CONCAT(DISTINCT NULLIF(r.source, '') ORDER BY r.source SEPARATOR ', ') AS sources,
            MAX(COALESCE(r.produce_date, r.sold_date, c.register_date, r.created_at, c.created_at)) AS latest_date
        FROM planting_material_commodities c
        LEFT JOIN planting_material_records r ON r.commodity_id = c.id AND r.is_active = 1
        WHERE c.is_active = 1
        GROUP BY c.id, c.commodity_name, c.rate, c.unit, c.product_photo, c.product_photos, c.product_note,
                 c.tagline, c.native_identity, c.gi_status, c.farmer_linkage, c.brand, c.catchment_reach,
                 c.product_highlights, c.register_date
        ORDER BY c.commodity_name ASC");

    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];


    return $rows;
}

function planting_public_decimal($value): string
{
    $number = max(0, (float) $value);
    $formatted = number_format($number, 3);
    return rtrim(rtrim($formatted, '0'), '.') ?: '0';
}

function planting_public_money($value): string
{
    return number_format(max(0, (float) $value), 2);
}

/**
 * Keep the public planting-material card concise. Product profile metadata
 * (Native Identity, ingredients, GI status, farmer linkage, brand, reach and
 * highlights) can remain stored in the database, but is not shown here.
 */
function planting_public_display_note($value): string
{
    $note = trim((string) $value);
    if ($note === '') {
        return '';
    }

    // Notes copied from the KVK Product module may contain a complete product
    // profile. Remove that profile from the first structured heading onward.
    $markers = [
        'Native Identity',
        'Foxtail Millet Ingredients',
        'Ingredients',
        'GI Status',
        'Farmer Linkage',
        'Brand',
        'Catchment & Reach',
        'Catchment and Reach',
        'Highlights',
        'Product Highlights',
    ];

    $cutAt = null;
    foreach ($markers as $marker) {
        $position = stripos($note, $marker);
        if ($position !== false && ($cutAt === null || $position < $cutAt)) {
            $cutAt = $position;
        }
    }

    if ($cutAt !== null) {
        $note = substr($note, 0, $cutAt);
    }

    return trim($note, " \t\n\r\0\x0B:-|•");
}

function planting_public_image(int $index): string
{
    $images = [
        'assets/img/campus-2.jpg',
        'assets/img/campus-3.jpeg',
        'assets/img/campus-5.jpeg',
        'assets/img/campus-7.jpg',
        'assets/img/slider6.jpg',
        'assets/img/slider7.jpg',
    ];

    return $images[$index % count($images)];
}

function planting_public_photo_list(array $material, int $index): array
{
    $photos = [];
    $stored = trim((string) ($material['product_photos'] ?? ''));
    if ($stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            foreach ($decoded as $photo) {
                $photo = trim((string) $photo);
                if ($photo !== '') {
                    $photos[] = $photo;
                }
            }
        }
    }

    $primary = trim((string) ($material['product_photo'] ?? ''));
    if ($primary !== '' && !in_array($primary, $photos, true)) {
        array_unshift($photos, $primary);
    }

    if (!$photos) {
        $photos[] = planting_public_image($index);
    }

    return array_slice(array_values(array_unique($photos)), 0, 3);
}

// Order Form Processing with Admin & Head Desk Notifications
$orderSuccess = false;
$orderError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $pdo = db();
    if ($pdo) {
        planting_public_ensure_tables($pdo);
        $commodityId = (int)($_POST['commodity_id'] ?? 0);
        $commodityName = trim((string)($_POST['commodity_name'] ?? ''));
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $customerPhone = trim((string)($_POST['customer_phone'] ?? ''));
        $customerAddress = trim((string)($_POST['customer_address'] ?? ''));
        $qty = max(0, (float)($_POST['quantity_ordered'] ?? 0));
        $rate = max(0, (float)($_POST['unit_rate'] ?? 0));
        $totalAmount = $qty * $rate;

        if ($commodityId > 0 && $customerName !== '' && $customerPhone !== '' && $qty > 0) {
            $stmt = $pdo->prepare("INSERT INTO planting_material_orders (commodity_id, commodity_name, customer_name, customer_phone, customer_address, quantity_ordered, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$commodityId, $commodityName, $customerName, $customerPhone, $customerAddress, $qty, $totalAmount])) {
                $orderId = (int)$pdo->lastInsertId();
                $orderSuccess = true;

                // Dispatch Notifications to Admin Desk and Head Desk
                $notifTitle = "New Order: " . $commodityName;
                $notifMsg = "Customer " . $customerName . " (" . $customerPhone . ") ordered " . $qty . " unit(s) for Rs. " . number_format($totalAmount, 2);
                $link = "admin-orders.php?order_id=" . $orderId;

                $notifStmt = $pdo->prepare("INSERT INTO desk_notifications (title, message, type, target_role, reference_id, link) VALUES (?, ?, 'order', ?, ?, ?)");
                
                // 1. Send to Admin Desk
                $notifStmt->execute([$notifTitle, $notifMsg, 'admin', $orderId, $link]);
                
                // 2. Send to Head Desk
                $notifStmt->execute([$notifTitle, $notifMsg, 'head', $orderId, $link]);
            } else {
                $orderError = "Unable to process your order request. Please try again.";
            }
        } else {
            $orderError = "Please complete all required fields accurately.";
        }
    }
}

$materials = planting_public_rows();
$availableCount = 0;
$totalAvailable = 0.0;
$totalValue = 0.0;
$units = [];

foreach ($materials as $material) {
    $available = max(0, (float) ($material['quantity_produced'] ?? 0) - (float) ($material['quantity_sold'] ?? 0) - (float) ($material['quantity_distributed'] ?? 0));
    if ($available > 0) {
        $availableCount++;
    }
    $totalAvailable += $available;
    $totalValue += max(0, (float) ($material['value_of_commodity'] ?? 0));
    $unit = trim((string) ($material['unit'] ?? ''));
    if ($unit !== '') {
        $units[$unit] = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
    :root {
        --pm-primary: #115e3b;
        --pm-primary-dark: #0a3a24;
        --pm-accent: #e07a5f;
        --pm-bg: #f4f7f5;
        --pm-surface: #ffffff;
        --pm-text-main: #1c2a23;
        --pm-text-muted: #5a6e63;
        --pm-border: #e1e8e3;
        --pm-radius: 16px;
        --pm-shadow: 0 10px 30px rgba(17, 94, 59, 0.08);
        --pm-shadow-hover: 0 18px 40px rgba(17, 94, 59, 0.15);
    }

    .pm-hero {
        position: relative;
        min-height: 380px;
        display: flex;
        align-items: center;
        color: #fff;
        background: linear-gradient(135deg, rgba(10, 58, 36, 0.92), rgba(17, 94, 59, 0.75)), url('assets/img/campus-5.jpeg') center/cover no-repeat;
        padding: 60px 0;
    }

    .pm-hero__label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 16px;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 50px;
        padding: 6px 16px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(8px);
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pm-hero h1 {
        margin: 0 0 12px;
        font-size: clamp(2.2rem, 5vw, 3.8rem);
        font-weight: 800;
        line-height: 1.1;
    }

    .pm-hero p {
        max-width: 650px;
        margin: 0;
        color: rgba(255, 255, 255, 0.9);
        font-size: 1.1rem;
        line-height: 1.6;
    }

    .pm-store {
        background: var(--pm-bg);
        padding: 40px 0 80px;
    }

    .pm-alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pm-alert--success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .pm-alert--error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .pm-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }

    .pm-summary-card {
        background: var(--pm-surface);
        border: 1px solid var(--pm-border);
        border-radius: var(--pm-radius);
        padding: 20px 24px;
        box-shadow: var(--pm-shadow);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .pm-summary-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(17, 94, 59, 0.1);
        color: var(--pm-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .pm-summary-info span {
        display: block;
        color: var(--pm-text-muted);
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .pm-summary-info strong {
        display: block;
        margin-top: 4px;
        color: var(--pm-primary-dark);
        font-size: 1.6rem;
        font-weight: 800;
        line-height: 1;
    }

    .pm-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .pm-search {
        position: relative;
        flex: 1;
        min-width: 280px;
    }

    .pm-search input {
        width: 100%;
        padding: 14px 20px 14px 48px;
        border: 1px solid var(--pm-border);
        border-radius: 50px;
        background: var(--pm-surface);
        color: var(--pm-text-main);
        font-size: 0.95rem;
        box-shadow: var(--pm-shadow);
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .pm-search input:focus {
        border-color: var(--pm-primary);
        box-shadow: 0 0 0 4px rgba(17, 94, 59, 0.12);
    }

    .pm-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--pm-text-muted);
        pointer-events: none;
    }

    .pm-filter-tabs {
        display: flex;
        gap: 8px;
        background: rgba(0, 0, 0, 0.04);
        padding: 4px;
        border-radius: 50px;
    }

    .pm-filter-btn {
        border: none;
        background: transparent;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--pm-text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pm-filter-btn.is-active {
        background: var(--pm-surface);
        color: var(--pm-primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .pm-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
    }

    .pm-card {
        background: var(--pm-surface);
        border: 1px solid var(--pm-border);
        border-radius: var(--pm-radius);
        overflow: hidden;
        box-shadow: var(--pm-shadow);
        display: flex;
        flex-direction: column;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .pm-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--pm-shadow-hover);
    }

    .pm-card__visual {
        position: relative;
        height: 240px;
        background: #eef3f0;
        overflow: hidden;
    }

    .pm-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    .pm-slide.is-active {
        opacity: 1;
    }

    .pm-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pm-card__badge {
        position: absolute;
        top: 14px;
        left: 14px;
        z-index: 2;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #fff;
        background: #10b981;
        backdrop-filter: blur(4px);
    }

    .pm-card__badge--out {
        background: #ef4444;
    }

    .pm-slider-dots {
        position: absolute;
        bottom: 12px;
        left: 0;
        right: 0;
        z-index: 2;
        display: flex;
        justify-content: center;
        gap: 6px;
    }

    .pm-slider-dot {
        width: 8px;
        height: 8px;
        border-radius: 50px;
        border: none;
        background: rgba(255, 255, 255, 0.5);
        padding: 0;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pm-slider-dot.is-active {
        width: 20px;
        background: #fff;
    }

    .pm-card__body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .pm-card h3 {
        margin: 0 0 8px;
        color: var(--pm-text-main);
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .pm-price {
        color: var(--pm-primary);
        font-size: 1.5rem;
        font-weight: 800;
        margin-bottom: 12px;
    }

    .pm-price small {
        color: var(--pm-text-muted);
        font-size: 0.85rem;
        font-weight: 500;
    }

    .pm-note {
        color: var(--pm-text-muted);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 16px;
        flex: 1;
    }

    .pm-meta {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        background: var(--pm-bg);
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 14px;
    }

    .pm-meta span {
        display: block;
        color: var(--pm-text-muted);
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .pm-meta strong {
        display: block;
        color: var(--pm-text-main);
        font-size: 0.95rem;
        margin-top: 2px;
    }

    .pm-source {
        font-size: 0.85rem;
        color: var(--pm-text-muted);
        border-top: 1px dashed var(--pm-border);
        padding-top: 12px;
        margin-bottom: 16px;
    }

    .pm-source strong {
        color: var(--pm-text-main);
    }

    .pm-order-btn {
        width: 100%;
        padding: 12px 18px;
        border: none;
        border-radius: 10px;
        background: var(--pm-primary);
        color: #fff;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.2s ease, transform 0.1s ease;
    }

    .pm-order-btn:hover {
        background: var(--pm-primary-dark);
    }

    .pm-order-btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }

    .pm-card-actions {
        display: grid;
        grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
        gap: 10px;
        margin-top: auto;
    }

    .pm-card-actions .pm-order-btn {
        min-width: 0;
    }

    .pm-qr-btn {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--pm-primary);
        border-radius: 10px;
        background: #fff;
        color: var(--pm-primary-dark);
        font-size: 0.92rem;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background 0.2s ease, color 0.2s ease, transform 0.1s ease;
    }

    .pm-qr-btn:hover {
        background: var(--pm-primary);
        color: #fff;
    }

    .pm-qr-box {
        width: 240px;
        min-height: 240px;
        margin: 4px auto 18px;
        padding: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--pm-border);
        border-radius: 14px;
        background: #fff;
    }

    .pm-qr-box img,
    .pm-qr-box canvas {
        max-width: 100%;
        height: auto !important;
    }

    .pm-qr-help {
        margin: 0 0 16px;
        color: var(--pm-text-muted);
        font-size: 0.9rem;
        line-height: 1.55;
        text-align: center;
    }

    .pm-qr-url {
        width: 100%;
        margin-bottom: 12px;
        padding: 11px 12px;
        border: 1px solid var(--pm-border);
        border-radius: 8px;
        background: var(--pm-bg);
        color: var(--pm-text-muted);
        font-size: 0.78rem;
    }

    .pm-details-link {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px 18px;
        border-radius: 10px;
        background: var(--pm-primary);
        color: #fff;
        font-weight: 800;
        text-decoration: none;
    }

    .pm-details-link:hover {
        background: var(--pm-primary-dark);
        color: #fff;
    }

    .pm-empty {
        grid-column: 1 / -1;
        background: var(--pm-surface);
        border: 2px dashed var(--pm-border);
        border-radius: var(--pm-radius);
        padding: 60px 20px;
        text-align: center;
        color: var(--pm-text-muted);
    }

    /* Modal Styles */
    .pm-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(10, 30, 20, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .pm-modal-overlay.is-active {
        opacity: 1;
        visibility: visible;
    }

    .pm-modal {
        background: var(--pm-surface);
        border-radius: var(--pm-radius);
        max-width: 500px;
        width: 100%;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        transform: translateY(20px);
        transition: transform 0.25s ease;
    }

    .pm-modal-overlay.is-active .pm-modal {
        transform: translateY(0);
    }

    .pm-modal-header {
        background: var(--pm-primary-dark);
        color: #fff;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pm-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .pm-modal-close {
        background: transparent;
        border: none;
        color: #fff;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        opacity: 0.8;
    }

    .pm-modal-close:hover {
        opacity: 1;
    }

    .pm-modal-body {
        padding: 24px;
    }

    .pm-form-group {
        margin-bottom: 18px;
    }

    .pm-form-group label {
        display: block;
        margin-bottom: 6px;
        color: var(--pm-text-main);
        font-size: 0.88rem;
        font-weight: 700;
    }

    .pm-form-group input,
    .pm-form-group textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--pm-border);
        border-radius: 8px;
        font-size: 0.95rem;
        color: var(--pm-text-main);
        outline: none;
        box-sizing: border-box;
    }

    .pm-form-group input:focus,
    .pm-form-group textarea:focus {
        border-color: var(--pm-primary);
        box-shadow: 0 0 0 3px rgba(17, 94, 59, 0.12);
    }

    .pm-summary-box {
        background: var(--pm-bg);
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pm-summary-box div span {
        display: block;
        font-size: 0.8rem;
        color: var(--pm-text-muted);
    }

    .pm-summary-box div strong {
        font-size: 1.1rem;
        color: var(--pm-primary-dark);
    }

    @media (max-width: 992px) {
        .pm-grid { grid-template-columns: repeat(2, 1fr); }
        .pm-summary { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 640px) {
        .pm-grid { grid-template-columns: 1fr; }
        .pm-summary { grid-template-columns: 1fr; }
        .pm-controls { flex-direction: column; align-items: stretch; }
        .pm-card-actions { grid-template-columns: 1fr; }
    }
</style>

<section class="pm-hero">
    <div class="container">
        <span class="pm-hero__label">KVK Agriculture Portal</span>
        <h1>Quality Planting Materials</h1>
        <p>Explore certified seeds, saplings, poultry, and agricultural inputs available directly from Krishi Vigyan Kendra.</p>
    </div>
</section>

<section class="pm-store" id="available-materials">
    <div class="container">

        <?php if ($orderSuccess): ?>
            <div class="pm-alert pm-alert--success">
                <span>🎉 Order submitted successfully! Our KVK team has been notified and will contact you shortly.</span>
            </div>
        <?php endif; ?>

        <?php if ($orderError !== ''): ?>
            <div class="pm-alert pm-alert--error">
                <span>⚠️ <?= e($orderError) ?></span>
            </div>
        <?php endif; ?>
        
        

        <div class="pm-controls">
            <div class="pm-search">
                <svg class="pm-search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" data-pm-search placeholder="Search planting materials, crops, or sources...">
            </div>

            <div class="pm-filter-tabs">
                <button class="pm-filter-btn is-active" data-filter="all">All Items</button>
                <button class="pm-filter-btn" data-filter="available">In Stock</button>
                <button class="pm-filter-btn" data-filter="out">Out of Stock</button>
            </div>
        </div>

        <?php if (!$materials): ?>
            <div class="pm-empty">
                <h3>No materials currently cataloged</h3>
                <p>Please check back later for updated farm produce and planting supplies.</p>
            </div>
        <?php else: ?>
            <div class="pm-grid" data-pm-grid>
                <?php foreach ($materials as $index => $material): ?>
                    <?php
                        $produced = max(0, (float) ($material['quantity_produced'] ?? 0));
                        $sold = max(0, (float) ($material['quantity_sold'] ?? 0));
                        $distributed = max(0, (float) ($material['quantity_distributed'] ?? 0));
                        $available = max(0, $produced - $sold - $distributed);
                        $unit = trim((string) ($material['unit'] ?? '')) ?: 'unit';
                        $rate = (float)($material['rate'] ?? 0);
                        $productNote = planting_public_display_note($material['product_note'] ?? '');
                        $productPhotos = planting_public_photo_list($material, (int) $index);
                        $searchText = strtolower(trim((string) ($material['commodity_name'] ?? '') . ' ' . (string) ($material['sources'] ?? '') . ' ' . $productNote . ' ' . $unit));
                        $statusClass = $available > 0 ? 'available' : 'out';
                    ?>
                    <article class="pm-card" data-pm-card data-pm-slider data-status="<?= $statusClass ?>" data-search="<?= e($searchText) ?>">
                        <div class="pm-card__visual">
                            <?php foreach ($productPhotos as $photoIndex => $productPhoto): ?>
                                <div class="pm-slide<?= $photoIndex === 0 ? ' is-active' : '' ?>" data-pm-slide="<?= e((string) $photoIndex) ?>">
                                    <img src="<?= e($productPhoto) ?>" alt="<?= e((string) ($material['commodity_name'] ?? 'Planting Material')) ?>" loading="lazy">
                                </div>
                            <?php endforeach; ?>
                            <span class="pm-card__badge<?= $available > 0 ? '' : ' pm-card__badge--out' ?>">
                                <?= $available > 0 ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                            <?php if (count($productPhotos) > 1): ?>
                                <div class="pm-slider-dots">
                                    <?php foreach ($productPhotos as $photoIndex => $productPhoto): ?>
                                        <button class="pm-slider-dot<?= $photoIndex === 0 ? ' is-active' : '' ?>" type="button" data-pm-dot="<?= e((string) $photoIndex) ?>" aria-label="Slide <?= e((string) ($photoIndex + 1)) ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="pm-card__body">
                            <h3><?= e((string) ($material['commodity_name'] ?? 'Planting Material')) ?></h3>
                            <div class="pm-price">Rs. <?= e(planting_public_money($rate)) ?> <small>/ <?= e($unit) ?></small></div>
                            
                            <?php if ($productNote !== ''): ?>
                                <div class="pm-note">
                                    <?= e($productNote) ?>
                                </div>
                            <?php endif; ?>

                            <div class="pm-meta">
                                <div><span>Available</span><strong><?= e(planting_public_decimal($available)) ?> <?= e($unit) ?></strong></div>
                                <div><span>Distributed/Sold</span><strong><?= e(planting_public_decimal($sold + $distributed)) ?> <?= e($unit) ?></strong></div>
                            </div>

                            <div class="pm-source">
                                <strong>Source:</strong> <?= e((string) (($material['sources'] ?? '') ?: 'KVK Farm Production Unit')) ?>
                            </div>

                            <div class="pm-card-actions">
                                <button class="pm-qr-btn"
                                        type="button"
                                        data-open-product-qr
                                        data-id="<?= e((string)$material['id']) ?>"
                                        data-name="<?= e((string)$material['commodity_name']) ?>"
                                        aria-label="Show QR code for <?= e((string)$material['commodity_name']) ?> product details">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h6v6H3V3Zm12 0h6v6h-6V3ZM3 15h6v6H3v-6Zm12 0h3v3h-3v-3Zm6 0v6h-6m3-3h3"></path>
                                    </svg>
                                    QR Details
                                </button>

                                <button class="pm-order-btn" 
                                        type="button" 
                                        data-open-order 
                                        data-id="<?= e((string)$material['id']) ?>" 
                                        data-name="<?= e((string)$material['commodity_name']) ?>" 
                                        data-rate="<?= e((string)$rate) ?>" 
                                        data-unit="<?= e($unit) ?>" 
                                        data-max="<?= e((string)$available) ?>"
                                        <?= $available <= 0 ? 'disabled' : '' ?>>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path>
                                    </svg>
                                    <?= $available > 0 ? 'Order Now' : 'Out of Stock' ?>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Product QR Modal -->
<div class="pm-modal-overlay" id="productQrModal" aria-hidden="true">
    <div class="pm-modal" role="dialog" aria-modal="true" aria-labelledby="productQrTitle">
        <div class="pm-modal-header">
            <h3 id="productQrTitle">Product QR Code</h3>
            <button class="pm-modal-close" type="button" data-close-product-qr aria-label="Close QR code">&times;</button>
        </div>
        <div class="pm-modal-body">
            <div class="pm-qr-box" id="productQrCode" aria-label="Product details QR code"></div>
            <p class="pm-qr-help">Scan this QR code to view the complete product profile from the planting_material_commodities table, including Tagline, Native Identity, GI Status, Farmer Linkage, Brand, Catchment &amp; Reach, Product Highlights and photos.</p>
            <input class="pm-qr-url" id="productQrUrl" type="text" readonly aria-label="Product details URL">
            <a class="pm-details-link" id="productDetailsLink" href="#" target="_blank" rel="noopener">
                View Full Product Details
                <span aria-hidden="true">↗</span>
            </a>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div class="pm-modal-overlay" id="orderModal">
    <div class="pm-modal">
        <div class="pm-modal-header">
            <h3 id="modalTitle">Order Material</h3>
            <button class="pm-modal-close" type="button" data-close-order>&times;</button>
        </div>
        <form method="POST" class="pm-modal-body">
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" name="commodity_id" id="modalCommodityId">
            <input type="hidden" name="commodity_name" id="modalCommodityName">
            <input type="hidden" name="unit_rate" id="modalUnitRate">

            <div class="pm-summary-box">
                <div>
                    <span>Selected Commodity</span>
                    <strong id="modalItemDisplay">-</strong>
                </div>
                <div style="text-align: right;">
                    <span>Estimated Total</span>
                    <strong id="modalTotalDisplay">Rs. 0.00</strong>
                </div>
            </div>

            <div class="pm-form-group">
                <label for="customer_name">Full Name *</label>
                <input type="text" id="customer_name" name="customer_name" required placeholder="Enter your full name">
            </div>

            <div class="pm-form-group">
                <label for="customer_phone">Phone Number *</label>
                <input type="tel" id="customer_phone" name="customer_phone" required placeholder="Enter active contact number">
            </div>

            <div class="pm-form-group">
                <label for="quantity_ordered">Quantity Required (<span id="modalUnitDisplay">units</span>) *</label>
                <input type="number" id="quantity_ordered" name="quantity_ordered" step="any" min="0.001" required placeholder="Enter quantity">
            </div>

            <div class="pm-form-group">
                <label for="customer_address">Delivery Address / Pick-up Note</label>
                <textarea id="customer_address" name="customer_address" rows="3" placeholder="Enter village, postal code, or special instructions"></textarea>
            </div>

            <button type="submit" class="pm-order-btn">Confirm Order Request</button>
        </form>
    </div>
</div>

<script src="assets/vendor/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('[data-pm-search]');
    const filterButtons = document.querySelectorAll('.pm-filter-btn');
    const cards = document.querySelectorAll('[data-pm-card]');

    let currentFilter = 'all';
    let currentSearch = '';

    const filterCards = () => {
        cards.forEach((card) => {
            const matchesSearch = currentSearch === '' || card.dataset.search.includes(currentSearch);
            const matchesStatus = currentFilter === 'all' || card.dataset.status === currentFilter;
            card.style.display = matchesSearch && matchesStatus ? 'flex' : 'none';
        });
    };

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.trim().toLowerCase();
            filterCards();
        });
    }

    filterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            currentFilter = btn.dataset.filter;
            filterCards();
        });
    });

    document.querySelectorAll('[data-pm-slider]').forEach((card) => {
        const slides = Array.from(card.querySelectorAll('[data-pm-slide]'));
        const dots = Array.from(card.querySelectorAll('[data-pm-dot]'));
        if (slides.length < 2) return;

        let activeIndex = 0;
        const showSlide = (nextIndex) => {
            activeIndex = (nextIndex + slides.length) % slides.length;
            slides.forEach((slide, idx) => slide.classList.toggle('is-active', idx === activeIndex));
            dots.forEach((dot, idx) => dot.classList.toggle('is-active', idx === activeIndex));
        };

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', (e) => {
                e.stopPropagation();
                showSlide(idx);
            });
        });
    });

    // Product QR / Full Details Logic
    const productQrModal = document.getElementById('productQrModal');
    const productQrCode = document.getElementById('productQrCode');
    const productQrTitle = document.getElementById('productQrTitle');
    const productQrUrl = document.getElementById('productQrUrl');
    const productDetailsLink = document.getElementById('productDetailsLink');
    const productQrButtons = document.querySelectorAll('[data-open-product-qr]');
    const productQrCloseButtons = document.querySelectorAll('[data-close-product-qr]');

    const absoluteProductDetailsUrl = (id) => {
        const url = new URL('planting-material-detail.php', window.location.href);
        url.searchParams.set('id', id);
        return url.href;
    };

    const closeProductQr = () => {
        if (!productQrModal) return;
        productQrModal.classList.remove('is-active');
        productQrModal.setAttribute('aria-hidden', 'true');
    };

    productQrButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id || '';
            const name = btn.dataset.name || 'Product';
            const detailsUrl = absoluteProductDetailsUrl(id);

            if (productQrTitle) productQrTitle.textContent = name + ' — QR Details';
            if (productQrUrl) productQrUrl.value = detailsUrl;
            if (productDetailsLink) productDetailsLink.href = detailsUrl;

            if (productQrCode) {
                productQrCode.innerHTML = '';
                if (typeof window.QRCode === 'function') {
                    new QRCode(productQrCode, {
                        text: detailsUrl,
                        width: 220,
                        height: 220,
                        colorDark: '#102a1c',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } else {
                    productQrCode.textContent = 'QR library not available. Use the View Full Product Details button below.';
                }
            }

            if (productQrModal) {
                productQrModal.classList.add('is-active');
                productQrModal.setAttribute('aria-hidden', 'false');
            }
        });
    });

    productQrCloseButtons.forEach((btn) => btn.addEventListener('click', closeProductQr));
    productQrModal?.addEventListener('click', (e) => {
        if (e.target === productQrModal) closeProductQr();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && productQrModal?.classList.contains('is-active')) {
            closeProductQr();
        }
    });

    // Modal & Order Logic
    const modal = document.getElementById('orderModal');
    const closeBtns = document.querySelectorAll('[data-close-order]');
    const openBtns = document.querySelectorAll('[data-open-order]');
    const modalCommodityId = document.getElementById('modalCommodityId');
    const modalCommodityName = document.getElementById('modalCommodityName');
    const modalUnitRate = document.getElementById('modalUnitRate');
    const modalItemDisplay = document.getElementById('modalItemDisplay');
    const modalUnitDisplay = document.getElementById('modalUnitDisplay');
    const modalTotalDisplay = document.getElementById('modalTotalDisplay');
    const quantityInput = document.getElementById('quantity_ordered');

    let currentRate = 0;

    const updateTotal = () => {
        const qty = parseFloat(quantityInput.value) || 0;
        const total = qty * currentRate;
        modalTotalDisplay.textContent = 'Rs. ' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    openBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            const rate = parseFloat(btn.dataset.rate) || 0;
            const unit = btn.dataset.unit;
            const max = parseFloat(btn.dataset.max) || 0;

            currentRate = rate;
            modalCommodityId.value = id;
            modalCommodityName.value = name;
            modalUnitRate.value = rate;
            modalItemDisplay.textContent = name;
            modalUnitDisplay.textContent = unit;
            quantityInput.max = max;
            quantityInput.value = 1;

            updateTotal();
            modal.classList.add('is-active');
        });
    });

    closeBtns.forEach((btn) => {
        btn.addEventListener('click', () => modal.classList.remove('is-active'));
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('is-active');
    });

    quantityInput.addEventListener('input', updateTotal);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>