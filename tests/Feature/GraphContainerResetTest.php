<?php

use MessengerBot\Http\GraphClient;
use MessengerBot\Profile\PageAccessTokenHealthCheck;
use MessengerBot\Support\GraphContainerReset;

test('graph container reset forgets graph client and page token health check singletons', function () {
    $graphBefore = app(GraphClient::class);
    $healthBefore = app(PageAccessTokenHealthCheck::class);

    GraphContainerReset::forget($this->app);

    $graphAfter = app(GraphClient::class);
    $healthAfter = app(PageAccessTokenHealthCheck::class);

    expect(spl_object_id($graphAfter))->not->toBe(spl_object_id($graphBefore));
    expect(spl_object_id($healthAfter))->not->toBe(spl_object_id($healthBefore));
});
