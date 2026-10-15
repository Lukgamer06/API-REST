# Introduction

API de publicaciones con autenticación Bearer.

<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>

Para probar los endpoints protegidos, primero ejecuta **POST /api/v1/register** con los ejemplos mostrados. La respuesta contiene el token de acceso. En cada endpoint protegido, selecciona **Try it out** y en el campo **Authorization** pega `Bearer ` seguido del token completo (incluye el prefijo y el espacio).

También puedes iniciar sesión en **POST /api/v1/login** con el mismo correo y contraseña usados al registrarte. Las rutas de lectura de publicaciones no requieren autenticación.

<aside>El registro requiere que `password_confirmation` sea idéntico a `password`. Para comentarios, likes y eliminación, usa un `post_id` existente obtenido de **GET /api/v1/posts**. El usuario solo puede eliminar sus propias publicaciones.</aside>

