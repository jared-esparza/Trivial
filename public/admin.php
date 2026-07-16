<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$sessionToken = (string) ($_COOKIE['rq_session'] ?? '');
$adminUser = $sessionToken === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($sessionToken);
if ($adminUser === null) {
    header('Location: account.php?return=admin.php');
    exit;
}
try {
    Authorization::requireAdmin($adminUser);
} catch (Throwable) {
    http_response_code(403);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso restringido</title><link rel="stylesheet" href="assets/styles.css"></head><body><?= NavigationView::renderHeader((string) $config['app_name'], $adminUser, 'account', 'admin.php') ?><main class="shell admin-shell"><?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Acceso restringido']]) ?><section class="panel admin-panel"><h1>Acceso restringido</h1><p>Inicia sesi&oacute;n con una cuenta administradora.</p><a href="account.php?return=admin.php">Ir a Mi cuenta</a></section></main><script src="assets/session-nav.js"></script></body></html><?php
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administracion - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <?= NavigationView::renderHeader((string) $config['app_name'], $adminUser, 'admin', 'admin.php') ?>

    <main class="shell admin-shell">
        <?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Administración']]) ?>
        <section class="panel admin-panel">
            <p class="eyebrow">Administraci&oacute;n</p>
            <h1>Usuarios</h1>
            <p class="muted">Gestiona roles y acceso. El sistema impedir&aacute; desactivar o degradar al &uacute;ltimo administrador.</p>
            <div id="adminUsers" class="question-list" aria-live="polite"></div>
        </section>

        <section class="panel admin-panel">
            <p class="eyebrow">Contenido</p>
            <h2>Packs del sistema y colores</h2>
            <p class="muted">Las preguntas se administran dentro de revisiones de pack para no alterar partidas ni revisiones activas.</p>
            <a href="packs.php">Abrir gestion de packs</a>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
