<?php

namespace MessengerBot\Contracts;

/**
 * Optional extension point: implement and bind in the container to short-circuit
 * webhook handling before routing (e.g. spam filtering).
 */
interface WebhookFilter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function shouldHandle(array $payload): bool;
}
