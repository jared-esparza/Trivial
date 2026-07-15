<?php

declare(strict_types=1);

final class ParticipantTokenService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function issue(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return ['token' => $token, 'hash' => hash('sha256', $token)];
    }

    public function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function register(
        string $roomCode,
        int $slot,
        ?int $userId,
        string $name,
        string $color,
        ?string $tokenHash,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_participants (room_code, slot, user_id, name, color, token_hash, created_at)
             VALUES (:room_code, :slot, :user_id, :name, :color, :token_hash, :created_at)'
        );
        $stmt->execute([
            ':room_code' => strtoupper($roomCode),
            ':slot' => $slot,
            ':user_id' => $userId,
            ':name' => $name,
            ':color' => $color,
            ':token_hash' => $tokenHash,
            ':created_at' => gmdate('c'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function authorize(array $room, string $token): array
    {
        if ($token === '') {
            throw new RuntimeException('PARTICIPANT_TOKEN_REQUIRED');
        }
        $hash = hash('sha256', $token);
        if (($room['controller_token_hash'] ?? null) !== null && hash_equals((string) $room['controller_token_hash'], $hash)) {
            return ['controller' => true, 'slot' => null, 'token_hash' => $hash];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM room_participants WHERE room_code = :room_code AND token_hash = :token_hash'
        );
        $stmt->execute([':room_code' => strtoupper($room['code']), ':token_hash' => $hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('PARTICIPANT_TOKEN_INVALID');
        }
        $row['id'] = (int) $row['id'];
        $row['slot'] = (int) $row['slot'];
        $row['user_id'] = $row['user_id'] === null ? null : (int) $row['user_id'];
        $row['controller'] = false;
        return $row;
    }

    public function participantForSlot(string $roomCode, int $slot): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM room_participants WHERE room_code = :room_code AND slot = :slot'
        );
        $stmt->execute([':room_code' => strtoupper($roomCode), ':slot' => $slot]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('PARTICIPANT_NOT_FOUND');
        }
        $row['id'] = (int) $row['id'];
        $row['slot'] = (int) $row['slot'];
        return $row;
    }
}
