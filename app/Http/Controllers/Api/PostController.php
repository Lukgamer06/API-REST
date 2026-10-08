<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $posts = Post::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->with(['images', 'comments'])
            ->latest()
            ->paginate(10);

        return response()->json($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $data = $request->validated();
        $images = $data['images'] ?? [];
        unset($data['images']);
        $storedPaths = [];

        try {
            $post = DB::transaction(function () use ($data, $images, $request, &$storedPaths): Post {
                $post = $request->user()->posts()->create($data);

                foreach ($images as $image) {
                    if (! $image instanceof UploadedFile) {
                        throw new RuntimeException('Validated post image is not an uploaded file.');
                    }

                    $path = $image->store('posts', 'public');

                    if ($path === false) {
                        throw new RuntimeException('Unable to store uploaded post image.');
                    }

                    $storedPaths[] = $path;
                    $post->images()->create(['image_path' => $path]);
                }

                return $post;
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== [] && ! Storage::disk('public')->delete($storedPaths)) {
                throw new RuntimeException('Unable to clean up uploaded post images after a failed request.', previous: $exception);
            }

            throw $exception;
        }

        return response()->json($post->refresh()->load(['images', 'comments']), 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json($post->load(['images', 'comments']));
    }

    public function like(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($post->likedByUsers()->whereKey($user->id)->exists()) {
            return response()->json([
                'message' => 'Ya has dado like a esta publicación.',
            ], 409);
        }

        $post->likedByUsers()->attach($user->id);
        $post->refresh();

        return response()->json([
            'message' => 'Like registrado correctamente.',
            'likes_count' => $post->likedByUsers()->count(),
            'post' => $post->load(['images', 'comments']),
        ]);
    }

    public function destroy(Request $request, Post $post): Response
    {
        abort_unless($request->user()->is($post->user), 403);

        $imagePaths = $post->images()->pluck('image_path')->all();

        if ($imagePaths !== [] && ! Storage::disk('public')->delete($imagePaths)) {
            throw new RuntimeException('Unable to delete one or more post images.');
        }

        $post->delete();

        return response()->noContent();
    }
}
