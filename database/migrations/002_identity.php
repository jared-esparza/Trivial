<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql'
        ? 'BIGINT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id {$id},
            email VARCHAR(254) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            email_verified_at VARCHAR(32) NULL,
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL,
            deleted_at VARCHAR(32) NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_sessions (
            id {$id},
            user_id BIGINT NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            csrf_token VARCHAR(128) NOT NULL,
            last_activity_at VARCHAR(32) NOT NULL,
            expires_at VARCHAR(32) NOT NULL,
            created_at VARCHAR(32) NOT NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS account_tokens (
            id {$id},
            user_id BIGINT NOT NULL,
            purpose VARCHAR(30) NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE,
            expires_at VARCHAR(32) NOT NULL,
            used_at VARCHAR(32) NULL,
            created_at VARCHAR(32) NOT NULL
        )"
    );
};
