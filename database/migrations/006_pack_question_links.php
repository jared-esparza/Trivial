<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasColumn = static function (string $table, string $column) use ($pdo, $driver): bool {
        if ($driver === 'sqlite') {
            $rows = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
            foreach ($rows as $row) {
                if (($row['name'] ?? null) === $column) {
                    return true;
                }
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('questions', 'pack_revision_id')) {
        $pdo->exec('ALTER TABLE questions ADD COLUMN pack_revision_id BIGINT NULL');
    }
    if (!$hasColumn('questions', 'pack_category_id')) {
        $pdo->exec('ALTER TABLE questions ADD COLUMN pack_category_id BIGINT NULL');
    }
};
