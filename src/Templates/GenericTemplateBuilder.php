<?php

namespace MessengerBot\Templates;

class GenericTemplateBuilder
{
    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array<string, mixed>
     */
    public static function attachment(array $elements): array
    {
        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => $elements,
                ],
            ],
        ];
    }
}
