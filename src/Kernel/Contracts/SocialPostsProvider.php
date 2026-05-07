<?php

namespace MessengerBot\Kernel\Contracts;

use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Posts\PostsSyncResult;

/**
 * Future: Instagram, Threads, LinkedIn, etc. Facebook Page implementation is {@see SyncsFacebookPagePosts}.
 */
interface SocialPostsProvider
{
    public function providerKey(): string;

    public function syncPosts(PostsSyncRequest $request): PostsSyncResult;
}
