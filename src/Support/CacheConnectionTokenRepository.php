<?php

namespace MessengerBot\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Kernel\Credentials\PageAccessTokenRecord;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

/**
 * Cache-backed connection tokens and page index (suitable for demos; use DB in production).
 */
class CacheConnectionTokenRepository implements ConnectionTokenRepository
{
    public function __construct(
        protected string $tokenKeyPrefix,
        protected string $pageIndexPrefix,
        protected string $versionPrefix,
        protected ?string $cacheStoreName,
    ) {}

    public function getByConnectionId(ConnectionId $connectionId): ?PageAccessTokenRecord
    {
        $row = $this->store()->get($this->tokenKey($connectionId));
        if (! is_array($row)) {
            return null;
        }

        return $this->rowToRecord($row);
    }

    public function getByPageId(string $pageId): ?PageAccessTokenRecord
    {
        $cid = $this->store()->get($this->pageIndexKey($pageId));
        if (! is_string($cid) || $cid === '') {
            return null;
        }

        return $this->getByConnectionId(new ConnectionId($cid));
    }

    /**
     * @param  array{access_token: string, expires_at?: ?int, page_id: string, tenant_id: string, connection_id: string}  $payload
     */
    public function put(array $payload): void
    {
        $token = (string) ($payload['access_token'] ?? '');
        $pageId = (string) ($payload['page_id'] ?? '');
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $connectionId = (string) ($payload['connection_id'] ?? '');
        if ($token === '' || $pageId === '' || $tenantId === '' || $connectionId === '') {
            return;
        }

        $expiresAt = array_key_exists('expires_at', $payload) ? $payload['expires_at'] : null;
        $row = [
            'access_token' => $token,
            'expires_at' => $expiresAt,
            'page_id' => $pageId,
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
        ];

        $conn = new ConnectionId($connectionId);
        $ttl = $this->cacheTtlSeconds(is_int($expiresAt) ? $expiresAt : null);
        $this->store()->put($this->tokenKey($conn), $row, $ttl);
        $this->store()->put($this->pageIndexKey($pageId), $connectionId, $ttl);
        $this->bumpPostsCacheVersion($conn);
    }

    public function forget(ConnectionId $connectionId): void
    {
        $row = $this->store()->get($this->tokenKey($connectionId));
        $this->store()->forget($this->tokenKey($connectionId));
        if (is_array($row) && isset($row['page_id']) && is_string($row['page_id']) && $row['page_id'] !== '') {
            $this->store()->forget($this->pageIndexKey($row['page_id']));
        }
        $this->store()->forget($this->versionKey($connectionId));
    }

    public function bumpPostsCacheVersion(ConnectionId $connectionId): int
    {
        $key = $this->versionKey($connectionId);
        $v = (int) $this->store()->get($key, 0);

        $v++;
        $this->store()->forever($key, $v);

        return $v;
    }

    public function getPostsCacheVersion(ConnectionId $connectionId): int
    {
        return (int) $this->store()->get($this->versionKey($connectionId), 0);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowToRecord(array $row): ?PageAccessTokenRecord
    {
        $token = (string) ($row['access_token'] ?? '');
        if ($token === '') {
            return null;
        }
        $pageId = (string) ($row['page_id'] ?? '');
        $tenantId = (string) ($row['tenant_id'] ?? '');
        $connectionId = (string) ($row['connection_id'] ?? '');
        if ($pageId === '' || $tenantId === '' || $connectionId === '') {
            return null;
        }
        $expires = $row['expires_at'] ?? null;

        return new PageAccessTokenRecord(
            $token,
            is_int($expires) ? $expires : (is_numeric($expires) ? (int) $expires : null),
            $pageId,
            new TenantId($tenantId),
            new ConnectionId($connectionId),
        );
    }

    protected function tokenKey(ConnectionId $connectionId): string
    {
        return $this->tokenKeyPrefix.$connectionId->value;
    }

    protected function pageIndexKey(string $pageId): string
    {
        return $this->pageIndexPrefix.$pageId;
    }

    protected function versionKey(ConnectionId $connectionId): string
    {
        return $this->versionPrefix.$connectionId->value;
    }

    protected function store(): CacheRepository
    {
        if ($this->cacheStoreName === null || $this->cacheStoreName === '') {
            return Cache::store();
        }

        return Cache::store($this->cacheStoreName);
    }

    protected function cacheTtlSeconds(?int $expiresAtUnix): \DateTimeInterface|int
    {
        if ($expiresAtUnix === null || $expiresAtUnix === 0) {
            return now()->addDays(60);
        }

        $seconds = $expiresAtUnix - time() - 120;
        if ($seconds < 3600) {
            return max($seconds, 300);
        }

        return $seconds;
    }
}
