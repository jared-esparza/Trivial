document.addEventListener('DOMContentLoaded', () => {
    refreshSessionNav();
});

async function refreshSessionNav() {
    const nav = document.querySelector('[data-session-nav]');
    if (!nav) return;

    try {
        const response = await fetch('./api.php/auth/me');
        const data = await response.json();
        if (!response.ok) throw new Error(data.error?.message ?? 'No se pudo cargar la sesion.');
        nav.innerHTML = renderSessionNav(data.user);
    } catch {
        nav.innerHTML = renderSessionNav(null);
    }
}

function renderSessionNav(user) {
    const current = currentPageName();
    const links = [
        navLink('./', 'Juego', current === ''),
        navLink('account.php', user ? escapeSessionNav(user.displayName) : 'Login / registro', current === 'account.php'),
    ];

    if (user?.emailVerified) {
        links.push(navLink('packs.php', 'Packs', current === 'packs.php'));
        links.push(navLink('history.php', 'Historial', current === 'history.php'));
    }
    if (user?.role === 'admin') {
        links.push(navLink('admin.php', 'Admin', current === 'admin.php'));
    }

    return links.join('');
}

function navLink(href, label, active) {
    return `<a class="topbar-link${active ? ' active' : ''}" href="${href}">${label}</a>`;
}

function currentPageName() {
    const segment = location.pathname.split('/').pop() ?? '';
    return segment === 'index.php' ? '' : segment;
}

function escapeSessionNav(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
