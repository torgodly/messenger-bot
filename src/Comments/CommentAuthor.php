<?php

namespace MessengerBot\Comments;

readonly class CommentAuthor
{
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {}
}
