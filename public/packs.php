<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$token = (string) ($_COOKIE['rq_session'] ?? '');
$user = $token === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($token);
try {
    Authorization::requireVerifiedUser($user);
} catch (Throwable) {
    header('Location: account.php?return=packs.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packs - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <?= NavigationView::renderHeader((string) $config['app_name'], $user, 'packs', 'packs.php') ?>
    <main class="packs-shell">
        <?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Packs']], 'packBreadcrumbs') ?>

        <header class="packs-page-heading">
            <div>
                <p class="eyebrow">Tu contenido</p>
                <h1>Packs tem&aacute;ticos</h1>
                <p class="muted">Organiza categor&iacute;as y preguntas sin perder de vista el pack que est&aacute;s editando.</p>
            </div>
        </header>

        <nav id="packsSectionTabs" class="packs-section-tabs" aria-label="Herramientas de packs" role="tablist">
            <button type="button" role="tab" aria-selected="true" aria-controls="packsSection" data-section-tab="packs">Packs</button>
            <button type="button" role="tab" aria-selected="false" aria-controls="schemesSection" data-section-tab="schemes">Esquemas de color</button>
            <button type="button" role="tab" aria-selected="false" aria-controls="importSection" data-section-tab="import">Importar</button>
        </nav>

        <section id="packsSection" class="packs-section" role="tabpanel">
            <div class="packs-section-heading">
                <div>
                    <h2>Mis packs</h2>
                    <p id="packCount" class="muted">Cargando packs&hellip;</p>
                </div>
                <button id="newPackButton" type="button">Nuevo pack</button>
            </div>

            <div id="adminPackFilters" class="packs-filter-bar hidden" aria-label="Tipo de pack">
                <button type="button" class="secondary selected" aria-pressed="true" data-pack-filter="user">Mis packs</button>
                <button type="button" class="secondary" aria-pressed="false" data-pack-filter="system">Sistema</button>
            </div>

            <div id="packsWorkspace" class="packs-workspace">
                <aside id="packIndex" class="panel packs-index" aria-label="Lista de packs">
                    <div id="packList" class="packs-list"></div>
                </aside>

                <section id="packEditor" class="panel packs-editor" aria-live="polite">
                    <div id="packEditorEmpty" class="packs-empty-state">
                        <span class="packs-empty-icon" aria-hidden="true">?</span>
                        <h2>Selecciona un pack</h2>
                        <p class="muted">El resumen, las categor&iacute;as y las preguntas aparecer&aacute;n aqu&iacute;.</p>
                    </div>

                    <div id="packEditorContent" class="hidden">
                        <button id="packMobileBack" class="secondary packs-mobile-back" type="button">&larr; Volver a packs</button>
                        <header class="packs-editor-heading">
                            <div>
                                <div id="packEditorBadges" class="packs-badges"></div>
                                <h2 id="packEditorTitle" tabindex="-1"></h2>
                                <p id="packEditorMeta" class="muted"></p>
                            </div>
                            <details id="packMoreActions" class="packs-more-actions">
                                <summary>M&aacute;s acciones</summary>
                                <div class="packs-action-menu">
                                    <button type="button" class="secondary" data-export-format="json">Descargar JSON</button>
                                    <button type="button" class="secondary" data-export-format="csv">Descargar CSV</button>
                                    <button type="button" class="secondary" data-copy-format="json">Copiar JSON</button>
                                    <button type="button" class="secondary" data-copy-format="csv">Copiar CSV</button>
                                    <button id="deletePackButton" type="button" class="danger-button">Eliminar pack</button>
                                </div>
                            </details>
                        </header>

                        <nav id="packEditorTabs" class="packs-editor-tabs" aria-label="Editor del pack" role="tablist">
                            <button type="button" role="tab" aria-selected="true" data-editor-tab="summary">Resumen</button>
                            <button type="button" role="tab" aria-selected="false" data-editor-tab="categories">Categor&iacute;as</button>
                            <button type="button" role="tab" aria-selected="false" data-editor-tab="questions">Preguntas</button>
                        </nav>

                        <section id="packSummaryPanel" class="packs-editor-panel" role="tabpanel">
                            <div id="packProgress" class="packs-progress"></div>
                            <div class="packs-summary-actions">
                                <button id="editPackButton" type="button" class="secondary">Crear borrador de edici&oacute;n</button>
                                <button id="activatePackButton" type="button">Activar revisi&oacute;n</button>
                            </div>
                        </section>

                        <section id="packCategoriesPanel" class="packs-editor-panel hidden" role="tabpanel">
                            <div class="packs-scheme-apply">
                                <label>Aplicar esquema de colores
                                    <select id="applyColorSchemeSelect"><option value="">Selecciona un esquema</option></select>
                                </label>
                                <p class="muted">Los colores se copiar&aacute;n al borrador y podr&aacute;s revisarlos antes de guardar.</p>
                            </div>
                            <form id="categoriesForm">
                                <div id="categoryFields" class="packs-category-grid"></div>
                                <div class="packs-sticky-actions">
                                    <span id="categoryDirtyState" class="muted" aria-live="polite">Sin cambios pendientes</span>
                                    <button type="submit">Guardar categor&iacute;as</button>
                                </div>
                            </form>
                        </section>

                        <section id="packQuestionsPanel" class="packs-editor-panel hidden" role="tabpanel">
                            <div class="packs-question-heading">
                                <div id="questionFilters" class="packs-question-filters" aria-label="Filtrar preguntas por categor&iacute;a"></div>
                                <button id="newQuestionButton" type="button">A&ntilde;adir pregunta</button>
                            </div>
                            <div id="packQuestions" class="packs-question-list"></div>
                        </section>
                    </div>
                </section>
            </div>
        </section>

        <section id="schemesSection" class="packs-section hidden" role="tabpanel">
            <div class="packs-section-heading">
                <div>
                    <h2>Esquemas de color</h2>
                    <p class="muted">Reutiliza combinaciones coherentes en packs y salas.</p>
                </div>
                <button id="newSchemeButton" type="button">Nuevo esquema</button>
            </div>
            <div id="adminSchemeFilters" class="packs-filter-bar hidden" aria-label="Tipo de esquema">
                <button type="button" class="secondary selected" aria-pressed="true" data-scheme-filter="user">Personales</button>
                <button type="button" class="secondary" aria-pressed="false" data-scheme-filter="system">Sistema</button>
            </div>
            <div id="schemeList" class="packs-scheme-library"></div>
        </section>

        <section id="importSection" class="packs-section hidden" role="tabpanel">
            <div class="packs-section-heading">
                <div>
                    <h2>Importar un pack</h2>
                    <p class="muted">Revisa el contenido antes de crear un nuevo borrador privado.</p>
                </div>
                <div class="packs-template-actions">
                    <button type="button" class="secondary" data-template-format="json">Plantilla JSON</button>
                    <button type="button" class="secondary" data-template-format="csv">Plantilla CSV</button>
                </div>
            </div>
            <div class="packs-import-layout">
                <form id="importPreviewForm" class="panel packs-import-form">
                    <label id="importDropzone" class="packs-dropzone">
                        <strong>Arrastra un archivo aqu&iacute;</strong>
                        <span>o selecci&oacute;nalo desde tu equipo</span>
                        <input id="importFile" type="file" accept=".json,.csv,application/json,text/csv">
                    </label>
                    <label>Formato
                        <select id="importFormat" name="format"><option value="json">JSON</option><option value="csv">CSV</option></select>
                    </label>
                    <label>Contenido
                        <textarea id="importContent" name="content" rows="12" placeholder="Pega aqu&iacute; el contenido JSON o CSV" required></textarea>
                    </label>
                    <button type="submit">Revisar importaci&oacute;n</button>
                </form>
                <aside id="importPreview" class="panel packs-import-preview" aria-live="polite">
                    <div class="packs-empty-state">
                        <h3>Vista previa</h3>
                        <p class="muted">Aqu&iacute; ver&aacute;s el nombre, las categor&iacute;as y el reparto de preguntas.</p>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    <dialog id="newPackDialog" class="packs-dialog">
        <form id="createPackForm" method="dialog">
            <header><p class="eyebrow">Nuevo borrador</p><h2>Crear pack</h2></header>
            <label>Nombre<input name="name" maxlength="120" autocomplete="off" required></label>
            <label>Colores iniciales<select id="createPackColorScheme" name="colorSchemeId" required></select></label>
            <p id="newPackKindHelp" class="muted"></p>
            <div class="packs-dialog-actions">
                <button type="button" class="secondary" data-close-dialog>Cancelar</button>
                <button type="submit">Crear y editar</button>
            </div>
        </form>
    </dialog>

    <dialog id="questionDialog" class="packs-dialog packs-question-dialog">
        <form id="questionForm" method="dialog">
            <input name="questionId" type="hidden">
            <header><p class="eyebrow">Contenido del pack</p><h2 id="questionDialogTitle">A&ntilde;adir pregunta</h2></header>
            <label>Categor&iacute;a<select name="slot" id="questionSlot"></select></label>
            <label>Enunciado<input name="question" maxlength="500" required></label>
            <fieldset class="packs-answer-grid">
                <legend>Respuestas</legend>
                <?php foreach (['A', 'B', 'C', 'D'] as $index => $letter): ?>
                    <label class="packs-answer-option">
                        <input type="radio" name="correct" value="<?= $index ?>" <?= $index === 0 ? 'checked' : '' ?> aria-label="Marcar <?= $letter ?> como correcta">
                        <span><?= $letter ?></span>
                        <input name="options[]" maxlength="300" aria-label="Respuesta <?= $letter ?>" required>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <div class="packs-dialog-actions">
                <button type="button" class="secondary" data-close-dialog>Cancelar</button>
                <button type="submit">Guardar pregunta</button>
            </div>
        </form>
    </dialog>

    <dialog id="schemeDialog" class="packs-dialog">
        <form id="schemeForm" method="dialog">
            <input name="colorSchemeId" type="hidden">
            <input name="kind" type="hidden" value="user">
            <header><p class="eyebrow">Biblioteca de color</p><h2 id="schemeDialogTitle">Nuevo esquema</h2></header>
            <label>Nombre<input name="name" maxlength="100" required></label>
            <div class="color-grid">
                <?php for ($slot = 0; $slot < 6; $slot++): ?>
                    <?php $color = '#' . str_repeat(dechex(2 + $slot), 6); ?>
                    <label class="color-field">Color <?= $slot + 1 ?><input name="colors[]" type="color" value="<?= $color ?>" required><span><?= $color ?></span></label>
                <?php endfor; ?>
            </div>
            <div class="packs-dialog-actions">
                <button type="button" class="secondary" data-close-dialog>Cancelar</button>
                <button type="submit">Guardar esquema</button>
            </div>
        </form>
    </dialog>

    <dialog id="confirmDialog" class="packs-dialog packs-confirm-dialog">
        <form id="confirmForm" method="dialog">
            <header><p class="eyebrow">Confirmaci&oacute;n</p><h2 id="confirmTitle">Confirmar acci&oacute;n</h2></header>
            <p id="confirmMessage"></p>
            <label id="confirmNameWrap" class="hidden">Escribe el nombre para confirmar<input id="confirmNameInput" autocomplete="off"></label>
            <div class="packs-dialog-actions">
                <button type="button" class="secondary" data-confirm-cancel>Cancelar</button>
                <button id="confirmSubmit" type="submit" class="danger-button">Confirmar</button>
            </div>
        </form>
    </dialog>

    <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/packs.js"></script>
</body>
</html>
