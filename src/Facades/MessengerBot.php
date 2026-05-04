<?php

namespace MessengerBot\Facades;

use Illuminate\Support\Facades\Facade;
use MessengerBot\MessengerBotManager;

/**
 * @method static void routes(?array $middleware = null) Only when webhook.auto_register is false; never from routes/web.php (CSRF 419).
 * @method static \MessengerBot\MessengerBotManager hears(string|array $pattern, callable $handler, int $priority = 0)
 * @method static \MessengerBot\MessengerBotManager payload(string $payload, callable $handler, int $priority = 0)
 * @method static \MessengerBot\MessengerBotManager onComment(callable $handler, string|array|null $onlyForPostIds = null)
 * @method static \MessengerBot\MessengerBotManager fallback(callable $handler)
 * @method static void reset()
 */
class MessengerBot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MessengerBotManager::class;
    }
}
