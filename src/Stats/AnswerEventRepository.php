<?php

declare(strict_types=1);

final class AnswerEventRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        string $roomCode,
        int $participantId,
        int $categorySlot,
        ?int $questionId,
        bool $correct,
        string $answerMode,
    ): array {
        $sequenceStmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM answer_events WHERE room_code = :room_code'
        );
        $sequenceStmt->execute([':room_code' => strtoupper($roomCode)]);
        $sequence = (int) $sequenceStmt->fetchColumn();
        $stmt = $this->pdo->prepare(
            'INSERT INTO answer_events
                (room_code, participant_id, category_slot, question_id, correct, answer_mode, sequence_no, answered_at)
             VALUES
                (:room_code, :participant_id, :category_slot, :question_id, :correct, :answer_mode, :sequence_no, :answered_at)'
        );
        $stmt->execute([
            ':room_code' => strtoupper($roomCode),
            ':participant_id' => $participantId,
            ':category_slot' => $categorySlot,
            ':question_id' => $questionId,
            ':correct' => $correct ? 1 : 0,
            ':answer_mode' => $answerMode,
            ':sequence_no' => $sequence,
            ':answered_at' => gmdate('c'),
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'sequence_no' => $sequence];
    }

    public function forRoom(string $roomCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM answer_events WHERE room_code = :room_code ORDER BY sequence_no');
        $stmt->execute([':room_code' => strtoupper($roomCode)]);
        return $stmt->fetchAll();
    }
}
