<?php

namespace MessengerBot\Http;

use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Contracts\PageAccessTokenSource;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Kernel\Tenancy\TenantContextHolder;

/**
 * Multi-tenant: token from ConnectionTokenRepository when tenant context is set; otherwise legacy single-Page flow.
 */
class ContextualPageAccessTokenProvider implements PageAccessTokenSource
{
    public function __construct(
        protected TenantContextHolder $tenantContext,
        protected ConnectionTokenRepository $connectionTokens,
        protected PageAccessTokenRepository $legacyRepository,
    ) {}

    public function token(): string
    {
        $resolution = $this->tenantContext->current();
        if ($resolution !== null) {
            $row = $this->connectionTokens->getByConnectionId($resolution->connectionId);
            if ($row !== null && $row->accessToken !== '') {
                return $row->accessToken;
            }
        }

        $cached = $this->legacyRepository->getToken();
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        return (string) config('messenger-bot.page_access_token', '');
    }

    public function source(): string
    {
        $resolution = $this->tenantContext->current();
        if ($resolution !== null) {
            $row = $this->connectionTokens->getByConnectionId($resolution->connectionId);
            if ($row !== null && $row->accessToken !== '') {
                return 'connection';
            }
        }

        $cached = $this->legacyRepository->getToken();
        if ($cached !== null && $cached !== '') {
            return 'cache';
        }

        return trim((string) config('messenger-bot.page_access_token', '')) !== '' ? 'config' : '';
    }
}
