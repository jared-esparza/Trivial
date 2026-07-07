const apiBase = 'api.php';
const playerColors = ['#2563eb', '#dc2626', '#16a34a', '#ca8a04', '#9333ea', '#0891b2'];

let currentRoom = null;
let playerId = null;
let pollingTimer = null;
let revealedJudgeQuestionKey = null;

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
        ${state.lastResult ? `<p class="muted">${escapeHtml(resultText(state.lastResult))}</p>` : ''}
    `;
}

function resultText(result) {
    if (result.type === 'rolled') return `Dado: ${result.dice}`;
    if (result.type === 'correct') return 'Respuesta correcta. Repite turno.';
    if (result.type === 'wrong') return 'Respuesta fallada. Pasa el turno.';
    if (result.type === 'roll_again') return 'Casilla de volver a tirar.';
    if (result.type === 'final_question') return 'Pregunta final para ganar.';
    return '';
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
        box.innerHTML = `<p class="muted">Dado: ${state.dice}. Elige una casilla marcada en el tablero.</p>`;
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
            valid.has(space.id) ? 'valid' : '',
            playerPositions.has(space.id) ? 'player-position' : ''
        ].filter(Boolean).join(' ');
        const shape = space.visual?.shape ?? 'point';
        const element = renderSpaceShape(space, classes, color);
        return `
            <g class="space-group space-${escapeAttr(shape)}">
                ${element}
                ${space.type === 'roll_again' ? `<text class="space-label" x="${point.x}" y="${point.y + 4}" text-anchor="middle">R</text>` : ''}
                ${space.type === 'wedge' ? `<text class="space-label wedge-label" x="${point.x}" y="${point.y + 5}" text-anchor="middle">Q</text>` : ''}
            </g>
        `;
    }).join('');

    const tokenMarkup = [...playerPositions.entries()].flatMap(([spaceId, players]) => {
        const point = pointForSpace(spaceId);
        return players.map((player, index) => {
            const offset = tokenOffset(index, players.length);
            return `<circle cx="${point.x + offset.x}" cy="${point.y + offset.y}" r="8" fill="${escapeAttr(player.color)}" stroke="#111827" stroke-width="2"></circle>`;
        });
    }).join('');

    mount.innerHTML = `
        <svg class="board-svg" viewBox="0 0 600 600" role="img" aria-label="Tablero de trivial">
            <rect x="0" y="0" width="600" height="600" rx="18" fill="#0b2852"></rect>
            <circle cx="300" cy="300" r="292" fill="#12396f" stroke="#d7c47a" stroke-width="4"></circle>
            <circle cx="300" cy="300" r="228" fill="#0b2852" stroke="#d7c47a" stroke-width="2"></circle>
            ${renderHubWedges(currentRoom.categories)}
            ${spaceMarkup}
            ${tokenMarkup}
        </svg>
    `;

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
    if (space.id === 'center') {
        return `<circle class="${classes}" data-space="${escapeAttr(space.id)}" cx="300" cy="300" r="42" fill="transparent"></circle>`;
    }
    if (space.track === 'outer') {
        const totalOuter = Object.values(currentRoom.spaces).filter((item) => item.track === 'outer').length;
        const angle = 360 / totalOuter;
        const start = -90 + space.index * angle + 0.5;
        const end = -90 + (space.index + 1) * angle - 0.5;
        const inner = space.type === 'wedge' ? 226 : 238;
        const outer = space.type === 'wedge' ? 292 : 286;
        return `<path class="${classes}" data-space="${escapeAttr(space.id)}" d="${ringSegmentPath(300, 300, inner, outer, start, end)}" fill="${color}"></path>`;
    }
    if (space.track === 'spoke') {
        const angle = -90 + space.spoke * 60;
        const inner = space.visual?.inner ?? 78;
        const outer = space.visual?.outer ?? 114;
        return `<path class="${classes}" data-space="${escapeAttr(space.id)}" d="${ringSegmentPath(300, 300, inner, outer, angle - 7.5, angle + 7.5)}" fill="${color}"></path>`;
    }

    const point = pointForSpace(space.id);
    return `<circle class="${classes}" data-space="${escapeAttr(space.id)}" cx="${point.x}" cy="${point.y}" r="18" fill="${color}"></circle>`;
}

function renderHubWedges(categories) {
    return categories.map((category, index) => {
        const start = -90 + index * 60 + 2;
        const end = -90 + (index + 1) * 60 - 2;
        return `<path d="${pieSlicePath(300, 300, 10, 39, start, end)}" fill="${escapeAttr(category.color)}" stroke="#f8fafc" stroke-width="2"></path>`;
    }).join('');
}

function pointForSpace(id) {
    const space = currentRoom?.spaces?.[id];
    if (!space || id === 'center') return { x: 300, y: 300 };
    if (space.track === 'spoke') {
        const radius = ((space.visual?.inner ?? 78) + (space.visual?.outer ?? 114)) / 2;
        return polarPoint(space.spoke, radius);
    }
    if (space.track === 'outer') {
        const totalOuter = Object.values(currentRoom.spaces).filter((item) => item.track === 'outer').length;
        return polarPointByDegrees(-90 + (space.index + 0.5) * (360 / totalOuter), space.type === 'wedge' ? 260 : 262);
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
    if (space.type === 'roll_again') return '#f8fafc';
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
