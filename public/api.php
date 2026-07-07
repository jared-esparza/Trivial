<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

$rooms = new RoomRepository(app_pdo());
$questions = new QuestionRepository(app_pdo());

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = api_path();
    $body = request_json();

    if ($method === 'POST' && $path === '/rooms') {
        $mode = (string) ($body['mode'] ?? 'online');
        if ($mode === 'local') {
            $room = $rooms->createLocalRoom((string) ($body['answerMode'] ?? 'judge'), normalize_players($body['players'] ?? []));
        } else {
            $room = $rooms->createRoom('online', 'auto', (string) ($body['teamName'] ?? 'Equipo 1'), (string) ($body['color'] ?? '#2563eb'));
        }
        json_response(['room' => sanitize_room($room)]);
    }

    if ($method === 'POST' && preg_match('#^/rooms/([A-Z0-9]{6})/join$#', $path, $m)) {
        $room = $rooms->joinRoom($m[1], (string) ($body['teamName'] ?? 'Equipo'), (string) ($body['color'] ?? '#dc2626'));
        json_response(['room' => sanitize_room($room)]);
    }

    if ($method === 'GET' && preg_match('#^/rooms/([A-Z0-9]{6})/state$#', $path, $m)) {
        $room = $rooms->getRoom($m[1]);
        json_response(['room' => sanitize_room($room)]);
    }

    if ($method === 'POST' && preg_match('#^/rooms/([A-Z0-9]{6})/actions$#', $path, $m)) {
        $room = $rooms->getRoom($m[1]);
        $room = apply_action($room, $body, $rooms, $questions);
        json_response(['room' => sanitize_room($room)]);
    }

    if ($method === 'GET' && $path === '/admin/questions') {
        require_admin(scalar_string($_GET['admin_key'] ?? ''));
        json_response(['questions' => $questions->all(), 'categories' => GameEngine::categories()]);
    }

    if ($method === 'POST' && $path === '/admin/questions') {
        require_admin(scalar_string($body['adminKey'] ?? ''));
        $rows = isset($body['csv'])
            ? QuestionImporter::fromCsv(scalar_string($body['csv']))
            : normalize_questions($body['questions'] ?? []);
        $count = !empty($body['replace'])
            ? $questions->replaceAll($rows)
            : import_append($questions, $rows);
        json_response(['imported' => $count, 'questions' => $questions->all()]);
    }

    json_response(['error' => 'Ruta no encontrada.'], 404);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], $e instanceof InvalidArgumentException ? 422 : 500);
}

function api_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== '' && str_starts_with($uri, $script)) {
        $uri = substr($uri, strlen($script)) ?: '/';
    }
    $uri = preg_replace('#^/api#', '', $uri) ?: '/';

    return '/' . trim($uri, '/');
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('JSON no valido.');
    }

    return $data;
}

function scalar_string(mixed $value): string
{
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException('Se esperaba un valor de texto.');
    }

    return (string) $value;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function normalize_players(array $players): array
{
    $colors = ['#2563eb', '#dc2626', '#16a34a', '#ca8a04', '#9333ea', '#0891b2'];
    $normalized = [];
    foreach (array_values($players) as $index => $player) {
        $normalized[] = [
            'name' => (string) ($player['name'] ?? 'Equipo ' . ($index + 1)),
            'color' => (string) ($player['color'] ?? $colors[$index]),
        ];
    }

    return $normalized;
}

function normalize_questions(array $questions): array
{
    return array_map(fn (array $row): array => QuestionImporter::normalizeRow($row), $questions);
}

function import_append(QuestionRepository $repo, array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        $repo->insert($row);
        $count++;
    }

    return $count;
}

function require_admin(string $key): void
{
    $config = app_config();
    if (!hash_equals((string) $config['admin_key'], $key)) {
        throw new InvalidArgumentException('Clave admin incorrecta.');
    }
}

function apply_action(array $room, array $body, RoomRepository $rooms, QuestionRepository $questions): array
{
    $action = (string) ($body['action'] ?? '');
    if ($action === 'start') {
        return $rooms->startGame($room['code']);
    }

    $state = $room['state'];
    $playerId = (int) ($body['playerId'] ?? $state['currentPlayer'] ?? 0);

    if ($action === 'roll') {
        $state = GameEngine::roll($state, $playerId);
    } elseif ($action === 'move') {
        $state = GameEngine::move($state, $playerId, (string) ($body['destination'] ?? ''));
        if (($state['phase'] ?? '') === 'question') {
            $category = (string) ($state['pendingSpace']['category'] ?? '');
            $state = GameEngine::attachQuestion($state, $questions->randomByCategory($category));
        }
    } elseif ($action === 'answer') {
        $answer = ($state['answerMode'] ?? 'auto') === 'judge'
            ? (bool) ($body['correct'] ?? false)
            : (int) ($body['option'] ?? -1);
        $state = GameEngine::answer($state, $playerId, $answer);
    } else {
        throw new InvalidArgumentException('Accion no valida.');
    }

    return $rooms->updateState($room['code'], $state);
}

function sanitize_room(array $room): array
{
    $state = $room['state'];
    $showCorrect = ($state['answerMode'] ?? $room['answer_mode']) === 'judge';
    if (!$showCorrect && isset($state['currentQuestion']['correct'])) {
        unset($state['currentQuestion']['correct']);
    }

    return [
        'code' => $room['code'],
        'mode' => $room['mode'],
        'answerMode' => $room['answer_mode'],
        'status' => $room['status'],
        'version' => $room['version'],
        'state' => $state,
        'categories' => GameEngine::categories(),
        'spaces' => GameEngine::boardSpaces(),
    ];
}
