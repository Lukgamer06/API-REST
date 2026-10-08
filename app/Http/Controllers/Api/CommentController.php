<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Mail\NewCommentMail;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

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

        $postOwner = $post->user()->first();
        if ($postOwner && $postOwner->email && $postOwner->id !== $request->user()->id) {
            Mail::to($postOwner->email)->send(new NewCommentMail($post->load('user'), $comment, $request->user()));
        }

        return response()->json($comment, 201);
    }
}
