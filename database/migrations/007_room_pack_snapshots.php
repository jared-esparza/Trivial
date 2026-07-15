<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasColumn = static function (string $column) use ($pdo, $driver): bool {
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(rooms)')->fetchAll() as $row) {
                if (($row['name'] ?? null) === $column) return true;
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([':table' => 'rooms', ':column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $columns = [
        'creator_user_id' => 'BIGINT NULL',
        'pack_id' => 'BIGINT NULL',
        'pack_revision_id' => 'BIGINT NULL',
        'pack_snapshot_json' => 'TEXT NULL',
        'controller_token_hash' => 'VARCHAR(64) NULL',
        'started_at' => 'VARCHAR(32) NULL',
        'finished_at' => 'VARCHAR(32) NULL',
        'winner_participant_id' => 'BIGINT NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!$hasColumn($name)) {
            $pdo->exec("ALTER TABLE rooms ADD COLUMN {$name} {$definition}");
        }
    }
};
