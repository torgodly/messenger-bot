<?php

namespace MessengerBot\Kernel\Posts;

use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

final class PostsCacheKey
{
    /**
     * @param  list<string>  $fields
     */
    public static function filterHash(?int $sinceUnix, ?int $untilUnix, array $fields, int $limitPerRequest): string
    {
        $normalized = [
            'since' => $sinceUnix,
            'until' => $untilUnix,
            'fields' => $fields,
            'limit' => $limitPerRequest,
        ];

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = serialize($normalized);
        }

        return hash('sha256', $json);
    }

    public static function build(
        TenantId $tenantId,
        ConnectionId $connectionId,
        string $pageId,
        string $filterHash,
        int $postsCacheVersion,
    ): string {
        return 'mb:v1:tenant:'.$tenantId->value
            .':conn:'.$connectionId->value
            .':page:'.$pageId
            .':posts:v'.$postsCacheVersion
            .':'.$filterHash;
    }
}
