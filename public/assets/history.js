document.addEventListener('DOMContentLoaded', async () => {
    const box = document.querySelector('#historyList');
    try {
        const response = await fetch('./api.php/me/games');
        const data = await response.json();
        if (!response.ok) throw new Error(data.error?.message ?? data.error ?? 'No se pudo cargar el historial.');
        if (!data.games.length) {
            box.innerHTML = '<p class="muted">Todav&iacute;a no tienes partidas asociadas.</p>';
            return;
        }
        box.innerHTML = data.games.map((game) => `
            <article class="question-item">
                <strong>Sala ${escapeHistory(game.code)}</strong>
                <p class="muted">${escapeHistory(game.mode)} &middot; ${escapeHistory(game.status)} &middot; ${escapeHistory(new Date(game.createdAt).toLocaleString())}</p>
                <button type="button" data-history-code="${escapeHistory(game.code)}">Ver informe</button>
            </article>`).join('');
        box.querySelectorAll('[data-history-code]').forEach((button) => button.addEventListener('click', () => loadHistoryDetail(button.dataset.historyCode)));
    } catch (error) {
        box.innerHTML = `<p>${escapeHistory(error.message)}</p>`;
    }
});

async function loadHistoryDetail(code) {
    const detail = document.querySelector('#historyDetail');
    try {
        const response = await fetch(`./api.php/me/games/${encodeURIComponent(code)}`);
        const data = await response.json();
        if (!response.ok) throw new Error(data.error?.message ?? data.error ?? 'No se pudo cargar el informe.');
        const report = data.statistics;
        updateHistoryBreadcrumb(report.code);
        detail.classList.remove('hidden');
        detail.innerHTML = `<h2>Informe de ${escapeHistory(report.code)}</h2>
            <p>Duraci&oacute;n: ${report.durationSeconds ?? '-'} s &middot; Ganador: ${report.winnerSlot === null ? '-' : Number(report.winnerSlot) + 1}</p>
            <div class="question-list">${report.teams.map((team) => `<article class="question-item"><strong>${escapeHistory(team.name)}</strong><p>${team.correct}/${team.answers} correctas &middot; ${team.accuracy}% &middot; racha m&aacute;xima ${team.longestStreak}</p></article>`).join('')}</div>`;
    } catch (error) {
        updateHistoryBreadcrumb(code);
        detail.classList.remove('hidden');
        detail.innerHTML = `<p>${escapeHistory(error.message)}</p>`;
    }
}

function updateHistoryBreadcrumb(code = null) {
    const breadcrumbs = document.querySelector('#historyBreadcrumbs ol');
    if (!breadcrumbs) return;
    breadcrumbs.innerHTML = `<li><a href="./">Jugar</a></li>`
        + (code
            ? `<li><a href="history.php">Historial</a></li><li><span aria-current="page">Sala ${escapeHistory(code)}</span></li>`
            : '<li><span aria-current="page">Historial</span></li>');
}

function escapeHistory(value) {
    return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}
