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

    assertSameValue('hex_hub', $spaces['center']['visual']['shape']);
    assertSameValue('outer_segment', $spaces['roll_again_0_1']['visual']['shape']);
    assertSameValue('straight_spoke', $spaces['r0_1']['visual']['shape']);
    assertSameValue('wedge_headquarters', $spaces['wedge_geography']['visual']['shape']);
});

$runner->test('board render separates fills from selection outlines', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($styles, '.space-track-spoke'), 'spoke spaces should have track-specific styling');
    assertTrueValue(str_contains($styles, '.space-track-spoke') && str_contains($styles, 'stroke: none'), 'spoke spaces should not draw white borders');
    assertTrueValue(str_contains($styles, '.space-highlight'), 'selection outlines should use a dedicated top layer');
    assertTrueValue(str_contains($appJs, 'const highlightMarkup'), 'board should render highlight markup separately');
    assertTrueValue(
        strpos($appJs, '${highlightMarkup}') > strpos($appJs, '${spaceMarkup}'),
        'selection outlines should render after board spaces'
    );
});

$runner->test('board preferences can toggle white space borders', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($index, 'id="preferencesOverlay"'), 'game view should expose a preferences overlay');
    assertTrueValue(str_contains($appJs, 'renderPreferences'), 'room render should include preferences');
    assertTrueValue(str_contains($appJs, 'board:whiteBorders'), 'white border preference should persist locally');
    assertTrueValue(str_contains($appJs, 'show-space-borders'), 'board svg should receive a class when borders are enabled');
    assertTrueValue(str_contains($styles, '.board-svg.show-space-borders'), 'css should define enabled white board borders');
});

$runner->test('game board exposes fullscreen controls and fullscreen layout styles', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($index, 'id="fullscreenBoardButton"'), 'board header should expose a fullscreen button');
    assertTrueValue(str_contains($appJs, 'bindFullscreenControls'), 'game forms should bind fullscreen controls');
    assertTrueValue(str_contains($appJs, 'requestFullscreen'), 'fullscreen button should use the Fullscreen API');
    assertTrueValue(str_contains($appJs, 'fullscreen-fallback'), 'fullscreen should have a css fallback');
    assertTrueValue(str_contains($styles, '.game-view:fullscreen'), 'fullscreen layout should style the game view');
    assertTrueValue(str_contains($styles, '.fullscreen-fallback'), 'css fallback should style fullscreen mode');
});

$runner->test('scoreboard is integrated near the board without changing board rendering', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($index, 'id="scoreboardBox"'), 'game board panel should expose a scoreboard box');
    assertTrueValue(strpos($index, 'id="scoreboardBox"') < strpos($index, 'id="boardMount"'), 'scoreboard should sit near the board, outside board mount');
    assertTrueValue(str_contains($appJs, 'renderScoreboard'), 'room render should include a scoreboard helper');
    assertTrueValue(str_contains($appJs, 'scoreboard-card'), 'scoreboard should render player cards');
    assertTrueValue(str_contains($appJs, 'scoreboard-active'), 'scoreboard should mark the active player');
    assertTrueValue(str_contains($appJs, 'renderScoreboardWedgeWheel'), 'scoreboard should render circular wedge wheels');
    assertTrueValue(str_contains($appJs, 'wedgeCount'), 'scoreboard should calculate owned wedge progress');
    assertTrueValue(str_contains($styles, '.scoreboard-box'), 'scoreboard should have dedicated layout styles');
    assertTrueValue(str_contains($styles, '.scoreboard-card'), 'scoreboard player cards should have dedicated styles');
    assertTrueValue(str_contains($styles, '@keyframes scoreboard-turn-pulse'), 'active scoreboard card should have a subtle animation');
});

$runner->test('scoreboard wedge progress renders as a circular medallion', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'function renderScoreboardWedgeWheel'), 'scoreboard should expose a wheel helper');
    assertTrueValue(str_contains($appJs, 'scoreboard-wheel'), 'scoreboard should render an inline wheel svg');
    assertTrueValue(str_contains($appJs, 'scoreboard-wheel-slice'), 'wheel should render category slices');
    assertTrueValue(str_contains($appJs, 'scoreboard-wheel-slice-owned'), 'wheel should distinguish owned slices');
    assertTrueValue(str_contains($appJs, 'scoreboardWheelSlicePath'), 'wheel slices should be generated as svg paths');
    assertTrueValue(!str_contains($appJs, '<span class="scoreboard-wedge'), 'scoreboard should not render the old linear wedge list');
    assertTrueValue(str_contains($styles, '.scoreboard-wheel'), 'wheel svg should have dedicated styles');
    assertTrueValue(str_contains($styles, '.scoreboard-wheel-slice'), 'wheel slices should have dedicated styles');
    assertTrueValue(str_contains($styles, '.scoreboard-wheel-slice-owned'), 'owned wheel slices should have dedicated styles');
});

$runner->test('fullscreen scoreboard stays compact and board mount can shrink', function (): void {
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($styles, 'overflow: hidden;'), 'fullscreen layout should avoid vertical overflow in the board area');
    assertTrueValue(str_contains($styles, '.game-view:fullscreen .scoreboard-box'), 'fullscreen should compact the scoreboard box');
    assertTrueValue(str_contains($styles, '.game-view:fullscreen .scoreboard-card'), 'fullscreen should compact scoreboard cards');
    assertTrueValue(str_contains($styles, '.game-view:fullscreen .board-mount'), 'fullscreen should explicitly size the board mount');
    assertTrueValue(str_contains($styles, 'grid-template-rows: auto auto minmax(0, 1fr);'), 'fullscreen board panel should reserve remaining space for the board');
    assertTrueValue(str_contains($styles, 'height: 100%;'), 'fullscreen board mount should fill the remaining row');
    assertTrueValue(str_contains($styles, 'max-height: 100%;'), 'fullscreen board frame should respect available height');
    assertTrueValue(str_contains($styles, 'grid-template-columns: minmax(0, 1fr);'), 'fullscreen should stay in a single-column board layout');
});

$runner->test('game view removes the sidebar and exposes top bar game controls', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(!str_contains($index, '<aside class="side-panel">'), 'game view should not render the old right sidebar');
    assertTrueValue(!str_contains($index, 'id="statusBox"'), 'status box should be removed from the game view');
    assertTrueValue(!str_contains($index, 'id="playersBox"'), 'players box should be removed from the game view');
    assertTrueValue(!str_contains($index, 'id="controlsBox"'), 'controls box should be removed from the game view');
    assertTrueValue(str_contains($index, 'id="topDiceStatus"'), 'top bar should expose a compact dice status');
    assertTrueValue(str_contains($index, 'id="preferencesButton"'), 'top bar should expose a preferences button');
    assertTrueValue(str_contains($appJs, 'renderTopDiceStatus'), 'room render should update the compact top dice status');
    assertTrueValue(str_contains($styles, 'grid-template-columns: minmax(0, 1fr);'), 'game view should be a centered single-column layout');
}
);

$runner->test('home exposes separate local setup view and navigation controls', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($index, 'id="homeView"'), 'home view should remain available');
    assertTrueValue(str_contains($index, 'id="localSetupView"'), 'local setup should be a separate view');
    assertTrueValue(str_contains($index, 'id="openLocalSetupButton"'), 'home should expose a local setup navigation button');
    assertTrueValue(str_contains($index, 'id="backHomeButton"'), 'local setup should expose a back button');
    assertTrueValue(str_contains($index, 'id="localSetupTeamCount"'), 'local setup should expose a live team counter');
    assertTrueValue(strpos($index, 'id="localSetupView"') < strpos($index, 'id="gameView"'), 'local setup should sit before the game view');
    assertTrueValue(str_contains($appJs, 'bindHomeNavigation'), 'frontend should bind home/local navigation');
    assertTrueValue(str_contains($appJs, 'updateLocalSetupTeamCount'), 'frontend should update the local team counter');
    assertTrueValue(str_contains($styles, '.local-setup-view'), 'local setup should have dedicated view styles');
    assertTrueValue(str_contains($styles, '.home-hero'), 'redesigned home hero should have dedicated styles');
});

$runner->test('landing forms keep online creation and join data on the home screen', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $homeStart = strpos($index, 'id="homeView"');
    $localStart = strpos($index, 'id="localSetupView"');
    $homeMarkup = substr($index, $homeStart, $localStart - $homeStart);

    assertTrueValue(str_contains($homeMarkup, 'id="onlineCreateForm"'), 'online create form should stay on the home view');
    assertTrueValue(str_contains($homeMarkup, 'name="teamName"'), 'online create form should ask for the team name');
    assertTrueValue(str_contains($homeMarkup, 'id="joinForm"'), 'join form should stay on the home view');
    assertTrueValue(str_contains($homeMarkup, 'name="code"'), 'join form should ask for a room code');
    assertTrueValue(str_contains($homeMarkup, 'name="teamName"'), 'join form should ask for a team name');
});

$runner->test('preferences are rendered in a floating overlay opened from a gear button', function (): void {
    $index = file_get_contents(__DIR__ . '/../public/index.php');
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');
    $gameStart = strpos($index, 'id="gameView"');
    $gameEnd = strpos($index, '</section>', $gameStart);
    $overlayPosition = strpos($index, 'id="preferencesOverlay"');

    assertTrueValue(str_contains($index, 'id="preferencesOverlay"'), 'page should expose a preferences overlay container');
    assertTrueValue($gameStart !== false && $gameEnd !== false && $overlayPosition !== false && $overlayPosition > $gameStart && $overlayPosition < $gameEnd, 'preferences overlay should be inside gameView so it is visible in fullscreen');
    assertTrueValue(str_contains($index, 'aria-label="Abrir preferencias"'), 'preferences button should be accessible');
    assertTrueValue(str_contains($appJs, 'bindPreferencesOverlayControls'), 'preferences overlay should bind open and close controls');
    assertTrueValue(str_contains($appJs, 'renderPreferencesOverlay'), 'preferences should render into a floating overlay');
    assertTrueValue(str_contains($appJs, 'preferencesOverlayOpen'), 'preferences overlay should use explicit open state');
    assertTrueValue(str_contains($appJs, 'board:whiteBorders'), 'white border preference should keep the existing key');
    assertTrueValue(str_contains($appJs, 'board:pulseDestinations'), 'pulse destination preference should keep the existing key');
    assertTrueValue(str_contains($appJs, 'board:animateTokens'), 'token animation preference should keep the existing key');
    assertTrueValue(str_contains($appJs, 'board:diceResultDelayMs'), 'dice delay preference should keep the existing key');
    assertTrueValue(str_contains($styles, '.preferences-overlay'), 'preferences overlay should have modal styles');
    assertTrueValue(str_contains($styles, '.preferences-card'), 'preferences card should have dedicated styles');
});

$runner->test('lobby and finished phases render board overlays without sidebar controls', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderLobbyOverlay'), 'lobby should render a board overlay');
    assertTrueValue(str_contains($appJs, 'renderFinishedOverlay'), 'finished phase should render a board overlay');
    assertTrueValue(str_contains($appJs, 'startButton'), 'lobby overlay should keep the start game action');
    assertTrueValue(str_contains($appJs, 'renderDiceRollOverlay(state, canAct)'), 'dice roll overlay should remain the main roll action');
    assertTrueValue(!str_contains($appJs, 'function renderControls'), 'sidebar controls renderer should be removed');
    assertTrueValue(!str_contains($appJs, 'function renderPlayers'), 'sidebar players renderer should be removed');
    assertTrueValue(!str_contains($appJs, 'function renderStatus'), 'sidebar status renderer should be removed');
    assertTrueValue(str_contains($styles, '.lobby-overlay-card'), 'lobby overlay should have dedicated styles');
    assertTrueValue(str_contains($styles, '.finished-overlay-card'), 'finished overlay should have dedicated styles');
});

$runner->test('sidebar players become compact summary instead of duplicated player cards', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(!str_contains($appJs, 'players-summary'), 'sidebar players summary should be removed with the sidebar');
    assertTrueValue(!str_contains($appJs, 'Equipo activo'), 'sidebar active team summary should be removed with the sidebar');
    assertTrueValue(!str_contains($appJs, '<article class="player-card ${index === state.currentPlayer ? \'active\' : \'\'}">'), 'sidebar should not duplicate full player cards');
    assertTrueValue(!str_contains($styles, '.players-summary'), 'compact players summary styles should be removed');
    assertTrueValue(!str_contains($styles, '.players-summary-active'), 'active team summary styles should be removed');
});

$runner->test('board uses inline icons and category labels instead of text markers', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderWedgeIcon'), 'wedges should render with an inline svg icon helper');
    assertTrueValue(str_contains($appJs, 'renderRerollIcon'), 'rerolls should render with an inline svg icon helper');
    assertTrueValue(str_contains($appJs, 'labelForSpace'), 'spaces should expose category labels');
    assertTrueValue(str_contains($appJs, '<title>${escapeHtml(labelForSpace(space))}</title>'), 'svg groups should include accessible titles');
    assertTrueValue(str_contains($appJs, 'data-space-label'), 'space elements should carry hover labels');
    assertTrueValue(str_contains($styles, '.space-icon'), 'svg icons should have dedicated styles');
    assertTrueValue(!str_contains($appJs, 'text-anchor="middle">Q</text>'), 'wedge letter marker should be removed');
    assertTrueValue(!str_contains($appJs, 'text-anchor="middle">R</text>'), 'reroll letter marker should be removed');
});

$runner->test('question phase renders a floating board dialog instead of sidebar question controls', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderQuestionOverlay'), 'question controls should render in a board overlay helper');
    assertTrueValue(str_contains($appJs, 'bindQuestionOverlayControls'), 'floating question controls should bind their own answer actions');
    assertTrueValue(!str_contains($appJs, 'renderQuestionSummary'), 'question summaries should not depend on sidebar controls');
    assertTrueValue(str_contains($appJs, 'role="dialog"'), 'floating question should be exposed as a dialog');
    assertTrueValue(str_contains($appJs, 'questionOverlayBackdrop'), 'board should include a modal overlay backdrop');
    assertTrueValue(!str_contains($appJs, 'renderQuestionControls(box'), 'sidebar should not render the full question controls');
    assertTrueValue(str_contains($styles, '.question-overlay'), 'floating question overlay should have dedicated styles');
    assertTrueValue(str_contains($styles, '@keyframes question-pop'), 'floating question should animate in');
});

$runner->test('answer results stay visible in the board card until continued', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'pendingAnswerFeedback'), 'answer feedback should keep local state');
    assertTrueValue(str_contains($appJs, 'submitAnswerWithFeedback'), 'answer buttons should submit through a feedback wrapper');
    assertTrueValue(str_contains($appJs, 'renderAnswerFeedbackOverlay'), 'board should render an answer feedback overlay');
    assertTrueValue(str_contains($appJs, 'buildAnswerFeedback'), 'answer feedback should be built from the server result');
    assertTrueValue(str_contains($appJs, 'answerFeedbackContinue'), 'answer feedback should expose a continue button');
    assertTrueValue(str_contains($appJs, 'correctOption'), 'option mode should show the correct answer when available');
    assertTrueValue(str_contains($styles, '.answer-feedback-card'), 'answer feedback should have dedicated card styles');
    assertTrueValue(str_contains($styles, '.answer-feedback-correct'), 'correct feedback should have a distinct style');
    assertTrueValue(str_contains($styles, '.answer-feedback-wrong'), 'wrong feedback should have a distinct style');
});

$runner->test('wedge icons are rounded and point toward the board center', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderWedgeIcon(point, space)'), 'wedge icon helper should receive the rendered space');
    assertTrueValue(str_contains($appJs, 'wedgeIconRotation(space)'), 'wedge icon rotation should be calculated per space');
    assertTrueValue(str_contains($appJs, 'rotate(${rotation})'), 'wedge icon transform should rotate each icon');
    assertTrueValue(str_contains($appJs, 'C -16 -6 -16 6 -11 10'), 'wedge icon should keep a curved short side');
    assertTrueValue(str_contains($styles, '.wedge-icon path'), 'rounded wedge icon should keep dedicated path styling');
});

$runner->test('wedge icon removes internal dots while keeping straight sides and curved base', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $start = strpos($appJs, 'function renderWedgeIcon');
    $end = strpos($appJs, 'function wedgeIconRotation', $start);
    $wedgeIcon = substr($appJs, $start, $end - $start);

    assertTrueValue(str_contains($wedgeIcon, 'rotate(${rotation})'), 'wedge icon should keep per-space rotation');
    assertTrueValue(!str_contains($wedgeIcon, '<circle'), 'wedge icon should not render internal dots');
    assertTrueValue(str_contains($wedgeIcon, 'L 13 0'), 'wedge icon should use straight long sides');
    assertTrueValue(str_contains($wedgeIcon, 'C -16 -6 -16 6 -11 10'), 'wedge icon should keep a curved short side');
});

$runner->test('board preferences can pulse selectable destinations', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'board:pulseDestinations'), 'destination pulse preference should persist locally');
    assertTrueValue(str_contains($appJs, 'pulseDestinationsToggle'), 'preferences should render a destination pulse toggle');
    assertTrueValue(str_contains($appJs, 'pulse-valid-destinations'), 'board svg should receive a class when pulse is enabled');
    assertTrueValue(str_contains($styles, '.board-svg.pulse-valid-destinations .valid-highlight'), 'css should scope pulse animation to valid highlights');
    assertTrueValue(str_contains($styles, '@keyframes destination-pulse'), 'destination pulse animation should be defined');
});

$runner->test('board preferences can animate token movement', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'board:animateTokens'), 'token animation preference should persist locally');
    assertTrueValue(str_contains($appJs, 'animateTokensToggle'), 'preferences should render a token animation toggle');
    assertTrueValue(str_contains($appJs, 'animateTokensPreferenceEnabled'), 'token animation should be enabled by default through a helper');
    assertTrueValue(str_contains($appJs, 'moveWithTokenAnimation'), 'valid destination clicks should use an animated move wrapper');
    assertTrueValue(str_contains($appJs, 'renderAnimatedToken'), 'board should render a temporary animated token');
    assertTrueValue(str_contains($appJs, 'pendingTokenAnimation'), 'board should keep local token animation state');
    assertTrueValue(str_contains($styles, '.animated-token'), 'animated token should have dedicated styles');
    assertTrueValue(str_contains($styles, '@keyframes token-move'), 'token movement animation should be defined');
});

$runner->test('roll phase uses a floating dice card on the board', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderDiceRollOverlay'), 'roll phase should render a board dice overlay');
    assertTrueValue(str_contains($appJs, 'submitRollFromOverlay'), 'dice card should submit rolls through its own handler');
    assertTrueValue(str_contains($appJs, 'diceRollOverlayButton'), 'dice card should expose the main roll button');
    assertTrueValue(!str_contains($appJs, 'renderRollSummary'), 'roll phase should not depend on sidebar summaries');
    assertTrueValue(!str_contains($appJs, 'box.innerHTML = `<button id="rollButton"'), 'sidebar should not render the main roll button');
    assertTrueValue(str_contains($styles, '.dice-roll-card'), 'dice roll card should have dedicated styles');
    assertTrueValue(str_contains($styles, '.dice-roll-card .dice-face'), 'dice face should be larger in the roll card');
});

$runner->test('preferences overlay includes dice result duration', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'preferencesOverlayOpen'), 'preferences should use modal open state');
    assertTrueValue(str_contains($appJs, 'preferencesCloseButton'), 'preferences should expose a close button');
    assertTrueValue(str_contains($appJs, 'board:diceResultDelayMs'), 'dice result delay should persist locally');
    assertTrueValue(str_contains($appJs, 'diceResultDelaySelect'), 'preferences should render a dice delay select');
    assertTrueValue(str_contains($appJs, 'diceResultDelayPreferenceMs'), 'dice delay should be read through a helper');
    assertTrueValue(str_contains($styles, '.preferences-overlay'), 'preferences modal backdrop should have dedicated styles');
    assertTrueValue(str_contains($styles, '.preferences-card'), 'preferences modal card should have dedicated styles');
    assertTrueValue(str_contains($styles, '.preferences-content'), 'collapsible preferences content should have dedicated styles');
});

$runner->test('dice roll card shows animated result before movement selection', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'pendingDiceRollFeedback'), 'dice roll should keep local feedback state');
    assertTrueValue(str_contains($appJs, 'minimumDiceRollAnimationMs'), 'dice roll should keep a minimum visible rolling animation');
    assertTrueValue(str_contains($appJs, 'setTimeout(() => {'), 'dice result feedback should wait before applying the new room state');
    assertTrueValue(str_contains($appJs, 'Has sacado un'), 'dice card should show the final dice value');
    assertTrueValue(str_contains($appJs, 'dice-roll-result'), 'dice result should have dedicated markup');
    assertTrueValue(str_contains($appJs, 'dice-roll-final'), 'dice card should distinguish final result from rolling state');
    assertTrueValue(str_contains($styles, '.dice-roll-result'), 'dice roll result should have dedicated styles');
    assertTrueValue(str_contains($styles, '.dice-roll-card.dice-roll-final'), 'final dice state should have dedicated styles');
    assertTrueValue(str_contains($styles, '@keyframes dice-tumble'), 'dice overlay should use a stronger tumble animation');
});

$runner->test('status renders visual animated dice results', function (): void {
    $appJs = file_get_contents(__DIR__ . '/../public/assets/app.js');
    $styles = file_get_contents(__DIR__ . '/../public/assets/styles.css');

    assertTrueValue(str_contains($appJs, 'renderDiceResult'), 'status should render dice with a visual helper');
    assertTrueValue(str_contains($appJs, 'renderDiceFace'), 'dice should render physical pips');
    assertTrueValue(str_contains($appJs, 'lastAnimatedDiceKey'), 'dice animation should only trigger for new rolls');
    assertTrueValue(str_contains($appJs, 'Resultado del dado'), 'dice should keep accessible result text');
    assertTrueValue(str_contains($styles, '.dice-result'), 'dice result should have dedicated styles');
    assertTrueValue(str_contains($styles, '@keyframes dice-roll'), 'dice roll animation should be defined');
});

$runner->test('board visual metadata uses straight spokes and a hexagonal center', function (): void {
    $spaces = GameEngine::boardSpaces();
    $hub = $spaces['center']['visual'];

    assertTrueValue(isset($hub['radius']), 'center missing hex radius');
    assertTrueValue(isset($hub['sideLength']), 'center missing hex side length');

    foreach (GameEngine::categories() as $spoke => $category) {
        $wedge = $spaces["wedge_{$category['slug']}"];
        $firstSpoke = $spaces["r{$spoke}_1"];
        $finalSpoke = $spaces["r{$spoke}_5"];

        assertTrueValue(isset($wedge['visual']['angleWidth']), $wedge['id'] . ' missing angle width');
        assertTrueValue(isset($wedge['visual']['angleOffset']), $wedge['id'] . ' missing angle offset');
        assertTrueValue(isset($wedge['visual']['inner']), $wedge['id'] . ' missing inner radius');
        assertTrueValue(isset($wedge['visual']['outer']), $wedge['id'] . ' missing outer radius');
        assertTrueValue(isset($firstSpoke['visual']['width']), $firstSpoke['id'] . ' missing straight width');
        assertTrueValue(isset($finalSpoke['visual']['width']), $finalSpoke['id'] . ' missing straight width');

        assertSameValue('straight_spoke', $firstSpoke['visual']['shape'], $firstSpoke['id'] . ' should render as a straight spoke');
        assertSameValue('curved_spoke_end', $finalSpoke['visual']['shape'], $finalSpoke['id'] . ' should curve into its wedge');
        assertSameValue($hub['radius'], $firstSpoke['visual']['inner'], $firstSpoke['id'] . ' should touch the hexagonal center');
        assertTrueValue(
            abs($hub['sideLength'] - $firstSpoke['visual']['width']) < 0.001,
            $firstSpoke['id'] . ' should match the side length of the hexagonal center'
        );
        assertSameValue($firstSpoke['visual']['width'], $finalSpoke['visual']['width'], $finalSpoke['id'] . ' should keep radial width');
        assertTrueValue(
            abs($finalSpoke['visual']['width'] - $wedge['visual']['arcWidth']) < 0.001,
            $finalSpoke['id'] . ' should match its wedge inner arc width exactly'
        );
        assertTrueValue(
            $wedge['visual']['angleWidth'] > $spaces["o{$spoke}_1"]['visual']['angleWidth'],
            $wedge['id'] . ' should be wider than normal outer spaces'
        );
        assertTrueValue(
            $spaces["o{$spoke}_1"]['visual']['angleWidth'] >= 6.0,
            "sector {$spoke} normal outer spaces should have enough visual width"
        );
        assertSameValue(
            $spaces["o{$spoke}_1"]['visual']['outer'],
            $wedge['visual']['outer'],
            $wedge['id'] . ' should stay inside the same outer circle as normal spaces'
        );
        assertSameValue(
            $spaces["o{$spoke}_1"]['visual']['inner'],
            $wedge['visual']['inner'],
            $wedge['id'] . ' should use the same inner radius as normal outer spaces'
        );
        assertTrueValue(
            $wedge['visual']['inner'] > $finalSpoke['visual']['outer'],
            $wedge['id'] . ' should keep the normal board gap after the final spoke'
        );
        assertSameValue(
            $wedge['visual']['inner'],
            $finalSpoke['visual']['curveOuter'],
            $finalSpoke['id'] . ' should curve up to the inner edge of its wedge'
        );
        assertTrueValue(
            abs(60.0 - ($wedge['visual']['angleWidth'] + 6 * $spaces["o{$spoke}_1"]['visual']['angleWidth'])) < 0.001,
            "sector {$spoke} outer spaces should fill the remaining span between wedges"
        );

        for ($outer = 1; $outer <= 6; $outer++) {
            $space = $spaces["o{$spoke}_{$outer}"] ?? $spaces["roll_again_{$spoke}_" . ($outer === 2 ? 1 : 2)];
            assertTrueValue(isset($space['visual']['angleOffset']), $space['id'] . ' missing angle offset');
            $expectedOffset = $wedge['visual']['angleOffset']
                + ($wedge['visual']['angleWidth'] / 2)
                + (($outer - 0.5) * $space['visual']['angleWidth']);
            assertTrueValue(
                abs($expectedOffset - $space['visual']['angleOffset']) < 0.001,
                $space['id'] . ' should be positioned in the available sector span'
            );
        }
    }
});

$runner->test('final radial spaces keep the same vertex-to-vertex size as other spoke spaces', function (): void {
    $spaces = GameEngine::boardSpaces();

    foreach (GameEngine::categories() as $spoke => $category) {
        $first = $spaces["r{$spoke}_1"]['visual'];
        $expectedLength = $first['outer'] - $first['inner'];
        $previousOuter = null;

        for ($spaceNumber = 1; $spaceNumber <= 5; $spaceNumber++) {
            $visual = $spaces["r{$spoke}_{$spaceNumber}"]['visual'];
            $length = $visual['outer'] - $visual['inner'];

            assertTrueValue(
                abs($expectedLength - $length) < 0.001,
                "r{$spoke}_{$spaceNumber} should match radial space length"
            );

            if ($previousOuter !== null) {
                assertTrueValue(
                    abs($previousOuter - $visual['inner']) < 0.001,
                    "r{$spoke}_{$spaceNumber} should start where the previous radial space ends"
                );
            }

            $previousOuter = $visual['outer'];
        }

        $final = $spaces["r{$spoke}_5"]['visual'];
        $halfWidth = $final['width'] / 2;
        $outerCornerRadius = sqrt(($final['outer'] ** 2) + ($halfWidth ** 2));

        assertTrueValue(
            abs($outerCornerRadius - $final['curveOuter']) < 0.001,
            "r{$spoke}_5 outer vertices should sit on the curved edge instead of adding an extra extension"
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
