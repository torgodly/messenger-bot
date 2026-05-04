<?php

namespace MessengerBot\Webhook;

use MessengerBot\Messages\IncomingMessage;
use MessengerBot\Messages\Postback;

class MessagingParser
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function parsePostback(array $event): ?Postback
    {
        $pb = $event['postback'] ?? null;
        if (! is_array($pb) || ! isset($pb['payload'])) {
            return null;
        }

        $sender = (string) ($event['sender']['id'] ?? '');
        $recipient = (string) ($event['recipient']['id'] ?? '');

        return new Postback(
            $sender,
            $recipient,
            (string) $pb['payload'],
            isset($pb['title']) ? (string) $pb['title'] : null,
            $event['timestamp'] ?? null,
            $event,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function parseMessage(array $event): ?IncomingMessage
    {
        $message = $event['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        if (isset($message['is_echo']) && $message['is_echo']) {
            return null;
        }

        $sender = (string) ($event['sender']['id'] ?? '');
        $recipient = (string) ($event['recipient']['id'] ?? '');

        $text = isset($message['text']) ? (string) $message['text'] : null;
        $attachments = [];
        if (isset($message['attachments']) && is_array($message['attachments'])) {
            foreach ($message['attachments'] as $a) {
                if (is_array($a)) {
                    $attachments[] = $a;
                }
            }
        }

        $qr = null;
        if (isset($message['quick_reply']) && is_array($message['quick_reply'])) {
            $qr = isset($message['quick_reply']['payload']) ? (string) $message['quick_reply']['payload'] : null;
        }

        if ($text === null && $attachments === [] && $qr === null) {
            return null;
        }

        return new IncomingMessage(
            $sender,
            $recipient,
            $text,
            $attachments,
            $qr,
            $event['timestamp'] ?? null,
            $event,
        );
    }
}
