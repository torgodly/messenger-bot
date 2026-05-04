<?php

namespace MessengerBot\Templates\Typed;

/**
 * Single line on a receipt (Meta {@code elements} item).
 */
readonly class ReceiptLineItem
{
    public function __construct(
        public string $title,
        public float $price,
        public string $currency,
        public int $quantity = 1,
        public ?string $subtitle = null,
        public ?string $imageUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetaFields(): array
    {
        $out = [
            'title' => $this->title,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'currency' => $this->currency,
        ];
        if ($this->subtitle !== null && $this->subtitle !== '') {
            $out['subtitle'] = $this->subtitle;
        }
        if ($this->imageUrl !== null && $this->imageUrl !== '') {
            $out['image_url'] = $this->imageUrl;
        }

        return $out;
    }
}
