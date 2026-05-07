<?php

namespace MessengerBot\Laravel\Tenancy;

/**
 * Default multi-tenant resolver: uses {@see config('messenger-bot.tenancy.connection_model')} and
 * {@see config('messenger-bot.tenancy.connection_page_id_column')}. Used when tenancy is enabled and
 * no custom {@see config('messenger-bot.tenancy.resolver')} is set.
 */
final class ConfigurableMessengerTenantResolver extends EloquentMessengerTenantResolver
{
    protected function modelClass(): string
    {
        return (string) config('messenger-bot.tenancy.connection_model', '');
    }

    protected function facebookPageIdColumn(): string
    {
        $col = trim((string) config('messenger-bot.tenancy.connection_page_id_column', 'facebook_page_id'));

        return $col !== '' ? $col : 'facebook_page_id';
    }
}
