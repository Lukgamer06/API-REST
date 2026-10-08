<x-mail::message>
# Nuevo comentario

Hola {{ $post->user?->name ?? 'Usuario' }},

{{ $commentAuthor->name }} ha publicado un nuevo comentario en tu publicación: "{{ $post->title }}".

**Comentario:**
> {{ $comment->content }}

Puedes revisar la publicación desde la plataforma.

Gracias,
{{ config('app.name') }}
</x-mail::message>
