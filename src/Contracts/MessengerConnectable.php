<?php

namespace MessengerBot\Contracts;

use MessengerBot\Kernel\Tenancy\TenantResolution;

/**
 * Host model or DTO that identifies a Facebook Page connection for OAuth, post sync, and jobs.
 */
interface MessengerConnectable
{
    /**
     * Stable tenant scope key (e.g. organization id, team ulid).
     */
    public function messengerTenantKey(): string;

    /**
     * Stable connection row key (e.g. primary key of the connection / store row).
     */
    public function messengerConnectionKey(): string;

    /**
     * Facebook Page ID for Graph and webhooks.
     */
    public function facebookPageId(): string;

    /**
     * Optional label for logs; may return null.
     */
    public function messengerDisplayName(): ?string;

    public function toMessengerTenantResolution(): TenantResolution;
}
