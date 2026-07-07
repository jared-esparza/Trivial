<?php

declare(strict_types=1);

require_once __DIR__ . '/GameEngine.php';

final class RoomRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createRoom(string $mode, string $answerMode, string $hostName, string $hostColor): array
    {
        if (!in_array($mode, ['local', 'online'], true)) {
            throw new InvalidArgumentException('Modo de sala no valido.');
        }
        if (!in_array($answerMode, ['auto', 'judge'], true)) {
            throw new InvalidArgumentException('Modo de respuesta no valido.');
        }

        $players = [[
            'name' => trim($hostName) !== '' ? trim($hostName) : 'Equipo 1',
            'color' => $hostColor,
        ]];
        $state = [
            'phase' => 'lobby',
            'mode' => $mode,
            'answerMode' => $answerMode,
            'players' => $players,
            'version' => 1,
        ];
        $code = $this->uniqueCode();
        $now = gmdate('c');

        $stmt = $this->pdo->prepare(
            'INSERT INTO rooms
                (code, mode, answer_mode, status, players_json, state_json, version, created_at, updated_at)
             VALUES
                (:code, :mode, :answer_mode, :status, :players_json, :state_json, :version, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':code' => $code,
            ':mode' => $mode,
            ':answer_mode' => $answerMode,
            ':status' => 'lobby',
            ':players_json' => json_encode($players, JSON_THROW_ON_ERROR),
            ':state_json' => json_encode($state, JSON_THROW_ON_ERROR),
            ':version' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $this->getRoom($code);
    }

    public function createLocalRoom(string $answerMode, array $players): array
    {
        if (count($players) < 2 || count($players) > 6) {
            throw new InvalidArgumentException('El modo local necesita entre 2 y 6 equipos.');
        }

        $first = array_shift($players);
        $room = $this->createRoom('local', $answerMode, $first['name'], $first['color']);
        foreach ($players as $player) {
            $this->joinRoom($room['code'], $player['name'], $player['color']);
        }

        return $this->startGame($room['code']);
    }

    public function joinRoom(string $code, string $name, string $color): array
    {
        $room = $this->getRoom($code);
        if ($room['status'] !== 'lobby') {
            throw new InvalidArgumentException('La sala ya ha empezado.');
        }
        $players = $room['players'];
        if (count($players) >= 6) {
            throw new InvalidArgumentException('La sala ya tiene 6 equipos.');
        }

        $players[] = [
            'name' => trim($name) !== '' ? trim($name) : 'Equipo ' . (count($players) + 1),
            'color' => $color,
        ];
        $state = $room['state'];
        $state['players'] = $players;
        $state['version'] = (int) $room['version'] + 1;

        $this->saveRoom($code, 'lobby', $players, $state);

        return $this->getRoom($code);
    }

    public function startGame(string $code): array
    {
        $room = $this->getRoom($code);
        if (count($room['players']) < 2) {
            throw new InvalidArgumentException('Hacen falta al menos 2 equipos para empezar.');
        }
        if ($room['status'] !== 'lobby') {
            return $room;
        }

        $state = GameEngine::newGame($room['players'], $room['mode']);
        $state['answerMode'] = $room['answer_mode'];
        $state['version'] = (int) $room['version'] + 1;
        $this->saveRoom($code, 'playing', $room['players'], $state);

        return $this->getRoom($code);
    }

    public function updateState(string $code, array $state): array
    {
        $room = $this->getRoom($code);
        $state['version'] = (int) $room['version'] + 1;
        $status = $state['phase'] === 'finished' ? 'finished' : 'playing';
        $this->saveRoom($code, $status, $room['players'], $state);

        return $this->getRoom($code);
    }

    public function getRoom(string $code): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rooms WHERE code = :code');
        $stmt->execute([':code' => strtoupper($code)]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Sala no encontrada.');
        }

        return [
            'code' => $row['code'],
            'mode' => $row['mode'],
            'answer_mode' => $row['answer_mode'],
            'status' => $row['status'],
            'players' => json_decode($row['players_json'], true, 512, JSON_THROW_ON_ERROR),
            'state' => json_decode($row['state_json'], true, 512, JSON_THROW_ON_ERROR),
            'version' => (int) $row['version'],
        ];
    }

    private function saveRoom(string $code, string $status, array $players, array $state): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rooms
             SET status = :status,
                 players_json = :players_json,
                 state_json = :state_json,
                 version = :version,
                 updated_at = :updated_at
             WHERE code = :code'
        );
        $stmt->execute([
            ':status' => $status,
            ':players_json' => json_encode($players, JSON_THROW_ON_ERROR),
            ':state_json' => json_encode($state, JSON_THROW_ON_ERROR),
            ':version' => (int) $state['version'],
            ':updated_at' => gmdate('c'),
            ':code' => strtoupper($code),
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rooms WHERE code = :code');
            $stmt->execute([':code' => $code]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $code;
    }
}
