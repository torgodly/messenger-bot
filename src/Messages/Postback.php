<?php

namespace MessengerBot\Messages;

readonly class Postback
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $senderId,
        public string $recipientId,
        public string $payload,
        public ?string $title,
        public int|string|null $timestamp,
        public array $raw,
    ) {}
}
