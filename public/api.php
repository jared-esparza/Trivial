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

    if (str_starts_with($path, '/auth/') || str_starts_with($path, '/admin/users')) {
        $request = new ApiRequest($method, $path, $body, $_GET, $_COOKIE, request_headers());
        write_api_response(app_auth_router()->dispatch($request));
    }

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
        require_admin_session(false);
        json_response(['questions' => $questions->all(), 'categories' => GameEngine::categories()]);
    }

    if ($method === 'POST' && $path === '/admin/questions') {
        require_admin_session(true);
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
    if ($e instanceof ApiException) {
        json_response(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->status);
    }
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

function request_headers(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function write_api_response(ApiResponse $response): never
{
    foreach ($response->cookies as $name => $cookie) {
        $value = (string) ($cookie['value'] ?? '');
        $options = $cookie;
        unset($options['value']);
        setcookie((string) $name, $value, $options);
    }

    json_response($response->payload, $response->status);
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

function require_admin_session(bool $requireCsrf): array
{
    $token = (string) ($_COOKIE['rq_session'] ?? '');
    $user = $token === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($token);
    try {
        $admin = Authorization::requireAdmin($user);
    } catch (RuntimeException $e) {
        $status = $e->getMessage() === 'AUTH_REQUIRED' ? 401 : 403;
        throw new ApiException($status, $e->getMessage(), 'No tienes acceso a esta operacion.');
    }
    if ($requireCsrf) {
        $provided = request_headers()['x-csrf-token'] ?? '';
        if ($provided === '' || !hash_equals((string) $admin['csrf_token'], (string) $provided)) {
            throw new ApiException(403, 'CSRF_INVALID', 'Token CSRF no valido.');
        }
    }

    return $admin;
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
