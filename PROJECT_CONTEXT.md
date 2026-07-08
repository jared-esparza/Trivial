# trivial - Contexto del Proyecto

Ultima actualizacion: 2026-07-08

## Objetivo

`trivial` es un juego web tipo Trivial con tablero circular, categorias, quesitos, casillas de volver a tirar, modo local y modo online por salas.

El nombre `trivial` es provisional. El usuario lo cambiara cuando tenga el nombre definitivo.

## Estado Git

- Rama principal: `main`
- Ultimo commit conocido al actualizar este archivo: `30c48c0 Mejora tablero fullscreen e indicadores visuales`
- Estado antes de actualizar este archivo: limpio contra `origin/main`.
- El commit `30c48c0` ya fue pusheado a `origin/main`.
- Este archivo quedara como cambio pendiente hasta que se haga commit.
- Commits recientes relevantes:
  - `30c48c0 Mejora tablero fullscreen e indicadores visuales`
  - `b959b54 Update PROJECT_CONTEXT for board geometry changes`
  - `477ebda Fix medidas de casillas de radios`
  - `c994a1f Añadir preferencias -> bordes blancos en tablero`
  - `5179c1d Mejora visual tablero y fix overlab bordes al seleccionar casillas`

## Stack y Despliegue

- PHP sin framework.
- MySQL/MariaDB para produccion en IONOS por FTP.
- SQLite local automatico si no existe `config.php`.
- JavaScript vanilla, sin build frontend.
- CSS propio.
- Sin Composer obligatorio.

Para local:

```powershell
php -S 127.0.0.1:4181 -t public
```

Luego abrir:

```text
http://127.0.0.1:4181
```

Si no existe `config.php`, la app usa:

- DB: `storage/dev.sqlite`
- clave admin: `admin-local`

Para produccion:

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
  assets/styles.css      Estilos UI y tablero.

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
- Seis categorias:
  - `geography`
  - `art`
  - `history`
  - `entertainment`
  - `science`
  - `sports`
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
- Los radios tienen categorias alternadas, no una sola categoria.
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

- Se muestra la pregunta sin respuesta.
- El juez pulsa `Mostrar respuesta`.
- Entonces aparecen respuesta correcta y botones `Acierto` / `Fallo`.
- Esto evita que el jugador vea la respuesta antes de contestar.

## Preferencias UI

En la vista de partida existe una caja `Preferencias` bajo `Estado`.

Preferencia actual:

- `Bordes blancos del tablero`.
- Se guarda en `localStorage` con clave `board:whiteBorders`.
- Si esta activada, el SVG recibe la clase `show-space-borders`.
- CSS relevante:
  - Por defecto: `.space-track-outer, .space-track-spoke { stroke: none; }`
  - Activado: `.board-svg.show-space-borders .space-track-outer, .board-svg.show-space-borders .space-track-spoke { stroke: #f8fafc; }`

Los bordes negros de seleccion/jugador se renderizan en una capa superior separada (`space-highlight`) para evitar que casillas vecinas los tapen.

## Interfaz Visual del Tablero

Mejoras actuales del tablero en `public/assets/app.js` y `public/assets/styles.css`:

- Boton `fullscreenBoardButton` en la cabecera del tablero.
- Pantalla completa jugable sobre `#gameView`:
  - Usa Fullscreen API cuando el navegador lo permite.
  - Usa clase `fullscreen-fallback` si el navegador bloquea fullscreen nativo.
  - Integra tablero, estado, preferencias, equipos y controles/pregunta.
- Casillas `wedge_*`:
  - Ya no muestran letra `Q`.
  - Renderizan un icono inline SVG minimalista de quesito con `renderWedgeIcon()`.
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

## API Principal

Rutas en `public/api.php`.

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
```

Resultado esperado actual:

```text
23 passed, 0 failed
```

Lint PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

Sintaxis JS:

```powershell
node --check public\assets\app.js
```

Verificacion visual reciente:

- App levantada por el usuario en `http://127.0.0.1:4181/?room=XPLDN8`.
- Se uso el navegador integrado para recargar la sala y medir elementos SVG.
- La sala `XPLDN8` quedo restaurada en el navegador integrado despues de las pruebas.
- Verificado:
  - 6 iconos de quesito.
  - 12 iconos de reroll.
  - Tooltips de casilla.
  - Fallback fullscreen.
  - Dado visual animado en una sala temporal.

## Puntos Delicados

- No meter `config.php` al repo.
- No meter `storage/dev.sqlite` al repo.
- `php-server*.log` esta ignorado.
- El tablero depende de que `GameEngine::boardDefinition()` y `public/assets/app.js` interpreten igual `track`, `spoke`, `index` y `visual.shape`.
- La geometria de radios se calcula desde:
  - `hubRadius`
  - `hubSideLength`
  - `spokeWidth`
  - `outerRingInner`
  - `spokeOuterVertexRadius`
  - `spokeLength`
- No volver a usar alturas fijas tipo `36` para las casillas de radio si se quiere mantener igualdad entre vertices.
- La forma `curved_spoke_end` requiere que los vertices exteriores de la ultima casilla caigan en la curva interior del anillo, no que se anada una extension extra.
- El fullscreen nativo puede fallar en el navegador integrado; `fullscreen-fallback` es parte esperada del comportamiento.
- Los iconos de quesito/reroll son SVG inline, no assets externos.
- El resultado del dado se muestra visualmente en Estado; evitar reintroducir texto duplicado `Dado: N` en controles.
- Si cambian IDs de casilla, actualizar tests y frontend juntos.
- El navegador integrado de Codex esta funcionando para la app local. Si hay problemas, reconectar el browser runtime y usar el tab in-app abierto por el usuario.

## Proximos Trabajos Probables

- Mejorar aun mas la apariencia del tablero si el usuario lo pide.
- Revisar UX movil del tablero.
- Mejorar administracion de preguntas.
- Preparar paquete de despliegue para IONOS.
- Anadir usuarios/login mas adelante, fuera de v1.
