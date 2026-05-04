<?php

namespace MessengerBot\Templates;

class QuickRepliesBuilder
{
    /**
     * @param  list<array<string, mixed>>  $quickReplies
     * @return array<string, mixed>
     */
    public static function normalize(array $quickReplies): array
    {
        $out = [];
        foreach ($quickReplies as $qr) {
            if (! is_array($qr)) {
                continue;
            }
            $out[] = $qr;
        }

        return ['quick_replies' => $out];
    }
}
