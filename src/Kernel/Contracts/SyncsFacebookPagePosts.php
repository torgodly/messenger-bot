<?php

namespace MessengerBot\Kernel\Contracts;

use MessengerBot\Kernel\Posts\PostsSyncOptions;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Posts\PostsSyncResult;
use MessengerBot\Kernel\Tenancy\ConnectionId;

/**
 * Fetches and caches Facebook Page posts (Graph {@see https://developers.facebook.com/docs/graph-api/reference/page/feed}).
 */
interface SyncsFacebookPagePosts
{
    public function sync(PostsSyncRequest $request, ?PostsSyncOptions $options = null): PostsSyncResult;

    /**
     * Bumps the logical posts cache version so existing cached lists miss on next sync.
     */
    public function bumpPostsCacheVersion(ConnectionId $connectionId): int;
}
