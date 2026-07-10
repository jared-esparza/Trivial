<?php

declare(strict_types=1);

final class UserAdminService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private SessionRepository $sessions,
    ) {
    }

    public function changeRole(int $userId, string $role): array
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException('USER_NOT_FOUND');
        if ($user['role'] === 'admin' && $role !== 'admin' && $this->users->activeAdminCount() <= 1) {
            throw new RuntimeException('LAST_ADMIN');
        }

        $this->users->updateRole($userId, $role);

        return $this->users->findById($userId) ?? throw new RuntimeException('USER_NOT_FOUND');
    }

    public function disable(int $userId): array
    {
        $user = $this->users->findById($userId) ?? throw new RuntimeException('USER_NOT_FOUND');
        if ($user['role'] === 'admin' && $this->users->activeAdminCount() <= 1) {
            throw new RuntimeException('LAST_ADMIN');
        }

        $this->pdo->beginTransaction();
        try {
            $this->users->updateStatus($userId, 'disabled');
            $this->sessions->deleteAllForUser($userId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->users->findById($userId) ?? throw new RuntimeException('USER_NOT_FOUND');
    }
}
