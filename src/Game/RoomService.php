<?php

declare(strict_types=1);

final class RoomService
{
    public function __construct(
        private RoomRepository $rooms,
        private PackRepository $packs,
        private ParticipantTokenService $tokens,
    ) {
    }

    public function createOnline(
        ?array $user,
        string $teamName,
        string $color,
        ?int $packId,
        ?int $colorSchemeId,
    ): array {
        return $this->tokens->transaction(function () use ($user, $teamName, $color, $packId, $colorSchemeId): array {
            $userId = $this->eligibleUserId($user);
            $selection = $this->packs->roomSelection($userId, $packId, $colorSchemeId);
            $participantToken = $this->tokens->issue();
            $room = $this->rooms->createRoom(
                'online',
                'auto',
                $teamName,
                $color,
                $userId,
                $selection['packId'],
                $selection['revisionId'],
                $selection['categories']
            );
            $this->tokens->register(
                $room['code'],
                0,
                $userId,
                $room['players'][0]['name'],
                $room['players'][0]['color'],
                $participantToken['hash']
            );
            $room['pack_name'] = $selection['packName'];
            $room['participant_token'] = $participantToken['token'];
            return $room;
        });
    }

    public function createLocal(
        ?array $user,
        string $answerMode,
        array $players,
        ?int $packId,
        ?int $colorSchemeId,
    ): array {
        return $this->tokens->transaction(function () use ($user, $answerMode, $players, $packId, $colorSchemeId): array {
            $userId = $this->eligibleUserId($user);
            $selection = $this->packs->roomSelection($userId, $packId, $colorSchemeId);
            $controllerToken = $this->tokens->issue();
            $room = $this->rooms->createLocalRoom(
                $answerMode,
                $players,
                $userId,
                $selection['packId'],
                $selection['revisionId'],
                $selection['categories'],
                $controllerToken['hash']
            );
            foreach ($room['players'] as $slot => $player) {
                $this->tokens->register($room['code'], $slot, null, $player['name'], $player['color'], null);
            }
            $room['pack_name'] = $selection['packName'];
            $room['participant_token'] = $controllerToken['token'];
            return $room;
        });
    }

    public function joinOnline(?array $user, string $code, string $name, string $color): array
    {
        return $this->tokens->transaction(function () use ($user, $code, $name, $color): array {
            $userId = $this->eligibleUserId($user);
            $room = $this->rooms->joinRoom($code, $name, $color);
            $slot = count($room['players']) - 1;
            $token = $this->tokens->issue();
            $this->tokens->register(
                $room['code'],
                $slot,
                $userId,
                $room['players'][$slot]['name'],
                $room['players'][$slot]['color'],
                $token['hash']
            );
            $room['participant_token'] = $token['token'];
            return $room;
        });
    }

    private function eligibleUserId(?array $user): ?int
    {
        if ($user === null || ($user['status'] ?? '') !== 'active' || ($user['email_verified_at'] ?? null) === null) {
            return null;
        }

        return (int) $user['id'];
    }
}
