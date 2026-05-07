<?php

namespace MessengerBot\Kernel\Contracts;

interface Clock
{
    public function nowUtc(): \DateTimeImmutable;
}
