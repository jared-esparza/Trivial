<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Database/MigrationRunner.php';
require_once __DIR__ . '/GameEngine.php';
require_once __DIR__ . '/QuestionImporter.php';
require_once __DIR__ . '/QuestionRepository.php';
require_once __DIR__ . '/RoomRepository.php';
require_once __DIR__ . '/Mail/Mailer.php';
require_once __DIR__ . '/Mail/NativeMailer.php';
require_once __DIR__ . '/Mail/LocalOutboxMailer.php';
require_once __DIR__ . '/Auth/UserRepository.php';
require_once __DIR__ . '/Auth/SessionRepository.php';
require_once __DIR__ . '/Auth/AccountDeletionService.php';
require_once __DIR__ . '/Auth/AccountTokenRepository.php';
require_once __DIR__ . '/Auth/AuthService.php';
require_once __DIR__ . '/Auth/AuthRateLimiter.php';
require_once __DIR__ . '/Auth/Authorization.php';
require_once __DIR__ . '/Auth/UserAdminService.php';
require_once __DIR__ . '/NavigationView.php';
require_once __DIR__ . '/Http/ApiException.php';
require_once __DIR__ . '/Http/ApiRequest.php';
require_once __DIR__ . '/Http/ApiResponse.php';
require_once __DIR__ . '/Http/ApiRouter.php';
require_once __DIR__ . '/Http/AuthController.php';
require_once __DIR__ . '/Http/AdminUserController.php';
require_once __DIR__ . '/Packs/PackRepository.php';
require_once __DIR__ . '/Packs/PackService.php';
require_once __DIR__ . '/Packs/PackImporter.php';
require_once __DIR__ . '/Packs/PackExporter.php';
require_once __DIR__ . '/Packs/PackSeeder.php';
require_once __DIR__ . '/Http/PackController.php';
require_once __DIR__ . '/Game/RoomService.php';
require_once __DIR__ . '/Game/ParticipantTokenService.php';
require_once __DIR__ . '/Stats/AnswerEventRepository.php';
require_once __DIR__ . '/Stats/StatisticsService.php';
require_once __DIR__ . '/Maintenance/CleanupService.php';

function app_config(): array
{
    $local = dirname(__DIR__) . '/config.php';
    if (!file_exists($local)) {
        return [
            'app_name' => 'trivial',
            'base_url' => 'http://127.0.0.1:4181',
            'anonymous_room_retention_days' => 30,
            'using_default_config' => true,
            'mail' => [
                'transport' => 'local',
                'outbox' => dirname(__DIR__) . '/storage/mail-outbox.log',
            ],
            'database' => [
                'driver' => 'sqlite',
                'path' => dirname(__DIR__) . '/storage/dev.sqlite',
            ],
        ];
    }

    $config = require $local;

    return $config;
}

function app_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    if (($config['database']['driver'] ?? '') === 'sqlite') {
        $dir = dirname((string) $config['database']['path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
    $pdo = Database::connect($config['database']);
    $migrations = new MigrationRunner($pdo, dirname(__DIR__) . '/database/migrations');
    $migrations->migrate();
    $seeder = new PackSeeder($pdo, dirname(__DIR__) . '/data/questions-demo.csv');
    $seeder->seed();

    return $pdo;
}

function app_mailer(): Mailer
{
    static $mailer = null;
    if ($mailer instanceof Mailer) {
        return $mailer;
    }

    $config = app_config();
    $mail = $config['mail'] ?? [];
    if (($mail['transport'] ?? 'local') === 'native') {
        $mailer = new NativeMailer((string) ($mail['from'] ?? 'no-reply@localhost'));
    } else {
        $mailer = new LocalOutboxMailer(
            (string) ($mail['outbox'] ?? dirname(__DIR__) . '/storage/mail-outbox.log')
        );
    }

    return $mailer;
}

function app_auth_router(): ApiRouter
{
    $pdo = app_pdo();
    $sessions = new SessionRepository($pdo);
    $auth = new AuthService(
        new UserRepository($pdo),
        $sessions,
        new AccountTokenRepository($pdo),
        app_mailer(),
        (string) (app_config()['base_url'] ?? 'http://127.0.0.1:4181'),
        new AuthRateLimiter($pdo)
    );
    $router = new ApiRouter();
    (new AuthController(
        $auth,
        $sessions,
        new AccountDeletionService($pdo, new UserRepository($pdo), $sessions)
    ))->registerRoutes($router);
    (new AdminUserController(
        new UserRepository($pdo),
        $sessions,
        new UserAdminService($pdo, new UserRepository($pdo), $sessions)
    ))->registerRoutes($router);
    $packRepository = new PackRepository($pdo);
    (new PackController(new PackService($packRepository), $packRepository, $sessions))->registerRoutes($router);

    return $router;
}
