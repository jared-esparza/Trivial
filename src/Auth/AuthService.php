<?php

declare(strict_types=1);

final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private AccountTokenRepository $tokens,
        private Mailer $mailer,
        private string $baseUrl,
        private ?AuthRateLimiter $rateLimiter = null,
    ) {
    }

    public function register(string $email, string $password, string $displayName): array
    {
        $normalizedEmail = strtolower(trim($email));
        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El email no es valido.');
        }
        if (strlen($password) < 10) {
            throw new InvalidArgumentException('La contrasena debe tener al menos 10 caracteres.');
        }

        $user = $this->users->create($normalizedEmail, password_hash($password, PASSWORD_DEFAULT), 'user', $displayName);
        $token = $this->tokens->issue($user['id'], 'verify_email', 24 * 60 * 60);
        $url = rtrim($this->baseUrl, '/') . '/account.php?action=verify&token=' . rawurlencode($token);
        $this->mailer->send(
            $normalizedEmail,
            'Verifica tu cuenta de Rueda Quiz',
            "Verifica tu cuenta abriendo este enlace:\n{$url}"
        );

        return $user;
    }

    public function verify(string $token): array
    {
        $userId = $this->tokens->consume($token, 'verify_email');

        return $this->users->markVerified($userId);
    }

    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $this->rateLimiter?->assertAllowed('login', $normalizedEmail);
        $user = $this->users->findByEmail($normalizedEmail);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->rateLimiter?->registerFailure('login', $normalizedEmail, 5, 15 * 60);
            throw new InvalidArgumentException('Email o contrasena incorrectos.');
        }
        if ($user['status'] !== 'active') {
            throw new RuntimeException('ACCOUNT_DISABLED');
        }

        $this->rateLimiter?->clear('login', $normalizedEmail);

        return [
            ...$this->sessions->create($user['id']),
            'user' => $user,
        ];
    }

    public function requestPasswordReset(string $email): void
    {
        $normalizedEmail = strtolower(trim($email));
        $this->rateLimiter?->registerFailure('password_reset', $normalizedEmail, 5, 15 * 60);
        $user = $this->users->findByEmail($normalizedEmail);
        if ($user === null || $user['status'] !== 'active') {
            return;
        }

        $token = $this->tokens->issue($user['id'], 'reset_password', 60 * 60);
        $url = rtrim($this->baseUrl, '/') . '/account.php?action=reset&token=' . rawurlencode($token);
        $this->mailer->send(
            $user['email'],
            'Restablece tu contrasena de Rueda Quiz',
            "Restablece tu contrasena abriendo este enlace:\n{$url}"
        );
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 10) {
            throw new InvalidArgumentException('La contrasena debe tener al menos 10 caracteres.');
        }
        $userId = $this->tokens->consume($token, 'reset_password');
        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        $this->sessions->deleteAllForUser($userId);
    }

    public function changePassword(int $userId, int $sessionId, string $currentPassword, string $newPassword): void
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException('USER_NOT_FOUND');
        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('La contrasena actual no es correcta.');
        }
        if (strlen($newPassword) < 10) {
            throw new InvalidArgumentException('La contrasena debe tener al menos 10 caracteres.');
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        $this->sessions->deleteAllForUserExcept($userId, $sessionId);
    }

    public function updateDisplayName(int $userId, string $displayName): array
    {
        return $this->users->updateDisplayName($userId, $displayName);
    }

    public function logout(string $sessionToken): void
    {
        $this->sessions->deleteByToken($sessionToken);
    }
}
