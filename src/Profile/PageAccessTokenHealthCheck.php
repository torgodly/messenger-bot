<?php

namespace MessengerBot\Profile;

use MessengerBot\Http\GraphClient;

/**
 * Lightweight Graph call to ensure the Page access token is accepted by Meta.
 */
class PageAccessTokenHealthCheck
{
    public function __construct(
        protected GraphClient $graph,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assertValid(): array
    {
        return $this->graph->get('me', ['fields' => 'id,name']);
    }
}
