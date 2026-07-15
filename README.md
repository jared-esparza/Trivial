# Rueda Quiz

Juego web de preguntas con tablero circular, seis categorias, quesitos, modo local y salas online. El proyecto usa PHP, PDO, JavaScript y CSS sin framework ni proceso de build.

## Ejecucion local

Requisitos: PHP 8.1+ con PDO SQLite.

```powershell
php -S 127.0.0.1:4181 -t public
```

Abre `http://127.0.0.1:4181`. Sin `config.php`, se usa `storage/dev.sqlite`, correo local en `storage/mail-outbox.log`, migraciones automaticas y el pack Clasico de demostracion.

Para crear el primer administrador:

```powershell
php bin/create-admin.php --email=admin@example.com --password=una-clave-segura
```

El administrador inicia sesion en `account.php` y accede a `admin.php`. Ya no existe una clave de administracion compartida.

## Cuentas, packs e historial

- Jugar como invitado sigue siendo posible.
- Las cuentas verificadas pueden crear packs privados, importar/exportar CSV o JSON y consultar su historial.
- Los administradores gestionan usuarios, packs y esquemas de colores del sistema.
- Los usuarios verificados pueden guardar esquemas privados y reutilizarlos en sus packs y salas.
- El creador fija los colores de la sala; todos los participantes ven el mismo snapshot y las preferencias personales no lo sustituyen.
- Cada partida conserva la revision y las categorias del pack con las que se creo.
- Las acciones online requieren un token de participante y una version esperada para evitar actuaciones cruzadas o sobrescrituras.
- Al eliminar una cuenta se anonimizan sus datos personales sin destruir historiales compartidos.

La limpieza de partidas completamente anonimas y finalizadas usa la retencion configurada (30 dias por defecto):

```powershell
php bin/cleanup.php
php bin/cleanup.php --days=45
```

Programa este comando como tarea periodica en produccion.

## Configuracion y despliegue MySQL

1. Crea una base MySQL/MariaDB.
2. Copia `config.example.php` como `config.php`.
3. Ajusta `base_url`, correo, retencion y credenciales de base de datos.
4. Sube el proyecto y apunta el document root a `public/`.
5. Abre la aplicacion una vez para ejecutar las migraciones y el seed idempotente.
6. Crea el administrador con `bin/create-admin.php`.

La fuente de verdad incremental es `database/migrations`. `database/schema.mysql.sql` refleja el esquema final para inspeccion o instalaciones manuales nuevas; una instalacion existente debe dejar que `MigrationRunner` aplique solo las versiones pendientes.

Transportes de correo:

- `native`: usa `mail()` de PHP con la direccion `from` configurada.
- `local`: escribe los enlaces de verificacion y recuperacion fuera de `public/`, en el fichero `outbox`.

## Packs completos

El JSON portable usa `format_version: 1` y contiene nombre, seis categorias y preguntas. El CSV completo utiliza:

```csv
pack_name,category_slot,category_key,category_name,category_color,question,option_a,option_b,option_c,option_d,correct
Clasico,0,history,Historia,#f2c94c,Fecha clave,1492,1789,1914,2001,0
```

`category_slot` va de `0` a `5`; `correct` es el indice `0..3`. Una importacion siempre crea un nuevo borrador privado y no acepta propietario ni visibilidad desde el archivo.

## Verificacion

```powershell
php tests/run.php
node --check public/assets/app.js
node --check public/assets/account.js
node --check public/assets/packs.js
node --check public/assets/history.js
```

El arranque aplica de forma idempotente las migraciones de `database/migrations` y siembra el contenido Clasico cuando falta.
