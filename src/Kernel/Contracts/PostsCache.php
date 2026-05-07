<?php

namespace MessengerBot\Kernel\Contracts;

/**
 * Tenant-scoped post list cache (Redis/database/in-memory via Laravel cache store).
 */
interface PostsCache
{
    /**
     * @return list<array<string, mixed>>|null Serialized post rows; null if missing.
     */
    public function get(string $key): ?array;

    /**
     * @param  list<array<string, mixed>>  $serializedPosts
     */
    public function put(string $key, array $serializedPosts, int $ttlSeconds): void;

    public function forget(string $key): void;
}
