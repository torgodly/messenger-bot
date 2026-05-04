<?php

namespace MessengerBot\Routing;

use MessengerBot\Comments\Comment;

readonly class CommentRegistration
{
    /**
     * @param  list<string>|null  $onlyForPostIds  When non-null, handler runs only for comments on these Graph post IDs.
     */
    public function __construct(
        public mixed $handler,
        public ?array $onlyForPostIds,
    ) {}

    public function matches(Comment $comment): bool
    {
        if ($this->onlyForPostIds === null) {
            return true;
        }

        $pid = $comment->postId;
        if ($pid === null || $pid === '') {
            return false;
        }

        return in_array($pid, $this->onlyForPostIds, true);
    }
}
