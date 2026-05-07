<?php

namespace MessengerBot\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MessengerBot\Contracts\PageAccessTokenRepository;
use MessengerBot\Http\GraphException;
use MessengerBot\Kernel\Contracts\ConnectionTokenRepository;
use MessengerBot\OAuth\FacebookOAuthClient;
use MessengerBot\OAuth\OAuthStateSigner;
use MessengerBot\Support\GraphContainerReset;

class FacebookOAuthController extends Controller
{
    public function __construct(
        protected FacebookOAuthClient $oauth,
        protected PageAccessTokenRepository $pageTokens,
        protected ConnectionTokenRepository $connectionTokens,
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

        try {
            $short = $this->oauth->exchangeCodeForUserAccessToken($code, $redirectUri);
            $shortUser = (string) ($short['access_token'] ?? '');
            if ($shortUser === '') {
                abort(500, 'Facebook did not return a user access token.');
            }

            $long = $this->oauth->exchangeLongLivedUserToken($shortUser);
            $longUser = (string) ($long['access_token'] ?? '');
            if ($longUser === '') {
                abort(500, 'Could not exchange for a long-lived user token.');
            }

            $pages = $this->oauth->fetchManagedPages($longUser);
            if ($pages === []) {
                abort(400, 'No Facebook Pages returned for this account. Grant Page permissions and ensure you manage at least one Page.');
            }

            $preferred = trim((string) config('messenger-bot.oauth.preferred_page_id', ''));
            $chosen = null;
            if ($preferred !== '') {
                foreach ($pages as $p) {
                    if ($p['id'] === $preferred) {
                        $chosen = $p;
                        break;
                    }
                }
                if ($chosen === null) {
                    abort(400, 'Preferred Page ID '.$preferred.' was not in the list of managed Pages. Check MESSENGER_BOT_OAUTH_PREFERRED_PAGE_ID.');
                }
            } else {
                $chosen = $pages[0];
                if (count($pages) > 1) {
                    Log::warning('Messenger OAuth: multiple Pages available; using the first. Set MESSENGER_BOT_OAUTH_PREFERRED_PAGE_ID.', [
                        'page_ids' => array_column($pages, 'id'),
                        'chosen' => $chosen['id'],
                    ]);
                }
            }

            $pageToken = $chosen['access_token'];
            $debug = $this->oauth->debugInputToken($pageToken);
            $expiresAt = $debug['expires_at'];

            $mt = isset($payload['mt']) && is_array($payload['mt']) ? $payload['mt'] : null;
            $dualWriteLegacy = (bool) config('messenger-bot.oauth.dual_write_legacy_token', true);

            $wroteConnection = false;
            if (is_array($mt)
                && isset($mt['tenant_id'], $mt['connection_id'])
                && is_string($mt['tenant_id'])
                && is_string($mt['connection_id'])
                && $mt['tenant_id'] !== ''
                && $mt['connection_id'] !== '') {
                $this->connectionTokens->put([
                    'access_token' => $pageToken,
                    'expires_at' => $expiresAt,
                    'page_id' => (string) $chosen['id'],
                    'tenant_id' => $mt['tenant_id'],
                    'connection_id' => $mt['connection_id'],
                ]);
                $wroteConnection = true;
            }

            if (! $wroteConnection || $dualWriteLegacy) {
                $this->pageTokens->put([
                    'access_token' => $pageToken,
                    'expires_at' => $expiresAt,
                    'page_id' => $chosen['id'],
                ]);
            }

            GraphContainerReset::forget(app());
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
