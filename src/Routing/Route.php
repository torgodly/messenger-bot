<?php

namespace MessengerBot\Routing;

class Route
{
    public function __construct(
        public string $type,
        public string $pattern,
        public mixed $handler,
        public int $priority = 0,
    ) {}
}
