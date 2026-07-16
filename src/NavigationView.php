<?php

declare(strict_types=1);

final class NavigationView
{
    public static function currentUser(SessionRepository $sessions, array $cookies): ?array
    {
        $token = (string) ($cookies['rq_session'] ?? '');

        return $token === '' ? null : $sessions->findUserByToken($token);
    }

    public static function safeReturnTarget(?string $target): string
    {
        $target = trim((string) $target);
        if ($target === '' || str_contains($target, '\\') || str_contains($target, '..')) {
            return './';
        }
        if (preg_match('~^(?:https?:)?//~i', $target) === 1) {
            return './';
        }
        if (in_array($target, ['./', 'index.php', 'packs.php', 'history.php', 'admin.php'], true)) {
            return $target === 'index.php' ? './' : $target;
        }
        if (preg_match('/^\.\/\?room=[A-Z0-9]{6}$/', $target) === 1) {
            return $target;
        }

        return './';
    }

    public static function renderHeader(string $appName, ?array $user, string $activePage, ?string $returnTarget = './'): string
    {
        $returnTarget = self::safeReturnTarget($returnTarget);
        $verified = $user !== null && ($user['email_verified_at'] ?? null) !== null;
        $isAdmin = ($user['role'] ?? null) === 'admin';
        $primaryLinks = [self::navLink('./', 'Jugar', $activePage === 'game')];
        if ($verified) {
            $primaryLinks[] = self::navLink('packs.php', 'Packs', $activePage === 'packs');
            $primaryLinks[] = self::navLink('history.php', 'Historial', $activePage === 'history');
        }
        $primary = implode('', $primaryLinks);
        $brand = self::escape($appName);
        $accessHref = 'account.php?return=' . rawurlencode($returnTarget);

        if ($user === null) {
            $desktopIdentity = self::navLink($accessHref, 'Entrar', $activePage === 'account', ' topbar-cta');
            $mobileIdentity = '<div class="mobile-menu-account">'
                . self::navLink($accessHref, 'Entrar o registrarse', $activePage === 'account', ' mobile-menu-cta')
                . '</div>';
        } else {
            $displayName = self::escape((string) ($user['display_name'] ?? 'Usuario'));
            $initial = self::escape(self::initial((string) ($user['display_name'] ?? 'U')));
            $status = $verified ? 'Email verificado' : 'Email pendiente';
            $csrf = self::escape((string) ($user['csrf_token'] ?? ''));
            $adminLink = $isAdmin
                ? self::menuLink('admin.php', 'Administración', $activePage === 'admin')
                : '';
            $accountLink = self::menuLink('account.php', 'Mi cuenta', $activePage === 'account');
            $desktopIdentity = <<<HTML
                <div class="profile-menu" data-profile-menu>
                    <button class="profile-menu-trigger" type="button" aria-expanded="false" aria-controls="profileMenuPanel" data-profile-menu-button>
                        <span class="profile-avatar" aria-hidden="true">{$initial}</span>
                        <span class="profile-name">{$displayName}</span>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7 5 5 5-5"></path></svg>
                    </button>
                    <div id="profileMenuPanel" class="profile-menu-panel hidden" data-profile-menu-panel>
                        <div class="profile-menu-identity"><strong>{$displayName}</strong><span>{$status}</span></div>
                        <div class="profile-menu-links">
                            {$adminLink}
                            {$accountLink}
                        </div>
                        <button class="profile-menu-logout" type="button" data-session-logout data-csrf="{$csrf}">Cerrar sesi&oacute;n</button>
                    </div>
                </div>
                HTML;
            $mobileIdentity = <<<HTML
                <div class="mobile-menu-account">
                    <div class="mobile-menu-identity"><span class="profile-avatar" aria-hidden="true">{$initial}</span><span><strong>{$displayName}</strong><small>{$status}</small></span></div>
                    {$adminLink}
                    {$accountLink}
                    <button class="mobile-menu-logout" type="button" data-session-logout data-csrf="{$csrf}">Cerrar sesi&oacute;n</button>
                </div>
                HTML;
        }

        return <<<HTML
            <header class="topbar" data-site-header>
                <a class="brand" href="./" aria-label="{$brand}">
                    <span class="brand-mark" aria-hidden="true"></span><span>{$brand}</span>
                </a>
                <nav class="topbar-nav topbar-nav-desktop" aria-label="Navegaci&oacute;n principal">{$primary}{$desktopIdentity}</nav>
                <button class="mobile-menu-button" type="button" aria-expanded="false" aria-controls="mobileMenuDrawer" aria-label="Abrir men&uacute;" data-mobile-menu-button>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg><span>Men&uacute;</span>
                </button>
            </header>
            <div class="mobile-menu-overlay hidden" data-mobile-menu-overlay>
                <aside id="mobileMenuDrawer" class="mobile-menu-drawer" aria-label="Men&uacute; principal" aria-hidden="true" data-mobile-menu-drawer>
                    <div class="mobile-menu-head"><strong>Navegaci&oacute;n</strong><button type="button" aria-label="Cerrar men&uacute;" data-mobile-menu-close>&times;</button></div>
                    <nav class="mobile-menu-links" aria-label="Destinos">{$primary}</nav>
                    {$mobileIdentity}
                </aside>
            </div>
            HTML;
    }

    public static function renderBreadcrumbs(array $items, ?string $id = null): string
    {
        $idAttribute = $id === null ? '' : ' id="' . self::escape($id) . '"';
        $parts = [];
        foreach ($items as $index => $item) {
            $href = $item[0] ?? null;
            $label = self::escape((string) ($item[1] ?? ''));
            $current = $index === array_key_last($items) || $href === null;
            $parts[] = $current
                ? '<li><span aria-current="page">' . $label . '</span></li>'
                : '<li><a href="' . self::escape((string) $href) . '">' . $label . '</a></li>';
        }

        return '<nav' . $idAttribute . ' class="breadcrumbs" aria-label="Migas de pan"><ol>'
            . implode('', $parts)
            . '</ol></nav>';
    }

    private static function navLink(string $href, string $label, bool $active, string $extraClass = ''): string
    {
        $current = $active ? ' aria-current="page"' : '';
        $activeClass = $active ? ' active' : '';

        return '<a class="topbar-link' . $activeClass . $extraClass . '" href="' . self::escape($href) . '"' . $current . '>'
            . self::escape($label) . '</a>';
    }

    private static function menuLink(string $href, string $label, bool $active): string
    {
        $current = $active ? ' aria-current="page"' : '';

        return '<a href="' . self::escape($href) . '" class="profile-menu-link"' . $current . '>' . self::escape($label) . '</a>';
    }

    private static function initial(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'U';
        }
        preg_match('/^./us', $name, $match);

        return strtoupper($match[0] ?? 'U');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
