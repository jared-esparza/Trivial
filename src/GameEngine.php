<?php

declare(strict_types=1);

final class GameEngine
{
    public static function categories(): array
    {
        return [
            ['slug' => 'geography', 'name' => 'Geografia', 'color' => '#2f80ed'],
            ['slug' => 'art', 'name' => 'Arte y literatura', 'color' => '#7b3ff2'],
            ['slug' => 'history', 'name' => 'Historia', 'color' => '#f2c94c'],
            ['slug' => 'entertainment', 'name' => 'Entretenimiento', 'color' => '#eb5757'],
            ['slug' => 'science', 'name' => 'Ciencia y naturaleza', 'color' => '#27ae60'],
            ['slug' => 'sports', 'name' => 'Deportes y ocio', 'color' => '#f2994a'],
        ];
    }

    public static function newGame(array $players, string $mode): array
    {
        if (count($players) < 2 || count($players) > 6) {
            throw new InvalidArgumentException('La partida necesita entre 2 y 6 equipos.');
        }

        $wedges = [];
        foreach (self::categories() as $category) {
            $wedges[$category['slug']] = false;
        }

        $normalizedPlayers = [];
        foreach (array_values($players) as $index => $player) {
            $normalizedPlayers[] = [
                'id' => $index,
                'name' => trim((string) ($player['name'] ?? 'Equipo ' . ($index + 1))),
                'color' => (string) ($player['color'] ?? self::playerColors()[$index]),
                'position' => 'center',
                'wedges' => $wedges,
            ];
        }

        return [
            'mode' => $mode,
            'phase' => 'roll',
            'currentPlayer' => 0,
            'players' => $normalizedPlayers,
            'dice' => null,
            'validDestinations' => [],
            'pendingSpace' => null,
            'currentQuestion' => null,
            'lastResult' => null,
            'winner' => null,
            'version' => 1,
        ];
    }

    public static function roll(array $state, int $playerId, ?int $forcedDice = null): array
    {
        self::assertCurrentPlayer($state, $playerId);
        self::assertPhase($state, 'roll');

        $dice = $forcedDice ?? random_int(1, 6);
        if ($dice < 1 || $dice > 6) {
            throw new InvalidArgumentException('El dado debe estar entre 1 y 6.');
        }

        $position = $state['players'][$playerId]['position'];
        $destinations = self::destinationsFrom($position, $dice);

        $state['dice'] = $dice;
        $state['validDestinations'] = $destinations;
        $state['phase'] = 'choose_move';
        $state['lastResult'] = ['type' => 'rolled', 'dice' => $dice];

        return self::bumpVersion($state);
    }

    public static function move(array $state, int $playerId, string $destination): array
    {
        self::assertCurrentPlayer($state, $playerId);
        self::assertPhase($state, 'choose_move');

        if (!in_array($destination, $state['validDestinations'], true)) {
            throw new InvalidArgumentException('Destino no valido para esta tirada.');
        }

        $space = self::space($destination);
        $state['players'][$playerId]['position'] = $destination;
        $state['validDestinations'] = [];
        $state['pendingSpace'] = $space;
        $state['currentQuestion'] = null;

        if ($space['type'] === 'roll_again') {
            $state['phase'] = 'roll';
            $state['dice'] = null;
            $state['pendingSpace'] = null;
            $state['lastResult'] = ['type' => 'roll_again', 'player' => $playerId];

            return self::bumpVersion($state);
        }

        if ($space['type'] === 'center' && self::hasAllWedges($state['players'][$playerId])) {
            $state['phase'] = 'question';
            $state['pendingSpace']['final'] = true;
            $state['pendingSpace']['category'] = self::randomCategorySlug();
            $state['lastResult'] = ['type' => 'final_question', 'player' => $playerId];

            return self::bumpVersion($state);
        }

        if ($space['type'] === 'center') {
            $state['phase'] = 'roll';
            $state['dice'] = null;
            $state['pendingSpace'] = null;
            $state['lastResult'] = ['type' => 'center_wait', 'player' => $playerId];

            return self::bumpVersion($state);
        }

        $state['phase'] = 'question';
        $state['lastResult'] = [
            'type' => 'question',
            'player' => $playerId,
            'category' => $space['category'],
            'wedge' => $space['type'] === 'wedge',
        ];

        return self::bumpVersion($state);
    }

    public static function attachQuestion(array $state, array $question): array
    {
        self::assertPhase($state, 'question');
        $state['currentQuestion'] = [
            'id' => (int) $question['id'],
            'category' => (string) $question['category'],
            'question' => (string) ($question['question'] ?? ''),
            'options' => $question['options'] ?? [],
            'correct' => (int) $question['correct'],
        ];

        return self::bumpVersion($state);
    }

    public static function answer(array $state, int $playerId, int|bool $answer): array
    {
        self::assertCurrentPlayer($state, $playerId);
        self::assertPhase($state, 'question');

        if ($state['currentQuestion'] === null) {
            throw new InvalidArgumentException('No hay pregunta activa.');
        }

        $correct = is_bool($answer)
            ? $answer
            : (int) $answer === (int) $state['currentQuestion']['correct'];

        $space = $state['pendingSpace'];
        $state['lastResult'] = [
            'type' => $correct ? 'correct' : 'wrong',
            'player' => $playerId,
            'questionId' => $state['currentQuestion']['id'],
            'correctOption' => $state['currentQuestion']['correct'],
        ];
        $state['currentQuestion'] = null;
        $state['pendingSpace'] = null;
        $state['dice'] = null;

        if ($correct && ($space['final'] ?? false)) {
            $state['phase'] = 'finished';
            $state['winner'] = $playerId;

            return self::bumpVersion($state);
        }

        if ($correct && $space['type'] === 'wedge') {
            $state['players'][$playerId]['wedges'][$space['category']] = true;
        }

        if ($correct) {
            $state['phase'] = 'roll';

            return self::bumpVersion($state);
        }

        $state['phase'] = 'roll';
        $state['currentPlayer'] = self::nextPlayerIndex($state);

        return self::bumpVersion($state);
    }

    public static function boardSpaces(): array
    {
        $spaces = [];
        foreach (self::boardDefinition() as $space) {
            $spaces[$space['id']] = $space;
        }

        return $spaces;
    }

    public static function boardDefinition(): array
    {
        $categories = self::categories();
        $categoryBySlug = [];
        foreach ($categories as $category) {
            $categoryBySlug[$category['slug']] = $category;
        }
        $categorySlugs = array_column($categories, 'slug');
        $spaces = [[
            'id' => 'center',
            'type' => 'center',
            'label' => 'Centro',
            'category' => null,
            'track' => 'hub',
            'spoke' => null,
            'index' => 0,
            'visual' => ['shape' => 'hub'],
        ]];

        $outerIndex = 0;
        foreach ($categories as $spoke => $category) {
            $slug = $category['slug'];
            $spokeSequence = [
                $categorySlugs[($spoke + 2) % 6],
                $categorySlugs[($spoke + 4) % 6],
                $categorySlugs[($spoke + 3) % 6],
                $categorySlugs[($spoke + 1) % 6],
                $categorySlugs[($spoke + 5) % 6],
            ];

            foreach ($spokeSequence as $index => $spaceCategory) {
                $spaceNumber = $index + 1;
                $spaces[] = [
                    'id' => "r{$spoke}_{$spaceNumber}",
                    'type' => 'category',
                    'label' => $categoryBySlug[$spaceCategory]['name'],
                    'category' => $spaceCategory,
                    'track' => 'spoke',
                    'spoke' => $spoke,
                    'index' => $spaceNumber,
                    'visual' => [
                        'shape' => 'spoke_segment',
                        'inner' => 42 + ($spaceNumber - 1) * 36,
                        'outer' => 78 + ($spaceNumber - 1) * 36,
                        'angleWidth' => 9.0,
                    ],
                ];
            }

            $spaces[] = [
                'id' => "wedge_{$slug}",
                'type' => 'wedge',
                'label' => 'Quesito: ' . $category['name'],
                'category' => $slug,
                'track' => 'outer',
                'spoke' => $spoke,
                'index' => $outerIndex++,
                'visual' => [
                    'shape' => 'wedge_headquarters',
                    'inner' => 222,
                    'outer' => 294,
                    'angleWidth' => 9.2,
                ],
            ];

            $rerollNumber = 1;
            for ($outer = 1; $outer <= 6; $outer++) {
                if ($outer === 2 || $outer === 5) {
                    $spaces[] = [
                        'id' => "roll_again_{$spoke}_{$rerollNumber}",
                        'type' => 'roll_again',
                        'label' => 'Vuelve a tirar',
                        'category' => null,
                        'track' => 'outer',
                        'spoke' => $spoke,
                        'index' => $outerIndex++,
                        'visual' => [
                            'shape' => 'outer_segment',
                            'inner' => 236,
                            'outer' => 286,
                            'angleWidth' => 6.8,
                        ],
                    ];
                    $rerollNumber++;
                    continue;
                }

                $outerCategory = $categorySlugs[($spoke + $outer) % 6];
                $spaces[] = [
                    'id' => "o{$spoke}_{$outer}",
                    'type' => 'category',
                    'label' => $categoryBySlug[$outerCategory]['name'],
                    'category' => $outerCategory,
                    'track' => 'outer',
                    'spoke' => $spoke,
                    'index' => $outerIndex++,
                    'visual' => [
                        'shape' => 'outer_segment',
                        'inner' => 236,
                        'outer' => 286,
                        'angleWidth' => 6.8,
                    ],
                ];
            }
        }

        return $spaces;
    }

    public static function graph(): array
    {
        $categories = self::categories();
        $graph = ['center' => []];

        foreach ($categories as $spoke => $category) {
            $slug = $category['slug'];
            self::connect($graph, 'center', "r{$spoke}_1");
            for ($spaceNumber = 1; $spaceNumber < 5; $spaceNumber++) {
                self::connect($graph, "r{$spoke}_{$spaceNumber}", "r{$spoke}_" . ($spaceNumber + 1));
            }
            self::connect($graph, "r{$spoke}_5", "wedge_{$slug}");
        }

        $outerSpaces = array_values(array_filter(
            self::boardDefinition(),
            fn (array $space): bool => $space['track'] === 'outer'
        ));
        usort($outerSpaces, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        $total = count($outerSpaces);
        for ($i = 0; $i < $total; $i++) {
            $current = $outerSpaces[$i]['id'];
            $next = $outerSpaces[($i + 1) % $total]['id'];
            self::connect($graph, $current, $next);
        }

        return $graph;
    }

    public static function destinationsFrom(string $start, int $steps): array
    {
        $graph = self::graph();
        if (!isset($graph[$start])) {
            throw new InvalidArgumentException('Posicion desconocida.');
        }

        $frontier = [[$start, 0, [$start]]];
        $destinations = [];

        while ($frontier !== []) {
            [$node, $distance, $path] = array_shift($frontier);
            if ($distance === $steps) {
                if ($node !== $start) {
                    $destinations[$node] = true;
                }
                continue;
            }

            foreach ($graph[$node] as $next) {
                if (in_array($next, $path, true)) {
                    continue;
                }
                $nextPath = $path;
                $nextPath[] = $next;
                $frontier[] = [$next, $distance + 1, $nextPath];
            }
        }

        $result = array_keys($destinations);
        sort($result);

        return $result;
    }

    public static function space(string $id): array
    {
        $spaces = self::boardSpaces();
        if (!isset($spaces[$id])) {
            throw new InvalidArgumentException('Casilla desconocida.');
        }

        return $spaces[$id];
    }

    private static function connect(array &$graph, string $a, string $b): void
    {
        $graph[$a] ??= [];
        $graph[$b] ??= [];
        if (!in_array($b, $graph[$a], true)) {
            $graph[$a][] = $b;
        }
        if (!in_array($a, $graph[$b], true)) {
            $graph[$b][] = $a;
        }
    }

    private static function assertCurrentPlayer(array $state, int $playerId): void
    {
        if (($state['currentPlayer'] ?? null) !== $playerId) {
            throw new InvalidArgumentException('No es el turno de este equipo.');
        }
    }

    private static function assertPhase(array $state, string $phase): void
    {
        if (($state['phase'] ?? null) !== $phase) {
            throw new InvalidArgumentException("La partida no esta en fase {$phase}.");
        }
    }

    private static function bumpVersion(array $state): array
    {
        $state['version'] = (int) ($state['version'] ?? 0) + 1;

        return $state;
    }

    private static function nextPlayerIndex(array $state): int
    {
        return ((int) $state['currentPlayer'] + 1) % count($state['players']);
    }

    private static function hasAllWedges(array $player): bool
    {
        foreach ($player['wedges'] as $hasWedge) {
            if (!$hasWedge) {
                return false;
            }
        }

        return true;
    }

    private static function randomCategorySlug(): string
    {
        $categories = self::categories();

        return $categories[random_int(0, count($categories) - 1)]['slug'];
    }

    private static function playerColors(): array
    {
        return ['#2563eb', '#dc2626', '#16a34a', '#ca8a04', '#9333ea', '#0891b2'];
    }
}
