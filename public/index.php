<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="./" aria-label="<?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            <span><?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <nav class="topbar-nav" data-session-nav aria-label="Navegaci&oacute;n principal">
            <a class="topbar-link" href="account.php">Login / registro</a>
        </nav>
    </header>

    <main class="shell">
        <section id="homeView" class="home-view">
            <div class="home-hero">
                <div class="home-copy">
                    <h1>Pon a prueba tus <span>conocimientos.</span></h1>
                    <p>Juega en local con tus amigos o crea una sala online para desafiar a equipos de todo el mundo.</p>
                    <div class="category-flags" aria-hidden="true">
                        <span class="flag-blue"></span>
                        <span class="flag-green"></span>
                        <span class="flag-yellow"></span>
                        <span class="flag-orange"></span>
                        <span class="flag-red"></span>
                        <span class="flag-purple"></span>
                    </div>
                </div>
                <div class="home-board-art" aria-hidden="true"></div>
            </div>

            <div class="home-actions">
                <article class="action-card action-card-local">
                    <div class="action-icon action-icon-blue" aria-hidden="true">
                        <span>&#8982;</span>
                    </div>
                    <div>
                        <h2>Partida local</h2>
                        <p>Reune a tus amigos y juega en la misma pantalla.</p>
                    </div>
                    <button id="openLocalSetupButton" class="wide-button blue-button" type="button">
                        Configurar partida local
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                </article>

                <article class="action-card action-card-online">
                    <div class="action-icon action-icon-purple" aria-hidden="true">
                        <span>&#9678;</span>
                    </div>
                    <div>
                        <h2>Crear sala online</h2>
                        <p>Crea tu sala, invita a otros equipos y empieza a jugar.</p>
                    </div>
                    <button id="openOnlineSetupButton" class="wide-button purple-button" type="button">
                        Crear sala
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                </article>

                <form id="joinForm" class="join-card">
                    <div class="join-heading">
                        <div class="action-icon action-icon-green" aria-hidden="true">
                            <span>&#9679;&#9679;</span>
                        </div>
                        <div>
                            <h2>Unirse a sala</h2>
                            <p>Ingresa el c&oacute;digo de la sala para unirte a la partida.</p>
                        </div>
                    </div>
                    <label>
                        C&oacute;digo de sala
                        <input name="code" maxlength="6" placeholder="Ej: 57639L" required>
                    </label>
                    <label>
                        Nombre de tu equipo
                        <input name="teamName" value="Equipo Rojo" required>
                    </label>
                    <button class="wide-button green-button" type="submit">
                        Entrar
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                </form>
            </div>
        </section>

        <section id="onlineSetupView" class="online-setup-view hidden">
            <div class="online-board-art" aria-hidden="true"></div>
            <form id="onlineCreateForm" class="local-setup-card online-setup-card">
                <p class="eyebrow"><span aria-hidden="true">&#9678;</span> Sala online</p>
                <h1>Crea tu sala online</h1>
                <p>Elige tu equipo y el pack de preguntas. Despu&eacute;s podr&aacute;s compartir el c&oacute;digo de sala con los dem&aacute;s jugadores.</p>
                <label>
                    Nombre de tu equipo
                    <input name="teamName" value="Equipo Azul" required>
                </label>
                <label>
                    Pack de preguntas
                    <select name="packId" data-pack-select><option value="">Cl&aacute;sico</option></select>
                </label>
                <details class="color-options">
                    <summary>Personalizar colores de categor&iacute;as</summary>
                    <label>Esquema de colores
                        <select name="colorSchemeId" data-color-scheme-select><option value="">Usar colores predeterminados del pack</option></select>
                    </label>
                    <div class="color-scheme-preview" data-color-scheme-preview aria-label="Vista previa de colores"></div>
                </details>
                <button class="wide-button purple-button" type="submit">Crear sala</button>
                <button id="backHomeFromOnlineButton" class="wide-button ghost-button" type="button">Volver</button>
            </form>
        </section>

        <section id="localSetupView" class="local-setup-view hidden">
            <div class="local-board-art" aria-hidden="true"></div>
            <form id="localForm" class="local-setup-card">
                <p class="eyebrow"><span aria-hidden="true">&#9733;</span> Partida local</p>
                <h1>Configura tu partida local</h1>
                <p>Elige el modo de respuesta y a&ntilde;ade entre 2 y 6 equipos para empezar a jugar en la misma pantalla.</p>
                <label>
                    Modo de respuesta
                    <select name="answerMode">
                        <option value="judge">Clasico con juez</option>
                        <option value="auto">4 opciones</option>
                    </select>
                </label>
                <label>
                    Pack de preguntas
                    <select name="packId" data-pack-select><option value="">Cl&aacute;sico</option></select>
                </label>
                <details class="color-options">
                    <summary>Personalizar colores de categor&iacute;as</summary>
                    <label>Esquema de colores
                        <select name="colorSchemeId" data-color-scheme-select><option value="">Usar colores predeterminados del pack</option></select>
                    </label>
                    <div class="color-scheme-preview" data-color-scheme-preview aria-label="Vista previa de colores"></div>
                </details>
                <label>
                    Equipos <span class="label-note">(minimo 2 &middot; maximo 6)</span>
                    <textarea name="players" rows="6">Equipo Azul
Equipo Rojo</textarea>
                </label>
                <div class="local-form-hint">
                    <span>Escribe un equipo por linea.</span>
                    <strong id="localSetupTeamCount">2/6 equipos</strong>
                </div>
                <button class="wide-button blue-button" type="submit">Crear partida local</button>
                <button id="backHomeButton" class="wide-button ghost-button" type="button">Volver</button>
            </form>
        </section>

        <section id="gameView" class="game-view hidden">
            <div class="game-board-panel">
                <div class="room-strip">
                    <div>
                        <span class="label">Sala</span>
                        <strong id="roomCode">------</strong>
                    </div>
                    <div class="room-actions">
                        <div id="topDiceStatus" class="top-dice-status" aria-live="polite"></div>
                        <button id="copyRoomButton" type="button">Copiar codigo</button>
                        <button id="preferencesButton" class="icon-button" type="button" aria-label="Abrir preferencias" title="Preferencias">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M19.4 13.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5-2-3.5-2.4 1a8 8 0 0 0-2.6-1.5L14 2h-4l-.4 2.5A8 8 0 0 0 7 6L4.6 5 2.6 8.5l2 1.5a9 9 0 0 0 0 3l-2 1.5 2 3.5L7 17a8 8 0 0 0 2.6 1.5L10 21h4l.4-2.5A8 8 0 0 0 17 17l2.4 1 2-3.5-2-1.5ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"></path>
                            </svg>
                        </button>
                        <button id="fullscreenBoardButton" class="icon-button" type="button" aria-label="Ver partida a pantalla completa" title="Pantalla completa">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 3H3v5h2V5h3V3Zm8 0v2h3v3h2V3h-5ZM5 16H3v5h5v-2H5v-3Zm14 3h-3v2h5v-5h-2v3Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="scoreboardBox" class="scoreboard-box" aria-label="Marcador de equipos"></div>
                <div id="boardMount" class="board-mount"></div>
            </div>
            <div id="preferencesOverlay" class="preferences-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="preferencesOverlayTitle"></div>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/session-nav.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
