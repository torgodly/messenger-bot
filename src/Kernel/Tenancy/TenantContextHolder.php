<?php

namespace MessengerBot\Kernel\Tenancy;

/**
 * Holds the active tenant resolution for the current webhook entry / scoped job (single-threaded per request).
 */
final class TenantContextHolder
{
    private ?TenantResolution $current = null;

    private int $depth = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(?TenantResolution $resolution, callable $callback): mixed
    {
        if ($resolution === null) {
            return $callback();
        }

        $previous = $this->current;
        $this->current = $resolution;
        $this->depth++;

        try {
            return $callback();
        } finally {
            $this->depth--;
            $this->current = $previous;
        }
    }

    public function current(): ?TenantResolution
    {
        return $this->current;
    }

    public function isActive(): bool
    {
        return $this->current !== null;
    }
}
