<?php

namespace MessengerBot\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use MessengerBot\Events\ConnectionTokenStored;
use MessengerBot\Http\GraphException;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantContextHolder;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;
use MessengerBot\Laravel\Jobs\SyncPageProfileAfterOAuthJob;
use MessengerBot\Profile\PageAccessTokenHealthCheck;
use MessengerBot\Profile\PageProfileCoordinator;
use MessengerBot\Support\GraphContainerReset;

final class SyncPageProfileAfterOAuthListener
{
    public function __construct(
        protected TenantContextHolder $tenantContext,
        protected PageProfileCoordinator $coordinator,
        protected PageAccessTokenHealthCheck $tokenHealth,
    ) {}

    public function handle(ConnectionTokenStored $event): void
    {
        $cfg = (array) config('messenger-bot.after_connection_token_stored', []);
        $subscribe = (bool) ($cfg['subscribe_webhooks'] ?? false);
        $menu = (bool) ($cfg['sync_persistent_menu'] ?? false);

        if (! $subscribe && ! $menu) {
            return;
        }

        $resolution = new TenantResolution(
            new TenantId($event->tenantId),
            new ConnectionId($event->connectionId),
            $event->pageId,
        );

        if ((bool) ($cfg['queue'] ?? false)) {
            $job = new SyncPageProfileAfterOAuthJob(
                $event->tenantId,
                $event->connectionId,
                $event->pageId,
                $subscribe,
                $menu,
                (bool) ($cfg['skip_token_check'] ?? false),
            );

            $connection = $this->appQueueConnection();
            if ($connection !== null) {
                $job->onConnection($connection);
            }

            $queueName = trim((string) ($cfg['queue_name'] ?? ''));
            if ($queueName !== '') {
                $job->onQueue($queueName);
            }

            dispatch($job);

            return;
        }

        try {
            $this->syncProfile(
                $resolution,
                $subscribe,
                $menu,
                (bool) ($cfg['skip_token_check'] ?? false),
            );
        } catch (\Throwable $e) {
            $this->logFailure($event, $e);
            $this->maybeRetryQueued($event, $cfg, $subscribe, $menu);
        }
    }

    public function syncProfile(
        TenantResolution $resolution,
        bool $subscribe,
        bool $menu,
        bool $skipTokenCheck,
    ): void {
        GraphContainerReset::forget(app());

        $this->tenantContext->run($resolution, function () use ($subscribe, $menu, $skipTokenCheck): void {
            if (! $skipTokenCheck) {
                $this->tokenHealth->assertValid();
            }

            if ($subscribe) {
                $this->coordinator->subscribeWebhooks();
            }

            if ($menu) {
                $this->coordinator->syncPersistentMenuFromConfig();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    protected function maybeRetryQueued(ConnectionTokenStored $event, array $cfg, bool $subscribe, bool $menu): void
    {
        if (! (bool) ($cfg['queue_retry_on_failure'] ?? true)) {
            return;
        }

        if ((bool) ($cfg['queue'] ?? false)) {
            return;
        }

        $job = new SyncPageProfileAfterOAuthJob(
            $event->tenantId,
            $event->connectionId,
            $event->pageId,
            $subscribe,
            $menu,
            (bool) ($cfg['skip_token_check'] ?? false),
        );

        $connection = $this->appQueueConnection();
        if ($connection !== null) {
            $job->onConnection($connection);
        }

        $queueName = trim((string) ($cfg['queue_name'] ?? ''));
        if ($queueName !== '') {
            $job->onQueue($queueName);
        }

        dispatch($job)->delay(now()->addSeconds(30));
    }

    protected function logFailure(ConnectionTokenStored $event, \Throwable $e): void
    {
        $context = [
            'tenant_id' => $event->tenantId,
            'connection_id' => $event->connectionId,
            'page_id' => $event->pageId,
            'exception' => $e,
        ];

        if ($e instanceof GraphException) {
            $context['status'] = $e->statusCode;
        }

        Log::error('Messenger post-OAuth profile sync failed.', $context);
    }

    protected function appQueueConnection(): ?string
    {
        $name = config('messenger-bot.after_connection_token_stored.queue_connection');
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return trim($name);
    }
}
