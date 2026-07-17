# Rueda Quiz - Contexto del proyecto

Ultima actualizacion: 2026-07-17

## Objetivo y stack

Juego web tipo Trivial con tablero radial, seis categorias, quesitos, dado, modo local y salas online.

- PHP 8.1+ sin framework ni Composer obligatorio.
- PDO con SQLite local y MySQL/MariaDB en produccion.
- JavaScript vanilla y CSS propio, sin build frontend.
- Despliegue previsto por FTP en IONOS, con `public/` como document root.

## Arranque rapido

```powershell
php -S 127.0.0.1:4181 -t public
```

Sin `config.php`:

- SQLite: `storage/dev.sqlite`.
- Correo: `storage/mail-outbox.log`.
- Retencion anonima: 30 dias.
- Las migraciones y el seed Clasico se ejecutan de forma idempotente al abrir la app.

Primer administrador:

```powershell
php bin/create-admin.php --email=admin@example.com --password=una-clave-segura
```

El nombre visible es opcional en CLI; si se omite se deriva del email:

```powershell
php bin/create-admin.php --email=admin@example.com --password=una-clave-segura --display-name="Admin principal"
```

Limpieza periodica:

```powershell
php bin/cleanup.php --days=30
```

## Estructura actual

```text
public/
  index.php                 Portada, configuracion y partida.
  api.php                   API de salas, acciones, estadisticas e historial.
  account.php               Registro, login, verificacion, reset y borrado.
  admin.php                 Administracion de usuarios y acceso a packs/esquemas.
  packs.php                 Espacio de trabajo de packs, esquemas e importacion.
  history.php               Historial e informes de la cuenta.
  assets/app.js             Cliente principal, tablero SVG y partida.
  assets/account.js         Cliente de cuenta.
  assets/packs.js           Workspace responsive, editor, colores e import/export.
  assets/history.js         Cliente de historial.
  assets/session-nav.js     Dropdown de perfil, drawer movil y cierre de sesion.

src/
  bootstrap.php             Config, dependencias, migraciones, seed y router modular.
  NavigationView.php        Cabecera PHP por rol, retornos seguros y migas de pan.
  Database.php              Conexion PDO.
  Database/MigrationRunner.php
  GameEngine.php            Grafo, movimiento, preguntas, turnos y victoria.
  RoomRepository.php        Persistencia y version optimista de salas.
  QuestionRepository.php    Preguntas globales y por revision/slot.
  Auth/                     Usuarios, sesiones, tokens, permisos y anonimizado.
  Game/                     Creacion de salas y tokens de participante.
  Packs/                    Packs, revisiones, import/export y seed.
  Stats/                    Eventos de respuesta, informes e historial.
  Http/                     Router y controladores modulares.
  Maintenance/              Retencion y purga anonima.

database/migrations/        Fuente de verdad incremental del esquema.
database/schema.mysql.sql   Esquema final de referencia para MySQL nuevo.
bin/create-admin.php        Alta/promocion segura del administrador.
bin/cleanup.php             Purga de salas anonimas expiradas.
tests/run.php               Suite PHP sin framework.
```

## Contratos funcionales importantes

### Tablero

- La geometria, coordenadas y grafo existentes estan congelados salvo decision explicita.
- Hay 73 casillas: centro, seis radios de cinco casillas y anillo exterior.
- Los identificadores semanticos clasicos (`history`, `sports`, etc.) siguen siendo los slots internos estables del motor.
- Un pack cambia nombre/color/contenido de los seis slots; no cambia la topologia.
- `public/assets/app.js` conserva el render del tablero para no fragmentar su contrato visual.

### Cuentas y administracion

- Invitados pueden configurar partida local, crear sala online y unirse a una sala sin cuenta.
- Registro y login usan sesiones en cookie `HttpOnly`, CSRF en mutaciones y limitacion persistente de intentos.
- Registrar una cuenta inicia automaticamente una sesion pendiente de verificacion.
- `display_name` es obligatorio en registro, editable desde la cuenta y visible en la cabecera compartida.
- Verificacion y recuperacion usan tokens de un solo uso con caducidad.
- Las funciones de packs e historial requieren cuenta verificada.
- La cabecera se renderiza en PHP antes de enviar la pagina; `session-nav.js` no consulta `/auth/me` ni sustituye enlaces al cargar.
- Invitado: `Jugar` y CTA `Entrar`. Usuario pendiente: `Jugar` y perfil con aviso. Usuario verificado: `Jugar`, `Packs`, `Historial` y perfil. Administracion vive dentro del perfil del rol `admin`.
- En movil la cabecera mide 66 px y abre un drawer accesible; Escape, clic exterior y cierre explicito restauran el foco y desbloquean el scroll.
- Login y registro vuelven a un destino local validado (`./`, Packs, Historial, Admin o sala); URLs externas, rutas ascendentes y barras invertidas caen en `./`.
- Packs, Historial, Cuenta y Administracion muestran migas de pan. Los detalles de pack e historial actualizan el ultimo nivel dinamicamente.
- `.admin-shell` usa filas `max-content` y `align-content: start`: las migas miden 19 px y quedan a 16 px de la primera caja tanto en escritorio como en movil.
- Administracion usa rol `admin`; no existe `admin_key` compartida.
- El ultimo administrador activo no se puede degradar o desactivar.
- Borrar cuenta revoca sesiones, desactiva packs y esquemas privados, elimina datos personales y desasocia el usuario, pero conserva historial compartido anonimizado.

### Packs y revisiones

- Un pack tiene exactamente seis slots `0..5`.
- Los packs pueden ser `system` o `user`; los privados pertenecen a una cuenta.
- Solo revisiones completas pueden activarse.
- Una revision activa es inmutable; editar crea un borrador nuevo.
- Una sala guarda `pack_id`, `pack_revision_id` y `pack_snapshot_json`; partidas existentes no cambian si se edita el pack.
- El administrador gestiona packs y esquemas de colores del sistema; cada usuario verificado puede gestionar esquemas privados propios.
- `packs.php` separa Packs, Esquemas de color e Importar. En escritorio mantiene lista y editor conectados; en movil usa navegacion lista-detalle con retorno explicito.
- El usuario normal solo ve en el workspace sus packs gestionables. El administrador alterna entre sus packs privados y los packs del sistema; no se listan packs privados de otros usuarios.
- El editor divide Resumen, Categorias y Preguntas. Las claves internas de categoria no se exponen, el guardado de categorias es explicito y avisa sobre cambios pendientes.
- Las preguntas de un borrador se pueden crear, editar y eliminar individualmente. Las revisiones activas permanecen inmutables y requieren crear un borrador de edicion.
- Activar una revision exige seis categorias y al menos una pregunta en cada una; la interfaz muestra el progreso antes de habilitar la accion.
- Un pack guarda seis colores predeterminados. Aplicar un esquema copia sus colores y no crea una dependencia dinamica.
- `Clasico` es el esquema inicial de packs nuevos y no se puede renombrar ni eliminar.
- Cambiar solo nombres o colores conserva las preguntas; cambiar claves de categoria reinicia las preguntas del borrador.
- JSON y CSV importados nunca deciden propietario o visibilidad; crean un borrador privado nuevo.

### Salas y concurrencia

- Crear sala acepta `packId` y `colorSchemeId` opcionales; sin esquema usa los colores del pack.
- Los colores finales se congelan en el snapshot de la sala y son iguales para todos los participantes.
- Cada participante online recibe una sola vez un token opaco; en base de datos solo se guarda su hash.
- Las acciones envian `X-Participant-Token` y `expectedVersion`.
- Una version obsoleta devuelve conflicto y no debe crear evento de respuesta.
- El modo local usa un token controlador capaz de actuar por todos sus slots.

### Estadisticas y retencion

- Cada respuesta confirmada produce un evento dentro de la misma transaccion que el estado.
- Informes: totales, aciertos/errores, porcentaje, categorias, racha maxima, ganador y duracion.
- Endpoints principales: `/rooms/{code}/statistics`, `/me/games`, `/me/games/{code}`.
- La limpieza solo borra salas finalizadas antiguas sin creador ni participantes asociados a usuarios.

## API relevante

```text
POST /api.php/auth/register
POST /api.php/auth/verify
POST /api.php/auth/login
GET  /api.php/auth/me
POST /api.php/auth/profile
POST /api.php/auth/logout
POST /api.php/auth/password/forgot
POST /api.php/auth/password/reset
POST /api.php/auth/delete

GET  /api.php/admin/users
POST /api.php/admin/users/update

GET  /api.php/packs
GET  /api.php/packs/colors
POST /api.php/packs/create|import|categories|questions|edit|activate|delete
POST /api.php/packs/import/preview
POST /api.php/packs/questions/update|delete
POST /api.php/packs/colors/create|update|delete

POST /api.php/rooms
POST /api.php/rooms/{code}/join
GET  /api.php/rooms/{code}/state
POST /api.php/rooms/{code}/actions
GET  /api.php/rooms/{code}/statistics

GET  /api.php/me/games
GET  /api.php/me/games/{code}
```

## Verificacion

```powershell
php tests/run.php
node --check public/assets/app.js
node --check public/assets/account.js
node --check public/assets/packs.js
node --check public/assets/history.js
node --check public/assets/session-nav.js
```

Antes de tocar UI de partida, preservar las regresiones de geometria, fullscreen, overlays y marcador. Antes de tocar persistencia, probar migracion nueva sobre base vacia y segunda ejecucion idempotente.

Referencia previa verificada el 2026-07-16: `85 passed, 0 failed`, lint PHP completo, checks Node y `git diff --check` limpio. La nueva UX de packs eleva la suite a 88 pruebas; ejecutar de nuevo toda la matriz antes de cerrar sus cambios locales.

## Estado reciente

- Rama de trabajo actual: `main`.
- Cambios locales pendientes de revision: rediseño completo de `packs.php` como workspace responsive, CRUD de preguntas, previsualizacion de importaciones y biblioteca compacta de esquemas.
- El tablero, `public/assets/app.js`, su geometria, overlays, marcador y fullscreen no forman parte del cambio de packs.
- `dc102d2`: jerarquia unica de esquemas, biblioteca personal, colores de sala congelados y preferencias sin overrides locales.
- `e4a291c`: display name, cuenta, navegacion compartida y mejoras de packs/admin.
- `TODO_LIST.md` y `video/` son archivos locales no versionados y no forman parte de estos commits.
