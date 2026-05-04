<?php

namespace MessengerBot\Tests;

use Illuminate\Foundation\Application;
use MessengerBot\MessengerBotServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            MessengerBotServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('messenger-bot.verify_token', 'test-verify-token');
        $app['config']->set('messenger-bot.signature_enabled', false);
        $app['config']->set('messenger-bot.page_access_token', 'test-page-token');
        $app['config']->set('messenger-bot.graph_version', 'v24.0');
        $app['config']->set('messenger-bot.app_secret', '');
    }
}
