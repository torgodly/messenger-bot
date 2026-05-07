<?php

use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Http\ContextualPageAccessTokenProvider;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\Kernel\Credentials\PageAccessTokenRecord;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantContextHolder;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;

test('contextual provider uses connection token when tenant context is active', function () {
    $legacy = new class implements PageAccessTokenRepository
    {
        public function getToken(): ?string
        {
            return 'legacy-token';
        }

        public function getExpiresAt(): ?int
        {
            return null;
        }

        public function getPageId(): ?string
        {
            return null;
        }

        public function put(array $payload): void {}

        public function forget(): void {}

        public function shouldRefreshSoon(int $bufferSeconds): bool
        {
            return false;
        }
    };

    $connections = new class implements ConnectionTokenRepository
    {
        public function getByConnectionId(ConnectionId $connectionId): ?PageAccessTokenRecord
        {
            if ($connectionId->value !== 'conn-1') {
                return null;
            }

            return new PageAccessTokenRecord(
                'conn-token',
                null,
                'page-1',
                new TenantId('tenant-1'),
                new ConnectionId('conn-1'),
            );
        }

        public function getByPageId(string $pageId): ?PageAccessTokenRecord
        {
            return null;
        }

        public function put(array $payload): void {}

        public function forget(ConnectionId $connectionId): void {}

        public function bumpPostsCacheVersion(ConnectionId $connectionId): int
        {
            return 1;
        }

        public function getPostsCacheVersion(ConnectionId $connectionId): int
        {
            return 0;
        }
    };

    $holder = new TenantContextHolder;
    $provider = new ContextualPageAccessTokenProvider($holder, $connections, $legacy);

    $resolution = new TenantResolution(new TenantId('tenant-1'), new ConnectionId('conn-1'), 'page-1');

    $holder->run($resolution, function () use ($provider) {
        expect($provider->token())->toBe('conn-token');
        expect($provider->source())->toBe('connection');
    });

    expect($provider->token())->toBe('legacy-token');
    expect($provider->source())->toBe('cache');
});
