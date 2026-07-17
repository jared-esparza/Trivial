const packsApi = './api.php';
let packCsrf = null;
let packUser = null;
let packs = [];
let colorSchemes = [];
let selectedPackId = null;
let activeSection = 'packs';
let activeEditorTab = 'summary';
let adminPackFilter = 'user';
let adminSchemeFilter = 'user';
let selectedQuestionSlot = 'all';
let categoriesDirty = false;
let importPreviewData = null;
let confirmResolver = null;
let confirmRequiredName = '';
const dialogTriggers = new WeakMap();

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const me = await getJson('/auth/me');
        packCsrf = me.csrfToken;
        packUser = me.user;
        const isAdmin = packUser?.role === 'admin';
        document.querySelector('#adminPackFilters')?.classList.toggle('hidden', !isAdmin);
        document.querySelector('#adminSchemeFilters')?.classList.toggle('hidden', !isAdmin);
        bindWorkspace();
        await Promise.all([loadPacks(), loadColorSchemes()]);
        renderWorkspace();
        renderSchemeLibrary();
    } catch (error) {
        notifyPack(error.message || 'No se pudo cargar la gestión de packs.');
    }
});

window.addEventListener('beforeunload', (event) => {
    if (!categoriesDirty) return;
    event.preventDefault();
    event.returnValue = '';
});

function bindWorkspace() {
    document.querySelectorAll('[data-section-tab]').forEach((button) => button.addEventListener('click', () => switchSection(button.dataset.sectionTab)));
    document.querySelectorAll('[data-editor-tab]').forEach((button) => button.addEventListener('click', () => switchEditorTab(button.dataset.editorTab)));
    document.querySelectorAll('[data-pack-filter]').forEach((button) => button.addEventListener('click', () => {
        if (!canDiscardCategoryChanges()) return;
        adminPackFilter = button.dataset.packFilter;
        selectedPackId = null;
        updatePressedButtons('[data-pack-filter]', button);
        renderWorkspace();
    }));
    document.querySelectorAll('[data-scheme-filter]').forEach((button) => button.addEventListener('click', () => {
        adminSchemeFilter = button.dataset.schemeFilter;
        updatePressedButtons('[data-scheme-filter]', button);
        renderSchemeLibrary();
    }));

    document.querySelector('#newPackButton')?.addEventListener('click', (event) => openNewPackDialog(event.currentTarget));
    document.querySelector('#createPackForm')?.addEventListener('submit', createPack);
    document.querySelector('#packMobileBack')?.addEventListener('click', showMobilePackIndex);
    document.querySelector('#categoriesForm')?.addEventListener('submit', saveCategories);
    document.querySelector('#categoryFields')?.addEventListener('input', markCategoriesDirty);
    document.querySelector('#applyColorSchemeSelect')?.addEventListener('change', applyColorSchemeToPack);
    document.querySelector('#editPackButton')?.addEventListener('click', () => packAction('/packs/edit', 'Borrador de edición creado.'));
    document.querySelector('#activatePackButton')?.addEventListener('click', () => packAction('/packs/activate', 'Revisión activada.'));
    document.querySelector('#newQuestionButton')?.addEventListener('click', (event) => openQuestionDialog(null, event.currentTarget));
    document.querySelector('#questionForm')?.addEventListener('submit', saveQuestion);
    document.querySelector('#deletePackButton')?.addEventListener('click', deleteSelectedPack);
    document.querySelectorAll('[data-export-format]').forEach((button) => button.addEventListener('click', () => downloadPack(button.dataset.exportFormat)));
    document.querySelectorAll('[data-copy-format]').forEach((button) => button.addEventListener('click', () => copyPack(button.dataset.copyFormat)));

    document.querySelector('#newSchemeButton')?.addEventListener('click', (event) => openSchemeDialog(null, event.currentTarget));
    document.querySelector('#schemeForm')?.addEventListener('submit', saveColorScheme);
    document.querySelector('#schemeForm')?.addEventListener('input', renderColorInputs);

    document.querySelector('#importPreviewForm')?.addEventListener('submit', previewImport);
    document.querySelector('#importFile')?.addEventListener('change', (event) => loadImportFile(event.target.files?.[0]));
    const dropzone = document.querySelector('#importDropzone');
    dropzone?.addEventListener('dragover', (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        loadImportFile(event.dataTransfer?.files?.[0]);
    });
    document.querySelectorAll('[data-template-format]').forEach((button) => button.addEventListener('click', () => downloadTemplate(button.dataset.templateFormat)));

    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelector('[data-confirm-cancel]')?.addEventListener('click', cancelConfirmation);
    document.querySelector('#confirmForm')?.addEventListener('submit', acceptConfirmation);
    document.querySelector('#confirmNameInput')?.addEventListener('input', updateConfirmButton);
    document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('close', () => {
        const trigger = dialogTriggers.get(dialog);
        if (trigger?.isConnected) trigger.focus();
        if (dialog.id === 'confirmDialog' && confirmResolver) finishConfirmation(false);
    }));
}

async function loadPacks() {
    const data = await getJson('/packs');
    packs = data.packs;
    const visible = visiblePacks();
    if (!visible.some((pack) => pack.id === selectedPackId)) selectedPackId = visible[0]?.id ?? null;
}

async function loadColorSchemes() {
    const data = await getJson('/packs/colors');
    colorSchemes = data.colorSchemes;
    renderColorSchemeSelects();
}

function visiblePacks() {
    if (packUser?.role !== 'admin') {
        return packs.filter((pack) => pack.kind === 'user' && pack.ownerUserId === packUser?.id);
    }
    return packs.filter((pack) => pack.kind === adminPackFilter);
}

function renderWorkspace() {
    const visible = visiblePacks();
    const count = document.querySelector('#packCount');
    if (count) count.textContent = `${visible.length} ${visible.length === 1 ? 'pack' : 'packs'} en esta vista`;
    renderPackList(visible);
    renderPackEditor();
}

function renderPackList(visible) {
    const list = document.querySelector('#packList');
    if (!list) return;
    if (visible.length === 0) {
        list.innerHTML = '<div class="packs-list-empty"><strong>No hay packs todavía</strong><p class="muted">Crea el primero para empezar a añadir preguntas.</p></div>';
        return;
    }
    list.innerHTML = visible.map((pack) => {
        const revision = packRevision(pack);
        const questionCount = revision?.questions?.length ?? 0;
        const selected = pack.id === selectedPackId;
        return `<button class="packs-list-row${selected ? ' selected' : ''}" type="button" data-pack-id="${pack.id}" aria-pressed="${selected}">
            <span class="packs-list-title"><strong>${escapePack(pack.name)}</strong><small>${escapePack(packStatus(pack.status))}</small></span>
            <span class="packs-list-meta">${questionCount} ${questionCount === 1 ? 'pregunta' : 'preguntas'}</span>
        </button>`;
    }).join('');
    list.querySelectorAll('[data-pack-id]').forEach((button) => button.addEventListener('click', () => selectPack(Number(button.dataset.packId))));
}

function selectPack(packId) {
    if (packId === selectedPackId) {
        openMobilePackDetail();
        return;
    }
    if (!canDiscardCategoryChanges()) return;
    selectedPackId = packId;
    activeEditorTab = 'summary';
    selectedQuestionSlot = 'all';
    renderWorkspace();
    openMobilePackDetail();
    updatePackBreadcrumb(selectedPack()?.name);
}

function renderPackEditor() {
    const pack = selectedPack();
    const empty = document.querySelector('#packEditorEmpty');
    const content = document.querySelector('#packEditorContent');
    if (!pack) {
        empty?.classList.remove('hidden');
        content?.classList.add('hidden');
        updatePackBreadcrumb();
        return;
    }
    empty?.classList.add('hidden');
    content?.classList.remove('hidden');
    const revision = packRevision(pack);
    const editable = Boolean(pack.draftRevision);
    const counts = questionCounts(revision);
    const complete = counts.every((count) => count > 0) && (revision?.categories?.length ?? 0) === 6;

    document.querySelector('#packEditorTitle').textContent = pack.name;
    document.querySelector('#packEditorMeta').textContent = `Revisión ${revision?.revisionNumber ?? '-'} · ${revision?.questions?.length ?? 0} preguntas`;
    document.querySelector('#packEditorBadges').innerHTML = `<span class="packs-badge ${editable ? 'is-draft' : 'is-active'}">${packStatus(pack.status)}</span><span class="packs-badge">${pack.kind === 'system' ? 'Sistema' : 'Privado'}</span>`;
    document.querySelector('#packProgress').innerHTML = renderProgress(revision, counts, complete);

    const editButton = document.querySelector('#editPackButton');
    editButton?.classList.toggle('hidden', editable);
    editButton.disabled = editable || (pack.kind === 'system' && packUser?.role !== 'admin');
    const activateButton = document.querySelector('#activatePackButton');
    activateButton?.classList.toggle('hidden', !editable);
    activateButton.disabled = !editable || !complete;
    activateButton.title = complete ? '' : 'Añade al menos una pregunta en cada categoría.';
    const deleteButton = document.querySelector('#deletePackButton');
    deleteButton.disabled = pack.kind === 'system' && pack.name === 'Clasico';
    deleteButton.title = deleteButton.disabled ? 'El pack Clásico está protegido.' : '';

    renderCategoryEditor(revision, editable);
    renderQuestionEditor(revision, editable);
    renderEditorTabs();
    if (!isMobilePackIndex()) updatePackBreadcrumb(pack.name);
}

function renderProgress(revision, counts, complete) {
    const categories = revision?.categories ?? [];
    return `<div class="packs-progress-heading"><div><h3>Preparación de la revisión</h3><p class="muted">${complete ? 'Todo listo para activar.' : 'Cada categoría necesita al menos una pregunta.'}</p></div><strong>${counts.filter((count) => count > 0).length}/6</strong></div>
        <div class="packs-progress-grid">${categories.map((category) => {
            const count = counts[category.slot] ?? 0;
            return `<div class="packs-progress-item${count > 0 ? ' is-complete' : ''}"><i style="--category-color:${escapeAttr(category.color)}"></i><span>${escapePack(category.name)}</span><strong>${count}</strong></div>`;
        }).join('')}</div>`;
}

function renderCategoryEditor(revision, editable) {
    const fields = document.querySelector('#categoryFields');
    if (!fields) return;
    fields.innerHTML = (revision?.categories ?? []).map((category) => `<fieldset data-category-slot="${category.slot}" data-category-key="${escapeAttr(category.key)}" style="--category-color:${escapeAttr(category.color)}">
        <legend>Posición ${category.slot + 1}</legend>
        <label>Nombre<input name="name" value="${escapeAttr(category.name)}" ${editable ? '' : 'disabled'} required></label>
        <label>Color<span class="packs-category-color"><input name="color" type="color" value="${escapeAttr(category.color)}" ${editable ? '' : 'disabled'} required><b>${escapePack(category.color.toUpperCase())}</b></span></label>
    </fieldset>`).join('');
    const apply = document.querySelector('#applyColorSchemeSelect');
    if (apply) { apply.disabled = !editable; apply.value = ''; }
    const save = document.querySelector('#categoriesForm button[type="submit"]');
    if (save) save.disabled = !editable || !categoriesDirty;
    setCategoryDirtyState();
}

function renderQuestionEditor(revision, editable) {
    const categories = revision?.categories ?? [];
    const questions = revision?.questions ?? [];
    const counts = questionCounts(revision);
    const slotSelect = document.querySelector('#questionSlot');
    if (slotSelect) slotSelect.innerHTML = categories.map((category) => `<option value="${category.slot}">${escapePack(category.name)}</option>`).join('');
    const addButton = document.querySelector('#newQuestionButton');
    if (addButton) addButton.disabled = !editable;

    const filters = document.querySelector('#questionFilters');
    if (filters) {
        filters.innerHTML = `<button type="button" class="secondary${selectedQuestionSlot === 'all' ? ' selected' : ''}" data-question-slot="all" aria-pressed="${selectedQuestionSlot === 'all'}">Todas <span>${questions.length}</span></button>`
            + categories.map((category) => `<button type="button" class="secondary${Number(selectedQuestionSlot) === category.slot ? ' selected' : ''}" data-question-slot="${category.slot}" aria-pressed="${Number(selectedQuestionSlot) === category.slot}">${escapePack(category.name)} <span>${counts[category.slot]}</span></button>`).join('');
        filters.querySelectorAll('[data-question-slot]').forEach((button) => button.addEventListener('click', () => {
            selectedQuestionSlot = button.dataset.questionSlot === 'all' ? 'all' : Number(button.dataset.questionSlot);
            renderQuestionEditor(packRevision(selectedPack()), Boolean(selectedPack()?.draftRevision));
        }));
    }

    const visibleQuestions = selectedQuestionSlot === 'all' ? questions : questions.filter((question) => question.slot === selectedQuestionSlot);
    const list = document.querySelector('#packQuestions');
    if (!list) return;
    list.innerHTML = visibleQuestions.length ? visibleQuestions.map((question) => {
        const category = categories.find((item) => item.slot === question.slot);
        return `<article class="packs-question-row">
            <div class="packs-question-copy"><span class="packs-category-dot" style="--category-color:${escapeAttr(category?.color ?? '#506080')}"></span><div><strong>${escapePack(question.question)}</strong><small>${escapePack(category?.name ?? 'Categoría')}</small></div></div>
            <ol>${question.options.map((option, index) => `<li class="${index === question.correct ? 'is-correct' : ''}">${escapePack(option)}</li>`).join('')}</ol>
            ${editable ? `<div class="packs-row-actions"><button type="button" class="secondary" data-edit-question="${question.id}">Editar</button><button type="button" class="secondary" data-delete-question="${question.id}">Eliminar</button></div>` : ''}
        </article>`;
    }).join('') : '<div class="packs-empty-state"><h3>No hay preguntas en esta vista</h3><p class="muted">Añade la primera para completar la categoría.</p></div>';
    list.querySelectorAll('[data-edit-question]').forEach((button) => button.addEventListener('click', () => {
        const question = questions.find((item) => item.id === Number(button.dataset.editQuestion));
        openQuestionDialog(question, button);
    }));
    list.querySelectorAll('[data-delete-question]').forEach((button) => button.addEventListener('click', () => deleteQuestion(Number(button.dataset.deleteQuestion), button)));
}

function switchEditorTab(tabName) {
    if (activeEditorTab === 'categories' && tabName !== 'categories' && !canDiscardCategoryChanges()) return;
    activeEditorTab = tabName;
    renderEditorTabs();
}

function renderEditorTabs() {
    document.querySelectorAll('[data-editor-tab]').forEach((button) => button.setAttribute('aria-selected', String(button.dataset.editorTab === activeEditorTab)));
    document.querySelector('#packSummaryPanel')?.classList.toggle('hidden', activeEditorTab !== 'summary');
    document.querySelector('#packCategoriesPanel')?.classList.toggle('hidden', activeEditorTab !== 'categories');
    document.querySelector('#packQuestionsPanel')?.classList.toggle('hidden', activeEditorTab !== 'questions');
}

function switchSection(sectionName) {
    if (sectionName === activeSection) return;
    if (!canDiscardCategoryChanges()) return;
    activeSection = sectionName;
    document.querySelector('.packs-shell')?.classList.remove('is-pack-detail');
    document.querySelector('#packsWorkspace')?.classList.remove('is-detail-open');
    document.querySelectorAll('[data-section-tab]').forEach((button) => button.setAttribute('aria-selected', String(button.dataset.sectionTab === sectionName)));
    document.querySelector('#packsSection')?.classList.toggle('hidden', sectionName !== 'packs');
    document.querySelector('#schemesSection')?.classList.toggle('hidden', sectionName !== 'schemes');
    document.querySelector('#importSection')?.classList.toggle('hidden', sectionName !== 'import');
    updatePackBreadcrumb(sectionName === 'packs' ? selectedPack()?.name : sectionName === 'schemes' ? 'Esquemas de color' : 'Importar');
}

function openNewPackDialog(trigger) {
    const form = document.querySelector('#createPackForm');
    form.reset();
    renderColorSchemeSelects();
    const kind = packUser?.role === 'admin' ? adminPackFilter : 'user';
    document.querySelector('#newPackKindHelp').textContent = kind === 'system' ? 'Se creará como pack del sistema.' : 'Se creará como borrador privado.';
    showDialog(document.querySelector('#newPackDialog'), trigger);
}

async function createPack(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    await withSubmitting(formElement, async () => {
        const form = new FormData(formElement);
        const kind = packUser?.role === 'admin' ? adminPackFilter : 'user';
        const result = await postJson('/packs/create', { name: form.get('name'), colorSchemeId: Number(form.get('colorSchemeId')) || null, kind });
        categoriesDirty = false;
        selectedPackId = result.pack.id;
        document.querySelector('#newPackDialog')?.close();
        await loadPacks();
        renderWorkspace();
        openMobilePackDetail();
        notifyPack('Pack creado. Ya puedes completar sus preguntas.');
    });
}

function markCategoriesDirty() {
    if (!selectedPack()?.draftRevision) return;
    categoriesDirty = true;
    setCategoryDirtyState();
}

function setCategoryDirtyState() {
    const state = document.querySelector('#categoryDirtyState');
    if (state) state.textContent = categoriesDirty ? 'Cambios pendientes de guardar' : 'Sin cambios pendientes';
    const button = document.querySelector('#categoriesForm button[type="submit"]');
    if (button) button.disabled = !selectedPack()?.draftRevision || !categoriesDirty;
}

function canDiscardCategoryChanges() {
    if (!categoriesDirty) return true;
    if (!window.confirm('Hay cambios de categorías sin guardar. ¿Quieres descartarlos?')) return false;
    categoriesDirty = false;
    return true;
}

async function saveCategories(event) {
    event.preventDefault();
    const categories = [...document.querySelectorAll('[data-category-slot]')].map((field) => ({
        slot: Number(field.dataset.categorySlot),
        key: field.dataset.categoryKey,
        name: field.querySelector('[name="name"]').value,
        color: field.querySelector('[name="color"]').value,
    }));
    await withSubmitting(event.currentTarget, async () => {
        await postJson('/packs/categories', { packId: selectedPackId, categories });
        categoriesDirty = false;
        await loadPacks();
        renderWorkspace();
        notifyPack('Categorías guardadas.');
    });
}

function applyColorSchemeToPack(event) {
    const scheme = colorSchemes.find((item) => item.id === Number(event.target.value));
    if (!scheme) return;
    document.querySelectorAll('[data-category-slot]').forEach((field) => {
        const slot = Number(field.dataset.categorySlot);
        const color = scheme.colors[slot];
        const input = field.querySelector('[name="color"]');
        const label = field.querySelector('.packs-category-color b');
        if (input) input.value = color;
        if (label) label.textContent = color.toUpperCase();
        field.style.setProperty('--category-color', color);
    });
    markCategoriesDirty();
    notifyPack(`Colores de ${scheme.name} aplicados. Revisa y guarda los cambios.`);
}

function openQuestionDialog(question, trigger) {
    const form = document.querySelector('#questionForm');
    form.reset();
    form.elements.questionId.value = question?.id ?? '';
    form.elements.question.value = question?.question ?? '';
    document.querySelector('#questionDialogTitle').textContent = question ? 'Editar pregunta' : 'Añadir pregunta';
    const revision = packRevision(selectedPack());
    document.querySelector('#questionSlot').innerHTML = (revision?.categories ?? []).map((category) => `<option value="${category.slot}">${escapePack(category.name)}</option>`).join('');
    form.elements.slot.value = String(question?.slot ?? (selectedQuestionSlot === 'all' ? 0 : selectedQuestionSlot));
    [...form.querySelectorAll('[name="options[]"]')].forEach((input, index) => { input.value = question?.options?.[index] ?? ''; });
    [...form.querySelectorAll('[name="correct"]')].forEach((radio) => { radio.checked = Number(radio.value) === (question?.correct ?? 0); });
    showDialog(document.querySelector('#questionDialog'), trigger);
}

async function saveQuestion(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    await withSubmitting(formElement, async () => {
        const data = new FormData(formElement);
        const questionId = Number(data.get('questionId')) || null;
        const payload = {
            packId: selectedPackId,
            questionId,
            slot: Number(data.get('slot')),
            question: data.get('question'),
            options: data.getAll('options[]'),
            correct: Number(data.get('correct')),
        };
        await postJson(questionId ? '/packs/questions/update' : '/packs/questions', payload);
        document.querySelector('#questionDialog')?.close();
        await loadPacks();
        activeEditorTab = 'questions';
        renderWorkspace();
        notifyPack(questionId ? 'Pregunta actualizada.' : 'Pregunta añadida.');
    });
}

async function deleteQuestion(questionId, trigger) {
    const question = packRevision(selectedPack())?.questions?.find((item) => item.id === questionId);
    const confirmed = await confirmDestructive('Eliminar pregunta', `Se eliminará “${question?.question ?? 'esta pregunta'}” del borrador.` , '', trigger);
    if (!confirmed) return;
    await postJson('/packs/questions/delete', { packId: selectedPackId, questionId });
    await loadPacks();
    activeEditorTab = 'questions';
    renderWorkspace();
    notifyPack('Pregunta eliminada.');
}

async function packAction(path, successMessage) {
    await postJson(path, { packId: selectedPackId });
    categoriesDirty = false;
    await loadPacks();
    renderWorkspace();
    notifyPack(successMessage);
}

async function deleteSelectedPack(event) {
    const pack = selectedPack();
    if (!pack) return;
    const confirmed = await confirmDestructive('Eliminar pack', 'Esta acción retirará el pack de tu biblioteca. Las partidas ya creadas conservarán su snapshot.', pack.name, event.currentTarget);
    if (!confirmed) return;
    await postJson('/packs/delete', { packId: pack.id });
    selectedPackId = null;
    categoriesDirty = false;
    await loadPacks();
    renderWorkspace();
    showMobilePackIndex();
    notifyPack('Pack eliminado.');
}

async function downloadPack(format) {
    const pack = selectedPack();
    if (!pack) return;
    const data = await getJson(`/packs/export?id=${pack.id}&format=${format}`);
    downloadText(data.content, `${safeFilename(pack.name)}.${format}`, format === 'json' ? 'application/json' : 'text/csv');
    document.querySelector('#packMoreActions')?.removeAttribute('open');
}

async function copyPack(format) {
    const pack = selectedPack();
    if (!pack) return;
    const data = await getJson(`/packs/export?id=${pack.id}&format=${format}`);
    await navigator.clipboard.writeText(data.content);
    document.querySelector('#packMoreActions')?.removeAttribute('open');
    notifyPack(`${format.toUpperCase()} copiado al portapapeles.`);
}

function renderColorSchemeSelects() {
    const grouped = colorSchemeOptions();
    const create = document.querySelector('#createPackColorScheme');
    if (create) {
        create.innerHTML = grouped;
        const classic = colorSchemes.find((scheme) => scheme.kind === 'system' && scheme.name === 'Clasico');
        create.value = classic ? String(classic.id) : '';
    }
    const apply = document.querySelector('#applyColorSchemeSelect');
    if (apply) apply.innerHTML = '<option value="">Selecciona un esquema</option>' + grouped;
}

function colorSchemeOptions() {
    const system = colorSchemes.filter((scheme) => scheme.kind === 'system');
    const personal = colorSchemes.filter((scheme) => scheme.kind === 'user');
    return `${system.length ? `<optgroup label="Sistema">${system.map(schemeOption).join('')}</optgroup>` : ''}${personal.length ? `<optgroup label="Mis esquemas">${personal.map(schemeOption).join('')}</optgroup>` : ''}`;
}

function schemeOption(scheme) { return `<option value="${scheme.id}">${escapePack(scheme.name)}</option>`; }

function renderSchemeLibrary() {
    const list = document.querySelector('#schemeList');
    if (!list) return;
    const isAdmin = packUser?.role === 'admin';
    const visible = isAdmin ? colorSchemes.filter((scheme) => scheme.kind === adminSchemeFilter) : colorSchemes;
    list.innerHTML = visible.length ? visible.map((scheme) => `<article class="panel packs-scheme-card">
        <header><div><div class="packs-badges"><span class="packs-badge">${scheme.kind === 'system' ? 'Sistema' : 'Personal'}</span>${scheme.kind === 'system' && scheme.name === 'Clasico' ? '<span class="packs-badge is-protected">Protegido</span>' : ''}</div><h3>${escapePack(scheme.name)}</h3></div>
        ${scheme.editable ? `<div class="packs-row-actions"><button type="button" class="secondary" data-edit-scheme="${scheme.id}">Editar</button>${scheme.kind === 'system' && scheme.name === 'Clasico' ? '' : `<button type="button" class="secondary" data-delete-scheme="${scheme.id}">Eliminar</button>`}</div>` : ''}</header>
        <div class="packs-scheme-swatches" aria-label="Seis colores del esquema">${scheme.colors.map((color) => `<i style="--swatch:${escapeAttr(color)}"><span>${escapePack(color.toUpperCase())}</span></i>`).join('')}</div>
    </article>`).join('') : '<div class="panel packs-empty-state"><h3>No hay esquemas en esta vista</h3><p class="muted">Crea una combinación para reutilizarla en tus packs.</p></div>';
    list.querySelectorAll('[data-edit-scheme]').forEach((button) => button.addEventListener('click', () => openSchemeDialog(colorSchemes.find((item) => item.id === Number(button.dataset.editScheme)), button)));
    list.querySelectorAll('[data-delete-scheme]').forEach((button) => button.addEventListener('click', () => deleteColorScheme(Number(button.dataset.deleteScheme), button)));
}

function openSchemeDialog(scheme, trigger) {
    const form = document.querySelector('#schemeForm');
    form.reset();
    const kind = scheme?.kind ?? (packUser?.role === 'admin' ? adminSchemeFilter : 'user');
    form.elements.colorSchemeId.value = scheme?.id ?? '';
    form.elements.kind.value = kind;
    form.elements.name.value = scheme?.name ?? '';
    form.elements.name.readOnly = scheme?.kind === 'system' && scheme?.name === 'Clasico';
    [...form.querySelectorAll('[name="colors[]"]')].forEach((input, index) => { input.value = scheme?.colors?.[index] ?? input.defaultValue; });
    document.querySelector('#schemeDialogTitle').textContent = scheme ? 'Editar esquema' : `Nuevo esquema ${kind === 'system' ? 'del sistema' : 'personal'}`;
    renderColorInputs();
    showDialog(document.querySelector('#schemeDialog'), trigger);
}

function renderColorInputs() {
    document.querySelectorAll('#schemeForm .color-field').forEach((field) => {
        const input = field.querySelector('input[type="color"]');
        const text = field.querySelector('span');
        if (input && text) text.textContent = input.value.toUpperCase();
    });
}

async function saveColorScheme(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    await withSubmitting(formElement, async () => {
        const form = new FormData(formElement);
        const id = Number(form.get('colorSchemeId')) || null;
        await postJson(id ? '/packs/colors/update' : '/packs/colors/create', {
            colorSchemeId: id,
            kind: form.get('kind'),
            name: form.get('name'),
            colors: form.getAll('colors[]'),
        });
        document.querySelector('#schemeDialog')?.close();
        await loadColorSchemes();
        renderSchemeLibrary();
        renderPackEditor();
        notifyPack(id ? 'Esquema actualizado.' : 'Esquema creado.');
    });
}

async function deleteColorScheme(id, trigger) {
    const scheme = colorSchemes.find((item) => item.id === id);
    const confirmed = await confirmDestructive('Eliminar esquema', `Se eliminará “${scheme?.name ?? 'este esquema'}”. Los packs y salas existentes conservarán sus colores.`, '', trigger);
    if (!confirmed) return;
    await postJson('/packs/colors/delete', { colorSchemeId: id });
    await loadColorSchemes();
    renderSchemeLibrary();
    renderPackEditor();
    notifyPack('Esquema eliminado.');
}

async function loadImportFile(file) {
    if (!file) return;
    const content = await file.text();
    document.querySelector('#importContent').value = content;
    document.querySelector('#importFormat').value = inferImportFormat(file.name, content);
    resetImportPreview(`Archivo seleccionado: ${file.name}`);
}

function inferImportFormat(filename, content) {
    if (String(filename).toLowerCase().endsWith('.csv')) return 'csv';
    if (String(filename).toLowerCase().endsWith('.json')) return 'json';
    return String(content).trimStart().startsWith('{') ? 'json' : 'csv';
}

async function previewImport(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    await withSubmitting(formElement, async () => {
        const form = new FormData(formElement);
        const format = String(form.get('format'));
        const content = String(form.get('content'));
        const result = await postJson('/packs/import/preview', { format, content });
        importPreviewData = { format, content, preview: result.preview };
        renderImportPreview();
    });
}

function renderImportPreview() {
    const preview = document.querySelector('#importPreview');
    if (!preview || !importPreviewData) return;
    const data = importPreviewData.preview;
    preview.innerHTML = `<div class="packs-import-preview-heading"><div><span class="packs-badge is-draft">Borrador privado</span><h3>${escapePack(data.name)}</h3><p class="muted">${data.questionCount} preguntas en seis categorías</p></div></div>
        <div class="packs-import-categories">${data.categories.map((category, index) => `<div><i style="--category-color:${escapeAttr(category.color)}"></i><span>${escapePack(category.name)}</span><strong>${data.questionsPerCategory[index]}</strong></div>`).join('')}</div>
        <button type="button" data-confirm-import>Importar y abrir editor</button>`;
    preview.querySelector('[data-confirm-import]')?.addEventListener('click', importPack);
}

function resetImportPreview(message) {
    importPreviewData = null;
    const preview = document.querySelector('#importPreview');
    if (preview) preview.innerHTML = `<div class="packs-empty-state"><h3>Vista previa</h3><p class="muted">${escapePack(message)}</p></div>`;
}

async function importPack(event) {
    if (!importPreviewData) return;
    const button = event.currentTarget;
    button.disabled = true;
    try {
        const result = await postJson('/packs/import', { format: importPreviewData.format, content: importPreviewData.content });
        adminPackFilter = 'user';
        const personalFilter = document.querySelector('[data-pack-filter="user"]');
        if (personalFilter) updatePressedButtons('[data-pack-filter]', personalFilter);
        selectedPackId = result.pack.id;
        activeSection = 'packs';
        activeEditorTab = 'summary';
        importPreviewData = null;
        document.querySelector('#importPreviewForm')?.reset();
        await loadPacks();
        switchSectionViewWithoutGuard('packs');
        renderWorkspace();
        openMobilePackDetail();
        notifyPack('Pack importado como borrador privado.');
    } finally {
        button.disabled = false;
    }
}

function switchSectionViewWithoutGuard(sectionName) {
    activeSection = sectionName;
    document.querySelectorAll('[data-section-tab]').forEach((button) => button.setAttribute('aria-selected', String(button.dataset.sectionTab === sectionName)));
    document.querySelector('#packsSection')?.classList.toggle('hidden', sectionName !== 'packs');
    document.querySelector('#schemesSection')?.classList.toggle('hidden', sectionName !== 'schemes');
    document.querySelector('#importSection')?.classList.toggle('hidden', sectionName !== 'import');
}

function downloadTemplate(format) {
    const categories = [
        ['history', 'Historia', '#f2c94c'], ['sports', 'Deportes y ocio', '#f2994a'], ['geography', 'Geografía', '#2f80ed'],
        ['art', 'Arte y literatura', '#8b5a2b'], ['science', 'Ciencia y naturaleza', '#27ae60'], ['entertainment', 'Entretenimiento', '#d94a9b'],
    ];
    if (format === 'json') {
        const pack = {
            format_version: 1,
            pack: {
                name: 'Mi pack',
                categories: categories.map((category, slot) => ({ slot, key: category[0], name: category[1], color: category[2] })),
                questions: categories.map((category, slot) => ({ slot, question: `Pregunta de ${category[1]}`, options: ['Respuesta A', 'Respuesta B', 'Respuesta C', 'Respuesta D'], correct: 0 })),
            },
        };
        downloadText(JSON.stringify(pack, null, 2), 'plantilla-pack.json', 'application/json');
        return;
    }
    const header = 'pack_name,category_slot,category_key,category_name,category_color,question,option_a,option_b,option_c,option_d,correct';
    const rows = categories.map((category, slot) => ['Mi pack', slot, ...category, `Pregunta de ${category[1]}`, 'Respuesta A', 'Respuesta B', 'Respuesta C', 'Respuesta D', 0].map(csvCell).join(','));
    downloadText([header, ...rows].join('\n'), 'plantilla-pack.csv', 'text/csv');
}

function csvCell(value) {
    const text = String(value);
    return /[",\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
}

function confirmDestructive(title, message, requiredName = '', trigger = null) {
    const dialog = document.querySelector('#confirmDialog');
    document.querySelector('#confirmTitle').textContent = title;
    document.querySelector('#confirmMessage').textContent = message;
    confirmRequiredName = requiredName;
    const wrap = document.querySelector('#confirmNameWrap');
    const input = document.querySelector('#confirmNameInput');
    wrap.classList.toggle('hidden', !requiredName);
    input.value = '';
    input.placeholder = requiredName;
    updateConfirmButton();
    showDialog(dialog, trigger);
    return new Promise((resolve) => { confirmResolver = resolve; });
}

function updateConfirmButton() {
    const input = document.querySelector('#confirmNameInput');
    const submit = document.querySelector('#confirmSubmit');
    submit.disabled = Boolean(confirmRequiredName) && input.value !== confirmRequiredName;
}

function acceptConfirmation(event) { event.preventDefault(); finishConfirmation(true); }
function cancelConfirmation() { finishConfirmation(false); }

function finishConfirmation(result) {
    const resolve = confirmResolver;
    confirmResolver = null;
    document.querySelector('#confirmDialog')?.close();
    if (resolve) resolve(result);
}

function showDialog(dialog, trigger) {
    if (!dialog) return;
    if (trigger) dialogTriggers.set(dialog, trigger);
    dialog.showModal();
    requestAnimationFrame(() => dialog.querySelector('input:not([type="hidden"]), select, button')?.focus());
}

function openMobilePackDetail() {
    document.querySelector('#packsWorkspace')?.classList.add('is-detail-open');
    if (window.matchMedia('(max-width: 760px)').matches) {
        document.querySelector('.packs-shell')?.classList.add('is-pack-detail');
        updatePackBreadcrumb(selectedPack()?.name);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        document.querySelector('#packEditorTitle')?.focus();
    }
}

function showMobilePackIndex() {
    document.querySelector('#packsWorkspace')?.classList.remove('is-detail-open');
    document.querySelector('.packs-shell')?.classList.remove('is-pack-detail');
    updatePackBreadcrumb();
    document.querySelector(`[data-pack-id="${selectedPackId}"]`)?.focus();
}

function isMobilePackIndex() {
    return window.matchMedia('(max-width: 760px)').matches && !document.querySelector('#packsWorkspace')?.classList.contains('is-detail-open');
}

function updatePressedButtons(selector, activeButton) {
    document.querySelectorAll(selector).forEach((button) => {
        const selected = button === activeButton;
        button.classList.toggle('selected', selected);
        button.setAttribute('aria-pressed', String(selected));
    });
}

function questionCounts(revision) {
    const counts = arrayOfSixZeros();
    (revision?.questions ?? []).forEach((question) => { if (question.slot >= 0 && question.slot < 6) counts[question.slot]++; });
    return counts;
}

function arrayOfSixZeros() { return [0, 0, 0, 0, 0, 0]; }
function selectedPack() { return packs.find((pack) => pack.id === selectedPackId) ?? null; }
function packRevision(pack) { return pack?.draftRevision ?? pack?.currentRevision ?? null; }
function packStatus(status) { return status === 'active' ? 'Activo' : 'Borrador'; }
function safeFilename(value) { return String(value).trim().toLowerCase().replace(/[^a-z0-9áéíóúüñ]+/gi, '-').replace(/^-|-$/g, '') || 'pack'; }

function updatePackBreadcrumb(lastLevel = null) {
    const breadcrumbs = document.querySelector('#packBreadcrumbs ol');
    if (!breadcrumbs) return;
    breadcrumbs.innerHTML = '<li><a href="./">Jugar</a></li><li><a href="packs.php">Packs</a></li>'
        + (lastLevel ? `<li><span aria-current="page">${escapePack(lastLevel)}</span></li>` : '');
}

function downloadText(content, filename, mimeType) {
    const blob = new Blob([content], { type: `${mimeType};charset=utf-8` });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

async function withSubmitting(form, operation) {
    const button = form.querySelector('button[type="submit"]');
    if (button?.disabled) return;
    if (button) button.disabled = true;
    try { await operation(); } finally { if (button) button.disabled = false; }
}

async function getJson(path) {
    const response = await fetch(packsApi + path);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error?.message ?? data.error ?? 'Error de API.');
    return data;
}

async function postJson(path, payload) {
    const response = await fetch(packsApi + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': packCsrf },
        body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) {
        const message = data.error?.message ?? data.error ?? 'Error de API.';
        notifyPack(message);
        throw new Error(message);
    }
    return data;
}

function notifyPack(message) {
    const toast = document.querySelector('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('hidden');
    window.clearTimeout(notifyPack.timer);
    notifyPack.timer = window.setTimeout(() => toast.classList.add('hidden'), 3500);
}

function escapePack(value) {
    return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function escapeAttr(value) { return escapePack(value).replaceAll('`', '&#096;'); }
