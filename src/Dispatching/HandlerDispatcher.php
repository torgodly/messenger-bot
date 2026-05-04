<?php

namespace MessengerBot\Dispatching;

use Illuminate\Container\Container;
use MessengerBot\Bot\Bot;
use MessengerBot\Comments\Comment;
use MessengerBot\Http\GraphClient;
use MessengerBot\Http\MessengerClient;
use MessengerBot\Messages\IncomingMessage;
use MessengerBot\Messages\Postback;
use MessengerBot\Routing\Route;

class HandlerDispatcher
{
    public function __construct(
        protected Container $container,
    ) {}

    public function dispatchIncoming(?Route $route, IncomingMessage $message): void
    {
        if ($route === null) {
            return;
        }

        $bot = Bot::forMessaging(
            $this->container->make(MessengerClient::class),
            $this->container->make(GraphClient::class),
            $message,
            null,
        );

        $this->container->call($route->handler, [
            'bot' => $bot,
            'message' => $message,
        ]);
    }

    public function dispatchPostback(?Route $route, Postback $postback): void
    {
        if ($route === null) {
            return;
        }

        $bot = Bot::forMessaging(
            $this->container->make(MessengerClient::class),
            $this->container->make(GraphClient::class),
            null,
            $postback,
        );

        $this->container->call($route->handler, [
            'bot' => $bot,
            'postback' => $postback,
        ]);
    }

    /**
     * @param  callable(Bot, IncomingMessage): mixed  $fallback
     */
    public function dispatchFallback(callable $fallback, IncomingMessage $message): void
    {
        $bot = Bot::forMessaging(
            $this->container->make(MessengerClient::class),
            $this->container->make(GraphClient::class),
            $message,
            null,
        );

        $this->container->call($fallback, [
            'bot' => $bot,
            'message' => $message,
        ]);
    }

    /**
     * @param  callable(Bot, Comment): mixed  $handler
     */
    public function dispatchComment(callable $handler, Comment $comment): void
    {
        $bot = Bot::forCommentContext(
            $this->container->make(MessengerClient::class),
            $this->container->make(GraphClient::class),
        );

        $this->container->call($handler, [
            'bot' => $bot,
            'comment' => $comment,
        ]);
    }
}
