<?php

namespace MessengerBot\Support;

use MessengerBot\Contracts\MessengerConnectable;
use MessengerBot\Kernel\Tenancy\ConnectionId;
use MessengerBot\Kernel\Tenancy\TenantId;
use MessengerBot\Kernel\Tenancy\TenantResolution;

final class MessengerConnection
{
    public static function toResolution(MessengerConnectable $connectable): TenantResolution
    {
        return new TenantResolution(
            new TenantId($connectable->messengerTenantKey()),
            new ConnectionId($connectable->messengerConnectionKey()),
            $connectable->facebookPageId(),
        );
    }
}
