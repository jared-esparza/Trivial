<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$options = getopt('', ['email:', 'password:', 'display-name::']);
$email = strtolower(trim((string) ($options['email'] ?? '')));
$password = (string) ($options['password'] ?? '');
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 10) {
    fwrite(STDERR, "Uso: php bin/create-admin.php --email=admin@example.com --password=una-clave-de-10-caracteres [--display-name=Admin]\n");
    exit(1);
}
$displayName = (string) ($options['display-name'] ?? UserRepository::displayNameFromEmail($email));

$users = new UserRepository(app_pdo());
$user = $users->findByEmail($email);
if ($user === null) {
    $user = $users->create($email, password_hash($password, PASSWORD_DEFAULT), 'admin', $displayName);
} else {
    $users->updateDisplayName($user['id'], $displayName);
    $users->updatePassword($user['id'], password_hash($password, PASSWORD_DEFAULT));
    $users->updateRole($user['id'], 'admin');
    $users->updateStatus($user['id'], 'active');
}
$users->markVerified($user['id']);

fwrite(STDOUT, "Administrador preparado: {$email}\n");
