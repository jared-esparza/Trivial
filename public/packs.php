<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$token = (string) ($_COOKIE['rq_session'] ?? '');
$user = $token === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($token);
try {
    Authorization::requireVerifiedUser($user);
} catch (Throwable) {
    header('Location: account.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis packs - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="./"><?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></a>
        <nav class="topbar-nav" data-session-nav aria-label="Navegaci&oacute;n principal">
            <a class="topbar-link" href="./">Juego</a>
            <a class="topbar-link" href="account.php">Cuenta</a>
            <a class="topbar-link active" href="packs.php">Packs</a>
        </nav>
    </header>
    <main class="shell admin-shell">
        <section class="panel admin-panel">
            <p class="eyebrow">Contenido personal</p>
            <h1>Mis packs tem&aacute;ticos</h1>
            <form id="createPackForm" class="inline-form">
                <input name="name" placeholder="Nombre del nuevo pack" maxlength="120" required>
                <label>Colores iniciales
                    <select id="createPackColorScheme" name="colorSchemeId" required></select>
                </label>
                <label id="adminPackControls" class="hidden">Tipo
                    <select name="kind"><option value="user">Privado</option><option value="system">Sistema</option></select>
                </label>
                <button type="submit">Crear borrador</button>
            </form>
            <div id="packList" class="question-list"></div>
        </section>

        <section id="personalColorSchemeSection" class="panel admin-panel">
            <p class="eyebrow">Biblioteca personal</p>
            <h2>Mis esquemas de colores</h2>
            <p class="muted">Guarda combinaciones para reutilizarlas en tus packs y salas.</p>
            <form id="personalColorSchemeForm" data-scheme-kind="user">
                <input name="colorSchemeId" type="hidden">
                <label>Nombre<input name="name" maxlength="100" required></label>
                <div class="color-grid">
                    <?php for ($slot = 0; $slot < 6; $slot++): ?>
                        <?php $color = '#' . str_repeat(dechex(2 + $slot), 6); ?>
                        <label class="color-field">Color <?= $slot + 1 ?><input name="colors[]" type="color" value="<?= $color ?>" required><span><?= $color ?></span></label>
                    <?php endfor; ?>
                </div>
                <div class="inline-form">
                    <button type="submit">Guardar esquema personal</button>
                    <button class="secondary hidden" type="button" data-cancel-scheme>Cancelar edici&oacute;n</button>
                </div>
            </form>
            <div id="personalColorSchemeList" class="question-list"></div>
        </section>

        <section id="colorSchemeAdmin" class="panel admin-panel hidden">
            <p class="eyebrow">Administraci&oacute;n</p>
            <h2>Esquemas de colores del sistema</h2>
            <form id="colorSchemeForm" data-scheme-kind="system">
                <input name="colorSchemeId" type="hidden">
                <label>Nombre<input name="name" maxlength="100" required></label>
                <div id="colorInputs" class="color-grid">
                    <?php for ($slot = 0; $slot < 6; $slot++): ?>
                        <?php $color = '#' . str_repeat(dechex(2 + $slot), 6); ?>
                        <label class="color-field">Color <?= $slot + 1 ?><input name="colors[]" type="color" value="<?= $color ?>" required><span><?= $color ?></span></label>
                    <?php endfor; ?>
                </div>
                <div class="inline-form">
                    <button type="submit">Guardar esquema del sistema</button>
                    <button class="secondary hidden" type="button" data-cancel-scheme>Cancelar edici&oacute;n</button>
                </div>
            </form>
            <div id="colorSchemeList" class="question-list"></div>
        </section>

        <section class="panel admin-panel">
            <h2>Importar pack completo</h2>
            <form id="importPackForm">
                <label>Formato<select name="format"><option value="json">JSON</option><option value="csv">CSV</option></select></label>
                <label>Contenido<textarea name="content" rows="10" required></textarea></label>
                <button type="submit">Importar como borrador nuevo</button>
            </form>
        </section>

        <section id="packEditor" class="panel admin-panel hidden">
            <div id="packEditorSummary"></div>
            <div class="scheme-apply-row">
                <label>Aplicar esquema de colores
                    <select id="applyColorSchemeSelect"><option value="">Selecciona un esquema</option></select>
                </label>
                <p class="muted">Copia los seis colores al borrador. Podr&aacute;s retocarlos antes de guardar.</p>
            </div>
            <form id="categoriesForm"><div id="categoryFields" class="account-grid"></div><button type="submit">Guardar categor&iacute;as</button></form>
            <hr>
            <form id="questionForm">
                <h2>A&ntilde;adir pregunta</h2>
                <label>Categor&iacute;a<select name="slot" id="questionSlot"></select></label>
                <label>Pregunta<input name="question" required></label>
                <label>Opciones, una por l&iacute;nea<textarea name="options" rows="4" required></textarea></label>
                <label>Respuesta correcta<select name="correct"><option value="0">1</option><option value="1">2</option><option value="2">3</option><option value="3">4</option></select></label>
                <button type="submit">A&ntilde;adir pregunta</button>
            </form>
            <div class="inline-form">
                <button id="editPackButton" type="button">Crear revisi&oacute;n de edici&oacute;n</button>
                <button id="activatePackButton" type="button">Activar revisi&oacute;n</button>
                <button id="exportJsonButton" type="button">Exportar JSON</button>
                <button id="exportCsvButton" type="button">Exportar CSV</button>
                <button id="deletePackButton" type="button">Eliminar pack</button>
            </div>
            <label>Exportaci&oacute;n<textarea id="packExportOutput" rows="10" readonly></textarea></label>
            <div id="packQuestions" class="question-list"></div>
        </section>
    </main>
    <div id="toast" class="toast hidden"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/packs.js"></script>
</body>
</html>
