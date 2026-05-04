<?php

namespace MessengerBot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class EnsureJsonMessengerWebhook
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $max = (int) config('messenger-bot.webhook.max_body_bytes', 262144);
        $cl = $request->headers->get('Content-Length');
        if ($cl !== null && (int) $cl > $max) {
            abort(413);
        }

        $raw = $request->getContent();
        if (strlen($raw) > $max) {
            abort(413);
        }

        $ct = (string) $request->header('Content-Type', '');
        if ($raw !== '' && $ct !== '' && ! str_contains(strtolower($ct), 'json')) {
            throw new UnsupportedMediaTypeHttpException('Expected application/json.');
        }

        return $next($request);
    }
}
