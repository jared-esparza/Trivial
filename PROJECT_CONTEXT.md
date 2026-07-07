# trivial - Contexto del Proyecto

Ultima actualizacion: 2026-07-07

## Objetivo

`trivial` es un juego web tipo Trivial con tablero circular, categorias, quesitos, casillas de volver a tirar, modo local y modo online por salas.

El nombre `trivial` es provisional. El usuario lo cambiara cuando tenga el nombre definitivo.

## Estado Git

- Rama principal: `main`
- Commit base actual: `37302cb feat: initial trivial game`
- El ultimo estado conocido estaba limpio antes de crear este archivo.
- Este archivo puede quedar como cambio pendiente si no se ha commiteado despues.

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
  assets/app.js          Cliente JS: tablero SVG, turnos, preguntas, polling.
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
- Los radios tienen categorias alternadas, no una sola categoria.
- Las casillas `roll_again` estan integradas en el anillo exterior.
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
php tests\run.php
```

Resultado esperado actual:

```text
13 passed, 0 failed
```

Lint PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Sintaxis JS:

```powershell
node --check public\assets\app.js
```

## Puntos Delicados

- No meter `config.php` al repo.
- No meter `storage/dev.sqlite` al repo.
- `php-server*.log` esta ignorado.
- El tablero depende de que `GameEngine::boardDefinition()` y `public/assets/app.js` interpreten igual `track`, `spoke`, `index` y `visual.shape`.
- Si cambian IDs de casilla, actualizar tests y frontend juntos.
- El navegador integrado de Codex tuvo problemas accediendo a `127.0.0.1`; las pruebas HTTP con PowerShell funcionaron.

## Proximos Trabajos Probables

- Mejorar aun mas la apariencia del tablero si el usuario lo pide.
- Revisar UX movil del tablero.
- Mejorar administracion de preguntas.
- Preparar paquete de despliegue para IONOS.
- Anadir usuarios/login mas adelante, fuera de v1.
