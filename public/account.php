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

    <main class="shell account-shell" data-return-target="<?= htmlspecialchars($returnTarget, ENT_QUOTES, 'UTF-8') ?>">
        <?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Cuenta']]) ?>

        <header class="account-heading">
            <div>
                <p class="eyebrow">Tu espacio personal</p>
                <h1>Mi cuenta</h1>
                <p class="muted">Gestiona tu identidad y la seguridad de tu cuenta sin salir de esta pantalla.</p>
            </div>
        </header>

        <section id="accountGuestForms" class="account-access" aria-labelledby="accountAccessTitle">
            <div class="account-access-intro">
                <p class="eyebrow">Acceso</p>
                <h2 id="accountAccessTitle">Continúa donde lo dejaste</h2>
                <p class="muted">Inicia sesión, crea una cuenta o recupera el acceso.</p>
            </div>

            <nav id="accountAccessTabs" class="account-access-tabs" role="tablist" aria-label="Opciones de acceso">
                <button type="button" role="tab" data-account-mode="login" aria-controls="accountLoginPanel">Entrar</button>
                <button type="button" role="tab" data-account-mode="register" aria-controls="accountRegisterPanel">Crear cuenta</button>
                <button type="button" role="tab" data-account-mode="forgot" aria-controls="accountForgotPanel">Recuperar acceso</button>
            </nav>

            <div id="accountGuestMessage" class="account-inline-status" role="status" aria-live="polite"></div>

            <section id="accountLoginPanel" class="account-access-panel" role="tabpanel" data-account-panel="login">
                <form id="loginForm" novalidate>
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <label>Contraseña<input name="password" type="password" autocomplete="current-password" minlength="10" required></label>
                    <button type="submit">Entrar</button>
                </form>
            </section>

            <section id="accountRegisterPanel" class="account-access-panel" role="tabpanel" data-account-panel="register" hidden>
                <form id="registerForm" novalidate>
                    <label>Nombre visible<input name="displayName" autocomplete="nickname" minlength="2" maxlength="40" required></label>
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <label>Contraseña<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
                    <p class="field-help">Usa al menos 10 caracteres.</p>
                    <button type="submit">Crear cuenta</button>
                </form>
            </section>

            <section id="accountForgotPanel" class="account-access-panel" role="tabpanel" data-account-panel="forgot" hidden>
                <form id="forgotForm" novalidate>
                    <label>Email<input name="email" type="email" autocomplete="email" required></label>
                    <p class="field-help">Si existe una cuenta, enviaremos un enlace para definir una nueva contraseña.</p>
                    <button type="submit">Enviar enlace</button>
                </form>
            </section>

            <section id="resetSection" class="account-access-panel" role="tabpanel" data-account-panel="reset" hidden>
                <h3>Define una nueva contraseña</h3>
                <form id="resetForm" novalidate>
                    <input name="token" type="hidden">
                    <label>Nueva contraseña<input name="password" type="password" autocomplete="new-password" minlength="10" required></label>
                    <button type="submit">Guardar contraseña</button>
                </form>
            </section>
        </section>

        <section id="accountWorkspace" class="account-workspace" hidden>
            <aside id="accountSummary" class="account-summary" aria-live="polite">
                <p class="eyebrow">Identidad</p>
                <h2>Consultando sesión…</h2>
            </aside>

            <div class="account-editor">
                <nav id="accountSectionTabs" class="account-section-tabs" role="tablist" aria-label="Secciones de cuenta">
                    <button type="button" role="tab" data-account-section="profile" aria-controls="accountProfilePanel">Perfil</button>
                    <button type="button" role="tab" data-account-section="security" aria-controls="accountSecurityPanel">Seguridad</button>
                </nav>

                <section id="accountProfilePanel" class="account-editor-panel" role="tabpanel" data-account-section-panel="profile">
                    <div class="account-section-heading">
                        <div><p class="eyebrow">Perfil</p><h2>Cómo te ven los demás</h2></div>
                        <span id="profilePending" class="account-pending" hidden>Cambios sin guardar</span>
                    </div>
                    <form id="profileForm" novalidate>
                        <label>Nombre visible<input name="displayName" minlength="2" maxlength="40" autocomplete="nickname" required></label>
                        <div id="profileStatus" class="account-inline-status" role="status" aria-live="polite"></div>
                        <div class="account-form-actions"><button type="submit">Guardar perfil</button></div>
                    </form>
                </section>

                <section id="accountSecurityPanel" class="account-editor-panel" role="tabpanel" data-account-section-panel="security" hidden>
                    <div class="account-section-heading"><div><p class="eyebrow">Seguridad</p><h2>Cambiar contraseña</h2></div></div>
                    <p class="muted">Conservarás esta sesión y cerraremos las demás sesiones abiertas.</p>
                    <form id="passwordChangeForm" novalidate>
                        <label>Contraseña actual<input name="currentPassword" type="password" autocomplete="current-password" required></label>
                        <label>Nueva contraseña<input name="newPassword" type="password" autocomplete="new-password" minlength="10" required></label>
                        <label>Confirmar nueva contraseña<input name="newPasswordConfirmation" type="password" autocomplete="new-password" minlength="10" required></label>
                        <div id="passwordStatus" class="account-inline-status" role="status" aria-live="polite"></div>
                        <div class="account-form-actions"><button type="submit">Cambiar contraseña</button></div>
                    </form>
                    <div class="account-session-actions">
                        <div><strong>Sesión actual</strong><p class="muted">Puedes cerrarla cuando termines.</p></div>
                        <button id="logoutButton" class="secondary" type="button">Cerrar sesión</button>
                    </div>
                    <div class="account-danger-card">
                        <div><strong>Eliminar cuenta</strong><p class="muted">Esta acción es permanente y requiere una confirmación adicional.</p></div>
                        <button id="openDeleteAccount" class="danger-button" type="button">Eliminar cuenta</button>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <dialog id="deleteAccountDialog" class="account-dialog" aria-labelledby="deleteAccountTitle">
        <form id="deleteAccountForm">
            <div class="account-dialog-heading">
                <p class="eyebrow">Acción permanente</p>
                <h2 id="deleteAccountTitle">Eliminar mi cuenta</h2>
            </div>
            <p>Tu perfil y tus packs privados se retirarán. El historial compartido necesario para partidas existentes se conservará anonimizado.</p>
            <label>Contraseña actual<input name="password" type="password" autocomplete="current-password" required></label>
            <label>Escribe <strong>ELIMINAR</strong> para confirmar<input name="confirmation" autocomplete="off" required></label>
            <div id="deleteAccountStatus" class="account-inline-status" role="status" aria-live="polite"></div>
            <div class="account-dialog-actions">
                <button type="button" class="secondary" data-dialog-close>Cancelar</button>
                <button class="danger-button" type="submit">Eliminar definitivamente</button>
            </div>
        </form>
    </dialog>

    <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/account.js"></script>
</body>
</html>
