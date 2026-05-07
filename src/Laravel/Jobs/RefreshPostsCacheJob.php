<?php

namespace MessengerBot\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Kernel\Contracts\SyncsFacebookPagePosts;
use MessengerBot\Kernel\Posts\PostsSyncOptions;
use MessengerBot\Kernel\Posts\PostsSyncRequest;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;

/**
 * Background refresh of cached Page posts (re-fetches Graph and repopulates cache).
 */
class RefreshPostsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $pageId,
        public bool $bypassCache = false,
    ) {}

    public static function forConnectable(MessengerConnectable $connectable, bool $bypassCache = false): PendingDispatch
    {
        return static::dispatch(
            $connectable->messengerTenantKey(),
            $connectable->messengerConnectionKey(),
            $connectable->facebookPageId(),
            $bypassCache,
        );
    }

    public function handle(SyncsFacebookPagePosts $posts): void
    {
        $request = new PostsSyncRequest(
            new TenantId($this->tenantId),
            new ConnectionId($this->connectionId),
            $this->pageId,
        );

        $options = new PostsSyncOptions(
            bypassCache: $this->bypassCache,
            useStampedeLock: true,
        );

        $posts->sync($request, $options);
    }
}
