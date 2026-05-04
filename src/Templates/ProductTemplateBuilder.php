<?php

namespace MessengerBot\Templates;

use InvalidArgumentException;

/**
 * Renders catalog products by id (Page-owned catalog).
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/product
 */
class ProductTemplateBuilder
{
    /**
     * @param  list<array<string, mixed>|string>  $elements  Each item is a product id string or `['id' => '…']`
     * @return array<string, mixed>
     */
    public static function attachment(array $elements): array
    {
        if ($elements === []) {
            throw new InvalidArgumentException('Product template requires at least one product id.');
        }

        if (count($elements) > 10) {
            throw new InvalidArgumentException('Product template supports at most 10 products per message.');
        }

        $normalized = [];
        foreach ($elements as $el) {
            if (is_string($el)) {
                $normalized[] = ['id' => $el];

                continue;
            }
            if (is_array($el) && isset($el['id'])) {
                $normalized[] = ['id' => (string) $el['id']];

                continue;
            }
            throw new InvalidArgumentException('Each product element must be a string id or an array with an id key.');
        }

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'product',
                    'elements' => $normalized,
                ],
            ],
        ];
    }
}
