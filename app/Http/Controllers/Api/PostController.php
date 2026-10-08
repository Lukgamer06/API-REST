<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Post::query()
            ->with(['images', 'comments'])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());

        return response()->json($post->refresh()->load(['images', 'comments']), 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json($post->load(['images', 'comments']));
    }

    public function like(Post $post): JsonResponse
    {
        $post->increment('likes_count');

        return response()->json($post->refresh()->load(['images', 'comments']));
    }

    public function destroy(Post $post): Response
    {
        $post->delete();

        return response()->noContent();
    }
}
