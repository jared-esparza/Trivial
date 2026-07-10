<?php

declare(strict_types=1);

final class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $directory,
    ) {
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedVersions();
        $files = glob(rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            if (isset($applied[$version])) {
                continue;
            }

            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("La migracion {$version} no es ejecutable.");
            }

            $transactional = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
            if ($transactional) {
                $this->pdo->beginTransaction();
            }

            try {
                $migration($this->pdo);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
                );
                $stmt->execute([
                    ':version' => $version,
                    ':applied_at' => gmdate('c'),
                ]);
                if ($transactional) {
                    $this->pdo->commit();
                }
            } catch (Throwable $e) {
                if ($transactional && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(160) PRIMARY KEY,
                applied_at VARCHAR(32) NOT NULL
            )'
        );
    }

    private function appliedVersions(): array
    {
        $rows = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys(array_map('strval', $rows), true);
    }
}
