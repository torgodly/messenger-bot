<?php

use MessengerBot\Contracts\PageAccessTokenRepository;

test('messenger-bot:clear-page-token forgets cached oauth token', function () {
    /** @var PageAccessTokenRepository $repo */
    $repo = app(PageAccessTokenRepository::class);
    $repo->put([
        'access_token' => 'cached-token',
        'expires_at' => time() + 3600,
        'page_id' => '123',
    ]);
    expect($repo->getToken())->toBe('cached-token');

    $this->artisan('messenger-bot:clear-page-token')->assertSuccessful();

    expect($repo->getToken())->toBeNull();
});
