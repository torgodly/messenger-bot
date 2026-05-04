<?php

namespace MessengerBot\Webhook;

class SignatureValidator
{
    public function isValid(string $rawBody, ?string $signatureHeader): bool
    {
        if (! config('messenger-bot.webhook.signature_enabled', true)) {
            return true;
        }

        $secret = (string) config('messenger-bot.app_secret', '');
        if ($secret === '') {
            return false;
        }

        if ($signatureHeader === null || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
        $provided = $signatureHeader;

        return hash_equals($expected, $provided);
    }
}
