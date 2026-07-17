const adminApi = './api.php';
const adminState = {
    users: [],
    currentUser: null,
    csrfToken: null,
    selectedId: null,
    section: 'users',
    dirty: false,
    listTrigger: null
};

document.addEventListener('DOMContentLoaded', async () => {
    bindAdminSections();
    bindAdminFilters();
    bindAdminWorkspace();
    bindAdminConfirmation();
    applyAdminLocation(false);
    await loadAdminUsers();
});

window.addEventListener('popstate', () => applyAdminLocation(false));
window.addEventListener('beforeunload', (event) => {
    if (!adminState.dirty) return;
    event.preventDefault();
    event.returnValue = '';
});

function bindAdminSections() {
    document.querySelector('#adminSectionTabs')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-admin-section]');
        if (button) switchAdminSection(button.dataset.adminSection);
    });
    document.querySelector('#adminSectionTabs')?.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        const tabs = [...event.currentTarget.querySelectorAll('[role="tab"]')];
        const current = tabs.indexOf(document.activeElement);
        if (current < 0) return;
        event.preventDefault();
        const next = (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        tabs[next].focus();
        tabs[next].click();
    });
}

function bindAdminFilters() {
    ['#adminUserSearch', '#adminRoleFilter', '#adminStatusFilter', '#adminVerificationFilter', '#adminSort'].forEach((selector) => {
        const element = document.querySelector(selector);
        element?.addEventListener(element.type === 'search' ? 'input' : 'change', () => {
            renderAdminUserList();
        });
    });
}

function bindAdminWorkspace() {
    document.querySelector('#adminUserList')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-admin-user-id]');
        if (!button) return;
        selectAdminUser(Number(button.dataset.adminUserId), button);
    });
    document.querySelector('#adminBackToUsers')?.addEventListener('click', closeAdminUserDetail);
}

function applyAdminLocation(update = false) {
    const requested = new URLSearchParams(location.search).get('section');
    switchAdminSection(requested === 'content' ? 'content' : 'users', update);
}

function switchAdminSection(section, updateLocation = true) {
    adminState.section = section === 'content' ? 'content' : 'users';
    document.querySelectorAll('[data-admin-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.adminPanel !== adminState.section;
    });
    document.querySelectorAll('[data-admin-section]').forEach((tab) => {
        const selected = tab.dataset.adminSection === adminState.section;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
    });
    if (updateLocation) {
        const url = new URL(location.href);
        url.search = '';
        url.searchParams.set('section', adminState.section);
        history.pushState({ section: adminState.section }, '', url);
    }
}

async function loadAdminUsers() {
    setAdminListStatus('Cargando usuarios…');
    try {
        const [meResponse, usersResponse] = await Promise.all([
            fetch(`${adminApi}/auth/me`),
            fetch(`${adminApi}/admin/users`)
        ]);
        const me = await meResponse.json();
        const users = await usersResponse.json();
        if (!meResponse.ok || !me.user) throw new Error('No se pudo comprobar la sesión administradora.');
        if (!usersResponse.ok) throw new Error(adminErrorMessage(users, 'No se pudieron cargar los usuarios.'));
        adminState.currentUser = me.user;
        adminState.csrfToken = me.csrfToken ?? null;
        adminState.users = users.users ?? [];
        if (!adminState.users.some((user) => user.id === adminState.selectedId)) adminState.selectedId = null;
        setAdminListStatus('');
        renderAdminUserList();
        if (adminState.selectedId) renderAdminUserDetail();
    } catch (error) {
        setAdminListStatus(error.message, true);
    }
}

function filteredAdminUsers() {
    const search = normalizeAdminText(document.querySelector('#adminUserSearch')?.value ?? '');
    const role = document.querySelector('#adminRoleFilter')?.value ?? 'all';
    const status = document.querySelector('#adminStatusFilter')?.value ?? 'all';
    const verification = document.querySelector('#adminVerificationFilter')?.value ?? 'all';
    const sort = document.querySelector('#adminSort')?.value ?? 'recent';
    const result = adminState.users.filter((user) => {
        const matchesSearch = !search || normalizeAdminText(`${user.displayName} ${user.email}`).includes(search);
        const matchesRole = role === 'all' || user.role === role;
        const matchesStatus = status === 'all' || user.status === status;
        const matchesVerification = verification === 'all' || (verification === 'verified' ? user.emailVerified : !user.emailVerified);
        return matchesSearch && matchesRole && matchesStatus && matchesVerification;
    });
    return result.sort((a, b) => {
        if (sort === 'name') return a.displayName.localeCompare(b.displayName, 'es', { sensitivity: 'base' });
        if (sort === 'email') return a.email.localeCompare(b.email, 'es', { sensitivity: 'base' });
        if (sort === 'oldest') return new Date(a.createdAt) - new Date(b.createdAt);
        return new Date(b.createdAt) - new Date(a.createdAt);
    });
}

function renderAdminUserList() {
    const list = document.querySelector('#adminUserList');
    const count = document.querySelector('#adminUserCount');
    if (!list || !count) return;
    const users = filteredAdminUsers();
    count.textContent = `${users.length} ${users.length === 1 ? 'resultado' : 'resultados'}`;
    if (!users.length) {
        list.innerHTML = '<div class="admin-list-empty"><strong>No hay coincidencias</strong><span>Prueba a cambiar la búsqueda o los filtros.</span></div>';
        return;
    }
    list.innerHTML = users.map((user) => `
        <button type="button" class="admin-user-list-item ${user.id === adminState.selectedId ? 'is-selected' : ''}" data-admin-user-id="${Number(user.id)}" aria-pressed="${user.id === adminState.selectedId}">
            <span class="admin-user-avatar" aria-hidden="true">${escapeAdmin(user.displayName.slice(0, 1).toUpperCase())}</span>
            <span class="admin-user-list-identity"><strong>${escapeAdmin(user.displayName)}</strong><small>${escapeAdmin(user.email)}</small></span>
            <span class="admin-user-list-badges"><span class="status-badge">${user.role === 'admin' ? 'Administrador' : 'Usuario'}</span><span class="status-badge ${user.status === 'disabled' ? 'is-disabled' : 'is-active'}">${user.status === 'disabled' ? 'Desactivado' : 'Activo'}</span></span>
        </button>`).join('');
}

async function selectAdminUser(userId, trigger = null) {
    if (adminState.dirty && userId !== adminState.selectedId) {
        const discard = await confirmAdminChange('Hay cambios sin guardar en el usuario actual. Si continúas, se descartarán.');
        if (!discard) return;
    }
    adminState.selectedId = userId;
    adminState.listTrigger = trigger;
    adminState.dirty = false;
    renderAdminUserList();
    renderAdminUserDetail();
    const workspace = document.querySelector('#adminWorkspace');
    workspace?.classList.add('is-user-detail');
    document.querySelector('.admin-page-shell')?.classList.add('is-user-detail');
    document.querySelector('#adminBackToUsers')?.focus({ preventScroll: true });
}

function renderAdminUserDetail() {
    const container = document.querySelector('#adminUserDetailContent');
    const user = adminState.users.find((candidate) => candidate.id === adminState.selectedId);
    if (!container || !user) return;
    const isSelf = user.id === adminState.currentUser?.id;
    container.className = '';
    container.innerHTML = `
        <div class="admin-detail-heading">
            <div class="admin-user-avatar is-large" aria-hidden="true">${escapeAdmin(user.displayName.slice(0, 1).toUpperCase())}</div>
            <div><p class="eyebrow">Detalle de usuario</p><h2>${escapeAdmin(user.displayName)}</h2><p class="muted">${escapeAdmin(user.email)}</p></div>
        </div>
        <dl class="admin-user-metadata">
            <div><dt>Fecha de alta</dt><dd>${formatAdminDate(user.createdAt)}</dd></div>
            <div><dt>Verificación</dt><dd><span class="status-badge ${user.emailVerified ? 'is-active' : 'is-pending'}">${user.emailVerified ? 'Email verificado' : 'Email pendiente'}</span></dd></div>
        </dl>
        <form id="adminUserForm" class="admin-user-form" novalidate>
            <div class="admin-user-fields">
                <label>Rol<select name="role" ${isSelf ? 'disabled' : ''}><option value="user" ${user.role === 'user' ? 'selected' : ''}>Usuario</option><option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrador</option></select></label>
                <label>Estado<select name="status" ${isSelf ? 'disabled' : ''}><option value="active" ${user.status === 'active' ? 'selected' : ''}>Activo</option><option value="disabled" ${user.status === 'disabled' ? 'selected' : ''}>Desactivado</option></select></label>
            </div>
            ${isSelf ? '<div class="admin-self-note" role="note"><strong>Esta es tu cuenta.</strong><span>Por seguridad no puedes cambiar aquí tu propio rol ni estado.</span></div>' : ''}
            <div id="adminDetailStatus" class="admin-inline-status" role="status" aria-live="polite"></div>
            <div class="admin-detail-actions">
                <span id="adminPendingChanges" class="admin-pending-changes" hidden>Cambios sin guardar</span>
                ${isSelf ? '' : '<button type="button" class="secondary" data-admin-reset disabled>Descartar</button><button type="submit" disabled>Guardar cambios</button>'}
            </div>
        </form>`;
    const form = container.querySelector('#adminUserForm');
    form?.addEventListener('change', () => markAdminDirty(user));
    form?.addEventListener('submit', (event) => saveAdminUser(event, user));
    form?.querySelector('[data-admin-reset]')?.addEventListener('click', () => {
        adminState.dirty = false;
        renderAdminUserDetail();
    });
}

function markAdminDirty(original) {
    const form = document.querySelector('#adminUserForm');
    if (!form) return;
    adminState.dirty = form.elements.role.value !== original.role || form.elements.status.value !== original.status;
    form.querySelector('[type="submit"]')?.toggleAttribute('disabled', !adminState.dirty);
    form.querySelector('[data-admin-reset]')?.toggleAttribute('disabled', !adminState.dirty);
    form.querySelector('#adminPendingChanges')?.toggleAttribute('hidden', !adminState.dirty);
}

async function saveAdminUser(event, original) {
    event.preventDefault();
    const form = event.currentTarget;
    const role = form.elements.role.value;
    const status = form.elements.status.value;
    const sensitive = (original.role === 'admin' && role === 'user') || (original.status === 'active' && status === 'disabled');
    if (sensitive) {
        const actions = [];
        if (original.role === 'admin' && role === 'user') actions.push('degradar su rol a Usuario');
        if (original.status === 'active' && status === 'disabled') actions.push('desactivar su acceso');
        const confirmed = await confirmAdminChange(`Vas a ${actions.join(' y ')} para ${original.displayName}. El cambio puede impedirle administrar o entrar en la aplicación.`);
        if (!confirmed) return;
    }
    const submit = form.querySelector('[type="submit"]');
    setAdminSubmitState(submit, true);
    setAdminDetailStatus('');
    try {
        const response = await fetch(`${adminApi}/admin/users/update`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminState.csrfToken },
            body: JSON.stringify({ userId: original.id, role, status })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(adminErrorMessage(data, 'No se pudo actualizar el usuario.'));
        adminState.users = adminState.users.map((user) => user.id === data.user.id ? data.user : user);
        adminState.dirty = false;
        renderAdminUserList();
        renderAdminUserDetail();
        setAdminDetailStatus('Cambios guardados.');
    } catch (error) {
        setAdminSubmitState(submit, false);
        setAdminDetailStatus(error.message, true);
    }
}

function closeAdminUserDetail() {
    document.querySelector('#adminWorkspace')?.classList.remove('is-user-detail');
    document.querySelector('.admin-page-shell')?.classList.remove('is-user-detail');
    const target = document.querySelector(`[data-admin-user-id="${adminState.selectedId}"]`) || adminState.listTrigger;
    target?.focus({ preventScroll: true });
}

function bindAdminConfirmation() {
    const dialog = document.querySelector('#adminConfirmDialog');
    bindDialogEscape(dialog);
    dialog?.querySelector('[data-admin-confirm-cancel]')?.addEventListener('click', () => dialog.close('cancel'));
    dialog?.querySelector('[data-admin-confirm-accept]')?.addEventListener('click', () => dialog.close('accept'));
}

function bindDialogEscape(dialog) {
    dialog?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !dialog.open) return;
        event.preventDefault();
        dialog.close('cancel');
    });
}

function confirmAdminChange(message) {
    const dialog = document.querySelector('#adminConfirmDialog');
    const text = document.querySelector('#adminConfirmText');
    if (!dialog || !text) return Promise.resolve(false);
    text.textContent = message;
    dialog.showModal();
    dialog.querySelector('[data-admin-confirm-cancel]')?.focus();
    return new Promise((resolve) => {
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'accept'), { once: true });
    });
}

function setAdminListStatus(message, error = false) {
    const status = document.querySelector('#adminUserListStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', error);
    status.hidden = message === '';
}

function setAdminDetailStatus(message, error = false) {
    const status = document.querySelector('#adminDetailStatus');
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', error);
    status.classList.toggle('is-success', Boolean(message) && !error);
}

function setAdminSubmitState(button, loading) {
    if (!button) return;
    if (loading) button.dataset.label = button.textContent;
    button.disabled = loading;
    button.setAttribute('aria-busy', String(loading));
    button.textContent = loading ? 'Guardando…' : (button.dataset.label || button.textContent);
}

function formatAdminDate(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 'No disponible' : new Intl.DateTimeFormat('es-ES', { dateStyle: 'long' }).format(date);
}

function normalizeAdminText(value) {
    return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function adminErrorMessage(data, fallback) {
    return typeof data?.error === 'string' ? data.error : data?.error?.message ?? fallback;
}

function escapeAdmin(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
