<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/training_registration.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_admin();
training_registration_ensure_tables();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id < 1) {
    http_response_code(404);
    exit('Photo not found.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT photo_path, photo_mime FROM training_farmer_registrations WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['photo_path'])) {
    http_response_code(404);
    exit('Photo not found.');
}

$root = realpath(training_photo_storage_root());
$path = realpath(__DIR__ . '/' . ltrim((string) $row['photo_path'], '/\\'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('Photo not found.');
}

$mime = (string) ($row['photo_mime'] ?? 'image/jpeg');
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="participant-photo.' . ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg')) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
