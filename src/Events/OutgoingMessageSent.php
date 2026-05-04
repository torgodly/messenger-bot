<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutgoingMessageSent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public array $request,
        public array $response,
    ) {}
}
