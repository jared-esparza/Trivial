# trivial

Juego web de preguntas por categorias inspirado en mecanicas de tablero circular, quesitos y tiradas de dado.

## Ejecucion local

Requisitos: PHP 8.1+ con PDO.

```powershell
php -S 127.0.0.1:4178 -t public
```

Abre `http://127.0.0.1:4178`. Si no existe `config.php`, la app usa SQLite local en `storage/dev.sqlite` y clave admin `admin-local`.

Para cargar preguntas demo:

1. Entra en `http://127.0.0.1:4178/admin.php`.
2. Usa la clave `admin-local`.
3. Pega el contenido de `data/questions-demo.csv`.
4. Marca "Reemplazar todas las preguntas existentes" e importa.

## Despliegue en IONOS con MySQL

1. Crea una base de datos MySQL/MariaDB en IONOS.
2. Copia `config.example.php` como `config.php`.
3. Cambia `admin_key` y los datos de conexion MySQL.
4. Sube el proyecto por FTP.
5. Configura el document root del dominio a la carpeta `public` si tu panel lo permite.

Si no puedes apuntar el document root a `public`, sube el contenido de `public` a la carpeta publica del hosting y deja `src`, `database`, `data`, `storage` y `config.php` un nivel por encima siempre que el hosting lo permita.

La app crea las tablas automaticamente al primer uso. Tambien puedes ejecutar manualmente `database/schema.mysql.sql`.

## Categorias

- `geography`: Geografia
- `art`: Arte y literatura
- `history`: Historia
- `entertainment`: Entretenimiento
- `science`: Ciencia y naturaleza
- `sports`: Deportes y ocio

## Formato CSV

```csv
category,question,option_a,option_b,option_c,option_d,correct
geography,Capital de Francia,Paris,Lyon,Burdeos,Niza,0
```

`correct` es el indice de la opcion correcta: `0`, `1`, `2` o `3`.
