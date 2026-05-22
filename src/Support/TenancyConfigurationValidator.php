<?php

namespace MessengerBot\Support;

use Illuminate\Database\Eloquent\Model;
use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Contracts\ValidatesMessengerPageLink;

final class TenancyConfigurationValidator
{
    /**
     * @return list<non-empty-string>
     */
    public static function errors(): array
    {
        $errors = [];
        $connection = self::connectionModelError();
        if ($connection !== null) {
            $errors[] = $connection;
        }

        $pageLink = self::validatesPageLinkError();
        if ($pageLink !== null) {
            $errors[] = $pageLink;
        }

        $pendingUrl = self::pendingPagesRedirectUrlError();
        if ($pendingUrl !== null) {
            $errors[] = $pendingUrl;
        }

        return $errors;
    }

    /**
     * @return non-empty-string|null
     */
    public static function validatesPageLinkError(): ?string
    {
        if (! (bool) config('messenger-bot.tenancy.enabled', false)) {
            return null;
        }

        $class = trim((string) config('messenger-bot.oauth.validates_page_link', ''));
        if ($class === '') {
            return 'MESSENGER_BOT_VALIDATES_PAGE_LINK is required when multi-tenant mode is enabled.';
        }

        if (! class_exists($class)) {
            return "MESSENGER_BOT_VALIDATES_PAGE_LINK class does not exist: {$class}";
        }

        if (! is_a($class, ValidatesMessengerPageLink::class, true)) {
            return 'MESSENGER_BOT_VALIDATES_PAGE_LINK must implement '.ValidatesMessengerPageLink::class.": {$class}";
        }

        return null;
    }

    /**
     * @return non-empty-string|null
     */
    public static function pendingPagesRedirectUrlError(): ?string
    {
        if (! (bool) config('messenger-bot.tenancy.enabled', false)) {
            return null;
        }

        $url = trim((string) config('messenger-bot.oauth.pending_pages_redirect_url', ''));
        if ($url === '') {
            return 'MESSENGER_BOT_OAUTH_PENDING_PAGES_URL is required when multi-tenant mode is enabled.';
        }

        return null;
    }

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
