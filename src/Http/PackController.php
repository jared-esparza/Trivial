<?php

declare(strict_types=1);

final class PackController
{
    private const COOKIE_NAME = 'rq_session';

    public function __construct(
        private PackService $service,
        private PackRepository $packs,
        private SessionRepository $sessions,
    ) {
    }

    public function registerRoutes(ApiRouter $router): void
    {
        $router->add('GET', '/packs', fn (ApiRequest $request): ApiResponse => $this->index($request));
        $router->add('GET', '/packs/colors', fn (ApiRequest $request): ApiResponse => $this->colorSchemes($request));
        $router->add('POST', '/packs/create', fn (ApiRequest $request): ApiResponse => $this->create($request));
        $router->add('POST', '/packs/import', fn (ApiRequest $request): ApiResponse => $this->import($request));
        $router->add('POST', '/packs/categories', fn (ApiRequest $request): ApiResponse => $this->categories($request));
        $router->add('POST', '/packs/questions', fn (ApiRequest $request): ApiResponse => $this->question($request));
        $router->add('POST', '/packs/activate', fn (ApiRequest $request): ApiResponse => $this->activate($request));
        $router->add('POST', '/packs/edit', fn (ApiRequest $request): ApiResponse => $this->edit($request));
        $router->add('GET', '/packs/export', fn (ApiRequest $request): ApiResponse => $this->export($request));
        $router->add('POST', '/packs/delete', fn (ApiRequest $request): ApiResponse => $this->delete($request));
        $router->add('POST', '/packs/colors/create', fn (ApiRequest $request): ApiResponse => $this->createColorScheme($request));
        $router->add('POST', '/packs/colors/update', fn (ApiRequest $request): ApiResponse => $this->updateColorScheme($request));
        $router->add('POST', '/packs/colors/delete', fn (ApiRequest $request): ApiResponse => $this->deleteColorScheme($request));
    }

    private function index(ApiRequest $request): ApiResponse
    {
        $user = $this->optionalUser($request);
        $userId = $user !== null && ($user['email_verified_at'] ?? null) !== null ? (int) $user['id'] : null;

        return new ApiResponse(['packs' => $this->packs->listAvailable($userId, ($user['role'] ?? null) === 'admin')]);
    }

    private function create(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $system = ($request->body['kind'] ?? 'user') === 'system';
        if ($system && $user['role'] !== 'admin') {
            throw new ApiException(403, 'ADMIN_REQUIRED', 'Solo un administrador puede crear packs del sistema.');
        }
        $requestedSchemeId = (int) ($request->body['colorSchemeId'] ?? 0);
        $colors = $this->packs->colorSchemeColorsForUser(
            $requestedSchemeId > 0 ? $requestedSchemeId : null,
            (int) $user['id']
        );
        $pack = $system
            ? $this->service->createSystemDraft((string) ($request->body['name'] ?? ''))
            : $this->service->createDraft((int) $user['id'], (string) ($request->body['name'] ?? ''));
        $categories = [];
        foreach (GameEngine::categories() as $slot => $category) {
            $categories[] = [
                'slot' => $slot,
                'key' => $category['slug'],
                'name' => $category['name'],
                'color' => $colors[$slot],
            ];
        }
        $this->service->replaceCategories((int) $user['id'], $pack['id'], $categories, $system);

        return new ApiResponse(['pack' => $this->packs->get($pack['id'])], 201);
    }

    private function import(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $pack = $this->service->importDraft(
            (int) $user['id'],
            (string) ($request->body['format'] ?? ''),
            (string) ($request->body['content'] ?? '')
        );

        return new ApiResponse(['pack' => $pack], 201);
    }

    private function categories(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $pack = $this->service->replaceCategories(
            (int) $user['id'],
            (int) ($request->body['packId'] ?? 0),
            is_array($request->body['categories'] ?? null) ? $request->body['categories'] : [],
            $user['role'] === 'admin'
        );
        return new ApiResponse(['revision' => $pack]);
    }

    private function question(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $question = $this->service->addQuestion(
            (int) $user['id'],
            (int) ($request->body['packId'] ?? 0),
            (int) ($request->body['slot'] ?? -1),
            [
                'question' => (string) ($request->body['question'] ?? ''),
                'options' => is_array($request->body['options'] ?? null) ? $request->body['options'] : [],
                'correct' => $request->body['correct'] ?? null,
            ],
            $user['role'] === 'admin'
        );
        return new ApiResponse(['question' => $question], 201);
    }

    private function activate(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $pack = $this->service->activate(
            (int) $user['id'],
            (int) ($request->body['packId'] ?? 0),
            $user['role'] === 'admin'
        );
        return new ApiResponse(['pack' => $pack]);
    }

    private function edit(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $revision = $this->service->beginEdit(
            (int) $user['id'],
            (int) ($request->body['packId'] ?? 0),
            $user['role'] === 'admin'
        );
        return new ApiResponse(['revision' => $revision]);
    }

    private function export(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request, false);
        $format = (string) ($request->query['format'] ?? 'json');
        $content = $this->service->export(
            (int) $user['id'],
            (int) ($request->query['id'] ?? 0),
            $format,
            $user['role'] === 'admin'
        );
        return new ApiResponse(['format' => $format, 'content' => $content]);
    }

    private function delete(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $this->service->delete(
            (int) $user['id'],
            (int) ($request->body['packId'] ?? 0),
            $user['role'] === 'admin'
        );
        return new ApiResponse(['ok' => true]);
    }

    private function createColorScheme(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $kind = ($request->body['kind'] ?? 'user') === 'system' ? 'system' : 'user';
        if ($kind === 'system' && $user['role'] !== 'admin') {
            throw new ApiException(403, 'ADMIN_REQUIRED', 'Solo un administrador puede crear esquemas del sistema.');
        }
        $scheme = $this->packs->createColorScheme(
            (string) ($request->body['name'] ?? ''),
            is_array($request->body['colors'] ?? null) ? $request->body['colors'] : [],
            $kind,
            $kind === 'user' ? (int) $user['id'] : null
        );
        return new ApiResponse(['colorScheme' => $scheme], 201);
    }

    private function colorSchemes(ApiRequest $request): ApiResponse
    {
        $user = $this->optionalUser($request);
        $verified = $user !== null && ($user['email_verified_at'] ?? null) !== null;
        return new ApiResponse([
            'colorSchemes' => $this->packs->listColorSchemes(
                $verified ? (int) $user['id'] : null,
                $verified && $user['role'] === 'admin'
            ),
        ]);
    }

    private function updateColorScheme(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $scheme = $this->packs->updateColorScheme(
            (int) ($request->body['colorSchemeId'] ?? 0),
            (int) $user['id'],
            $user['role'] === 'admin',
            (string) ($request->body['name'] ?? ''),
            is_array($request->body['colors'] ?? null) ? $request->body['colors'] : []
        );
        return new ApiResponse(['colorScheme' => $scheme]);
    }

    private function deleteColorScheme(ApiRequest $request): ApiResponse
    {
        $user = $this->requireVerifiedUser($request);
        $this->packs->softDeleteColorScheme(
            (int) ($request->body['colorSchemeId'] ?? 0),
            (int) $user['id'],
            $user['role'] === 'admin'
        );
        return new ApiResponse(['ok' => true]);
    }

    private function requireAdmin(ApiRequest $request): array
    {
        $user = $this->requireVerifiedUser($request);
        if ($user['role'] !== 'admin') {
            throw new ApiException(403, 'ADMIN_REQUIRED', 'Solo un administrador puede realizar esta operacion.');
        }
        return $user;
    }

    private function optionalUser(ApiRequest $request): ?array
    {
        $token = (string) ($request->cookies[self::COOKIE_NAME] ?? '');
        return $token === '' ? null : $this->sessions->findUserByToken($token);
    }

    private function requireVerifiedUser(ApiRequest $request, bool $csrf = true): array
    {
        try {
            $user = Authorization::requireVerifiedUser($this->optionalUser($request));
        } catch (RuntimeException $e) {
            $status = $e->getMessage() === 'AUTH_REQUIRED' ? 401 : 403;
            throw new ApiException($status, $e->getMessage(), 'Necesitas una cuenta verificada.');
        }
        if ($csrf) {
            $provided = $request->header('x-csrf-token') ?? '';
            if ($provided === '' || !hash_equals((string) $user['csrf_token'], $provided)) {
                throw new ApiException(403, 'CSRF_INVALID', 'Token CSRF no valido.');
            }
        }

        return $user;
    }
}
