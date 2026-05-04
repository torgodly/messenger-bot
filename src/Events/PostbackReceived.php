<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use MessengerBot\Messages\Postback;

class PostbackReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Postback $postback,
    ) {}
}
