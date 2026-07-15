const apiBase = 'api.php';
const playerColors = ['#2563eb', '#dc2626', '#16a34a', '#ca8a04', '#9333ea', '#0891b2'];
const whiteBordersPreferenceKey = 'board:whiteBorders';
const pulseDestinationsPreferenceKey = 'board:pulseDestinations';
const animateTokensPreferenceKey = 'board:animateTokens';
const diceResultDelayPreferenceKey = 'board:diceResultDelayMs';
const minimumDiceRollAnimationMs = 520;

let currentRoom = null;
let playerId = null;
let participantToken = null;
let currentStatistics = null;
let statisticsLoading = false;
let pollingTimer = null;
let revealedJudgeQuestionKey = null;
let lastAnimatedDiceKey = null;
let pendingAnswerFeedback = null;
let pendingTokenAnimation = null;
let pendingDiceRollFeedback = null;
let isRollSubmitting = false;
let preferencesOverlayOpen = false;
let diceRollFeedbackTimer = null;
let availablePacks = [];
let availableColorSchemes = [];

const categoryLabels = {
    geography: 'Geografia',
    art: 'Arte y literatura',
    history: 'Historia',
    entertainment: 'Entretenimiento',
    science: 'Ciencia y naturaleza',
    sports: 'Deportes y ocio'
};

document.addEventListener('DOMContentLoaded', () => {
    bindHomeNavigation();
    bindGameForms();
    loadAvailablePacks();
    bindAdminForms();
    bindFullscreenControls();
    bindPreferencesOverlayControls();
    const params = new URLSearchParams(window.location.search);
    const code = params.get('room');
    if (code) {
        playerId = Number(localStorage.getItem(`room:${code}:playerId`) ?? 0);
        participantToken = localStorage.getItem(`room:${code}:participantToken`);
        loadRoom(code.toUpperCase());
    }
});

function bindHomeNavigation() {
    const homeView = document.querySelector('#homeView');
    const localSetupView = document.querySelector('#localSetupView');
    const openLocalSetupButton = document.querySelector('#openLocalSetupButton');
    const backHomeButton = document.querySelector('#backHomeButton');
    const localPlayersInput = document.querySelector('#localForm textarea[name="players"]');

    openLocalSetupButton?.addEventListener('click', () => {
        homeView?.classList.add('hidden');
        localSetupView?.classList.remove('hidden');
        updateLocalSetupTeamCount();
        localPlayersInput?.focus();
    });

    backHomeButton?.addEventListener('click', () => {
        localSetupView?.classList.add('hidden');
        homeView?.classList.remove('hidden');
    });

    localPlayersInput?.addEventListener('input', updateLocalSetupTeamCount);
    updateLocalSetupTeamCount();
}

function localSetupTeamNames() {
    const localPlayersInput = document.querySelector('#localForm textarea[name="players"]');
    return String(localPlayersInput?.value ?? '')
        .split(/\r?\n/)
        .map((name) => name.trim())
        .filter(Boolean);
}

function updateLocalSetupTeamCount() {
    const names = localSetupTeamNames();
    const count = names.length;
    const counter = document.querySelector('#localSetupTeamCount');
    const localForm = document.querySelector('#localForm');
    const submitButton = localForm?.querySelector('button[type="submit"]');
    const isValid = count >= 2 && count <= 6;

    if (counter) {
        counter.textContent = `${count}/6 equipos`;
        counter.classList.toggle('invalid', !isValid);
    }
    localForm?.classList.toggle('local-form-invalid', !isValid);
    submitButton?.toggleAttribute('disabled', !isValid);

    return { count, isValid };
}

function bindGameForms() {
    const localForm = document.querySelector('#localForm');
    const onlineCreateForm = document.querySelector('#onlineCreateForm');
    const joinForm = document.querySelector('#joinForm');
    const copyButton = document.querySelector('#copyRoomButton');

    document.querySelectorAll('[data-pack-select]').forEach((select) => {
        select.addEventListener('change', () => resetRoomColorScheme(select.closest('form')));
    });
    document.querySelectorAll('[data-color-scheme-select]').forEach((select) => {
        select.addEventListener('change', () => renderRoomColorPreview(select.closest('form')));
    });

    if (localForm) {
        localForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(localForm);
            const localSetupStatus = updateLocalSetupTeamCount();
            if (!localSetupStatus.isValid) {
                toast('La partida local necesita entre 2 y 6 equipos.');
                return;
            }
            const players = localSetupTeamNames()
                .map((name, index) => ({ name, color: playerColors[index] }));
            const response = await apiFetch('/rooms', {
                mode: 'local',
                answerMode: data.get('answerMode'),
                players,
                packId: data.get('packId'),
                colorSchemeId: data.get('colorSchemeId')
            });
            playerId = 0;
            persistParticipantToken(response);
            setRoom(response.room);
        });
    }

    if (onlineCreateForm) {
        onlineCreateForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(onlineCreateForm);
            const response = await apiFetch('/rooms', {
                mode: 'online',
                teamName: data.get('teamName'),
                color: playerColors[0],
                packId: data.get('packId'),
                colorSchemeId: data.get('colorSchemeId')
            });
            playerId = 0;
            localStorage.setItem(`room:${response.room.code}:playerId`, String(playerId));
            persistParticipantToken(response);
            setRoom(response.room);
        });
    }

    if (joinForm) {
        joinForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(joinForm);
            const code = String(data.get('code')).trim().toUpperCase();
            const existing = await getRoom(code);
            const response = await apiFetch(`/rooms/${code}/join`, {
                teamName: data.get('teamName'),
                color: playerColors[existing.room.state.players.length % playerColors.length]
            });
            playerId = response.room.state.players.length - 1;
            localStorage.setItem(`room:${response.room.code}:playerId`, String(playerId));
            persistParticipantToken(response);
            setRoom(response.room);
        });
    }

    if (copyButton) {
        copyButton.addEventListener('click', async () => {
            if (!currentRoom) return;
            try {
                await navigator.clipboard.writeText(currentRoom.code);
                toast('Codigo copiado.');
            } catch {
                toast(`Codigo: ${currentRoom.code}`);
            }
        });
    }
}

function bindFullscreenControls() {
    const button = document.querySelector('#fullscreenBoardButton');
    const gameView = document.querySelector('#gameView');
    if (!button || !gameView) return;

    button.addEventListener('click', async () => {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
            gameView.classList.remove('fullscreen-fallback');
            updateFullscreenButton();
            return;
        }

        if (gameView.classList.contains('fullscreen-fallback')) {
            gameView.classList.remove('fullscreen-fallback');
            updateFullscreenButton();
            return;
        }

        try {
            if (gameView.requestFullscreen) {
                await gameView.requestFullscreen();
            } else {
                gameView.classList.add('fullscreen-fallback');
            }
        } catch {
            gameView.classList.add('fullscreen-fallback');
        }
        updateFullscreenButton();
    });

    document.addEventListener('fullscreenchange', updateFullscreenButton);
}

function bindPreferencesOverlayControls() {
    document.querySelector('#preferencesButton')?.addEventListener('click', () => {
        preferencesOverlayOpen = true;
        renderPreferencesOverlay();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !preferencesOverlayOpen) return;
        preferencesOverlayOpen = false;
        renderPreferencesOverlay();
    });
}

function updateFullscreenButton() {
    const button = document.querySelector('#fullscreenBoardButton');
    const gameView = document.querySelector('#gameView');
    if (!button || !gameView) return;
    const isFullscreen = document.fullscreenElement === gameView || gameView.classList.contains('fullscreen-fallback');

    button.classList.toggle('active', isFullscreen);
    button.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
    button.setAttribute('aria-label', isFullscreen ? 'Salir de pantalla completa' : 'Ver partida a pantalla completa');
    button.setAttribute('title', isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa');
}

function bindAdminForms() {
    const users = document.querySelector('#adminUsers');
    if (users) {
        fetch(`${apiBase}/auth/me`)
            .then((response) => response.json())
            .then((data) => {
                if (data.user?.role === 'admin') return loadAdminUsers(data.csrfToken ?? null);
                return null;
            })
            .catch(() => toast('No se pudo comprobar la sesion.'));
    }
}

async function apiFetch(path, payload, extraHeaders = {}) {
    const response = await fetch(apiBase + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...extraHeaders },
        body: JSON.stringify(payload)
    });
    const json = await response.json();
    if (!response.ok) {
        const message = apiErrorMessage(json, 'Error de API.');
        toast(message);
        throw new Error(message);
    }
    return json;
}

async function loadAvailablePacks() {
    const selects = [...document.querySelectorAll('[data-pack-select]')];
    if (!selects.length) return;
    try {
        const [response, colorResponse] = await Promise.all([
            fetch(`${apiBase}/packs`),
            fetch(`${apiBase}/packs/colors`)
        ]);
        const data = await response.json();
        const colorData = await colorResponse.json();
        if (!response.ok) throw new Error(apiErrorMessage(data, 'No se pudieron cargar los packs.'));
        if (!colorResponse.ok) throw new Error(apiErrorMessage(colorData, 'No se pudieron cargar los colores.'));
        availablePacks = data.packs.filter((pack) => pack.status === 'active');
        availableColorSchemes = colorData.colorSchemes;
        const options = availablePacks
            .map((pack) => `<option value="${Number(pack.id)}">${escapeHtml(pack.name)}</option>`)
            .join('');
        selects.forEach((select) => { select.innerHTML = options; });
        const systemSchemes = availableColorSchemes.filter((scheme) => scheme.kind === 'system');
        const personalSchemes = availableColorSchemes.filter((scheme) => scheme.kind === 'user');
        const colorOptions = '<option value="">Usar colores predeterminados del pack</option>'
            + (systemSchemes.length ? `<optgroup label="Sistema">${systemSchemes.map(colorSchemeOption).join('')}</optgroup>` : '')
            + (personalSchemes.length ? `<optgroup label="Mis esquemas">${personalSchemes.map(colorSchemeOption).join('')}</optgroup>` : '');
        document.querySelectorAll('[data-color-scheme-select]').forEach((select) => { select.innerHTML = colorOptions; });
        document.querySelectorAll('[data-color-scheme-preview]').forEach((preview) => renderRoomColorPreview(preview.closest('form')));
    } catch (error) {
        toast(error.message);
    }
}

function colorSchemeOption(scheme) {
    return `<option value="${Number(scheme.id)}">${escapeHtml(scheme.name)}</option>`;
}

function resetRoomColorScheme(form) {
    if (!form) return;
    const select = form.querySelector('[data-color-scheme-select]');
    if (select) select.value = '';
    renderRoomColorPreview(form);
}

function renderRoomColorPreview(form) {
    if (!form) return;
    const preview = form.querySelector('[data-color-scheme-preview]');
    if (!preview) return;
    const schemeId = Number(form.querySelector('[data-color-scheme-select]')?.value) || null;
    const scheme = availableColorSchemes.find((item) => item.id === schemeId);
    const packId = Number(form.querySelector('[data-pack-select]')?.value) || null;
    const pack = availablePacks.find((item) => item.id === packId) ?? availablePacks[0];
    const revision = pack?.currentRevision ?? pack?.draftRevision;
    const colors = scheme?.colors ?? (revision?.categories ?? []).map((category) => category.color);
    preview.innerHTML = colors.map((color) => `<i class="color-swatch" title="${escapeAttr(color)}" style="background:${escapeAttr(color)}"></i>`).join('');
}

async function loadAdminUsers(csrfToken) {
    const response = await fetch(`${apiBase}/admin/users`);
    const json = await response.json();
    if (!response.ok) throw new Error(apiErrorMessage(json, 'No se pudieron cargar los usuarios.'));
    renderAdminUsers(json.users, csrfToken);
}

function renderAdminUsers(users, csrfToken) {
    const box = document.querySelector('#adminUsers');
    if (!box) return;
    box.innerHTML = users.map((user) => `
        <article class="question-item admin-user-row" data-user-id="${Number(user.id)}">
            <div class="admin-user-identity">
                <strong>${escapeHtml(user.displayName ?? user.email)}</strong>
                <p class="admin-user-email">${escapeHtml(user.email)}</p>
                <p class="muted">${user.emailVerified ? 'Verificado' : 'Pendiente de verificar'}</p>
            </div>
            <label>Rol
                <select data-user-field="role">
                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>Usuario</option>
                    <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                </select>
            </label>
            <label>Estado
                <select data-user-field="status">
                    <option value="active" ${user.status === 'active' ? 'selected' : ''}>Activo</option>
                    <option value="disabled" ${user.status === 'disabled' ? 'selected' : ''}>Desactivado</option>
                </select>
            </label>
        </article>
    `).join('');

    box.querySelectorAll('[data-user-field]').forEach((select) => {
        select.addEventListener('change', async () => {
            const row = select.closest('[data-user-id]');
            const payload = {
                userId: Number(row.dataset.userId),
                [select.dataset.userField]: select.value
            };
            try {
                await apiFetch('/admin/users/update', payload, { 'X-CSRF-Token': csrfToken });
                toast('Usuario actualizado.');
                await loadAdminUsers(csrfToken);
            } catch (error) {
                toast(error.message);
                await loadAdminUsers(csrfToken);
            }
        });
    });
}

function apiErrorMessage(json, fallback) {
    return typeof json?.error === 'string' ? json.error : json?.error?.message ?? fallback;
}

function persistParticipantToken(response) {
    if (!response.participantToken || !response.room?.code) return;
    participantToken = response.participantToken;
    localStorage.setItem(`room:${response.room.code}:participantToken`, participantToken);
}

function participantHeaders() {
    return participantToken ? { 'X-Participant-Token': participantToken } : {};
}

async function getRoom(code) {
    const response = await fetch(`${apiBase}/rooms/${code}/state`);
    const json = await response.json();
    if (!response.ok) throw new Error(json.error ?? 'Sala no encontrada.');
    return json;
}

async function loadRoom(code) {
    try {
        const response = await getRoom(code);
        setRoom(response.room);
    } catch (error) {
        toast(error.message);
    }
}

function setRoom(room) {
    if (currentRoom?.code !== room.code) currentStatistics = null;
    currentRoom = room;
    pendingAnswerFeedback = null;
    document.querySelector('#homeView')?.classList.add('hidden');
    document.querySelector('#localSetupView')?.classList.add('hidden');
    document.querySelector('#gameView')?.classList.remove('hidden');
    document.querySelector('#roomCode').textContent = room.code;
    history.replaceState(null, '', `?room=${room.code}`);
    renderRoom();
    startPolling();
}

function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(async () => {
        if (!currentRoom || currentRoom.status === 'finished') return;
        try {
            const response = await getRoom(currentRoom.code);
            if (response.room.version !== currentRoom.version) {
                currentRoom = response.room;
                renderRoom();
            }
        } catch (error) {
            console.warn(error);
        }
    }, 2500);
}

function renderRoom() {
    renderScoreboard();
    renderBoard();
    renderTopDiceStatus();
    renderPreferencesOverlay();
}

function renderTopDiceStatus() {
    const box = document.querySelector('#topDiceStatus');
    if (!box || !currentRoom) return;
    const state = currentRoom.state;
    const active = state.players[state.currentPlayer] ?? state.players[0];
    const label = {
        lobby: 'Esperando equipos',
        roll: `Turno de ${active?.name ?? 'equipo'}`,
        choose_move: `Mover ${active?.name ?? 'equipo'}`,
        question: `Pregunta de ${active?.name ?? 'equipo'}`,
        finished: `${state.players[state.winner]?.name ?? 'Un equipo'} gana`
    }[state.phase] ?? state.phase;
    const lastRoll = state.lastResult?.type === 'rolled' ? Number(state.lastResult.dice) : null;

    box.innerHTML = `
        <span class="top-dice-label">${escapeHtml(label)}</span>
        <span class="top-dice-face" aria-label="${lastRoll ? `Ultimo dado: ${lastRoll}` : 'Sin tirada reciente'}">
            ${renderDiceFace(Math.max(1, Math.min(6, lastRoll || state.dice || 1)))}
        </span>
    `;
}

function renderResultStatus(result) {
    if (result.type === 'rolled') return renderDiceResult(result);
    const text = resultText(result);
    return text ? `<p class="muted">${escapeHtml(text)}</p>` : '';
}

function resultText(result) {
    if (result.type === 'correct') return 'Respuesta correcta. Repite turno.';
    if (result.type === 'wrong') return 'Respuesta fallada. Pasa el turno.';
    if (result.type === 'roll_again') return 'Casilla de volver a tirar.';
    if (result.type === 'final_question') return 'Pregunta final para ganar.';
    return '';
}

function renderDiceResult(result) {
    const dice = Math.max(1, Math.min(6, Number(result.dice) || 1));
    const diceKey = `${currentRoom?.code ?? 'local'}:${currentRoom?.version ?? 0}:${dice}`;
    const shouldAnimate = lastAnimatedDiceKey !== diceKey;
    lastAnimatedDiceKey = diceKey;

    return `
        <div class="dice-result" role="status" aria-label="Resultado del dado: ${dice}">
            <span class="dice-result-label">Dado</span>
            <span class="dice-face ${shouldAnimate ? 'rolling' : ''}" aria-hidden="true">
                ${renderDiceFace(dice)}
            </span>
        </div>
    `;
}

function renderDiceFace(value) {
    const pips = {
        1: [[50, 50]],
        2: [[30, 30], [70, 70]],
        3: [[30, 30], [50, 50], [70, 70]],
        4: [[30, 30], [70, 30], [30, 70], [70, 70]],
        5: [[30, 30], [70, 30], [50, 50], [30, 70], [70, 70]],
        6: [[30, 25], [70, 25], [30, 50], [70, 50], [30, 75], [70, 75]]
    }[value] ?? [[50, 50]];

    return `
        <svg class="dice-svg" viewBox="0 0 100 100" focusable="false">
            <rect x="8" y="8" width="84" height="84" rx="16"></rect>
            ${pips.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="7"></circle>`).join('')}
        </svg>
    `;
}

function renderPreferencesOverlay() {
    const box = document.querySelector('#preferencesOverlay');
    if (!box || !currentRoom) return;
    const whiteBordersEnabled = localStorage.getItem(whiteBordersPreferenceKey) === '1';
    const pulseDestinationsEnabled = localStorage.getItem(pulseDestinationsPreferenceKey) === '1';
    const animateTokensEnabled = animateTokensPreferenceEnabled();
    const diceResultDelay = diceResultDelayPreferenceMs();

    box.classList.toggle('hidden', !preferencesOverlayOpen);
    box.innerHTML = `
        <article class="preferences-card">
            <div class="preferences-card-head">
                <div>
                    <p class="eyebrow">Preferencias</p>
                    <h2 id="preferencesOverlayTitle">Ajustes del tablero</h2>
                </div>
                <button id="preferencesCloseButton" class="icon-button secondary" type="button" aria-label="Cerrar preferencias">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m6.4 5 12.6 12.6-1.4 1.4L5 6.4 6.4 5Zm12.6 1.4L6.4 19 5 17.6 17.6 5 19 6.4Z"></path>
                    </svg>
                </button>
            </div>
            <div class="preferences-content">
                <label class="toggle-row">
                    <span>Bordes blancos del tablero</span>
                    <input id="whiteBordersToggle" type="checkbox" ${whiteBordersEnabled ? 'checked' : ''}>
                </label>
                <label class="toggle-row">
                    <span>Animar destinos disponibles</span>
                    <input id="pulseDestinationsToggle" type="checkbox" ${pulseDestinationsEnabled ? 'checked' : ''}>
                </label>
                <label class="toggle-row">
                    <span>Animar movimiento de fichas</span>
                    <input id="animateTokensToggle" type="checkbox" ${animateTokensEnabled ? 'checked' : ''}>
                </label>
                <label class="select-row">
                    <span>Duracion resultado dado</span>
                    <select id="diceResultDelaySelect">
                        <option value="500" ${diceResultDelay === 500 ? 'selected' : ''}>0.5s</option>
                        <option value="1000" ${diceResultDelay === 1000 ? 'selected' : ''}>1s</option>
                        <option value="1500" ${diceResultDelay === 1500 ? 'selected' : ''}>1.5s</option>
                        <option value="2000" ${diceResultDelay === 2000 ? 'selected' : ''}>2s</option>
                    </select>
                </label>
            </div>
        </article>
    `;

    box.onclick = (event) => {
        if (event.target !== box) return;
        preferencesOverlayOpen = false;
        renderPreferencesOverlay();
    };
    document.querySelector('#preferencesCloseButton')?.addEventListener('click', () => {
        preferencesOverlayOpen = false;
        renderPreferencesOverlay();
    });
    document.querySelector('#whiteBordersToggle')?.addEventListener('change', (event) => {
        localStorage.setItem(whiteBordersPreferenceKey, event.target.checked ? '1' : '0');
        renderBoard();
    });
    document.querySelector('#pulseDestinationsToggle')?.addEventListener('change', (event) => {
        localStorage.setItem(pulseDestinationsPreferenceKey, event.target.checked ? '1' : '0');
        renderBoard();
    });
    document.querySelector('#animateTokensToggle')?.addEventListener('change', (event) => {
        localStorage.setItem(animateTokensPreferenceKey, event.target.checked ? '1' : '0');
    });
    document.querySelector('#diceResultDelaySelect')?.addEventListener('change', (event) => {
        localStorage.setItem(diceResultDelayPreferenceKey, event.target.value);
    });
}

function renderPreferences() {
    renderPreferencesOverlay();
}

function animateTokensPreferenceEnabled() {
    return localStorage.getItem(animateTokensPreferenceKey) !== '0';
}

function diceResultDelayPreferenceMs() {
    const stored = Number(localStorage.getItem(diceResultDelayPreferenceKey));
    return [500, 1000, 1500, 2000].includes(stored) ? stored : 1000;
}

function renderScoreboard() {
    const box = document.querySelector('#scoreboardBox');
    if (!box || !currentRoom) return;
    const state = currentRoom.state;
    const categories = currentRoom.categories;

    box.innerHTML = `
        <div class="scoreboard-track" role="list" aria-label="Marcador principal">
            ${state.players.map((player, index) => {
                const wedgeCount = categories.filter((category) => player.wedges?.[category.slug]).length;
                const isActive = index === state.currentPlayer;
                return `
                    <article class="scoreboard-card ${isActive ? 'scoreboard-active' : ''}" role="listitem" style="--player-color:${escapeAttr(player.color)}">
                        <div class="scoreboard-token" aria-hidden="true">${escapeHtml(player.name.charAt(0).toUpperCase())}</div>
                        <div class="scoreboard-main">
                            <div class="scoreboard-head">
                                <strong>${escapeHtml(player.name)}</strong>
                                ${isActive ? '<span class="turn-badge">Turno</span>' : ''}
                            </div>
                            <div class="scoreboard-progress">
                                <span>${wedgeCount}/${categories.length} quesitos</span>
                            </div>
                        </div>
                        ${renderScoreboardWedgeWheel(player, categories)}
                    </article>
                `;
            }).join('')}
        </div>
    `;
}

function renderScoreboardWedgeWheel(player, categories) {
    const wedgeCount = categories.filter((category) => player.wedges?.[category.slug]).length;
    const sliceAngle = 360 / categories.length;

    return `
        <svg class="scoreboard-wheel" viewBox="0 0 100 100" role="img" aria-label="${wedgeCount} de ${categories.length} quesitos">
            <title>${wedgeCount} de ${categories.length} quesitos</title>
            ${categories.map((category, index) => {
                const owned = Boolean(player.wedges?.[category.slug]);
                const startAngle = -90 + index * sliceAngle;
                const endAngle = startAngle + sliceAngle;
                return `
                    <path class="scoreboard-wheel-slice ${owned ? 'scoreboard-wheel-slice-owned' : ''}"
                          d="${scoreboardWheelSlicePath(50, 50, 42, startAngle, endAngle)}"
                          style="--category-color:${escapeAttr(category.color)}">
                        <title>${escapeHtml(category.name)}: ${owned ? 'conseguido' : 'pendiente'}</title>
                    </path>
                `;
            }).join('')}
            <circle class="scoreboard-wheel-border" cx="50" cy="50" r="42"></circle>
        </svg>
    `;
}

function scoreboardWheelSlicePath(cx, cy, radius, startAngle, endAngle) {
    const start = scoreboardWheelPoint(cx, cy, radius, startAngle);
    const end = scoreboardWheelPoint(cx, cy, radius, endAngle);
    const largeArc = endAngle - startAngle > 180 ? 1 : 0;

    return [
        `M ${cx} ${cy}`,
        `L ${start.x.toFixed(3)} ${start.y.toFixed(3)}`,
        `A ${radius} ${radius} 0 ${largeArc} 1 ${end.x.toFixed(3)} ${end.y.toFixed(3)}`,
        'Z'
    ].join(' ');
}

function scoreboardWheelPoint(cx, cy, radius, degrees) {
    const angle = degrees * Math.PI / 180;
    return {
        x: cx + Math.cos(angle) * radius,
        y: cy + Math.sin(angle) * radius
    };
}

async function sendAction(payload) {
    if (!currentRoom) return;
    const response = await apiFetch(`/rooms/${currentRoom.code}/actions`, {
        ...payload,
        expectedVersion: currentRoom.version
    }, participantHeaders());
    currentRoom = response.room;
    renderRoom();
}

async function submitRollFromOverlay() {
    if (!currentRoom || isRollSubmitting) return;
    const player = currentRoom.state.players[currentRoom.state.currentPlayer];
    const rollStartedAt = Date.now();
    isRollSubmitting = true;
    pendingDiceRollFeedback = {
        isRolling: true,
        playerName: player?.name ?? 'equipo',
        dice: null
    };
    renderRoom();

    try {
        const response = await apiFetch(`/rooms/${currentRoom.code}/actions`, {
            action: 'roll',
            playerId: currentRoom.state.currentPlayer,
            expectedVersion: currentRoom.version
        }, participantHeaders());
        const elapsed = Date.now() - rollStartedAt;
        if (elapsed < minimumDiceRollAnimationMs) {
            await new Promise((resolve) => setTimeout(resolve, minimumDiceRollAnimationMs - elapsed));
        }
        currentRoom = response.room;
        pendingDiceRollFeedback = {
            isRolling: false,
            playerName: player?.name ?? 'equipo',
            dice: Math.max(1, Math.min(6, Number(response.room.state.dice) || 1))
        };
        clearTimeout(diceRollFeedbackTimer);
        diceRollFeedbackTimer = setTimeout(() => {
            pendingDiceRollFeedback = null;
            diceRollFeedbackTimer = null;
            renderRoom();
        }, diceResultDelayPreferenceMs());
    } catch (error) {
        pendingDiceRollFeedback = null;
        toast(error.message);
    } finally {
        isRollSubmitting = false;
        renderRoom();
    }
}

async function submitAnswerWithFeedback(payload) {
    if (!currentRoom) return;
    const previousState = currentRoom.state;
    const response = await apiFetch(`/rooms/${currentRoom.code}/actions`, {
        ...payload,
        expectedVersion: currentRoom.version
    }, participantHeaders());
    currentRoom = response.room;
    pendingAnswerFeedback = buildAnswerFeedback(previousState, currentRoom.state, payload);
    renderRoom();
}

function buildAnswerFeedback(previousState, nextState, payload) {
    const question = previousState.currentQuestion;
    const result = nextState.lastResult ?? {};
    const correct = result.type === 'correct';
    const selectedOption = Number.isInteger(payload.option) ? payload.option : null;
    const correctOption = Number.isInteger(result.correctOption) ? result.correctOption : null;
    const nextPlayer = nextState.players[nextState.currentPlayer];
    const winner = nextState.players[nextState.winner];
    const category = categoryMeta(question?.category);

    return {
        correct,
        categoryName: category.name,
        categoryColor: category.color,
        questionText: question?.question ?? '',
        selectedOptionText: selectedOption !== null ? question?.options?.[selectedOption] : null,
        correctOptionText: correctOption !== null ? question?.options?.[correctOption] : null,
        title: correct ? 'Correcto' : 'Fallado',
        nextText: correct
            ? (nextState.phase === 'finished' ? `Partida terminada. Ha ganado ${winner?.name ?? 'un equipo'}.` : 'Vuelve a tirar.')
            : `Turno de ${nextPlayer?.name ?? 'siguiente equipo'}.`
    };
}

function renderBoard() {
    const mount = document.querySelector('#boardMount');
    if (!mount || !currentRoom) return;
    const state = currentRoom.state;
    const spaces = currentRoom.spaces;
    const valid = new Set(state.validDestinations ?? []);
    const canAct = currentRoom.mode === 'local' || playerId === state.currentPlayer || state.phase === 'lobby';
    const playerPositions = new Map();
    for (const [index, player] of state.players.entries()) {
        if (!player.position) continue;
        if (!playerPositions.has(player.position)) playerPositions.set(player.position, []);
        playerPositions.get(player.position).push({ ...player, playerIndex: index });
    }

    const orderedSpaces = Object.values(spaces).sort((a, b) => {
        const trackOrder = { hub: 0, spoke: 1, outer: 2 };
        return (trackOrder[a.track] ?? 9) - (trackOrder[b.track] ?? 9)
            || (a.spoke ?? -1) - (b.spoke ?? -1)
            || (a.index ?? 0) - (b.index ?? 0);
    });

    const spaceMarkup = orderedSpaces.map((space) => {
        const point = pointForSpace(space.id);
        const color = colorForSpace(space, currentRoom.categories);
        const classes = [
            'space',
            `space-track-${space.track}`,
            `space-type-${space.type}`,
            valid.has(space.id) ? 'valid' : '',
            playerPositions.has(space.id) ? 'player-position' : ''
        ].filter(Boolean).join(' ');
        const shape = space.visual?.shape ?? 'point';
        const element = renderSpaceShape(space, classes, color);
        return `
            <g class="space-group space-${escapeAttr(shape)}" aria-label="${escapeAttr(labelForSpace(space))}">
                <title>${escapeHtml(labelForSpace(space))}</title>
                ${element}
                ${space.type === 'roll_again' ? renderRerollIcon(point) : ''}
                ${space.type === 'wedge' ? renderWedgeIcon(point, space) : ''}
            </g>
        `;
    }).join('');

    const highlightMarkup = orderedSpaces
        .filter((space) => valid.has(space.id) || playerPositions.has(space.id))
        .map((space) => renderSpaceHighlight(space, valid.has(space.id), playerPositions.has(space.id)))
        .join('');

    const tokenMarkup = [...playerPositions.entries()].flatMap(([spaceId, players]) => {
        const point = pointForSpace(spaceId);
        const visiblePlayers = players.filter((player) => !pendingTokenAnimation
            || player.playerIndex !== pendingTokenAnimation.playerId
            || spaceId !== pendingTokenAnimation.from);

        return visiblePlayers.map((player, index) => {
            const offset = tokenOffset(index, visiblePlayers.length);
            return `<circle cx="${point.x + offset.x}" cy="${point.y + offset.y}" r="8" fill="${escapeAttr(player.color)}" stroke="#111827" stroke-width="2"></circle>`;
        });
    }).join('');
    const animatedTokenMarkup = pendingTokenAnimation ? renderAnimatedToken(pendingTokenAnimation) : '';

    const boardClasses = [
        'board-svg',
        localStorage.getItem(whiteBordersPreferenceKey) === '1' ? 'show-space-borders' : '',
        localStorage.getItem(pulseDestinationsPreferenceKey) === '1' ? 'pulse-valid-destinations' : ''
    ].filter(Boolean).join(' ');

    mount.innerHTML = `
        <div class="board-frame">
            <svg class="${boardClasses}" viewBox="0 0 600 600" role="img" aria-label="Tablero de trivial">
                <rect x="0" y="0" width="600" height="600" rx="18" fill="#0b2852"></rect>
                <circle cx="300" cy="300" r="292" fill="#12396f" stroke="#d7c47a" stroke-width="4"></circle>
                <circle cx="300" cy="300" r="222" fill="#0b2852" stroke="#d7c47a" stroke-width="2"></circle>
                <circle class="outer-track-base" cx="300" cy="300" r="261" fill="none" stroke="#f8fafc" stroke-width="50"></circle>
                ${spaceMarkup}
                ${renderCenterHex(spaces.center?.visual)}
                ${highlightMarkup}
                ${tokenMarkup}
                ${animatedTokenMarkup}
            </svg>
            <div id="spaceTooltip" class="space-tooltip hidden" role="tooltip"></div>
            ${pendingAnswerFeedback
                ? renderAnswerFeedbackOverlay(pendingAnswerFeedback)
                : renderLobbyOverlay(state, canAct)
                    || renderFinishedOverlay(state)
                    || renderDiceRollOverlay(state, canAct)
                    || renderQuestionOverlay(state, canAct)}
        </div>
    `;

    bindLobbyOverlayControls(state, canAct);
    bindQuestionOverlayControls(state, canAct);
    bindAnswerFeedbackControls();
    bindDiceRollOverlayControls(state, canAct);

    mount.querySelectorAll('.space').forEach((spaceEl) => {
        spaceEl.addEventListener('mouseenter', (event) => showSpaceTooltip(spaceEl.dataset.spaceLabel, event));
        spaceEl.addEventListener('mousemove', (event) => showSpaceTooltip(spaceEl.dataset.spaceLabel, event));
        spaceEl.addEventListener('mouseleave', hideSpaceTooltip);
        spaceEl.addEventListener('focus', (event) => showSpaceTooltip(spaceEl.dataset.spaceLabel, event));
        spaceEl.addEventListener('blur', hideSpaceTooltip);
    });

    mount.querySelectorAll('.space.valid').forEach((spaceEl) => {
        spaceEl.addEventListener('click', () => {
            if (currentRoom.mode !== 'local' && playerId !== currentRoom.state.currentPlayer) {
                toast('No es tu turno.');
                return;
            }
            moveWithTokenAnimation(spaceEl.dataset.space);
        });
    });
}

function renderAnimatedToken(animation) {
    const dx = animation.toPoint.x - animation.fromPoint.x;
    const dy = animation.toPoint.y - animation.fromPoint.y;

    return `
        <g class="animated-token"
           style="--token-move-x:${dx}px;--token-move-y:${dy}px"
           aria-hidden="true">
            <circle cx="${animation.fromPoint.x}" cy="${animation.fromPoint.y}" r="9"
                    fill="${escapeAttr(animation.playerColor)}"
                    stroke="#111827"
                    stroke-width="2.4"></circle>
        </g>
    `;
}

async function moveWithTokenAnimation(destination) {
    if (!currentRoom || pendingTokenAnimation) return;
    const state = currentRoom.state;
    const player = state.players[state.currentPlayer];
    const from = player?.position;
    const payload = { action: 'move', playerId: state.currentPlayer, destination };

    if (!animateTokensPreferenceEnabled() || !from || from === destination) {
        sendAction(payload);
        return;
    }

    pendingTokenAnimation = {
        playerId: state.currentPlayer,
        playerColor: player.color,
        from,
        to: destination,
        fromPoint: pointForSpace(from),
        toPoint: pointForSpace(destination)
    };
    renderBoard();

    await new Promise((resolve) => setTimeout(resolve, 560));

    try {
        const response = await apiFetch(`/rooms/${currentRoom.code}/actions`, {
            ...payload,
            expectedVersion: currentRoom.version
        }, participantHeaders());
        currentRoom = response.room;
    } catch (error) {
        toast(error.message);
    } finally {
        pendingTokenAnimation = null;
        renderRoom();
    }
}

function renderLobbyOverlay(state, canAct) {
    if (state.phase !== 'lobby') return '';
    const ready = state.players.length >= 2;

    return `
        <div class="question-overlay lobby-overlay" role="dialog" aria-modal="true" aria-labelledby="lobbyOverlayTitle">
            <article class="floating-question-card lobby-overlay-card">
                <p class="eyebrow">Sala preparada</p>
                <h2 id="lobbyOverlayTitle">Equipos conectados</h2>
                <div class="lobby-team-list" role="list">
                    ${state.players.map((player) => `
                        <span class="lobby-team-pill" role="listitem" style="--player-color:${escapeAttr(player.color)}">
                            ${escapeHtml(player.name)}
                        </span>
                    `).join('')}
                </div>
                <p class="muted">${ready ? 'Cuando querais, empezad la partida.' : 'Minimo 2 equipos para empezar.'}</p>
                <button id="startButton" type="button" ${ready && canAct ? '' : 'disabled'}>Empezar partida</button>
            </article>
        </div>
    `;
}

function bindLobbyOverlayControls(state, canAct) {
    if (state.phase !== 'lobby' || !canAct || state.players.length < 2) return;
    document.querySelector('#startButton')?.addEventListener('click', () => sendAction({ action: 'start' }));
}

function renderFinishedOverlay(state) {
    if (state.phase !== 'finished') return '';
    const winner = state.players[state.winner];
    if (!currentStatistics && !statisticsLoading) loadFinishedStatistics();

    return `
        <div class="question-overlay finished-overlay" role="dialog" aria-modal="true" aria-labelledby="finishedOverlayTitle">
            <article class="floating-question-card finished-overlay-card">
                <p class="eyebrow">Partida terminada</p>
                <h2 id="finishedOverlayTitle">${escapeHtml(winner?.name ?? 'Un equipo')} ha ganado</h2>
                <p class="muted">Todos los quesitos y pregunta final completados.</p>
                ${renderFinishedStatistics(currentStatistics)}
            </article>
        </div>
    `;
}

function renderFinishedStatistics(statistics) {
    if (!statistics) return '<p class="muted">Calculando estad&iacute;sticas...</p>';
    return `<div class="finished-statistics">
        ${statistics.teams.map((team) => `
            <article class="question-item">
                <strong>${escapeHtml(team.name)}</strong>
                <p>${team.correct}/${team.answers} aciertos &middot; ${Number(team.accuracy).toFixed(2)}%</p>
                <p class="muted">Mejor racha: ${team.longestStreak}</p>
            </article>`).join('')}
    </div>`;
}

async function loadFinishedStatistics() {
    if (!currentRoom || !participantToken) return;
    statisticsLoading = true;
    try {
        const response = await fetch(`${apiBase}/rooms/${currentRoom.code}/statistics`, {
            headers: participantHeaders()
        });
        const data = await response.json();
        if (!response.ok) throw new Error(apiErrorMessage(data, 'No se pudieron cargar las estadisticas.'));
        currentStatistics = data.statistics;
    } catch (error) {
        toast(error.message);
    } finally {
        statisticsLoading = false;
        renderRoom();
    }
}

function renderDiceRollOverlay(state, canAct) {
    if ((!pendingDiceRollFeedback && state.phase !== 'roll') || pendingTokenAnimation) return '';
    const player = state.players[state.currentPlayer];
    const feedback = pendingDiceRollFeedback;
    const isRolling = feedback?.isRolling || isRollSubmitting;
    const dice = feedback?.dice ?? Math.max(1, Math.min(6, Number(state.dice) || 6));
    const title = feedback && !feedback.isRolling ? `Has sacado un ${dice}` : 'Tira el dado';
    const cardClass = feedback && !feedback.isRolling ? 'dice-roll-final' : '';

    return `
        <div class="question-overlay dice-roll-overlay" role="dialog" aria-modal="true" aria-labelledby="diceRollTitle">
            <article class="floating-question-card dice-roll-card ${cardClass}">
                <p class="eyebrow">Turno de ${escapeHtml(feedback?.playerName ?? player?.name ?? 'equipo')}</p>
                <h2 id="diceRollTitle">${title}</h2>
                <span class="dice-face ${isRolling ? 'rolling dice-tumbling' : ''}" aria-hidden="true">
                    ${renderDiceFace(dice)}
                </span>
                ${feedback && !feedback.isRolling ? `<p class="dice-roll-result" role="status">Has sacado un ${dice}</p>` : ''}
                ${feedback && !feedback.isRolling ? '<p class="muted">Elige destino en un momento...</p>' : `
                    <button id="diceRollOverlayButton" class="dice-roll-button" type="button" ${canAct && !isRollSubmitting ? '' : 'disabled'}>
                        ${isRollSubmitting ? 'Tirando...' : 'Tirar dado'}
                    </button>
                `}
            </article>
        </div>
    `;
}

function bindDiceRollOverlayControls(state, canAct) {
    if (state.phase !== 'roll' || !canAct) return;
    document.querySelector('#diceRollOverlayButton')?.addEventListener('click', submitRollFromOverlay);
}

function renderAnswerFeedbackOverlay(feedback) {
    const stateClass = feedback.correct ? 'answer-feedback-correct' : 'answer-feedback-wrong';

    return `
        <div class="question-overlay answer-feedback-overlay" role="dialog" aria-modal="true" aria-labelledby="answerFeedbackTitle">
            <article class="floating-question-card answer-feedback-card ${stateClass}" style="--question-color:${escapeAttr(feedback.categoryColor)}">
                <div class="floating-question-topline">
                    <span class="category-pill">${escapeHtml(feedback.categoryName)}</span>
                    <span class="question-mode">Resultado</span>
                </div>
                <p class="answer-feedback-kicker">${feedback.correct ? 'Acierto' : 'Fallo'}</p>
                <h2 id="answerFeedbackTitle">${escapeHtml(feedback.title)}</h2>
                ${feedback.questionText ? `<p class="muted">${escapeHtml(feedback.questionText)}</p>` : ''}
                ${feedback.selectedOptionText ? `<p class="answer-feedback-choice"><strong>Respuesta elegida:</strong> ${escapeHtml(feedback.selectedOptionText)}</p>` : ''}
                ${feedback.correctOptionText && feedback.correctOptionText !== feedback.selectedOptionText ? `<p class="answer-feedback-choice"><strong>Respuesta correcta:</strong> ${escapeHtml(feedback.correctOptionText)}</p>` : ''}
                <p class="answer-feedback-next">${escapeHtml(feedback.nextText)}</p>
                <button id="answerFeedbackContinue" type="button">Continuar</button>
            </article>
        </div>
    `;
}

function bindAnswerFeedbackControls() {
    document.querySelector('#answerFeedbackContinue')?.addEventListener('click', () => {
        pendingAnswerFeedback = null;
        renderRoom();
    });
}

function renderQuestionOverlay(state, canAct) {
    if (state.phase !== 'question') return '';

    const question = state.currentQuestion;
    if (!question) {
        return `
            <div class="question-overlay" id="questionOverlayBackdrop" role="dialog" aria-modal="true" aria-label="Cargando pregunta">
                <div class="floating-question-card">
                    <p class="muted">Cargando pregunta...</p>
                </div>
            </div>
        `;
    }

    const category = categoryMeta(question.category);
    const options = question.options ?? [];
    const correctText = Number.isInteger(question.correct) ? options[question.correct] : null;
    const questionKey = `${question.id}:${state.currentPlayer}`;
    const isJudge = state.answerMode === 'judge';
    const isRevealed = isJudge && revealedJudgeQuestionKey === questionKey;
    const controls = isJudge
        ? renderJudgeQuestionActions(isRevealed, correctText, canAct)
        : renderOptionQuestionActions(options, canAct);

    return `
        <div class="question-overlay" id="questionOverlayBackdrop" role="dialog" aria-modal="true" aria-labelledby="questionOverlayTitle">
            <article class="floating-question-card" style="--question-color:${escapeAttr(category.color)}">
                <div class="floating-question-topline">
                    <span class="category-pill">${escapeHtml(category.name)}</span>
                    <span class="question-mode">${isJudge ? 'Modo juez' : '4 opciones'}</span>
                </div>
                <h2 id="questionOverlayTitle">${escapeHtml(question.question)}</h2>
                ${isRevealed && correctText ? `<p class="revealed-answer"><strong>Respuesta:</strong> ${escapeHtml(correctText)}</p>` : ''}
                ${controls}
            </article>
        </div>
    `;
}

function renderJudgeQuestionActions(isRevealed, correctText, canAct) {
    if (isRevealed) {
        return `
            <div class="judge-actions">
                <button class="good" id="judgeCorrect" type="button" ${canAct ? '' : 'disabled'}>Acierto</button>
                <button class="bad" id="judgeWrong" type="button" ${canAct ? '' : 'disabled'}>Fallo</button>
            </div>
        `;
    }

    return `
        <button class="reveal-answer-button" id="revealAnswer" type="button" ${canAct && correctText ? '' : 'disabled'}>
            Mostrar respuesta
        </button>
    `;
}

function renderOptionQuestionActions(options, canAct) {
    return `
        <div class="answer-grid floating-answer-grid">
            ${options.map((option, index) => `
                <button class="secondary answer-button" data-option="${index}" type="button" ${canAct ? '' : 'disabled'}>
                    ${escapeHtml(option)}
                </button>
            `).join('')}
        </div>
    `;
}

function bindQuestionOverlayControls(state, canAct) {
    if (state.phase !== 'question' || !state.currentQuestion) return;

    document.querySelector('#revealAnswer')?.addEventListener('click', () => {
        if (!canAct) return;
        revealedJudgeQuestionKey = `${state.currentQuestion.id}:${state.currentPlayer}`;
        renderRoom();
    });
    document.querySelector('#judgeCorrect')?.addEventListener('click', () => submitAnswerWithFeedback({ action: 'answer', playerId: state.currentPlayer, correct: true }));
    document.querySelector('#judgeWrong')?.addEventListener('click', () => submitAnswerWithFeedback({ action: 'answer', playerId: state.currentPlayer, correct: false }));
    document.querySelectorAll('.answer-button').forEach((button) => {
        button.addEventListener('click', () => submitAnswerWithFeedback({ action: 'answer', playerId: state.currentPlayer, option: Number(button.dataset.option) }));
    });
}

function renderSpaceShape(space, classes, color) {
    const shape = pathForSpace(space);
    const label = escapeAttr(labelForSpace(space));
    const focusable = space.track === 'hub' || space.type === 'category' || space.type === 'wedge' || space.type === 'roll_again' ? '0' : '-1';
    if (shape.type === 'path') {
        return `<path class="${classes}" data-space="${escapeAttr(space.id)}" data-space-label="${label}" aria-label="${label}" tabindex="${focusable}" d="${shape.d}" fill="${color}"></path>`;
    }

    return `<circle class="${classes}" data-space="${escapeAttr(space.id)}" data-space-label="${label}" aria-label="${label}" tabindex="${focusable}" cx="${shape.cx}" cy="${shape.cy}" r="${shape.r}" fill="${color}"></circle>`;
}

function renderWedgeIcon(point, space) {
    const rotation = wedgeIconRotation(space);

    return `
        <g class="space-icon wedge-icon" transform="translate(${point.x} ${point.y}) rotate(${rotation})" aria-hidden="true">
            <path d="M -11 -10 C -16 -6 -16 6 -11 10 L 13 0 L -11 -10 Z"></path>
        </g>
    `;
}

function wedgeIconRotation(space) {
    const outwardAngle = -90 + (space.visual?.angleOffset ?? space.spoke * 60);
    return outwardAngle + 180;
}

function renderRerollIcon(point) {
    return `
        <g class="space-icon reroll-icon" transform="translate(${point.x} ${point.y})" aria-hidden="true">
            <rect x="-9" y="-7" width="14" height="14" rx="2.5"></rect>
            <circle cx="-5" cy="-3" r="1.3"></circle>
            <circle cx="-2" cy="0" r="1.3"></circle>
            <circle cx="1" cy="3" r="1.3"></circle>
            <path class="reroll-arrow" d="M -2 -12 A 12 12 0 0 1 12 -1"></path>
            <path class="reroll-arrow-head" d="M 12 -1 L 7 -2 L 10 -6"></path>
        </g>
    `;
}

function renderSpaceHighlight(space, isValid, hasPlayer) {
    const shape = pathForSpace(space);
    const classes = [
        'space-highlight',
        isValid ? 'valid-highlight' : '',
        hasPlayer ? 'player-highlight' : ''
    ].filter(Boolean).join(' ');

    if (shape.type === 'path') {
        return `<path class="${classes}" data-space="${escapeAttr(space.id)}" d="${shape.d}"></path>`;
    }

    return `<circle class="${classes}" data-space="${escapeAttr(space.id)}" cx="${shape.cx}" cy="${shape.cy}" r="${shape.r}"></circle>`;
}

function labelForSpace(space) {
    if (!space) return '';
    if (space.type === 'center') return 'Centro';
    if (space.type === 'roll_again') return 'Vuelve a tirar';

    const category = categoryLabels[space.category] ?? space.label ?? space.category;
    if (space.type === 'wedge') return `Quesito: ${category}`;

    return category;
}

function showSpaceTooltip(label, event) {
    const tooltip = document.querySelector('#spaceTooltip');
    const frame = tooltip?.closest('.board-frame');
    if (!tooltip || !frame || !label) return;

    tooltip.textContent = label;
    tooltip.classList.remove('hidden');

    const frameRect = frame.getBoundingClientRect();
    const targetRect = event.currentTarget?.getBoundingClientRect?.();
    const clientX = event.clientX ?? ((targetRect?.left ?? frameRect.left) + (targetRect?.width ?? 0) / 2);
    const clientY = event.clientY ?? ((targetRect?.top ?? frameRect.top) + (targetRect?.height ?? 0) / 2);
    const tooltipRect = tooltip.getBoundingClientRect();
    const x = Math.min(Math.max(clientX - frameRect.left + 12, 8), Math.max(8, frameRect.width - tooltipRect.width - 8));
    const y = Math.min(Math.max(clientY - frameRect.top + 12, 8), Math.max(8, frameRect.height - tooltipRect.height - 8));

    tooltip.style.left = `${x}px`;
    tooltip.style.top = `${y}px`;
}

function hideSpaceTooltip() {
    document.querySelector('#spaceTooltip')?.classList.add('hidden');
}

function pathForSpace(space) {
    if (space.id === 'center') {
        return { type: 'path', d: hexagonPath(300, 300, space.visual?.radius ?? 42) };
    }
    if (space.track === 'outer') {
        const totalOuter = Object.values(currentRoom.spaces).filter((item) => item.track === 'outer').length;
        const slotAngle = 360 / totalOuter;
        const centerAngle = -90 + (space.visual?.angleOffset ?? (space.type === 'wedge' ? space.spoke * 60 : space.index * slotAngle));
        const width = space.visual?.angleWidth ?? (slotAngle - 1);
        const start = centerAngle - width / 2;
        const end = centerAngle + width / 2;
        const inner = space.visual?.inner ?? 236;
        const outer = space.visual?.outer ?? 286;
        return { type: 'path', d: ringSegmentPath(300, 300, inner, outer, start, end) };
    }
    if (space.track === 'spoke') {
        const angle = -90 + (space.visual?.angleOffset ?? space.spoke * 60);
        const inner = space.visual?.inner ?? 78;
        const outer = space.visual?.outer ?? 114;
        const width = space.visual?.width ?? 42;
        if (space.visual?.shape === 'curved_spoke_end') {
            const curveOuter = space.visual?.curveOuter ?? outer;
            return { type: 'path', d: curvedSpokeEndPath(300, 300, inner, outer, curveOuter, width, angle) };
        }
        return { type: 'path', d: straightSpokePath(300, 300, inner, outer, width, angle) };
    }

    const point = pointForSpace(space.id);
    return { type: 'circle', cx: point.x, cy: point.y, r: 18 };
}

function renderCenterHex(visual = {}) {
    return `<path d="${hexagonPath(300, 300, visual?.radius ?? 42)}" fill="#0b2852" stroke="#f8fafc" stroke-width="2.5" pointer-events="none"></path>`;
}

function pointForSpace(id) {
    const space = currentRoom?.spaces?.[id];
    if (!space || id === 'center') return { x: 300, y: 300 };
    if (space.track === 'spoke') {
        const radius = ((space.visual?.inner ?? 78) + (space.visual?.outer ?? 114)) / 2;
        return polarPointByDegrees(-90 + (space.visual?.angleOffset ?? space.spoke * 60), radius);
    }
    if (space.track === 'outer') {
        const totalOuter = Object.values(currentRoom.spaces).filter((item) => item.track === 'outer').length;
        const angle = -90 + (space.visual?.angleOffset ?? (space.type === 'wedge' ? space.spoke * 60 : space.index * (360 / totalOuter)));
        return polarPointByDegrees(angle, space.type === 'wedge' ? 258 : 261);
    }
    return polarPoint(space.spoke ?? 0, 120);
}

function polarPoint(index, radius) {
    return polarPointByDegrees(-90 + index * 60, radius);
}

function polarPointByDegrees(degrees, radius) {
    const angle = degrees * Math.PI / 180;
    return {
        x: Math.round(300 + Math.cos(angle) * radius),
        y: Math.round(300 + Math.sin(angle) * radius)
    };
}

function ringSegmentPath(cx, cy, innerRadius, outerRadius, startDegrees, endDegrees) {
    const outerStart = pointOnCircle(cx, cy, outerRadius, startDegrees);
    const outerEnd = pointOnCircle(cx, cy, outerRadius, endDegrees);
    const innerEnd = pointOnCircle(cx, cy, innerRadius, endDegrees);
    const innerStart = pointOnCircle(cx, cy, innerRadius, startDegrees);
    const largeArc = endDegrees - startDegrees > 180 ? 1 : 0;

    return [
        `M ${outerStart.x} ${outerStart.y}`,
        `A ${outerRadius} ${outerRadius} 0 ${largeArc} 1 ${outerEnd.x} ${outerEnd.y}`,
        `L ${innerEnd.x} ${innerEnd.y}`,
        `A ${innerRadius} ${innerRadius} 0 ${largeArc} 0 ${innerStart.x} ${innerStart.y}`,
        'Z'
    ].join(' ');
}

function straightSpokePath(cx, cy, innerRadius, outerRadius, width, degrees) {
    const angle = degrees * Math.PI / 180;
    const ux = Math.cos(angle);
    const uy = Math.sin(angle);
    const px = -uy;
    const py = ux;
    const half = width / 2;
    const inner = { x: cx + ux * innerRadius, y: cy + uy * innerRadius };
    const outer = { x: cx + ux * outerRadius, y: cy + uy * outerRadius };
    const points = [
        { x: inner.x + px * half, y: inner.y + py * half },
        { x: outer.x + px * half, y: outer.y + py * half },
        { x: outer.x - px * half, y: outer.y - py * half },
        { x: inner.x - px * half, y: inner.y - py * half }
    ];

    return polygonPath(points);
}

function curvedSpokeEndPath(cx, cy, innerRadius, outerRadius, curveOuterRadius, width, degrees) {
    const angle = degrees * Math.PI / 180;
    const ux = Math.cos(angle);
    const uy = Math.sin(angle);
    const px = -uy;
    const py = ux;
    const half = width / 2;
    const inner = { x: cx + ux * innerRadius, y: cy + uy * innerRadius };
    const shoulder = { x: cx + ux * outerRadius, y: cy + uy * outerRadius };
    const halfAngle = Math.asin(Math.min(half / curveOuterRadius, 0.98)) * 180 / Math.PI;
    const curveStart = pointOnCircle(cx, cy, curveOuterRadius, degrees + halfAngle);
    const curveEnd = pointOnCircle(cx, cy, curveOuterRadius, degrees - halfAngle);
    const innerStart = { x: inner.x + px * half, y: inner.y + py * half };
    const innerEnd = { x: inner.x - px * half, y: inner.y - py * half };
    const shoulderStart = { x: shoulder.x + px * half, y: shoulder.y + py * half };
    const shoulderEnd = { x: shoulder.x - px * half, y: shoulder.y - py * half };

    return [
        `M ${Number(innerStart.x.toFixed(2))} ${Number(innerStart.y.toFixed(2))}`,
        `L ${Number(shoulderStart.x.toFixed(2))} ${Number(shoulderStart.y.toFixed(2))}`,
        `L ${curveStart.x} ${curveStart.y}`,
        `A ${curveOuterRadius} ${curveOuterRadius} 0 0 0 ${curveEnd.x} ${curveEnd.y}`,
        `L ${Number(shoulderEnd.x.toFixed(2))} ${Number(shoulderEnd.y.toFixed(2))}`,
        `L ${Number(innerEnd.x.toFixed(2))} ${Number(innerEnd.y.toFixed(2))}`,
        'Z'
    ].join(' ');
}

function hexagonPath(cx, cy, apothem) {
    const radius = apothem / Math.cos(Math.PI / 6);
    const points = Array.from({ length: 6 }, (_, index) => pointOnCircle(cx, cy, radius, -120 + index * 60));

    return polygonPath(points);
}

function polygonPath(points) {
    return points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${Number(point.x.toFixed(2))} ${Number(point.y.toFixed(2))}`)
        .concat('Z')
        .join(' ');
}

function pieSlicePath(cx, cy, innerRadius, outerRadius, startDegrees, endDegrees) {
    return ringSegmentPath(cx, cy, innerRadius, outerRadius, startDegrees, endDegrees);
}

function pointOnCircle(cx, cy, radius, degrees) {
    const angle = degrees * Math.PI / 180;
    return {
        x: Number((cx + Math.cos(angle) * radius).toFixed(2)),
        y: Number((cy + Math.sin(angle) * radius).toFixed(2))
    };
}

function colorForSpace(space, categories) {
    if (space.type === 'center') return '#ffffff';
    if (space.type === 'roll_again') return '#cbd5e1';
    const category = categories.find((item) => item.slug === space.category);
    return category?.color ?? '#e5e7eb';
}

function categoryMeta(slug) {
    const categories = currentRoom ? currentRoom.categories : [];
    const category = categories.find((item) => item.slug === slug);
    return {
        name: category?.name ?? categoryLabels[slug] ?? slug,
        color: category?.color ?? '#1457d9'
    };
}

function tokenOffset(index, total) {
    if (total === 1) return { x: 0, y: 0 };
    const angle = (index / total) * Math.PI * 2;
    return { x: Math.cos(angle) * 11, y: Math.sin(angle) * 11 };
}

function toast(message) {
    const el = document.querySelector('#toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 2800);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}
