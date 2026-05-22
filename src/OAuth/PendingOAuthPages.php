<?php

namespace MessengerBot\OAuth;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived cache of managed Pages between OAuth callback and host Page picker.
 */
final class PendingOAuthPages
{
    /**
     * @param  list<array{id: string, name: string, access_token: string}>  $pages
     * @param  array{tenant_id: string, connection_id: string}|null  $mt
     */
    public static function store(array $pages, ?array $mt): string
    {
        $token = Str::random(64);
        $payload = [
            'pages' => array_values($pages),
            'mt' => $mt,
        ];

        self::storeRepository()->put(self::cacheKey($token), $payload, self::ttl());

        return $token;
    }

    /**
     * @return array{pages: list<array{id: string, name: string, access_token: string}>, mt: array{tenant_id: string, connection_id: string}|null}|null
     */
    public static function pull(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $payload = self::storeRepository()->pull(self::cacheKey($token));
        if (! is_array($payload) || ! isset($payload['pages']) || ! is_array($payload['pages'])) {
            return null;
        }

        $mt = isset($payload['mt']) && is_array($payload['mt']) ? $payload['mt'] : null;

        return [
            'pages' => array_values($payload['pages']),
            'mt' => $mt,
        ];
    }

    public static function forget(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        self::storeRepository()->forget(self::cacheKey($token));
    }

    protected static function cacheKey(string $token): string
    {
        $prefix = (string) config('messenger-bot.oauth.pending_pages_cache_prefix', 'messenger_bot:oauth_pages:');

        return $prefix.$token;
    }

    protected static function ttl(): \DateTimeInterface|int
    {
        $minutes = (int) config('messenger-bot.oauth.pending_pages_ttl_minutes', 10);
        if ($minutes < 1) {
            $minutes = 10;
        }

        return now()->addMinutes($minutes);
    }

    protected static function storeRepository(): CacheRepository
    {
        $store = config('messenger-bot.oauth.pending_pages_cache_store');
        if (is_string($store) && trim($store) !== '') {
            return Cache::store(trim($store));
        }

        return Cache::store();
    }
}
