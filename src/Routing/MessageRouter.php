<?php

namespace MessengerBot\Routing;

use MessengerBot\Messages\IncomingMessage;
use MessengerBot\MessengerBotManager;

class MessageRouter
{
    public function __construct(
        protected MessengerBotManager $manager,
    ) {}

    public function matchPayload(string $payload): ?Route
    {
        foreach ($this->sortedRoutes() as $route) {
            if ($route->type === 'payload' && hash_equals($route->pattern, $payload)) {
                return $route;
            }
        }

        return null;
    }

    public function matchIncomingMessage(IncomingMessage $message): ?Route
    {
        $text = trim((string) ($message->text ?? ''));

        foreach ($this->sortedRoutes() as $route) {
            if ($route->type !== 'hears') {
                continue;
            }

            if ($this->matchesHears($route->pattern, $text)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @return list<Route>
     */
    protected function sortedRoutes(): array
    {
        $routes = $this->manager->getRoutes();
        usort($routes, static fn (Route $a, Route $b): int => $b->priority <=> $a->priority);

        return $routes;
    }

    protected function matchesHears(string $pattern, string $text): bool
    {
        if ($this->isRegexPattern($pattern)) {
            $result = @preg_match($pattern, $text);

            return $result === 1;
        }

        return strcasecmp($pattern, $text) === 0;
    }

    protected function isRegexPattern(string $pattern): bool
    {
        return strlen($pattern) >= 2 && $pattern[0] === '/' && str_ends_with($pattern, '/');
    }
}
