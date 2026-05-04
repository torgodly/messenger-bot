<?php

namespace MessengerBot\Profile;

use MessengerBot\Http\GraphClient;

/**
 * Subscribes the Meta App (from the Page token) to Page webhook fields via
 * {@code POST /me/subscribed_apps}. With a **Page** access token, {@code me} is the Page.
 *
 * @see https://developers.facebook.com/docs/graph-api/reference/page/subscribed_apps/
 */
class PageWebhookSubscriber
{
    public function __construct(
        protected GraphClient $graph,
    ) {}

    /**
     * @param  list<string>|null  $fields  Defaults to {@code config('messenger-bot.webhook_fields')}.
     * @return array<string, mixed>
     */
    public function subscribe(?array $fields = null): array
    {
        $fields ??= (array) config('messenger-bot.webhook_fields', []);
        $fields = array_values(array_filter(array_map('strval', $fields)));
        if ($fields === []) {
            throw new \InvalidArgumentException('No webhook fields configured to subscribe.');
        }

        return $this->graph->post(
            'me/subscribed_apps',
            [],
            ['subscribed_fields' => implode(',', $fields)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function currentSubscriptions(): array
    {
        return $this->graph->get('me/subscribed_apps', []);
    }
}
