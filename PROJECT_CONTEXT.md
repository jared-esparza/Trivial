# trivial - Contexto del Proyecto

Ultima actualizacion: 2026-07-09

## Objetivo

`trivial` es un juego web tipo Trivial con tablero circular, categorias, quesitos, casillas de volver a tirar, modo local y modo online por salas.

El nombre `trivial` es provisional. El usuario lo cambiara cuando tenga el nombre definitivo.

## Estado Git

- Rama principal: `main`.
- Ultimo commit conocido al actualizar este archivo: `1c8f7c1 Marcadores mejorados e integrados`.
- Estado antes de actualizar este archivo: limpio contra `origin/main`.
- Este archivo quedara como cambio pendiente hasta que se haga commit.
- Commits recientes relevantes:
  - `1c8f7c1 Marcadores mejorados e integrados`
  - `d4e754b Anadir animacion dado y preferencias desplegables`
  - `5a84d46 Update project context for recent UI changes`
  - `763e258 Anadir animacion de movimiento de ficha y tarjeta flotante para lanzar dado`
  - `4e4a81c Mejora tarjetas preguntas con indicador de acierto o error, mejora de iconos quesitos, anadido efecto latido a la seleccion de casillas como preferencia de sala`
  - `3b78bdf Mostrar preguntas en tarjetas flotantes, modificar aspecto de quesitos`

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
tablero.jpg              Imagen de referencia inicial del tablero.
```

## Reglas Implementadas

- 2 a 6 equipos.
- Seis categorias: `geography`, `art`, `history`, `entertainment`, `science`, `sports`.
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
- Los radios tienen categorias alternadas.
- Las casillas `roll_again` estan integradas en el anillo exterior y se pintan en gris.
- Casilla `roll_again`: no hay pregunta, el mismo equipo vuelve a tirar.
- Casilla `wedge_*`: si acierta, gana el quesito de esa categoria.
- Si falla, pasa el turno.
- Si acierta, repite turno.
- Para ganar debe tener todos los quesitos, llegar al centro y acertar pregunta final aleatoria.

## Modos de Juego

### Online

- Crear sala online con codigo.
- Unirse con codigo.
- Validacion automatica con 4 opciones.
- Polling HTTP cada 2.5 segundos desde `public/assets/app.js`.

### Local

Al crear partida local se elige:

- `auto`: 4 opciones y validacion automatica.
- `judge`: modo clasico con juez.

En modo `judge`:

- La tarjeta flotante muestra la pregunta sin respuesta.
- El juez pulsa `Mostrar respuesta`.
- Entonces aparecen respuesta correcta y botones `Acierto` / `Fallo`.
- Tras marcar acierto o fallo aparece una tarjeta de resultado con boton `Continuar`.
- Esto evita que el jugador vea la respuesta antes de contestar y confirma claramente el resultado.

## Preferencias UI

La caja `Preferencias` esta en la columna lateral, bajo `Estado`, y esta plegada por defecto.

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
- `playersBox` en la columna lateral ya no duplica tarjetas completas:
  - muestra equipo activo,
  - numero de equipos,
  - progreso global de quesitos.

## Interfaz Visual del Tablero

Mejoras actuales en `public/assets/app.js` y `public/assets/styles.css`:

- Boton `fullscreenBoardButton` en la cabecera del tablero.
- Pantalla completa jugable sobre `#gameView`:
  - Usa Fullscreen API cuando el navegador lo permite.
  - Usa clase `fullscreen-fallback` si el navegador bloquea fullscreen nativo.
  - Integra tablero, marcador, estado, preferencias, equipos y controles/pregunta.
  - En fullscreen el marcador se compacta.
  - `game-board-panel` usa `grid-template-rows: auto auto minmax(0, 1fr)`.
  - `board-mount` usa `min-height: 0`, `overflow: hidden` y `container-type: size`.
  - En fullscreen, `board-frame` usa `width: min(100%, 100cqh)` para respetar la altura real disponible.
  - En movil fullscreen se usa `grid-template-rows: minmax(0, 1fr) auto`.
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
  - El boton principal `Tirar dado` vive en la tarjeta flotante, no en la columna lateral.
  - `submitRollFromOverlay()` bloquea dobles clicks.
  - `pendingDiceRollFeedback` muestra animacion y resultado final antes de elegir destino.
  - La duracion visible del resultado se toma de `board:diceResultDelayMs`.
- Preguntas:
  - `renderQuestionOverlay()` muestra la pregunta en una tarjeta flotante sobre el tablero.
  - En modo `judge`, primero solo aparece `Mostrar respuesta`; tras revelar, aparecen `Acierto` y `Fallo`.
  - En modo `auto`/online, las 4 opciones aparecen en la tarjeta.
  - El panel lateral solo muestra un resumen compacto de la pregunta en curso.
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
36 passed, 0 failed
```

Verificacion visual reciente:

- App levantada por el usuario en `http://127.0.0.1:4181/?room=ABWCHL`.
- Se uso el navegador integrado para recargar la sala y medir elementos.
- Verificado:
  - Marcador principal sobre el tablero.
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
  - Preferencias plegables y toggles actuales.
  - Animacion de ficha antes de mostrar la pregunta.

## Puntos Delicados

- No meter `config.php` al repo.
- No meter `storage/dev.sqlite` al repo.
- `php-server*.log` esta ignorado.
- El tablero depende de que `GameEngine::boardDefinition()` y `public/assets/app.js` interpreten igual `track`, `spoke`, `index` y `visual.shape`.
- La geometria de radios se calcula desde `hubRadius`, `hubSideLength`, `spokeWidth`, `outerRingInner`, `spokeOuterVertexRadius` y `spokeLength`.
- No volver a usar alturas fijas tipo `36` para las casillas de radio si se quiere mantener igualdad entre vertices.
- La forma `curved_spoke_end` requiere que los vertices exteriores de la ultima casilla caigan en la curva interior del anillo, no que se anada una extension extra.
- El tablero actual esta considerado estable visualmente; si se cambia marcador o fullscreen, no tocar `renderBoard()`, `GameEngine::boardDefinition()` ni la geometria SVG salvo peticion explicita.
- El fullscreen nativo puede fallar en el navegador integrado; `fullscreen-fallback` es parte esperada del comportamiento.
- En fullscreen, no dimensionar `board-frame` solo contra `100vh`; debe respetar la altura real de `board-mount`.
- `scoreboardBox` debe quedarse fuera de `boardMount` para no interferir con overlays del tablero.
- Las ruedas del marcador son SVG inline generadas por `renderScoreboardWedgeWheel()`, no assets externos.
- Los iconos de quesito/reroll son SVG inline, no assets externos.
- El resultado del dado se muestra visualmente en Estado y la tirada principal se hace desde la tarjeta flotante; evitar reintroducir texto duplicado `Dado: N` o botones principales en controles laterales.
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
