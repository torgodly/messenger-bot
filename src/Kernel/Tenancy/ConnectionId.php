<?php

namespace MessengerBot\Kernel\Tenancy;

/**
 * Identifies a connected Facebook Page (and token row) within a tenant.
 */
final readonly class ConnectionId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if ($this->value === '') {
            throw new \InvalidArgumentException('ConnectionId must not be empty.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
