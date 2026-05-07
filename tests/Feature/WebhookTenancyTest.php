<?php

use Illuminate\Support\Facades\Event;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Contracts\PageAccessTokenSource;
use MessengerBot\Events\MessageReceived;
use MessengerBot\Facades\MessengerBot;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Kernel\Contracts\TenantResolver;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Webhook\WebhookProcessor;

class FixedPageTenantResolver implements TenantResolver
{
    public function __construct(
        protected string $pageId,
        protected TenantResolution $resolution,
    ) {}

    public function resolveFromPageId(string $pageId): ?TenantResolution
    {
        return $pageId === $this->pageId ? $this->resolution : null;
    }
}

test('webhook processes messaging when tenancy disabled', function () {
    config(['messenger-bot.tenancy.enabled' => false]);

    $seen = false;
    MessengerBot::hears('ping', function () use (&$seen) {
        $seen = true;
    });

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'PAGE99',
            'messaging' => [[
                'sender' => ['id' => 'USER1'],
                'recipient' => ['id' => 'PAGE99'],
                'timestamp' => 123,
                'message' => ['mid' => 'm.1', 'text' => 'ping'],
            ]],
        ]],
    ];

    app(WebhookProcessor::class)->process($payload);

    expect($seen)->toBeTrue();
});

test('webhook uses tenant resolver when tenancy enabled', function () {
    config(['messenger-bot.tenancy.enabled' => true]);
    config(['messenger-bot.tenancy.fallback_to_legacy_when_unresolved' => false]);
    config(['messenger-bot.tenancy.skip_entry_when_unresolved' => true]);

    $resolution = new TenantResolution(new TenantId('t1'), new ConnectionId('c1'), 'PAGE99');
    $this->app->singleton(TenantResolver::class, fn () => new FixedPageTenantResolver('PAGE99', $resolution));

    app(ConnectionTokenRepository::class)->put([
        'access_token' => 'conn-page-token',
        'expires_at' => null,
        'page_id' => 'PAGE99',
        'tenant_id' => 't1',
        'connection_id' => 'c1',
    ]);

    app(PageAccessTokenRepository::class)->forget();

    $tokenUsed = null;
    MessengerBot::hears('ping', function ($bot) use (&$tokenUsed) {
        $tokenUsed = app(PageAccessTokenSource::class)->token();
    });

    Event::fake([MessageReceived::class]);

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'PAGE99',
            'messaging' => [[
                'sender' => ['id' => 'USER1'],
                'recipient' => ['id' => 'PAGE99'],
                'timestamp' => 123,
                'message' => ['mid' => 'm.1', 'text' => 'ping'],
            ]],
        ]],
    ];

    app(WebhookProcessor::class)->process($payload);

    expect($tokenUsed)->toBe('conn-page-token');
});

test('webhook skips unresolved page when configured', function () {
    config(['messenger-bot.tenancy.enabled' => true]);
    config(['messenger-bot.tenancy.fallback_to_legacy_when_unresolved' => false]);
    config(['messenger-bot.tenancy.skip_entry_when_unresolved' => true]);

    $this->app->singleton(TenantResolver::class, fn () => new FixedPageTenantResolver('OTHER', new TenantResolution(
        new TenantId('t1'),
        new ConnectionId('c1'),
        'OTHER',
    )));

    $hit = false;
    MessengerBot::hears('ping', function () use (&$hit) {
        $hit = true;
    });

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'UNKNOWN_PAGE',
            'messaging' => [[
                'sender' => ['id' => 'USER1'],
                'recipient' => ['id' => 'UNKNOWN_PAGE'],
                'timestamp' => 123,
                'message' => ['mid' => 'm.1', 'text' => 'ping'],
            ]],
        ]],
    ];

    app(WebhookProcessor::class)->process($payload);

    expect($hit)->toBeFalse();
});

afterEach(function () {
    MessengerBot::reset();
});
