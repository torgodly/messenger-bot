<?php

namespace MessengerBot\Http;

use RuntimeException;

class GraphException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }
}
