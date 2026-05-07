<?php

namespace MessengerBot\Kernel\Posts;

/**
 * Normalized Facebook Page post (subset of Graph fields).
 */
final readonly class Post
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $message,
        public ?string $createdTime,
        public ?string $permalinkUrl,
        public array $raw = [],
    ) {}
}
