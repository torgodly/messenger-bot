<?php

namespace MessengerBot\Tests\Fixtures;

use MessengerBot\Contracts\ValidatesMessengerPageLink;

/**
 * @internal
 */
final class AllowAllPageLinkValidator implements ValidatesMessengerPageLink
{
    public function assertMayLinkPage(array $page, array $mt): void {}
}
