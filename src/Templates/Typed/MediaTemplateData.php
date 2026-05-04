<?php

namespace MessengerBot\Templates\Typed;

use InvalidArgumentException;

/**
 * Media template: Facebook-hosted URL **or** upload API attachment id (never both).
 */
readonly class MediaTemplateData
{
    /**
     * @param  'image'|'video'  $mediaType
     * @param  list<array<string, mixed>>  $buttons
     */
    private function __construct(
        public string $mediaType,
        public ?string $facebookUrl,
        public ?string $attachmentId,
        public array $buttons,
    ) {
        if (! in_array($this->mediaType, ['image', 'video'], true)) {
            throw new InvalidArgumentException('mediaType must be image or video.');
        }
    }

    /**
     * @param  'image'|'video'  $mediaType
     * @param  list<array<string, mixed>>  $buttons
     */
    public static function fromFacebookUrl(string $mediaType, string $url, array $buttons = []): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('Facebook url must not be empty.');
        }

        return new self($mediaType, $url, null, $buttons);
    }

    /**
     * @param  'image'|'video'  $mediaType
     * @param  list<array<string, mixed>>  $buttons
     */
    public static function fromAttachmentId(string $mediaType, string $attachmentId, array $buttons = []): self
    {
        if ($attachmentId === '') {
            throw new InvalidArgumentException('attachment_id must not be empty.');
        }

        return new self($mediaType, null, $attachmentId, $buttons);
    }

    /**
     * @return 'image'|'video'
     */
    public function mediaType(): string
    {
        return $this->mediaType;
    }

    public function facebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function attachmentId(): ?string
    {
        return $this->attachmentId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buttons(): array
    {
        return $this->buttons;
    }
}
