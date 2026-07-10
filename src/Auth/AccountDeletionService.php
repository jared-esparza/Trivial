<?php

declare(strict_types=1);

final class AccountDeletionService
{
    public function __construct(
        private PDO $pdo,
        private UserRepository $users,
        private SessionRepository $sessions
    ) {
    }

    public function delete(int $userId): void
    {
        $userRecord = $this->users->findById($userId);
        if ($userRecord === null) {
            throw new RuntimeException('USER_NOT_FOUND');
        }
        if ($userRecord['role'] === 'admin' && $userRecord['status'] === 'active' && $this->users->activeAdminCount() <= 1) {
            throw new RuntimeException('LAST_ADMIN');
        }

        $now = gmdate('c');
        $this->pdo->beginTransaction();
        try {
            $this->sessions->deleteAllForUser($userId);

            $participants = $this->pdo->prepare(
                'UPDATE room_participants SET user_id = NULL WHERE user_id = :user_id'
            );
            $participants->execute([':user_id' => $userId]);

            $rooms = $this->pdo->prepare(
                'UPDATE rooms SET creator_user_id = NULL WHERE creator_user_id = :user_id'
            );
            $rooms->execute([':user_id' => $userId]);

            $packs = $this->pdo->prepare(
                "UPDATE question_packs
                 SET status = 'disabled', deleted_at = :deleted_at, updated_at = :updated_at
                 WHERE owner_user_id = :user_id AND deleted_at IS NULL"
            );
            $packs->execute([
                ':deleted_at' => $now,
                ':updated_at' => $now,
                ':user_id' => $userId,
            ]);

            $deletedEmail = sprintf('deleted-%d-%s@invalid.local', $userId, bin2hex(random_bytes(8)));
            $user = $this->pdo->prepare(
                "UPDATE users
                 SET email = :email,
                     display_name = :display_name,
                     password_hash = :password_hash,
                     role = 'user',
                     status = 'disabled',
                     email_verified_at = NULL,
                     deleted_at = :deleted_at,
                     updated_at = :updated_at
                 WHERE id = :id"
            );
            $user->execute([
                ':email' => $deletedEmail,
                ':display_name' => 'Usuario eliminado',
                ':password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                ':deleted_at' => $now,
                ':updated_at' => $now,
                ':id' => $userId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
