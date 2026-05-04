<?php

namespace MessengerBot\Contracts;

interface ConversationStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function forget(string $key): void;
}
