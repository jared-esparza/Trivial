<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $email, string $passwordHash, string $role = 'user'): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
             VALUES (:email, :password_hash, :role, :status, :created_at, :updated_at)'
        );

        try {
            $stmt->execute([
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':role' => $role,
                ':status' => 'active',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('Ya existe una cuenta con ese email.', 0, $e);
            }
            throw $e;
        }

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user === false ? null : $this->hydrate($user);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user === false ? null : $this->hydrate($user);
    }

    public function markVerified(int $id): array
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verified_at = :verified_at, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':verified_at' => $now,
            ':updated_at' => $now,
            ':id' => $id,
        ]);

        return $this->findById($id) ?? throw new RuntimeException('Usuario no encontrado.');
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':updated_at' => gmdate('c'),
            ':id' => $id,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Usuario no encontrado.');
        }
    }

    public function updateRole(int $id, string $role): void
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            throw new InvalidArgumentException('Rol no valido.');
        }
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':role' => $role,
            ':updated_at' => gmdate('c'),
            ':id' => $id,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new InvalidArgumentException('Estado de usuario no valido.');
        }
        $stmt = $this->pdo->prepare('UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':updated_at' => gmdate('c'),
            ':id' => $id,
        ]);
    }

    public function activeAdminCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND deleted_at IS NULL"
        )->fetchColumn();
    }

    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT * FROM users WHERE deleted_at IS NULL ORDER BY created_at, id'
        )->fetchAll();

        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];

        return $row;
    }
}
