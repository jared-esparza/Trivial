const accountApi = './api.php';
let currentCsrfToken = null;

document.addEventListener('DOMContentLoaded', async () => {
    bindAccountForms();
    await handleAccountLink();
    await refreshAccount();
});

function bindAccountForms() {
    bindJsonForm('#registerForm', '/auth/register', () => {
        showAccountMessage('Cuenta creada. Revisa tu correo para verificarla.');
    });
    bindJsonForm('#loginForm', '/auth/login', async () => {
        await refreshAccount();
    });
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
        if (status) status.innerHTML = '<h1>Mi cuenta</h1><p class="muted">Juega como invitado o inicia sesi&oacute;n para usar packs e historial.</p>';
        guestForms?.classList.remove('hidden');
        return;
    }

    guestForms?.classList.add('hidden');
    if (status) {
        status.innerHTML = `
            <p class="eyebrow">Sesi&oacute;n iniciada</p>
            <h1>${escapeAccount(data.user.email)}</h1>
            <p>${data.user.emailVerified ? 'Email verificado' : 'Email pendiente de verificaci&oacute;n'} &middot; ${escapeAccount(data.user.role)}</p>
            ${data.user.emailVerified ? '<p><a href="packs.php">Gestionar mis packs</a></p>' : ''}
            ${data.user.emailVerified ? '<p><a href="history.php">Ver historial de partidas</a></p>' : ''}
            ${data.user.role === 'admin' ? '<p><a href="admin.php">Abrir administraci&oacute;n</a></p>' : ''}
            <button id="logoutButton" type="button">Cerrar sesi&oacute;n</button>
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
            currentCsrfToken = null;
            await refreshAccount();
        });
        status.querySelector('#deleteAccountForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!window.confirm('Esta accion no se puede deshacer. ¿Eliminar tu cuenta?')) return;
            const password = new FormData(event.currentTarget).get('password');
            try {
                await request('/auth/delete', { password }, currentCsrfToken);
                currentCsrfToken = null;
                showAccountMessage('Cuenta eliminada y datos personales anonimizados.');
                await refreshAccount();
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
