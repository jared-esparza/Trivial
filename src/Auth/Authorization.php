<?php

declare(strict_types=1);

final class Authorization
{
    public static function requireVerifiedUser(?array $user): array
    {
        if ($user === null) {
            throw new RuntimeException('AUTH_REQUIRED');
        }
        if (($user['status'] ?? '') !== 'active') {
            throw new RuntimeException('ACCOUNT_DISABLED');
        }
        if (($user['email_verified_at'] ?? null) === null) {
            throw new RuntimeException('EMAIL_NOT_VERIFIED');
        }

        return $user;
    }

    public static function requireAdmin(?array $user): array
    {
        $user = self::requireVerifiedUser($user);
        if (($user['role'] ?? '') !== 'admin') {
            throw new RuntimeException('ADMIN_REQUIRED');
        }

        return $user;
    }
}
