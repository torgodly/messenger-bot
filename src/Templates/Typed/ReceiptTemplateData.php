<?php

namespace MessengerBot\Templates\Typed;

use InvalidArgumentException;

/**
 * Typed receipt template payload (maps to Meta receipt fields).
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt
 */
readonly class ReceiptTemplateData
{
    /**
     * @param  list<ReceiptLineItem>  $lineItems
     * @param  list<ReceiptAdjustment>|null  $adjustments
     * @param  array<string, mixed>  $extra  Additional Meta keys merged last (escape hatch)
     */
    public function __construct(
        public string $recipientName,
        public string $orderNumber,
        public string $currency,
        public string $paymentMethod,
        public string $timestamp,
        public ReceiptSummary $summary,
        public array $lineItems,
        public ?string $orderUrl = null,
        public ?ReceiptAddress $shippingAddress = null,
        public ?array $adjustments = null,
        public array $extra = [],
    ) {
        if ($this->lineItems === []) {
            throw new InvalidArgumentException('Receipt requires at least one ReceiptLineItem.');
        }
        foreach ($this->lineItems as $i => $item) {
            if (! $item instanceof ReceiptLineItem) {
                throw new InvalidArgumentException('Line item at index '.$i.' must be a ReceiptLineItem instance.');
            }
        }
        if ($this->adjustments !== null) {
            foreach ($this->adjustments as $j => $adj) {
                if (! $adj instanceof ReceiptAdjustment) {
                    throw new InvalidArgumentException('Adjustment at index '.$j.' must be a ReceiptAdjustment instance.');
                }
            }
        }
    }

    /**
     * Meta payload fields (without {@code template_type}; the builder adds it).
     *
     * @return array<string, mixed>
     */
    public function toMetaFields(): array
    {
        $out = [
            'recipient_name' => $this->recipientName,
            'order_number' => $this->orderNumber,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod,
            'timestamp' => $this->timestamp,
            'summary' => $this->summary->toMetaFields(),
            'elements' => array_map(
                static fn (ReceiptLineItem $line) => $line->toMetaFields(),
                $this->lineItems,
            ),
        ];

        if ($this->orderUrl !== null && $this->orderUrl !== '') {
            $out['order_url'] = $this->orderUrl;
        }

        if ($this->shippingAddress !== null) {
            $out['address'] = $this->shippingAddress->toMetaFields();
        }

        if ($this->adjustments !== null && $this->adjustments !== []) {
            $out['adjustments'] = array_map(
                static fn (ReceiptAdjustment $a) => $a->toMetaFields(),
                $this->adjustments,
            );
        }

        if ($this->extra !== []) {
            $out = array_merge($out, $this->extra);
        }

        return $out;
    }
}
