<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use MessengerBot\Events\ConnectionTokenStored;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\OAuth\CompleteOAuthPageLink;
use MessengerBot\OAuth\PendingOAuthPages;
use MessengerBot\Tests\Fixtures\AllowAllPageLinkValidator;
use MessengerBot\Tests\Fixtures\RejectPageLinkValidator;

beforeEach(function () {
    config([
        'messenger-bot.app_id' => 'app-id',
        'messenger-bot.app_secret' => 'app-secret',
        'messenger-bot.graph_version' => 'v24.0',
        'messenger-bot.oauth.success_redirect_path' => '/oauth/done',
        'messenger-bot.oauth.pending_pages_redirect_url' => 'https://app.test/meta/pick-page',
        'messenger-bot.oauth.validates_page_link' => AllowAllPageLinkValidator::class,
        'messenger-bot.tenancy.enabled' => true,
        'messenger-bot.oauth.dual_write_legacy_token' => false,
    ]);
});

function fakeOAuthGraphResponses(array $pages): void
{
    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short-user-token'])
            ->push(['access_token' => 'long-user-token']),
        'graph.facebook.com/*/me/accounts*' => Http::response([
            'data' => array_map(fn (array $p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'access_token' => $p['access_token'],
            ], $pages),
        ]),
        'graph.facebook.com/*/debug_token*' => Http::response([
            'data' => ['is_valid' => true, 'expires_at' => time() + 86400],
        ]),
    ]);
}

function putOAuthState(?array $mt = ['tenant_id' => 't1', 'connection_id' => 'c1']): string
{
    $state = 'test-state-'.uniqid();
    Cache::put('messenger_bot:oauth_state:'.$state, [
        'redirect_uri' => 'https://app.test/messenger-bot/oauth/facebook/callback',
        'issued_at' => time(),
        'mt' => $mt,
    ], now()->addMinutes(10));

    return $state;
}

test('oauth callback with zero pages returns 400', function () {
    fakeOAuthGraphResponses([]);
    $state = putOAuthState();

    $response = $this->get('/messenger-bot/oauth/facebook/callback?code=abc&state='.$state);

    $response->assertStatus(400);
});

test('oauth callback with one page stores token and redirects success', function () {
    Event::fake([ConnectionTokenStored::class]);
    fakeOAuthGraphResponses([
        ['id' => 'PAGE1', 'name' => 'Shop', 'access_token' => 'page-token-1'],
    ]);
    $state = putOAuthState();

    $response = $this->get('/messenger-bot/oauth/facebook/callback?code=abc&state='.$state);

    $response->assertRedirect('/oauth/done');
    expect(app(ConnectionTokenRepository::class)->getByPageId('PAGE1')?->accessToken)->toBe('page-token-1');
    Event::assertDispatched(ConnectionTokenStored::class);
});

test('oauth callback with three pages does not store token and redirects with opaque token', function () {
    Event::fake([ConnectionTokenStored::class]);
    $pages = [
        ['id' => 'P1', 'name' => 'A', 'access_token' => 't1'],
        ['id' => 'P2', 'name' => 'B', 'access_token' => 't2'],
        ['id' => 'P3', 'name' => 'C', 'access_token' => 't3'],
    ];
    fakeOAuthGraphResponses($pages);
    $state = putOAuthState();

    $response = $this->get('/messenger-bot/oauth/facebook/callback?code=abc&state='.$state);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://app.test/meta/pick-page');
    expect($response->headers->get('Location'))->toContain('token=');
    expect(app(ConnectionTokenRepository::class)->getByPageId('P1'))->toBeNull();

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $q);
    expect(isset($q['token']))->toBeTrue();
    $pulled = PendingOAuthPages::pull($q['token']);
    expect($pulled)->not->toBeNull()
        ->and(count($pulled['pages']))->toBe(3);
    Event::assertNotDispatched(ConnectionTokenStored::class);
});

test('pending pages pull and complete stores one token', function () {
    Event::fake([ConnectionTokenStored::class]);
    Http::fake([
        'graph.facebook.com/*/debug_token*' => Http::response([
            'data' => ['is_valid' => true, 'expires_at' => time() + 86400],
        ]),
    ]);

    $token = PendingOAuthPages::store([
        ['id' => 'PICKED', 'name' => 'Chosen', 'access_token' => 'picked-token'],
        ['id' => 'OTHER', 'name' => 'Other', 'access_token' => 'other-token'],
    ], ['tenant_id' => 't1', 'connection_id' => 'c1']);

    $payload = PendingOAuthPages::pull($token);
    expect($payload)->not->toBeNull();

    app(CompleteOAuthPageLink::class)->complete($payload['pages'][0], $payload['mt']);

    expect(app(ConnectionTokenRepository::class)->getByPageId('PICKED')?->accessToken)->toBe('picked-token');
    Event::assertDispatched(ConnectionTokenStored::class);
});

test('expired pending pages pull returns null', function () {
    $token = PendingOAuthPages::store([
        ['id' => 'X', 'name' => 'X', 'access_token' => 'x'],
    ], null);

    PendingOAuthPages::pull($token);
    expect(PendingOAuthPages::pull($token))->toBeNull();
});

test('page link validator reject prevents put and sets session flash key', function () {
    config(['messenger-bot.oauth.validates_page_link' => RejectPageLinkValidator::class]);
    Event::fake([ConnectionTokenStored::class]);
    fakeOAuthGraphResponses([
        ['id' => 'PAGE1', 'name' => 'Shop', 'access_token' => 'page-token-1'],
    ]);
    $state = putOAuthState();

    $response = $this->get('/messenger-bot/oauth/facebook/callback?code=abc&state='.$state);

    $response->assertRedirect('/oauth/done');
    $response->assertSessionHas('messenger_bot_oauth_error');
    expect(app(ConnectionTokenRepository::class)->getByPageId('PAGE1'))->toBeNull();
    Event::assertNotDispatched(ConnectionTokenStored::class);
});

test('preferred page id with single match completes without pending redirect', function () {
    config(['messenger-bot.oauth.preferred_page_id' => 'P2']);
    Event::fake([ConnectionTokenStored::class]);
    fakeOAuthGraphResponses([
        ['id' => 'P1', 'name' => 'A', 'access_token' => 't1'],
        ['id' => 'P2', 'name' => 'B', 'access_token' => 't2'],
        ['id' => 'P3', 'name' => 'C', 'access_token' => 't3'],
    ]);
    $state = putOAuthState();

    $response = $this->get('/messenger-bot/oauth/facebook/callback?code=abc&state='.$state);

    $response->assertRedirect('/oauth/done');
    expect(app(ConnectionTokenRepository::class)->getByPageId('P2')?->accessToken)->toBe('t2');
    Event::assertDispatched(ConnectionTokenStored::class);
});
