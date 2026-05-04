<?php

namespace MessengerBot\Profile;

use InvalidArgumentException;
use MessengerBot\Http\GraphClient;

/**
 * Sets Messenger {@code persistent_menu} via {@code POST /me/messenger_profile}.
 *
 * @see https://developers.facebook.com/docs/messenger-platform/send-messages/persistent-menu
 */
class PersistentMenuConfigurator
{
    public function __construct(
        protected GraphClient $graph,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $menu  Meta {@code persistent_menu} array (locale blocks).
     * @return array<string, mixed>
     */
    public function sync(array $menu): array
    {
        if ($menu === []) {
            throw new InvalidArgumentException('persistent_menu payload must not be empty.');
        }

        $started = trim((string) config('messenger-bot.get_started.payload', 'GET_STARTED'));
        if ($started === '') {
            $started = 'GET_STARTED';
        }

        $body = [
            'get_started' => ['payload' => $started],
            'persistent_menu' => array_values($menu),
        ];

        return $this->graph->post('me/messenger_profile', [], $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncFromConfig(): array
    {
        $menu = config('messenger-bot.persistent_menu');
        if (! is_array($menu) || $menu === []) {
            throw new InvalidArgumentException('Set messenger-bot.persistent_menu to a non-empty array in config.');
        }

        return $this->sync($menu);
    }
}
