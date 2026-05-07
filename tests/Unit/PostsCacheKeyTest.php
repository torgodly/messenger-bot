<?php

use MessengerBot\Kernel\Posts\PostsCacheKey;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

test('posts cache key includes tenant connection page and version', function () {
    $h = PostsCacheKey::filterHash(100, 200, ['id', 'message'], 25);
    $key = PostsCacheKey::build(
        new TenantId('t1'),
        new ConnectionId('c1'),
        'page99',
        $h,
        3,
    );

    expect($key)->toContain('tenant:t1')
        ->and($key)->toContain('conn:c1')
        ->and($key)->toContain('page:page99')
        ->and($key)->toContain(':posts:v3:');
});
