<?php

use Illuminate\Support\Facades\Bus;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Facades\MessengerOAuth;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Laravel\Jobs\RefreshPostsCacheJob;
use MessengerBot\OAuth\OAuthStateSigner;
use MessengerBot\Support\MessengerConnection;

test('posts sync request for connectable maps keys and page id', function () {
    $c = new class implements MessengerConnectable
    {
        public function messengerTenantKey(): string
        {
            return 'tenant-a';
        }

        public function messengerConnectionKey(): string
        {
            return 'conn-b';
        }

        public function facebookPageId(): string
        {
            return 'page-999';
        }

        public function messengerDisplayName(): ?string
        {
            return null;
        }

        public function toMessengerTenantResolution(): TenantResolution
        {
            return MessengerConnection::toResolution($this);
        }
    };

    $req = PostsSyncRequest::forConnectable($c, maxPosts: 10);

    expect($req->pageId)->toBe('page-999')
        ->and($req->tenantId->value)->toBe('tenant-a')
        ->and($req->connectionId->value)->toBe('conn-b')
        ->and($req->maxPosts)->toBe(10);
});

test('oauth redirect url includes verifiable mt_sig', function () {
    config(['messenger-bot.app_secret' => 'unit-test-secret']);

    $c = new class implements MessengerConnectable
    {
        public function messengerTenantKey(): string
        {
            return 't1';
        }

        public function messengerConnectionKey(): string
        {
            return 'c1';
        }

        public function facebookPageId(): string
        {
            return 'p1';
        }

        public function messengerDisplayName(): ?string
        {
            return null;
        }

        public function toMessengerTenantResolution(): TenantResolution
        {
            return MessengerConnection::toResolution($this);
        }
    };

    $url = MessengerOAuth::facebookRedirectUrl($c);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    expect(isset($q['tenant_id']) && isset($q['connection_id']) && isset($q['mt_sig']))->toBeTrue()
        ->and($q['tenant_id'])->toBe('t1')
        ->and($q['connection_id'])->toBe('c1')
        ->and(OAuthStateSigner::verify('t1', 'c1', 'unit-test-secret', $q['mt_sig']))->toBeTrue();
});

test('refresh posts job for connectable dispatches with string keys', function () {
    Bus::fake();

    $c = new class implements MessengerConnectable
    {
        public function messengerTenantKey(): string
        {
            return 't';
        }

        public function messengerConnectionKey(): string
        {
            return 'c';
        }

        public function facebookPageId(): string
        {
            return 'page';
        }

        public function messengerDisplayName(): ?string
        {
            return null;
        }

        public function toMessengerTenantResolution(): TenantResolution
        {
            return MessengerConnection::toResolution($this);
        }
    };

    RefreshPostsCacheJob::forConnectable($c, bypassCache: true);

    Bus::assertDispatched(RefreshPostsCacheJob::class, function (RefreshPostsCacheJob $job): bool {
        return $job->tenantId === 't'
            && $job->connectionId === 'c'
            && $job->pageId === 'page'
            && $job->bypassCache === true;
    });
});
