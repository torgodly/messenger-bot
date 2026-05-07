<?php

namespace MessengerBot\Facades;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Facade;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Laravel\MessengerOAuthService;

/**
 * @method static string facebookRedirectUrl(MessengerConnectable $connectable)
 * @method static RedirectResponse redirectToFacebook(MessengerConnectable $connectable)
 *
 * @see MessengerOAuthService
 */
class MessengerOAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MessengerOAuthService::class;
    }
}
