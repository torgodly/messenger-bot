<?php

namespace MessengerBot\Webhook;

class EntryIterator
{
    /**
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    public function messagingEvents(array $entry): array
    {
        $m = $entry['messaging'] ?? [];

        return is_array($m) ? array_values(array_filter($m, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    public function changes(array $entry): array
    {
        $c = $entry['changes'] ?? [];

        return is_array($c) ? array_values(array_filter($c, 'is_array')) : [];
    }
}
