<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_store_creates_post_and_returns_created_status(): void
    {
        $response = $this->postJson('/api/v1/posts', [
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
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_validates_required_post_fields(): void
    {
        $this->postJson('/api/v1/posts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_store_persists_multiple_images_for_a_post(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pR8AAAAASUVORK5CYII=', true);

        $response = $this->post('/api/v1/posts', [
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
        $this->post('/api/v1/posts', [
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

        $this->post('/api/v1/posts', [
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

        $this->post('/api/v1/posts', [
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
        $post = $this->user->posts()->create([
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

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.comments.0.author', 'Ana')
            ->assertJsonPath('data.0.images.0.image_path', 'posts/example.jpg')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('current_page', 1)
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);

        $this->getJson('/api/v1/posts?search=prueba')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id);

        $this->getJson("/api/v1/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('comments.0.author', 'Ana')
            ->assertJsonPath('images.0.image_path', 'posts/example.jpg');
    }

    public function test_destroy_returns_no_content_and_deletes_post(): void
    {
        Storage::fake('public');

        $post = $this->user->posts()->create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $imagePaths = ['posts/first.png', 'posts/second.png'];

        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->put($imagePath, 'fake image content');
            $post->images()->create(['image_path' => $imagePath]);
        }

        $this->deleteJson("/api/v1/posts/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);

        foreach ($imagePaths as $imagePath) {
            $this->assertDatabaseMissing('post_images', ['image_path' => $imagePath]);
            Storage::disk('public')->assertMissing($imagePath);
        }
    }

    public function test_destroy_forbids_deleting_another_users_post(): void
    {
        $post = User::factory()->create()->posts()->create([
            'title' => 'Publicación ajena',
            'content' => 'Contenido de prueba',
        ]);

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_destroy_requires_authentication(): void
    {
        $post = Post::create([
            'title' => 'Publicación protegida',
            'content' => 'Contenido de prueba',
        ]);
        $this->app['auth']->forgetGuards();

        $this->deleteJson("/api/v1/posts/{$post->id}")->assertUnauthorized();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/posts', [
            'title' => 'Publicación sin sesión',
            'content' => 'Contenido de prueba',
        ])->assertUnauthorized();
    }

    public function test_show_returns_not_found_for_missing_post(): void
    {
        $this->getJson('/api/v1/posts/999999')->assertNotFound();
    }

    public function test_like_registers_real_like_and_prevents_duplicates(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/v1/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('likes_count', 1)
            ->assertJsonPath('post.id', $post->id);

        $this->assertDatabaseHas('post_user', [
            'post_id' => $post->id,
            'user_id' => $this->user->id,
        ]);

        $this->postJson("/api/v1/posts/{$post->id}/like")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ya has dado like a esta publicación.');

        $this->assertDatabaseCount('post_user', 1);
    }

    public function test_index_supports_search_in_title_and_content_with_pagination(): void
    {
        $this->user->posts()->create([
            'title' => 'Viaje a Japón',
            'content' => 'Las mejores experiencias del recorrido.',
        ]);
        $this->user->posts()->create([
            'title' => 'Trabajo del curso',
            'content' => 'La universidad ofrece clases interesantes.',
        ]);

        $this->getJson('/api/v1/posts?search=viaje')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Viaje a Japón');

        $this->getJson('/api/v1/posts?search=universidad')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.content', 'La universidad ofrece clases interesantes.');
    }

    public function test_v1_routes_are_available_for_api_versioning(): void
    {
        $this->user->posts()->create([
            'title' => 'Versión v1',
            'content' => 'Ruta versionada',
        ]);

        $this->getJson('/api/v1/posts')
            ->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data',
                'last_page',
                'per_page',
                'total',
            ]);
    }
}
