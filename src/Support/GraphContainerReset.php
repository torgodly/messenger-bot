<?php

namespace MessengerBot\Support;

use Illuminate\Contracts\Foundation\Application;
use MessengerBot\Http\GraphClient;
use MessengerBot\Http\MessengerClient;
use MessengerBot\Profile\PageAccessTokenHealthCheck;
use MessengerBot\Profile\PageProfileCoordinator;
use MessengerBot\Profile\PageWebhookSubscriber;
use MessengerBot\Profile\PersistentMenuConfigurator;

/**
 * Clears Graph-related singletons so a new Page token from config/cache is picked up.
 */
class GraphContainerReset
{
    public static function forget(Application $app): void
    {
        foreach ([
            PageProfileCoordinator::class,
            PersistentMenuConfigurator::class,
            PageWebhookSubscriber::class,
            MessengerClient::class,
            PageAccessTokenHealthCheck::class,
            GraphClient::class,
        ] as $abstract) {
            $app->forgetInstance($abstract);
        }
    }
}
