<?php

declare(strict_types=1);

final class AuthRateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assertAllowed(string $action, string $identifier): void
    {
        $row = $this->find($action, $identifier);
        if ($row !== null && $row['blocked_until'] !== null && strtotime((string) $row['blocked_until']) > time()) {
            throw new RuntimeException('TOO_MANY_ATTEMPTS');
        }
    }

    public function registerFailure(
        string $action,
        string $identifier,
        int $limit,
        int $windowSeconds,
    ): void {
        $now = time();
        $hash = $this->identifierHash($identifier);
        $this->pdo->beginTransaction();
        try {
            $row = $this->findByHash($action, $hash);
            if ($row === null) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO auth_attempts
                        (action, identifier_hash, attempts, window_started_at, blocked_until)
                     VALUES (:action, :identifier_hash, 1, :window_started_at, NULL)'
                );
                $stmt->execute([
                    ':action' => $action,
                    ':identifier_hash' => $hash,
                    ':window_started_at' => gmdate('c', $now),
                ]);
                $this->pdo->commit();
                return;
            }

            $windowStart = strtotime((string) $row['window_started_at']);
            $attempts = $windowStart + $windowSeconds <= $now ? 1 : (int) $row['attempts'] + 1;
            $startedAt = $windowStart + $windowSeconds <= $now ? gmdate('c', $now) : $row['window_started_at'];
            $blockedUntil = $attempts >= $limit ? gmdate('c', $now + $windowSeconds) : null;
            $stmt = $this->pdo->prepare(
                'UPDATE auth_attempts
                 SET attempts = :attempts, window_started_at = :window_started_at, blocked_until = :blocked_until
                 WHERE id = :id'
            );
            $stmt->execute([
                ':attempts' => $attempts,
                ':window_started_at' => $startedAt,
                ':blocked_until' => $blockedUntil,
                ':id' => $row['id'],
            ]);
            $this->pdo->commit();
            if ($blockedUntil !== null) {
                throw new RuntimeException('TOO_MANY_ATTEMPTS');
            }
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function clear(string $action, string $identifier): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM auth_attempts WHERE action = :action AND identifier_hash = :identifier_hash'
        );
        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $this->identifierHash($identifier),
        ]);
    }

    private function find(string $action, string $identifier): ?array
    {
        return $this->findByHash($action, $this->identifierHash($identifier));
    }

    private function findByHash(string $action, string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM auth_attempts WHERE action = :action AND identifier_hash = :identifier_hash'
        );
        $stmt->execute([
            ':action' => $action,
            ':identifier_hash' => $hash,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private function identifierHash(string $identifier): string
    {
        return hash('sha256', strtolower(trim($identifier)));
    }
}
