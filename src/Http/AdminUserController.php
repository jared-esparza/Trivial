<?php

declare(strict_types=1);

final class AdminUserController
{
    private const COOKIE_NAME = 'rq_session';

    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private UserAdminService $admin,
    ) {
    }

    public function registerRoutes(ApiRouter $router): void
    {
        $router->add('GET', '/admin/users', fn (ApiRequest $request): ApiResponse => $this->index($request));
        $router->add('POST', '/admin/users/update', fn (ApiRequest $request): ApiResponse => $this->update($request));
    }

    private function index(ApiRequest $request): ApiResponse
    {
        $this->requireAdmin($request, false);

        return new ApiResponse([
            'users' => array_map([$this, 'publicUser'], $this->users->all()),
        ]);
    }

    private function update(ApiRequest $request): ApiResponse
    {
        $currentAdmin = $this->requireAdmin($request, true);
        $userId = (int) ($request->body['userId'] ?? 0);
        if ($userId < 1) {
            throw new ApiException(422, 'USER_ID_REQUIRED', 'Usuario no valido.');
        }
        if ($userId === (int) $currentAdmin['id']) {
            throw new ApiException(409, 'SELF_ADMIN_CHANGE', 'No puedes cambiar tu propio rol o estado.');
        }

        try {
            if (isset($request->body['role'])) {
                $this->admin->changeRole($userId, (string) $request->body['role']);
            }
            if (isset($request->body['status'])) {
                $status = (string) $request->body['status'];
                if ($status === 'disabled') {
                    $this->admin->disable($userId);
                } elseif ($status === 'active') {
                    $this->admin->enable($userId);
                } else {
                    throw new InvalidArgumentException('Estado de usuario no valido.');
                }
            }
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'LAST_ADMIN') {
                throw new ApiException(409, 'LAST_ADMIN', 'Debe quedar al menos un administrador activo.');
            }
            throw $e;
        }

        $user = $this->users->findById($userId) ?? throw new ApiException(404, 'USER_NOT_FOUND', 'Usuario no encontrado.');

        return new ApiResponse(['user' => $this->publicUser($user)]);
    }

    private function requireAdmin(ApiRequest $request, bool $csrf): array
    {
        $token = (string) ($request->cookies[self::COOKIE_NAME] ?? '');
        $user = $token === '' ? null : $this->sessions->findUserByToken($token);
        try {
            $admin = Authorization::requireAdmin($user);
        } catch (RuntimeException $e) {
            $status = $e->getMessage() === 'AUTH_REQUIRED' ? 401 : 403;
            throw new ApiException($status, $e->getMessage(), 'No tienes acceso a esta operacion.');
        }
        if ($csrf) {
            $provided = $request->header('x-csrf-token') ?? '';
            if ($provided === '' || !hash_equals((string) $admin['csrf_token'], $provided)) {
                throw new ApiException(403, 'CSRF_INVALID', 'Token CSRF no valido.');
            }
        }

        return $admin;
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'displayName' => (string) $user['display_name'],
            'role' => (string) $user['role'],
            'status' => (string) $user['status'],
            'emailVerified' => ($user['email_verified_at'] ?? null) !== null,
            'createdAt' => (string) $user['created_at'],
        ];
    }
}
