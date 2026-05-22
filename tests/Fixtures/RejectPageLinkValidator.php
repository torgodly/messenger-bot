<?php

namespace MessengerBot\Tests\Fixtures;

use MessengerBot\Contracts\ValidatesMessengerPageLink;
use MessengerBot\Exceptions\PageLinkRejectedException;

/**
 * @internal
 */
final class RejectPageLinkValidator implements ValidatesMessengerPageLink
{
    public function assertMayLinkPage(array $page, array $mt): void
    {
        throw new PageLinkRejectedException('This Page is already linked to another account.');
    }
}
