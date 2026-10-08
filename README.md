# API REST Tipo Facebook con Laravel

API REST académica tipo Facebook para crear publicaciones con múltiples imágenes, comentarios y Me gusta. La Fase 1 avanzada incorpora autenticación por token con Laravel Sanctum y atribución de publicaciones y comentarios a su usuario.

## Objetivo

Practicar el desarrollo de una API REST con Laravel 11, arquitectura MVC, Eloquent ORM, validación con Form Requests, almacenamiento de archivos, pruebas y consumo desde Postman.

## Tecnologías

- PHP 8.3 en Docker (Laravel 11 requiere PHP 8.2 o superior; no es compatible con PHP 8.1).
- Laravel 11.57
- Laravel Sanctum 4
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
users 1 ─────────── * posts 1 ─────────── * post_images
  │                     │
  │                     └──────────────── * comments
  └─────────────────────────────────────── * comments
```

Las nuevas columnas `posts.user_id` y `comments.user_id` son claves foráneas anulables para conservar registros que existían antes de activar la autenticación; las escrituras nuevas siempre asignan el usuario autenticado. Si se elimina un usuario, estas referencias pasan a `NULL`.

## Migraciones

| Tabla | Columnas de dominio | Restricciones |
|---|---|---|
| `posts` | `id`, `user_id`, `title`, `content`, `likes_count`, timestamps | `user_id` referencia `users.id`; `likes_count` predeterminado a `0` |
| `comments` | `id`, `post_id`, `user_id`, `author`, `content`, timestamps | Claves foráneas a `posts.id` y `users.id`; `post_id` se borra en cascada |
| `post_images` | `id`, `post_id`, `image_path`, timestamps | `post_id` referencia `posts.id`, borrado en cascada |
| `personal_access_tokens` | Tokens de acceso de Sanctum | Los tokens quedan asociados al usuario y pueden revocarse |

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
- `Post::user()` → `belongsTo(User::class)`
- `Comment::post()` → `belongsTo(Post::class)`
- `Comment::user()` → `belongsTo(User::class)`
- `PostImage::post()` → `belongsTo(Post::class)`
- `User::posts()` y `User::comments()` → `hasMany`

Los modelos declaran campos asignables explícitamente. `likes_count` se convierte a entero y no forma parte de los atributos asignables masivamente. La base de datos aplica borrado en cascada de comentarios e imágenes.

## Fase avanzada 1: autenticación con Sanctum

Se usa autenticación **Bearer Token** para clientes API. El registro y login son públicos; logout y las operaciones que modifican recursos requieren un token válido. La lectura de publicaciones continúa siendo pública. Solo el dueño puede eliminar su publicación.

| Método | Ruta | Acceso |
|---|---|---|
| `POST` | `/api/register` | Público; registra y devuelve token (`201`) |
| `POST` | `/api/login` | Público; inicia sesión y devuelve token (`200`) |
| `POST` | `/api/logout` | Bearer Token; revoca el token actual (`200`) |

Registro — `POST /api/register`:

```json
{
  "name": "Ada Lovelace",
  "email": "ada@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

La respuesta contiene `token`, `token_type: "Bearer"` y el usuario sin campos sensibles. Las reglas Form Request validan nombre, email único, contraseña de al menos 8 caracteres y su confirmación. El login devuelve `401` cuando las credenciales no coinciden; errores de formato devuelven `422`.

Respuesta — `201 Created` (el valor de token es generado):

```json
{
  "token": "<token-generado>",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com"
  }
}
```

Para Postman:

1. Envía `POST {{baseUrl}}/api/register` o `POST {{baseUrl}}/api/login` con `Content-Type: application/json`.
2. Copia el valor de `token` de la respuesta.
3. En las solicitudes protegidas selecciona **Authorization → Bearer Token** y pega el token; alternativamente envía `Authorization: Bearer <token>`.
4. Usa `POST /api/posts`, `POST /api/posts/{id}/comments`, `POST /api/posts/{id}/like`, `DELETE /api/posts/{id}` o `POST /api/logout`.
5. Sin token válido una mutación devuelve `401`; un usuario distinto del dueño que intente borrar el post recibe `403`.

El usuario de la sesión es la fuente de identidad para `posts.user_id`, `comments.user_id` y el `author` de cada comentario; no se acepta que el cliente asigne otro propietario.

## Fase 2: paginación

Se aplica `paginate(10)` en `GET /api/posts` para mantener respuestas predecibles y paginadas. La API devuelve un payload estándar de Laravel con `data`, `current_page`, `last_page`, `per_page`, `total` y enlaces de navegación.

Ejemplo de respuesta:

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 5,
      "title": "Publicación 5",
      "content": "Contenido de ejemplo",
      "likes_count": 0,
      "created_at": "2026-10-08T12:00:00.000000Z",
      "updated_at": "2026-10-08T12:00:00.000000Z"
    }
  ],
  "first_page_url": "http://localhost:8000/api/posts?page=1",
  "from": 1,
  "last_page": 2,
  "last_page_url": "http://localhost:8000/api/posts?page=2",
  "next_page_url": "http://localhost:8000/api/posts?page=2",
  "path": "http://localhost:8000/api/posts",
  "per_page": 10,
  "prev_page_url": null,
  "to": 10,
  "total": 15
}
```

Verificación con Postman: `GET {{baseUrl}}/api/posts?page=1` y `GET {{baseUrl}}/api/posts?page=2`.

## Endpoints

Todas las rutas están bajo `/api`. Las respuestas de validación se entregan como JSON al incluir `Accept: application/json`.

| Método | Ruta | Descripción | Respuesta exitosa |
|---|---|---|---:|
| `POST` | `/api/register` | Registrar usuario y emitir token | `201` |
| `POST` | `/api/login` | Iniciar sesión y emitir token | `200` |
| `POST` | `/api/logout` | Revocar token actual (**Bearer requerido**) | `200` |
| `POST` | `/api/posts` | Crear publicación; acepta título, contenido e imágenes opcionales (**Bearer requerido**) | `201` |
| `GET` | `/api/posts` | Lista paginada con comentarios e imágenes (público) | `200` |
| `GET` | `/api/posts/{id}` | Consultar una publicación y sus relaciones | `200` |
| `POST` | `/api/posts/{id}/comments` | Agregar comentario (**Bearer requerido**) | `201` |
| `POST` | `/api/posts/{id}/like` | Incrementar contador (**Bearer requerido**) | `200` |
| `DELETE` | `/api/posts/{id}` | Eliminar post propio, comentarios, imágenes y archivos (**Bearer requerido**) | `204` |

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

Suite del taller inicial: **17 pruebas, 70 aserciones**. La colección inicial de Postman se ejecutó con Newman: **11 solicitudes, 13 aserciones, 0 fallos**. En la Fase avanzada 1, las pruebas ampliadas validan registro, login, revocación, Bearer Token, protección de endpoints, asociación con usuario autenticado y autorización de borrado.

**Regresión de la Fase avanzada 1:** PHPUnit aprobó **29 pruebas y 114 aserciones**; Pint aprobó **46 archivos PHP**; Newman completó **14 solicitudes y 16 aserciones sin fallos**. Las 8 migraciones están aplicadas y las 9 rutas API están registradas.

## Fase 3: sistema de likes profesional

Se reemplaza el contador simple `likes_count` por una relación muchos a muchos entre `posts` y `users` a través de `post_user`, con `unique(post_id, user_id)` para impedir duplicados. La cantidad real de likes se calcula con `post->likedByUsers()->count()`. El endpoint exige autenticación y responde `409 Conflict` si el usuario ya dio like.

Ejemplo de prueba en Postman:

```http
POST {{baseUrl}}/api/posts/1/like
Authorization: Bearer {{token}}
```

Respuesta exitosa:

```json
{
  "message": "Like registrado correctamente.",
  "likes_count": 1,
  "post": {
    "id": 1,
    "title": "Publicación de prueba",
    "content": "Contenido de prueba",
    "likes_count": 0,
    "comments": [],
    "images": []
  }
}
```

Respuesta duplicada:

```json
{
  "message": "Ya has dado like a esta publicación."
}
```

## Fase 5: notificaciones por correo

La API envía un correo al propietario del post cuando alguien comenta en su publicación. El flujo usa un `Mailable` (`NewCommentMail`) y una plantilla Blade (`resources/views/emails/new-comment.blade.php`).

Se recomienda Mailtrap para pruebas locales y un archivo `.env` local con valores SMTP reales. La configuración base se incluye en `.env.example`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Ejemplo de prueba:

```http
POST {{baseUrl}}/api/posts/1/comments
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "content": "¡Excelente publicación!"
}
```

El correo recibido incluye:
- asunto: `Nuevo comentario`
- nombre del autor
- título de la publicación
- contenido del comentario

## Fase 6: versionado de la API

La API quedó versionada con prefijo `v1` para preparar evoluciones futuras. Las rutas principales se exponen en `/api/v1` y el proyecto mantiene compatibilidad con las rutas legacy bajo `/api` durante la transición.

Ejemplos:

```http
GET {{baseUrl}}/api/v1/posts
GET {{baseUrl}}/api/v1/posts/1
POST {{baseUrl}}/api/v1/register
POST {{baseUrl}}/api/v1/login
POST {{baseUrl}}/api/v1/posts
POST {{baseUrl}}/api/v1/posts/1/comments
POST {{baseUrl}}/api/v1/posts/1/like
```

El enrutado quedó agrupado con:

```php
Route::prefix('v1')->group(function () {
    // rutas públicas y protegidas
});
```

Esto permite mantener versiones futuras como `v1`, `v2` sin mezclar contratos incompatibles.

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

### Correcciones finales y regresión

La suite cubre errores `404` y `422`, prevención de cambios del contador desde el request, el máximo de 10 imágenes, el tamaño máximo de 5 MB, carga múltiple y eliminación de varios archivos. Se corrigieron también los cuatro problemas de formato encontrados en el esqueleto original.

```text
PHPUnit: 17 pruebas aprobadas, 70 aserciones
Pint: 40 archivos aprobados
Newman: 11 solicitudes, 13 aserciones, 0 fallos
API: 6 rutas registradas
SQLite: todas las migraciones aplicadas
HTTP: 200
```

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

Commits de la Fase avanzada completada hasta el momento:

```text
feat: instalar laravel sanctum
feat: implementar registro de usuarios
```
