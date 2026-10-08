<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Post $post,
        public Comment $comment,
        public User $commentAuthor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo comentario',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-comment',
            with: [
                'post' => $this->post,
                'comment' => $this->comment,
                'commentAuthor' => $this->commentAuthor,
            ],
        );
    }
}
