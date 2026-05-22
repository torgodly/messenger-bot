<?php

namespace MessengerBot\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Laravel\Listeners\SyncPageProfileAfterOAuthListener;

class SyncPageProfileAfterOAuthJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $pageId,
        public bool $subscribeWebhooks,
        public bool $syncPersistentMenu,
        public bool $skipTokenCheck,
    ) {}

    public function uniqueId(): string
    {
        return 'messenger-bot:sync-page-profile:'.$this->connectionId;
    }

    public function handle(SyncPageProfileAfterOAuthListener $listener): void
    {
        $listener->syncProfile(
            new TenantResolution(
                new TenantId($this->tenantId),
                new ConnectionId($this->connectionId),
                $this->pageId,
            ),
            $this->subscribeWebhooks,
            $this->syncPersistentMenu,
            $this->skipTokenCheck,
        );
    }
}
