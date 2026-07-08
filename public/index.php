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
        <a class="brand" href="./"><?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></a>
        <a class="admin-link" href="admin.php">Admin preguntas</a>
    </header>

    <main class="shell">
        <section id="homeView" class="home-grid">
            <div class="panel intro-panel">
                <p class="eyebrow">Juego de preguntas por categorias</p>
                <h1>Tablero circular, quesitos y salas online.</h1>
                <p>Crea una partida local en una pantalla o abre una sala online para jugar con equipos remotos.</p>
            </div>

            <form id="localForm" class="panel form-panel">
                <h2>Partida local</h2>
                <label>
                    Modo de respuesta
                    <select name="answerMode">
                        <option value="judge">Clasico con juez</option>
                        <option value="auto">4 opciones</option>
                    </select>
                </label>
                <label>
                    Equipos, uno por linea
                    <textarea name="players" rows="6">Equipo Azul
Equipo Rojo</textarea>
                </label>
                <button type="submit">Crear partida local</button>
            </form>

            <form id="onlineCreateForm" class="panel form-panel">
                <h2>Crear sala online</h2>
                <label>
                    Nombre de tu equipo
                    <input name="teamName" value="Equipo Azul" required>
                </label>
                <button type="submit">Crear sala</button>
            </form>

            <form id="joinForm" class="panel form-panel">
                <h2>Unirse a sala</h2>
                <label>
                    Codigo de sala
                    <input name="code" maxlength="6" required>
                </label>
                <label>
                    Nombre de tu equipo
                    <input name="teamName" value="Equipo Rojo" required>
                </label>
                <button type="submit">Entrar</button>
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
                        <button id="copyRoomButton" type="button">Copiar codigo</button>
                        <button id="fullscreenBoardButton" class="icon-button" type="button" aria-label="Ver partida a pantalla completa" title="Pantalla completa">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M8 3H3v5h2V5h3V3Zm8 0v2h3v3h2V3h-5ZM5 16H3v5h5v-2H5v-3Zm14 3h-3v2h5v-5h-2v3Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="boardMount" class="board-mount"></div>
            </div>

            <aside class="side-panel">
                <div id="statusBox" class="status-box"></div>
                <div id="preferencesBox" class="preferences-box"></div>
                <div id="playersBox" class="players-box"></div>
                <div id="controlsBox" class="controls-box"></div>
            </aside>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/app.js"></script>
</body>
</html>
