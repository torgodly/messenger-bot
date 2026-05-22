<?php

namespace MessengerBot\Exceptions;

use RuntimeException;

/**
 * Thrown when {@see ValidatesMessengerPageLink} rejects a Page link (host maps to user-facing OAuth error).
 */
final class PageLinkRejectedException extends RuntimeException {}
