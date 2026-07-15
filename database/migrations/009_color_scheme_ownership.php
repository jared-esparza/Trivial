<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $columns = static function () use ($pdo, $driver): array {
        if ($driver === 'sqlite') {
            return array_column($pdo->query('PRAGMA table_info(color_schemes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        }

        $stmt = $pdo->prepare(
            'SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute([':table' => 'color_schemes']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    };

    $existing = $columns();
    if (!in_array('kind', $existing, true)) {
        $pdo->exec("ALTER TABLE color_schemes ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'system'");
    }
    if (!in_array('owner_user_id', $existing, true)) {
        $pdo->exec('ALTER TABLE color_schemes ADD COLUMN owner_user_id BIGINT NULL');
    }

    $pdo->exec("UPDATE color_schemes SET kind = 'system', owner_user_id = NULL WHERE owner_user_id IS NULL");
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_color_schemes_owner ON color_schemes (owner_user_id)');
    } else {
        $index = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index_name'
        );
        $index->execute([':table' => 'color_schemes', ':index_name' => 'idx_color_schemes_owner']);
        if ((int) $index->fetchColumn() === 0) {
            $pdo->exec('CREATE INDEX idx_color_schemes_owner ON color_schemes (owner_user_id)');
        }
    }
};
