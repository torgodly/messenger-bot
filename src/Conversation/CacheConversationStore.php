<?php

namespace MessengerBot\Conversation;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use MessengerBot\Contracts\ConversationStore;

class CacheConversationStore implements ConversationStore
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $prefix,
        protected int $ttlMinutes,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->prefix.$key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->cache->put($this->prefix.$key, $value, now()->addMinutes($this->ttlMinutes));
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->prefix.$key);
    }
}
