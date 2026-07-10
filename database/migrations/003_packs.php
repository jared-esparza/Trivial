<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql'
        ? 'BIGINT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS question_packs (
            id {$id},
            owner_user_id BIGINT NULL,
            name VARCHAR(120) NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT 'user',
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            current_revision_id BIGINT NULL,
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL,
            deleted_at VARCHAR(32) NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pack_revisions (
            id {$id},
            pack_id BIGINT NOT NULL,
            revision_number INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at VARCHAR(32) NOT NULL,
            activated_at VARCHAR(32) NULL,
            UNIQUE (pack_id, revision_number)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pack_categories (
            id {$id},
            revision_id BIGINT NOT NULL,
            slot INTEGER NOT NULL,
            category_key VARCHAR(60) NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(16) NOT NULL,
            UNIQUE (revision_id, slot),
            UNIQUE (revision_id, category_key)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS color_schemes (
            id {$id},
            name VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL,
            deleted_at VARCHAR(32) NULL
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS color_scheme_slots (
            id {$id},
            color_scheme_id BIGINT NOT NULL,
            slot INTEGER NOT NULL,
            color VARCHAR(16) NOT NULL,
            UNIQUE (color_scheme_id, slot)
        )"
    );
};
