<?php

namespace MessengerBot\Kernel\Posts;

/**
 * @template-covariant T of Post
 */
final readonly class PostsSyncResult
{
    /**
     * @param  list<Post>  $items
     */
    public function __construct(
        public array $items,
        public bool $truncatedByMax,
        public int $apiCalls,
        public bool $cacheHit,
        public \DateTimeImmutable $syncedAt,
        public ?string $nextPagingUrl = null,
    ) {}
}
