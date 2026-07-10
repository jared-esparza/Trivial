<?php

declare(strict_types=1);

final class PackSeeder
{
    public function __construct(
        private PDO $pdo,
        private string $questionsCsvPath,
    ) {
    }

    public function seed(): void
    {
        $this->seedClassicPack();
        $this->seedColorScheme('Clasico', [
            '#f2c94c', '#f2994a', '#2f80ed', '#8b5a2b', '#27ae60', '#d94a9b',
        ]);
        $this->seedColorScheme('Alternativo', [
            '#f2c94c', '#f2994a', '#2f80ed', '#7b3ff2', '#27ae60', '#eb5757',
        ]);
    }

    private function seedClassicPack(): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM question_packs WHERE kind = :kind AND name = :name AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':kind' => 'system', ':name' => 'Clasico']);
        if ($stmt->fetchColumn() !== false) {
            return;
        }
        $csv = file_get_contents($this->questionsCsvPath);
        if ($csv === false) {
            throw new RuntimeException('No se pudo leer el banco de preguntas demo.');
        }

        $repo = new PackRepository($this->pdo);
        $pack = $repo->createDraft(null, 'Clasico', 'system');
        $revisionId = $pack['draftRevision']['id'];
        $categories = [];
        $slotsByKey = [];
        foreach (GameEngine::categories() as $slot => $category) {
            $categories[] = [
                'slot' => $slot,
                'key' => $category['slug'],
                'name' => $category['name'],
                'color' => $category['color'],
            ];
            $slotsByKey[$category['slug']] = $slot;
        }
        $repo->replaceCategories($revisionId, $categories);
        foreach (QuestionImporter::fromCsv($csv) as $question) {
            $slot = $slotsByKey[$question['category']] ?? throw new RuntimeException('Categoria demo desconocida.');
            $repo->addQuestion($revisionId, $slot, $question);
        }
        $repo->activate($pack['id'], $revisionId);
    }

    private function seedColorScheme(string $name, array $colors): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM color_schemes WHERE name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $name]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $now = gmdate('c');
        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO color_schemes (name, status, created_at, updated_at)
                 VALUES (:name, :status, :created_at, :updated_at)'
            );
            $insert->execute([':name' => $name, ':status' => 'active', ':created_at' => $now, ':updated_at' => $now]);
            $schemeId = (int) $this->pdo->lastInsertId();
            $slotInsert = $this->pdo->prepare(
                'INSERT INTO color_scheme_slots (color_scheme_id, slot, color)
                 VALUES (:color_scheme_id, :slot, :color)'
            );
            foreach ($colors as $slot => $color) {
                $slotInsert->execute([':color_scheme_id' => $schemeId, ':slot' => $slot, ':color' => $color]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
