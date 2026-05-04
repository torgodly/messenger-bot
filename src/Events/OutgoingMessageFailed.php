<?php

namespace MessengerBot\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class OutgoingMessageFailed
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $request
     */
    public function __construct(
        public array $request,
        public Throwable $exception,
    ) {}
}
