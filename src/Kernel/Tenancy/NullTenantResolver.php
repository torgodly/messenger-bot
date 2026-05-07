<?php

namespace MessengerBot\Kernel\Tenancy;

use MessengerBot\Kernel\Contracts\TenantResolver;

/**
 * Always unresolved; used when tenancy is enabled but no custom resolver is bound yet (tests / fallback).
 */
final class NullTenantResolver implements TenantResolver
{
    public function resolveFromPageId(string $pageId): ?TenantResolution
    {
        return null;
    }
}
