const apiBase = 'api.php';
const playerColors = ['#2563eb', '#dc2626', '#16a34a', '#ca8a04', '#9333ea', '#0891b2'];
const whiteBordersPreferenceKey = 'board:whiteBorders';

let currentRoom = null;
let playerId = null;
let pollingTimer = null;
let revealedJudgeQuestionKey = null;
let lastAnimatedDiceKey = null;

const categoryLabels = {
    geography: 'Geografia',
    art: 'Arte y literatura',
    history: 'Historia',
    entertainment: 'Entretenimiento',
    science: 'Ciencia y naturaleza',
    sports: 'Deportes y ocio'
};

document.addEventListener('DOMContentLoaded', () => {
    bindGameForms();
    bindAdminForms();
    bindFullscreenControls();
    const params = new URLSearchParams(window.location.search);
    const code = params.get('room');
    if (code) {
        playerId = Number(localStorage.getItem(`room:${code}:playerId`) ?? 0);
        loadRoom(code.toUpperCase());
    }
});

function bindGameForms() {
    const localForm = document.querySelector('#localForm');
    const onlineCreateForm = document.querySelector('#onlineCreateForm');
    const joinForm = document.querySelector('#joinForm');
    const copyButton = document.querySelector('#copyRoomButton');

    if (localForm) {
        localForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(localForm);
            const players = String(data.get('players'))
                .split(/\r?\n/)
                .map((name) => name.trim())
                .filter(Boolean)
                .map((name, index) => ({ name, color: playerColors[index] }));
            const response = await apiFetch('/rooms', {
                mode: 'local',
                answerMode: data.get('answerMode'),
                players
            });
            playerId = 0;
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
                color: playerColors[0]
            });
            playerId = 0;
            localStorage.setItem(`room:${response.room.code}:playerId`, String(playerId));
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
    const importForm = document.querySelector('#adminImportForm');
    const listForm = document.querySelector('#adminListForm');

    if (importForm) {
        importForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(importForm);
            const response = await apiFetch('/admin/questions', {
                adminKey: data.get('adminKey'),
                csv: data.get('csv'),
                replace: data.get('replace') === 'on'
            });
            toast(`${response.imported} preguntas importadas.`);
            renderQuestions(response.questions);
        });
    }

    if (listForm) {
        listForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(listForm);
            const response = await fetch(`${apiBase}/admin/questions?admin_key=${encodeURIComponent(data.get('adminKey'))}`);
            const json = await response.json();
            if (!response.ok) throw new Error(json.error ?? 'Error al cargar preguntas.');
            renderQuestions(json.questions);
        });
    }
}

async function apiFetch(path, payload) {
    const response = await fetch(apiBase + path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const json = await response.json();
    if (!response.ok) {
        toast(json.error ?? 'Error de API.');
        throw new Error(json.error ?? 'Error de API.');
    }
    return json;
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
    currentRoom = room;
    document.querySelector('#homeView')?.classList.add('hidden');
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
    renderBoard();
    renderStatus();
    renderPreferences();
    renderPlayers();
    renderControls();
}

function renderStatus() {
    const box = document.querySelector('#statusBox');
    if (!box || !currentRoom) return;
    const state = currentRoom.state;
    const active = state.players[state.currentPlayer] ?? state.players[0];
    const statusText = {
        lobby: 'Esperando equipos',
        roll: `Turno de ${active?.name ?? 'equipo'}: tirar dado`,
        choose_move: `Turno de ${active?.name ?? 'equipo'}: elegir destino`,
        question: `Pregunta para ${active?.name ?? 'equipo'}`,
        finished: `${state.players[state.winner]?.name ?? 'Un equipo'} ha ganado`
    }[state.phase] ?? state.phase;

    box.innerHTML = `
        <p class="eyebrow">Estado</p>
        <h2>${escapeHtml(statusText)}</h2>
        ${state.lastResult ? renderResultStatus(state.lastResult) : ''}
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

function renderPreferences() {
    const box = document.querySelector('#preferencesBox');
    if (!box || !currentRoom) return;
    const whiteBordersEnabled = localStorage.getItem(whiteBordersPreferenceKey) === '1';

    box.innerHTML = `
        <p class="eyebrow">Preferencias</p>
        <label class="toggle-row">
            <span>Bordes blancos del tablero</span>
            <input id="whiteBordersToggle" type="checkbox" ${whiteBordersEnabled ? 'checked' : ''}>
        </label>
    `;

    document.querySelector('#whiteBordersToggle')?.addEventListener('change', (event) => {
        localStorage.setItem(whiteBordersPreferenceKey, event.target.checked ? '1' : '0');
        renderBoard();
    });
}

function renderPlayers() {
    const box = document.querySelector('#playersBox');
    if (!box || !currentRoom) return;
    const state = currentRoom.state;
    const categories = currentRoom.categories;
    box.innerHTML = state.players.map((player, index) => `
        <article class="player-card ${index === state.currentPlayer ? 'active' : ''}">
            <div class="player-head">
                <strong>${escapeHtml(player.name)}</strong>
                <span class="swatch" style="background:${escapeAttr(player.color)}"></span>
            </div>
            <div class="wedge-row">
                ${categories.map((category) => `
                    <span class="wedge-dot ${player.wedges?.[category.slug] ? 'owned' : ''}"
                          title="${escapeAttr(category.name)}"
                          style="background:${escapeAttr(category.color)}"></span>
                `).join('')}
            </div>
        </article>
    `).join('');
}

function renderControls() {
    const box = document.querySelector('#controlsBox');
    if (!box || !currentRoom) return;
    const state = currentRoom.state;
    const canAct = currentRoom.mode === 'local' || playerId === state.currentPlayer || state.phase === 'lobby';

    if (state.phase === 'lobby') {
        box.innerHTML = `
            <p class="muted">Comparte el codigo de sala. Minimo 2 equipos, maximo 6.</p>
            <button id="startButton" type="button" ${state.players.length < 2 ? 'disabled' : ''}>Empezar partida</button>
        `;
        document.querySelector('#startButton')?.addEventListener('click', () => sendAction({ action: 'start' }));
        return;
    }

    if (state.phase === 'roll') {
        box.innerHTML = `<button id="rollButton" type="button" ${canAct ? '' : 'disabled'}>Tirar dado</button>`;
        document.querySelector('#rollButton')?.addEventListener('click', () => sendAction({ action: 'roll', playerId: state.currentPlayer }));
        return;
    }

    if (state.phase === 'choose_move') {
        box.innerHTML = '<p class="muted">Elige una casilla marcada en el tablero.</p>';
        return;
    }

    if (state.phase === 'question') {
        renderQuestionControls(box, state, canAct);
        return;
    }

    if (state.phase === 'finished') {
        box.innerHTML = `<p class="muted">Partida terminada.</p>`;
    }
}

function renderQuestionControls(box, state, canAct) {
    const question = state.currentQuestion;
    if (!question) {
        box.innerHTML = '<p class="muted">Cargando pregunta...</p>';
        return;
    }
    const category = categoryLabels[question.category] ?? question.category;
    const options = question.options ?? [];
    const correctText = Number.isInteger(question.correct) ? options[question.correct] : null;

    if (state.answerMode === 'judge') {
        const questionKey = `${question.id}:${state.currentPlayer}`;
        const isRevealed = revealedJudgeQuestionKey === questionKey;
        box.innerHTML = `
            <div class="question-card">
                <p class="eyebrow">${escapeHtml(category)}</p>
                <h2>${escapeHtml(question.question)}</h2>
                ${isRevealed && correctText ? `<p class="revealed-answer"><strong>Respuesta:</strong> ${escapeHtml(correctText)}</p>` : ''}
            </div>
            ${isRevealed
                ? `<button class="good" id="judgeCorrect" type="button" ${canAct ? '' : 'disabled'}>Acierto</button>
                   <button class="bad" id="judgeWrong" type="button" ${canAct ? '' : 'disabled'}>Fallo</button>`
                : `<button id="revealAnswer" type="button" ${canAct ? '' : 'disabled'}>Mostrar respuesta</button>`}
        `;
        document.querySelector('#revealAnswer')?.addEventListener('click', () => {
            revealedJudgeQuestionKey = questionKey;
            renderControls();
        });
        document.querySelector('#judgeCorrect')?.addEventListener('click', () => sendAction({ action: 'answer', playerId: state.currentPlayer, correct: true }));
        document.querySelector('#judgeWrong')?.addEventListener('click', () => sendAction({ action: 'answer', playerId: state.currentPlayer, correct: false }));
        return;
    }

    box.innerHTML = `
        <div class="question-card">
            <p class="eyebrow">${escapeHtml(category)}</p>
            <h2>${escapeHtml(question.question)}</h2>
        </div>
        <div class="answer-grid">
            ${options.map((option, index) => `
                <button class="secondary answer-button" data-option="${index}" type="button" ${canAct ? '' : 'disabled'}>
                    ${escapeHtml(option)}
                </button>
            `).join('')}
        </div>
    `;
    document.querySelectorAll('.answer-button').forEach((button) => {
        button.addEventListener('click', () => sendAction({ action: 'answer', playerId: state.currentPlayer, option: Number(button.dataset.option) }));
    });
}

async function sendAction(payload) {
    if (!currentRoom) return;
    const response = await apiFetch(`/rooms/${currentRoom.code}/actions`, payload);
    currentRoom = response.room;
    renderRoom();
}

function renderBoard() {
    const mount = document.querySelector('#boardMount');
    if (!mount || !currentRoom) return;
    const state = currentRoom.state;
    const spaces = currentRoom.spaces;
    const valid = new Set(state.validDestinations ?? []);
    const playerPositions = new Map();
    for (const player of state.players) {
        if (!player.position) continue;
        if (!playerPositions.has(player.position)) playerPositions.set(player.position, []);
        playerPositions.get(player.position).push(player);
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
                ${space.type === 'wedge' ? renderWedgeIcon(point) : ''}
            </g>
        `;
    }).join('');

    const highlightMarkup = orderedSpaces
        .filter((space) => valid.has(space.id) || playerPositions.has(space.id))
        .map((space) => renderSpaceHighlight(space, valid.has(space.id), playerPositions.has(space.id)))
        .join('');

    const tokenMarkup = [...playerPositions.entries()].flatMap(([spaceId, players]) => {
        const point = pointForSpace(spaceId);
        return players.map((player, index) => {
            const offset = tokenOffset(index, players.length);
            return `<circle cx="${point.x + offset.x}" cy="${point.y + offset.y}" r="8" fill="${escapeAttr(player.color)}" stroke="#111827" stroke-width="2"></circle>`;
        });
    }).join('');

    mount.innerHTML = `
        <div class="board-frame">
            <svg class="board-svg ${localStorage.getItem(whiteBordersPreferenceKey) === '1' ? 'show-space-borders' : ''}" viewBox="0 0 600 600" role="img" aria-label="Tablero de trivial">
                <rect x="0" y="0" width="600" height="600" rx="18" fill="#0b2852"></rect>
                <circle cx="300" cy="300" r="292" fill="#12396f" stroke="#d7c47a" stroke-width="4"></circle>
                <circle cx="300" cy="300" r="222" fill="#0b2852" stroke="#d7c47a" stroke-width="2"></circle>
                <circle class="outer-track-base" cx="300" cy="300" r="261" fill="none" stroke="#f8fafc" stroke-width="50"></circle>
                ${spaceMarkup}
                ${renderCenterHex(spaces.center?.visual)}
                ${highlightMarkup}
                ${tokenMarkup}
            </svg>
            <div id="spaceTooltip" class="space-tooltip hidden" role="tooltip"></div>
        </div>
    `;

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
            sendAction({ action: 'move', playerId: currentRoom.state.currentPlayer, destination: spaceEl.dataset.space });
        });
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

function renderWedgeIcon(point) {
    return `
        <g class="space-icon wedge-icon" transform="translate(${point.x} ${point.y})" aria-hidden="true">
            <path d="M -12 -8 L 13 0 L -12 8 Z"></path>
            <circle cx="-5" cy="-3" r="1.8"></circle>
            <circle cx="-2" cy="4" r="1.5"></circle>
            <circle cx="5" cy="0" r="1.6"></circle>
        </g>
    `;
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

function tokenOffset(index, total) {
    if (total === 1) return { x: 0, y: 0 };
    const angle = (index / total) * Math.PI * 2;
    return { x: Math.cos(angle) * 11, y: Math.sin(angle) * 11 };
}

function renderQuestions(questions) {
    const box = document.querySelector('#adminQuestions');
    if (!box) return;
    if (!questions.length) {
        box.innerHTML = '<p class="muted">No hay preguntas cargadas.</p>';
        return;
    }
    box.innerHTML = questions.map((question) => `
        <article class="question-item">
            <p class="eyebrow">${escapeHtml(categoryLabels[question.category] ?? question.category)}</p>
            <strong>${escapeHtml(question.question)}</strong>
            <p class="muted">${question.options.map(escapeHtml).join(' / ')}</p>
        </article>
    `).join('');
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
