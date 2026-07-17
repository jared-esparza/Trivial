<?php

declare(strict_types=1);

final class SessionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId): array
    {
        $token = self::randomToken();
        $csrfToken = self::randomToken();
        $now = time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_sessions
                (user_id, token_hash, csrf_token, last_activity_at, expires_at, created_at)
             VALUES
                (:user_id, :token_hash, :csrf_token, :last_activity_at, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => hash('sha256', $token),
            ':csrf_token' => $csrfToken,
            ':last_activity_at' => gmdate('c', $now),
            ':expires_at' => gmdate('c', $now + 30 * 24 * 60 * 60),
            ':created_at' => gmdate('c', $now),
        ]);

        return [
            'token' => $token,
            'csrfToken' => $csrfToken,
            'expiresAt' => gmdate('c', $now + 30 * 24 * 60 * 60),
        ];
    }

    public function findUserByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, s.id AS session_id, s.csrf_token, s.expires_at AS session_expires_at
             FROM auth_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :token_hash
               AND s.expires_at >= :now
               AND u.status = :status
               AND u.deleted_at IS NULL'
        );
        $stmt->execute([
            ':token_hash' => hash('sha256', $token),
            ':now' => gmdate('c'),
            ':status' => 'active',
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['session_id'] = (int) $row['session_id'];

        return $row;
    }

    public function deleteByToken(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM auth_sessions WHERE token_hash = :token_hash');
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
    }

    public function deleteAllForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM auth_sessions WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    }

    public function deleteAllForUserExcept(int $userId, int $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM auth_sessions WHERE user_id = :user_id AND id <> :session_id'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':session_id' => $sessionId,
        ]);
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
