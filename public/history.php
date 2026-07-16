<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
$token = (string) ($_COOKIE['rq_session'] ?? '');
$user = $token === '' ? null : (new SessionRepository(app_pdo()))->findUserByToken($token);
try {
    Authorization::requireVerifiedUser($user);
} catch (Throwable) {
    header('Location: account.php?return=history.php');
    exit;
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Historial - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title><link rel="stylesheet" href="assets/styles.css"></head>
<body><?= NavigationView::renderHeader((string) $config['app_name'], $user, 'history', 'history.php') ?>
<main class="shell admin-shell"><?= NavigationView::renderBreadcrumbs([['./', 'Jugar'], [null, 'Historial']], 'historyBreadcrumbs') ?><section class="panel admin-panel"><p class="eyebrow">Mi actividad</p><h1>Historial de partidas</h1><div id="historyList" class="question-list"><p class="muted">Cargando...</p></div></section><section id="historyDetail" class="panel admin-panel hidden" aria-live="polite"></section></main>
<script src="assets/session-nav.js"></script><script src="assets/history.js"></script></body></html>
