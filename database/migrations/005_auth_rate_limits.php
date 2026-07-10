<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql'
        ? 'BIGINT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_attempts (
            id {$id},
            action VARCHAR(40) NOT NULL,
            identifier_hash VARCHAR(64) NOT NULL,
            attempts INTEGER NOT NULL,
            window_started_at VARCHAR(32) NOT NULL,
            blocked_until VARCHAR(32) NULL,
            UNIQUE (action, identifier_hash)
        )"
    );
};
