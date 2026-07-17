const accountApi = './api.php';
const accessModes = ['login', 'register', 'forgot'];
const accountSections = ['profile', 'security'];
let currentCsrfToken = null;
let currentAccountUser = null;
let accountDirty = false;
let deleteDialogTrigger = null;

document.addEventListener('DOMContentLoaded', async () => {
    bindAccountTabs();
    bindAccountForms();
    bindAccountDialog();
    bindDirtyState();

    const linkHandled = await handleAccountLink();
    if (!linkHandled) {
        const requestedMode = new URLSearchParams(location.search).get('mode');
        showAccountMode(accessModes.includes(requestedMode) ? requestedMode : 'login', { updateUrl: false });
    }
    await refreshAccount();
});

window.addEventListener('beforeunload', (event) => {
    if (!accountDirty) return;
    event.preventDefault();
    event.returnValue = '';
});

function bindAccountTabs() {
    document.querySelector('#accountAccessTabs')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-account-mode]');
        if (button) showAccountMode(button.dataset.accountMode);
    });
    document.querySelector('#accountSectionTabs')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-account-section]');
        if (button) showAccountSection(button.dataset.accountSection);
    });
    document.querySelectorAll('[role="tablist"]').forEach((tablist) => {
        tablist.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const tabs = [...tablist.querySelectorAll('[role="tab"]:not([hidden])')];
            const current = tabs.indexOf(document.activeElement);
            if (current < 0) return;
            event.preventDefault();
            let next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : current + (event.key === 'ArrowRight' ? 1 : -1);
            next = (next + tabs.length) % tabs.length;
            tabs[next].focus();
            tabs[next].click();
        });
    });
}

function bindAccountForms() {
    bindJsonForm('#registerForm', '/auth/register', redirectAfterAccess);
    bindJsonForm('#loginForm', '/auth/login', redirectAfterAccess);
    bindJsonForm('#forgotForm', '/auth/password/forgot', async (form) => {
        form.reset();
        setAccountStatus('#accountGuestMessage', 'Si la cuenta existe, recibirás un enlace de recuperación.');
    });
    bindJsonForm('#resetForm', '/auth/password/reset', async (form) => {
        form.reset();
        showAccountMode('login');
        setAccountStatus('#accountGuestMessage', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    });

    document.querySelector('#profileForm')?.addEventListener('submit', saveProfile);
    document.querySelector('#passwordChangeForm')?.addEventListener('submit', changePassword);
    document.querySelector('#logoutButton')?.addEventListener('click', logoutAccount);
}

function bindJsonForm(selector, path, onSuccess) {
    document.querySelector(selector)?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const submit = form.querySelector('[type="submit"]');
        setSubmitState(submit, true);
        setAccountStatus('#accountGuestMessage', '');
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await request(path, payload);
            await onSuccess(form);
        } catch (error) {
            setAccountStatus('#accountGuestMessage', error.message, true);
        } finally {
            setSubmitState(submit, false);
        }
    });
}

function bindDirtyState() {
    document.querySelector('#profileForm input')?.addEventListener('input', () => {
        const value = document.querySelector('#profileForm input')?.value.trim() ?? '';
        const changed = Boolean(currentAccountUser && value !== currentAccountUser.displayName);
        document.querySelector('#profilePending')?.toggleAttribute('hidden', !changed);
        updateAccountDirty();
    });
    document.querySelectorAll('#passwordChangeForm input').forEach((input) => input.addEventListener('input', updateAccountDirty));
}

function updateAccountDirty() {
    const profileChanged = Boolean(currentAccountUser && (document.querySelector('#profileForm input')?.value.trim() ?? '') !== currentAccountUser.displayName);
    const passwordChanged = [...document.querySelectorAll('#passwordChangeForm input')].some((input) => input.value !== '');
    accountDirty = profileChanged || passwordChanged;
}

async function handleAccountLink() {
    const params = new URLSearchParams(location.search);
    const action = params.get('action');
    const token = params.get('token');
    if (action === 'reset' && token) {
        showAccountMode('reset', { updateUrl: false });
        const input = document.querySelector('#resetForm input[name="token"]');
        if (input) input.value = token;
        return true;
    }
    if (action === 'verify' && token) {
        showAccountMode('login', { updateUrl: false });
        setAccountStatus('#accountGuestMessage', 'Verificando el enlace…');
        try {
            await request('/auth/verify', { token });
            replaceAccountMode('login');
            setAccountStatus('#accountGuestMessage', 'Email verificado. Ya puedes usar todas las funciones de tu cuenta.');
        } catch (error) {
            setAccountStatus('#accountGuestMessage', error.message, true);
        }
        return true;
    }
    return false;
}

function showAccountMode(mode, options = {}) {
    const validMode = [...accessModes, 'reset'].includes(mode) ? mode : 'login';
    document.querySelectorAll('[data-account-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.accountPanel !== validMode;
    });
    document.querySelectorAll('[data-account-mode]').forEach((tab) => {
        const selected = tab.dataset.accountMode === validMode;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
    });
    document.querySelector('#accountAccessTabs')?.toggleAttribute('hidden', validMode === 'reset');
    if (options.updateUrl !== false && validMode !== 'reset') replaceAccountMode(validMode);
}

function replaceAccountMode(mode) {
    const params = new URLSearchParams(location.search);
    params.delete('action');
    params.delete('token');
    params.set('mode', mode);
    history.replaceState({ mode }, '', `account.php?${params.toString()}`);
}

function showAccountSection(section) {
    const validSection = accountSections.includes(section) ? section : 'profile';
    document.querySelectorAll('[data-account-section-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.accountSectionPanel !== validSection;
    });
    document.querySelectorAll('[data-account-section]').forEach((tab) => {
        const selected = tab.dataset.accountSection === validSection;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
    });
}

async function refreshAccount() {
    try {
        const response = await fetch(`${accountApi}/auth/me`);
        const data = await response.json();
        currentCsrfToken = data.csrfToken ?? null;
        currentAccountUser = data.user ?? null;
        const guest = document.querySelector('#accountGuestForms');
        const workspace = document.querySelector('#accountWorkspace');
        if (!data.user) {
            guest.hidden = false;
            workspace.hidden = true;
            return;
        }

        guest.hidden = true;
        workspace.hidden = false;
        renderAccountSummary(data.user);
        const displayName = document.querySelector('#profileForm input[name="displayName"]');
        if (displayName) displayName.value = data.user.displayName;
        showAccountSection('profile');
        accountDirty = false;
    } catch (error) {
        setAccountStatus('#accountGuestMessage', 'No se pudo consultar la sesión. Recarga la página para intentarlo de nuevo.', true);
    }
}

function renderAccountSummary(user) {
    const summary = document.querySelector('#accountSummary');
    if (!summary) return;
    const role = user.role === 'admin' ? 'Administrador' : 'Usuario';
    const verification = user.emailVerified ? 'Email verificado' : 'Email pendiente';
    summary.innerHTML = `
        <p class="eyebrow">Identidad</p>
        <div class="account-avatar" aria-hidden="true">${escapeAccount(user.displayName.slice(0, 1).toUpperCase())}</div>
        <h2>${escapeAccount(user.displayName)}</h2>
        <p class="muted account-email">${escapeAccount(user.email)}</p>
        <div class="account-badges"><span>${role}</span><span class="${user.emailVerified ? 'is-success' : 'is-warning'}">${verification}</span></div>
        ${user.emailVerified ? '' : '<div class="pending-verification-note" role="status"><strong>Verifica tu email</strong><span>Abre el enlace recibido para desbloquear Packs e Historial.</span></div>'}`;
}

async function saveProfile(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const submit = form.querySelector('[type="submit"]');
    setSubmitState(submit, true);
    setAccountStatus('#profileStatus', '');
    try {
        const displayName = new FormData(form).get('displayName').trim();
        await request('/auth/profile', { displayName }, currentCsrfToken);
        currentAccountUser.displayName = displayName;
        document.querySelector('#profilePending')?.setAttribute('hidden', '');
        renderAccountSummary(currentAccountUser);
        updateSharedNavDisplayName(displayName);
        updateAccountDirty();
        setAccountStatus('#profileStatus', 'Perfil guardado.');
    } catch (error) {
        setAccountStatus('#profileStatus', error.message, true);
    } finally {
        setSubmitState(submit, false);
    }
}

function updateSharedNavDisplayName(displayName) {
    document.querySelectorAll('.profile-name, .profile-menu-identity strong, .mobile-menu-identity strong')
        .forEach((element) => { element.textContent = displayName; });
    const initial = displayName.trim().slice(0, 1).toUpperCase() || 'U';
    document.querySelectorAll('.profile-avatar').forEach((avatar) => { avatar.textContent = initial; });
}

async function changePassword(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const values = Object.fromEntries(new FormData(form).entries());
    if (values.newPassword !== values.newPasswordConfirmation) {
        setAccountStatus('#passwordStatus', 'Las nuevas contraseñas no coinciden.', true);
        form.elements.newPasswordConfirmation.focus();
        return;
    }
    const submit = form.querySelector('[type="submit"]');
    setSubmitState(submit, true);
    setAccountStatus('#passwordStatus', '');
    try {
        await request('/auth/password/change', {
            currentPassword: values.currentPassword,
            newPassword: values.newPassword
        }, currentCsrfToken);
        form.reset();
        updateAccountDirty();
        setAccountStatus('#passwordStatus', 'Contraseña actualizada. Las demás sesiones se han cerrado.');
    } catch (error) {
        setAccountStatus('#passwordStatus', error.message, true);
    } finally {
        setSubmitState(submit, false);
    }
}

async function logoutAccount() {
    const button = document.querySelector('#logoutButton');
    setSubmitState(button, true);
    accountDirty = false;
    try {
        await request('/auth/logout', {}, currentCsrfToken);
        location.href = './';
    } catch (error) {
        setSubmitState(button, false);
        setAccountStatus('#passwordStatus', error.message, true);
    }
}

function bindAccountDialog() {
    const dialog = document.querySelector('#deleteAccountDialog');
    bindDialogEscape(dialog);
    document.querySelector('#openDeleteAccount')?.addEventListener('click', (event) => {
        deleteDialogTrigger = event.currentTarget;
        document.querySelector('#deleteAccountForm')?.reset();
        setAccountStatus('#deleteAccountStatus', '');
        dialog?.showModal();
        dialog?.querySelector('input[name="password"]')?.focus();
    });
    dialog?.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('close', () => deleteDialogTrigger?.focus());
    document.querySelector('#deleteAccountForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const payload = Object.fromEntries(new FormData(form).entries());
        if (payload.confirmation !== 'ELIMINAR') {
            setAccountStatus('#deleteAccountStatus', 'Escribe ELIMINAR exactamente para continuar.', true);
            form.elements.confirmation.focus();
            return;
        }
        const submit = form.querySelector('[type="submit"]');
        setSubmitState(submit, true);
        try {
            await request('/auth/delete', payload, currentCsrfToken);
            accountDirty = false;
            location.href = './';
        } catch (error) {
            setAccountStatus('#deleteAccountStatus', error.message, true);
            setSubmitState(submit, false);
        }
    });
}

function bindDialogEscape(dialog) {
    dialog?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !dialog.open) return;
        event.preventDefault();
        dialog.close('cancel');
    });
}

async function request(path, payload, csrfToken = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const response = await fetch(accountApi + path, {
        method: 'POST',
        headers,
        body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.error?.message ?? 'No se pudo completar la operación.');
    return data;
}

function redirectAfterAccess() {
    accountDirty = false;
    const target = document.querySelector('main[data-return-target]')?.dataset.returnTarget || './';
    location.href = target;
}

function setAccountStatus(selector, message, error = false) {
    const status = document.querySelector(selector);
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', error);
    status.classList.toggle('is-success', Boolean(message) && !error);
}

function setSubmitState(button, loading) {
    if (!button) return;
    if (loading) button.dataset.label = button.textContent;
    button.disabled = loading;
    button.setAttribute('aria-busy', String(loading));
    button.textContent = loading ? 'Procesando…' : (button.dataset.label || button.textContent);
}

function escapeAccount(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
