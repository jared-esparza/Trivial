<?php

declare(strict_types=1);

final class StatisticsService
{
    public function __construct(
        private ?PDO $pdo = null,
        private ?AnswerEventRepository $events = null,
    ) {
    }

    public static function summarize(array $participants, array $events): array
    {
        $eventsByParticipant = [];
        foreach ($events as $event) {
            $eventsByParticipant[(int) $event['participant_id']][] = $event;
        }

        $summaries = [];
        foreach ($participants as $participant) {
            $participantEvents = $eventsByParticipant[(int) $participant['id']] ?? [];
            usort($participantEvents, fn (array $a, array $b): int => (int) $a['sequence_no'] <=> (int) $b['sequence_no']);
            $correct = 0;
            $currentStreak = 0;
            $longestStreak = 0;
            $categories = [];
            foreach ($participantEvents as $event) {
                $isCorrect = (bool) $event['correct'];
                $slot = (int) $event['category_slot'];
                $categories[$slot] ??= ['answers' => 0, 'correct' => 0];
                $categories[$slot]['answers']++;
                if ($isCorrect) {
                    $correct++;
                    $categories[$slot]['correct']++;
                    $currentStreak++;
                    $longestStreak = max($longestStreak, $currentStreak);
                } else {
                    $currentStreak = 0;
                }
            }
            foreach ($categories as &$category) {
                $category['incorrect'] = $category['answers'] - $category['correct'];
                $category['accuracy'] = self::percentage($category['correct'], $category['answers']);
            }
            unset($category);
            ksort($categories);
            $answers = count($participantEvents);
            $summaries[] = [
                'participantId' => (int) $participant['id'],
                'slot' => (int) $participant['slot'],
                'name' => $participant['name'],
                'color' => $participant['color'],
                'answers' => $answers,
                'correct' => $correct,
                'incorrect' => $answers - $correct,
                'accuracy' => self::percentage($correct, $answers),
                'longestStreak' => $longestStreak,
                'categories' => $categories,
            ];
        }

        return $summaries;
    }

    private static function percentage(int $correct, int $answers): float
    {
        return $answers === 0 ? 0.0 : round(($correct / $answers) * 100, 2);
    }

    public function roomReport(string $roomCode): array
    {
        $pdo = $this->pdo ?? throw new LogicException('StatisticsService necesita PDO para consultar informes.');
        $events = $this->events ?? new AnswerEventRepository($pdo);
        $roomStmt = $pdo->prepare('SELECT * FROM rooms WHERE code = :code');
        $roomStmt->execute([':code' => strtoupper($roomCode)]);
        $room = $roomStmt->fetch();
        if ($room === false) {
            throw new RuntimeException('ROOM_NOT_FOUND');
        }
        $participantStmt = $pdo->prepare('SELECT * FROM room_participants WHERE room_code = :code ORDER BY slot');
        $participantStmt->execute([':code' => strtoupper($roomCode)]);
        $participants = $participantStmt->fetchAll();
        $state = json_decode($room['state_json'], true, 512, JSON_THROW_ON_ERROR);
        $categories = $room['pack_snapshot_json'] === null
            ? GameEngine::categories()
            : json_decode($room['pack_snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        $duration = null;
        if ($room['started_at'] !== null && $room['finished_at'] !== null) {
            $duration = max(0, strtotime($room['finished_at']) - strtotime($room['started_at']));
        }

        return [
            'code' => $room['code'],
            'status' => $room['status'],
            'winnerSlot' => $state['winner'] ?? null,
            'durationSeconds' => $duration,
            'categories' => $categories,
            'teams' => self::summarize($participants, $events->forRoom($roomCode)),
        ];
    }

    public function historyForUser(int $userId): array
    {
        $pdo = $this->pdo ?? throw new LogicException('StatisticsService necesita PDO para consultar historial.');
        $stmt = $pdo->prepare(
            'SELECT DISTINCT r.code, r.mode, r.status, r.created_at, r.started_at, r.finished_at, r.state_json
             FROM rooms r
             LEFT JOIN room_participants p ON p.room_code = r.code
             WHERE r.creator_user_id = :creator_user_id OR p.user_id = :participant_user_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([':creator_user_id' => $userId, ':participant_user_id' => $userId]);
        return array_map(static function (array $row): array {
            $state = json_decode($row['state_json'], true, 512, JSON_THROW_ON_ERROR);
            return [
                'code' => $row['code'],
                'mode' => $row['mode'],
                'status' => $row['status'],
                'createdAt' => $row['created_at'],
                'finishedAt' => $row['finished_at'],
                'winnerSlot' => $state['winner'] ?? null,
            ];
        }, $stmt->fetchAll());
    }

    public function roomReportForUser(string $roomCode, int $userId): array
    {
        $pdo = $this->pdo ?? throw new LogicException('StatisticsService necesita PDO para consultar historial.');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM rooms r
             LEFT JOIN room_participants p ON p.room_code = r.code AND p.user_id = :participant_user_id
             WHERE r.code = :code
               AND (r.creator_user_id = :creator_user_id OR p.user_id IS NOT NULL)'
        );
        $stmt->execute([
            ':code' => strtoupper($roomCode),
            ':creator_user_id' => $userId,
            ':participant_user_id' => $userId,
        ]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException('HISTORY_FORBIDDEN');
        }

        return $this->roomReport($roomCode);
    }
}
