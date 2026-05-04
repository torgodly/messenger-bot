<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutgoingMessageSending
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public array $body,
    ) {}
}
