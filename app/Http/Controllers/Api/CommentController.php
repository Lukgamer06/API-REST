<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = new Comment([
            'author' => $request->user()->name,
            'content' => $request->validated('content'),
        ]);
        $comment->user()->associate($request->user());
        $post->comments()->save($comment);

        return response()->json($comment, 201);
    }
}
