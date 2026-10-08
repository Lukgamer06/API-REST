# API REST Tipo Facebook con Laravel

## Objetivo del proyecto

Desarrollar una API REST académica tipo Facebook con Laravel 11, enfocada en publicaciones, comentarios, imágenes, likes, autenticación con Sanctum y documentación automática. El proyecto está diseñado para practicar arquitectura MVC, Eloquent ORM, validaciones, almacenamiento de archivos, API versionada y pruebas funcionales.

## Tecnologías utilizadas

- PHP 8.3
- Laravel 11
- SQLite
- Laravel Sanctum
- Docker + Docker Compose
- Postman + Newman
- Git + GitHub
- VS Code
- Scribe para documentación automática

## Arquitectura

El proyecto sigue un patrón MVC con separación clara entre:

- Models: `User`, `Post`, `Comment`, `PostImage`
- Controllers: `AuthController`, `PostController`, `CommentController`
- Requests: `RegisterRequest`, `LoginRequest`, `StorePostRequest`, `StoreCommentRequest`
- Views: plantillas Blade para correos y documentación Scribe
- Routes: agrupadas bajo `/api/v1`

## Configuración del proyecto

### Requisitos

- Docker
- Docker Compose
- Git
- VS Code

### Instalación

```bash
docker compose up --build -d
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan scribe:generate
```

### Base URL local

```text
http://localhost:8000
```

### Documentación automática

```text
http://localhost:8000/docs
```

## Base de datos y SQLite

El proyecto usa SQLite como base de datos local. La base se almacena en:

```text
database/database.sqlite
```

Laravel usa por defecto ese archivo y el contenedor crea la DB automáticamente si no existe.

## Diagrama relacional

```text
users
  | 1 ────< * posts
  |          ├──< post_images
  |          └──< comments
  |
  └──< post_user

posts
  | 1 ────< * comments
  | 1 ────< * post_images
  | * ────< * users (likes via post_user)
```

## Migraciones

### posts

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->text('content');
    $table->unsignedInteger('likes_count')->default(0);
    $table->timestamps();
});
```

### comments

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('author');
    $table->text('content');
    $table->timestamps();
});
```

### post_images

```php
Schema::create('post_images', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->string('image_path');
    $table->timestamps();
});
```

### post_user (likes por usuario)

```php
Schema::create('post_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unique(['post_id', 'user_id']);
    $table->timestamps();
});
```

## Modelos

### User

```php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }
}
```

### Post

```php
class Post extends Model
{
    protected $fillable = ['title', 'content'];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
```

### Comment

```php
class Comment extends Model
{
    protected $fillable = ['author', 'content'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### PostImage

```php
class PostImage extends Model
{
    protected $fillable = ['image_path'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```

## Relaciones principales

- `Post` tiene muchos `Comment`.
- `Post` tiene muchas `PostImage`.
- `Post` pertenece a `User`.
- `Comment` pertenece a `Post` y `User`.
- `User` puede dar likes a muchas publicaciones mediante la tabla `post_user`.

## Endpoints

Todos los endpoints están protegidos o versionados bajo `/api/v1`.

### Auth

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/register` | Registro de usuario |
| POST | `/api/v1/login` | Inicio de sesión |
| POST | `/api/v1/logout` | Cierre de sesión |

### Posts

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/posts` | Listado paginado |
| GET | `/api/v1/posts/{id}` | Consulta de una publicación |
| POST | `/api/v1/posts` | Crear publicación |
| DELETE | `/api/v1/posts/{id}` | Eliminar publicación |

### Comentarios y likes

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/v1/posts/{id}/comments` | Crear comentario |
| POST | `/api/v1/posts/{id}/like` | Dar like |

## Ejemplos de uso con Postman

### 1. Registro

```http
POST {{baseUrl}}/api/v1/register
Content-Type: application/json

{
  "name": "Ada Lovelace",
  "email": "ada@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Respuesta:

```json
{
  "token": "<token>",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com"
  }
}
```

### 2. Login

```http
POST {{baseUrl}}/api/v1/login
Content-Type: application/json

{
  "email": "ada@example.com",
  "password": "password123"
}
```

### 3. Crear publicación

```http
POST {{baseUrl}}/api/v1/posts
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "title": "Mi primera publicación",
  "content": "Contenido de prueba"
}
```

### 4. Listar publicaciones

```http
GET {{baseUrl}}/api/v1/posts?page=1
```

### 5. Agregar comentario

```http
POST {{baseUrl}}/api/v1/posts/1/comments
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "content": "Excelente publicación"
}
```

### 6. Dar like

```http
POST {{baseUrl}}/api/v1/posts/1/like
Authorization: Bearer {{token}}
```

### 7. Eliminar publicación

```http
DELETE {{baseUrl}}/api/v1/posts/1
Authorization: Bearer {{token}}
```

## Capturas JSON de Postman

### Crear publicación (201 Created)

```json
{
  "id": 1,
  "title": "Mi primera publicación",
  "content": "Contenido de prueba",
  "likes_count": 0,
  "comments": [],
  "images": []
}
```

### Listado paginado (200 OK)

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "title": "Mi primera publicación",
      "content": "Contenido de prueba"
    }
  ],
  "per_page": 10,
  "total": 1,
  "last_page": 1
}
```

### Like duplicado (409 Conflict)

```json
{
  "message": "Ya has dado like a esta publicación."
}
```

### Validación 422

```json
{
  "message": "The title field is required. (and 1 more error)",
  "errors": {
    "title": ["The title field is required."],
    "content": ["The content field is required."]
  }
}
```

## Notificaciones por email

Cuando un usuario comenta en una publicación ajena, el propietario recibe un correo con asunto:

```text
Nuevo comentario
```

Se configura con Mailtrap o SMTP real, usando `MAIL_MAILER=smtp` y valores del entorno.

## Versionado

La API quedó versionada bajo el prefijo `v1`:

```text
/api/v1
```

Esto permite mantener versiones futuras sin romper compatibilidad:

```text
/api/v1
/api/v2
```

## Documentación automática

La documentación se genera con Scribe y queda disponible en:

```text
http://localhost:8000/docs
```

También se genera:

- colección Postman
- especificación OpenAPI

## Pruebas y evidencias

Ejecuta la validación de la API:

```bash
docker compose exec app php artisan test
```

Resultado verificado:

```text
21 tests passed
104 assertions
```

## Historial de commits

```text
feat: instalar laravel sanctum
feat: implementar registro de usuarios
feat: implementar login y logout
feat: proteger endpoints con sanctum
feat: asociar publicaciones a usuarios
feat: implementar paginacion de publicaciones
feat: crear sistema de likes por usuario
feat: evitar likes duplicados
feat: implementar busqueda por titulo y contenido
feat: configurar notificaciones por correo
feat: enviar correo al crear comentarios
feat: versionar rutas api v1
feat: implementar documentacion con scribe
test: validar endpoints autenticados en postman
docs: actualizar readme
fix: corregir errores finales
```

## Evidencias de funcionamiento

- Registro y login con Sanctum funcionando
- Tokens Bearer funcionando en endpoints protegidos
- Publicaciones con múltiples imágenes
- Comentarios asociados al usuario autenticado
- Likes reales, sin duplicados
- Búsqueda por título y contenido
- Paginación en `GET /api/v1/posts`
- Correos de notificación al propietario del post
- Documentación automática en `/docs`
- API versionada bajo `/api/v1`

## Commit final recomendado

```bash
git add .
git commit -m "docs: actualizar readme"
git commit -m "fix: corregir errores finales"
```
