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
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso restringido</title><link rel="stylesheet" href="assets/styles.css"></head><body><?= NavigationView::renderHeader((string) $config['app_name'], $adminUser, 'account', 'admin.php') ?><main class="shell admin-shell"><?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Acceso restringido']]) ?><section class="panel admin-panel"><h1>Acceso restringido</h1><p>Inicia sesión con una cuenta administradora.</p><a href="account.php?return=admin.php">Ir a Mi cuenta</a></section></main><script src="assets/session-nav.js"></script></body></html><?php
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <?= NavigationView::renderHeader((string) $config['app_name'], $adminUser, 'admin', 'admin.php') ?>

    <main class="shell admin-shell admin-page-shell">
        <?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Administración']]) ?>

        <header class="admin-workspace-heading">
            <div>
                <p class="eyebrow">Administración</p>
                <h1>Centro de control</h1>
                <p class="muted">Gestiona el acceso de usuarios y entra directamente en las herramientas de contenido.</p>
            </div>
        </header>

        <nav id="adminSectionTabs" class="admin-section-tabs" role="tablist" aria-label="Secciones de administración">
            <button type="button" role="tab" data-admin-section="users" aria-controls="adminUsersSection">Usuarios</button>
            <button type="button" role="tab" data-admin-section="content" aria-controls="adminContentSection">Contenido</button>
        </nav>

        <section id="adminUsersSection" class="admin-section" role="tabpanel" data-admin-panel="users">
            <div class="admin-filter-panel" aria-label="Buscar y filtrar usuarios">
                <label class="admin-search-field">Buscar
                    <input id="adminUserSearch" type="search" placeholder="Nombre o email" autocomplete="off">
                </label>
                <label>Rol
                    <select id="adminRoleFilter"><option value="all">Todos</option><option value="admin">Administrador</option><option value="user">Usuario</option></select>
                </label>
                <label>Estado
                    <select id="adminStatusFilter"><option value="all">Todos</option><option value="active">Activo</option><option value="disabled">Desactivado</option></select>
                </label>
                <label>Verificación
                    <select id="adminVerificationFilter"><option value="all">Todos</option><option value="verified">Email verificado</option><option value="pending">Email pendiente</option></select>
                </label>
                <label>Orden
                    <select id="adminSort"><option value="recent">Más recientes</option><option value="name">Nombre</option><option value="email">Email</option><option value="oldest">Antigüedad</option></select>
                </label>
            </div>

            <div id="adminWorkspace" class="admin-workspace">
                <aside class="admin-user-list-panel" aria-labelledby="adminUserListTitle">
                    <div class="admin-list-heading">
                        <div><p class="eyebrow">Directorio</p><h2 id="adminUserListTitle">Usuarios</h2></div>
                        <span id="adminUserCount" class="admin-result-count">0 resultados</span>
                    </div>
                    <div id="adminUserListStatus" class="admin-inline-status" role="status" aria-live="polite">Cargando usuarios…</div>
                    <div id="adminUserList" class="admin-user-list" aria-label="Resultados de usuarios"></div>
                </aside>

                <article id="adminUserDetail" class="admin-user-detail" aria-live="polite">
                    <button id="adminBackToUsers" class="admin-back-button secondary" type="button">← Volver a usuarios</button>
                    <div id="adminUserDetailContent" class="admin-detail-empty">
                        <p class="eyebrow">Detalle</p>
                        <h2>Selecciona un usuario</h2>
                        <p class="muted">Consulta su estado y aplica los cambios conjuntamente.</p>
                    </div>
                </article>
            </div>
        </section>

        <section id="adminContentSection" class="admin-section" role="tabpanel" data-admin-panel="content" hidden>
            <div class="admin-content-heading">
                <p class="eyebrow">Contenido</p>
                <h2>Herramientas del sistema</h2>
                <p class="muted">Los editores viven en Packs. Entra directamente en la tarea que necesitas.</p>
            </div>
            <div class="admin-content-grid">
                <a class="admin-content-card" href="packs.php?section=packs&amp;scope=system"><span class="admin-content-icon" aria-hidden="true">P</span><span><strong>Packs del sistema</strong><small>Revisiones, categorías y preguntas</small></span><span aria-hidden="true">→</span></a>
                <a class="admin-content-card" href="packs.php?section=schemes&amp;scope=system"><span class="admin-content-icon" aria-hidden="true">C</span><span><strong>Esquemas del sistema</strong><small>Paletas compartidas y preset Clásico</small></span><span aria-hidden="true">→</span></a>
                <a class="admin-content-card" href="packs.php?section=import"><span class="admin-content-icon" aria-hidden="true">I</span><span><strong>Importar contenido</strong><small>Previsualiza JSON o CSV antes de crear el borrador</small></span><span aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <dialog id="adminConfirmDialog" class="admin-confirm-dialog" aria-labelledby="adminConfirmTitle">
        <form method="dialog">
            <p class="eyebrow">Confirma el cambio</p>
            <h2 id="adminConfirmTitle">Cambiar acceso</h2>
            <p id="adminConfirmText"></p>
            <div class="admin-dialog-actions">
                <button type="button" class="secondary" data-admin-confirm-cancel>Cancelar</button>
                <button type="button" class="danger-button" data-admin-confirm-accept>Confirmar cambio</button>
            </div>
        </form>
    </dialog>

    <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/admin.js"></script>
</body>
</html>
