<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$navigationUser = NavigationView::currentUser(new SessionRepository(app_pdo()), $_COOKIE);
$returnTarget = NavigationView::safeReturnTarget(isset($_GET['return']) ? (string) $_GET['return'] : null);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi cuenta - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <?= NavigationView::renderHeader((string) $config['app_name'], $navigationUser, 'account', $returnTarget) ?>

    <main class="shell admin-shell" data-return-target="<?= htmlspecialchars($returnTarget, ENT_QUOTES, 'UTF-8') ?>">
        <?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Cuenta']]) ?>
        <section id="accountStatus" class="panel admin-panel" aria-live="polite">
            <p class="eyebrow">Acceso</p>
            <h1>Login o registro</h1>
            <p class="muted">Consultando la sesi&oacute;n...</p>
        </section>

        <div id="accountGuestForms" class="account-grid">
            <section class="panel admin-panel">
                <p class="eyebrow">Cuenta existente</p>
                <h1>Iniciar sesi&oacute;n</h1>
                <form id="loginForm">
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <label>Contrase&ntilde;a<input name="password" type="password" autocomplete="current-password" minlength="10" required></label>
                    <button type="submit">Entrar</button>
                </form>
            </section>

            <section class="panel admin-panel">
                <p class="eyebrow">Nueva cuenta</p>
                <h1>Registrarse</h1>
                <form id="registerForm">
                    <label>Nombre visible<input name="displayName" autocomplete="nickname" minlength="2" maxlength="40" required></label>
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <label>Contrase&ntilde;a<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
                    <button type="submit">Crear cuenta</button>
                </form>
            </section>

            <section class="panel admin-panel">
                <h2>Recuperar contrase&ntilde;a</h2>
                <form id="forgotForm">
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <button type="submit">Enviar enlace</button>
                </form>
            </section>
        </div>

        <section id="resetSection" class="panel admin-panel hidden">
            <h1>Definir nueva contrase&ntilde;a</h1>
            <form id="resetForm">
                <input name="token" type="hidden">
                <label>Nueva contrase&ntilde;a<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
                <button type="submit">Guardar contrase&ntilde;a</button>
            </form>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/account.js"></script>
</body>
</html>
