<?php

declare(strict_types=1);

final class CleanupService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function purgeAnonymousFinishedRooms(int $retentionDays, ?int $now = null): int
    {
        if ($retentionDays < 1) {
            throw new InvalidArgumentException('La retencion debe ser de al menos un dia.');
        }

        $cutoff = gmdate('c', ($now ?? time()) - ($retentionDays * 86400));
        $stmt = $this->pdo->prepare(
            "SELECT r.code
             FROM rooms r
             WHERE r.status = 'finished'
               AND r.finished_at IS NOT NULL
               AND r.finished_at < :cutoff
               AND r.creator_user_id IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM room_participants rp
                   WHERE rp.room_code = r.code AND rp.user_id IS NOT NULL
               )"
        );
        $stmt->execute([':cutoff' => $cutoff]);
        $codes = array_column($stmt->fetchAll(), 'code');
        if ($codes === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $deleteEvents = $this->pdo->prepare('DELETE FROM answer_events WHERE room_code = :code');
            $deleteParticipants = $this->pdo->prepare('DELETE FROM room_participants WHERE room_code = :code');
            $deleteRoom = $this->pdo->prepare('DELETE FROM rooms WHERE code = :code');
            foreach ($codes as $code) {
                $deleteEvents->execute([':code' => $code]);
                $deleteParticipants->execute([':code' => $code]);
                $deleteRoom->execute([':code' => $code]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return count($codes);
    }
}
