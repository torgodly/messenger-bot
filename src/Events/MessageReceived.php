<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MessengerBot\Messages\IncomingMessage;

class MessageReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public IncomingMessage $message,
    ) {}
}
