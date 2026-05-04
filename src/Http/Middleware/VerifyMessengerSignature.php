<?php

namespace MessengerBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MessengerBot\Webhook\SignatureValidator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyMessengerSignature
{
    protected static bool $loggedMissingSecret = false;

    public function __construct(
        protected SignatureValidator $validator,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('messenger-bot.webhook.signature_enabled', true)) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $secret = (string) config('messenger-bot.app_secret', '');
        if ($secret === '') {
            if (! self::$loggedMissingSecret) {
                self::$loggedMissingSecret = true;
                Log::warning('messenger-bot: signature verification is enabled but MESSENGER_BOT_APP_SECRET is empty; skipping X-Hub-Signature-256 check. Set the App Secret from Meta → App Settings → Basic, or set MESSENGER_BOT_SIGNATURE_ENABLED=false for local testing only.');
            }

            return $next($request);
        }

        $raw = $request->getContent();
        $sig = $request->headers->get('X-Hub-Signature-256');

        if (! $this->validator->isValid($raw, $sig)) {
            throw new AccessDeniedHttpException('Invalid webhook signature (check MESSENGER_BOT_APP_SECRET matches Meta App Dashboard and request body is not modified by a proxy).');
        }

        return $next($request);
    }
}
