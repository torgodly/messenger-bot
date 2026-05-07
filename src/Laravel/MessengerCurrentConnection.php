<?php

namespace MessengerBot\Laravel;

use MessengerBot\Kernel\Tenancy\TenantContextHolder;
use MessengerBot\Kernel\Tenancy\TenantResolution;

/**
 * Read-only view of the active webhook / scoped tenant context.
 */
final class MessengerCurrentConnection
{
    public function __construct(
        protected TenantContextHolder $tenantContext,
    ) {}

    public function resolution(): ?TenantResolution
    {
        return $this->tenantContext->current();
    }
}
