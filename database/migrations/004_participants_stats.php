<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'mysql'
        ? 'BIGINT AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS room_participants (
            id {$id},
            room_code VARCHAR(8) NOT NULL,
            slot INTEGER NOT NULL,
            user_id BIGINT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(16) NOT NULL,
            token_hash VARCHAR(64) NULL,
            created_at VARCHAR(32) NOT NULL,
            UNIQUE (room_code, slot)
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS answer_events (
            id {$id},
            room_code VARCHAR(8) NOT NULL,
            participant_id BIGINT NOT NULL,
            category_slot INTEGER NOT NULL,
            question_id BIGINT NULL,
            correct INTEGER NOT NULL,
            answer_mode VARCHAR(20) NOT NULL,
            sequence_no INTEGER NOT NULL,
            answered_at VARCHAR(32) NOT NULL,
            UNIQUE (room_code, sequence_no)
        )"
    );
};
