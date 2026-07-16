document.addEventListener('DOMContentLoaded', bindSessionNavigation);

function bindSessionNavigation() {
    const profile = document.querySelector('[data-profile-menu]');
    const profileButton = profile?.querySelector('[data-profile-menu-button]');
    const profilePanel = profile?.querySelector('[data-profile-menu-panel]');
    const mobileButton = document.querySelector('[data-mobile-menu-button]');
    const mobileOverlay = document.querySelector('[data-mobile-menu-overlay]');
    const mobileDrawer = document.querySelector('[data-mobile-menu-drawer]');
    const mobileClose = document.querySelector('[data-mobile-menu-close]');

    const closeProfile = (restoreFocus = false) => {
        if (!profileButton || !profilePanel) return;
        profileButton.setAttribute('aria-expanded', 'false');
        profilePanel.classList.add('hidden');
        if (restoreFocus) profileButton.focus();
    };
    const openProfile = () => {
        if (!profileButton || !profilePanel) return;
        profileButton.setAttribute('aria-expanded', 'true');
        profilePanel.classList.remove('hidden');
    };
    const closeMobile = (restoreFocus = false) => {
        if (!mobileButton || !mobileOverlay || !mobileDrawer) return;
        mobileButton.setAttribute('aria-expanded', 'false');
        mobileButton.setAttribute('aria-label', 'Abrir menú');
        mobileDrawer.setAttribute('aria-hidden', 'true');
        mobileOverlay.classList.add('hidden');
        document.body.classList.remove('navigation-open');
        if (restoreFocus) mobileButton.focus();
    };
    const openMobile = () => {
        if (!mobileButton || !mobileOverlay || !mobileDrawer) return;
        closeProfile();
        mobileButton.setAttribute('aria-expanded', 'true');
        mobileButton.setAttribute('aria-label', 'Cerrar menú');
        mobileDrawer.setAttribute('aria-hidden', 'false');
        mobileOverlay.classList.remove('hidden');
        document.body.classList.add('navigation-open');
        mobileClose?.focus();
    };

    profileButton?.addEventListener('click', () => {
        profileButton.getAttribute('aria-expanded') === 'true' ? closeProfile() : openProfile();
    });
    mobileButton?.addEventListener('click', () => {
        mobileButton.getAttribute('aria-expanded') === 'true' ? closeMobile(true) : openMobile();
    });
    mobileClose?.addEventListener('click', () => closeMobile(true));
    mobileOverlay?.addEventListener('click', (event) => {
        if (event.target === mobileOverlay) closeMobile(true);
    });
    mobileDrawer?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => closeMobile()));

    document.addEventListener('click', (event) => {
        if (profile && !profile.contains(event.target)) closeProfile();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (mobileButton?.getAttribute('aria-expanded') === 'true') closeMobile(true);
            else if (profileButton?.getAttribute('aria-expanded') === 'true') closeProfile(true);
        }
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) closeMobile();
    });

    document.querySelectorAll('[data-session-logout]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const response = await fetch('./api.php/auth/logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': button.dataset.csrf ?? ''
                    },
                    body: '{}'
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error?.message ?? 'No se pudo cerrar la sesión.');
                location.href = './';
            } catch (error) {
                button.disabled = false;
                showNavigationMessage(error.message, true);
            }
        });
    });
}

function showNavigationMessage(message, error = false) {
    let toast = document.querySelector('#toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast';
        document.body.append(toast);
    }
    toast.textContent = message;
    toast.classList.toggle('toast-error', error);
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 4500);
}
