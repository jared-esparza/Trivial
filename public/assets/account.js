const accountApi = './api.php';
let currentCsrfToken = null;

document.addEventListener('DOMContentLoaded', async () => {
    bindAccountForms();
    await handleAccountLink();
    await refreshAccount();
});

function bindAccountForms() {
    bindJsonForm('#registerForm', '/auth/register', redirectAfterAccess);
    bindJsonForm('#loginForm', '/auth/login', redirectAfterAccess);
    bindJsonForm('#forgotForm', '/auth/password/forgot', () => {
        showAccountMessage('Si la cuenta existe, recibir&aacute;s un enlace de recuperaci&oacute;n.');
    });
    bindJsonForm('#resetForm', '/auth/password/reset', async () => {
        history.replaceState(null, '', 'account.php');
        document.querySelector('#resetSection')?.classList.add('hidden');
        showAccountMessage('Contrase&ntilde;a actualizada. Ya puedes iniciar sesi&oacute;n.');
    });
}

function bindJsonForm(selector, path, onSuccess) {
    document.querySelector(selector)?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(event.currentTarget).entries());
        try {
            await request(path, payload);
            await onSuccess();
        } catch (error) {
            showAccountMessage(error.message, true);
        }
    });
}

async function handleAccountLink() {
    const params = new URLSearchParams(location.search);
    const action = params.get('action');
    const token = params.get('token');
    if (action === 'verify' && token) {
        try {
            await request('/auth/verify', { token });
            history.replaceState(null, '', 'account.php');
            showAccountMessage('Email verificado. Ya puedes usar las funciones de tu cuenta.');
        } catch (error) {
            showAccountMessage(error.message, true);
        }
    }
    if (action === 'reset' && token) {
        const section = document.querySelector('#resetSection');
        section?.classList.remove('hidden');
        const input = section?.querySelector('input[name="token"]');
        if (input) input.value = token;
    }
}

async function refreshAccount() {
    const response = await fetch(`${accountApi}/auth/me`);
    const data = await response.json();
    currentCsrfToken = data.csrfToken ?? null;
    const status = document.querySelector('#accountStatus');
    const guestForms = document.querySelector('#accountGuestForms');
    if (!data.user) {
        if (status) status.innerHTML = '<p class="eyebrow">Acceso</p><h1 data-auth-title>Login o registro</h1><p class="muted">Juega como invitado o inicia sesi&oacute;n para usar packs e historial.</p>';
        guestForms?.classList.remove('hidden');
        await refreshSharedNav();
        return;
    }

    guestForms?.classList.add('hidden');
    if (status) {
        status.innerHTML = `
            <p class="eyebrow">Sesi&oacute;n iniciada</p>
            <h1 data-auth-title>${escapeAccount(data.user.displayName)}</h1>
            <p class="muted">${escapeAccount(data.user.email)}</p>
            <div class="account-badges">
                <span>${data.user.emailVerified ? 'Email verificado' : 'Email pendiente de verificaci&oacute;n'}</span>
                <span>${escapeAccount(data.user.role)}</span>
            </div>
            ${data.user.emailVerified ? '' : `<div class="pending-verification-note" role="status">
                <strong>Verifica tu email para desbloquear Packs e Historial.</strong>
                <span>Revisa el mensaje que te enviamos y abre el enlace de verificaci&oacute;n.</span>
            </div>`}
            <form id="profileForm" class="inline-form account-profile-form">
                <label>Nombre visible<input name="displayName" value="${escapeAccount(data.user.displayName)}" minlength="2" maxlength="40" required></label>
                <button type="submit">Guardar nombre</button>
            </form>
            <button id="logoutButton" class="secondary" type="button">Cerrar sesi&oacute;n</button>
            <hr>
            <details class="danger-zone">
                <summary>Eliminar mi cuenta</summary>
                <form id="deleteAccountForm">
                    <p class="muted">Se anonimizar&aacute; tu historial compartido y se retirar&aacute;n tus packs privados.</p>
                    <label>Contrase&ntilde;a actual<input name="password" type="password" autocomplete="current-password" required></label>
                    <button class="danger-button" type="submit">Eliminar definitivamente</button>
                </form>
            </details>`;
        status.querySelector('#logoutButton')?.addEventListener('click', async () => {
            await request('/auth/logout', {}, currentCsrfToken);
            location.href = './';
        });
        status.querySelector('#deleteAccountForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm('Esta accion no se puede deshacer. ¿Eliminar tu cuenta?')) return;
            const password = new FormData(event.currentTarget).get('password');
            try {
                await request('/auth/delete', { password }, currentCsrfToken);
                location.href = './';
            } catch (error) {
                showAccountMessage(error.message, true);
            }
        });
        status.querySelector('#profileForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const displayName = new FormData(event.currentTarget).get('displayName');
            try {
                await request('/auth/profile', { displayName }, currentCsrfToken);
                showAccountMessage('Nombre visible actualizado.');
                setTimeout(() => location.reload(), 350);
            } catch (error) {
                showAccountMessage(error.message, true);
            }
        });
    }
}

async function request(path, payload, csrfToken = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const response = await fetch(accountApi + path, {
        method: 'POST',
        headers,
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error?.message ?? 'No se pudo completar la operaci&oacute;n.');
    return data;
}

function redirectAfterAccess() {
    const target = document.querySelector('main[data-return-target]')?.dataset.returnTarget || './';
    location.href = target;
}

function showAccountMessage(message, error = false) {
    const toast = document.querySelector('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('toast-error', error);
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 4500);
}

function escapeAccount(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
