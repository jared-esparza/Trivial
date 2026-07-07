<?php

require_once __DIR__ . '/../src/bootstrap.php';
$config = app_config();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin preguntas - <?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="./"><?= htmlspecialchars($config['app_name'], ENT_QUOTES, 'UTF-8') ?></a>
        <a class="admin-link" href="./">Volver al juego</a>
    </header>

    <main class="shell admin-shell">
        <section class="panel admin-panel">
            <p class="eyebrow">Banco de preguntas</p>
            <h1>Importar preguntas</h1>
            <p class="muted">Formato CSV: category,question,option_a,option_b,option_c,option_d,correct. La respuesta correcta es 0, 1, 2 o 3.</p>
            <form id="adminImportForm">
                <label>
                    Clave admin
                    <input name="adminKey" type="password" required>
                </label>
                <label>
                    CSV
                    <textarea name="csv" rows="12" required>category,question,option_a,option_b,option_c,option_d,correct
geography,Capital de Francia,Paris,Lyon,Burdeos,Niza,0</textarea>
                </label>
                <label class="checkbox-line">
                    <input name="replace" type="checkbox">
                    Reemplazar todas las preguntas existentes
                </label>
                <button type="submit">Importar</button>
            </form>
        </section>

        <section class="panel admin-panel">
            <h2>Preguntas cargadas</h2>
            <form id="adminListForm" class="inline-form">
                <input name="adminKey" type="password" placeholder="Clave admin" required>
                <button type="submit">Ver listado</button>
            </form>
            <div id="adminQuestions" class="question-list"></div>
        </section>
    </main>

    <div id="toast" class="toast hidden"></div>
    <script src="assets/app.js"></script>
</body>
</html>
