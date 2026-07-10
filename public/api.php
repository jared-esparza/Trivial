<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');

$rooms = new RoomRepository(app_pdo());
$questions = new QuestionRepository(app_pdo());
$packRepository = new PackRepository(app_pdo());
$participantTokens = new ParticipantTokenService(app_pdo());
$roomService = new RoomService($rooms, $packRepository, $participantTokens);

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = api_path();
    $body = request_json();

    if (str_starts_with($path, '/auth/') || str_starts_with($path, '/admin/users') || str_starts_with($path, '/packs')) {
        $request = new ApiRequest($method, $path, $body, $_GET, $_COOKIE, request_headers());
        write_api_response(app_auth_router()->dispatch($request));
    }

    if ($method === 'POST' && $path === '/rooms') {
        $mode = (string) ($body['mode'] ?? 'online');
        $user = current_optional_user();
        $packId = optional_positive_int($body['packId'] ?? null);
        $colorSchemeId = optional_positive_int($body['colorSchemeId'] ?? null);
        if ($mode === 'local') {
            $room = $roomService->createLocal(
                $user,
                (string) ($body['answerMode'] ?? 'judge'),
                normalize_players($body['players'] ?? []),
                $packId,
                $colorSchemeId
            );
        } else {
            $room = $roomService->createOnline(
                $user,
                (string) ($body['teamName'] ?? 'Equipo 1'),
                (string) ($body['color'] ?? '#2563eb'),
                $packId,
                $colorSchemeId
            );
        }
        json_response(room_response($room));
    }

    if ($method === 'POST' && preg_match('#^/rooms/([A-Z0-9]{6})/join$#', $path, $m)) {
        $room = $roomService->joinOnline(
            current_optional_user(),
            $m[1],
            (string) ($body['teamName'] ?? 'Equipo'),
            (string) ($body['color'] ?? '#dc2626')
        );
        json_response(room_response($room));
    }

    if ($method === 'GET' && preg_match('#^/rooms/([A-Z0-9]{6})/state$#', $path, $m)) {
        $room = $rooms->getRoom($m[1]);
        json_response(['room' => sanitize_room($room)]);
    }

    if ($method === 'GET' && preg_match('#^/rooms/([A-Z0-9]{6})/statistics$#', $path, $m)) {
        $room = $rooms->getRoom($m[1]);
        try {
            $participantTokens->authorize($room, (string) (request_headers()['x-participant-token'] ?? ''));
        } catch (RuntimeException) {
            throw new ApiException(403, 'PARTICIPANT_TOKEN_INVALID', 'No puedes consultar esta partida.');
        }
        $stats = new StatisticsService(app_pdo(), new AnswerEventRepository(app_pdo()));
        json_response(['statistics' => $stats->roomReport($room['code'])]);
    }

    if ($method === 'GET' && $path === '/me/games') {
        $user = current_required_user();
        $stats = new StatisticsService(app_pdo(), new AnswerEventRepository(app_pdo()));
        json_response(['games' => $stats->historyForUser((int) $user['id'])]);
    }

    if ($method === 'GET' && preg_match('#^/me/games/([A-Z0-9]{6})$#', $path, $m)) {
        $user = current_required_user();
        $stats = new StatisticsService(app_pdo(), new AnswerEventRepository(app_pdo()));
        try {
            $report = $stats->roomReportForUser($m[1], (int) $user['id']);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'HISTORY_FORBIDDEN') {
                throw new ApiException(403, 'HISTORY_FORBIDDEN', 'No puedes consultar esta partida.');
            }
            throw $e;
        }
        json_response(['statistics' => $report]);
    }

    if ($method === 'POST' && preg_match('#^/rooms/([A-Z0-9]{6})/actions$#', $path, $m)) {
        $room = $rooms->getRoom($m[1]);
        $expectedVersion = filter_var($body['expectedVersion'] ?? null, FILTER_VALIDATE_INT);
        if ($expectedVersion === false || $expectedVersion !== $room['version']) {
            throw new ApiException(409, 'ROOM_VERSION_CONFLICT', 'La sala ha cambiado; vuelve a sincronizar.');
        }
        try {
            $participant = $participantTokens->authorize(
                $room,
                (string) (request_headers()['x-participant-token'] ?? '')
            );
        } catch (RuntimeException) {
            throw new ApiException(403, 'PARTICIPANT_TOKEN_INVALID', 'No puedes actuar por este equipo.');
        }
        if ($room['mode'] === 'online') {
            $body['playerId'] = $participant['slot'];
            if (($body['action'] ?? '') === 'start' && $participant['slot'] !== 0) {
                throw new ApiException(403, 'HOST_REQUIRED', 'Solo el anfitrion puede iniciar la partida.');
            }
        }
        $playerId = (int) ($body['playerId'] ?? 0);
        $actingParticipant = ($participant['controller'] ?? false)
            ? $participantTokens->participantForSlot($room['code'], $playerId)
            : $participant;
        $pdo = app_pdo();
        $pdo->beginTransaction();
        try {
            $previousState = $room['state'];
            $room = apply_action($room, $body, $rooms, $questions, $expectedVersion);
            if (($body['action'] ?? '') === 'answer') {
                $question = $previousState['currentQuestion'] ?? [];
                $slot = $question['categorySlot'] ?? category_slot_for_room($room, (string) ($question['category'] ?? ''));
                (new AnswerEventRepository($pdo))->record(
                    $room['code'],
                    (int) $actingParticipant['id'],
                    (int) $slot,
                    isset($question['id']) ? (int) $question['id'] : null,
                    ($room['state']['lastResult']['type'] ?? '') === 'correct',
                    (string) ($room['answer_mode'] ?? 'auto')
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        json_response(['room' => sanitize_room($room)]);
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

function apply_action(
    array $room,
    array $body,
    RoomRepository $rooms,
    QuestionRepository $questions,
    ?int $expectedVersion = null,
): array
{
    $action = (string) ($body['action'] ?? '');
    if ($action === 'start') {
        return $rooms->startGame($room['code'], $expectedVersion);
    }

    $state = $room['state'];
    $playerId = (int) ($body['playerId'] ?? $state['currentPlayer'] ?? 0);

    if ($action === 'roll') {
        $state = GameEngine::roll($state, $playerId);
    } elseif ($action === 'move') {
        $state = GameEngine::move($state, $playerId, (string) ($body['destination'] ?? ''));
        if (($state['phase'] ?? '') === 'question') {
            $category = (string) ($state['pendingSpace']['category'] ?? '');
            if ($room['pack_revision_id'] !== null && is_array($room['pack_snapshot'])) {
                $slot = null;
                foreach ($room['pack_snapshot'] as $snapshotCategory) {
                    if (($snapshotCategory['slug'] ?? null) === $category) {
                        $slot = (int) $snapshotCategory['slot'];
                        break;
                    }
                }
                if ($slot === null) {
                    throw new RuntimeException('Categoria de sala no encontrada.');
                }
                $question = $questions->randomByRevisionSlot($room['pack_revision_id'], $slot);
            } else {
                $question = $questions->randomByCategory($category);
            }
            $state = GameEngine::attachQuestion($state, $question);
        }
    } elseif ($action === 'answer') {
        $answer = ($state['answerMode'] ?? 'auto') === 'judge'
            ? (bool) ($body['correct'] ?? false)
            : (int) ($body['option'] ?? -1);
        $state = GameEngine::answer($state, $playerId, $answer);
    } else {
        throw new InvalidArgumentException('Accion no valida.');
    }

    return $rooms->updateState($room['code'], $state, $expectedVersion);
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
        'categories' => $room['pack_snapshot'] ?? GameEngine::categories(),
        'spaces' => GameEngine::boardSpaces(),
    ];
}

function room_response(array $room): array
{
    $response = ['room' => sanitize_room($room)];
    if (isset($room['participant_token'])) {
        $response['participantToken'] = $room['participant_token'];
    }
    return $response;
}

function current_optional_user(): ?array
{
    $token = (string) ($_COOKIE['rq_session'] ?? '');
    return $token === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($token);
}

function current_required_user(): array
{
    try {
        return Authorization::requireVerifiedUser(current_optional_user());
    } catch (RuntimeException $e) {
        $status = $e->getMessage() === 'AUTH_REQUIRED' ? 401 : 403;
        throw new ApiException($status, $e->getMessage(), 'Necesitas una cuenta verificada.');
    }
}

function category_slot_for_room(array $room, string $slug): int
{
    foreach (($room['pack_snapshot'] ?? []) as $category) {
        if (($category['slug'] ?? null) === $slug) {
            return (int) $category['slot'];
        }
    }
    foreach (GameEngine::categories() as $slot => $category) {
        if ($category['slug'] === $slug) {
            return $slot;
        }
    }
    throw new RuntimeException('Categoria de sala no encontrada.');
}

function optional_positive_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < 1) {
        throw new InvalidArgumentException('Identificador no valido.');
    }
    return $number;
}
