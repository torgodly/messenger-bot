<?php

use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Laravel\Listeners\SyncPageProfileAfterOAuthListener;
use MessengerBot\Profile\PageAccessTokenHealthCheck;
use MessengerBot\Profile\PageProfileCoordinator;
use MessengerBot\Profile\PageWebhookSubscriber;
use MessengerBot\Profile\PersistentMenuConfigurator;

test('sync page profile after oauth listener subscribes and syncs menu in tenant context', function () {
    config([
        'messenger-bot.persistent_menu' => [
            [
                'locale' => 'default',
                'composer_input_disabled' => false,
                'call_to_actions' => [
                    ['type' => 'postback', 'title' => 'Help', 'payload' => 'HELP'],
                ],
            ],
        ],
    ]);

    $subscriber = Mockery::mock(PageWebhookSubscriber::class);
    $subscriber->shouldReceive('subscribe')->once()->andReturn(['result' => 'ok']);

    $menu = Mockery::mock(PersistentMenuConfigurator::class);
    $menu->shouldReceive('sync')->once()->andReturn(['result' => 'ok']);

    $health = Mockery::mock(PageAccessTokenHealthCheck::class);
    $health->shouldReceive('assertValid')->once();

    app()->instance(PageWebhookSubscriber::class, $subscriber);
    app()->instance(PersistentMenuConfigurator::class, $menu);
    app()->instance(PageAccessTokenHealthCheck::class, $health);
    app()->instance(PageProfileCoordinator::class, new PageProfileCoordinator($subscriber, $menu, $health));

    $listener = app(SyncPageProfileAfterOAuthListener::class);

    $listener->syncProfile(
        new TenantResolution(new TenantId('t1'), new ConnectionId('c1'), 'PAGE1'),
        subscribe: true,
        menu: true,
        skipTokenCheck: false,
    );

    expect(true)->toBeTrue();
});
