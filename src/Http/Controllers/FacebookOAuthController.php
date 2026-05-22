<?php

namespace MessengerBot\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MessengerBot\Exceptions\PageLinkRejectedException;
use MessengerBot\Http\GraphException;
use MessengerBot\OAuth\CompleteOAuthPageLink;
use MessengerBot\OAuth\ExchangeOAuthCodeForManagedPages;
use MessengerBot\OAuth\OAuthStateSigner;
use MessengerBot\OAuth\PendingOAuthPages;

class FacebookOAuthController extends Controller
{
    public function __construct(
        protected ExchangeOAuthCodeForManagedPages $exchangePages,
        protected CompleteOAuthPageLink $completePageLink,
    ) {}

    public function redirectToFacebook(Request $request): RedirectResponse
    {
        $appId = trim((string) config('messenger-bot.app_id', ''));
        if ($appId === '') {
            abort(500, 'Set MESSENGER_BOT_APP_ID to use Facebook OAuth.');
        }

        $redirectUri = $this->callbackUrl();
        $state = Str::random(40);

        $tenantId = trim((string) $request->query('tenant_id', ''));
        $connectionId = trim((string) $request->query('connection_id', ''));
        $mtSig = trim((string) $request->query('mt_sig', ''));

        $mt = null;
        if ($tenantId !== '' || $connectionId !== '') {
            if ($tenantId === '' || $connectionId === '') {
                abort(400, 'Multi-tenant OAuth requires both tenant_id and connection_id query parameters.');
            }

            $secret = trim((string) config('messenger-bot.app_secret', ''));
            $requireSig = (bool) config('messenger-bot.oauth.require_mt_signature', true);
            if ($requireSig) {
                if ($secret === '') {
                    abort(500, 'MESSENGER_BOT_APP_SECRET is required to verify mt_sig for multi-tenant OAuth.');
                }
                if (! OAuthStateSigner::verify($tenantId, $connectionId, $secret, $mtSig)) {
                    abort(403, 'Invalid or missing mt_sig for multi-tenant OAuth.');
                }
            }

            $mt = [
                'tenant_id' => $tenantId,
                'connection_id' => $connectionId,
            ];
        }

        Cache::put($this->stateCacheKey($state), [
            'redirect_uri' => $redirectUri,
            'issued_at' => time(),
            'mt' => $mt,
        ], now()->addMinutes(10));

        $scopes = (array) config('messenger-bot.oauth.scopes', []);
        $scopeString = implode(',', array_filter(array_map('trim', $scopes)));

        $version = (string) config('messenger-bot.graph_version', 'v24.0');
        $dialog = 'https://www.facebook.com/'.$version.'/dialog/oauth?'.http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scopeString,
            'response_type' => 'code',
        ]);

        return redirect()->away($dialog);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            Log::warning('Messenger OAuth error from Facebook.', [
                'error' => $request->query('error'),
                'description' => $request->query('error_description'),
            ]);
            abort(400, 'Facebook OAuth was denied or failed.');
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        if ($code === '' || $state === '') {
            abort(400, 'Missing OAuth code or state.');
        }

        $payload = Cache::pull($this->stateCacheKey($state));
        if (! is_array($payload) || empty($payload['redirect_uri'])) {
            abort(400, 'Invalid or expired OAuth state. Start again from the connect URL.');
        }

        $redirectUri = (string) $payload['redirect_uri'];
        $mt = isset($payload['mt']) && is_array($payload['mt']) ? $payload['mt'] : null;

        try {
            $result = $this->exchangePages->exchange($code, $redirectUri, $mt);
            $pages = $result['pages'];
            $mt = $result['mt'];

            if ($pages === []) {
                abort(400, 'No Facebook Pages returned for this account. Grant Page permissions and ensure you manage at least one Page.');
            }

            $preferred = trim((string) config('messenger-bot.oauth.preferred_page_id', ''));
            if ($preferred !== '') {
                $matching = array_values(array_filter($pages, fn (array $p): bool => ($p['id'] ?? '') === $preferred));
                if (count($matching) === 1) {
                    return $this->completeAndRedirectSuccess($matching[0], $mt);
                }
            }

            if (count($pages) === 1) {
                return $this->completeAndRedirectSuccess($pages[0], $mt);
            }

            return $this->redirectToPendingPagesPicker($pages, $mt);
        } catch (PageLinkRejectedException $e) {
            return $this->redirectWithOAuthError($e->getMessage());
        } catch (HttpException $e) {
            throw $e;
        } catch (GraphException $e) {
            Log::error('Messenger OAuth callback failed.', [
                'exception' => $e,
                'status' => $e->statusCode,
            ]);
            abort(500, 'OAuth failed. Check application logs.');
        } catch (\Throwable $e) {
            Log::error('Messenger OAuth callback failed.', [
                'exception' => $e,
            ]);
            abort(500, 'OAuth failed. Check application logs.');
        }
    }

    /**
     * @param  array{id: string, name: string, access_token: string}  $page
     * @param  array{tenant_id: string, connection_id: string}|null  $mt
     */
    protected function completeAndRedirectSuccess(array $page, ?array $mt): RedirectResponse
    {
        $this->completePageLink->complete($page, $mt);

        return redirect()->to($this->successPath());
    }

    /**
     * @param  list<array{id: string, name: string, access_token: string}>  $pages
     * @param  array{tenant_id: string, connection_id: string}|null  $mt
     */
    protected function redirectToPendingPagesPicker(array $pages, ?array $mt): RedirectResponse
    {
        $baseUrl = trim((string) config('messenger-bot.oauth.pending_pages_redirect_url', ''));
        if ($baseUrl === '') {
            abort(500, 'Set MESSENGER_BOT_OAUTH_PENDING_PAGES_URL for multi-Page OAuth (host Page picker).');
        }

        $token = PendingOAuthPages::store($pages, $mt);

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return redirect()->away($baseUrl.$separator.http_build_query(['token' => $token]));
    }

    protected function redirectWithOAuthError(string $message): RedirectResponse
    {
        session()->flash('messenger_bot_oauth_error', $message);

        return redirect()->to($this->successPath());
    }

    protected function callbackUrl(): string
    {
        $configured = config('messenger-bot.oauth.redirect_uri');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return route('messenger-bot.oauth.callback', [], true);
    }

    protected function successPath(): string
    {
        $path = (string) config('messenger-bot.oauth.success_redirect_path', '/');
        if ($path === '' || ! str_starts_with($path, '/')) {
            return '/';
        }

        return $path;
    }

    protected function stateCacheKey(string $state): string
    {
        return 'messenger_bot:oauth_state:'.$state;
    }
}
