<?php

namespace MessengerBot\Kernel\Credentials;

use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

/**
 * Cached Page token row for a connection (multi-tenant).
 */
final readonly class PageAccessTokenRecord
{
    public function __construct(
        public string $accessToken,
        public ?int $expiresAt,
        public string $pageId,
        public TenantId $tenantId,
        public ConnectionId $connectionId,
    ) {
        if ($this->accessToken === '') {
            throw new \InvalidArgumentException('accessToken must not be empty.');
        }
    }
}
