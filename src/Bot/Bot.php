<?php

namespace MessengerBot\Bot;

use InvalidArgumentException;
use LogicException;
use MessengerBot\Http\GraphClient;
use MessengerBot\Http\GraphException;
use MessengerBot\Http\MessengerClient;
use MessengerBot\Messages\IncomingMessage;
use MessengerBot\Messages\Postback;
use MessengerBot\Templates\ButtonTemplateBuilder;
use MessengerBot\Templates\GenericTemplateBuilder;
use MessengerBot\Templates\ListTemplateBuilder;
use MessengerBot\Templates\MediaTemplateBuilder;
use MessengerBot\Templates\ProductTemplateBuilder;
use MessengerBot\Templates\QuickRepliesBuilder;
use MessengerBot\Templates\ReceiptTemplateBuilder;
use MessengerBot\Templates\Typed\ListTemplateData;
use MessengerBot\Templates\Typed\MediaTemplateData;
use MessengerBot\Templates\Typed\ProductTemplateData;
use MessengerBot\Templates\Typed\ReceiptTemplateData;

class Bot
{
    public function __construct(
        protected MessengerClient $messenger,
        protected GraphClient $graph,
        protected ?IncomingMessage $incoming,
        protected ?Postback $postback,
        protected string $recipientPsid,
        protected bool $commentContext = false,
    ) {}

    public static function forMessaging(
        MessengerClient $messenger,
        GraphClient $graph,
        ?IncomingMessage $incoming,
        ?Postback $postback,
    ): self {
        $recipient = $incoming?->senderId ?? $postback?->senderId ?? '';

        return new self($messenger, $graph, $incoming, $postback, $recipient, false);
    }

    public static function forCommentContext(
        MessengerClient $messenger,
        GraphClient $graph,
    ): self {
        return new self($messenger, $graph, null, null, '', true);
    }

    public function isCommentContext(): bool
    {
        return $this->commentContext;
    }

    public function incoming(): ?IncomingMessage
    {
        return $this->incoming;
    }

    public function postback(): ?Postback
    {
        return $this->postback;
    }

    public function recipientPsid(): string
    {
        return $this->recipientPsid;
    }

    public function types(string $senderAction = 'typing_on'): void
    {
        $this->messenger->senderAction($this->requireRecipient(), $senderAction);
    }

    public function reply(string $text, array $options = []): void
    {
        $this->messenger->text($this->requireRecipient(), $text, $this->mergeMessagingOptions($options));
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     */
    public function replyGenericTemplate(array $elements, array $options = []): void
    {
        $normalized = [];
        foreach ($elements as $element) {
            if (! is_array($element)) {
                throw new InvalidArgumentException('Each generic template element must be an array.');
            }
            $normalized[] = $this->normalizeGenericElement($element);
        }

        $message = GenericTemplateBuilder::attachment($normalized);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * @param  list<array<string, mixed>>  $buttons
     */
    public function buttonTemplate(string $text, array $buttons, array $options = []): void
    {
        $message = ButtonTemplateBuilder::attachment($text, $buttons);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * Image or video template (Facebook-hosted URL or attachment upload id).
     *
     * @param  'image'|'video'  $mediaType
     * @param  list<array<string, mixed>>  $buttons
     */
    public function mediaTemplate(string $mediaType, ?string $url = null, ?string $attachmentId = null, array $buttons = [], array $options = []): void
    {
        $message = MediaTemplateBuilder::attachment($mediaType, $url, $attachmentId, $buttons);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * Order receipt / confirmation (structured fields per Meta).
     *
     * @param  array<string, mixed>  $receiptFields
     */
    public function receiptTemplate(array $receiptFields, array $options = []): void
    {
        $message = ReceiptTemplateBuilder::attachment($receiptFields);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * Catalog product carousel by retailer product id.
     *
     * @param  list<array<string, mixed>|string>  $products
     */
    public function productTemplate(array $products, array $options = []): void
    {
        $message = ProductTemplateBuilder::attachment($products);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * List template (2–4 vertical rows).
     *
     * @param  list<array<string, mixed>>  $elements
     * @param  'large'|'compact'  $topElementStyle
     * @param  list<array<string, mixed>>  $globalButtons
     */
    public function listTemplate(array $elements, string $topElementStyle = 'compact', array $globalButtons = [], array $options = []): void
    {
        $message = ListTemplateBuilder::attachment($elements, $topElementStyle, $globalButtons);
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * Receipt template using typed line items, summary, and optional address (IDE-friendly).
     *
     * @see ReceiptTemplateData
     */
    public function receiptFrom(ReceiptTemplateData $data, array $options = []): void
    {
        $this->receiptTemplate($data->toMetaFields(), $options);
    }

    /**
     * Media template from {@see MediaTemplateData} (factory picks URL vs attachment id).
     */
    public function mediaFrom(MediaTemplateData $data, array $options = []): void
    {
        $this->mediaTemplate(
            $data->mediaType(),
            $data->facebookUrl(),
            $data->attachmentId(),
            $data->buttons(),
            $options,
        );
    }

    /**
     * Product template from catalog ids (1–10).
     */
    public function productFrom(ProductTemplateData $data, array $options = []): void
    {
        $this->productTemplate($data->toElements(), $options);
    }

    /**
     * List template from typed rows (2–4) and style enum.
     */
    public function listFrom(ListTemplateData $data, array $options = []): void
    {
        $this->listTemplate(
            $data->elementPayloads(),
            $data->topStyleValue(),
            $data->globalButtons,
            $options,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $quickReplies
     */
    public function quickReplies(string $text, array $quickReplies, array $options = []): void
    {
        $message = array_merge(
            ['text' => $text],
            QuickRepliesBuilder::normalize($quickReplies),
        );
        $this->messenger->sendMessage($this->requireRecipient(), $message, $this->mergeMessagingOptions($options));
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    public function replyAttachment(array $attachment, array $options = []): void
    {
        $this->messenger->sendMessage($this->requireRecipient(), [
            'attachment' => $attachment,
        ], $this->mergeMessagingOptions($options));
    }

    public function replyImage(string $url, array $options = []): void
    {
        $this->replyAttachment([
            'type' => 'image',
            'payload' => ['url' => $url, 'is_reusable' => true],
        ], $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function replyToComment(string $commentId, string $message, array $options = []): array
    {
        unset($options);

        return $this->graph->replyToPublicComment($commentId, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function privateReplyToComment(string $commentId, string $message, array $options = []): array
    {
        $merged = $this->mergeMessagingOptions($options);

        try {
            return $this->graph->privateReplyToCommentEdge($commentId, $message);
        } catch (GraphException) {
            return $this->messenger->sendPrivateReplyToComment($commentId, $message, $merged);
        }
    }

    /**
     * Send a Messenger message using a Page-scoped user ID (PSID).
     *
     * @param  array<string, mixed>|string  $message
     * @return array<string, mixed>
     */
    public function sendMessageToPsid(string $psid, array|string $message, array $options = []): array
    {
        if (is_string($message)) {
            return $this->messenger->text($psid, $message, $this->mergeMessagingOptions($options));
        }

        return $this->messenger->sendMessage($psid, $message, $this->mergeMessagingOptions($options));
    }

    /**
     * @deprecated Use sendMessageToPsid(); comment Graph IDs are not always valid PSIDs.
     *
     * @param  array<string, mixed>|string  $message
     * @return array<string, mixed>
     */
    public function sendDirectMessage(string $recipientId, array|string $message, array $options = []): array
    {
        return $this->sendMessageToPsid($recipientId, $message, $options);
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    protected function normalizeGenericElement(array $element): array
    {
        $out = [
            'title' => (string) ($element['title'] ?? ''),
        ];

        if (isset($element['subtitle'])) {
            $out['subtitle'] = (string) $element['subtitle'];
        }

        if (isset($element['image_url'])) {
            $out['image_url'] = (string) $element['image_url'];
        }

        if (isset($element['default_action']) && is_array($element['default_action'])) {
            $out['default_action'] = $element['default_action'];
        }

        if (isset($element['buttons']) && is_array($element['buttons'])) {
            $out['buttons'] = $element['buttons'];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mergeMessagingOptions(array $options): array
    {
        $allowed = ['messaging_type', 'tag', 'notification_type', 'persona_id'];
        $merged = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $options)) {
                $merged[$key] = $options[$key];
            }
        }

        return $merged;
    }

    protected function requireRecipient(): string
    {
        if ($this->recipientPsid === '') {
            throw new LogicException('No Messenger recipient in this context. reply() is only available in message or postback handlers.');
        }

        return $this->recipientPsid;
    }
}
