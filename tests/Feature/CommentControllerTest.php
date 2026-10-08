<?php

namespace Tests\Feature;

use App\Mail\NewCommentMail;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Ana']);
        $this->actingAs($this->user, 'sanctum');
    }

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
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_validates_required_comment_content(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/posts/{$post->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_uses_authenticated_user_as_comment_author(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);

        $this->postJson("/api/posts/{$post->id}/comments", [
            'author' => 'Autor falsificado',
            'content' => 'Buen aporte',
        ])
            ->assertCreated()
            ->assertJsonPath('author', 'Ana')
            ->assertJsonPath('user_id', $this->user->id);
    }

    public function test_store_sends_email_to_post_owner_when_comment_is_created(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $post = $owner->posts()->create([
            'title' => 'Publicación con dueño',
            'content' => 'Contenido de prueba',
        ]);

        $commenter = User::factory()->create(['name' => 'Luis', 'email' => 'luis@example.com']);
        $this->actingAs($commenter, 'sanctum');

        $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Nuevo comentario',
        ])
            ->assertCreated();

        Mail::assertSent(NewCommentMail::class, function (NewCommentMail $mail) use ($owner, $post) {
            return $mail->hasTo($owner->email)
                && $mail->post->id === $post->id;
        });
    }

    public function test_store_requires_authentication(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Buen aporte',
        ])->assertUnauthorized();
    }

    public function test_store_returns_not_found_for_missing_post(): void
    {
        $this->postJson('/api/posts/999999/comments', [
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ])->assertNotFound();
    }
}
