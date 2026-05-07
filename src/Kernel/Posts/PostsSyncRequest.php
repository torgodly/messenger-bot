<?php

namespace MessengerBot\Kernel\Posts;

use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Support\MessengerConnection;

/**
 * Sync posts for a Page. Call within {@see TenantContextHolder::run()} when using contextual tokens,
 * or ensure legacy single-Page token matches this pageId.
 */
final readonly class PostsSyncRequest
{
    /**
     * @param  list<string>|null  $fields
     */
    public function __construct(
        public TenantId $tenantId,
        public ConnectionId $connectionId,
        public string $pageId,
        public ?\DateTimeInterface $since = null,
        public ?\DateTimeInterface $until = null,
        public int $maxPosts = 500,
        public ?array $fields = null,
    ) {
        if ($this->pageId === '') {
            throw new \InvalidArgumentException('pageId must not be empty.');
        }
        if ($this->maxPosts < 1) {
            throw new \InvalidArgumentException('maxPosts must be at least 1.');
        }
    }

    /**
     * @param  list<string>|null  $fields
     */
    public static function forConnectable(
        MessengerConnectable $connectable,
        ?\DateTimeInterface $since = null,
        ?\DateTimeInterface $until = null,
        int $maxPosts = 500,
        ?array $fields = null,
    ): self {
        $r = MessengerConnection::toResolution($connectable);

        return new self(
            $r->tenantId,
            $r->connectionId,
            $r->pageId,
            $since,
            $until,
            $maxPosts,
            $fields,
        );
    }
}
