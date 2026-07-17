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

    public function listAvailable(?int $userId, bool $admin = false): array
    {
        if ($userId === null) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM question_packs
                 WHERE kind = 'system' AND status = 'active' AND deleted_at IS NULL
                 ORDER BY name, id"
            );
            $stmt->execute();
        } elseif ($admin) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM question_packs
                 WHERE deleted_at IS NULL
                   AND (kind = 'system' OR owner_user_id = :owner_user_id)
                 ORDER BY kind, name, id"
            );
            $stmt->execute([':owner_user_id' => $userId]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM question_packs
                 WHERE deleted_at IS NULL
                   AND ((kind = 'system' AND status = 'active') OR owner_user_id = :owner_user_id)
                 ORDER BY kind, name, id"
            );
            $stmt->execute([':owner_user_id' => $userId]);
        }

        return array_map(fn ($id): array => $this->get((int) $id), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function listColorSchemes(?int $userId = null, bool $isAdmin = false): array
    {
        $schemes = [];
        if ($userId === null) {
            $stmt = $this->pdo->query(
                "SELECT id, name, kind, owner_user_id FROM color_schemes
                 WHERE kind = 'system' AND status = 'active' AND deleted_at IS NULL
                 ORDER BY kind DESC, name, id"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, kind, owner_user_id FROM color_schemes
                 WHERE status = 'active' AND deleted_at IS NULL
                   AND (kind = 'system' OR owner_user_id = :owner_user_id)
                 ORDER BY kind DESC, name, id"
            );
            $stmt->execute([':owner_user_id' => $userId]);
        }
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $ownerUserId = $row['owner_user_id'] === null ? null : (int) $row['owner_user_id'];
            $schemes[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'colors' => array_values($this->colorSchemeColors((int) $row['id'], $userId)),
                'kind' => $row['kind'],
                'ownerUserId' => $ownerUserId,
                'editable' => $row['kind'] === 'user' ? $ownerUserId === $userId : $isAdmin,
            ];
        }
        return $schemes;
    }

    public function createColorScheme(string $name, array $colors, string $kind = 'system', ?int $ownerUserId = null): array
    {
        [$name, $colors] = $this->normalizeColorScheme($name, $colors);
        if (!in_array($kind, ['system', 'user'], true) || ($kind === 'system' && $ownerUserId !== null) || ($kind === 'user' && $ownerUserId === null)) {
            throw new InvalidArgumentException('Propietario del esquema de colores no valido.');
        }

        $now = gmdate('c');
        $this->pdo->beginTransaction();
        try {
            $scheme = $this->pdo->prepare(
                "INSERT INTO color_schemes (name, kind, owner_user_id, status, created_at, updated_at)
                 VALUES (:name, :kind, :owner_user_id, 'active', :created_at, :updated_at)"
            );
            $scheme->execute([
                ':name' => $name,
                ':kind' => $kind,
                ':owner_user_id' => $ownerUserId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $slot = $this->pdo->prepare(
                'INSERT INTO color_scheme_slots (color_scheme_id, slot, color) VALUES (:id, :slot, :color)'
            );
            foreach ($colors as $position => $color) {
                $slot->execute([':id' => $id, ':slot' => $position, ':color' => strtolower($color)]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $id,
            'name' => $name,
            'colors' => $colors,
            'kind' => $kind,
            'ownerUserId' => $ownerUserId,
            'editable' => true,
        ];
    }

    public function updateColorScheme(int $colorSchemeId, int $userId, bool $isAdmin, string $name, array $colors): array
    {
        $scheme = $this->editableColorScheme($colorSchemeId, $userId, $isAdmin);
        [$name, $colors] = $this->normalizeColorScheme($name, $colors);
        if ($scheme['kind'] === 'system' && $scheme['name'] === 'Clasico' && $name !== 'Clasico') {
            throw new RuntimeException('DEFAULT_COLOR_SCHEME_REQUIRED');
        }
        $now = gmdate('c');

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE color_schemes SET name = :name, updated_at = :updated_at WHERE id = :id');
            $update->execute([':name' => $name, ':updated_at' => $now, ':id' => $colorSchemeId]);
            $slot = $this->pdo->prepare(
                'UPDATE color_scheme_slots SET color = :color WHERE color_scheme_id = :id AND slot = :slot'
            );
            foreach ($colors as $position => $color) {
                $slot->execute([':color' => $color, ':id' => $colorSchemeId, ':slot' => $position]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $colorSchemeId,
            'name' => $name,
            'colors' => $colors,
            'kind' => $scheme['kind'],
            'ownerUserId' => $scheme['ownerUserId'],
            'editable' => true,
        ];
    }

    public function softDeleteColorScheme(int $colorSchemeId, int $userId, bool $isAdmin): void
    {
        $scheme = $this->editableColorScheme($colorSchemeId, $userId, $isAdmin);
        if ($scheme['kind'] === 'system' && $scheme['name'] === 'Clasico') {
            throw new RuntimeException('DEFAULT_COLOR_SCHEME_REQUIRED');
        }
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            "UPDATE color_schemes
             SET status = 'disabled', deleted_at = :deleted_at, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':deleted_at' => $now, ':updated_at' => $now, ':id' => $colorSchemeId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('COLOR_SCHEME_NOT_FOUND');
        }
    }

    public function defaultColorScheme(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM color_schemes
             WHERE kind = 'system' AND name = 'Clasico' AND status = 'active' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute();
        $id = (int) $stmt->fetchColumn();
        if ($id <= 0) {
            throw new RuntimeException('DEFAULT_COLOR_SCHEME_REQUIRED');
        }
        return ['id' => $id, 'colors' => array_values($this->colorSchemeColors($id, null))];
    }

    public function colorSchemeColorsForUser(?int $colorSchemeId, ?int $userId): array
    {
        if ($colorSchemeId === null) {
            return $this->defaultColorScheme()['colors'];
        }
        return array_values($this->colorSchemeColors($colorSchemeId, $userId));
    }

    public function portableDefinition(int $packId): array
    {
        $pack = $this->get($packId);
        $revision = $pack['currentRevision'] ?? $pack['draftRevision'] ?? throw new RuntimeException('REVISION_NOT_FOUND');

        return [
            'name' => $pack['name'],
            'categories' => array_map(static fn (array $category): array => [
                'slot' => $category['slot'],
                'key' => $category['key'],
                'name' => $category['name'],
                'color' => $category['color'],
            ], $revision['categories']),
            'questions' => array_map(static fn (array $question): array => [
                'slot' => $question['slot'],
                'question' => $question['question'],
                'options' => $question['options'],
                'correct' => $question['correct'],
            ], $revision['questions']),
        ];
    }

    public function roomSelection(?int $userId, ?int $packId, ?int $colorSchemeId): array
    {
        if ($packId === null) {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM question_packs
                 WHERE kind = 'system' AND name = 'Clasico' AND status = 'active' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute();
            $packId = (int) $stmt->fetchColumn();
        }
        $pack = $this->get($packId);
        $allowed = $pack['kind'] === 'system' && $pack['status'] === 'active';
        if ($userId !== null && $pack['ownerUserId'] === $userId && $pack['status'] === 'active') {
            $allowed = true;
        }
        if (!$allowed || $pack['currentRevision'] === null) {
            throw new RuntimeException('PACK_FORBIDDEN');
        }

        $schemeColors = $colorSchemeId === null ? [] : $this->colorSchemeColors($colorSchemeId, $userId);
        $internal = GameEngine::categories();
        $categories = [];
        foreach ($pack['currentRevision']['categories'] as $category) {
            $slot = $category['slot'];
            $categories[] = [
                'slot' => $slot,
                'slug' => $internal[$slot]['slug'],
                'key' => $category['key'],
                'name' => $category['name'],
                'color' => $schemeColors[$slot] ?? $category['color'],
            ];
        }

        return [
            'packId' => $pack['id'],
            'packName' => $pack['name'],
            'revisionId' => $pack['currentRevision']['id'],
            'categories' => $categories,
        ];
    }

    public function softDelete(int $packId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE question_packs SET deleted_at = :deleted_at, status = :status, updated_at = :updated_at WHERE id = :id'
        );
        $now = gmdate('c');
        $stmt->execute([':deleted_at' => $now, ':status' => 'disabled', ':updated_at' => $now, ':id' => $packId]);
    }

    private function colorSchemeColors(int $colorSchemeId, ?int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.slot, s.color
             FROM color_scheme_slots s
             INNER JOIN color_schemes c ON c.id = s.color_scheme_id
             WHERE c.id = :id AND c.status = 'active' AND c.deleted_at IS NULL
               AND (c.kind = 'system' OR c.owner_user_id = :owner_user_id)
             ORDER BY s.slot"
        );
        $stmt->execute([':id' => $colorSchemeId, ':owner_user_id' => $userId]);
        $colors = [];
        foreach ($stmt->fetchAll() as $row) {
            $colors[(int) $row['slot']] = $row['color'];
        }
        if (count($colors) !== 6) {
            throw new RuntimeException('COLOR_SCHEME_FORBIDDEN');
        }
        return $colors;
    }

    private function editableColorScheme(int $colorSchemeId, int $userId, bool $isAdmin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, kind, owner_user_id FROM color_schemes
             WHERE id = :id AND status = 'active' AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':id' => $colorSchemeId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('COLOR_SCHEME_FORBIDDEN');
        }
        $ownerUserId = $row['owner_user_id'] === null ? null : (int) $row['owner_user_id'];
        $editable = $row['kind'] === 'system' ? $isAdmin : $ownerUserId === $userId;
        if (!$editable) {
            throw new RuntimeException('COLOR_SCHEME_FORBIDDEN');
        }
        return ['id' => (int) $row['id'], 'name' => $row['name'], 'kind' => $row['kind'], 'ownerUserId' => $ownerUserId];
    }

    private function normalizeColorScheme(string $name, array $colors): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $colors = array_values($colors);
        if ($name === '' || strlen($name) > 100 || count($colors) !== 6) {
            throw new InvalidArgumentException('El esquema de colores necesita un nombre y seis colores.');
        }
        foreach ($colors as &$color) {
            if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                throw new InvalidArgumentException('Color no valido.');
            }
            $color = strtolower($color);
        }
        unset($color);
        return [$name, $colors];
    }

    public function replaceCategories(int $revisionId, array $categories): array
    {
        $this->assertDraft($revisionId);
        $normalized = $this->normalizeCategories($categories);
        $existingStmt = $this->pdo->prepare(
            'SELECT slot, category_key FROM pack_categories WHERE revision_id = :revision_id ORDER BY slot'
        );
        $existingStmt->execute([':revision_id' => $revisionId]);
        $existing = $existingStmt->fetchAll();
        $preserveQuestions = count($existing) === 6;
        foreach ($normalized as $slot => $category) {
            if (!isset($existing[$slot]) || (int) $existing[$slot]['slot'] !== $slot || $existing[$slot]['category_key'] !== $category['key']) {
                $preserveQuestions = false;
                break;
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($preserveQuestions) {
                $update = $this->pdo->prepare(
                    'UPDATE pack_categories SET name = :name, color = :color
                     WHERE revision_id = :revision_id AND slot = :slot'
                );
                foreach ($normalized as $category) {
                    $update->execute([
                        ':name' => $category['name'],
                        ':color' => $category['color'],
                        ':revision_id' => $revisionId,
                        ':slot' => $category['slot'],
                    ]);
                }
                $this->pdo->commit();
                return $this->revisionDetails($revisionId);
            }
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
        $normalized = $this->normalizeQuestion($question);

        $stmt = $this->pdo->prepare(
            'INSERT INTO questions
                (category, question, option_a, option_b, option_c, option_d, correct, created_at, pack_revision_id, pack_category_id)
             VALUES
                (:category, :question, :option_a, :option_b, :option_c, :option_d, :correct, :created_at, :pack_revision_id, :pack_category_id)'
        );
        $stmt->execute([
            ':category' => $category['category_key'],
            ':question' => $normalized['question'],
            ':option_a' => $normalized['options'][0],
            ':option_b' => $normalized['options'][1],
            ':option_c' => $normalized['options'][2],
            ':option_d' => $normalized['options'][3],
            ':correct' => $normalized['correct'],
            ':created_at' => gmdate('c'),
            ':pack_revision_id' => $revisionId,
            ':pack_category_id' => $category['id'],
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'slot' => $slot] + $normalized;
    }

    public function updateQuestion(int $revisionId, int $questionId, int $slot, array $question): array
    {
        $this->assertDraft($revisionId);
        $category = $this->categoryBySlot($revisionId, $slot);
        $normalized = $this->normalizeQuestion($question);
        $stmt = $this->pdo->prepare(
            'UPDATE questions
             SET category = :category, question = :question, option_a = :option_a, option_b = :option_b,
                 option_c = :option_c, option_d = :option_d, correct = :correct, pack_category_id = :pack_category_id
             WHERE id = :id AND pack_revision_id = :revision_id'
        );
        $stmt->execute([
            ':category' => $category['category_key'],
            ':question' => $normalized['question'],
            ':option_a' => $normalized['options'][0],
            ':option_b' => $normalized['options'][1],
            ':option_c' => $normalized['options'][2],
            ':option_d' => $normalized['options'][3],
            ':correct' => $normalized['correct'],
            ':pack_category_id' => $category['id'],
            ':id' => $questionId,
            ':revision_id' => $revisionId,
        ]);
        if ($stmt->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT 1 FROM questions WHERE id = :id AND pack_revision_id = :revision_id');
            $exists->execute([':id' => $questionId, ':revision_id' => $revisionId]);
            if ($exists->fetchColumn() === false) {
                throw new RuntimeException('QUESTION_NOT_FOUND');
            }
        }

        return ['id' => $questionId, 'slot' => $slot] + $normalized;
    }

    public function deleteQuestion(int $revisionId, int $questionId): void
    {
        $this->assertDraft($revisionId);
        $stmt = $this->pdo->prepare('DELETE FROM questions WHERE id = :id AND pack_revision_id = :revision_id');
        $stmt->execute([':id' => $questionId, ':revision_id' => $revisionId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('QUESTION_NOT_FOUND');
        }
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

    private function normalizeQuestion(array $question): array
    {
        $text = trim((string) ($question['question'] ?? ''));
        $options = array_map(static fn ($value): string => trim((string) $value), array_values($question['options'] ?? []));
        $correct = filter_var($question['correct'] ?? null, FILTER_VALIDATE_INT);
        if ($text === '' || count($options) !== 4 || in_array('', $options, true)) {
            throw new InvalidArgumentException('Cada pregunta necesita enunciado y cuatro opciones.');
        }
        if ($correct === false || $correct < 0 || $correct > 3) {
            throw new InvalidArgumentException('Respuesta correcta no valida.');
        }

        return ['question' => $text, 'options' => $options, 'correct' => $correct];
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
