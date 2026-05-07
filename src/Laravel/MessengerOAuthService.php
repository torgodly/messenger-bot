<?php

namespace MessengerBot\Laravel;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\OAuth\OAuthStateSigner;

final class MessengerOAuthService
{
    public function __construct(
        protected UrlGenerator $url,
    ) {}

    public function facebookRedirectUrl(MessengerConnectable $connectable): string
    {
        $tenantId = $connectable->messengerTenantKey();
        $connectionId = $connectable->messengerConnectionKey();
        $secret = trim((string) config('messenger-bot.app_secret', ''));
        if ($secret === '') {
            throw new \RuntimeException('MESSENGER_BOT_APP_SECRET is required to build signed multi-tenant OAuth URLs.');
        }

        $sig = OAuthStateSigner::sign($tenantId, $connectionId, $secret);
        $base = $this->url->route('messenger-bot.oauth.redirect', [], true);
        $qs = http_build_query([
            'tenant_id' => $tenantId,
            'connection_id' => $connectionId,
            'mt_sig' => $sig,
        ]);

        return $base.(str_contains($base, '?') ? '&' : '?').$qs;
    }

    public function redirectToFacebook(MessengerConnectable $connectable): RedirectResponse
    {
        return Redirect::away($this->facebookRedirectUrl($connectable));
    }
}
