# API REST Tipo Facebook con Laravel

## Objetivo del proyecto

Desarrollar una API REST académica para crear y consultar publicaciones con imágenes, comentarios y "Me gusta", y permitir eliminar publicaciones junto con sus imágenes.

## Tecnologías

- PHP 8.2 o superior
- Laravel 11
- Docker y Docker Compose
- SQLite
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
- [x] Crear migración de imágenes
- [x] Configurar modelos y relaciones Eloquent
- [x] Implementar controlador de publicaciones
- [x] Implementar controlador de comentarios
- [x] Registrar rutas API
- [x] Implementar almacenamiento y eliminación de imágenes
- [x] Validar endpoints con colección Postman
- [x] Exportar y documentar colección Postman
- [ ] Completar la documentación y evidencias

## Modelo de datos y diagrama relacional

Diagrama relacional:

```text
                    +--------------------+
                    |       posts        |
                    +--------------------+
                    | id (PK)            |
                    | title              |
                    | content            |
                    | likes_count        |
                    | created_at         |
                    | updated_at         |
                    +--------------------+
                       ^              ^
                       |              |
          post_id (FK, CASCADE)  post_id (FK, CASCADE)
                       |              |
          +------------------+  +---------------------+
          |     comments     |  |     post_images    |
          +------------------+  +---------------------+
          | id (PK)          |  | id (PK)            |
          | author           |  | image_path         |
          | content          |  | created_at         |
          | created_at       |  | updated_at         |
          | updated_at       |  +---------------------+
          +------------------+
```

## Migraciones, modelos y relaciones

Las migraciones `create_posts_table`, `create_comments_table` y `create_post_images_table` definen sus respectivos campos. `comments.post_id` y `post_images.post_id` referencian `posts.id` y eliminan sus registros asociados al borrar una publicación.

- `Post::comments()` y `Post::images()` son relaciones `hasMany`.
- `Comment::post()` y `PostImage::post()` son relaciones `belongsTo`.
- Los modelos permiten asignación masiva solo de sus campos de contenido y ruta; `likes_count` se convierte a entero y no puede cambiarse accidentalmente por asignación masiva.

Las pruebas de relaciones usan SQLite en memoria, sin cambiar la base local del desarrollador.

## Endpoints

| Método | Ruta | Descripción | Respuesta exitosa |
|---|---|---|---:|
| `POST` | `/api/posts` | Crea una publicación con título, contenido e imágenes opcionales (`images[]`, multipart/form-data) | `201` |
| `GET` | `/api/posts` | Lista publicaciones paginadas con comentarios e imágenes | `200` |
| `GET` | `/api/posts/{id}` | Consulta una publicación con sus relaciones | `200` |
| `POST` | `/api/posts/{id}/comments` | Agrega un comentario | `201` |
| `POST` | `/api/posts/{id}/like` | Incrementa el contador de Me gusta | `200` |
| `DELETE` | `/api/posts/{id}` | Elimina una publicación | `204` |

Los controladores de publicaciones y comentarios usan Form Requests. Las validaciones fallidas responden `422` y los recursos inexistentes `404`. La lista está paginada (15 registros por página). Se admiten hasta 10 imágenes por solicitud de creación, en formatos JPEG, PNG, GIF o WebP, con un máximo de 5 MB cada una. Laravel guarda los archivos en `storage/app/public/posts`; `image_path` contiene una ruta relativa que se puede servir bajo `/storage/`. Al eliminar una publicación se eliminan sus archivos y registros relacionados.

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

### Fase 5: migración de imágenes

La tabla `post_images` incluye `id`, `post_id`, `image_path` y marcas de tiempo. `post_id` referencia `posts.id` con borrado en cascada. Verifica el esquema con:

```sh
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:table post_images
```

### Fase 6: modelos y relaciones Eloquent

Las pruebas de integración verifican las relaciones `hasMany` y `belongsTo`, además del borrado en cascada de comentarios e imágenes al eliminar una publicación. SQLite en memoria mantiene estas pruebas aisladas de los datos locales:

```sh
docker compose exec app php artisan test --filter=PostRelationshipsTest
```

### Fase 7: controlador de publicaciones

Pruebas del controlador: creación (`201`), validación (`422`), listado y consulta con relaciones (`200`), eliminación (`204`) y publicación inexistente (`404`).

```sh
docker compose exec app php artisan test --filter=PostControllerTest
```

### Fase 8: controlador de comentarios

`StoreCommentRequest` valida `author` (requerido, máximo 255 caracteres) y `content` (requerido). Las pruebas verifican la creación (`201`), errores de validación (`422`) y publicación inexistente (`404`).

```sh
docker compose exec app php artisan test --filter=CommentControllerTest
```

### Fase 9: rutas API

Las pruebas ahora invocan las rutas reales registradas en `routes/api.php`; se verifica también el endpoint de Me gusta (`POST /api/posts/{id}/like`).

```sh
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan test
```

### Fase 10: almacenamiento y eliminación de imágenes

La prueba de `POST /api/posts` carga dos imágenes de prueba, verifica sus registros y archivos en el disco público. Otra prueba confirma que `DELETE /api/posts/{id}` elimina el archivo y sus datos asociados. Se rechazan formatos que no sean imagen.

```sh
docker compose exec app php artisan test --filter=PostControllerTest
```

### Fase 11: validación de endpoints con Postman

La colección se ejecutó contra `http://localhost:8000` con Newman, runner compatible con el formato de colección Postman. Resultado: 11 solicitudes y 13 aserciones, sin fallos. Se comprobaron publicaciones, comentarios, Me gusta, carga y acceso público a imágenes, validación `422` y eliminación `204`.

Capturas JSON de respuestas reales:

**Crear publicación — `201 Created`**

```json
{
  "id": 5,
  "title": "Post desde Postman",
  "content": "Publicación de prueba para el taller.",
  "likes_count": 0,
  "images": [],
  "comments": []
}
```

### Fase 12: colección Postman exportada

Archivos para importar desde Postman:

- [Colección API REST Tipo Facebook](<./postman/Facebook API.postman_collection.json>)
- [Entorno local](<./postman/Facebook API - Local.postman_environment.json>)
- [Imagen PNG de prueba](./postman/fixtures/sample.png)

En Postman, importa ambos archivos JSON y selecciona el entorno **Facebook API - Local**. Ejecuta todos los requests de la colección en orden. Si Postman solicita volver a seleccionar archivos adjuntos, elige `postman/fixtures/sample.png` en los dos campos `images[]`. La colección crea sus propios posts, los usa durante el flujo y los elimina al final.

Para ejecutar la misma colección desde terminal con Newman:

```sh
npx --yes newman run "postman/Facebook API.postman_collection.json" \
  --environment "postman/Facebook API - Local.postman_environment.json" \
  --working-dir .
```

Ejecución de validación del entregable: **11 requests, 13 assertions, 0 failures**. Para evidencias visuales adicionales, guarda capturas reales del Runner y de las respuestas JSON en `postman/evidence/` antes de entregar.

**Crear publicación con imágenes — `201 Created`**

```json
{
  "id": 6,
  "title": "Post con imágenes de prueba",
  "likes_count": 0,
  "images": [
    { "image_path": "posts/Y05IhDYJn0UAQSRAFwdvpnzG1btRBqg42zfMN2CS.png" },
    { "image_path": "posts/XzgnbZE114NCHtyoJFtkTHeL0eWVwE3gLFuZlvlk.png" }
  ],
  "comments": []
}
```

**Validación de publicación — `422 Unprocessable Content`**

```json
{
  "message": "The title field is required. (and 1 more error)",
  "errors": {
    "title": ["The title field is required."],
    "content": ["The content field is required."]
  }
}
```

### Fase 6: modelos y relaciones Eloquent

Las pruebas crean comentarios e imágenes desde una publicación, verifican que ambos hijos recuperan su publicación y confirman que SQLite elimina sus registros asociados al borrar el padre:

```sh
docker compose exec app php artisan test --filter=PostRelationshipsTest
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
