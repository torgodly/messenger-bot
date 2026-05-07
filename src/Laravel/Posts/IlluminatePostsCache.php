<?php

namespace MessengerBot\Laravel\Posts;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use MessengerBot\Kernel\Contracts\PostsCache;

final class IlluminatePostsCache implements PostsCache
{
    public function __construct(
        protected ?string $cacheStoreName,
    ) {}

    public function get(string $key): ?array
    {
        $v = $this->store()->get($key);
        if (! is_array($v)) {
            return null;
        }

        return $v;
    }

    public function put(string $key, array $serializedPosts, int $ttlSeconds): void
    {
        $this->store()->put($key, $serializedPosts, $ttlSeconds);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($key);
    }

    protected function store(): CacheRepository
    {
        if ($this->cacheStoreName === null || $this->cacheStoreName === '') {
            return Cache::store();
        }

        return Cache::store($this->cacheStoreName);
    }
}
