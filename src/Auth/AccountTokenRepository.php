<?php

declare(strict_types=1);

final class AccountTokenRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(int $userId, string $purpose, int $ttlSeconds): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO account_tokens
                (user_id, purpose, token_hash, expires_at, created_at)
             VALUES
                (:user_id, :purpose, :token_hash, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':purpose' => $purpose,
            ':token_hash' => hash('sha256', $token),
            ':expires_at' => gmdate('c', $now + $ttlSeconds),
            ':created_at' => gmdate('c', $now),
        ]);

        return $token;
    }

    public function consume(string $token, string $purpose): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, expires_at, used_at
             FROM account_tokens
             WHERE token_hash = :token_hash AND purpose = :purpose'
        );
        $stmt->execute([
            ':token_hash' => hash('sha256', $token),
            ':purpose' => $purpose,
        ]);
        $row = $stmt->fetch();
        if ($row === false || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
            throw new InvalidArgumentException('El enlace no es valido o ha caducado.');
        }

        $usedAt = gmdate('c');
        $update = $this->pdo->prepare(
            'UPDATE account_tokens SET used_at = :used_at WHERE id = :id AND used_at IS NULL'
        );
        $update->execute([
            ':used_at' => $usedAt,
            ':id' => $row['id'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new InvalidArgumentException('El enlace ya se ha utilizado.');
        }

        return (int) $row['user_id'];
    }
}
