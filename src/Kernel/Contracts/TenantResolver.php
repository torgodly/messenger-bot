<?php

namespace MessengerBot\Kernel\Contracts;

use MessengerBot\Kernel\Tenancy\TenantResolution;

/**
 * Maps a Facebook Page ID (webhook entry id) to tenant + connection. Host implements for multi-tenant mode.
 */
interface TenantResolver
{
    public function resolveFromPageId(string $pageId): ?TenantResolution;
}
