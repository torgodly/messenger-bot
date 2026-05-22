<?php

namespace MessengerBot\OAuth;

/**
 * Exchanges an OAuth authorization code for managed Facebook Pages (does not store tokens).
 */
final class ExchangeOAuthCodeForManagedPages
{
    public function __construct(
        protected FacebookOAuthClient $oauth,
    ) {}

    /**
     * @param  array{tenant_id: string, connection_id: string}|null  $mt
     * @return array{pages: list<array{id: string, name: string, access_token: string}>, mt: array{tenant_id: string, connection_id: string}|null}
     */
    public function exchange(string $code, string $redirectUri, ?array $mt = null): array
    {
        $short = $this->oauth->exchangeCodeForUserAccessToken($code, $redirectUri);
        $shortUser = (string) ($short['access_token'] ?? '');
        if ($shortUser === '') {
            throw new \RuntimeException('Facebook did not return a user access token.');
        }

        $long = $this->oauth->exchangeLongLivedUserToken($shortUser);
        $longUser = (string) ($long['access_token'] ?? '');
        if ($longUser === '') {
            throw new \RuntimeException('Could not exchange for a long-lived user token.');
        }

        $pages = $this->oauth->fetchManagedPages($longUser);

        return [
            'pages' => $pages,
            'mt' => $this->normalizeMt($mt),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $mt
     * @return array{tenant_id: string, connection_id: string}|null
     */
    protected function normalizeMt(?array $mt): ?array
    {
        if ($mt === null) {
            return null;
        }

        $tenantId = trim((string) ($mt['tenant_id'] ?? ''));
        $connectionId = trim((string) ($mt['connection_id'] ?? ''));
        if ($tenantId === '' || $connectionId === '') {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
        ];
    }
}
