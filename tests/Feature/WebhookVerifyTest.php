<?php

test('webhook challenge returns plain text hub challenge', function () {
    $token = (string) config('messenger-bot.verify_token');

    $response = $this->get('/webhook/messenger?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => $token,
        'hub_challenge' => 'CHALLENGE_ACCEPTED',
    ]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    expect($response->getContent())->toBe('CHALLENGE_ACCEPTED');
});

test('webhook challenge rejects invalid verify token', function () {
    $this->get('/webhook/messenger?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'x',
    ]))->assertForbidden();
});
