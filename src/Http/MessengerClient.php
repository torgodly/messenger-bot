<?php

namespace MessengerBot\Http;

use Illuminate\Support\Facades\Event;
use MessengerBot\Events\OutgoingMessageFailed;
use MessengerBot\Events\OutgoingMessageSending;
use MessengerBot\Events\OutgoingMessageSent;

class MessengerClient
{
    public function __construct(
        protected GraphClient $graph,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function sendRaw(array $body): array
    {
        Event::dispatch(new OutgoingMessageSending($body));

        try {
            $result = $this->graph->post('me/messages', [], $body);
            Event::dispatch(new OutgoingMessageSent($body, $result));

            return $result;
        } catch (\Throwable $e) {
            Event::dispatch(new OutgoingMessageFailed($body, $e));

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function text(string $recipientPsid, string $text, array $options = []): array
    {
        $payload = array_merge([
            'recipient' => ['id' => $recipientPsid],
            'message' => ['text' => $text],
        ], $options);

        return $this->sendRaw($payload);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function sendMessage(string $recipientPsid, array $message, array $options = []): array
    {
        $payload = array_merge([
            'recipient' => ['id' => $recipientPsid],
            'message' => $message,
        ], $options);

        return $this->sendRaw($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function senderAction(string $recipientPsid, string $action): array
    {
        return $this->sendRaw([
            'recipient' => ['id' => $recipientPsid],
            'sender_action' => $action,
        ]);
    }

    /**
     * Private reply to a Page comment via Send API (recipient.comment_id).
     *
     * @param  array<string, mixed>  $options  Top-level Send API fields (e.g. messaging_type, tag).
     * @return array<string, mixed>
     */
    public function sendPrivateReplyToComment(string $commentId, string $text, array $options = []): array
    {
        $payload = array_merge([
            'recipient' => ['comment_id' => $commentId],
            'message' => ['text' => $text],
        ], $options);

        return $this->sendRaw($payload);
    }
}
