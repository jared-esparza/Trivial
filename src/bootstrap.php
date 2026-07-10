<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Database/MigrationRunner.php';
require_once __DIR__ . '/GameEngine.php';
require_once __DIR__ . '/QuestionImporter.php';
require_once __DIR__ . '/QuestionRepository.php';
require_once __DIR__ . '/RoomRepository.php';

function app_config(): array
{
    $local = dirname(__DIR__) . '/config.php';
    if (!file_exists($local)) {
        return [
            'app_name' => 'trivial',
            'admin_key' => 'admin-local',
            'using_default_config' => true,
            'database' => [
                'driver' => 'sqlite',
                'path' => dirname(__DIR__) . '/storage/dev.sqlite',
            ],
        ];
    }

    $config = require $local;

    if (($config['admin_key'] ?? '') === 'cambia-esta-clave') {
        $config['using_example_config'] = !file_exists($local);
    }

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

    return $pdo;
}
