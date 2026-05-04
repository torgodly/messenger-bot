<?php

namespace MessengerBot\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MessengerBot\Webhook\WebhookProcessor;
use MessengerBot\Webhook\WebhookVerifier;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebhookController
{
    public function handle(
        Request $request,
        WebhookVerifier $verifier,
        WebhookProcessor $processor,
    ): Response {
        if ($request->isMethod('GET')) {
            $challenge = $verifier->verifyChallenge($request);

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            throw new AccessDeniedHttpException('Invalid JSON payload.');
        }

        try {
            $processor->process($payload);
        } catch (\Throwable $e) {
            report($e);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
