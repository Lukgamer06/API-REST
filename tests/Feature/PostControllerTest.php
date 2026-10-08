<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_post_and_returns_created_status(): void
    {
        $response = $this->postJson('/api/posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
            'likes_count' => 999,
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

    public function test_store_persists_multiple_images_for_a_post(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pR8AAAAASUVORK5CYII=', true);

        $response = $this->post('/api/posts', [
            'title' => 'Publicación con imágenes',
            'content' => 'Contenido de prueba',
            'images' => [
                UploadedFile::fake()->createWithContent('first.png', $png),
                UploadedFile::fake()->createWithContent('second.png', $png),
            ],
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'images')
            ->assertJsonPath('images.0.image_path', fn (string $path): bool => str_starts_with($path, 'posts/'))
            ->assertJsonPath('images.1.image_path', fn (string $path): bool => str_starts_with($path, 'posts/'));

        $this->assertDatabaseCount('post_images', 2);

        foreach ($response->json('images') as $image) {
            Storage::disk('public')->assertExists($image['image_path']);
        }
    }

    public function test_store_rejects_non_image_uploads(): void
    {
        $this->post('/api/posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
            'images' => [
                UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_images', 0);
    }

    public function test_store_rejects_more_than_ten_images(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pR8AAAAASUVORK5CYII=', true);

        $this->post('/api/posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
            'images' => array_map(
                fn (int $index): UploadedFile => UploadedFile::fake()->createWithContent("image-{$index}.png", $png),
                range(1, 11),
            ),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images']);

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_images', 0);
    }

    public function test_store_rejects_images_larger_than_five_megabytes(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pR8AAAAASUVORK5CYII=', true);
        $oversizedPng = $png.str_repeat('x', (5 * 1024 * 1024) + 1 - strlen($png));

        $this->post('/api/posts', [
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
            'images' => [
                UploadedFile::fake()->createWithContent('oversized.png', $oversizedPng),
            ],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_images', 0);
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
        Storage::fake('public');

        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $imagePaths = ['posts/first.png', 'posts/second.png'];

        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->put($imagePath, 'fake image content');
            $post->images()->create(['image_path' => $imagePath]);
        }

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);

        foreach ($imagePaths as $imagePath) {
            $this->assertDatabaseMissing('post_images', ['image_path' => $imagePath]);
            Storage::disk('public')->assertMissing($imagePath);
        }
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
