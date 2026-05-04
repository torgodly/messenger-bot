<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MessengerBot\Comments\Comment;

class CommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Comment $comment,
    ) {}
}
