<?php

namespace MessengerBot\Support;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;

final class TenancyConfigurationValidator
{
    /**
     * @return non-empty-string|null Error message when invalid; null when OK or validation not applicable.
     */
    public static function connectionModelError(): ?string
    {
        if (! (bool) config('messenger-bot.tenancy.enabled', false)) {
            return null;
        }

        $custom = config('messenger-bot.tenancy.resolver');
        if (is_string($custom) && trim($custom) !== '') {
            return null;
        }

        $class = trim((string) config('messenger-bot.tenancy.connection_model', ''));
        if ($class === '') {
            return null;
        }

        if (! class_exists($class)) {
            return "MESSENGER_BOT_TENANCY_CONNECTION_MODEL class does not exist: {$class}";
        }

        if (! is_a($class, Model::class, true)) {
            return 'MESSENGER_BOT_TENANCY_CONNECTION_MODEL must extend '.Model::class.": {$class}";
        }

        if (! is_a($class, MessengerConnectable::class, true)) {
            return 'MESSENGER_BOT_TENANCY_CONNECTION_MODEL must implement '.MessengerConnectable::class.": {$class}";
        }

        return null;
    }
}
