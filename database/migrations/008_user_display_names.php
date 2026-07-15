<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasColumn = static function () use ($pdo, $driver): bool {
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(users)')->fetchAll() as $row) {
                if (($row['name'] ?? null) === 'display_name') {
                    return true;
                }
            }
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([':table' => 'users', ':column' => 'display_name']);

        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN display_name VARCHAR(40) NULL');
    }

    $rows = $pdo->query(
        "SELECT id, email, display_name FROM users
         WHERE deleted_at IS NULL AND (display_name IS NULL OR TRIM(display_name) = '')"
    )->fetchAll();
    $stmt = $pdo->prepare('UPDATE users SET display_name = :display_name, updated_at = :updated_at WHERE id = :id');
    foreach ($rows as $row) {
        $email = (string) ($row['email'] ?? '');
        $fallback = trim((string) strtok($email, '@'));
        if ($fallback === '') {
            $fallback = 'Usuario';
        }
        $fallback = substr(preg_replace('/\s+/', ' ', $fallback) ?? $fallback, 0, 40);
        $stmt->execute([
            ':display_name' => $fallback,
            ':updated_at' => gmdate('c'),
            ':id' => (int) $row['id'],
        ]);
    }
};
