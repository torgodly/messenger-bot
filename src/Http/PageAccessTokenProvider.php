<?php

namespace MessengerBot\Http;

use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Contracts\PageAccessTokenSource;

/**
 * Resolves the Page access token: cached OAuth token first, then optional config (.env) fallback.
 */
class PageAccessTokenProvider implements PageAccessTokenSource
{
    public function __construct(
        protected PageAccessTokenRepository $repository,
    ) {}

    public function token(): string
    {
        $cached = $this->repository->getToken();
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        return (string) config('messenger-bot.page_access_token', '');
    }

    public function source(): string
    {
        $cached = $this->repository->getToken();
        if ($cached !== null && $cached !== '') {
            return 'cache';
        }

        return trim((string) config('messenger-bot.page_access_token', '')) !== '' ? 'config' : '';
    }
}
