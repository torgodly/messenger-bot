<?php

namespace MessengerBot\Support;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;

final class MessengerConnectableValidator
{
    /**
     * @return non-empty-string|null Error message when invalid; null when OK.
     */
    public static function validateClass(string $class): ?string
    {
        $class = trim($class);
        if ($class === '') {
            return 'Model class name is empty.';
        }

        if (! class_exists($class)) {
            return "Class does not exist: {$class}";
        }

        if (! is_a($class, Model::class, true)) {
            return 'Class must extend '.Model::class.": {$class}";
        }

        if (! is_a($class, MessengerConnectable::class, true)) {
            return 'Class must implement '.MessengerConnectable::class.": {$class}";
        }

        return null;
    }
}
