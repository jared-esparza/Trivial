<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$options = getopt('', ['days::']);
$days = (int) ($options['days'] ?? (app_config()['anonymous_room_retention_days'] ?? 30));
if ($days < 1) {
    fwrite(STDERR, "Uso: php bin/cleanup.php [--days=30]\n");
    exit(1);
}

$deleted = (new CleanupService(app_pdo()))->purgeAnonymousFinishedRooms($days);
fwrite(STDOUT, "Partidas anonimas eliminadas: {$deleted}\n");
