<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_post_and_returns_created_status(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('title', 'Publicación de prueba')
            ->assertJsonPath('likes_count', 0)
            ->assertJsonPath('comments', [])
            ->assertJsonPath('images', []);

        $this->assertDatabaseHas('posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
    }

    public function test_store_validates_required_post_fields(): void
    {
        $this->postJson('/api/posts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_index_and_show_include_post_relations(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $post->comments()->create([
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ]);
        $post->images()->create([
            'image_path' => 'posts/example.jpg',
        ]);

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.comments.0.author', 'Ana')
            ->assertJsonPath('data.0.images.0.image_path', 'posts/example.jpg');

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('comments.0.author', 'Ana')
            ->assertJsonPath('images.0.image_path', 'posts/example.jpg');
    }

    public function test_destroy_returns_no_content_and_deletes_post(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_show_returns_not_found_for_missing_post(): void
    {
        $this->getJson('/api/posts/999999')->assertNotFound();
    }

    public function test_like_increments_likes_count_and_returns_updated_post(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('likes_count', 1);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'likes_count' => 1,
        ]);
    }
}
