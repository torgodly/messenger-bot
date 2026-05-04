<?php

namespace MessengerBot;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Macroable;
use MessengerBot\Bot\Bot;
use MessengerBot\Http\Controllers\WebhookController;
use MessengerBot\Messages\IncomingMessage;
use MessengerBot\Routing\CommentRegistration;
use MessengerBot\Routing\Route as MessengerRoute;

class MessengerBotManager
{
    use Macroable;

    /** @var list<MessengerRoute> */
    protected array $routes = [];

    /** @var list<CommentRegistration> */
    protected array $commentHandlers = [];

    /** @var (callable(Bot, IncomingMessage): mixed)|null */
    protected $fallbackHandler = null;

    protected bool $routesRegistered = false;

    /**
     * Clear registered handlers and route flag (intended for automated tests).
     */
    public function reset(): void
    {
        $this->routes = [];
        $this->commentHandlers = [];
        $this->fallbackHandler = null;
    }

    public function routes(?array $middleware = null): void
    {
        if (config('messenger-bot.webhook.auto_register', true)) {
            return;
        }

        if ($this->routesRegistered) {
            return;
        }

        $path = (string) config('messenger-bot.webhook.path', '/webhook/messenger');
        $stack = $middleware ?? (array) config('messenger-bot.webhook.middleware', []);

        Route::match(['get', 'post'], $path, [WebhookController::class, 'handle'])
            ->middleware($stack);

        $this->routesRegistered = true;
    }

    /**
     * @param  string|array<int, string>  $pattern
     */
    public function hears(string|array $pattern, callable $handler, int $priority = 0): self
    {
        foreach ((array) $pattern as $p) {
            $this->routes[] = new MessengerRoute('hears', (string) $p, $handler, $priority);
        }

        return $this;
    }

    public function payload(string $payload, callable $handler, int $priority = 0): self
    {
        $this->routes[] = new MessengerRoute('payload', $payload, $handler, $priority);

        return $this;
    }

    /**
     * @param  string|array<int, string>|null  $onlyForPostIds  Graph post ID(s). Omit or null = all Page comments; pass one ID or an array to limit this handler.
     */
    public function onComment(callable $handler, string|array|null $onlyForPostIds = null): self
    {
        $ids = null;
        if ($onlyForPostIds !== null) {
            $flat = array_values(array_unique(array_filter(array_map('strval', (array) $onlyForPostIds))));
            $ids = $flat === [] ? null : $flat;
        }

        $this->commentHandlers[] = new CommentRegistration($handler, $ids);

        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;

        return $this;
    }

    /**
     * @return list<MessengerRoute>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return list<CommentRegistration>
     */
    public function getCommentHandlers(): array
    {
        return $this->commentHandlers;
    }

    /**
     * @return (callable(Bot, IncomingMessage): mixed)|null
     */
    public function getFallback(): mixed
    {
        return $this->fallbackHandler;
    }
}
