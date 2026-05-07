<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

beforeEach(function () {
    config(['messenger-bot.tenancy.enabled' => false]);
    config(['messenger-bot.app_secret' => '']);

    app(PageAccessTokenRepository::class)->put([
        'access_token' => 'graph-test-token',
        'expires_at' => null,
        'page_id' => 'PAGE123',
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'PAGE123/posts')) {
            return Http::response([
                'data' => [
                    ['id' => 'p1', 'message' => 'a', 'created_time' => '2026-05-01T00:00:00+0000'],
                ],
                'paging' => [
                    'next' => 'https://graph.facebook.com/v24.0/next-page?access_token=graph-test-token',
                ],
            ], 200);
        }
        if (str_contains($url, 'next-page')) {
            return Http::response([
                'data' => [
                    ['id' => 'p2', 'message' => 'b', 'created_time' => '2026-05-02T00:00:00+0000'],
                ],
            ], 200);
        }

        return Http::response(['error' => ['message' => 'unexpected url '.$url]], 400);
    });
});

test('sync fetches paginated page posts with legacy token', function () {
    $svc = app(SyncsFacebookPagePosts::class);

    $result = $svc->sync(new PostsSyncRequest(
        new TenantId('legacy-tenant'),
        new ConnectionId('legacy-conn'),
        'PAGE123',
        since: new DateTimeImmutable('2026-05-01T00:00:00Z'),
        until: new DateTimeImmutable('2026-05-31T23:59:59Z'),
        maxPosts: 10,
    ));

    expect($result->cacheHit)->toBeFalse()
        ->and($result->apiCalls)->toBe(2)
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0]->id)->toBe('p1')
        ->and($result->items[1]->id)->toBe('p2');
});

test('second sync hits cache', function () {
    $svc = app(SyncsFacebookPagePosts::class);
    $req = new PostsSyncRequest(
        new TenantId('legacy-tenant'),
        new ConnectionId('legacy-conn'),
        'PAGE123',
        since: new DateTimeImmutable('2026-05-01T00:00:00Z'),
        until: new DateTimeImmutable('2026-05-31T23:59:59Z'),
        maxPosts: 10,
    );

    $svc->sync($req);
    Http::fake(); // no outbound expected

    $second = $svc->sync($req);
    expect($second->cacheHit)->toBeTrue()
        ->and($second->apiCalls)->toBe(0)
        ->and($second->items)->toHaveCount(2);
});
