<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/GameEngine.php';
require_once __DIR__ . '/../src/QuestionImporter.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/QuestionRepository.php';
require_once __DIR__ . '/../src/RoomRepository.php';

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $fn): void
    {
        try {
            $fn();
            $this->passed++;
            echo "PASS {$name}\n";
        } catch (Throwable $e) {
            $this->failed++;
            echo "FAIL {$name}: {$e->getMessage()}\n";
        }
    }

    public function finish(): void
    {
        echo "\n{$this->passed} passed, {$this->failed} failed\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = $message !== '' ? "{$message}: " : '';
        throw new RuntimeException($detail . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertContainsValue(mixed $needle, array $haystack, string $message = ''): void
{
    if (!in_array($needle, $haystack, true)) {
        $detail = $message !== '' ? "{$message}: " : '';
        throw new RuntimeException($detail . 'missing ' . var_export($needle, true) . ' in ' . var_export($haystack, true));
    }
}

function assertTrueValue(bool $actual, string $message = ''): void
{
    if (!$actual) {
        throw new RuntimeException($message !== '' ? $message : 'expected true, got false');
    }
}

function testPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    Database::createSchema($pdo);

    return $pdo;
}

$runner = new TestRunner();

$runner->test('each radial spoke contains multiple categories', function (): void {
    $spaces = GameEngine::boardSpaces();

    for ($spoke = 0; $spoke < 6; $spoke++) {
        $categories = [];
        foreach ($spaces as $space) {
            if (($space['track'] ?? null) === 'spoke' && ($space['spoke'] ?? null) === $spoke && $space['category'] !== null) {
                $categories[$space['category']] = true;
            }
        }

        assertTrueValue(count($categories) >= 4, "spoke {$spoke} should contain mixed category spaces");
    }
});

$runner->test('each radial spoke has five spaces before its wedge', function (): void {
    $spaces = GameEngine::boardSpaces();
    $graph = GameEngine::graph();

    foreach (GameEngine::categories() as $spoke => $category) {
        $radialSpaces = array_filter(
            $spaces,
            fn (array $space): bool => ($space['track'] ?? null) === 'spoke' && ($space['spoke'] ?? null) === $spoke
        );

        assertSameValue(5, count($radialSpaces), "spoke {$spoke} should have five radial spaces");
        assertTrueValue(isset($spaces["r{$spoke}_5"]), "spoke {$spoke} missing fifth radial space");
        assertContainsValue("wedge_{$category['slug']}", $graph["r{$spoke}_5"], "spoke {$spoke} should connect to wedge");
    }
});

$runner->test('roll again spaces are integrated into the main board path', function (): void {
    $spaces = GameEngine::boardSpaces();
    $graph = GameEngine::graph();
    $rollAgain = array_filter($spaces, fn (array $space): bool => $space['type'] === 'roll_again');

    assertSameValue(12, count($rollAgain));
    foreach ($rollAgain as $space) {
        assertSameValue('outer', $space['track']);
        assertTrueValue(isset($graph[$space['id']]), $space['id'] . ' missing from graph');
        assertTrueValue(count($graph[$space['id']]) >= 2, $space['id'] . ' should be connected as part of a path');
    }
});

$runner->test('each outer sector has six spaces between wedges with two rerolls', function (): void {
    $spaces = GameEngine::boardSpaces();

    for ($spoke = 0; $spoke < 6; $spoke++) {
        $sectorSpaces = array_filter(
            $spaces,
            fn (array $space): bool => ($space['track'] ?? null) === 'outer'
                && ($space['spoke'] ?? null) === $spoke
                && $space['type'] !== 'wedge'
        );
        $rerolls = array_filter($sectorSpaces, fn (array $space): bool => $space['type'] === 'roll_again');

        assertSameValue(6, count($sectorSpaces), "sector {$spoke} should have six spaces between wedges");
        assertSameValue(2, count($rerolls), "sector {$spoke} should have two rerolls");
        assertTrueValue(isset($spaces["roll_again_{$spoke}_1"]), "sector {$spoke} missing first reroll");
        assertTrueValue(isset($spaces["roll_again_{$spoke}_2"]), "sector {$spoke} missing second reroll");
    }
});

$runner->test('wedges are reachable from center with exact six-step rolls', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    $state = GameEngine::roll($state, 0, 6);

    foreach (GameEngine::categories() as $category) {
        assertContainsValue("wedge_{$category['slug']}", $state['validDestinations']);
    }
});

$runner->test('board spaces expose visual metadata for svg rendering', function (): void {
    $spaces = GameEngine::boardSpaces();

    foreach ($spaces as $space) {
        assertTrueValue(isset($space['visual']), $space['id'] . ' missing visual metadata');
        assertTrueValue(isset($space['visual']['shape']), $space['id'] . ' missing shape metadata');
    }

    assertSameValue('hub', $spaces['center']['visual']['shape']);
    assertSameValue('outer_segment', $spaces['roll_again_0_1']['visual']['shape']);
    assertSameValue('spoke_segment', $spaces['r0_1']['visual']['shape']);
    assertSameValue('wedge_headquarters', $spaces['wedge_geography']['visual']['shape']);
});

$runner->test('board visual metadata keeps wedges clear of neighbours and aligned with spokes', function (): void {
    $spaces = GameEngine::boardSpaces();
    $outerSpaces = array_filter($spaces, fn (array $space): bool => ($space['track'] ?? null) === 'outer');
    $slotAngle = 360 / count($outerSpaces);

    foreach (GameEngine::categories() as $spoke => $category) {
        $wedge = $spaces["wedge_{$category['slug']}"];
        $finalSpoke = $spaces["r{$spoke}_5"];

        assertTrueValue(isset($wedge['visual']['angleWidth']), $wedge['id'] . ' missing angle width');
        assertTrueValue(isset($wedge['visual']['inner']), $wedge['id'] . ' missing inner radius');
        assertTrueValue(isset($wedge['visual']['outer']), $wedge['id'] . ' missing outer radius');
        assertTrueValue(isset($finalSpoke['visual']['angleWidth']), $finalSpoke['id'] . ' missing angle width');

        $outerNeighbourWidth = $spaces["o{$spoke}_1"]['visual']['angleWidth'];
        assertTrueValue(
            (($wedge['visual']['angleWidth'] + $outerNeighbourWidth) / 2) < $slotAngle,
            $wedge['id'] . ' should leave angular space beside neighbouring outer spaces'
        );
        assertTrueValue(
            $finalSpoke['visual']['angleWidth'] <= $wedge['visual']['angleWidth'],
            $finalSpoke['id'] . ' should not be wider than its wedge connection'
        );
        assertSameValue(
            $finalSpoke['visual']['outer'],
            $wedge['visual']['inner'],
            $finalSpoke['id'] . ' should touch the inner edge of its wedge'
        );
    }
});

$runner->test('board exposes selectable destinations after a dice roll from center', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    $state = GameEngine::roll($state, 0, 3);

    assertSameValue('choose_move', $state['phase']);
    assertSameValue(3, $state['dice']);
    assertContainsValue('r0_3', $state['validDestinations']);
    assertContainsValue('r1_3', $state['validDestinations']);
    assertContainsValue('r5_3', $state['validDestinations']);
});

$runner->test('correct answer grants wedge on matching wedge space and keeps turn', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    $state = GameEngine::roll($state, 0, 6);
    $state = GameEngine::move($state, 0, 'wedge_geography');
    $state['currentQuestion'] = [
        'id' => 10,
        'category' => 'geography',
        'correct' => 2,
    ];
    $state = GameEngine::answer($state, 0, 2);

    assertSameValue(true, $state['players'][0]['wedges']['geography']);
    assertSameValue(0, $state['currentPlayer']);
    assertSameValue('roll', $state['phase']);
    assertSameValue('correct', $state['lastResult']['type']);
});

$runner->test('wrong answer passes turn without granting wedge', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    $state = GameEngine::roll($state, 0, 6);
    $state = GameEngine::move($state, 0, 'wedge_history');
    $state['currentQuestion'] = [
        'id' => 11,
        'category' => 'history',
        'correct' => 1,
    ];
    $state = GameEngine::answer($state, 0, 3);

    assertSameValue(false, $state['players'][0]['wedges']['history']);
    assertSameValue(1, $state['currentPlayer']);
    assertSameValue('roll', $state['phase']);
    assertSameValue('wrong', $state['lastResult']['type']);
});

$runner->test('roll again space skips question and keeps the same turn', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    $state['players'][0]['position'] = 'o0_1';
    $state = GameEngine::roll($state, 0, 1);
    $state = GameEngine::move($state, 0, 'roll_again_0_1');

    assertSameValue(0, $state['currentPlayer']);
    assertSameValue('roll', $state['phase']);
    assertSameValue('roll_again', $state['lastResult']['type']);
});

$runner->test('final question win requires all wedges and center answer', function (): void {
    $state = GameEngine::newGame([
        ['name' => 'Equipo Azul', 'color' => '#2563eb'],
        ['name' => 'Equipo Rojo', 'color' => '#dc2626'],
    ], 'online');

    foreach (GameEngine::categories() as $category) {
        $state['players'][0]['wedges'][$category['slug']] = true;
    }

    $state['players'][0]['position'] = 'r0_1';
    $state = GameEngine::roll($state, 0, 1);
    $state = GameEngine::move($state, 0, 'center');
    $state['currentQuestion'] = [
        'id' => 12,
        'category' => 'science',
        'correct' => 0,
    ];
    $state = GameEngine::answer($state, 0, 0);

    assertSameValue('finished', $state['phase']);
    assertSameValue(0, $state['winner']);
});

$runner->test('question importer accepts valid csv rows', function (): void {
    $csv = "category,question,option_a,option_b,option_c,option_d,correct\n"
        . "geography,Capital de Francia,Paris,Lyon,Burdeos,Niza,0\n"
        . "science,Planeta rojo,Venus,Marte,Jupiter,Saturno,1\n";

    $rows = QuestionImporter::fromCsv($csv);

    assertSameValue(2, count($rows));
    assertSameValue('geography', $rows[0]['category']);
    assertSameValue(['Paris', 'Lyon', 'Burdeos', 'Niza'], $rows[0]['options']);
    assertSameValue(0, $rows[0]['correct']);
});

$runner->test('question importer rejects rows without four options', function (): void {
    $csv = "category,question,option_a,option_b,option_c,option_d,correct\n"
        . "history,Fecha clave,1492,1789,,1914,0\n";

    try {
        QuestionImporter::fromCsv($csv);
    } catch (InvalidArgumentException $e) {
        assertSameValue(true, str_contains($e->getMessage(), 'cuatro opciones'));
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException.');
});

$runner->test('question importer rejects unknown categories', function (): void {
    $csv = "category,question,option_a,option_b,option_c,option_d,correct\n"
        . "unknown,Pregunta,A,B,C,D,0\n";

    try {
        QuestionImporter::fromCsv($csv);
    } catch (InvalidArgumentException $e) {
        assertSameValue(true, str_contains($e->getMessage(), 'categoria'));
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException.');
});

$runner->test('question repository stores and fetches questions by category', function (): void {
    $pdo = testPdo();
    $repo = new QuestionRepository($pdo);
    $repo->replaceAll([
        [
            'category' => 'geography',
            'question' => 'Capital de Francia',
            'options' => ['Paris', 'Lyon', 'Burdeos', 'Niza'],
            'correct' => 0,
        ],
    ]);

    $question = $repo->randomByCategory('geography');

    assertSameValue('Capital de Francia', $question['question']);
    assertSameValue(['Paris', 'Lyon', 'Burdeos', 'Niza'], $question['options']);
});

$runner->test('room repository creates lobby, joins player and starts game', function (): void {
    $pdo = testPdo();
    $repo = new RoomRepository($pdo);

    $room = $repo->createRoom('online', 'auto', 'Equipo Azul', '#2563eb');
    $repo->joinRoom($room['code'], 'Equipo Rojo', '#dc2626');
    $started = $repo->startGame($room['code']);

    assertSameValue('roll', $started['state']['phase']);
    assertSameValue(2, count($started['state']['players']));
    assertSameValue('Equipo Rojo', $started['state']['players'][1]['name']);
});

$runner->finish();
