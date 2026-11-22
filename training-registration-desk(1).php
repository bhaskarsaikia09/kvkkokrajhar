<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_admin();

// Compatibility entry point: the Training Register is now maintained by the
// single training-register.php module so programme and participant data cannot
// be split across two different registration tables.
header('Location: training-register.php', true, 302);
exit;
