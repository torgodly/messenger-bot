<?php

namespace MessengerBot\Webhook;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use MessengerBot\Bot\Bot;
use MessengerBot\Dispatching\HandlerDispatcher;
use MessengerBot\Events\CommentCreated;
use MessengerBot\Events\MessageReceived;
use MessengerBot\Events\PostbackReceived;
use MessengerBot\Events\WebhookReceived;
use MessengerBot\Http\GraphClient;
use MessengerBot\Http\MessengerClient;
use MessengerBot\Kernel\Contracts\TenantResolver;
use MessengerBot\Kernel\Tenancy\TenantContextHolder;
use MessengerBot\Messages\Postback;
use MessengerBot\MessengerBotManager;
use MessengerBot\Routing\MessageRouter;

class WebhookProcessor
{
    public function __construct(
        protected MessengerBotManager $manager,
        protected MessageRouter $router,
        protected HandlerDispatcher $dispatcher,
        protected MessagingParser $messagingParser,
        protected FeedChangeParser $feedChangeParser,
        protected EntryIterator $entryIterator,
        protected Container $container,
        protected TenantContextHolder $tenantContext,
        protected TenantResolver $tenantResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        Event::dispatch(new WebhookReceived($payload));

        if (($payload['object'] ?? '') !== 'page') {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $this->processEntry($entry);
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function processEntry(array $entry): void
    {
        $pageId = isset($entry['id']) ? (string) $entry['id'] : '';
        $tenancyEnabled = (bool) config('messenger-bot.tenancy.enabled', false);

        if (! $tenancyEnabled || $pageId === '') {
            $this->tenantContext->run(null, fn () => $this->dispatchEntry($entry));

            return;
        }

        $resolution = $this->tenantResolver->resolveFromPageId($pageId);
        if ($resolution !== null) {
            $this->tenantContext->run($resolution, fn () => $this->dispatchEntry($entry));

            return;
        }

        if ((bool) config('messenger-bot.tenancy.skip_entry_when_unresolved', false)) {
            return;
        }

        if (! (bool) config('messenger-bot.tenancy.fallback_to_legacy_when_unresolved', true)) {
            return;
        }

        $this->tenantContext->run(null, fn () => $this->dispatchEntry($entry));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function dispatchEntry(array $entry): void
    {
        foreach ($this->entryIterator->messagingEvents($entry) as $event) {
            $this->processMessagingEvent($event);
        }

        foreach ($this->entryIterator->changes($entry) as $change) {
            $this->processFeedChange($change);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function processMessagingEvent(array $event): void
    {
        $postback = $this->messagingParser->parsePostback($event);
        if ($postback !== null) {
            Event::dispatch(new PostbackReceived($postback));
            $route = $this->router->matchPayload($postback->payload);
            if ($route !== null) {
                $this->dispatcher->dispatchPostback($route, $postback);

                return;
            }

            $this->maybeReplyDefaultGetStarted($postback);

            return;
        }

        $message = $this->messagingParser->parseMessage($event);
        if ($message === null) {
            return;
        }

        Event::dispatch(new MessageReceived($message));

        if ($message->hasQuickReply()) {
            $route = $this->router->matchPayload((string) $message->quickReplyPayload);
            if ($route !== null) {
                $this->dispatcher->dispatchIncoming($route, $message);

                return;
            }
        }

        $route = $this->router->matchIncomingMessage($message);
        if ($route !== null) {
            $this->dispatcher->dispatchIncoming($route, $message);

            return;
        }

        $fallback = $this->manager->getFallback();
        if ($fallback !== null) {
            $this->dispatcher->dispatchFallback($fallback, $message);
        }
    }

    protected function maybeReplyDefaultGetStarted(Postback $postback): void
    {
        $expected = trim((string) config('messenger-bot.get_started.payload', 'GET_STARTED'));
        if ($expected === '' || $postback->payload !== $expected) {
            return;
        }

        $reply = config('messenger-bot.get_started.default_reply');
        if (! is_string($reply) || trim($reply) === '') {
            return;
        }

        $bot = Bot::forMessaging(
            $this->container->make(MessengerClient::class),
            $this->container->make(GraphClient::class),
            null,
            $postback,
        );
        $bot->reply(trim($reply));
    }

    /**
     * @param  array<string, mixed>  $change
     */
    protected function processFeedChange(array $change): void
    {
        $comment = $this->feedChangeParser->parseComment($change);
        if ($comment === null) {
            return;
        }

        if (! $comment->isTopLevelOnPost()) {
            return;
        }

        Event::dispatch(new CommentCreated($comment));

        foreach ($this->manager->getCommentHandlers() as $registration) {
            if ($registration->matches($comment)) {
                $this->dispatcher->dispatchComment($registration->handler, $comment);
            }
        }
    }
}
