<?php

namespace MessengerBot\Templates\Typed;

/**
 * Price adjustment row (Meta {@code adjustments} item).
 */
readonly class ReceiptAdjustment
{
    public function __construct(
        public string $name,
        public float $amount,
    ) {}

    /**
     * @return array{name: string, amount: float}
     */
    public function toMetaFields(): array
    {
        return [
            'name' => $this->name,
            'amount' => $this->amount,
        ];
    }
}
