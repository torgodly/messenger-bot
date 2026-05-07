<?php

namespace MessengerBot\Kernel\Contracts;

use MessengerBot\Kernel\Credentials\PageAccessTokenRecord;
use MessengerBot\Kernel\Tenancy\ConnectionId;

/**
 * Persists Page access tokens per connection (replace with DB implementation in production SaaS).
 */
interface ConnectionTokenRepository
{
    public function getByConnectionId(ConnectionId $connectionId): ?PageAccessTokenRecord;

    public function getByPageId(string $pageId): ?PageAccessTokenRecord;

    /**
     * @param  array{access_token: string, expires_at?: ?int, page_id: string, tenant_id: string, connection_id: string}  $payload
     */
    public function put(array $payload): void;

    public function forget(ConnectionId $connectionId): void;

    /**
     * Increment logical cache version for posts (invalidates composite cache keys).
     */
    public function bumpPostsCacheVersion(ConnectionId $connectionId): int;

    public function getPostsCacheVersion(ConnectionId $connectionId): int;
}
