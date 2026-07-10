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

    private function authorize(int $actorUserId, int $packId, bool $admin): array
    {
        $pack = $this->packs->get($packId);
        if (!$admin && $pack['ownerUserId'] !== $actorUserId) {
            throw new RuntimeException('PACK_FORBIDDEN');
        }
        return $pack;
    }
}
