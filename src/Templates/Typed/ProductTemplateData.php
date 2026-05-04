<?php

namespace MessengerBot\Templates\Typed;

use InvalidArgumentException;

/**
 * Product carousel: Page catalog retailer ids (1–10).
 */
readonly class ProductTemplateData
{
    /**
     * @param  non-empty-list<string>  $productIds
     */
    public function __construct(
        public array $productIds,
    ) {
        if ($this->productIds === []) {
            throw new InvalidArgumentException('Provide at least one catalog product id.');
        }
        if (count($this->productIds) > 10) {
            throw new InvalidArgumentException('At most 10 product ids per message.');
        }
    }

    /**
     * @return list<array{id: string}>
     */
    public function toElements(): array
    {
        return array_map(
            static fn (string $id) => ['id' => $id],
            array_values($this->productIds),
        );
    }
}
