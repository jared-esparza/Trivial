const packsApi = './api.php';
let packCsrf = null;
let packs = [];
let selectedPackId = null;
let packUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    const me = await getJson('/auth/me');
    packCsrf = me.csrfToken;
    packUser = me.user;
    const isAdmin = me.user?.role === 'admin';
    document.querySelector('#adminPackControls')?.classList.toggle('hidden', !isAdmin);
    document.querySelector('#colorSchemeAdmin')?.classList.toggle('hidden', !isAdmin);
    renderColorInputs();
    bindPackForms();
    await Promise.all([loadPacks(), isAdmin ? loadColorSchemes() : Promise.resolve()]);
});

function bindPackForms() {
    document.querySelector('#createPackForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        await withSubmitting(formElement, async () => {
            const form = new FormData(formElement);
            const result = await postJson('/packs/create', { name: form.get('name'), kind: form.get('kind') ?? 'user' });
            selectedPackId = result.pack.id;
            formElement.reset();
            notifyPack('Pack creado y seleccionado.');
            await loadPacks();
        });
    });
    document.querySelector('#importPackForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        await withSubmitting(formElement, async () => {
            const data = new FormData(formElement);
            const result = await postJson('/packs/import', { format: data.get('format'), content: data.get('content') });
            selectedPackId = result.pack.id;
            formElement.reset();
            notifyPack('Pack importado y seleccionado.');
            await loadPacks();
        });
    });
    document.querySelector('#categoriesForm')?.addEventListener('submit', saveCategories);
    document.querySelector('#questionForm')?.addEventListener('submit', addQuestion);
    document.querySelector('#editPackButton')?.addEventListener('click', () => packAction('/packs/edit'));
    document.querySelector('#activatePackButton')?.addEventListener('click', () => packAction('/packs/activate'));
    document.querySelector('#deletePackButton')?.addEventListener('click', () => packAction('/packs/delete', true));
    document.querySelector('#exportJsonButton')?.addEventListener('click', () => exportPack('json'));
    document.querySelector('#exportCsvButton')?.addEventListener('click', () => exportPack('csv'));
    document.querySelector('#colorSchemeForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        await withSubmitting(formElement, async () => {
            const form = new FormData(formElement);
            await postJson('/packs/colors/create', { name: form.get('name'), colors: form.getAll('colors[]') });
            formElement.reset();
            renderColorInputs();
            notifyPack('Pack de colores creado.');
            await loadColorSchemes();
        });
    });
}

async function loadPacks() {
    const data = await getJson('/packs');
    packs = data.packs;
    renderPackList();
    if (selectedPackId && packs.some((pack) => pack.id === selectedPackId)) renderPackEditor();
    else document.querySelector('#packEditor')?.classList.add('hidden');
}

function renderPackList() {
    const list = document.querySelector('#packList');
    list.innerHTML = packs.map((pack) => {
        const selected = selectedPackId === pack.id;
        return `
        <button class="question-item pack-list-item${selected ? ' selected' : ''}" type="button" data-pack-id="${pack.id}" aria-pressed="${selected ? 'true' : 'false'}">
            <strong>${escapePack(pack.name)}</strong>
            <span>${escapePack(pack.kind === 'system' ? 'Sistema' : pack.status)}</span>
        </button>`;
    }).join('');
    list.querySelectorAll('[data-pack-id]').forEach((button) => {
        button.addEventListener('click', () => {
            selectedPackId = Number(button.dataset.packId);
            renderPackEditor();
        });
    });
}

function renderPackEditor() {
    const pack = selectedPack();
    if (!pack) return;
    const revision = pack.draftRevision ?? pack.currentRevision;
    const editable = Boolean(pack.draftRevision);
    document.querySelector('#packEditor')?.classList.remove('hidden');
    document.querySelector('#packEditorSummary').innerHTML = `<p class="eyebrow">${escapePack(pack.status)}</p><h1>${escapePack(pack.name)}</h1><p>Revisi&oacute;n ${revision?.revisionNumber ?? '-'} &middot; ${revision?.questions.length ?? 0} preguntas</p>`;
    document.querySelector('#categoryFields').innerHTML = (revision?.categories ?? []).map((category) => `
        <fieldset data-category-slot="${category.slot}">
            <legend>Posici&oacute;n ${category.slot + 1}</legend>
            <label>Clave<input name="key" value="${escapePack(category.key)}" ${editable ? '' : 'disabled'} required></label>
            <label>Nombre<input name="name" value="${escapePack(category.name)}" ${editable ? '' : 'disabled'} required></label>
            <label>Color<input name="color" type="color" value="${escapePack(category.color)}" ${editable ? '' : 'disabled'} required></label>
        </fieldset>`).join('');
    document.querySelector('#questionSlot').innerHTML = (revision?.categories ?? []).map((category) => `<option value="${category.slot}">${escapePack(category.name)}</option>`).join('');
    document.querySelector('#categoriesForm button').disabled = !editable;
    document.querySelector('#questionForm button').disabled = !editable;
    document.querySelector('#editPackButton').disabled = editable || (pack.kind === 'system' && packUser?.role !== 'admin');
    document.querySelector('#activatePackButton').disabled = !editable;
    document.querySelector('#deletePackButton').disabled = pack.kind === 'system' && (packUser?.role !== 'admin' || pack.name === 'Clasico');
    document.querySelector('#packQuestions').innerHTML = (revision?.questions ?? []).map((question) => `<article class="question-item"><strong>${escapePack(question.question)}</strong><p class="muted">Categor&iacute;a ${question.slot + 1} &middot; ${question.options.map(escapePack).join(' / ')}</p></article>`).join('');
}

async function saveCategories(event) {
    event.preventDefault();
    const categories = [...document.querySelectorAll('[data-category-slot]')].map((field) => ({
        slot: Number(field.dataset.categorySlot),
        key: field.querySelector('[name="key"]').value,
        name: field.querySelector('[name="name"]').value,
        color: field.querySelector('[name="color"]').value
    }));
    await postJson('/packs/categories', { packId: selectedPackId, categories });
    notifyPack('Categor&iacute;as guardadas. Al cambiarlas se reinician las preguntas del borrador.');
    await loadPacks();
}

async function addQuestion(event) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const options = String(data.get('options')).split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
    await postJson('/packs/questions', {
        packId: selectedPackId,
        slot: Number(data.get('slot')),
        question: data.get('question'),
        options,
        correct: Number(data.get('correct'))
    });
    event.currentTarget.reset();
    notifyPack('Pregunta a&ntilde;adida.');
    await loadPacks();
}

async function packAction(path, clearSelection = false) {
    await postJson(path, { packId: selectedPackId });
    if (clearSelection) selectedPackId = null;
    await loadPacks();
}

async function exportPack(format) {
    const data = await getJson(`/packs/export?id=${selectedPackId}&format=${format}`);
    document.querySelector('#packExportOutput').value = data.content;
}

async function loadColorSchemes() {
    const data = await getJson('/packs/colors');
    const list = document.querySelector('#colorSchemeList');
    if (!list) return;
    list.innerHTML = data.colorSchemes.map((scheme) => `
        <article class="question-item">
            <strong>${escapePack(scheme.name)}</strong>
            <span>${scheme.colors.map((color) => `<i class="color-swatch" style="background:${escapePack(color)}"></i>`).join('')}</span>
            <button type="button" data-delete-scheme="${scheme.id}">Eliminar</button>
        </article>`).join('');
    list.querySelectorAll('[data-delete-scheme]').forEach((button) => button.addEventListener('click', async () => {
        await postJson('/packs/colors/delete', { colorSchemeId: Number(button.dataset.deleteScheme) });
        await loadColorSchemes();
    }));
}

function renderColorInputs() {
    document.querySelectorAll('.color-field input[type="color"]').forEach((input) => {
        const value = input.value || '#222222';
        input.value = value;
        const label = input.closest('.color-field');
        const text = label?.querySelector('span');
        if (text) text.textContent = value.toUpperCase();
        input.addEventListener('input', () => {
            const span = input.closest('.color-field')?.querySelector('span');
            if (span) span.textContent = input.value.toUpperCase();
        });
    });
}

async function withSubmitting(form, operation) {
    const button = form.querySelector('button[type="submit"]');
    if (button?.disabled) return;
    if (button) button.disabled = true;
    try {
        await operation();
    } finally {
        if (button) button.disabled = false;
    }
}

function selectedPack() { return packs.find((pack) => pack.id === selectedPackId); }

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
        body: JSON.stringify(payload)
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
    setTimeout(() => toast.classList.add('hidden'), 3500);
}

function escapePack(value) {
    return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
