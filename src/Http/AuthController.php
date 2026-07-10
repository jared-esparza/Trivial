<?php

declare(strict_types=1);

final class AuthController
{
    private const COOKIE_NAME = 'rq_session';

    public function __construct(
        private AuthService $auth,
        private SessionRepository $sessions,
        private ?AccountDeletionService $accountDeletion = null,
    ) {
    }

    public function registerRoutes(ApiRouter $router): void
    {
        $router->add('POST', '/auth/register', fn (ApiRequest $request): ApiResponse => $this->register($request));
        $router->add('POST', '/auth/verify', fn (ApiRequest $request): ApiResponse => $this->verify($request));
        $router->add('POST', '/auth/login', fn (ApiRequest $request): ApiResponse => $this->login($request));
        $router->add('GET', '/auth/me', fn (ApiRequest $request): ApiResponse => $this->me($request));
        $router->add('POST', '/auth/profile', fn (ApiRequest $request): ApiResponse => $this->profile($request));
        $router->add('POST', '/auth/logout', fn (ApiRequest $request): ApiResponse => $this->logout($request));
        $router->add('POST', '/auth/password/forgot', fn (ApiRequest $request): ApiResponse => $this->forgotPassword($request));
        $router->add('POST', '/auth/password/reset', fn (ApiRequest $request): ApiResponse => $this->resetPassword($request));
        $router->add('POST', '/auth/delete', fn (ApiRequest $request): ApiResponse => $this->deleteAccount($request));
    }

    private function register(ApiRequest $request): ApiResponse
    {
        $user = $this->auth->register(
            (string) ($request->body['email'] ?? ''),
            (string) ($request->body['password'] ?? ''),
            (string) ($request->body['displayName'] ?? '')
        );

        return new ApiResponse(['user' => $this->publicUser($user)], 201);
    }

    private function login(ApiRequest $request): ApiResponse
    {
        $session = $this->auth->login(
            (string) ($request->body['email'] ?? ''),
            (string) ($request->body['password'] ?? '')
        );

        return new ApiResponse(
            ['user' => $this->publicUser($session['user'])],
            200,
            [self::COOKIE_NAME => $this->sessionCookie($session['token'], $session['expiresAt'])]
        );
    }

    private function verify(ApiRequest $request): ApiResponse
    {
        $user = $this->auth->verify((string) ($request->body['token'] ?? ''));

        return new ApiResponse(['user' => $this->publicUser($user)]);
    }

    private function forgotPassword(ApiRequest $request): ApiResponse
    {
        $this->auth->requestPasswordReset((string) ($request->body['email'] ?? ''));

        return new ApiResponse(['ok' => true]);
    }

    private function resetPassword(ApiRequest $request): ApiResponse
    {
        $this->auth->resetPassword(
            (string) ($request->body['token'] ?? ''),
            (string) ($request->body['password'] ?? '')
        );

        return new ApiResponse(['ok' => true]);
    }

    private function me(ApiRequest $request): ApiResponse
    {
        $token = (string) ($request->cookies[self::COOKIE_NAME] ?? '');
        $user = $token === '' ? null : $this->sessions->findUserByToken($token);

        return new ApiResponse([
            'user' => $user === null ? null : $this->publicUser($user),
            'csrfToken' => $user['csrf_token'] ?? null,
        ]);
    }

    private function logout(ApiRequest $request): ApiResponse
    {
        [$token] = $this->requireSession($request, true);

        $this->auth->logout($token);

        return new ApiResponse(
            ['ok' => true],
            200,
            [self::COOKIE_NAME => $this->sessionCookie('', gmdate('c', 1))]
        );
    }

    private function profile(ApiRequest $request): ApiResponse
    {
        [, $user] = $this->requireSession($request, true);
        $updated = $this->auth->updateDisplayName(
            (int) $user['id'],
            (string) ($request->body['displayName'] ?? '')
        );

        return new ApiResponse(['user' => $this->publicUser($updated)]);
    }

    private function deleteAccount(ApiRequest $request): ApiResponse
    {
        if ($this->accountDeletion === null) {
            throw new ApiException(503, 'ACCOUNT_DELETION_UNAVAILABLE', 'El borrado de cuenta no esta disponible.');
        }

        [, $user] = $this->requireSession($request, true);
        if (!password_verify((string) ($request->body['password'] ?? ''), (string) $user['password_hash'])) {
            throw new InvalidArgumentException('La contrasena actual no es correcta.');
        }

        $this->accountDeletion->delete((int) $user['id']);

        return new ApiResponse(
            ['ok' => true],
            200,
            [self::COOKIE_NAME => $this->sessionCookie('', gmdate('c', 1))]
        );
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
        ];
    }

    private function requireSession(ApiRequest $request, bool $csrf): array
    {
        $token = (string) ($request->cookies[self::COOKIE_NAME] ?? '');
        $user = $token === '' ? null : $this->sessions->findUserByToken($token);
        if ($user === null) {
            throw new ApiException(401, 'AUTH_REQUIRED', 'Debes iniciar sesion.');
        }
        if ($csrf) {
            $providedCsrf = $request->header('x-csrf-token') ?? '';
            if ($providedCsrf === '' || !hash_equals((string) $user['csrf_token'], $providedCsrf)) {
                throw new ApiException(403, 'CSRF_INVALID', 'Token CSRF no valido.');
            }
        }

        return [$token, $user];
    }

    private function sessionCookie(string $value, string $expiresAt): array
    {
        return [
            'value' => $value,
            'expires' => strtotime($expiresAt),
            'path' => '/',
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ];
    }
}
