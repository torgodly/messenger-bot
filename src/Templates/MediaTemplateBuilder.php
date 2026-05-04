<?php

namespace MessengerBot\Templates;

use InvalidArgumentException;

class MediaTemplateBuilder
{
    /**
     * Structured image or video with optional buttons.
     *
     * Use a public **Facebook URL** in {@see $url}, or an **attachment_id** from Meta’s
     * [Attachment Upload API](https://developers.facebook.com/docs/messenger-platform/reference/attachment-upload-api)
     * for external assets. Do not pass both.
     *
     * @param  'image'|'video'  $mediaType
     * @param  list<array<string, mixed>>  $buttons
     * @return array<string, mixed>
     */
    public static function attachment(string $mediaType, ?string $url = null, ?string $attachmentId = null, array $buttons = []): array
    {
        if (! in_array($mediaType, ['image', 'video'], true)) {
            throw new InvalidArgumentException('media_type must be image or video.');
        }

        $hasUrl = $url !== null && $url !== '';
        $hasId = $attachmentId !== null && $attachmentId !== '';

        if ($hasUrl && $hasId) {
            throw new InvalidArgumentException('Pass either url or attachment_id, not both.');
        }

        if (! $hasUrl && ! $hasId) {
            throw new InvalidArgumentException('Media template requires a Facebook url or attachment_id.');
        }

        $element = ['media_type' => $mediaType];
        if ($hasUrl) {
            $element['url'] = $url;
        } else {
            $element['attachment_id'] = $attachmentId;
        }

        if ($buttons !== []) {
            $element['buttons'] = $buttons;
        }

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'media',
                    'elements' => [$element],
                ],
            ],
        ];
    }
}
