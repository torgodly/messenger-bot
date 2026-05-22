<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when tenant resolution used the connection-token page index (DB row had no page_id yet).
 * Host apps may persist {@code page_id} on their {@see MessengerConnectable} model.
 */
final readonly class ConnectablePageIdSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $connectionId,
        public string $pageId,
    ) {}
}
