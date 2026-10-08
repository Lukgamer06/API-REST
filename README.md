# API REST Tipo Facebook con Laravel

## Objetivo del proyecto

Desarrollar una API REST académica para crear y consultar publicaciones con imágenes, comentarios y "Me gusta", y permitir eliminar publicaciones junto con sus imágenes.

## Tecnologías

- PHP 8.2 o superior
- Laravel 11
- Docker y Docker Compose
- SQLite (se configurará en la fase 2)
- Postman, Git, GitHub y Visual Studio Code

> Laravel 11 requiere PHP 8.2 o superior. Por ello, este requisito efectivo prevalece sobre la indicación general de PHP 8.1+.
>
> Laravel 11 ya no está en su período de soporte y Composer informa avisos de seguridad para esta rama. Se conserva porque es un requisito académico; la instalación permite esos avisos explícitamente. No utilizar este proyecto en producción. Para una aplicación pública, se debe migrar a una versión de Laravel con soporte vigente.

## Configuración del proyecto

La aplicación corre dentro de Docker; no es necesario instalar PHP ni Composer en el equipo.

```sh
docker compose up --build -d
docker compose exec app php artisan --version
docker compose exec app php artisan test
```

La API quedará accesible en <http://localhost:8000>. Para consultar los logs:

```sh
docker compose logs -f app
```

La base de datos local es `database/database.sqlite`; Compose la crea si no existe. No se versiona el archivo SQLite: sus cambios se conservan en el workspace. Laravel utiliza SQLite como conexión predeterminada; sesiones y caché usan archivos para no requerir tablas adicionales al iniciar.

Para crear las tablas iniciales de Laravel:

```sh
docker compose exec app php artisan migrate
```

En Linux con SELinux, el montaje del código usa una etiqueta privada para permitir que Laravel escriba en el directorio compartido. El contenedor ejecuta los procesos con UID/GID `1000:1000` por defecto; se pueden ajustar con las variables `UID` y `GID`.

## Fases del taller

- [x] Crear la base del proyecto Laravel 11
- [x] Configurar SQLite
- [x] Crear migración de publicaciones
- [x] Crear migración de comentarios
- [ ] Crear migración de imágenes
- [ ] Configurar modelos y relaciones Eloquent
- [ ] Implementar controladores, validación y rutas REST
- [ ] Implementar almacenamiento y eliminación de imágenes
- [ ] Validar y exportar la colección de Postman
- [ ] Completar la documentación y evidencias

## Modelo de datos y diagrama relacional

Diagrama parcial (se completará al agregar imágenes):

```text
posts                         comments
-----                         --------
id (PK) <-------------------- post_id (FK, CASCADE)
title                         id (PK)
content                       author
likes_count                   content
created_at                    created_at
updated_at                    updated_at
```

## Migraciones, modelos y relaciones

Las migraciones `create_posts_table` y `create_comments_table` definen sus respectivos campos. La clave foránea `comments.post_id` referencia `posts.id` y elimina los comentarios asociados al borrar su publicación. Las relaciones Eloquent se implementarán en su fase.

## Endpoints

Pendiente de registrar y validar las rutas de la API.

## Evidencias de funcionamiento

### Fase 1: proyecto Laravel

Validado dentro del servicio Docker:

```text
Laravel Framework 11.57.0
PHP 8.3.35
Tests: 2 passed (2 assertions)
```

Las capturas de Postman se agregarán al implementar y probar los endpoints.

### Fase 3: migración de publicaciones

La tabla `posts` está creada en SQLite con `id`, `title`, `content`, `likes_count` (predeterminado a `0`) y marcas de tiempo. Comandos de verificación:

```sh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:table posts
```

### Fase 4: migración de comentarios

La tabla `comments` incluye `id`, `post_id`, `author`, `content` y marcas de tiempo. `post_id` referencia `posts.id` con borrado en cascada. Verifica el esquema con:

```sh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:table comments
```

### Fase 2: SQLite

Validado en Docker:

```text
Conexión predeterminada: sqlite
Driver PDO: pdo_sqlite
Migraciones base de Laravel: ejecutadas en database/database.sqlite
```

## Historial de commits

El historial solicitado se consulta con:

```sh
git log --oneline
```
