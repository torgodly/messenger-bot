<?php

namespace MessengerBot\Laravel\Support;

use MessengerBot\Kernel\Contracts\Clock;

final class SystemClock implements Clock
{
    public function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
