<?php

namespace MessengerBot\Contracts;

/**
 * Host implements business rules: one Page per tenant, one tenant per Page (reconnect same page_id allowed).
 */
interface ValidatesMessengerPageLink
{
    /**
     * @param  array{id: string, name: string, access_token: string}  $page
     * @param  array{tenant_id: string, connection_id: string}  $mt
     *
     * @throws \Throwable user-safe message for OAuth redirect flash
     */
    public function assertMayLinkPage(array $page, array $mt): void;
}
