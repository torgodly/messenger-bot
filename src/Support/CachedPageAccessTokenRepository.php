<?php

namespace MessengerBot\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use MessengerBot\Contracts\PageAccessTokenRepository;

class CachedPageAccessTokenRepository implements PageAccessTokenRepository
{
    public function __construct(
        protected string $cacheKey,
        protected ?string $cacheStoreName,
    ) {}

    public function getToken(): ?string
    {
        $row = $this->row();
        if ($row === null) {
            return null;
        }
        $t = (string) ($row['access_token'] ?? '');
        if ($t === '') {
            return null;
        }

        return $t;
    }

    public function getExpiresAt(): ?int
    {
        $row = $this->row();
        if ($row === null || ! array_key_exists('expires_at', $row)) {
            return null;
        }
        $v = $row['expires_at'];
        if ($v === null) {
            return null;
        }

        return is_int($v) ? $v : (int) $v;
    }

    public function getPageId(): ?string
    {
        $row = $this->row();
        if ($row === null) {
            return null;
        }
        $id = $row['page_id'] ?? null;

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function put(array $payload): void
    {
        $accessToken = (string) ($payload['access_token'] ?? '');
        if ($accessToken === '') {
            return;
        }

        $expiresAt = array_key_exists('expires_at', $payload) ? $payload['expires_at'] : null;
        $pageId = array_key_exists('page_id', $payload) ? $payload['page_id'] : null;

        $row = [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
            'page_id' => $pageId !== null && $pageId !== '' ? (string) $pageId : null,
        ];

        $this->store()->put($this->cacheKey, $row, $this->cacheTtlSeconds($expiresAt));
    }

    public function forget(): void
    {
        $this->store()->forget($this->cacheKey);
    }

    public function shouldRefreshSoon(int $bufferSeconds): bool
    {
        $expiresAt = $this->getExpiresAt();
        if ($expiresAt === null || $expiresAt === 0) {
            return false;
        }

        return ($expiresAt - time()) <= $bufferSeconds;
    }

    /**
     * @return array{access_token: string, expires_at?: ?int, page_id?: ?string}|null
     */
    protected function row(): ?array
    {
        $v = $this->store()->get($this->cacheKey);
        if (! is_array($v)) {
            return null;
        }

        return $v;
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
