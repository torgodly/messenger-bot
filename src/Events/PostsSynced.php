<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostsSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $pageId,
        public int $itemCount,
        public bool $truncatedByMax,
        public int $apiCalls,
        public bool $cacheHit,
    ) {}
}
