<?php

namespace MessengerBot\Templates;

use InvalidArgumentException;

/**
 * Vertical list (2–4 rows) with optional large hero on the first row.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/template/list
 */
class ListTemplateBuilder
{
    /**
     * @param  list<array<string, mixed>>  $elements  2–4 list rows (title, subtitle, image_url, default_action, buttons — see Meta reference)
     * @param  'large'|'compact'  $topElementStyle  When `large`, the first element should include `image_url`.
     * @param  list<array<string, mixed>>  $buttons  Optional global button(s) under the list (see Meta limits)
     * @return array<string, mixed>
     */
    public static function attachment(array $elements, string $topElementStyle = 'compact', array $buttons = []): array
    {
        $n = count($elements);
        if ($n < 2 || $n > 4) {
            throw new InvalidArgumentException('List template requires between 2 and 4 elements.');
        }

        $style = strtolower($topElementStyle);
        if (! in_array($style, ['large', 'compact'], true)) {
            throw new InvalidArgumentException('top_element_style must be large or compact.');
        }

        $payload = [
            'template_type' => 'list',
            'top_element_style' => $style,
            'elements' => array_values($elements),
        ];

        if ($buttons !== []) {
            $payload['buttons'] = $buttons;
        }

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => $payload,
            ],
        ];
    }
}
