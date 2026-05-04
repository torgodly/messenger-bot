<?php

namespace MessengerBot\Messages;

readonly class IncomingMessage
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $senderId,
        public string $recipientId,
        public ?string $text,
        public array $attachments,
        public ?string $quickReplyPayload,
        public int|string|null $timestamp,
        public array $raw,
    ) {}

    public function hasQuickReply(): bool
    {
        return $this->quickReplyPayload !== null && $this->quickReplyPayload !== '';
    }
}
