<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $questionId = $driver === 'mysql'
        ? 'id BIGINT AUTO_INCREMENT PRIMARY KEY'
        : 'id INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rooms (
            code VARCHAR(8) PRIMARY KEY,
            mode VARCHAR(20) NOT NULL,
            answer_mode VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            players_json TEXT NOT NULL,
            state_json TEXT NOT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            created_at VARCHAR(32) NOT NULL,
            updated_at VARCHAR(32) NOT NULL
        )'
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS questions (
            {$questionId},
            category VARCHAR(40) NOT NULL,
            question TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct INTEGER NOT NULL,
            created_at VARCHAR(32) NOT NULL
        )"
    );
};
