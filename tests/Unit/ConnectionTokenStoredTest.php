<?php

use Illuminate\Support\Facades\Event;
use MessengerBot\Events\ConnectionTokenStored;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;

test('connection token stored event is dispatched on put', function () {
    Event::fake([ConnectionTokenStored::class]);

    app(ConnectionTokenRepository::class)->put([
        'access_token' => 'page-token-abc',
        'expires_at' => null,
        'page_id' => 'PAGE-1',
        'tenant_id' => 'tenant-1',
        'connection_id' => 'conn-1',
    ]);

    Event::assertDispatched(ConnectionTokenStored::class, function (ConnectionTokenStored $event): bool {
        return $event->tenantId === 'tenant-1'
            && $event->connectionId === 'conn-1'
            && $event->pageId === 'PAGE-1'
            && $event->accessToken === 'page-token-abc';
    });
});
