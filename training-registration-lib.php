<?php
declare(strict_types=1);

function farmer_registration_ensure_tables(?PDO $pdo): void
{
    if (!$pdo) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS farmer_training_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        activity_id INT NOT NULL,
        farmer_name VARCHAR(160) NOT NULL,
        age TINYINT UNSIGNED NOT NULL,
        village VARCHAR(160) NOT NULL,
        district VARCHAR(160) NOT NULL,
        state VARCHAR(160) NOT NULL,
        pincode CHAR(6) NOT NULL,
        aadhaar_no CHAR(12) NOT NULL,
        photo_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_farmer_training (activity_id),
        INDEX idx_farmer_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function farmer_training_programmes(?PDO $pdo): array
{
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("SELECT a.id, a.title, tr.training_name, tr.duration_from, tr.duration_to, tr.training_location
            FROM activities a INNER JOIN training_records tr ON tr.activity_id = a.id
            WHERE a.is_active = 1 AND tr.is_active = 1 ORDER BY tr.duration_from DESC, a.id DESC");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function farmer_training_programme(?PDO $pdo, int $id): ?array
{
    if (!$pdo || $id < 1) return null;
    try {
        $stmt = $pdo->prepare("SELECT a.id, a.title, tr.training_name, tr.duration_from, tr.duration_to, tr.training_location
            FROM activities a INNER JOIN training_records tr ON tr.activity_id = a.id
            WHERE a.id = ? AND a.is_active = 1 AND tr.is_active = 1 LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function farmer_registration_url(int $activityId): string
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $secure ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (str_ends_with($base, '/admin')) $base = substr($base, 0, -6);
    return $scheme . '://' . $host . $base . '/farmer-training-registration.php?programme=' . $activityId;
}

function farmer_mask_aadhaar(string $aadhaar): string
{
    return 'XXXX XXXX ' . substr(preg_replace('/\D+/', '', $aadhaar), -4);
}
