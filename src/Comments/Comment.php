<?php

namespace MessengerBot\Comments;

readonly class Comment
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $message,
        public CommentAuthor $from,
        public ?string $postId,
        public ?string $parentId,
        public ?string $createdTime,
        public ?bool $canReplyPrivately,
        public array $raw,
    ) {}

    /**
     * True for a direct comment on the post, false for a reply under another comment (e.g. Page reply webhooks).
     *
     * Meta feed webhooks: top-level uses no parent_id or parent_id equal to post_id; replies use parent_id of the parent comment.
     */
    public function isTopLevelOnPost(): bool
    {
        if ($this->parentId === null || $this->parentId === '') {
            return true;
        }

        if ($this->postId === null || $this->postId === '') {
            return false;
        }

        return $this->parentId === $this->postId;
    }
}
