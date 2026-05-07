<?php

namespace MessengerBot\Kernel\Tenancy;

/**
 * Result of mapping a Facebook Page ID to tenant + connection for webhook and API calls.
 */
final readonly class TenantResolution
{
    public function __construct(
        public TenantId $tenantId,
        public ConnectionId $connectionId,
        public string $pageId,
    ) {
        if ($this->pageId === '') {
            throw new \InvalidArgumentException('pageId must not be empty.');
        }
    }
}
