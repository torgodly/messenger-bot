<?php

namespace MessengerBot\Contracts;

interface PageAccessTokenRepository
{
    /**
     * Cached Page access token, or null if not stored.
     */
    public function getToken(): ?string;

    /**
     * Unix timestamp when the token expires, or null if unknown / non-expiring.
     */
    public function getExpiresAt(): ?int;

    public function getPageId(): ?string;

    /**
     * @param  array{access_token: string, expires_at?: ?int, page_id?: ?string}  $payload
     */
    public function put(array $payload): void;

    public function forget(): void;

    /**
     * True when expiry is known and within the buffer (seconds) from now.
     */
    public function shouldRefreshSoon(int $bufferSeconds): bool;
}
