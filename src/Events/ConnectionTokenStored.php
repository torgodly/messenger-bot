<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ConnectionTokenStored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $connectionId,
        public string $pageId,
        public string $accessToken,
        public ?int $expiresAt,
    ) {}
}
