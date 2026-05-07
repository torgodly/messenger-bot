<?php

namespace MessengerBot\Kernel\Tenancy;

/**
 * Opaque tenant identifier (e.g. ULID) for SaaS isolation.
 */
final readonly class TenantId implements \Stringable
{
    public function __construct(
        public string $value,
    ) {
        if ($this->value === '') {
            throw new \InvalidArgumentException('TenantId must not be empty.');
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
