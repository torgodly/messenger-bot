<?php

namespace MessengerBot\Kernel\Posts;

final readonly class PostsSyncOptions
{
    public function __construct(
        public int $limitPerRequest = 25,
        public int $maxApiCalls = 50,
        public int $maxDurationMs = 0,
        public bool $bypassCache = false,
        public bool $useStampedeLock = true,
        public int $cacheTtlSeconds = 300,
    ) {
        if ($this->limitPerRequest < 1) {
            throw new \InvalidArgumentException('limitPerRequest must be at least 1.');
        }
        if ($this->maxApiCalls < 1) {
            throw new \InvalidArgumentException('maxApiCalls must be at least 1.');
        }
    }
}
