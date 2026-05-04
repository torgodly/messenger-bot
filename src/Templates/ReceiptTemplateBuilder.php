<?php

namespace MessengerBot\Templates;

/**
 * Order / purchase confirmation layout (Meta “receipt” template).
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt
 */
class ReceiptTemplateBuilder
{
    /**
     * @param  array<string, mixed>  $fields  Meta fields: recipient_name, order_number, currency, payment_method,
     *                                        order_url, timestamp, address, summary, adjustments, elements, etc.
     * @return array<string, mixed>
     */
    public static function attachment(array $fields): array
    {
        $payload = array_merge(['template_type' => 'receipt'], $fields);

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => $payload,
            ],
        ];
    }
}
