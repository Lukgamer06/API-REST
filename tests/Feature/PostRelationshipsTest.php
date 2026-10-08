<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_has_many_comments_and_images_and_children_belong_to_post(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $comment = $post->comments()->create([
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ]);
        $image = $post->images()->create([
            'image_path' => 'posts/example.jpg',
        ]);

        $this->assertCount(1, $post->comments);
        $this->assertCount(1, $post->images);
        $this->assertInstanceOf(Post::class, $comment->post);
        $this->assertInstanceOf(Post::class, $image->post);
    }

    public function test_deleting_post_cascades_to_comments_and_images(): void
    {
        $post = Post::create([
            'title' => 'Publicación de prueba',
            'content' => 'Contenido de prueba',
        ]);
        $comment = $post->comments()->create([
            'author' => 'Ana',
            'content' => 'Buen aporte',
        ]);
        $image = $post->images()->create([
            'image_path' => 'posts/example.jpg',
        ]);

        $post->delete();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('post_images', ['id' => $image->id]);
    }
}
