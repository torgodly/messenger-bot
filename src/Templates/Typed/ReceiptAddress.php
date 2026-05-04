<?php

namespace MessengerBot\Templates\Typed;

/**
 * Shipping address on a receipt (Meta {@code address} object).
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt
 */
readonly class ReceiptAddress
{
    public function __construct(
        public string $street1,
        public string $city,
        public string $postalCode,
        public string $state,
        public string $country,
        public ?string $street2 = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetaFields(): array
    {
        $out = [
            'street_1' => $this->street1,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'state' => $this->state,
            'country' => $this->country,
        ];
        if ($this->street2 !== null && $this->street2 !== '') {
            $out['street_2'] = $this->street2;
        }

        return $out;
    }
}
