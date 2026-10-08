# API REST Tipo Facebook con Laravel

API REST académica para crear publicaciones con múltiples imágenes, agregar comentarios, dar Me gusta, consultar publicaciones y borrar una publicación con sus imágenes y comentarios.

## Objetivo

Practicar el desarrollo de una API REST con Laravel 11, arquitectura MVC, Eloquent ORM, validación con Form Requests, almacenamiento de archivos, pruebas y consumo desde Postman.

## Tecnologías

- PHP 8.3 en Docker (Laravel 11 requiere PHP 8.2 o superior; no es compatible con PHP 8.1).
- Laravel 11.57
- SQLite
- Docker y Docker Compose
- Postman y Newman
- Git, GitHub y Visual Studio Code

> **Aviso de soporte:** Laravel 11 ya no recibe soporte de seguridad y Composer publica avisos para esta versión. Se conserva por ser requisito académico. Esta aplicación es únicamente para uso local en el taller; no desplegar en producción. Para un sistema público, actualizar primero a una versión mantenida de Laravel.

## Configuración

Docker ejecuta PHP y Composer; no es necesario instalarlos en el host.

```sh
docker compose up --build -d
docker compose exec app php artisan --version
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Base URL local: <http://localhost:8000>.

SQLite se guarda en `database/database.sqlite`. Compose crea el archivo si no existe y Laravel usa esa ruta de forma predeterminada. El archivo de base local se ignora en Git; se conservan los datos mientras no se borre del workspace. Las pruebas PHPUnit usan SQLite en memoria, por lo que no modifican esa base.

Para inspeccionar el estado:

```sh
docker compose exec app php artisan config:show database.default
docker compose exec app php artisan migrate:status
docker compose logs -f app
```

El servicio configura sesiones y caché en archivos y la cola en modo síncrono para no requerir tablas adicionales. En Linux con SELinux, el montaje del código utiliza una etiqueta privada. El contenedor usa UID/GID `1000:1000` por defecto; se puede ajustar con las variables `UID` y `GID`.

## Modelo de datos

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
          |     comments     |  |     post_images     |
          +------------------+  +---------------------+
          | id (PK)          |  | id (PK)            |
          | author           |  | image_path         |
          | content          |  | created_at         |
          | created_at       |  | updated_at         |
          | updated_at       |  +---------------------+
          +------------------+
```

## Migraciones

| Tabla | Columnas de dominio | Restricciones |
|---|---|---|
| `posts` | `id`, `title`, `content`, `likes_count`, timestamps | `likes_count` predeterminado a `0` |
| `comments` | `id`, `post_id`, `author`, `content`, timestamps | `post_id` referencia `posts.id`, borrado en cascada |
| `post_images` | `id`, `post_id`, `image_path`, timestamps | `post_id` referencia `posts.id`, borrado en cascada |

Las migraciones iniciales de Laravel también crean sus tablas de usuarios, caché y tareas.

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:table posts
docker compose exec app php artisan db:table comments
docker compose exec app php artisan db:table post_images
```

## Modelos y relaciones

- `Post::comments()` → `hasMany(Comment::class)`
- `Post::images()` → `hasMany(PostImage::class)`
- `Comment::post()` → `belongsTo(Post::class)`
- `PostImage::post()` → `belongsTo(Post::class)`

Los modelos declaran campos asignables explícitamente. `likes_count` se convierte a entero y no forma parte de los atributos asignables masivamente. La base de datos aplica borrado en cascada de comentarios e imágenes.

## Endpoints

Todas las rutas están bajo `/api`. Las respuestas de validación se entregan como JSON al incluir `Accept: application/json`.

| Método | Ruta | Descripción | Respuesta exitosa |
|---|---|---|---:|
| `POST` | `/api/posts` | Crear publicación; acepta título, contenido e imágenes opcionales | `201` |
| `GET` | `/api/posts` | Lista paginada con comentarios e imágenes | `200` |
| `GET` | `/api/posts/{id}` | Consultar una publicación y sus relaciones | `200` |
| `POST` | `/api/posts/{id}/comments` | Agregar comentario | `201` |
| `POST` | `/api/posts/{id}/like` | Incrementar contador de Me gusta | `200` |
| `DELETE` | `/api/posts/{id}` | Eliminar publicación, comentarios, imágenes y archivos | `204` |

Errores comunes: `404` si no existe la publicación; `422` si falla la validación.

### Crear una publicación

Envía JSON:

```json
{
  "title": "Mi publicación",
  "content": "Contenido de ejemplo."
}
```

Para adjuntar imágenes, envía `multipart/form-data` con los campos `title`, `content` y uno o más archivos bajo la misma clave `images[]`. Se permiten hasta 10 archivos por publicación, JPEG, PNG, GIF o WebP, máximo 5 MB cada uno.

Los archivos se guardan en `storage/app/public/posts`. La API devuelve `image_path` como ruta relativa; su URL pública es `{{baseUrl}}/storage/{{image_path}}`. Compose crea el enlace `public/storage` al iniciar el contenedor. Al eliminar la publicación, el controlador borra sus archivos y la base de datos elimina en cascada sus filas relacionadas.

## Postman

Importa en Postman la colección [API REST Tipo Facebook](<./postman/Facebook API.postman_collection.json>) y el entorno [Facebook API - Local](<./postman/Facebook API - Local.postman_environment.json>). Selecciona ese entorno; por defecto apunta a `http://localhost:8000`.

La colección incluye el flujo de creación, listado, consulta, comentario, Me gusta, carga y consulta pública de imágenes, validaciones `422` y eliminación. El fixture [sample.png](./postman/fixtures/sample.png) se adjunta dos veces en `images[]`. Si Postman solicita asignar los archivos al importar, selecciona ese fixture en ambos campos. Ejecuta las solicitudes en orden: las pruebas guardan IDs y rutas en las variables del entorno y limpian los posts creados al final.

La misma colección puede ejecutarse desde terminal con Newman:

```sh
npx --yes newman run "postman/Facebook API.postman_collection.json" \
  --environment "postman/Facebook API - Local.postman_environment.json" \
  --working-dir .
```

## Pruebas y evidencias

Ejecutar la suite:

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

Última suite automatizada registrada: **15 pruebas, 56 aserciones**, todas aprobadas. La colección de Postman se ejecutó con Newman: **11 solicitudes, 13 aserciones, 0 fallos**.

### Respuestas JSON verificadas

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

**Validación — `422 Unprocessable Content`**

```json
{
  "message": "The title field is required. (and 1 more error)",
  "errors": {
    "title": ["The title field is required."],
    "content": ["The content field is required."]
  }
}
```

Para evidencias visuales de Postman Desktop, guarda capturas del Runner y de las respuestas en `postman/evidence/`. Los archivos para importar, el fixture y los resultados del runner Newman están versionados; Newman no reemplaza una captura de la interfaz de Postman.

## Historial de commits

Commits del taller, en orden:

```text
feat: crear proyecto laravel
feat: configurar sqlite
feat: crear migraciones de posts
feat: crear migraciones de comentarios
feat: crear migraciones de imagenes
feat: configurar relaciones eloquent
feat: implementar post controller
feat: implementar comment controller
feat: registrar rutas api
feat: implementar almacenamiento de imagenes
test: validar endpoints en postman
test: exportar coleccion postman
docs: crear readme del proyecto
fix: corregir validaciones y errores finales
```

Los commits se consultan con:

```sh
git log --oneline
```
