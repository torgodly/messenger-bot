<?php

namespace MessengerBot\Templates;

class ButtonTemplateBuilder
{
    /**
     * @param  list<array<string, mixed>>  $buttons
     * @return array<string, mixed>
     */
    public static function attachment(string $text, array $buttons): array
    {
        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $text,
                    'buttons' => $buttons,
                ],
            ],
        ];
    }
}
