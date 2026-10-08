<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_comment_for_post_and_returns_created_status(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/posts/{$post->id}/comments", [
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ])
            ->assertCreated()
            ->assertJsonPath('post_id', $post->id)
            ->assertJsonPath('author', 'Ana')
            ->assertJsonPath('content', 'Buen aporte');

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ]);
    }

    public function test_store_validates_required_comment_fields(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/posts/{$post->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['author', 'content']);
    }

    public function test_store_returns_not_found_for_missing_post(): void
    {
        $this->postJson('/api/posts/999999/comments', [
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ])->assertNotFound();
    }
}
