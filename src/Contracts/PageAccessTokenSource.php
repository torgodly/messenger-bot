<?php

namespace MessengerBot\Contracts;

/**
 * Resolves the Page access token for the current Graph request (legacy single-Page or multi-tenant context).
 */
interface PageAccessTokenSource
{
    public function token(): string;

    public function source(): string;
}
