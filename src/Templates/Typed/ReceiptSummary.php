<?php

namespace MessengerBot\Templates\Typed;

/**
 * Receipt totals block (Meta {@code summary} object).
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt
 */
readonly class ReceiptSummary
{
    public function __construct(
        public float $totalCost,
        public ?float $subtotal = null,
        public ?float $shippingCost = null,
        public ?float $totalTax = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetaFields(): array
    {
        $out = ['total_cost' => $this->totalCost];
        if ($this->subtotal !== null) {
            $out['subtotal'] = $this->subtotal;
        }
        if ($this->shippingCost !== null) {
            $out['shipping_cost'] = $this->shippingCost;
        }
        if ($this->totalTax !== null) {
            $out['total_tax'] = $this->totalTax;
        }

        return $out;
    }
}
