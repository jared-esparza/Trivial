<?php

declare(strict_types=1);

final class PackService
{
    public function __construct(private PackRepository $packs)
    {
    }

    public function createDraft(int $ownerUserId, string $name): array
    {
        return $this->packs->createDraft($ownerUserId, $name, 'user');
    }

    public function createSystemDraft(string $name): array
    {
        return $this->packs->createDraft(null, $name, 'system');
    }

    public function replaceCategories(int $actorUserId, int $packId, array $categories, bool $admin = false): array
    {
        $this->authorize($actorUserId, $packId, $admin);
        $revision = $this->packs->editableRevisionForPack($packId);
        return $this->packs->replaceCategories($revision['id'], $categories);
    }

    public function addQuestion(int $actorUserId, int $packId, int $slot, array $question, bool $admin = false): array
    {
        $this->authorize($actorUserId, $packId, $admin);
        $revision = $this->packs->editableRevisionForPack($packId);
        return $this->packs->addQuestion($revision['id'], $slot, $question);
    }

    public function updateQuestion(int $actorUserId, int $packId, int $questionId, int $slot, array $question, bool $admin = false): array
    {
        $this->authorize($actorUserId, $packId, $admin);
        $revision = $this->packs->editableRevisionForPack($packId);
        return $this->packs->updateQuestion($revision['id'], $questionId, $slot, $question);
    }

    public function deleteQuestion(int $actorUserId, int $packId, int $questionId, bool $admin = false): void
    {
        $this->authorize($actorUserId, $packId, $admin);
        $revision = $this->packs->editableRevisionForPack($packId);
        $this->packs->deleteQuestion($revision['id'], $questionId);
    }

    public function activate(int $actorUserId, int $packId, bool $admin = false): array
    {
        $this->authorize($actorUserId, $packId, $admin);
        $revision = $this->packs->editableRevisionForPack($packId);
        return $this->packs->activate($packId, $revision['id']);
    }

    public function beginEdit(int $actorUserId, int $packId, bool $admin = false): array
    {
        $this->authorize($actorUserId, $packId, $admin);
        return $this->packs->beginEdit($packId);
    }

    public function importDraft(int $ownerUserId, string $format, string $content): array
    {
        $definition = $this->importDefinition($format, $content);
        $pack = $this->createDraft($ownerUserId, $definition['name']);
        $this->replaceCategories($ownerUserId, $pack['id'], $definition['categories']);
        foreach ($definition['questions'] as $question) {
            $this->addQuestion($ownerUserId, $pack['id'], $question['slot'], $question);
        }

        return $this->packs->get($pack['id']);
    }

    public function previewImport(string $format, string $content): array
    {
        $definition = $this->importDefinition($format, $content);
        $counts = array_fill(0, 6, 0);
        foreach ($definition['questions'] as $question) {
            $counts[$question['slot']]++;
        }

        return [
            'name' => $definition['name'],
            'categories' => $definition['categories'],
            'questionCount' => count($definition['questions']),
            'questionsPerCategory' => $counts,
        ];
    }

    public function export(int $actorUserId, int $packId, string $format, bool $admin = false): string
    {
        $pack = $this->packs->get($packId);
        if ($pack['kind'] === 'system' && !$admin) {
            throw new RuntimeException('PACK_FORBIDDEN');
        }
        $this->authorize($actorUserId, $packId, $admin);
        $definition = $this->packs->portableDefinition($packId);

        return match (strtolower($format)) {
            'json' => PackExporter::toJson($definition),
            'csv' => PackExporter::toCsv($definition),
            default => throw new InvalidArgumentException('Formato de exportacion no valido.'),
        };
    }

    public function delete(int $actorUserId, int $packId, bool $admin = false): void
    {
        $pack = $this->authorize($actorUserId, $packId, $admin);
        if ($pack['kind'] === 'system' && $pack['name'] === 'Clasico') {
            throw new RuntimeException('DEFAULT_PACK_REQUIRED');
        }
        $this->packs->softDelete($packId);
    }

    private function authorize(int $actorUserId, int $packId, bool $admin): array
    {
        $pack = $this->packs->get($packId);
        if (!$admin && $pack['ownerUserId'] !== $actorUserId) {
            throw new RuntimeException('PACK_FORBIDDEN');
        }
        return $pack;
    }

    private function importDefinition(string $format, string $content): array
    {
        return match (strtolower($format)) {
            'json' => PackImporter::fromJson($content),
            'csv' => PackImporter::fromCsv($content),
            default => throw new InvalidArgumentException('Formato de importacion no valido.'),
        };
    }
}
