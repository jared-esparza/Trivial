<?php

declare(strict_types=1);

require_once __DIR__ . '/QuestionImporter.php';

final class QuestionRepository
{
    private string $randomFunction;

    public function __construct(private PDO $pdo)
    {
        $this->randomFunction = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'RAND()' : 'RANDOM()';
    }

    public function replaceAll(array $questions): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM questions');
            $count = 0;
            foreach ($questions as $question) {
                $this->insert($question);
                $count++;
            }
            $this->pdo->commit();

            return $count;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function insert(array $question): void
    {
        $normalized = QuestionImporter::normalizeRow([
            'category' => $question['category'] ?? '',
            'question' => $question['question'] ?? '',
            'option_a' => $question['options'][0] ?? $question['option_a'] ?? '',
            'option_b' => $question['options'][1] ?? $question['option_b'] ?? '',
            'option_c' => $question['options'][2] ?? $question['option_c'] ?? '',
            'option_d' => $question['options'][3] ?? $question['option_d'] ?? '',
            'correct' => $question['correct'] ?? '',
        ]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO questions
                (category, question, option_a, option_b, option_c, option_d, correct, created_at)
             VALUES
                (:category, :question, :option_a, :option_b, :option_c, :option_d, :correct, :created_at)'
        );
        $stmt->execute([
            ':category' => $normalized['category'],
            ':question' => $normalized['question'],
            ':option_a' => $normalized['options'][0],
            ':option_b' => $normalized['options'][1],
            ':option_c' => $normalized['options'][2],
            ':option_d' => $normalized['options'][3],
            ':correct' => $normalized['correct'],
            ':created_at' => gmdate('c'),
        ]);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM questions ORDER BY category, id');

        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    public function randomByCategory(string $category): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM questions WHERE category = :category ORDER BY ' . $this->randomFunction . ' LIMIT 1');
        $stmt->execute([':category' => $category]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('No hay preguntas para la categoria ' . $category . '.');
        }

        return $this->hydrate($row);
    }

    public function randomByRevisionSlot(int $revisionId, int $slot): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*
             FROM questions q
             INNER JOIN pack_categories c ON c.id = q.pack_category_id
             WHERE q.pack_revision_id = :revision_id AND c.slot = :slot
             ORDER BY ' . $this->randomFunction . ' LIMIT 1'
        );
        $stmt->execute([':revision_id' => $revisionId, ':slot' => $slot]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('No hay preguntas para la categoria seleccionada.');
        }
        $question = $this->hydrate($row);
        $internal = GameEngine::categories();
        $question['categoryKey'] = $question['category'];
        $question['category'] = $internal[$slot]['slug'] ?? throw new InvalidArgumentException('Slot de categoria no valido.');
        $question['categorySlot'] = $slot;

        return $question;
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'category' => $row['category'],
            'question' => $row['question'],
            'options' => [$row['option_a'], $row['option_b'], $row['option_c'], $row['option_d']],
            'correct' => (int) $row['correct'],
        ];
    }
}
