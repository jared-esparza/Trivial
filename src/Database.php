<?php

declare(strict_types=1);

final class Database
{
    public static function connect(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . $config['path']);
        } else {
            $host = $config['host'] ?? 'localhost';
            $port = (int) ($config['port'] ?? 3306);
            $dbname = $config['database'] ?? '';
            $charset = $config['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            $pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    public static function createSchema(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $questionId = $driver === 'mysql'
            ? 'id INT AUTO_INCREMENT PRIMARY KEY'
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

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_questions_category ON questions (category)');
    }
}
