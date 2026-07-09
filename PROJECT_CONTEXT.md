# trivial - Contexto del Proyecto

Ultima actualizacion: 2026-07-09

## Objetivo

`trivial` es un juego web tipo Trivial con tablero circular, categorias, quesitos, casillas de volver a tirar, modo local y modo online por salas.

El nombre `trivial` es provisional. El usuario lo cambiara cuando tenga el nombre definitivo.

## Estado Git

- Rama principal: `main`.
- Ultimo commit conocido al actualizar este archivo: `645bf95 Merge pull request #1 from jared-esparza/codex/home-local-redesign`.
- Estado antes de actualizar este archivo: limpio contra `origin/main`.
- Este archivo quedara como cambio pendiente hasta que se haga commit.
- Commits recientes relevantes:
  - `645bf95 Merge pull request #1 from jared-esparza/codex/home-local-redesign`
  - `f609876 Anadir packs de colores y preferencias para cambiarlos`
  - `a5ff3f1 Fix distribucion de casillas y uso de colores oficiales`
  - `aab8e48 Mejora UI/UX de pagina principal y creacion de salas`
  - `490bb9e Update PROJECT_CONTEXT.md`

## Stack y Despliegue

- PHP sin framework.
- MySQL/MariaDB para produccion en IONOS por FTP.
- SQLite local automatico si no existe `config.php`.
- JavaScript vanilla, sin build frontend.
- CSS propio.
- Sin Composer obligatorio.

Local:

```powershell
php -S 127.0.0.1:4181 -t public
```

Abrir:

```text
http://127.0.0.1:4181
```

Si no existe `config.php`, la app usa:

- DB: `storage/dev.sqlite`
- clave admin: `admin-local`

Produccion:

1. Copiar `config.example.php` a `config.php`.
2. Cambiar `admin_key`.
3. Configurar datos MySQL.
4. Subir por FTP.
5. Idealmente apuntar el document root del hosting a `public/`.

## Estructura Importante

```text
public/
  index.php              Pantalla principal y partida.
  admin.php              Importacion/listado de preguntas.
  api.php                API JSON de salas, acciones y admin.
  assets/app.js          Cliente JS: tablero SVG, preferencias, turnos, preguntas, polling.
  assets/styles.css      Estilos UI, tablero, overlays, marcador y fullscreen.
  assets/home-board-art.png  Recorte decorativo para la portada.
  assets/local-board-art.png Recorte decorativo para configuracion local.

src/
  bootstrap.php          Carga config, conecta DB, crea schema.
  Database.php           PDO y creacion de tablas.
  GameEngine.php         Reglas, tablero, grafo, movimiento, turnos, victoria.
  QuestionImporter.php   Validacion/importacion CSV.
  QuestionRepository.php Persistencia de preguntas.
  RoomRepository.php     Persistencia de salas y estado de partida.

tests/run.php            Test runner PHP sin framework.
data/questions-demo.csv  Preguntas demo para probar las 6 categorias.
database/schema.mysql.sql Schema MySQL opcional/manual.
Mockups/                 Mockups y tablero de referencia usados para la portada/configuracion local.
```

## Reglas Implementadas

- 2 a 6 equipos.
- Seis categorias: `geography`, `art`, `history`, `entertainment`, `science`, `sports`.
- Colores/categorias clasicas:
  - `geography`: azul, Geografia.
  - `entertainment`: rosa, Entretenimiento.
  - `history`: amarillo, Historia.
  - `art`: marron, Arte y literatura.
  - `science`: verde, Ciencia y naturaleza.
  - `sports`: naranja, Deportes y ocio.
- Los jugadores empiezan en `center`.
- En su turno tiran dado y eligen una casilla valida.
- El tablero se modela como grafo en `GameEngine::graph()`.
- Las casillas se definen en `GameEngine::boardDefinition()` y se exponen con metadata visual via `boardSpaces()`.
- Tablero actual:
  - Centro hexagonal.
  - Seis radios rectos de 5 casillas cada uno.
  - La primera casilla de cada radio toca el hexagono central.
  - La ultima casilla de cada radio conserva borde curvo hacia el anillo.
  - Las 5 casillas de cada radio tienen la misma medida entre vertices.
  - Cada casilla `wedge_*` esta en el anillo exterior, alineada con su radio y con color de categoria.
  - Cada sector exterior tiene 6 casillas entre quesitos con patron estructural `pregunta, reroll, pregunta, pregunta, reroll, pregunta`.
  - Hay 12 casillas `roll_again`, integradas en el anillo exterior.
- Distribucion de quesitos clasica desde las 3 en punto y en sentido antihorario:
  - `history`, `sports`, `geography`, `art`, `science`, `entertainment`.
- `GameEngine::boardDistribution()` centraliza la distribucion logica de quesitos, aro exterior y radios.
- Las 3 casillas vecinas de cada quesito usan la categoria opuesta:
  - `history` <-> `art`.
  - `sports` <-> `science`.
  - `geography` <-> `entertainment`.
- Cada categoria aparece 10 veces contando su quesito: 1 quesito + 9 casillas normales.
- Los radios tienen 5 casillas normales y no contienen `roll_again`.
- Las casillas `roll_again` estan integradas en el anillo exterior y se pintan en gris.
- Casilla `roll_again`: no hay pregunta, el mismo equipo vuelve a tirar.
- Casilla `wedge_*`: si acierta, gana el quesito de esa categoria.
- Si falla, pasa el turno.
- Si acierta, repite turno.
- Para ganar debe tener todos los quesitos, llegar al centro y acertar pregunta final aleatoria.

## Modos de Juego

### Online

- Crear sala online con codigo desde la portada.
- El formulario de crear sala online pide el nombre del equipo directamente, sin pantalla intermedia.
- Unirse con codigo.
- Validacion automatica con 4 opciones.
- Polling HTTP cada 2.5 segundos desde `public/assets/app.js`.

### Local

Al crear partida local se elige:

- `auto`: 4 opciones y validacion automatica.
- `judge`: modo clasico con juez.
- La portada abre una vista separada `#localSetupView` para configurar partida local.
- `#localSetupView` permite escribir de 2 a 6 equipos, muestra contador `N/6 equipos` y tiene boton `Volver`.

En modo `judge`:

- La tarjeta flotante muestra la pregunta sin respuesta.
- El juez pulsa `Mostrar respuesta`.
- Entonces aparecen respuesta correcta y botones `Acierto` / `Fallo`.
- Tras marcar acierto o fallo aparece una tarjeta de resultado con boton `Continuar`.
- Esto evita que el jugador vea la respuesta antes de contestar y confirma claramente el resultado.

## Preferencias UI

Las preferencias ya no viven en la columna lateral. Se abren desde el boton de engranaje `#preferencesButton` en la barra superior de la partida.

La tarjeta se renderiza en `#preferencesOverlay`, que debe estar dentro de `#gameView`. Esto es importante: si queda fuera de `#gameView`, no se vera cuando la partida entre en fullscreen porque el navegador solo muestra el arbol del elemento fullscreen.

Preferencias actuales:

- `Bordes blancos del tablero`.
  - `localStorage`: `board:whiteBorders`.
  - Si esta activada, el SVG recibe `show-space-borders`.
- `Animar destinos disponibles`.
  - `localStorage`: `board:pulseDestinations`.
  - Desactivada por defecto.
  - Si esta activada, el SVG recibe `pulse-valid-destinations` y los destinos validos usan `destination-pulse`.
- `Animar movimiento de fichas`.
  - `localStorage`: `board:animateTokens`.
  - Activada por defecto.
  - Si esta activada, al escoger destino se dibuja una ficha temporal animada y se oculta la ficha real del jugador activo hasta completar el movimiento.
- `Duracion resultado dado`.
  - `localStorage`: `board:diceResultDelayMs`.
  - Selector con valores `0.5s`, `1s`, `1.5s`, `2s`; por defecto `1s`.
- `Pack de colores`.
  - `localStorage`: `board:colorPack`.
  - Valores: `classic` y `alternative`; por defecto `classic`.
  - `classic`: azul, rosa, amarillo, marron, verde, naranja.
  - `alternative`: conserva los colores anteriores donde `art` usa morado y `entertainment` usa rojo.
  - Solo cambia el color visual asociado a cada categoria en tablero, marcador y tarjetas; no cambia distribucion, IDs, grafo ni reglas.

Los bordes negros de seleccion/jugador se renderizan en una capa superior separada (`space-highlight`) para evitar que casillas vecinas los tapen.

## Marcador y Columna Lateral

- `#scoreboardBox` esta dentro del panel del tablero pero fuera de `#boardMount`.
- `renderScoreboard()` pinta una banda de equipos sobre el tablero.
- Cada equipo se muestra como ficha de juego con color, nombre, etiqueta `Turno` si esta activo y progreso `N/6 quesitos`.
- El equipo activo usa `scoreboard-active` y una animacion sutil `scoreboard-turn-pulse`.
- Los quesitos del marcador son una rueda circular SVG:
  - `renderScoreboardWedgeWheel(player, categories)`.
  - 6 porciones `scoreboard-wheel-slice`.
  - Porciones ganadas con `scoreboard-wheel-slice-owned` a color completo.
  - Porciones pendientes con el mismo color atenuado.
- La columna lateral fue eliminada.
- Ya no existen `side-panel`, `statusBox`, `preferencesBox`, `playersBox` ni `controlsBox` en la vista de partida.
- El estado de equipos y turno se comunica mediante el marcador principal y las tarjetas flotantes.
- La accion de lobby `Empezar partida` vive en una tarjeta flotante sobre el tablero.

## Interfaz Visual del Tablero

Mejoras actuales en `public/assets/app.js` y `public/assets/styles.css`:

- Pantalla principal redisenada:
  - `#homeView` tiene hero con ilustracion decorativa del tablero y tarjetas de accion.
  - `Partida local` abre `#localSetupView`.
  - `Crear sala online` incluye input de nombre de equipo.
  - `Unirse a sala` mantiene codigo de sala + nombre de equipo.
  - Usa recortes decorativos `public/assets/home-board-art.png` y `public/assets/local-board-art.png`.
- Boton `fullscreenBoardButton` en la cabecera del tablero.
- Boton `preferencesButton` con icono de engranaje en la cabecera del tablero.
- `topDiceStatus` muestra un dado compacto y el estado/turno actual en la barra superior.
- Pantalla completa jugable sobre `#gameView`:
  - Usa Fullscreen API cuando el navegador lo permite.
  - Usa clase `fullscreen-fallback` si el navegador bloquea fullscreen nativo.
  - Integra tablero, marcador, dado superior, preferencias, equipos y tarjetas flotantes.
  - En fullscreen el marcador se compacta.
  - `game-board-panel` usa `grid-template-rows: auto auto minmax(0, 1fr)`.
  - `board-mount` usa `min-height: 0`, `overflow: hidden` y `container-type: size`.
  - En fullscreen, `board-frame` usa `width: min(100%, 100cqh)` para respetar la altura real disponible.
- Layout de partida:
  - `gameView` es de una sola columna.
  - El tablero queda centrado y puede crecer mas que cuando existia la columna lateral.
  - La anchura normal de `board-frame` es `min(100%, 820px)`.
- Casillas `wedge_*`:
  - Ya no muestran letra `Q`.
  - Renderizan un icono inline SVG minimalista con `renderWedgeIcon(point, space)`.
  - La punta mira hacia el centro del tablero.
  - El icono no tiene puntos internos; usa lados largos rectos y lado corto curvado.
- Casillas `roll_again`:
  - Ya no muestran letra `R`.
  - Renderizan un icono inline SVG de dado con flecha usando `renderRerollIcon()`.
- Hover/focus de casillas:
  - `labelForSpace()` genera etiquetas de categoria/accion.
  - Cada casilla lleva `data-space-label`, `aria-label` y un `<title>`.
  - Tooltip visual propio: `#spaceTooltip`.
- Dado visual:
  - `renderDiceResult()` sustituye el texto del resultado en Estado.
  - `renderDiceFace()` pinta puntos fisicos del dado.
  - `lastAnimatedDiceKey` evita reanimar el mismo resultado en cada render.
- Fase de tirada:
  - `renderDiceRollOverlay()` muestra una tarjeta flotante sobre el tablero.
  - El boton principal `Tirar dado` vive en la tarjeta flotante, no en la barra superior.
  - El dado superior es solo indicador compacto, no dispara la tirada.
  - `submitRollFromOverlay()` bloquea dobles clicks.
  - `pendingDiceRollFeedback` muestra animacion y resultado final antes de elegir destino.
  - La duracion visible del resultado se toma de `board:diceResultDelayMs`.
- Lobby y final:
  - `renderLobbyOverlay()` muestra equipos conectados y boton `Empezar partida`.
  - `renderFinishedOverlay()` muestra el ganador.
- Preguntas:
  - `renderQuestionOverlay()` muestra la pregunta en una tarjeta flotante sobre el tablero.
  - En modo `judge`, primero solo aparece `Mostrar respuesta`; tras revelar, aparecen `Acierto` y `Fallo`.
  - En modo `auto`/online, las 4 opciones aparecen en la tarjeta.
- Resultado de respuesta:
  - `renderAnswerFeedbackOverlay()` muestra `Correcto` o `Fallado` tras responder.
  - La tarjeta indica si el equipo vuelve a tirar o si cambia el turno.
  - El usuario la cierra con `Continuar`.
  - El feedback es local al navegador que responde.
- Movimiento de fichas:
  - `moveWithTokenAnimation()` intercepta el click de destino cuando `board:animateTokens` esta activo.
  - `renderAnimatedToken()` dibuja la ficha temporal.
  - La pregunta aparece solo despues de completar la animacion y recibir el nuevo estado.

## API Principal

Rutas en `public/api.php`:

```text
POST /api.php/rooms
POST /api.php/rooms/{code}/join
GET  /api.php/rooms/{code}/state
POST /api.php/rooms/{code}/actions
GET  /api.php/admin/questions?admin_key=...
POST /api.php/admin/questions
```

Tambien deberia funcionar con `.htaccess` como `/api/...` si Apache permite rewrite.

Acciones de sala:

```json
{ "action": "start" }
{ "action": "roll", "playerId": 0 }
{ "action": "move", "playerId": 0, "destination": "r0_2" }
{ "action": "answer", "playerId": 0, "option": 2 }
{ "action": "answer", "playerId": 0, "correct": true }
```

## Preguntas

Formato CSV:

```csv
category,question,option_a,option_b,option_c,option_d,correct
geography,Capital de Francia,Paris,Lyon,Burdeos,Niza,0
```

`correct` es indice `0`, `1`, `2` o `3`.

Todas las preguntas deben tener 4 opciones, incluso si se juega en modo juez.

## Verificacion

Comandos usados y esperados:

```powershell
C:\xampp\php\php.exe tests\run.php
node --check public\assets\app.js
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

Resultado esperado actual de tests:

```text
44 passed, 0 failed
```

Verificacion visual reciente:

- App levantada en `http://127.0.0.1:4190/` y sala `http://127.0.0.1:4190/?room=WPY23T`.
- Se uso el navegador integrado para recargar la app y medir elementos.
- Verificado:
  - Home desktop/mobile sin overflow horizontal.
  - Vista `#localSetupView`, contador de equipos y boton `Volver`.
  - Crear sala online desde portada con nombre de equipo.
  - Crear partida local desde `#localSetupView`.
  - Distribucion clasica de categorias del tablero con 12 `roll_again`.
  - Selector de pack de colores en preferencias: `classic` y `alternative`.
  - Marcador principal sobre el tablero.
  - Columna derecha eliminada.
  - Dado compacto y boton de preferencias en la barra superior.
  - Preferencias en tarjeta flotante abierta con engranaje.
  - `#preferencesOverlay` dentro de `#gameView` para verse tambien en fullscreen.
  - Ruedas de quesitos del marcador: 2 equipos detectados, 6 porciones por rueda.
  - No quedan indicadores lineales `.scoreboard-wedge` como marcador principal.
  - Tablero normal sigue midiendo 720px en la vista verificada.
  - Fullscreen/fallback sin desborde: `frameFitsMount: true`.
  - `board-frame` cabe dentro de `board-mount` en fullscreen.
  - 6 iconos de quesito.
  - 12 iconos de reroll.
  - Tooltips de casilla.
  - Dado visual y tarjeta flotante para tirar.
  - Tarjeta flotante de pregunta en modo juez y modo opciones.
  - Tarjeta de resultado tras responder con boton `Continuar`.
  - Preferencias flotantes y toggles actuales.
  - Animacion de ficha antes de mostrar la pregunta.

## Puntos Delicados

- No meter `config.php` al repo.
- No meter `storage/dev.sqlite` al repo.
- `php-server*.log` esta ignorado.
- El tablero depende de que `GameEngine::boardDefinition()` y `public/assets/app.js` interpreten igual `track`, `spoke`, `index` y `visual.shape`.
- La distribucion logica de categorias vive en `GameEngine::boardDistribution()`. Si se cambia, actualizar tests de distribucion clasica.
- La geometria de radios se calcula desde `hubRadius`, `hubSideLength`, `spokeWidth`, `outerRingInner`, `spokeOuterVertexRadius` y `spokeLength`.
- No volver a usar alturas fijas tipo `36` para las casillas de radio si se quiere mantener igualdad entre vertices.
- La forma `curved_spoke_end` requiere que los vertices exteriores de la ultima casilla caigan en la curva interior del anillo, no que se anada una extension extra.
- El tablero actual esta considerado estable visualmente; si se cambia marcador o fullscreen, no tocar `renderBoard()`, la geometria SVG ni los metadatos `visual` salvo peticion explicita.
- Cambiar los packs de colores debe hacerse en `categoryColorPacks` de `public/assets/app.js`; no tocar la distribucion ni el grafo para cambios solo visuales.
- El fullscreen nativo puede fallar en el navegador integrado; `fullscreen-fallback` es parte esperada del comportamiento.
- En fullscreen, no dimensionar `board-frame` solo contra `100vh`; debe respetar la altura real de `board-mount`.
- `scoreboardBox` debe quedarse fuera de `boardMount` para no interferir con overlays del tablero.
- `preferencesOverlay` debe quedarse dentro de `gameView` para ser visible cuando `gameView` esta en fullscreen.
- Las ruedas del marcador son SVG inline generadas por `renderScoreboardWedgeWheel()`, no assets externos.
- Los iconos de quesito/reroll son SVG inline, no assets externos.
- El resultado/estado compacto del dado se muestra en `topDiceStatus` y la tirada principal se hace desde la tarjeta flotante; evitar reintroducir columna lateral o botones principales fuera del overlay.
- `pendingAnswerFeedback` es local y bloquea temporalmente otros overlays hasta pulsar `Continuar`.
- `pendingDiceRollFeedback` es local y retrasa visualmente el paso a elegir destino segun `board:diceResultDelayMs`.
- `pendingTokenAnimation` es local; si hay error de API tras la animacion, se limpia y se re-renderiza el estado real.
- La animacion de movimiento no debe disparar preguntas antes de recibir el estado posterior al `move`.
- Si cambian IDs de casilla, actualizar tests y frontend juntos.
- El navegador integrado de Codex esta funcionando para la app local. Si hay problemas, reconectar el browser runtime y usar el tab in-app abierto por el usuario.

## Proximos Trabajos Probables

- Mejorar o ajustar marcador/columna lateral si el usuario quiere mas presencia visual.
- Revisar UX movil del tablero.
- Mejorar administracion de preguntas.
- Preparar paquete de despliegue para IONOS.
- Anadir usuarios/login mas adelante, fuera de v1.
