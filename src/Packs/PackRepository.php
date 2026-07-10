<?php

declare(strict_types=1);

final class PackRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createDraft(?int $ownerUserId, string $name, string $kind = 'user'): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del pack es obligatorio.');
        }
        if (!in_array($kind, ['user', 'system'], true)) {
            throw new InvalidArgumentException('Tipo de pack no valido.');
        }

        $now = gmdate('c');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO question_packs
                    (owner_user_id, name, kind, status, created_at, updated_at)
                 VALUES
                    (:owner_user_id, :name, :kind, :status, :created_at, :updated_at)'
            );
            $stmt->execute([
                ':owner_user_id' => $ownerUserId,
                ':name' => $name,
                ':kind' => $kind,
                ':status' => 'draft',
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $packId = (int) $this->pdo->lastInsertId();
            $revisionId = $this->insertRevision($packId, 1);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->get($packId);
    }

    public function get(int $packId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM question_packs WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $packId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('PACK_NOT_FOUND');
        }

        $draft = $this->latestRevisionByStatus($packId, 'draft');
        $current = $row['current_revision_id'] === null
            ? null
            : $this->revisionDetails((int) $row['current_revision_id']);

        return [
            'id' => (int) $row['id'],
            'ownerUserId' => $row['owner_user_id'] === null ? null : (int) $row['owner_user_id'],
            'name' => $row['name'],
            'kind' => $row['kind'],
            'status' => $row['status'],
            'currentRevision' => $current,
            'draftRevision' => $draft,
        ];
    }

    public function replaceCategories(int $revisionId, array $categories): array
    {
        $this->assertDraft($revisionId);
        $normalized = $this->normalizeCategories($categories);

        $this->pdo->beginTransaction();
        try {
            $deleteQuestions = $this->pdo->prepare('DELETE FROM questions WHERE pack_revision_id = :revision_id');
            $deleteQuestions->execute([':revision_id' => $revisionId]);
            $deleteCategories = $this->pdo->prepare('DELETE FROM pack_categories WHERE revision_id = :revision_id');
            $deleteCategories->execute([':revision_id' => $revisionId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO pack_categories (revision_id, slot, category_key, name, color)
                 VALUES (:revision_id, :slot, :category_key, :name, :color)'
            );
            foreach ($normalized as $category) {
                $insert->execute([
                    ':revision_id' => $revisionId,
                    ':slot' => $category['slot'],
                    ':category_key' => $category['key'],
                    ':name' => $category['name'],
                    ':color' => $category['color'],
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->revisionDetails($revisionId);
    }

    public function addQuestion(int $revisionId, int $slot, array $question): array
    {
        $this->assertDraft($revisionId);
        $category = $this->categoryBySlot($revisionId, $slot);
        $text = trim((string) ($question['question'] ?? ''));
        $options = array_values($question['options'] ?? []);
        $correct = filter_var($question['correct'] ?? null, FILTER_VALIDATE_INT);
        if ($text === '' || count($options) !== 4 || in_array('', array_map(fn ($value): string => trim((string) $value), $options), true)) {
            throw new InvalidArgumentException('Cada pregunta necesita enunciado y cuatro opciones.');
        }
        if ($correct === false || $correct < 0 || $correct > 3) {
            throw new InvalidArgumentException('Respuesta correcta no valida.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO questions
                (category, question, option_a, option_b, option_c, option_d, correct, created_at, pack_revision_id, pack_category_id)
             VALUES
                (:category, :question, :option_a, :option_b, :option_c, :option_d, :correct, :created_at, :pack_revision_id, :pack_category_id)'
        );
        $stmt->execute([
            ':category' => $category['category_key'],
            ':question' => $text,
            ':option_a' => trim((string) $options[0]),
            ':option_b' => trim((string) $options[1]),
            ':option_c' => trim((string) $options[2]),
            ':option_d' => trim((string) $options[3]),
            ':correct' => $correct,
            ':created_at' => gmdate('c'),
            ':pack_revision_id' => $revisionId,
            ':pack_category_id' => $category['id'],
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'question' => $text];
    }

    public function activate(int $packId, int $revisionId): array
    {
        $this->assertDraft($revisionId);
        $revision = $this->revisionDetails($revisionId);
        if (count($revision['categories']) !== 6) {
            throw new RuntimeException('PACK_INCOMPLETE');
        }
        $counts = array_fill(0, 6, 0);
        foreach ($revision['questions'] as $question) {
            $counts[$question['slot']]++;
        }
        if (in_array(0, $counts, true)) {
            throw new RuntimeException('PACK_INCOMPLETE');
        }

        $now = gmdate('c');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE pack_revisions SET status = :status, activated_at = :activated_at WHERE id = :id AND pack_id = :pack_id'
            );
            $stmt->execute([':status' => 'active', ':activated_at' => $now, ':id' => $revisionId, ':pack_id' => $packId]);
            $pack = $this->pdo->prepare(
                'UPDATE question_packs
                 SET status = :status, current_revision_id = :revision_id, updated_at = :updated_at
                 WHERE id = :id'
            );
            $pack->execute([':status' => 'active', ':revision_id' => $revisionId, ':updated_at' => $now, ':id' => $packId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->get($packId);
    }

    public function beginEdit(int $packId): array
    {
        $pack = $this->get($packId);
        if ($pack['draftRevision'] !== null) {
            return $pack['draftRevision'];
        }
        if ($pack['currentRevision'] === null) {
            throw new RuntimeException('PACK_HAS_NO_ACTIVE_REVISION');
        }

        $this->pdo->beginTransaction();
        try {
            $number = $this->nextRevisionNumber($packId);
            $newId = $this->insertRevision($packId, $number);
            foreach ($pack['currentRevision']['categories'] as $category) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO pack_categories (revision_id, slot, category_key, name, color)
                     VALUES (:revision_id, :slot, :category_key, :name, :color)'
                );
                $stmt->execute([
                    ':revision_id' => $newId,
                    ':slot' => $category['slot'],
                    ':category_key' => $category['key'],
                    ':name' => $category['name'],
                    ':color' => $category['color'],
                ]);
            }
            foreach ($pack['currentRevision']['questions'] as $question) {
                $this->addQuestionInsideTransaction($newId, $question['slot'], $question);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->revisionDetails($newId);
    }

    public function editableRevisionForPack(int $packId): array
    {
        $pack = $this->get($packId);
        return $pack['draftRevision'] ?? throw new RuntimeException('PACK_NOT_EDITABLE');
    }

    private function addQuestionInsideTransaction(int $revisionId, int $slot, array $question): void
    {
        $category = $this->categoryBySlot($revisionId, $slot);
        $stmt = $this->pdo->prepare(
            'INSERT INTO questions
                (category, question, option_a, option_b, option_c, option_d, correct, created_at, pack_revision_id, pack_category_id)
             VALUES
                (:category, :question, :option_a, :option_b, :option_c, :option_d, :correct, :created_at, :pack_revision_id, :pack_category_id)'
        );
        $stmt->execute([
            ':category' => $category['category_key'],
            ':question' => $question['question'],
            ':option_a' => $question['options'][0],
            ':option_b' => $question['options'][1],
            ':option_c' => $question['options'][2],
            ':option_d' => $question['options'][3],
            ':correct' => $question['correct'],
            ':created_at' => gmdate('c'),
            ':pack_revision_id' => $revisionId,
            ':pack_category_id' => $category['id'],
        ]);
    }

    private function revisionDetails(int $revisionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pack_revisions WHERE id = :id');
        $stmt->execute([':id' => $revisionId]);
        $revision = $stmt->fetch();
        if ($revision === false) {
            throw new RuntimeException('REVISION_NOT_FOUND');
        }
        $categoriesStmt = $this->pdo->prepare('SELECT * FROM pack_categories WHERE revision_id = :id ORDER BY slot');
        $categoriesStmt->execute([':id' => $revisionId]);
        $categories = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slot' => (int) $row['slot'],
            'key' => $row['category_key'],
            'name' => $row['name'],
            'color' => $row['color'],
        ], $categoriesStmt->fetchAll());

        $questionsStmt = $this->pdo->prepare(
            'SELECT q.*, c.slot
             FROM questions q INNER JOIN pack_categories c ON c.id = q.pack_category_id
             WHERE q.pack_revision_id = :id ORDER BY q.id'
        );
        $questionsStmt->execute([':id' => $revisionId]);
        $questions = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slot' => (int) $row['slot'],
            'question' => $row['question'],
            'options' => [$row['option_a'], $row['option_b'], $row['option_c'], $row['option_d']],
            'correct' => (int) $row['correct'],
        ], $questionsStmt->fetchAll());

        return [
            'id' => (int) $revision['id'],
            'packId' => (int) $revision['pack_id'],
            'revisionNumber' => (int) $revision['revision_number'],
            'status' => $revision['status'],
            'categories' => $categories,
            'questions' => $questions,
        ];
    }

    private function latestRevisionByStatus(int $packId, string $status): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM pack_revisions WHERE pack_id = :pack_id AND status = :status ORDER BY revision_number DESC LIMIT 1'
        );
        $stmt->execute([':pack_id' => $packId, ':status' => $status]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : $this->revisionDetails((int) $id);
    }

    private function assertDraft(int $revisionId): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM pack_revisions WHERE id = :id');
        $stmt->execute([':id' => $revisionId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw new RuntimeException('REVISION_NOT_FOUND');
        }
        if ($status !== 'draft') {
            throw new RuntimeException('REVISION_IMMUTABLE');
        }
    }

    private function categoryBySlot(int $revisionId, int $slot): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pack_categories WHERE revision_id = :revision_id AND slot = :slot'
        );
        $stmt->execute([':revision_id' => $revisionId, ':slot' => $slot]);
        $category = $stmt->fetch();
        if ($category === false) {
            throw new InvalidArgumentException('Categoria no encontrada en el pack.');
        }
        return $category;
    }

    private function normalizeCategories(array $categories): array
    {
        if (count($categories) !== 6) {
            throw new InvalidArgumentException('El pack debe definir exactamente seis categorias.');
        }
        $normalized = [];
        $keys = [];
        foreach ($categories as $category) {
            $slot = filter_var($category['slot'] ?? null, FILTER_VALIDATE_INT);
            $key = strtolower(trim((string) ($category['key'] ?? '')));
            $name = trim((string) ($category['name'] ?? ''));
            $color = trim((string) ($category['color'] ?? ''));
            if ($slot === false || $slot < 0 || $slot > 5 || isset($normalized[$slot])) {
                throw new InvalidArgumentException('Los slots de categoria deben ser unicos entre 0 y 5.');
            }
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,59}$/', $key) || isset($keys[$key])) {
                throw new InvalidArgumentException('Las claves de categoria deben ser validas y unicas.');
            }
            if ($name === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                throw new InvalidArgumentException('Nombre o color de categoria no valido.');
            }
            $normalized[$slot] = compact('slot', 'key', 'name', 'color');
            $keys[$key] = true;
        }
        ksort($normalized);
        return array_values($normalized);
    }

    private function insertRevision(int $packId, int $number): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pack_revisions (pack_id, revision_number, status, created_at)
             VALUES (:pack_id, :revision_number, :status, :created_at)'
        );
        $stmt->execute([
            ':pack_id' => $packId,
            ':revision_number' => $number,
            ':status' => 'draft',
            ':created_at' => gmdate('c'),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function nextRevisionNumber(int $packId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(revision_number), 0) + 1 FROM pack_revisions WHERE pack_id = :pack_id');
        $stmt->execute([':pack_id' => $packId]);
        return (int) $stmt->fetchColumn();
    }
}
