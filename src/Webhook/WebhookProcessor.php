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

            foreach ($this->entryIterator->messagingEvents($entry) as $event) {
                $this->processMessagingEvent($event);
            }

            foreach ($this->entryIterator->changes($entry) as $change) {
                $this->processFeedChange($change);
            }
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

    /**
     * @param  array<string, mixed>  $change
     */
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
