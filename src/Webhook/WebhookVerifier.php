<?php

namespace MessengerBot\Webhook;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebhookVerifier
{
    public function verifyChallenge(Request $request): string
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode !== 'subscribe' || $challenge === null) {
            throw new AccessDeniedHttpException('Invalid verification request.');
        }

        $expected = (string) config('messenger-bot.verify_token', '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            throw new AccessDeniedHttpException('Invalid verify token.');
        }

        return (string) $challenge;
    }
}
