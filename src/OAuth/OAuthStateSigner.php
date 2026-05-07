<?php

namespace MessengerBot\OAuth;

/**
 * HMAC helper for optional multi-tenant fields embedded in OAuth state cache payload.
 */
final class OAuthStateSigner
{
    public static function sign(string $tenantId, string $connectionId, string $secret): string
    {
        $payload = $tenantId."\n".$connectionId;

        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $tenantId, string $connectionId, string $secret, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals(self::sign($tenantId, $connectionId, $secret), $signature);
    }
}
